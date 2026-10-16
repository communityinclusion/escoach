<?php

namespace Drupal\Tests\memcache\Unit;

use Drupal\memcache\Connection\ServerAddress;
use Drupal\Tests\UnitTestCase;

/**
 * Tests the parsing of configured memcache server addresses.
 *
 * @coversDefaultClass \Drupal\memcache\Connection\ServerAddress
 * @group memcache
 */
class ServerAddressTest extends UnitTestCase {

  /**
   * Tests that every supported address form yields the right host and port.
   *
   * @covers ::parse
   * @dataProvider providerParse
   */
  public function testParse($server_path, $expected_host, $expected_port) {
    $this->assertSame([$expected_host, $expected_port], ServerAddress::parse($server_path));
  }

  /**
   * Data provider for testParse().
   */
  public static function providerParse() {
    return [
      'ipv4 with port' => ['127.0.0.1:11211', '127.0.0.1', 11211],
      'ipv4 with other port' => ['127.0.0.1:11212', '127.0.0.1', 11212],
      'ipv4 without port' => ['127.0.0.1', '127.0.0.1', 11211],
      'hostname with port' => ['server1.com:11211', 'server1.com', 11211],
      'hostname without port' => ['memcached1', 'memcached1', 11211],
      'surrounding whitespace' => [' 127.0.0.1:11211 ', '127.0.0.1', 11211],
      'bracketed ipv6 with port' => [
        '[2001:0db8:85a3:0000:0000:8a2e:0370:7334]:11211',
        '2001:0db8:85a3:0000:0000:8a2e:0370:7334',
        11211,
      ],
      'bracketed compressed ipv6 with port' => ['[2001:db8::1]:11212', '2001:db8::1', 11212],
      'bracketed loopback with port' => ['[::1]:11211', '::1', 11211],
      'bracketed ipv6 without port' => ['[::1]', '::1', 11211],
      'bare ipv6' => ['2001:db8::1', '2001:db8::1', 11211],
      'bare ipv6 loopback' => ['::1', '::1', 11211],
      'unix socket' => ['unix:///path/to/socket', '/path/to/socket', 0],
    ];
  }

  /**
   * Tests that a malformed bracketed address is rejected with a clear message.
   *
   * @covers ::parse
   * @dataProvider providerMalformed
   */
  public function testMalformed($server_path) {
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('is not a valid memcache server address');
    ServerAddress::parse($server_path);
  }

  /**
   * Data provider for testMalformed().
   */
  public static function providerMalformed() {
    return [
      'unclosed bracket' => ['[::1:11211'],
      'non numeric port' => ['[::1]:abc'],
      'trailing garbage' => ['[::1]:11211x'],
    ];
  }

  /**
   * Tests the unix socket check.
   *
   * @covers ::isUnixSocket
   */
  public function testIsUnixSocket() {
    $this->assertTrue(ServerAddress::isUnixSocket('unix:///path/to/socket'));
    $this->assertFalse(ServerAddress::isUnixSocket('unix.example.com:11211'));
    $this->assertFalse(ServerAddress::isUnixSocket('[::1]:11211'));
  }

}
