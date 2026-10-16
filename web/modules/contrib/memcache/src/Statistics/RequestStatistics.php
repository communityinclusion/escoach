<?php

namespace Drupal\memcache\Statistics;

/**
 * Collects statistics about the memcache operations made during a request.
 *
 * Nothing is recorded unless the driver factory wraps its drivers in a
 * \Drupal\memcache\Driver\StatisticsDriverDecorator, which it does only when
 * $settings['memcache']['collect_statistics'] is TRUE. The memcache_admin
 * module reads the collected data to render its per-page statistics.
 */
class RequestStatistics {

  /**
   * One row per key touched: [ms, action, bin, key, 'hit' or 'miss'].
   *
   * @var array[]
   */
  protected $all = [];

  /**
   * Totals per action: [action, total ms, hits, misses], keyed by action.
   *
   * @var array[]
   */
  protected $ops = [];

  /**
   * Records the outcome of one memcache operation.
   *
   * @param string $action
   *   The operation performed: get, getMulti, set, add, delete or flush.
   * @param string $bin
   *   The cache bin the operation was performed on.
   * @param bool[] $keys
   *   Whether the operation succeeded, keyed by memcache key. For reads TRUE
   *   is a hit; for writes and deletes it means the call succeeded.
   * @param float $time
   *   How long the operation took, in milliseconds.
   */
  public function record($action, $bin, array $keys, $time) {
    if (!isset($this->ops[$action])) {
      $this->ops[$action] = [$action, 0, 0, 0];
    }
    $this->ops[$action][1] += $time;

    foreach ($keys as $key => $success) {
      $this->all[] = [
        number_format($time, 2),
        $action,
        $bin,
        (string) $key,
        $success ? 'hit' : 'miss',
      ];
      if ($success) {
        $this->ops[$action][2]++;
      }
      else {
        $this->ops[$action][3]++;
      }
    }
  }

  /**
   * Returns everything recorded so far.
   *
   * @return array
   *   An array with two keys: 'all', one row per key touched in the form
   *   [ms, action, bin, key, status]; and 'ops', totals per action in the form
   *   [action, total ms, hits, misses], keyed by action.
   */
  public function get() {
    return [
      'all' => $this->all,
      'ops' => $this->ops,
    ];
  }

  /**
   * Returns whether anything has been recorded.
   *
   * @return bool
   *   TRUE if no operation has been recorded.
   */
  public function isEmpty() {
    return empty($this->all) && empty($this->ops);
  }

  /**
   * Discards everything recorded so far.
   */
  public function reset() {
    $this->all = [];
    $this->ops = [];
  }

}
