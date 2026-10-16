<?php

namespace Drupal\Tests\memcache\Kernel;

use Drupal\Core\Site\Settings;
use Drupal\KernelTests\KernelTestBase;
use Drupal\memcache\Connection\MemcachedConnection;
use Drupal\memcache\Driver\MemcacheDriverFactory;
use Drupal\memcache\MemcacheSettings;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests persistent connections with the Memcached PECL extension.
 *
 * A persistent pool outlives the request and is shared by every construction
 * with the same id in the PHP process, so besides being persistent at all the
 * pool must not accumulate duplicate servers, must not be shared between
 * clusters or configurations, and must not be touched by the status report
 * probe.
 *
 * @group memcache
 *
 * @see \Drupal\memcache\Connection\MemcachedConnection
 * @see \Drupal\memcache\Driver\MemcacheDriverFactory::getPersistentId()
 */
#[RunTestsInSeparateProcesses]
class MemcachePersistentConnectionTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'memcache'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    if (!class_exists('Memcached')) {
      $this->markTestSkipped('The Memcached PECL extension is not installed.');
    }
  }

  /**
   * Returns a reachable server address.
   */
  protected function reachableServer() {
    return getenv('MEMCACHED_HOST') ?: '127.0.0.1:11211';
  }

  /**
   * Builds a driver factory using the Memcached extension.
   *
   * @param array $settings
   *   Values for $settings['memcache'] on top of a single reachable server, a
   *   persistent pool labelled after this test run and the test key prefix.
   *
   * @return \Drupal\memcache\Driver\MemcacheDriverFactory
   *   The configured factory.
   */
  protected function factoryFor(array $settings = []) {
    $this->setSetting('memcache', $settings + [
      'extension' => \Memcached::class,
      'servers' => [$this->reachableServer() => 'default'],
      'bins' => ['default' => 'default'],
      'key_prefix' => $this->databasePrefix,
      'persistent' => TRUE,
      'persistent_id' => $this->databasePrefix,
    ]);

    return new MemcacheDriverFactory(new MemcacheSettings(Settings::getInstance()));
  }

  /**
   * Returns the \Memcached instance serving a bin.
   *
   * @param \Drupal\memcache\Driver\MemcacheDriverFactory $factory
   *   The factory.
   * @param string $bin
   *   The bin.
   *
   * @return \Memcached
   *   The extension object behind the driver.
   */
  protected function memcachedFor(MemcacheDriverFactory $factory, $bin = 'default') {
    $driver = $factory->get($bin);
    $this->assertNotFalse($driver);
    $memcached = $driver->getMemcache();
    $this->assertInstanceOf(\Memcached::class, $memcached);
    return $memcached;
  }

  /**
   * Returns the "host:port" addresses of a \Memcached instance's servers.
   */
  protected function serverAddresses(\Memcached $memcached) {
    return array_map(function (array $server) {
      return $server['host'] . ':' . $server['port'];
    }, $memcached->getServerList());
  }

  /**
   * Tests that closing a connection leaves a persistent pool alone.
   *
   * The factory closes its connections when it is flushed and memcache_admin
   * closes them at the end of a page; quitting a persistent pool there would
   * cost the next request the handshakes the pool exists to avoid.
   */
  public function testCloseKeepsPersistentPool() {
    $settings = $this->container->get('memcache.settings');

    foreach ([TRUE, FALSE] as $persistent) {
      $client = new class($persistent) extends \Memcached {

        /**
         * Whether quit() was called.
         *
         * @var bool
         */
        public $quit = FALSE;

        /**
         * Constructs the double.
         *
         * @param bool $persistent
         *   What isPersistent() reports.
         */
        public function __construct(protected bool $persistent) {
          parent::__construct();
        }

        /**
         * {@inheritdoc}
         */
        public function isPersistent(): bool {
          return $this->persistent;
        }

        /**
         * {@inheritdoc}
         */
        public function quit(): bool {
          $this->quit = TRUE;
          return TRUE;
        }

      };
      $connection = new class($settings, $client) extends MemcachedConnection {

        /**
         * Constructs a connection around the given client.
         *
         * @param \Drupal\memcache\MemcacheSettings $settings
         *   The memcache settings.
         * @param \Memcached $client
         *   The client double to use instead of a real \Memcached.
         */
        public function __construct($settings, \Memcached $client) {
          parent::__construct($settings);
          $this->memcache = $client;
        }

      };

      $connection->close();
      $this->assertSame(!$persistent, $client->quit, $persistent ? 'A persistent pool is not quit.' : 'A non-persistent connection is quit.');
    }
  }

  /**
   * Tests that connections are not persistent unless asked for.
   */
  public function testNotPersistentByDefault() {
    $memcached = $this->memcachedFor($this->factoryFor(['persistent' => FALSE]));
    $this->assertFalse($memcached->isPersistent());
    $this->assertSame([$this->reachableServer()], $this->serverAddresses($memcached));
  }

  /**
   * Tests that the persistent setting produces a working persistent pool.
   */
  public function testPersistent() {
    $memcached = $this->memcachedFor($this->factoryFor());
    $this->assertTrue($memcached->isPersistent());
    $this->assertSame([$this->reachableServer()], $this->serverAddresses($memcached));
    $this->assertSame(\Memcached::DISTRIBUTION_CONSISTENT, $memcached->getOption(\Memcached::OPT_DISTRIBUTION));

    $driver = $this->factoryFor()->get('default');
    $this->assertTrue($driver->set('persistent', 'value'));
    $this->assertSame('value', $driver->get('persistent'));
  }

  /**
   * Tests that reusing a pool does not add its servers a second time.
   *
   * Every request constructs the connection anew and adds every server to it;
   * on a pool that survived the previous request that would list each server
   * once per request and skew the key distribution towards nothing useful.
   */
  public function testPoolIsReusedWithoutDuplicatingServers() {
    $first = $this->memcachedFor($this->factoryFor());
    $second = $this->memcachedFor($this->factoryFor());
    // Both a new factory and a flushed one construct the connection again.
    $third = $this->factoryFor()->get('default', TRUE)->getMemcache();

    foreach ([$first, $second, $third] as $memcached) {
      $this->assertTrue($memcached->isPersistent());
      $this->assertSame([$this->reachableServer()], $this->serverAddresses($memcached));
    }
  }

  /**
   * Tests that each cluster gets its own pool.
   *
   * With one pool for the whole site the second cluster would find the first
   * cluster's servers already in it and add its own, merging both.
   */
  public function testClustersHaveSeparatePools() {
    $factory = $this->factoryFor([
      'servers' => [
        $this->reachableServer() => 'default',
        'memcache-unreachable.invalid:11211' => 'other',
      ],
      'bins' => ['default' => 'default', 'other' => 'other'],
    ]);

    $default = $this->memcachedFor($factory, 'default');
    $other = $this->memcachedFor($factory, 'other');

    $this->assertSame([$this->reachableServer()], $this->serverAddresses($default));
    $this->assertSame(['memcache-unreachable.invalid:11211'], $this->serverAddresses($other));
  }

  /**
   * Tests that a configuration change does not inherit an earlier pool.
   *
   * Options are only applied to a pool without servers, so a pool keyed on
   * anything less than the configuration would keep serving the options it was
   * created with after settings.php changed.
   */
  public function testConfigurationChangeStartsNewPool() {
    $memcached = $this->memcachedFor($this->factoryFor([
      'options' => [\Memcached::OPT_CONNECT_TIMEOUT => 1234],
    ]));
    $this->assertSame(1234, $memcached->getOption(\Memcached::OPT_CONNECT_TIMEOUT));

    $memcached = $this->memcachedFor($this->factoryFor([
      'options' => [\Memcached::OPT_CONNECT_TIMEOUT => 4321],
    ]));
    $this->assertSame(4321, $memcached->getOption(\Memcached::OPT_CONNECT_TIMEOUT));
  }

  /**
   * Tests that the status report probe leaves the persistent pool alone.
   *
   * The probe builds its own connection with short timeouts, adds one server
   * and closes it; none of that may reach the pool serving the request.
   */
  public function testServerProbeDoesNotTouchPool() {
    $factory = $this->factoryFor([
      'options' => [\Memcached::OPT_CONNECT_TIMEOUT => 1234],
    ]);
    $memcached = $this->memcachedFor($factory);

    $this->assertSame([$this->reachableServer() => TRUE], $factory->checkServers(500));

    $this->assertSame([$this->reachableServer()], $this->serverAddresses($memcached));
    $this->assertSame(1234, $memcached->getOption(\Memcached::OPT_CONNECT_TIMEOUT));

    $again = $this->memcachedFor($this->factoryFor([
      'options' => [\Memcached::OPT_CONNECT_TIMEOUT => 1234],
    ]));
    $this->assertSame([$this->reachableServer()], $this->serverAddresses($again));
    $this->assertSame(1234, $again->getOption(\Memcached::OPT_CONNECT_TIMEOUT));
  }

  /**
   * Builds a factory that exposes the pool id for the current settings.
   */
  protected function idFactory(array $settings = []) {
    $this->factoryFor($settings);
    return new class(new MemcacheSettings(Settings::getInstance())) extends MemcacheDriverFactory {

      /**
       * Exposes the pool id.
       */
      public function persistentId($cluster) {
        return $this->getPersistentId($cluster);
      }

    };
  }

  /**
   * Tests how the pool id is derived.
   */
  public function testPersistentId() {
    $factory = $this->idFactory();
    $id = $factory->persistentId('default');
    $this->assertStringStartsWith($this->databasePrefix . ':default:', $id);
    $this->assertNotSame($id, $factory->persistentId('other'));

    // The same configuration always maps to the same pool.
    $this->assertSame($id, $this->idFactory()->persistentId('default'));

    // Servers, options and SASL settings are part of the configuration.
    $this->assertNotSame($id, $this->idFactory(['servers' => ['127.0.0.1:11212' => 'default']])->persistentId('default'));
    $this->assertNotSame($id, $this->idFactory(['options' => [\Memcached::OPT_CONNECT_TIMEOUT => 1]])->persistentId('default'));
    $this->assertNotSame($id, $this->idFactory(['sasl' => ['username' => 'u', 'password' => 'p']])->persistentId('default'));

    // Without a label, the pool is named after the module.
    $this->assertStringStartsWith('drupal_memcache:default:', $this->idFactory(['persistent_id' => NULL])->persistentId('default'));
  }

}
