<?php

namespace Drupal\Tests\memcache\Kernel;

use Drupal\memcache\Invalidator\MemcacheTimestampInvalidator;

/**
 * Timestamp invalidator that never returns the same millisecond twice.
 *
 * Invalidation timestamps have a millisecond resolution. In production the
 * clock skew tolerance keeps writes and invalidations that happen in the same
 * millisecond apart; tests run with a tolerance of 0 and execute several cache
 * operations per millisecond, so wait for the clock to move on instead.
 */
class MonotonicTimestampInvalidator extends MemcacheTimestampInvalidator {

  /**
   * The last timestamp that was handed out.
   *
   * @var float
   */
  protected static $last = 0.0;

  /**
   * {@inheritdoc}
   */
  public function getCurrentTimestamp($offset = 0.0) {
    $timestamp = parent::getCurrentTimestamp($offset);
    while ($timestamp <= static::$last) {
      usleep(100);
      $timestamp = parent::getCurrentTimestamp($offset);
    }
    static::$last = $timestamp;
    return $timestamp;
  }

}
