<?php

namespace Drupal\Tests\memcache\Kernel;

use Drupal\Core\Site\Settings;
use Drupal\KernelTests\KernelTestBase;
use Drupal\memcache\Driver\DriverBase;
use Drupal\memcache\Driver\MemcacheDriverFactory;
use Drupal\memcache\Driver\StatisticsDriverDecorator;
use Drupal\memcache\MemcacheBackend;
use Drupal\memcache\MemcacheSettings;
use Drupal\memcache\Statistics\RequestStatistics;

/**
 * Tests per-request statistics collection through the driver decorator.
 *
 * @group memcache
 *
 * @see https://www.drupal.org/project/memcache/issues/2996054
 */
class MemcacheRequestStatisticsTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'memcache'];

  /**
   * The memcache settings shared by every test.
   *
   * @var array
   */
  protected $memcacheSettings;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $host = getenv('MEMCACHED_HOST') ?: '127.0.0.1:11211';
    $this->memcacheSettings = [
      'servers' => [$host => 'default'],
      'bins' => ['default' => 'default'],
      'key_prefix' => $this->databasePrefix,
    ];
    $this->setSetting('memcache', $this->memcacheSettings);
  }

  /**
   * Builds a driver factory from the current settings plus overrides.
   *
   * @param array $overrides
   *   Memcache settings to add to the shared ones.
   * @param \Drupal\memcache\Statistics\RequestStatistics|null $statistics
   *   (optional) The collector to hand to the factory.
   *
   * @return \Drupal\memcache\Driver\MemcacheDriverFactory
   *   The factory.
   */
  protected function createFactory(array $overrides, ?RequestStatistics $statistics = NULL) {
    $this->setSetting('memcache', $overrides + $this->memcacheSettings);
    return new MemcacheDriverFactory(new MemcacheSettings(Settings::getInstance()), $statistics);
  }

  /**
   * Tests that nothing is collected, or decorated, by default.
   */
  public function testNotCollectedByDefault(): void {
    /** @var \Drupal\memcache\Driver\MemcacheDriverFactory $factory */
    $factory = $this->container->get('memcache.factory');
    $driver = $factory->get('default');

    $this->assertInstanceOf(DriverBase::class, $driver);
    $this->assertNotInstanceOf(StatisticsDriverDecorator::class, $driver);

    $driver->set('stats_default', 'value');
    $driver->get('stats_default');
    $driver->getMulti(['stats_default', 'stats_missing']);
    $driver->delete('stats_default');

    /** @var \Drupal\memcache\Statistics\RequestStatistics $statistics */
    $statistics = $this->container->get('memcache.request_statistics');
    $this->assertSame($statistics, $factory->getRequestStatistics());
    $this->assertTrue($statistics->isEmpty());
    $this->assertSame(['all' => [], 'ops' => []], $statistics->get());
    $this->assertSame(['all' => [], 'ops' => []], $driver->requestStats());
  }

  /**
   * Tests that enabling the setting records every operation.
   */
  public function testOperationsAreRecorded(): void {
    $statistics = new RequestStatistics();
    $factory = $this->createFactory(['collect_statistics' => TRUE], $statistics);
    $this->assertSame($statistics, $factory->getRequestStatistics());

    $driver = $factory->get('default');
    $this->assertInstanceOf(StatisticsDriverDecorator::class, $driver);
    $this->assertInstanceOf(DriverBase::class, $driver->getDecoratedDriver());
    $this->assertTrue($statistics->isEmpty());

    // The decorator must behave exactly like the driver it wraps.
    $this->assertTrue($driver->set('stats_hit', 'value'));
    $this->assertSame('value', $driver->get('stats_hit'));
    $this->assertFalse($driver->get('stats_miss'));
    $this->assertSame(['stats_hit' => 'value'], $driver->getMulti(['stats_hit', 'stats_miss']));
    $this->assertTrue($driver->add('stats_added', 'value'));
    $this->assertFalse($driver->add('stats_added', 'value'));
    $this->assertTrue($driver->delete('stats_hit'));

    $recorded = $statistics->get();
    $this->assertSame($recorded, $driver->requestStats());
    $this->assertFalse($statistics->isEmpty());

    // Totals per action: [action, total ms, hits, misses].
    $ops = $recorded['ops'];
    $this->assertSame(['set', 'get', 'getMulti', 'add', 'delete'], array_keys($ops));
    $this->assertSame([1, 0], array_slice($ops['set'], 2));
    $this->assertSame([1, 1], array_slice($ops['get'], 2));
    $this->assertSame([1, 1], array_slice($ops['getMulti'], 2));
    $this->assertSame([1, 1], array_slice($ops['add'], 2));
    $this->assertSame([1, 0], array_slice($ops['delete'], 2));
    foreach ($ops as $action => $row) {
      $this->assertSame($action, $row[0]);
      $this->assertGreaterThanOrEqual(0, $row[1], "$action has an elapsed time");
    }

    // One row per key touched: [ms, action, bin, key, status], labelled with
    // the bin and the key as stored in memcache.
    $all = $recorded['all'];
    $this->assertCount(8, $all);
    $expected = [
      ['set', 'stats_hit', 'hit'],
      ['get', 'stats_hit', 'hit'],
      ['get', 'stats_miss', 'miss'],
      ['getMulti', 'stats_hit', 'hit'],
      ['getMulti', 'stats_miss', 'miss'],
      ['add', 'stats_added', 'hit'],
      ['add', 'stats_added', 'miss'],
      ['delete', 'stats_hit', 'hit'],
    ];
    foreach ($expected as $i => [$action, $cid, $status]) {
      $this->assertSame($action, $all[$i][1]);
      $this->assertSame('default', $all[$i][2]);
      $this->assertSame($driver->key($cid), $all[$i][3]);
      $this->assertSame($status, $all[$i][4]);
      $this->assertIsNumeric($all[$i][0]);
    }

    $statistics->reset();
    $this->assertTrue($statistics->isEmpty());
    $this->assertSame(['all' => [], 'ops' => []], $statistics->get());
  }

  /**
   * Tests that a cache backend built on a decorated driver is recorded.
   */
  public function testCacheBackendOperationsAreRecorded(): void {
    $statistics = new RequestStatistics();
    $factory = $this->createFactory(['collect_statistics' => TRUE], $statistics);
    $backend = new MemcacheBackend(
      'default',
      $factory->get('default'),
      $this->container->get('cache_tags.invalidator.checksum'),
      $this->container->get('memcache.timestamp.invalidator.bin')
    );
    // A new backend marks the bin as deleted as of "now" plus the invalidator's
    // tolerance, so give that timestamp a moment to pass before writing.
    usleep(10000);

    $backend->set('stats_backend', 'value');
    $this->assertSame('value', $backend->get('stats_backend')->data);
    $this->assertFalse($backend->get('stats_backend_missing'));

    $ops = $statistics->get()['ops'];
    $this->assertArrayHasKey('set', $ops);
    $this->assertArrayHasKey('getMulti', $ops);
    $this->assertSame([1, 1], array_slice($ops['getMulti'], 2));
  }

  /**
   * Tests that the helpers memcache_admin relies on reach the wrapped driver.
   */
  public function testDriverHelpersAreForwarded(): void {
    $statistics = new RequestStatistics();
    $driver = $this->createFactory(['collect_statistics' => TRUE], $statistics)->get('default');
    $this->assertInstanceOf(StatisticsDriverDecorator::class, $driver);

    $this->assertSame($driver->getDecoratedDriver()->getMemcache(), $driver->getMemcache());
    $this->assertSame(['default' => 'default'], $driver->getBins());
    $this->assertSame($driver->getDecoratedDriver()->getServers(), $driver->getServers());
    $this->assertSame($driver->getDecoratedDriver()->statsTypes(), $driver->statsTypes());
    $this->assertArrayHasKey('default', $driver->stats('default'));

    // Fetching server statistics is not a cache operation.
    $this->assertTrue($statistics->isEmpty());
  }

  /**
   * Tests that a factory built without a collector still works.
   */
  public function testFactoryCreatesItsOwnCollector(): void {
    $factory = $this->createFactory(['collect_statistics' => TRUE]);
    $this->assertInstanceOf(RequestStatistics::class, $factory->getRequestStatistics());

    $driver = $factory->get('default');
    $driver->get('stats_own_collector');
    $this->assertSame([0, 1], array_slice($factory->getRequestStatistics()->get()['ops']['get'], 2));
  }

}
