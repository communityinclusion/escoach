<?php

namespace Drupal\memcache\Driver;

use Drupal\memcache\Connection\AsciiAuthenticator;

/**
 * Defines the memcached driver.
 */
class MemcachedDriver extends DriverBase {

  /**
   * The authenticator for servers that require ASCII authentication.
   *
   * @var \Drupal\memcache\Connection\AsciiAuthenticator|null
   */
  protected $asciiAuthenticator;

  /**
   * Whether $asciiAuthenticator has been resolved from the settings.
   *
   * @var bool
   */
  protected $asciiAuthenticatorResolved = FALSE;

  /**
   * The oldest memcached extension release that works on PHP 8.1 and later.
   *
   * Earlier releases store a bare float value, which is how bin deletion and
   * cache tag invalidation times are stored, with its sign flipped.
   *
   * @see memcache_requirements()
   * @see https://github.com/php-memcached-dev/php-memcached/issues/500
   */
  const MINIMUM_EXTENSION_VERSION = '3.2.0';

  /**
   * {@inheritdoc}
   */
  public function set($key, $value, $exp = 0, $flag = FALSE) {
    $full_key = $this->key($key);
    $result = $this->retryAfterAuthentication($full_key, function () use ($full_key, $value, $exp) {
      return $this->memcache->set($full_key, $value, $exp);
    });

    if (!$result) {
      $this->checkResult('set', $full_key);
    }

    return $result;
  }

  /**
   * {@inheritdoc}
   */
  public function add($key, $value, $expire = 0) {
    $full_key = $this->key($key);
    $result = $this->retryAfterAuthentication($full_key, function () use ($full_key, $value, $expire) {
      return $this->memcache->add($full_key, $value, $expire);
    });

    if (!$result) {
      $this->checkResult('add', $full_key);
    }

    return $result;
  }

  /**
   * {@inheritdoc}
   */
  public function delete($key) {
    $full_key = $this->key($key);
    $result = $this->retryAfterAuthentication($full_key, function () use ($full_key) {
      return $this->memcache->delete($full_key, 0);
    });

    if (!$result) {
      $this->checkResult('delete', $full_key);
    }

    return $result;
  }

  /**
   * {@inheritdoc}
   */
  public function flush() {
    $result = $this->retryAfterAuthentication(NULL, function () {
      return $this->memcache->flush();
    });

    if (!$result) {
      $this->checkResult('flush', '');
    }

    return $result;
  }

  /**
   * Runs a write, re-authenticating and retrying once if the server refused it.
   *
   * With ASCII authentication every new socket starts unauthenticated, and
   * libmemcached reopens sockets on its own after a server restart or a
   * timeout. The server answers writes on such a socket with CLIENT_ERROR.
   * Reads cannot be told apart from a miss (the extension reports both as
   * "not found"), so they are not retried; the next write re-authenticates
   * the connection and reads work again from then on.
   *
   * @param string|null $server_key
   *   A key routed to the server involved, or NULL for every server.
   * @param callable $operation
   *   The write to perform; returns FALSE on failure.
   *
   * @return mixed
   *   The operation's result.
   */
  protected function retryAfterAuthentication($server_key, callable $operation) {
    $result = $operation();

    if ($result === FALSE && ($authenticator = $this->asciiAuthenticator())) {
      $code = $this->memcache->getResultCode();
      // A flush touches every server and reports a mix of outcomes.
      if ($code === \Memcached::RES_CLIENT_ERROR || ($server_key === NULL && $code === \Memcached::RES_SOME_ERRORS)) {
        $authenticated = $server_key === NULL
          ? $authenticator->authenticateAll($this->memcache)
          : $authenticator->authenticateKey($this->memcache, $server_key);
        if ($authenticated) {
          $result = $operation();
        }
      }
    }

    return $result;
  }

  /**
   * Returns the ASCII authenticator when credentials are configured.
   *
   * @return \Drupal\memcache\Connection\AsciiAuthenticator|null
   *   The authenticator, or NULL.
   */
  protected function asciiAuthenticator() {
    if (!$this->asciiAuthenticatorResolved) {
      $this->asciiAuthenticator = AsciiAuthenticator::fromSettings($this->settings);
      $this->asciiAuthenticatorResolved = TRUE;
    }
    return $this->asciiAuthenticator;
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
      $results = $this->memcache->getMulti($full_keys, \Memcached::GET_PRESERVE_ORDER);
    }
    catch (\Throwable $e) {
      // A single item that cannot be unserialized aborts the whole multi-get,
      // so retry key by key: the readable items are still returned and the
      // offending items are dropped from memcache.
      $results = $this->getMultiFallback($full_keys);
    }

    // Misses come back as an array of NULLs; FALSE means the request itself
    // failed.
    if ($results === FALSE) {
      $this->checkResult('getMulti', sprintf('%d key(s) starting with %s', count($full_keys), reset($full_keys)));
      $results = [];
    }

    // Convert the full keys back to the cid.
    $cid_results = [];
    $cid_lookup = array_flip($full_keys);

    foreach (array_filter($results) as $key => $value) {
      $cid_results[$cid_lookup[$key]] = $value;
    }

    return $cid_results;
  }

  /**
   * {@inheritdoc}
   *
   * Misses and add() on an existing key are normal outcomes; anything else
   * (failed lookup, refused or timed-out connection, server marked dead, item
   * too large) is a failure worth logging.
   */
  protected function checkResult($operation, $key) {
    $code = $this->memcache->getResultCode();
    $expected = [
      \Memcached::RES_SUCCESS,
      \Memcached::RES_NOTFOUND,
      \Memcached::RES_NOTSTORED,
      \Memcached::RES_DATA_EXISTS,
    ];
    if (in_array($code, $expected, TRUE)) {
      return;
    }

    $this->logFailure($operation, $key, sprintf('[%d] %s', $code, $this->memcache->getResultMessage()));
  }

}
