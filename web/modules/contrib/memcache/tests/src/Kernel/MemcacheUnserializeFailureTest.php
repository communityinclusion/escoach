<?php

namespace Drupal\Tests\memcache\Kernel;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceModifierInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\memcache\MemcacheBackendFactory;
use Drupal\Tests\memcache\Kernel\Fixtures\ThrowingOnWakeup;

/**
 * Tests that unreadable cache items do not break the rest of the cache.
 *
 * An object cached by a module that has since been uninstalled throws while
 * being unserialized, which aborts the entire multi-get it happens to be part
 * of. Before this was handled the only remedy was flushing memcache by hand.
 *
 * @group memcache
 *
 * @see https://www.drupal.org/project/memcache/issues/3096216
 */
class MemcacheUnserializeFailureTest extends KernelTestBase implements ServiceModifierInterface {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'memcache'];

  /**
   * The cache backend under test.
   *
   * @var \Drupal\memcache\MemcacheBackend
   */
  protected $backend;

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container) {
    // Remove the timestamp tolerance so items written immediately after a bin
    // deletion are still considered fresh.
    $container->getDefinition('memcache.timestamp.invalidator.bin')->setArgument(2, 0);
  }

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();

    $host = getenv('MEMCACHED_HOST') ?: '127.0.0.1:11211';
    $this->setSetting('memcache', [
      'servers' => [$host => 'default'],
      'bin' => ['default' => 'default'],
    ]);

    $factory = new MemcacheBackendFactory(
      $this->container->get('memcache.factory'),
      $this->container->get('cache_tags.invalidator.checksum'),
      $this->container->get('memcache.timestamp.invalidator.bin'),
    );
    $this->backend = $factory->get('unserialize_failure');
    $this->backend->deleteAll();
    usleep(10000);
  }

  /**
   * Tests that one unreadable item does not hide the readable ones.
   */
  public function testUnreadableItemDoesNotBreakMultiGet() {
    $this->backend->set('before', 'first value');
    $this->backend->set('unreadable', new ThrowingOnWakeup());
    $this->backend->set('after', 'second value');

    $cids = ['before', 'unreadable', 'after'];
    $cache = $this->backend->getMultiple($cids);

    $this->assertArrayHasKey('before', $cache);
    $this->assertArrayHasKey('after', $cache);
    $this->assertSame('first value', $cache['before']->data);
    $this->assertSame('second value', $cache['after']->data);

    // The unreadable item is reported as a miss so that it gets rebuilt.
    $this->assertArrayNotHasKey('unreadable', $cache);
    $this->assertSame(['unreadable'], array_values($cids));
  }

  /**
   * Tests that an unreadable item is removed instead of poisoning every read.
   */
  public function testUnreadableItemIsDeleted() {
    $this->backend->set('unreadable', new ThrowingOnWakeup());
    $this->backend->set('readable', 'a value');

    $cids = ['unreadable', 'readable'];
    $this->backend->getMultiple($cids);

    // The item is gone, so the second read never has to unserialize it again.
    $this->assertFalse($this->backend->get('unreadable'));
    $this->assertSame('a value', $this->backend->get('readable')->data);
  }

  /**
   * Tests that a single get of an unreadable item is a miss, not a fatal.
   */
  public function testGetOfUnreadableItem() {
    $this->backend->set('unreadable', new ThrowingOnWakeup());

    $this->assertFalse($this->backend->get('unreadable'));
  }

}
