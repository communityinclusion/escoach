<?php

namespace Drupal\Tests\memcache\Kernel;

use Drupal\Core\Site\Settings;
use Drupal\KernelTests\KernelTestBase;
use Drupal\memcache\Driver\MemcacheDriverFactory;
use Drupal\memcache\Invalidator\KeyValueTimestampInvalidator;
use Drupal\memcache\Invalidator\MemcacheTimestampInvalidator;
use Drupal\memcache\Invalidator\TimestampInvalidatorInterface;
use Drupal\memcache\MemcacheBackend;
use Drupal\memcache\MemcacheSettings;

/**
 * Tests bin deletion across isolated memcached instances.
 *
 * Each web server running its own private memcached is emulated with a
 * driver factory of its own, using a distinct key prefix: two prefixes on one
 * memcached daemon are as invisible to each other as two daemons are.
 *
 * @group memcache
 *
 * @see https://www.drupal.org/project/memcache/issues/3259354
 */
class MemcacheKeyValueTimestampInvalidatorTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'memcache'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    KeyValueTimestampInvalidator::resetTimestamps();
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    KeyValueTimestampInvalidator::resetTimestamps();
    parent::tearDown();
  }

  /**
   * Builds a cache backend for the given emulated web server.
   *
   * @param string $server
   *   A name for the emulated web server; its private memcached is emulated
   *   by using the name as part of the key prefix.
   * @param \Drupal\memcache\Invalidator\TimestampInvalidatorInterface $invalidator
   *   The bin timestamp invalidator the server uses.
   * @param string $bin
   *   The cache bin.
   *
   * @return \Drupal\memcache\MemcacheBackend
   *   The backend, as a fresh request on that server would build it.
   */
  protected function backendFor($server, TimestampInvalidatorInterface $invalidator, $bin = 'default') {
    $backend = new MemcacheBackend(
      $bin,
      $this->factoryFor($server)->get($bin),
      $this->container->get('cache_tags.invalidator.checksum'),
      $invalidator
    );
    // A new backend marks its bin as deleted "now" if it has no deletion time
    // yet; give the clock a moment so the items set below are newer than that.
    usleep(10000);

    return $backend;
  }

  /**
   * Builds a driver factory whose keys are private to the given server.
   *
   * @param string $server
   *   The emulated web server name.
   *
   * @return \Drupal\memcache\Driver\MemcacheDriverFactory
   *   The factory.
   */
  protected function factoryFor($server) {
    $host = getenv('MEMCACHED_HOST') ?: '127.0.0.1:11211';
    $this->setSetting('memcache', [
      'servers' => [$host => 'default'],
      'bins' => ['default' => 'default'],
      'key_prefix' => $this->databasePrefix . '_' . $server,
    ]);

    return new MemcacheDriverFactory(new MemcacheSettings(Settings::getInstance()));
  }

  /**
   * Builds a memcache-backed bin invalidator private to the given server.
   *
   * @param string $server
   *   The emulated web server name.
   *
   * @return \Drupal\memcache\Invalidator\MemcacheTimestampInvalidator
   *   The invalidator.
   */
  protected function memcacheInvalidatorFor($server) {
    return new MemcacheTimestampInvalidator($this->factoryFor($server), 'memcache_bin_timestamps', 0);
  }

  /**
   * Builds a key-value backed bin invalidator, shared through the database.
   *
   * @return \Drupal\memcache\Invalidator\KeyValueTimestampInvalidator
   *   The invalidator.
   */
  protected function keyValueInvalidator() {
    return new KeyValueTimestampInvalidator($this->container->get('keyvalue.database'), 'memcache_bin_timestamps', 0);
  }

  /**
   * Emulates the start of a new request on another web server.
   *
   * A fresh request instantiates a fresh invalidator that reads the shared
   * store again; in-process that means dropping what this process read.
   */
  protected function newRequest() {
    KeyValueTimestampInvalidator::resetTimestamps();
  }

  /**
   * Demonstrates the reported problem with the default invalidator.
   *
   * With the bin deletion timestamp kept in memcache, a deleteAll() on one
   * server is invisible to a server with its own memcached.
   */
  public function testMemcacheInvalidatorIsPrivateToEachServer() {
    $first = $this->backendFor('first', $this->memcacheInvalidatorFor('first'));
    $second = $this->backendFor('second', $this->memcacheInvalidatorFor('second'));
    $first->set('item', 'value');
    $second->set('item', 'value');
    $this->assertSame('value', $first->get('item')->data);
    $this->assertSame('value', $second->get('item')->data);

    $first->deleteAll();

    $this->assertFalse($first->get('item'));
    $second = $this->backendFor('second', $this->memcacheInvalidatorFor('second'));
    $this->assertSame('value', $second->get('item')->data, 'The second server still serves the item deleted on the first.');
  }

  /**
   * Tests that a bin deletion on one server reaches the others.
   */
  public function testBinDeletionIsSharedThroughKeyValue() {
    $first = $this->backendFor('first', $this->keyValueInvalidator());
    $second = $this->backendFor('second', $this->keyValueInvalidator());
    $first->set('item', 'value');
    $second->set('item', 'value');
    $this->assertSame('value', $first->get('item')->data);
    $this->assertSame('value', $second->get('item')->data);

    $first->deleteAll();
    $this->assertFalse($first->get('item'));

    // The next request on the second server picks the deletion up.
    $this->newRequest();
    $second = $this->backendFor('second', $this->keyValueInvalidator());
    $this->assertFalse($second->get('item'), 'The second server no longer serves the item deleted on the first.');

    // Items set after the deletion are served again on both servers.
    $first->set('item', 'new');
    $second->set('item', 'new');
    $this->assertSame('new', $first->get('item')->data);
    $this->assertSame('new', $second->get('item')->data);

    // The timestamp lives in the database, keyed by bin.
    $stored = $this->container->get('keyvalue.database')->get('memcache_bin_timestamps')->get('default');
    $this->assertIsFloat($stored);
    $this->assertSame($stored, $this->keyValueInvalidator()->getLastInvalidationTimestamp('default'));
  }

  /**
   * Tests that removing a bin on one server reaches the others too.
   */
  public function testRemoveBinIsSharedThroughKeyValue() {
    $first = $this->backendFor('first', $this->keyValueInvalidator());
    $second = $this->backendFor('second', $this->keyValueInvalidator());
    $first->set('item', 'value');
    $second->set('item', 'value');

    $first->removeBin();

    $this->newRequest();
    $second = $this->backendFor('second', $this->keyValueInvalidator());
    $this->assertFalse($second->get('item'));
  }

  /**
   * Tests the invalidator against the interface on its own.
   */
  public function testInvalidator() {
    $invalidator = $this->keyValueInvalidator();
    $this->assertFalse($invalidator->getLastInvalidationTimestamp('default'));
    $this->assertSame([], $invalidator->getLastInvalidationTimestamps(['default', 'render']));

    $timestamp = $invalidator->invalidateTimestamp('default');
    $this->assertIsFloat($timestamp);
    $this->assertSame($timestamp, $invalidator->getLastInvalidationTimestamp('default'));
    $this->assertSame(['default' => $timestamp], $invalidator->getLastInvalidationTimestamps(['default', 'render']));

    // Invalidating again moves the timestamp forward, never backwards.
    usleep(2000);
    $later = $invalidator->invalidateTimestamp('default');
    $this->assertGreaterThan($timestamp, $later);

    // A second instance in the same process shares what was read, and a new
    // process reads the same values from the store.
    $this->assertSame($later, $this->keyValueInvalidator()->getLastInvalidationTimestamp('default'));
    $this->newRequest();
    $this->assertSame($later, $this->keyValueInvalidator()->getLastInvalidationTimestamp('default'));

    // Collections are independent.
    $other = new KeyValueTimestampInvalidator($this->container->get('keyvalue.database'), 'other_collection', 0);
    $this->assertFalse($other->getLastInvalidationTimestamp('default'));
    $this->assertSame($later, $invalidator->getLastInvalidationTimestamp('default'));
  }

  /**
   * Tests that the service is usable as an alias target from a site override.
   */
  public function testServiceDefinition() {
    $invalidator = $this->container->get('memcache.timestamp.invalidator.bin.key_value');
    $this->assertInstanceOf(KeyValueTimestampInvalidator::class, $invalidator);
    $timestamp = $invalidator->invalidateTimestamp('default');
    $this->assertSame($timestamp, $this->container->get('keyvalue.database')->get('memcache_bin_timestamps')->get('default'));
  }

}
