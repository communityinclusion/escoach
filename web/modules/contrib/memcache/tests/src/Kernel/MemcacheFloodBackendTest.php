<?php

namespace Drupal\Tests\memcache\Kernel;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceModifierInterface;
use Drupal\Core\Flood\FloodInterface;
use Drupal\Core\Flood\PrefixFloodInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\memcache\Driver\MemcachedDriver;
use Drupal\memcache\Driver\StatisticsDriverDecorator;
use Drupal\memcache\Flood\MemcacheFloodBackend;
use Drupal\Tests\memcache\Kernel\Fixture\RacingMemcached;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpFoundation\Request;

/**
 * Tests the memcache flood backend.
 *
 * @group memcache
 */
class MemcacheFloodBackendTest extends KernelTestBase implements ServiceModifierInterface {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'memcache'];

  /**
   * The request time the flood backend under test sees.
   *
   * Starts at the real request time, since memcached expirations beyond 30
   * days are absolute timestamps checked against the daemon's own clock.
   *
   * @var int
   */
  protected $now;

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container) {
    $definition = new Definition(FloodInterface::class);
    $definition->setFactory([new Reference('memcache.flood.factory'), 'get']);
    $definition->setPublic(TRUE);
    $container->setDefinition('flood', $definition);
  }

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();
    $this->now = $this->container->get('datetime.time')->getRequestTime();
    $host = getenv('MEMCACHED_HOST') ?: '127.0.0.1:11211';
    $this->setSetting('memcache', [
      'servers' => [$host => 'default'],
      'bins' => ['default' => 'default'],
      // Keep this run's keys apart from other runs sharing the daemon.
      'key_prefix' => $this->randomMachineName(),
    ]);
  }

  /**
   * Creates a flood backend whose clock is controlled by the test.
   *
   * @return \Drupal\memcache\Flood\MemcacheFloodBackend
   *   The backend.
   */
  protected function createBackend() {
    $time = $this->createMock(TimeInterface::class);
    $time->method('getRequestTime')->willReturnCallback(function () {
      return $this->now;
    });

    return new MemcacheFloodBackend(
      $this->container->get('memcache.factory')->get('flood'),
      $this->container->get('request_stack'),
      $time
    );
  }

  /**
   * Tests that the core flood service can be pointed at the factory.
   */
  public function testServiceOverride() {
    $flood = $this->container->get('flood');
    $this->assertInstanceOf(MemcacheFloodBackend::class, $flood);
    $this->assertInstanceOf(PrefixFloodInterface::class, $flood);

    $name = 'flood_test_service';
    $this->assertTrue($flood->isAllowed($name, 2, 3600, 'a'));
    $flood->register($name, 3600, 'a');
    $this->assertTrue($flood->isAllowed($name, 2, 3600, 'a'));
    $flood->register($name, 3600, 'a');
    $this->assertFalse($flood->isAllowed($name, 2, 3600, 'a'));
    $flood->clear($name, 'a');
    $this->assertTrue($flood->isAllowed($name, 2, 3600, 'a'));
  }

  /**
   * Tests thresholds and the isolation of events and identifiers.
   */
  public function testThreshold() {
    $flood = $this->createBackend();
    $name = 'flood_test_threshold';

    $this->assertFalse($flood->isAllowed($name, 0, 3600, 'a'));
    $this->assertTrue($flood->isAllowed($name, 1, 3600, 'a'));

    for ($i = 0; $i < 25; $i++) {
      $flood->register($name, 3600, 'a');
    }
    $this->assertFalse($flood->isAllowed($name, 25, 3600, 'a'));
    $this->assertTrue($flood->isAllowed($name, 26, 3600, 'a'));

    // Other identifiers and other events are unaffected.
    $this->assertTrue($flood->isAllowed($name, 1, 3600, 'b'));
    $this->assertTrue($flood->isAllowed($name . '_other', 1, 3600, 'a'));

    // Clearing one identifier leaves the others alone.
    $flood->register($name, 3600, 'b');
    $flood->clear($name, 'b');
    $this->assertTrue($flood->isAllowed($name, 1, 3600, 'b'));
    $this->assertFalse($flood->isAllowed($name, 25, 3600, 'a'));
  }

  /**
   * Tests that events stop counting once their window has passed.
   */
  public function testWindow() {
    $flood = $this->createBackend();
    $name = 'flood_test_window';

    // An event registered with an expired window never counts, and nothing
    // has to run for that to happen.
    $flood->register($name, -1, 'a');
    $this->assertTrue($flood->isAllowed($name, 1, 3600, 'a'));

    $flood->register($name, 60, 'a');
    $this->assertFalse($flood->isAllowed($name, 1, 3600, 'a'));
    // Garbage collection is a no-op; a live event stays counted.
    $flood->garbageCollection();
    $this->assertFalse($flood->isAllowed($name, 1, 3600, 'a'));

    // Checking with a shorter window than the event was registered with only
    // counts events inside that window.
    $this->now += 30;
    $this->assertFalse($flood->isAllowed($name, 1, 3600, 'a'));
    $this->assertTrue($flood->isAllowed($name, 1, 10, 'a'));

    // Once the registration window has passed the event is gone for good,
    // whatever window is asked about.
    $this->now += 31;
    $this->assertTrue($flood->isAllowed($name, 1, 3600, 'a'));

    // A newer event keeps the older, expired, one from being resurrected.
    $flood->register($name, 60, 'a');
    $this->assertFalse($flood->isAllowed($name, 1, 3600, 'a'));
    $this->assertTrue($flood->isAllowed($name, 2, 3600, 'a'));
  }

  /**
   * Tests that the item is stored with an absolute expiration for long windows.
   */
  public function testLongWindow() {
    $flood = $this->createBackend();
    $name = 'flood_test_long_window';

    // Relative expirations above 30 days are treated by memcached as absolute
    // timestamps; passing one through unchanged would expire the item at once.
    $flood->register($name, 60 * 60 * 24 * 31, 'a');
    $this->assertFalse($flood->isAllowed($name, 1, 3600, 'a'));
    $this->assertNotFalse($this->container->get('memcache.factory')->get('flood')->get('flood:' . $name . ':a'));
  }

  /**
   * Tests clearing by identifier prefix.
   */
  public function testClearByPrefix() {
    $flood = $this->createBackend();
    $name = 'flood_test_prefix';
    $other = 'flood_test_prefix_other';

    $flood->register($name, 3600, '5-127.0.0.1');
    $flood->register($name, 3600, '5-10.0.0.1');
    $flood->register($name, 3600, '55-127.0.0.1');
    $flood->register($name, 3600, '127.0.0.1');
    $flood->register($other, 3600, '5-127.0.0.1');

    $this->assertFalse($flood->isAllowed($name, 1, 3600, '5-127.0.0.1'));
    $this->assertFalse($flood->isAllowed($name, 1, 3600, '5-10.0.0.1'));

    $flood->clearByPrefix($name, '5');

    // Everything under the prefix for that event is gone.
    $this->assertTrue($flood->isAllowed($name, 1, 3600, '5-127.0.0.1'));
    $this->assertTrue($flood->isAllowed($name, 1, 3600, '5-10.0.0.1'));
    // Other prefixes, unprefixed identifiers and other events are untouched.
    $this->assertFalse($flood->isAllowed($name, 1, 3600, '55-127.0.0.1'));
    $this->assertFalse($flood->isAllowed($name, 1, 3600, '127.0.0.1'));
    $this->assertFalse($flood->isAllowed($other, 1, 3600, '5-127.0.0.1'));

    // Clearing a prefix nothing was registered under is harmless, and an
    // identifier registered after the clear is tracked again.
    $flood->clearByPrefix($name, 'unknown');
    $flood->register($name, 3600, '5-127.0.0.1');
    $this->assertFalse($flood->isAllowed($name, 1, 3600, '5-127.0.0.1'));
    $flood->clearByPrefix($name, '5');
    $this->assertTrue($flood->isAllowed($name, 1, 3600, '5-127.0.0.1'));
  }

  /**
   * Tests that the identifier defaults to the client IP address.
   */
  public function testDefaultIdentifier() {
    $flood = $this->createBackend();
    $name = 'flood_test_ip';

    $request = Request::create('/', 'GET', [], [], [], ['REMOTE_ADDR' => '192.0.2.7']);
    $this->container->get('request_stack')->push($request);
    try {
      $flood->register($name);
      $this->assertFalse($flood->isAllowed($name, 1));
      $this->assertFalse($flood->isAllowed($name, 1, 3600, '192.0.2.7'));
      $this->assertTrue($flood->isAllowed($name, 1, 3600, '192.0.2.8'));
      $flood->clear($name);
      $this->assertTrue($flood->isAllowed($name, 1));
    }
    finally {
      $this->container->get('request_stack')->pop();
    }
  }

  /**
   * Tests that events registered by concurrent requests are never lost.
   */
  public function testConcurrentRegister() {
    if (!class_exists('Memcached')) {
      $this->markTestSkipped('Atomic updates need the Memcached extension.');
    }
    $host = getenv('MEMCACHED_HOST') ?: '127.0.0.1:11211';
    [$hostname, $port] = explode(':', $host) + [1 => 11211];
    $client = new RacingMemcached();
    $client->addServer($hostname, (int) $port);
    $client->event = ['time' => $this->now, 'expire' => $this->now + 3600];

    $time = $this->createMock(TimeInterface::class);
    $time->method('getRequestTime')->willReturnCallback(function () {
      return $this->now;
    });
    $flood = new MemcacheFloodBackend(
      new MemcachedDriver($this->container->get('memcache.settings'), $client, 'flood'),
      $this->container->get('request_stack'),
      $time
    );
    $name = 'flood_test_concurrent';

    // Another request creates the item between this one finding it missing
    // and adding it: both events must survive.
    $client->races = 1;
    $flood->register($name, 3600, 'a');
    $this->assertSame(0, $client->races);
    $this->assertFalse($flood->isAllowed($name, 2, 3600, 'a'));
    $this->assertTrue($flood->isAllowed($name, 3, 3600, 'a'));

    // Another request changes the item between this one reading it and
    // writing it back, twice in a row: all four events must survive.
    $client->races = 2;
    $flood->register($name, 3600, 'a');
    $this->assertSame(0, $client->races);
    $this->assertFalse($flood->isAllowed($name, 5, 3600, 'a'));
    $this->assertTrue($flood->isAllowed($name, 6, 3600, 'a'));

    // The same holds for the prefix index used by clearByPrefix().
    $client->races = 2;
    $flood->register($name, 3600, '7-a');
    $this->assertSame(0, $client->races);
    $flood->clearByPrefix($name, '7');
    $this->assertTrue($flood->isAllowed($name, 1, 3600, '7-a'));
  }

  /**
   * Tests that per-request statistics do not cost the atomic update.
   *
   * With $settings['memcache']['collect_statistics'] on, the factory hands out
   * a decorated driver; the backend must still reach the client underneath so
   * that concurrent registrations keep using compare-and-swap.
   */
  public function testConcurrentRegisterWithStatisticsDecorator() {
    if (!class_exists('Memcached')) {
      $this->markTestSkipped('Atomic updates need the Memcached extension.');
    }
    $host = getenv('MEMCACHED_HOST') ?: '127.0.0.1:11211';
    [$hostname, $port] = explode(':', $host) + [1 => 11211];
    $client = new RacingMemcached();
    $client->addServer($hostname, (int) $port);
    $client->event = ['time' => $this->now, 'expire' => $this->now + 3600];

    $time = $this->createMock(TimeInterface::class);
    $time->method('getRequestTime')->willReturnCallback(function () {
      return $this->now;
    });
    $statistics = $this->container->get('memcache.request_statistics');
    $driver = new StatisticsDriverDecorator(
      new MemcachedDriver($this->container->get('memcache.settings'), $client, 'flood'),
      $statistics,
      'flood'
    );
    $flood = new MemcacheFloodBackend($driver, $this->container->get('request_stack'), $time);
    $name = 'flood_test_decorated';

    // Without the client the update would be a plain read and write, the
    // race would go unnoticed and the other request's event would be lost.
    $client->races = 2;
    $flood->register($name, 3600, 'a');
    $this->assertSame(0, $client->races, 'The compare-and-swap retries were used.');
    $this->assertFalse($flood->isAllowed($name, 3, 3600, 'a'));
    $this->assertTrue($flood->isAllowed($name, 4, 3600, 'a'));
    $this->assertFalse($statistics->isEmpty(), 'Operations went through the decorator.');
  }

}
