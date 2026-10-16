<?php

namespace Drupal\memcache\Connection;

/**
 * Parses the server addresses configured in $settings['memcache']['servers'].
 *
 * Supported forms:
 * - 'host:port' and 'host' (the port defaults to 11211);
 * - '[ipv6]:port' and '[ipv6]', with the address in square brackets, which is
 *   the only way to tell the port apart from the address;
 * - a bare IPv6 address such as '::1', which cannot carry a port;
 * - 'unix:///path/to/socket'.
 */
final class ServerAddress {

  /**
   * The memcached port used when an address does not specify one.
   */
  const DEFAULT_PORT = 11211;

  /**
   * The prefix that marks a unix socket address.
   */
  const UNIX_SOCKET_PREFIX = 'unix://';

  /**
   * Splits a configured server address into a host and a port.
   *
   * @param string $server_path
   *   The address as configured, e.g. '127.0.0.1:11211', '[::1]:11211' or
   *   'unix:///path/to/socket'.
   *
   * @return array
   *   A two element array: the host (an IPv4 address, an IPv6 address without
   *   brackets, a hostname, or the path of a unix socket without the 'unix://'
   *   prefix) and the port as an integer, 0 for a unix socket.
   *
   * @throws \InvalidArgumentException
   *   When a bracketed address is malformed.
   */
  public static function parse($server_path) {
    $server_path = trim((string) $server_path);

    if (self::isUnixSocket($server_path)) {
      return [substr($server_path, strlen(self::UNIX_SOCKET_PREFIX)), 0];
    }

    // An IPv6 address contains colons itself, so when it carries a port it has
    // to be wrapped in square brackets: '[2001:db8::1]:11211'.
    if ($server_path !== '' && $server_path[0] === '[') {
      if (!preg_match('/^\[([^\]]+)\](?::([0-9]+))?$/', $server_path, $matches)) {
        throw new \InvalidArgumentException(sprintf('"%s" is not a valid memcache server address. An IPv6 address must be written as "[address]" or "[address]:port".', $server_path));
      }
      return [$matches[1], isset($matches[2]) ? (int) $matches[2] : self::DEFAULT_PORT];
    }

    // A bare IPv6 address has no room for a port; use the default.
    if (filter_var($server_path, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== FALSE) {
      return [$server_path, self::DEFAULT_PORT];
    }

    $parts = explode(':', $server_path, 2);
    $port = isset($parts[1]) && $parts[1] !== '' ? (int) $parts[1] : self::DEFAULT_PORT;

    return [$parts[0], $port];
  }

  /**
   * Checks whether a configured address refers to a unix socket.
   *
   * @param string $server_path
   *   The address as configured.
   *
   * @return bool
   *   TRUE for 'unix:///path/to/socket' style addresses.
   */
  public static function isUnixSocket($server_path) {
    return strpos(trim((string) $server_path), self::UNIX_SOCKET_PREFIX) === 0;
  }

}
