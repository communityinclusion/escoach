<?php

namespace Drupal\Tests\memcache\Kernel;

use Drupal\Core\Site\Settings;
use Drupal\KernelTests\KernelTestBase;
use Drupal\memcache\Driver\MemcacheDriverFactory;
use Drupal\memcache\MemcacheSettings;

/**
 * Tests that the key_prefix setting is part of every key sent to memcache.
 *
 * Several Drupal installations sharing one memcached pool rely on
 * $settings['memcache']['key_prefix'] to keep their items apart. The setting
 * was once read but never used, so the sites served each other's cache items,
 * and the hashed form of an over-long key later dropped the prefix again.
 * Both are asserted here against a real server, so that the items really are
 * stored under the prefixed key and not only reported as such.
 *
 * @see https://www.drupal.org/project/memcache/issues/2701903
 *
 * @group memcache
 */
class MemcacheKeyPrefixTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'memcache'];

  /**
   * Returns the address of the memcache server used by the test run.
   *
   * @return string
   *   A reachable server address.
   */
  protected function server() {
    return getenv('MEMCACHED_HOST') ?: '127.0.0.1:11211';
  }

  /**
   * Returns a prefix that no other test run shares.
   *
   * @param string $suffix
   *   Distinguishes several prefixes within one test.
   *
   * @return string
   *   The prefix.
   */
  protected function prefix($suffix) {
    return $this->databasePrefix . '_' . $suffix;
  }

  /**
   * Builds a driver for the given key prefix and bin.
   *
   * @param string $prefix
   *   The value of the key_prefix setting; an empty string disables it.
   * @param string $bin
   *   The bin the driver is for.
   * @param array $settings
   *   Further memcache settings.
   *
   * @return \Drupal\memcache\Driver\DriverBase
   *   The driver.
   */
  protected function driverFor($prefix, $bin = 'default', array $settings = []) {
    $this->setSetting('memcache', $settings + [
      'servers' => [$this->server() => 'default'],
      'bins' => ['default' => 'default'],
      'key_prefix' => $prefix,
    ]);

    $factory = new MemcacheDriverFactory(new MemcacheSettings(Settings::getInstance()));

    return $factory->get($bin);
  }

  /**
   * Reads an item straight from the server, bypassing the driver.
   *
   * @param string $full_key
   *   The key exactly as it is stored on the server.
   *
   * @return mixed
   *   The item, or FALSE if the key is not stored.
   */
  protected function readRaw($full_key) {
    [$host, $port] = explode(':', $this->server());

    $memcache = new \Memcached();
    $memcache->setOption(\Memcached::OPT_COMPRESSION, TRUE);
    $memcache->addServer($host, (int) $port);
    $value = $memcache->get($full_key);
    $memcache->quit();

    return $value;
  }

  /**
   * Tests that the prefix and the bin lead every key.
   */
  public function testPrefixAndBinLeadTheKey() {
    $this->assertSame(urlencode('site_one:render:-the_cid'), $this->driverFor('site_one', 'render')->key('the_cid'));
    $this->assertSame(urlencode('site_one:default:-the_cid'), $this->driverFor('site_one')->key('the_cid'));
    // Without a prefix the bin alone namespaces the key.
    $this->assertSame(urlencode('render:-the_cid'), $this->driverFor('', 'render')->key('the_cid'));
  }

  /**
   * Tests that sites with different prefixes do not see each other's items.
   */
  public function testPrefixesKeepSitesApart() {
    $cid = 'shared_cid';
    $one = $this->driverFor($this->prefix('one'));
    $two = $this->driverFor($this->prefix('two'));
    $none = $this->driverFor('');

    $this->assertNotSame($one->key($cid), $two->key($cid));
    $this->assertNotSame($one->key($cid), $none->key($cid));

    $this->assertTrue($one->set($cid, 'from one'));
    $this->assertSame('from one', $one->get($cid));
    $this->assertFalse($two->get($cid), 'The other site does not see the item.');
    $this->assertFalse($none->get($cid), 'A site without a prefix does not see the item.');

    // The item is stored under the prefixed key, not merely reported so.
    $this->assertSame('from one', $this->readRaw($one->key($cid)));
    $this->assertFalse($this->readRaw($two->key($cid)));

    // Writing the same cid from the other site leaves the first one intact.
    $this->assertTrue($two->set($cid, 'from two'));
    $this->assertSame('from one', $one->get($cid));
    $this->assertSame('from two', $two->get($cid));

    $this->assertSame(['shared_cid' => 'from one'], $one->getMulti([$cid, 'missing_cid']));

    $one->delete($cid);
    $this->assertFalse($one->get($cid));
    $this->assertSame('from two', $two->get($cid), 'Deleting on one site does not touch the other.');
    $two->delete($cid);
  }

  /**
   * Tests that a key hashed for length keeps its prefix.
   *
   * Memcached rejects keys over 250 bytes, so such a key is replaced by a hash
   * of the cid. The hash must still carry the prefix, or every site sharing
   * the pool collides on its long keys.
   */
  public function testPrefixSurvivesHashing() {
    $cid = str_repeat('a_long_cid_', 30);
    $one = $this->driverFor($this->prefix('one'), 'render');
    $two = $this->driverFor($this->prefix('two'), 'render');

    $this->assertGreaterThan(250, strlen(urlencode($cid)), 'The cid alone is already too long for memcached.');

    $key = $one->key($cid);
    $this->assertLessThanOrEqual(250, strlen($key));
    $this->assertStringStartsWith(urlencode($this->prefix('one') . ':render:-' . hash('sha1', $cid)) . '-', $key);
    $this->assertNotSame($key, $two->key($cid));
    $this->assertStringStartsWith(urlencode($this->prefix('two') . ':render:-'), $two->key($cid));

    $this->assertTrue($one->set($cid, 'from one'));
    $this->assertSame('from one', $one->get($cid));
    $this->assertFalse($two->get($cid), 'The other site does not see the item.');
    $this->assertSame('from one', $this->readRaw($key));

    $one->delete($cid);
    $this->assertFalse($one->get($cid));
  }

  /**
   * Tests that the configured hash algorithm is used for long keys.
   */
  public function testHashAlgorithmSetting() {
    $cid = str_repeat('a_long_cid_', 30);
    $driver = $this->driverFor('site_one', 'render', ['key_hash_algorithm' => 'md5']);

    $key = $driver->key($cid);
    $this->assertLessThanOrEqual(250, strlen($key));
    $this->assertStringStartsWith(urlencode('site_one:render:-' . hash('md5', $cid)) . '-', $key);
  }

}
