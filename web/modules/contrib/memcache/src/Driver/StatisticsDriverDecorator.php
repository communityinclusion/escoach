<?php

namespace Drupal\memcache\Driver;

use Drupal\memcache\DrupalMemcacheInterface;
use Drupal\memcache\Statistics\RequestStatistics;

/**
 * Records per-request statistics for the memcache driver it wraps.
 *
 * The drivers themselves know nothing about statistics. The driver factory
 * wraps them in this decorator only when
 * $settings['memcache']['collect_statistics'] is TRUE, so by default the cache
 * hot path carries no timing or bookkeeping cost at all.
 *
 * The public helpers that DriverBase adds on top of DrupalMemcacheInterface,
 * such as getMemcache(), stats(), getBins() and statsTypes(), are forwarded to
 * the wrapped driver through __call() so that memcache_admin works with a
 * decorated driver too.
 *
 * @method \Memcache|\Memcached getMemcache()
 * @method array stats($stats_bin = 'cache', $stats_type = 'default', $aggregate = FALSE)
 * @method array getBins()
 * @method array getServers()
 * @method string[] statsTypes()
 */
class StatisticsDriverDecorator implements DrupalMemcacheInterface {

  /**
   * The wrapped driver.
   *
   * @var \Drupal\memcache\DrupalMemcacheInterface
   */
  protected $driver;

  /**
   * The collector that receives the statistics.
   *
   * @var \Drupal\memcache\Statistics\RequestStatistics
   */
  protected $statistics;

  /**
   * The cache bin the wrapped driver serves.
   *
   * @var string
   */
  protected $bin;

  /**
   * Constructs a StatisticsDriverDecorator object.
   *
   * @param \Drupal\memcache\DrupalMemcacheInterface $driver
   *   The driver to wrap.
   * @param \Drupal\memcache\Statistics\RequestStatistics $statistics
   *   The collector that receives the statistics.
   * @param string|null $bin
   *   The cache bin the driver serves, used to label the recorded rows.
   */
  public function __construct(DrupalMemcacheInterface $driver, RequestStatistics $statistics, $bin = NULL) {
    $this->driver = $driver;
    $this->statistics = $statistics;
    $this->bin = $bin ?? 'default';
  }

  /**
   * {@inheritdoc}
   */
  public function set($key, $value, $exp = 0, $flag = FALSE) {
    $start = hrtime(TRUE);
    $result = $this->driver->set($key, $value, $exp, $flag);
    $this->record('set', [$key => (bool) $result], $start);
    return $result;
  }

  /**
   * {@inheritdoc}
   */
  public function get($key) {
    $start = hrtime(TRUE);
    $result = $this->driver->get($key);
    $this->record('get', [$key => $result !== FALSE], $start);
    return $result;
  }

  /**
   * {@inheritdoc}
   */
  public function getMulti(array $keys) {
    $start = hrtime(TRUE);
    $results = $this->driver->getMulti($keys);
    $hits = [];
    foreach ($keys as $cid) {
      $hits[$cid] = isset($results[$cid]);
    }
    $this->record('getMulti', $hits, $start);
    return $results;
  }

  /**
   * {@inheritdoc}
   */
  public function delete($key) {
    $start = hrtime(TRUE);
    $result = $this->driver->delete($key);
    $this->record('delete', [$key => (bool) $result], $start);
    return $result;
  }

  /**
   * {@inheritdoc}
   */
  public function add($key, $value, $expire = 0) {
    $start = hrtime(TRUE);
    $result = $this->driver->add($key, $value, $expire);
    $this->record('add', [$key => (bool) $result], $start);
    return $result;
  }

  /**
   * {@inheritdoc}
   */
  public function key($key) {
    return $this->driver->key($key);
  }

  /**
   * {@inheritdoc}
   */
  public function flush() {
    $start = hrtime(TRUE);
    $result = $this->driver->flush();
    $this->statistics->record('flush', $this->bin, ['' => (bool) $result], $this->elapsed($start));
    return $result;
  }

  /**
   * Returns the statistics collected during this request.
   *
   * Kept so that code written against DriverBase::requestStats() keeps
   * working with a decorated driver.
   *
   * @return array
   *   The collected statistics, see RequestStatistics::get().
   */
  public function requestStats() {
    return $this->statistics->get();
  }

  /**
   * Returns the driver this decorator wraps.
   *
   * @return \Drupal\memcache\DrupalMemcacheInterface
   *   The wrapped driver.
   */
  public function getDecoratedDriver() {
    return $this->driver;
  }

  /**
   * Forwards DriverBase's extra public helpers to the wrapped driver.
   *
   * @param string $method
   *   The method name.
   * @param array $arguments
   *   The arguments.
   *
   * @return mixed
   *   Whatever the wrapped driver returns.
   */
  public function __call($method, array $arguments) {
    return $this->driver->$method(...$arguments);
  }

  /**
   * Records one operation, labelled with the keys as stored in memcache.
   *
   * @param string $action
   *   The operation performed.
   * @param bool[] $keys
   *   Whether the operation succeeded, keyed by the cache id passed in.
   * @param int $start
   *   The hrtime() the operation started at.
   */
  protected function record($action, array $keys, $start) {
    $time = $this->elapsed($start);
    $full_keys = [];
    foreach ($keys as $cid => $success) {
      $full_keys[$this->driver->key($cid)] = $success;
    }
    $this->statistics->record($action, $this->bin, $full_keys, $time);
  }

  /**
   * Returns the milliseconds elapsed since the given hrtime().
   *
   * @param int $start
   *   The hrtime() to measure from.
   *
   * @return float
   *   The elapsed time in milliseconds.
   */
  protected function elapsed($start) {
    return (hrtime(TRUE) - $start) / 1e6;
  }

}
