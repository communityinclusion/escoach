<?php

namespace Drupal\memcache_test;

/**
 * Behaves like memcached behind a proxy that lacks the management commands.
 *
 * Twemproxy implements the storage and retrieval commands of the memcached
 * protocol but not "stats" or "version": it closes the connection instead, and
 * the Memcached extension reports that as FALSE with RES_CONNECTION_FAILURE
 * (measured against twemproxy 0.4.1). Everything else is a real \Memcached.
 */
class StatslessMemcached extends \Memcached {

  /**
   * {@inheritdoc}
   */
  public function getStats($type = NULL): array|false {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function getVersion(): array|false {
    return FALSE;
  }

}
