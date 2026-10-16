<?php

namespace Drupal\Tests\memcache\Kernel;

use Drupal\Core\Site\Settings;
use Drupal\KernelTests\KernelTestBase;
use Drupal\memcache\Connection\ServerAddress;
use Drupal\memcache\Driver\MemcacheDriverFactory;
use Drupal\memcache\MemcacheSettings;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests how a cluster of several memcache servers behaves.
 *
 * Multi-server clusters shard: a key lives on exactly one server of the
 * cluster, and every server of the cluster has to be in the pool for that to
 * work. Both halves of that are asserted here, because a cluster that silently
 * collapsed onto a single server looks like a working site until the cache
 * pool turns out to be a fraction of its configured size.
 *
 * Requires at least two reachable memcached servers, listed in the
 * MEMCACHED_HOSTS environment variable as a comma separated list of
 * host:port addresses.
 *
 * @group memcache
 */
#[RunTestsInSeparateProcesses]
class MemcacheMultiServerTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'memcache'];

  /**
   * The reachable servers used by this test.
   *
   * @var string[]
   */
  protected $servers = [];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $hosts = getenv('MEMCACHED_HOSTS');
    $this->servers = array_values(array_filter(array_map('trim', explode(',', (string) $hosts))));

    if (count($this->servers) < 2) {
      $this->markTestSkipped('Set MEMCACHED_HOSTS to two or more "host:port" addresses to run the multi-server tests.');
    }
  }

  /**
   * Builds a driver factory for the given server and bin configuration.
   *
   * @param array $servers
   *   The cluster name, keyed by server address.
   * @param array $bins
   *   The cluster name, keyed by bin name.
   *
   * @return \Drupal\memcache\Driver\MemcacheDriverFactory
   *   The configured factory.
   */
  protected function factoryFor(array $servers, array $bins = ['default' => 'default']) {
    $this->setSetting('memcache', [
      'servers' => $servers,
      'bins' => $bins,
      'key_prefix' => $this->databasePrefix,
    ]);

    return new MemcacheDriverFactory(new MemcacheSettings(Settings::getInstance()));
  }

  /**
   * Returns the servers of a cluster, keyed by address.
   *
   * @param array $servers
   *   The server addresses.
   * @param string $cluster
   *   The cluster to assign them to.
   *
   * @return string[]
   *   The cluster name, keyed by server address.
   */
  protected function cluster(array $servers, $cluster = 'default') {
    return array_fill_keys($servers, $cluster);
  }

  /**
   * Returns the addresses in a pool, as reported by the extension.
   *
   * @param \Drupal\memcache\Driver\DriverBase $driver
   *   The driver whose pool to inspect.
   *
   * @return string[]
   *   The "host:port" addresses, sorted.
   */
  protected function poolAddresses($driver) {
    $addresses = [];
    foreach ($driver->getMemcache()->getServerList() as $server) {
      $addresses[] = $server['host'] . ':' . $server['port'];
    }
    sort($addresses);

    return $addresses;
  }

  /**
   * Returns the servers that hold a key.
   *
   * Each server is queried on its own connection, so that the answer is where
   * the item physically is rather than where the pool would look for it.
   *
   * @param string $full_key
   *   The prefixed key, as sent to memcache.
   * @param string[] $servers
   *   The server addresses to query.
   *
   * @return string[]
   *   The addresses of the servers that returned the key.
   */
  protected function serversHoldingKey($full_key, array $servers) {
    $holders = [];

    foreach ($servers as $server) {
      [$host, $port] = ServerAddress::parse($server);

      $memcache = new \Memcached();
      $memcache->setOption(\Memcached::OPT_COMPRESSION, TRUE);
      $memcache->addServer($host, $port);
      $memcache->get($full_key);

      if ($memcache->getResultCode() === \Memcached::RES_SUCCESS) {
        $holders[] = $server;
      }

      $memcache->quit();
    }

    return $holders;
  }

  /**
   * Tests that every server configured for a cluster joins its pool.
   */
  public function testAllServersJoinTheCluster() {
    $factory = $this->factoryFor($this->cluster($this->servers));
    $driver = $factory->get('default');

    $expected = $this->servers;
    sort($expected);

    $this->assertSame($expected, $this->poolAddresses($driver));
  }

  /**
   * Tests that a cluster shards its keys instead of replicating them.
   */
  public function testKeysAreShardedNotReplicated() {
    $factory = $this->factoryFor($this->cluster($this->servers));
    $driver = $factory->get('default');

    $used = [];
    for ($i = 0; $i < 100; $i++) {
      $cid = 'multi_server_' . $i;
      $this->assertTrue($driver->set($cid, $i), sprintf('Wrote %s.', $cid));

      $holders = $this->serversHoldingKey($driver->key($cid), $this->servers);
      $this->assertCount(1, $holders, sprintf('%s is stored on exactly one server.', $cid));

      $used[reset($holders)] = TRUE;
      // Whichever server holds it, the pool reads it back.
      $this->assertSame($i, $driver->get($cid), sprintf('Read %s back through the cluster.', $cid));
    }

    $this->assertGreaterThan(1, count($used), 'Keys are spread over more than one server of the cluster.');
  }

  /**
   * Tests that bins mapped to different clusters use different pools.
   */
  public function testClustersAreSeparatePools() {
    [$first, $second] = $this->servers;

    $factory = $this->factoryFor(
      [$first => 'default', $second => 'cluster1'],
      ['default' => 'default', 'bin1' => 'cluster1']
    );

    $this->assertSame([$first], $this->poolAddresses($factory->get('default')));
    $this->assertSame([$second], $this->poolAddresses($factory->get('bin1')));
  }

  /**
   * Tests that bins sharing a cluster share its pool.
   */
  public function testBinsShareTheirClusterPool() {
    $factory = $this->factoryFor(
      $this->cluster($this->servers),
      ['default' => 'default', 'bin1' => 'default']
    );

    $expected = $this->servers;
    sort($expected);

    $this->assertSame($expected, $this->poolAddresses($factory->get('bin1')));
  }

  /**
   * Tests that aggregate statistics sum the servers of a cluster.
   */
  public function testAggregateStats() {
    $factory = $this->factoryFor($this->cluster($this->servers));
    $driver = $factory->get('default');
    $driver->set('multi_server_stats', 'value');

    $stats = $driver->stats('default', 'default', TRUE);
    $servers = $stats['default'];
    $this->assertArrayHasKey('total', $servers);
    $total = $servers['total'];
    unset($servers['total']);
    $this->assertCount(count($this->servers), $servers);

    // Counters add up across the cluster.
    $this->assertSame(array_sum(array_column($servers, 'curr_items')), $total['curr_items']);
    $this->assertSame(array_sum(array_column($servers, 'cmd_set')), $total['cmd_set']);

    // Properties of a server are not sums of anything: they are reported once
    // when every server has the same value, and left out when they differ.
    // Both are possible here, e.g. containers all running memcached as pid 1.
    foreach (['pid', 'version'] as $property) {
      $values = array_unique(array_column($servers, $property));
      if (count($values) === 1) {
        $this->assertSame(reset($values), $total[$property], $property);
      }
      else {
        $this->assertArrayNotHasKey($property, $total, $property);
      }
    }
  }

}
