<?php

namespace Drupal\memcache_test\Driver;

use Drupal\memcache\Driver\MemcachedDriver;
use Drupal\memcache_test\Connection\StatslessMemcachedConnection;

/**
 * A Memcached driver whose per-server statistics probes also lack "stats".
 *
 * When the pool does not answer "stats", DriverBase::stats() retries every
 * server over a fresh connection. Behind a proxy that connection is just as
 * "stats"-less as the pool, so the double has to cover it as well.
 */
class StatslessMemcachedDriver extends MemcachedDriver {

  /**
   * {@inheritdoc}
   */
  protected function createStatsConnection() {
    return new StatslessMemcachedConnection($this->settings);
  }

}
