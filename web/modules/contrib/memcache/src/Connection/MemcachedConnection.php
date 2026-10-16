<?php

namespace Drupal\memcache\Connection;

use Drupal\memcache\MemcacheSettings;

/**
 * Defines the connection of memcache.
 */
class MemcachedConnection implements MemcacheConnectionInterface {

  /**
   * The memcache object.
   *
   * @var \Memcached
   */
  protected $memcache;

  /**
   * Authenticates new connections when the servers require it, or NULL.
   *
   * @var \Drupal\memcache\Connection\AsciiAuthenticator|null
   */
  protected $authenticator;

  /**
   * Constructs a MemcachedConnection object.
   *
   * @param \Drupal\memcache\MemcacheSettings $settings
   *   The memcache config object.
   * @param string|null $persistent_id
   *   (optional) A persistent connection pool id. When given, the Memcached
   *   PECL extension keeps the connections to the servers open across requests
   *   served by the same PHP process, and every construction with the same id
   *   in that process returns the same, already configured, pool. Options and
   *   servers are therefore only applied to a pool that does not have servers
   *   yet. NULL creates a connection that is closed at the end of the request.
   */
  public function __construct(MemcacheSettings $settings, $persistent_id = NULL) {
    $this->memcache = $persistent_id ? new \Memcached($persistent_id) : new \Memcached();
    $this->authenticator = AsciiAuthenticator::fromSettings($settings);

    // A persistent pool that already has servers was configured by an earlier
    // request in this process. Setting options again would be a no-op at best
    // and, for options libmemcached only honours before the first connection,
    // a silent failure at worst.
    if ($this->memcache->isPersistent() && $this->memcache->getServerList()) {
      return;
    }

    $default_opts = [
      \Memcached::OPT_COMPRESSION => TRUE,
      \Memcached::OPT_DISTRIBUTION => \Memcached::DISTRIBUTION_CONSISTENT,
    ];
    foreach ($default_opts as $key => $value) {
      $this->memcache->setOption($key, $value);
    }
    // See README.md for setting custom Memcache options when using the
    // memcached PECL extension.
    foreach ($settings->get('options', []) as $key => $value) {
      $this->memcache->setOption($key, $value);
    }

    // SASL configuration to authenticate with Memcached.
    // Note: this only affects the Memcached PECL extension.
    if ($sasl_config = $settings->get('sasl', [])) {
      $this->memcache->setSaslAuthData($sasl_config['username'], $sasl_config['password']);
    }
  }

  /**
   * {@inheritdoc}
   *
   * With the Memcached PECL extension a connection is persistent because of
   * the pool id the object was constructed with (see the constructor), not
   * per server, so $persistent is not used here. The third argument of
   * \Memcached::addServer() is the server weight, not a persistence flag.
   *
   * @param string $server_path
   *   The server address as configured.
   * @param bool $persistent
   *   Unused, see above.
   * @param bool $authenticate
   *   (optional) Whether to authenticate the new connection when
   *   $settings['memcache']['ascii_auth'] is set. Diagnostics pass FALSE to
   *   probe a server as an anonymous client would see it.
   *
   * @return bool
   *   TRUE when the server was added and, if required, authenticated.
   */
  public function addServer($server_path, $persistent = FALSE, $authenticate = TRUE) {
    // Memcached expects just the path to a unix socket without the protocol,
    // an IPv6 address without its brackets, and the port as an integer, which
    // is what the parser hands back.
    [$host, $port] = ServerAddress::parse($server_path);

    // A persistent pool remembers its servers between requests; adding one
    // again would list it twice and hand it a double share of the keys.
    foreach ($this->memcache->getServerList() as $server) {
      if ($server['host'] === $host && (int) $server['port'] === (int) $port) {
        return TRUE;
      }
    }

    if (!$this->memcache->addServer($host, $port)) {
      return FALSE;
    }

    // Servers started with "-Y" reject every command on a connection that
    // has not authenticated, so do that right away. libmemcached only opens
    // the socket on first use; the authentication write is that first use.
    if ($authenticate && $this->authenticator) {
      return $this->authenticator->authenticateServer($this->memcache, (string) $host, (int) $port);
    }

    return TRUE;
  }

  /**
   * Returns the authenticator for the configured credentials, if any.
   *
   * @return \Drupal\memcache\Connection\AsciiAuthenticator|null
   *   The authenticator, or NULL when no ASCII authentication is configured.
   */
  public function getAuthenticator() {
    return $this->authenticator;
  }

  /**
   * {@inheritdoc}
   */
  public function getMemcache() {
    return $this->memcache;
  }

  /**
   * {@inheritdoc}
   */
  public function close() {
    // A persistent pool is meant to outlive the request; quit() would close
    // its sockets for the whole process and the next request would pay the
    // handshakes the pool exists to avoid. Non-persistent connections are
    // closed at the end of the request anyway, so this only shortens that.
    if ($this->memcache->isPersistent()) {
      return;
    }
    $this->memcache->quit();
  }

}
