<?php

namespace Drupal\memcache\Driver;

/**
 * Defines the memcache driver.
 */
class MemcacheDriver extends DriverBase {

  /**
   * {@inheritdoc}
   */
  public function set($key, $value, $exp = 0, $flag = FALSE) {
    return $this->memcache->set($this->key($key), $value, $flag, $exp);
  }

  /**
   * {@inheritdoc}
   */
  public function add($key, $value, $expire = 0) {
    return $this->memcache->add($this->key($key), $value, FALSE, $expire);
  }

  /**
   * {@inheritdoc}
   */
  public function getMulti(array $keys) {
    $full_keys = [];
    foreach ($keys as $cid) {
      $full_keys[$cid] = $this->key($cid);
    }

    try {
      $results = $this->memcache->get($full_keys);
    }
    catch (\Throwable $e) {
      // A single item that cannot be unserialized aborts the whole multi-get,
      // so retry key by key: the readable items are still returned and the
      // offending items are dropped from memcache.
      $results = $this->getMultiFallback($full_keys);
    }

    // If $results is FALSE, convert it to an empty array.
    if (!$results) {
      $results = [];
    }

    // Convert the full keys back to the cid.
    $cid_results = [];

    // Order isn't guaranteed, so ensure the return order matches that
    // requested. So base the results on the order of the full_keys, as they
    // reflect the order of the $cids passed in.
    foreach (array_intersect($full_keys, array_keys($results)) as $cid => $full_key) {
      $cid_results[$cid] = $results[$full_key];
    }

    return $cid_results;
  }

}
