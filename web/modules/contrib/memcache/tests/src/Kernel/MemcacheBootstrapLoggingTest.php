<?php

namespace Drupal\Tests\memcache\Kernel;

use Drupal\Core\Site\Settings;
use Drupal\KernelTests\KernelTestBase;
use Drupal\memcache\Driver\MemcacheDriverFactory;
use Drupal\memcache\MemcacheSettings;
use Psr\Log\AbstractLogger;

/**
 * Tests that a failed operation is logged safely before the container exists.
 *
 * The container cache bin is written while the kernel is still building the
 * container, so there is no logger service at that point. A failed set() on
 * that bin must not turn into a fatal error just because it cannot be logged.
 *
 * @group memcache
 *
 * @see \Drupal\memcache\Driver\DriverBase::getLogger()
 * @see https://www.drupal.org/project/memcache/issues/3480438
 */
class MemcacheBootstrapLoggingTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'memcache'];

  /**
   * An address that no memcache server listens on.
   */
  const UNREACHABLE = 'memcache-unreachable.invalid:11211';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    if (!class_exists(\Memcached::class)) {
      $this->markTestSkipped('The Memcached PHP extension is not installed; the Memcache extension does not log from the driver.');
    }
  }

  /**
   * Builds a driver, with debug logging on, that cannot reach its server.
   *
   * @return \Drupal\memcache\DrupalMemcacheInterface
   *   The driver.
   */
  protected function failingDriver() {
    $this->setSetting('memcache', [
      'servers' => [self::UNREACHABLE => 'default'],
      'bins' => ['default' => 'default'],
      'key_prefix' => $this->databasePrefix,
      'extension' => \Memcached::class,
      'debug' => TRUE,
    ]);

    return (new MemcacheDriverFactory(new MemcacheSettings(Settings::getInstance())))->get('default');
  }

  /**
   * Tests that a failed set() falls back to the error log with no container.
   */
  public function testSetFailureWithoutContainer() {
    $driver = $this->failingDriver();
    // A real file: error_log() cannot write to the vfs:// site directory.
    $error_log = tempnam(sys_get_temp_dir(), 'memcache-bootstrap-');
    $previous_error_log = ini_set('error_log', $error_log);

    $container = \Drupal::getContainer();
    \Drupal::unsetContainer();
    try {
      // Before the fix this threw ContainerNotInitializedException.
      $result = $driver->set('foo', 'bar');
    }
    finally {
      \Drupal::setContainer($container);
      ini_set('error_log', $previous_error_log);
      $logged = file_get_contents($error_log);
      unlink($error_log);
    }

    $this->assertFalse($result);
    $this->assertStringContainsString('[memcache] error: Memcache set failed for key ' . $this->databasePrefix . '%3Adefault%3A-foo on server(s) ', $logged);
    // Placeholders are replaced, not left verbatim.
    $this->assertStringNotContainsString('@key', $logged);
  }

  /**
   * Tests that the same failure goes to the logger channel once it exists.
   */
  public function testSetFailureWithContainer() {
    $driver = $this->failingDriver();

    $logger = new class() extends AbstractLogger {

      /**
       * The messages logged, keyed by nothing in particular.
       *
       * @var string[]
       */
      public $messages = [];

      /**
       * {@inheritdoc}
       */
      public function log($level, $message, array $context = []): void {
        $this->messages[] = strtr($message, array_filter($context, 'is_scalar'));
      }

    };
    $this->container->get('logger.factory')->addLogger($logger);

    $this->assertFalse($driver->set('foo', 'bar'));
    $this->assertCount(1, $logger->messages);
    $this->assertStringContainsString('Memcache set failed for key ' . $this->databasePrefix . '%3Adefault%3A-foo on server(s) ', $logger->messages[0]);
  }

}
