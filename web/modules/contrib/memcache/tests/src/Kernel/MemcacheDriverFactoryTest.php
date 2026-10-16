<?php

namespace Drupal\Tests\memcache\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\memcache\Driver\MemcacheDriverFactory;
use Drupal\memcache\MemcacheException;
use Drupal\memcache\MemcacheSettings;

/**
 * Tests bin to cluster mapping in the memcache driver factory.
 *
 * @group memcache
 *
 * @coversDefaultClass \Drupal\memcache\Driver\MemcacheDriverFactory
 */
class MemcacheDriverFactoryTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['memcache'];

  /**
   * The memcache server to connect to.
   *
   * @var string
   */
  protected $host;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->host = getenv('MEMCACHED_HOST') ?: '127.0.0.1:11211';
  }

  /**
   * Returns a driver factory configured with the given memcache settings.
   *
   * @param array $settings
   *   The value for the 'memcache' site setting.
   *
   * @return \Drupal\memcache\Driver\MemcacheDriverFactory
   *   The driver factory.
   */
  protected function getFactory(array $settings) {
    $this->setSetting('memcache', $settings);

    return new MemcacheDriverFactory(new MemcacheSettings($this->container->get('settings')));
  }

  /**
   * Tests that an unmapped bin follows the cluster of the 'default' bin.
   *
   * @covers ::get
   * @covers ::resolveDefaultCluster
   */
  public function testUnmappedBinFollowsDefaultBinCluster() {
    $factory = $this->getFactory([
      'servers' => [$this->host => 'cluster_default'],
      'bins' => ['default' => 'cluster_default'],
    ]);

    // 'memcache_bin_timestamps' is not listed in 'bins' and no server is
    // assigned to a cluster literally named 'default'.
    $driver = $factory->get('memcache_bin_timestamps');
    $this->assertInstanceOf('Drupal\memcache\DrupalMemcacheInterface', $driver);

    // Both bins are served by the same cluster, so they share a connection.
    $this->assertSame($driver->getMemcache(), $factory->get('default')->getMemcache());
  }

  /**
   * Tests that an unmapped bin falls back to the only configured cluster.
   *
   * @covers ::get
   * @covers ::resolveDefaultCluster
   */
  public function testUnmappedBinFallsBackToSoleCluster() {
    $factory = $this->getFactory([
      'servers' => [$this->host => 'cluster1'],
      'bins' => ['render' => 'cluster1'],
    ]);

    $this->assertInstanceOf('Drupal\memcache\DrupalMemcacheInterface', $factory->get('memcache_bin_timestamps'));
  }

  /**
   * Tests that the 'default' cluster still wins when it has servers.
   *
   * @covers ::resolveDefaultCluster
   */
  public function testDefaultClusterTakesPrecedence() {
    $factory = $this->getFactory([
      'servers' => [
        $this->host => 'default',
        // A cluster that points at nothing usable, to prove unmapped bins are
        // not sent there.
        '127.0.0.1:1' => 'cluster1',
      ],
      'bins' => ['render' => 'cluster1'],
    ]);

    $this->assertSame(
      $factory->get('default')->getMemcache(),
      $factory->get('memcache_bin_timestamps')->getMemcache()
    );
  }

  /**
   * Tests that bins mapped to the same cluster share a single connection.
   *
   * @covers ::get
   */
  public function testBinsShareClusterConnection() {
    $factory = $this->getFactory([
      'servers' => [$this->host => 'default'],
      'bins' => ['default' => 'default', 'render' => 'default'],
    ]);

    $memcache = $factory->get('render')->getMemcache();
    $this->assertSame($memcache, $factory->get('default')->getMemcache());
    $this->assertSame($memcache, $factory->get('an_unmapped_bin')->getMemcache());
  }

  /**
   * Tests that a bin mapped to a cluster without servers still fails loudly.
   *
   * @covers ::get
   */
  public function testUnknownClusterThrows() {
    $factory = $this->getFactory([
      'servers' => [$this->host => 'default'],
      'bins' => ['render' => 'nonexistent_cluster'],
    ]);

    $this->expectException(MemcacheException::class);
    $factory->get('render');
  }

}
