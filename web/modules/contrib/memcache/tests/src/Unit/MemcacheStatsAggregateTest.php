<?php

namespace Drupal\Tests\memcache\Unit;

use Drupal\Core\Site\Settings;
use Drupal\memcache\Driver\MemcachedDriver;
use Drupal\memcache\MemcacheSettings;
use Drupal\Tests\UnitTestCase;

/**
 * Tests how the statistics of several servers are summed into totals.
 *
 * The "slabs" and "items" statistics of the Memcache extension mix counters
 * that add up across servers (pages, chunks, hits) with properties of a slab
 * class that do not (its chunk size). The fixtures below are shaped like the
 * output of the extension so that the keys under test are the real ones.
 *
 * @group memcache
 */
class MemcacheStatsAggregateTest extends UnitTestCase {

  /**
   * A driver exposing the aggregation for testing.
   *
   * @var \Drupal\memcache\Driver\MemcachedDriver
   */
  protected $driver;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    if (!class_exists(\Memcached::class)) {
      $this->markTestSkipped('The memcached extension is not available.');
    }

    $settings = new MemcacheSettings(new Settings([
      'memcache' => ['servers' => ['127.0.0.2:12345' => 'default']],
      'hash_salt' => $this->randomMachineName(),
    ]));

    $this->driver = new class($settings, new \Memcached()) extends MemcachedDriver {

      /**
       * Exposes the protected aggregation.
       */
      public function aggregate(array $servers) {
        return $this->aggregateStats($servers);
      }

    };
  }

  /**
   * Tests that slab class properties are not summed across servers.
   */
  public function testSlabPropertiesAreNotSummed() {
    $total = $this->driver->aggregate([
      'memcache:11211' => [
        'active_slabs' => 2,
        'total_malloced' => 2097152,
        '2:chunk_size' => 120,
        '2:chunks_per_page' => 8738,
        '2:total_pages' => 1,
        '2:total_chunks' => 8738,
        '2:used_chunks' => 100,
        '2:free_chunks' => 8638,
        '2:get_hits' => 50,
      ],
      'memcachebis:11211' => [
        'active_slabs' => 2,
        'total_malloced' => 2097152,
        '2:chunk_size' => 120,
        '2:chunks_per_page' => 8738,
        '2:total_pages' => 1,
        '2:total_chunks' => 8738,
        '2:used_chunks' => 200,
        '2:free_chunks' => 8538,
        '2:get_hits' => 75,
      ],
    ]);

    // Properties of the slab class, identical on both servers, are kept once.
    $this->assertSame(120, $total['2:chunk_size']);
    $this->assertSame(8738, $total['2:chunks_per_page']);
    // Counters and gauges are summed.
    $this->assertSame(4, $total['active_slabs']);
    $this->assertSame(4194304, $total['total_malloced']);
    $this->assertSame(2, $total['2:total_pages']);
    $this->assertSame(17476, $total['2:total_chunks']);
    $this->assertSame(300, $total['2:used_chunks']);
    $this->assertSame(17176, $total['2:free_chunks']);
    $this->assertSame(125, $total['2:get_hits']);
  }

  /**
   * Tests that a property differing between servers is left out.
   *
   * Slab classes are numbered per server, so slab 2 of a server started with
   * another growth factor is a different size. A total for it would describe
   * neither server.
   */
  public function testDifferingPropertiesAreLeftOut() {
    $total = $this->driver->aggregate([
      'memcache:11211' => ['2:chunk_size' => 120, '2:total_pages' => 1],
      'memcachebis:11211' => ['2:chunk_size' => 96, '2:total_pages' => 1],
    ]);

    $this->assertArrayNotHasKey('2:chunk_size', $total);
    $this->assertSame(2, $total['2:total_pages']);
  }

  /**
   * Tests that a property missing from a server is left out.
   */
  public function testPropertiesMissingFromOneServerAreLeftOut() {
    $total = $this->driver->aggregate([
      'memcache:11211' => ['2:chunk_size' => 120, '3:chunk_size' => 152],
      'memcachebis:11211' => ['2:chunk_size' => 120],
    ]);

    $this->assertSame(120, $total['2:chunk_size']);
    $this->assertArrayNotHasKey('3:chunk_size', $total);
  }

  /**
   * Tests the ages and eviction times of the "items" statistics.
   */
  public function testItemAgesAreNotSummed() {
    $total = $this->driver->aggregate([
      'memcache:11211' => [
        'items:2:number' => 100,
        'items:2:age' => 3600,
        'items:2:evicted' => 5,
        'items:2:evicted_time' => 60,
      ],
      'memcachebis:11211' => [
        'items:2:number' => 200,
        'items:2:age' => 3600,
        'items:2:evicted' => 7,
        'items:2:evicted_time' => 90,
      ],
    ]);

    $this->assertSame(300, $total['items:2:number']);
    $this->assertSame(12, $total['items:2:evicted']);
    $this->assertSame(3600, $total['items:2:age']);
    $this->assertArrayNotHasKey('items:2:evicted_time', $total);
  }

  /**
   * Tests the server properties of the default statistics.
   */
  public function testServerPropertiesAreNotSummed() {
    $total = $this->driver->aggregate([
      'memcache:11211' => [
        'pid' => 12,
        'time' => 1700000000,
        'version' => '1.6.21',
        'libevent' => '2.1.12-stable',
        'get_hits' => 10,
        'uptime' => 100,
      ],
      'memcachebis:11211' => [
        'pid' => 34,
        'time' => 1700000001,
        'version' => '1.6.21',
        'libevent' => '2.1.12-stable',
        'get_hits' => 20,
        'uptime' => 300,
      ],
      // An unreachable server has no statistics and must not affect totals.
      'memcache-unreachable.invalid:11211' => FALSE,
    ]);

    $this->assertArrayNotHasKey('pid', $total);
    $this->assertArrayNotHasKey('time', $total);
    $this->assertSame('1.6.21', $total['version']);
    $this->assertSame('2.1.12-stable', $total['libevent']);
    $this->assertSame(30, $total['get_hits']);
    $this->assertSame(400, $total['uptime']);
  }

  /**
   * Tests that nothing is totalled when no server responded.
   */
  public function testNoTotalsWithoutServers() {
    $this->assertSame([], $this->driver->aggregate([]));
    $this->assertSame([], $this->driver->aggregate(['memcache-unreachable.invalid:11211' => FALSE]));
  }

}
