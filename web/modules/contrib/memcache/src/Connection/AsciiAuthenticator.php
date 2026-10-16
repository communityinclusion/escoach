<?php

namespace Drupal\memcache\Connection;

use Drupal\memcache\MemcacheException;
use Drupal\memcache\MemcacheSettings;

/**
 * Authenticates connections to memcached servers started with "-Y authfile".
 *
 * Since 1.5.15 memcached can require authentication on the plain text
 * protocol: every new connection has to authenticate before the server
 * accepts any other command. Authentication is a "set" whose value is
 * "username password"; the key, flags and expiry are ignored (see
 * doc/protocol.txt in memcached). It is per TCP connection, so it has to be
 * repeated whenever libmemcached opens a socket: on the first use of a server
 * in a request, and after a reconnect.
 *
 * Neither libmemcached nor the Memcached PECL extension know about this, so
 * the module does it itself. The extension cannot talk to one server directly,
 * so a key that hashes to the server is looked up and the authentication
 * "set" is routed through it with setByKey().
 *
 * On a connection that is already authenticated the same "set" is an ordinary
 * write and would store the credentials in the cache, so the key is deleted
 * again after every attempt.
 *
 * @see https://github.com/memcached/memcached/wiki/ReleaseNotes1515
 */
final class AsciiAuthenticator {

  /**
   * The first memcached release with "-Y" authentication.
   */
  const MINIMUM_SERVER_VERSION = '1.5.15';

  /**
   * The release that fixed a CPU spin in the authentication code.
   *
   * @see https://github.com/memcached/memcached/wiki/ReleaseNotes164
   */
  const RECOMMENDED_SERVER_VERSION = '1.6.4';

  /**
   * The key the authentication "set" is sent under.
   *
   * The server ignores it; it only matters for routing the command to the
   * right server, and as the key to delete afterwards.
   */
  const AUTH_KEY = 'memcache_ascii_auth';

  /**
   * How many keys to try when looking for one that hashes to a given server.
   */
  const KEY_SCAN_LIMIT = 1000;

  /**
   * Constructs an AsciiAuthenticator.
   *
   * @param string $username
   *   The username from the server's authfile.
   * @param string $password
   *   The matching password.
   */
  public function __construct(
    protected string $username,
    protected string $password,
  ) {}

  /**
   * Builds an authenticator from $settings['memcache']['ascii_auth'].
   *
   * @param \Drupal\memcache\MemcacheSettings $settings
   *   The memcache settings.
   *
   * @return static|null
   *   The authenticator, or NULL when the setting is absent.
   *
   * @throws \Drupal\memcache\MemcacheException
   *   When the setting is present but not a usable username/password pair.
   */
  public static function fromSettings(MemcacheSettings $settings): ?self {
    $config = $settings->get('ascii_auth');
    if ($config === NULL || $config === []) {
      return NULL;
    }

    $username = is_array($config) ? (string) ($config['username'] ?? '') : '';
    $password = is_array($config) ? (string) ($config['password'] ?? '') : '';
    // The token is sent as "username password" on one line, so neither part
    // can contain whitespace.
    if ($username === '' || $password === '' || preg_match('/\s/', $username . $password)) {
      throw new MemcacheException("\$settings['memcache']['ascii_auth'] must be an array with a non-empty 'username' and 'password', neither containing whitespace.");
    }

    return new self($username, $password);
  }

  /**
   * Returns the configured username.
   *
   * @return string
   *   The username.
   */
  public function getUsername(): string {
    return $this->username;
  }

  /**
   * Authenticates the connection to the server a key is routed to.
   *
   * @param \Memcached $memcache
   *   The client.
   * @param string $server_key
   *   Any key that hashes to the server to authenticate with.
   *
   * @return bool
   *   TRUE when the server accepted the credentials.
   */
  public function authenticateKey(\Memcached $memcache, string $server_key): bool {
    // The token has to reach the server verbatim.
    $compression = $memcache->getOption(\Memcached::OPT_COMPRESSION);
    if ($compression) {
      $memcache->setOption(\Memcached::OPT_COMPRESSION, FALSE);
    }
    try {
      $result = $memcache->setByKey($server_key, self::AUTH_KEY, $this->username . ' ' . $this->password);
      // Already authenticated connections store the token as a value; never
      // leave it there.
      $memcache->deleteByKey($server_key, self::AUTH_KEY);
    }
    finally {
      if ($compression) {
        $memcache->setOption(\Memcached::OPT_COMPRESSION, TRUE);
      }
    }

    return (bool) $result;
  }

  /**
   * Authenticates the connection to one server.
   *
   * @param \Memcached $memcache
   *   The client.
   * @param string $host
   *   The server's host as the extension lists it: an address, a hostname or
   *   the path of a unix socket.
   * @param int $port
   *   The server's port, 0 for a unix socket.
   *
   * @return bool
   *   TRUE when the server accepted the credentials, FALSE when it refused
   *   them or when no key could be found that routes to it.
   */
  public function authenticateServer(\Memcached $memcache, string $host, int $port): bool {
    $server_key = $this->serverKey($memcache, $host, $port);
    return $server_key !== NULL && $this->authenticateKey($memcache, $server_key);
  }

  /**
   * Authenticates the connections to every server in the pool.
   *
   * @param \Memcached $memcache
   *   The client.
   *
   * @return bool
   *   TRUE when every server accepted the credentials.
   */
  public function authenticateAll(\Memcached $memcache): bool {
    $result = TRUE;
    foreach ($memcache->getServerList() as $server) {
      $result = $this->authenticateServer($memcache, (string) $server['host'], (int) $server['port']) && $result;
    }
    return $result;
  }

  /**
   * Finds a key that the client routes to the given server.
   *
   * @param \Memcached $memcache
   *   The client.
   * @param string $host
   *   The server's host.
   * @param int $port
   *   The server's port.
   *
   * @return string|null
   *   A key, or NULL when none of the first KEY_SCAN_LIMIT candidates hashes
   *   to the server, which does not happen with any sane number of servers.
   */
  protected function serverKey(\Memcached $memcache, string $host, int $port): ?string {
    for ($i = 0; $i < self::KEY_SCAN_LIMIT; $i++) {
      $server = $memcache->getServerByKey((string) $i);
      if (is_array($server) && (string) $server['host'] === $host && (int) $server['port'] === $port) {
        return (string) $i;
      }
    }
    return NULL;
  }

}
