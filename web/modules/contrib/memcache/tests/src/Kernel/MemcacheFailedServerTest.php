<?php

namespace Drupal\Tests\memcache\Kernel;

use Drupal\Core\Site\Settings;
use Drupal\KernelTests\KernelTestBase;
use Drupal\memcache\Driver\MemcacheDriverFactory;
use Drupal\memcache\MemcacheSettings;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests how a cluster behaves while one of its servers is down.
 *
 * The module opens a fresh, non-persistent connection on every request, so
 * libmemcached's failure accounting starts from zero each time. The README
 * documents the Memcached options that still let a dead server be dropped
 * from the hash ring under that model, and this test is what keeps that
 * recipe honest.
 *
 * Requires one reachable memcached server in the MEMCACHED_HOST environment
 * variable. The dead server is an address nothing can ever listen on.
 *
 * @group memcache
 */
#[RunTestsInSeparateProcesses]
class MemcacheFailedServerTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'memcache'];

  /**
   * An address that no memcache server listens on.
   */
  const UNREACHABLE = 'memcache-unreachable.invalid:11211';

  /**
   * How many keys to write per pass.
   *
   * Enough that the chance of every key hashing to the live server is nil.
   */
  const KEYS = 40;

  /**
   * The options that let libmemcached drop a dead server within a request.
   *
   * @var array
   */
  const REMOVE_FAILED = [
    \Memcached::OPT_REMOVE_FAILED_SERVERS => TRUE,
    \Memcached::OPT_SERVER_FAILURE_LIMIT => 1,
    \Memcached::OPT_CONNECT_TIMEOUT => 100,
  ];

  /**
   * The reachable server.
   *
   * @var string
   */
  protected $live;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    if (!class_exists('\Memcached')) {
      $this->markTestSkipped('Failed server removal is a feature of the Memcached PECL extension.');
    }

    $this->live = getenv('MEMCACHED_HOST') ?: '127.0.0.1:11211';
  }

  /**
   * Builds a driver for a cluster of the live server and a dead one.
   *
   * @param array $options
   *   Memcached options, as they would be set in settings.php.
   *
   * @return \Drupal\memcache\Driver\DriverBase
   *   The driver for the default bin.
   */
  protected function driverFor(array $options) {
    $this->setSetting('memcache', [
      'servers' => [
        $this->live => 'default',
        self::UNREACHABLE => 'default',
      ],
      'bins' => ['default' => 'default'],
      'key_prefix' => $this->databasePrefix,
      'options' => $options,
    ]);

    $factory = new MemcacheDriverFactory(new MemcacheSettings(Settings::getInstance()));

    return $factory->get('default');
  }

  /**
   * Writes a set of keys through the driver and reads them back.
   *
   * @param \Drupal\memcache\Driver\DriverBase $driver
   *   The driver to write through.
   *
   * @return int
   *   The number of keys that could not be written or did not read back.
   */
  protected function writeAndReadBack($driver) {
    $failures = 0;

    for ($i = 0; $i < self::KEYS; $i++) {
      $cid = 'failed_server_' . $i;
      if (!$driver->set($cid, $i) || $driver->get($cid) !== $i) {
        $failures++;
      }
    }

    return $failures;
  }

  /**
   * Tests that a dead server is removed from the pool within the request.
   */
  public function testDeadServerIsRemovedFromThePool() {
    $driver = $this->driverFor(self::REMOVE_FAILED);

    // The first pass pays for discovering the dead server: the operation
    // that hits it fails, and libmemcached drops it from the ring.
    $this->writeAndReadBack($driver);

    // From then on every key lands on the live server.
    $this->assertSame(0, $this->writeAndReadBack($driver), 'Every key round-trips once the dead server has been removed from the pool.');

    // Prove that by reading them back from the live server on its own.
    $alone = new \Memcached();
    $alone->setOption(\Memcached::OPT_COMPRESSION, TRUE);
    [$host, $port] = explode(':', $this->live);
    $alone->addServer($host, (int) $port);

    $found = 0;
    for ($i = 0; $i < self::KEYS; $i++) {
      if ($alone->get($driver->key('failed_server_' . $i)) === $i) {
        $found++;
      }
    }
    $alone->quit();

    $this->assertSame(self::KEYS, $found, 'The keys were rehashed onto the live server.');
  }

  /**
   * Tests that without removal the dead server keeps its share of the keys.
   *
   * This is the behaviour the README warns about: the keys that hash to the
   * dead server fail for the whole request, however short the timeouts.
   */
  public function testDeadServerKeepsItsKeysWithoutRemoval() {
    $options = self::REMOVE_FAILED;
    unset($options[\Memcached::OPT_REMOVE_FAILED_SERVERS]);
    $driver = $this->driverFor($options);

    $this->writeAndReadBack($driver);

    $this->assertGreaterThan(0, $this->writeAndReadBack($driver), 'Keys that hash to the dead server keep failing while it stays in the pool.');
  }

  /**
   * Tests that statistics are still collected from the servers that are up.
   *
   * \Memcached::getStats() gives up on the whole pool as soon as one server
   * does not answer, which used to leave the statistics page with nothing
   * but a generic configuration error.
   */
  public function testStatisticsReportEachServer() {
    $driver = $this->driverFor([]);

    $stats = $driver->stats('default');

    $this->assertIsArray($stats['default']);
    $this->assertArrayHasKey($this->live, $stats['default']);
    $this->assertArrayHasKey('uptime', $stats['default'][$this->live], 'The live server still reports its statistics.');
    $this->assertArrayHasKey(self::UNREACHABLE, $stats['default']);
    $this->assertFalse($stats['default'][self::UNREACHABLE], 'The dead server is reported as not answering.');
  }

  /**
   * Accepts the connections queued on a listening socket without reading.
   *
   * @param resource $server
   *   The listening socket.
   * @param array $accepted
   *   The accepted connections, kept open so that they are never answered.
   */
  protected function acceptPending($server, array &$accepted) {
    while ($connection = @stream_socket_accept($server, 0)) {
      $accepted[] = $connection;
    }
  }

  /**
   * Tests that a hung server is bounded by the poll timeout, not removed.
   *
   * A server that accepts the TCP connection but never answers (frozen,
   * overloaded, a container that was paused rather than stopped) is not a
   * connection failure to libmemcached: OPT_REMOVE_FAILED_SERVERS never
   * removes it, and every operation routed to it waits out OPT_POLL_TIMEOUT,
   * 5000 ms by default in the PECL extension. The README tells users to set
   * the poll timeout for that reason; this pins the behaviour it describes.
   *
   * OPT_SERVER_TIMEOUT_LIMIT sounds like it should cover this and does not:
   * libmemcached only counts connection attempts, and a server that answered
   * the connection is closed and reconnected, never counted. The option is
   * set here so that the test keeps proving that.
   *
   * The hung server is a listening socket in this process that is never
   * read from: the kernel completes the handshake, so connections succeed,
   * and nothing ever replies. The queued connections are accepted between
   * passes so that the listen backlog cannot fill up; once it does, the
   * kernel drops new connections, which libmemcached does count as failures,
   * and the server would be removed for the wrong reason.
   */
  public function testHungServerIsBoundedByPollTimeoutButNotRemoved() {
    $hung = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
    $this->assertNotFalse($hung, "Could not open a listening socket: $errstr");
    $address = stream_socket_get_name($hung, FALSE);
    $accepted = [];

    $this->setSetting('memcache', [
      'servers' => [
        $this->live => 'default',
        $address => 'default',
      ],
      'bins' => ['default' => 'default'],
      'key_prefix' => $this->databasePrefix,
      'options' => self::REMOVE_FAILED + [
        \Memcached::OPT_POLL_TIMEOUT => 100,
        \Memcached::OPT_SERVER_TIMEOUT_LIMIT => 1,
      ],
    ]);
    $driver = (new MemcacheDriverFactory(new MemcacheSettings(Settings::getInstance())))->get('default');

    $start = hrtime(TRUE);
    $first = $this->writeAndReadBack($driver);
    $this->acceptPending($hung, $accepted);
    $second = $this->writeAndReadBack($driver);
    $elapsed = (hrtime(TRUE) - $start) / 1e9;
    fclose($hung);

    $this->assertGreaterThan(0, $first, 'Keys routed to the hung server fail.');
    // Not removed: keys still fail on the next pass. libmemcached does back
    // off from the server for OPT_RETRY_TIMEOUT seconds after a timeout, so
    // the exact number differs between passes; what matters is that it never
    // reaches zero the way it does for a refused or timed-out connection.
    $this->assertGreaterThan(0, $second, 'The hung server is not removed from the pool.');
    // Every failing key costs at most one poll timeout for its set() and one
    // for its get(); with the 5 s default these two passes would take
    // minutes.
    $this->assertLessThan(($first + $second) * 2 * 0.1 + 1, $elapsed, sprintf('Each failed operation waited about OPT_POLL_TIMEOUT, not the 5 s default (%.1f s for %d failures).', $elapsed, $first + $second));
  }

  /**
   * Tests that a persistent pool remembers a dead server and retries it later.
   *
   * Without persistent connections every request pays for rediscovering the
   * dead server: one connection attempt, or one DNS lookup when the name no
   * longer resolves, which OPT_CONNECT_TIMEOUT does not bound. A persistent
   * pool pays it once per PHP worker, and OPT_DEAD_TIMEOUT is what makes the
   * pool retry the server later instead of leaving it out until the workers
   * restart.
   */
  public function testPersistentPoolRetriesDeadServerAfterDeadTimeout() {
    $options = self::REMOVE_FAILED + [\Memcached::OPT_DEAD_TIMEOUT => 2];
    $this->setSetting('memcache', [
      'servers' => [
        $this->live => 'default',
        self::UNREACHABLE => 'default',
      ],
      'bins' => ['default' => 'default'],
      'key_prefix' => $this->databasePrefix,
      'options' => $options,
      'persistent' => TRUE,
      'persistent_id' => $this->databasePrefix,
    ]);
    $settings = new MemcacheSettings(Settings::getInstance());

    // Request 1 discovers the dead server and ejects it.
    $driver = (new MemcacheDriverFactory($settings))->get('default');
    $this->writeAndReadBack($driver);
    $this->assertSame(0, $this->writeAndReadBack($driver));

    // Request 2, same worker: the pool still knows the server is dead, so no
    // key fails and no connection attempt is made.
    $driver = (new MemcacheDriverFactory($settings))->get('default');
    $this->assertTrue($driver->getMemcache()->isPersistent());
    $start = hrtime(TRUE);
    $this->assertSame(0, $this->writeAndReadBack($driver), 'A later request on the same pool does not rediscover the dead server.');
    $this->assertLessThan(0.1, (hrtime(TRUE) - $start) / 1e9, 'No connection attempt was made to the dead server.');

    // After OPT_DEAD_TIMEOUT the pool tries the server once more, so that a
    // server that came back is used again. The probe is a real cache
    // operation: it is still dead here, so the one key whose operation was
    // routed to it fails (its set and its read-back), and the server is
    // ejected again for the rest.
    sleep(3);
    $driver = (new MemcacheDriverFactory($settings))->get('default');
    $failures = $this->writeAndReadBack($driver);
    $this->assertGreaterThan(0, $failures, 'The pool re-probed the dead server after OPT_DEAD_TIMEOUT.');
    $this->assertLessThanOrEqual(2, $failures, 'Only the operation that served as the probe failed; the server was ejected again.');
    $this->assertSame(0, $this->writeAndReadBack($driver), 'Every key round-trips again after the re-ejection.');
  }

}
