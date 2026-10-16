<?php

namespace Drupal\Tests\memcache\Kernel\Fixture;

/**
 * A Memcached client that lets another writer in after every read.
 *
 * While $races is positive, each extended get() first returns the item as it
 * was, but only after storing an extra flood event under the same key, as a
 * concurrent request would. A compare-and-swap made with the returned token
 * therefore fails, and an add() finds the item already there.
 */
class RacingMemcached extends \Memcached {

  /**
   * How many more reads should be raced.
   *
   * @var int
   */
  public $races = 0;

  /**
   * The flood event to inject on each raced read.
   *
   * @var array
   */
  public $event = [];

  /**
   * {@inheritdoc}
   */
  public function get($key, $cache_cb = NULL, $get_flags = 0): mixed {
    $result = parent::get($key, $cache_cb, $get_flags);
    if ($this->races > 0 && ($get_flags & \Memcached::GET_EXTENDED)) {
      $this->races--;
      $events = is_array($result) && is_array($result['value']) ? $result['value'] : [];
      $events[] = $this->event;
      parent::set($key, $events, 3600);
    }
    return $result;
  }

}
