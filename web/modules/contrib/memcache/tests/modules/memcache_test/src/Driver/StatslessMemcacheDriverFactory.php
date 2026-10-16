<?php

namespace Drupal\memcache_test\Driver;

use Drupal\memcache\Connection\MemcachedConnection;
use Drupal\memcache\Driver\MemcacheDriverFactory;
use Drupal\memcache\Driver\MemcachedDriver;
use Drupal\memcache_test\Connection\StatslessMemcachedConnection;

/**
 * A driver factory whose connections behave like a proxy without "stats".
 *
 * Only the Memcached extension is simulated: the Memcache extension connects
 * eagerly and is not affected by the missing management commands.
 */
class StatslessMemcacheDriverFactory extends MemcacheDriverFactory {

  /**
   * {@inheritdoc}
   */
  protected function initialize() {
    parent::initialize();

    if ($this->connectionClass === MemcachedConnection::class) {
      $this->connectionClass = StatslessMemcachedConnection::class;
    }
    if ($this->driverClass === MemcachedDriver::class) {
      $this->driverClass = StatslessMemcachedDriver::class;
    }
  }

}
