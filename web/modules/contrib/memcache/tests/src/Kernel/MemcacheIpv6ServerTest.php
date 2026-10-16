<?php

namespace Drupal\Tests\memcache\Kernel;

use Drupal\Core\Site\Settings;
use Drupal\KernelTests\KernelTestBase;
use Drupal\memcache\Driver\MemcacheDriverFactory;
use Drupal\memcache\MemcacheSettings;

/**
 * Tests that IPv6 server addresses in $settings['memcache']['servers'] work.
 *
 * Registering a server with the Memcached extension does not connect to it,
 * so these tests do not need an IPv6 network: they check that the host and
 * port the extension was given are the ones that were configured.
 *
 * @group memcache
 *
 * @see \Drupal\memcache\Connection\ServerAddress
 */
class MemcacheIpv6ServerTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'memcache'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    if (!class_exists(\Memcached::class)) {
      $this->markTestSkipped('The Memcached extension is required to inspect the registered server list.');
    }
  }

  /**
   * Returns the servers the driver for the default bin was given.
   *
   * @param array $servers
   *   The cluster name, keyed by configured server address.
   *
   * @return array
   *   The host and port of each registered server, in registration order.
   */
  protected function registeredServers(array $servers) {
    $this->setSetting('memcache', [
      'servers' => $servers,
      'bins' => ['default' => 'default'],
      'key_prefix' => $this->databasePrefix,
    ]);
    $factory = new MemcacheDriverFactory(new MemcacheSettings(Settings::getInstance()));
    $driver = $factory->get('default');
    $this->assertNotFalse($driver);

    return array_map(function (array $server) {
      return [$server['host'], $server['port']];
    }, $driver->getMemcache()->getServerList());
  }

  /**
   * Tests a bracketed IPv6 address with an explicit port.
   */
  public function testBracketedAddressWithPort() {
    $this->assertSame([['2001:db8::1', 11212]], $this->registeredServers(['[2001:db8::1]:11212' => 'default']));
  }

  /**
   * Tests a bracketed IPv6 address falls back to the default port.
   */
  public function testBracketedAddressWithoutPort() {
    $this->assertSame([['::1', 11211]], $this->registeredServers(['[::1]' => 'default']));
  }

  /**
   * Tests a bare IPv6 address falls back to the default port.
   */
  public function testBareAddress() {
    $this->assertSame([['::1', 11211]], $this->registeredServers(['::1' => 'default']));
  }

  /**
   * Tests that IPv6 and IPv4 servers can share a cluster.
   */
  public function testMixedAddresses() {
    $this->assertSame([['::1', 11211], ['127.0.0.1', 11212]], $this->registeredServers([
      '[::1]:11211' => 'default',
      '127.0.0.1:11212' => 'default',
    ]));
  }

  /**
   * Tests that the status report can probe an IPv6 server without failing.
   *
   * Nothing listens on the address, so the probe must report the server as
   * unreachable rather than throw while parsing the address.
   */
  public function testCheckServersParsesAddress() {
    $this->setSetting('memcache', [
      'servers' => ['[2001:db8::1]:11211' => 'default'],
      'bins' => ['default' => 'default'],
    ]);
    $factory = new MemcacheDriverFactory(new MemcacheSettings(Settings::getInstance()));
    $this->assertSame(['[2001:db8::1]:11211' => FALSE], $factory->checkServers(200));
  }

}
