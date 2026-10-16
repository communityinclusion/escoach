<?php

namespace Drupal\Tests\memcache\Kernel;

use Drupal\Core\Logger\RfcLogLevel;
use Drupal\Core\Site\Settings;
use Drupal\KernelTests\KernelTestBase;
use Drupal\memcache\Driver\DriverBase;
use Drupal\memcache\Driver\MemcacheDriverFactory;
use Drupal\memcache\MemcacheSettings;
use Psr\Log\AbstractLogger;

/**
 * Tests that failed memcache operations are logged, once per request.
 *
 * @group memcache
 *
 * @see \Drupal\memcache\Driver\DriverBase::logFailure()
 * @see \Drupal\memcache\Driver\MemcachedDriver::checkResult()
 */
class MemcacheFailureLoggingTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'memcache'];

  /**
   * An address that no memcache server listens on.
   */
  const UNREACHABLE = 'memcache-unreachable.invalid:11211';

  /**
   * Log entries collected during the test, as [level, message, context].
   *
   * Public so the collecting logger registered in setUp() can append to it.
   *
   * @var array
   */
  public $logged = [];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    if (!class_exists('Memcached')) {
      $this->markTestSkipped('The Memcached PECL extension is not installed.');
    }

    // The once-per-request flag survives across tests in the same process.
    $flag = new \ReflectionProperty(DriverBase::class, 'failureLogged');
    $flag->setAccessible(TRUE);
    $flag->setValue(NULL, FALSE);

    $this->container->get('logger.factory')->addLogger(new class($this) extends AbstractLogger {

      /**
       * Constructs the collecting logger.
       *
       * @param \Drupal\Tests\memcache\Kernel\MemcacheFailureLoggingTest $test
       *   The running test.
       */
      public function __construct(protected MemcacheFailureLoggingTest $test) {}

      /**
       * {@inheritdoc}
       */
      public function log($level, $message, array $context = []): void {
        if (($context['channel'] ?? '') === 'memcache') {
          $this->test->logged[] = [$level, $message, $context];
        }
      }

    });
  }

  /**
   * Builds a driver for the given servers.
   *
   * @param array $servers
   *   The cluster name, keyed by server address.
   * @param array $settings
   *   Additional memcache settings.
   *
   * @return \Drupal\memcache\Driver\MemcachedDriver
   *   A driver for the 'default' bin.
   */
  protected function driverForServers(array $servers, array $settings = []) {
    $this->setSetting('memcache', $settings + [
      'servers' => $servers,
      'bins' => ['default' => 'default'],
      'key_prefix' => $this->databasePrefix,
      'extension' => \Memcached::class,
      // Keep the cross-request throttle out of the way: it depends on APCu.
      'failure_log_interval' => 0,
    ]);

    $factory = new MemcacheDriverFactory(new MemcacheSettings(Settings::getInstance()));
    return $factory->get('default');
  }

  /**
   * Returns the address of the memcache server used by the test run.
   *
   * @return string
   *   A reachable server address.
   */
  protected function reachableServer() {
    return getenv('MEMCACHED_HOST') ?: '127.0.0.1:11211';
  }

  /**
   * Tests that the first failure in a request is logged and the rest are not.
   */
  public function testUnreachableServerIsLoggedOnce() {
    $driver = $this->driverForServers([self::UNREACHABLE => 'default']);

    $this->assertFalse($driver->set('foo', 'bar'));
    $this->assertCount(1, $this->logged);
    [$level, $message, $context] = $this->logged[0];
    $this->assertSame(RfcLogLevel::ERROR, $level);
    $this->assertStringContainsString('@operation failed', $message);
    $this->assertSame('set', $context['@operation']);
    $this->assertSame($driver->key('foo'), $context['@key']);
    $this->assertSame(self::UNREACHABLE, $context['@servers']);
    $this->assertMatchesRegularExpression('/^\[\d+\] /', $context['@reason']);
    $this->assertStringContainsString('Further failures in this request are not logged.', $message);

    // Everything else that fails in the same request stays quiet.
    $this->assertSame([], $driver->getMulti(['foo', 'baz']));
    $this->assertFalse($driver->get('foo'));
    $this->assertFalse($driver->add('foo', 'bar'));
    $this->assertFalse($driver->delete('foo'));
    $this->assertCount(1, $this->logged);
  }

  /**
   * Tests that a read failure is logged too, not only a write failure.
   */
  public function testReadFailureIsLogged() {
    $driver = $this->driverForServers([self::UNREACHABLE => 'default']);

    $this->assertSame([], $driver->getMulti(['foo', 'baz']));
    $this->assertCount(1, $this->logged);
    $this->assertSame('getMulti', $this->logged[0][2]['@operation']);
    $this->assertStringContainsString('2 key(s)', $this->logged[0][2]['@key']);
  }

  /**
   * Tests that misses and add() on an existing key are not logged.
   */
  public function testNormalOutcomesAreNotLogged() {
    $driver = $this->driverForServers([$this->reachableServer() => 'default']);

    $this->assertFalse($driver->get('missing'));
    $this->assertSame([], $driver->getMulti(['missing', 'also-missing']));
    $this->assertFalse($driver->delete('missing'));
    $this->assertTrue($driver->set('present', 1));
    $this->assertFalse($driver->add('present', 2));
    $this->assertTrue($driver->flush());

    $this->assertSame([], $this->logged);
  }

  /**
   * Tests that the debug setting logs every failure.
   */
  public function testDebugLogsEveryFailure() {
    $driver = $this->driverForServers([self::UNREACHABLE => 'default'], ['debug' => TRUE]);

    $this->assertFalse($driver->set('foo', 'bar'));
    $this->assertFalse($driver->set('baz', 'qux'));
    $this->assertSame([], $driver->getMulti(['foo']));

    $this->assertCount(3, $this->logged);
    $this->assertStringNotContainsString('Further failures', $this->logged[0][1]);
  }

  /**
   * Tests that a logger which reads the cache does not log recursively.
   *
   * Logging goes through services (the current user, config) that may read
   * the cache, and with the server down those reads fail too. Each such read
   * must not start another log entry, in particular with debug on, where the
   * once-per-request guard is off.
   */
  public function testLoggerReadingTheCacheDoesNotRecurse() {
    $driver = $this->driverForServers([self::UNREACHABLE => 'default'], ['debug' => TRUE]);

    $reentries = 0;
    $this->container->get('logger.factory')->addLogger(new class($driver, $reentries) extends AbstractLogger {

      public function __construct(protected $driver, protected &$reentries) {}

      /**
       * {@inheritdoc}
       */
      public function log($level, $message, array $context = []): void {
        if (($context['channel'] ?? '') === 'memcache') {
          $this->reentries++;
          // A cache read from inside the logger fails like any other.
          $this->driver->get('read-while-logging');
        }
      }

    });

    $this->assertFalse($driver->set('foo', 'bar'));

    $this->assertSame(1, $reentries);
    $this->assertCount(1, $this->logged);
    $this->assertSame('set', $this->logged[0][2]['@operation']);

    // Logging works again for the next failure.
    $this->assertFalse($driver->set('baz', 'qux'));
    $this->assertSame(2, $reentries);
    $this->assertCount(2, $this->logged);
  }

}
