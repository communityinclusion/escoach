<?php

namespace Drupal\memcache_test\Connection;

use Drupal\memcache\Connection\MemcachedConnection;
use Drupal\memcache\MemcacheSettings;
use Drupal\memcache_test\StatslessMemcached;

/**
 * A Memcached connection to a server that rejects management commands.
 */
class StatslessMemcachedConnection extends MemcachedConnection {

  /**
   * {@inheritdoc}
   */
  public function __construct(MemcacheSettings $settings, $persistent_id = NULL) {
    // The double is never persistent: it is rebuilt for every request anyway.
    parent::__construct($settings);

    $memcache = new StatslessMemcached();
    // Carry the options the parent applied over to the double.
    foreach ([\Memcached::OPT_COMPRESSION, \Memcached::OPT_DISTRIBUTION] as $option) {
      $memcache->setOption($option, $this->memcache->getOption($option));
    }
    foreach ($settings->get('options', []) as $option => $value) {
      $memcache->setOption($option, $value);
    }
    $this->memcache = $memcache;
  }

}
