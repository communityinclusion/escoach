<?php

namespace Drupal\Tests\memcache\Kernel;

use Drupal\Core\Site\Settings;
use Drupal\KernelTests\KernelTestBase;
use Drupal\memcache\Driver\MemcacheDriverFactory;
use Drupal\memcache\Driver\MemcachedDriver;
use Drupal\memcache\MemcacheException;
use Drupal\memcache\MemcacheSettings;
use Drupal\memcache_test\Driver\StatslessMemcacheDriverFactory;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests that unreachable memcache servers are reported rather than ignored.
 *
 * Also covers the disabled mode, where $settings['memcache']['servers'] and
 * $settings['memcache']['bins'] are both empty arrays so that the module can
 * stay enabled on an environment that has no memcache at all.
 *
 * @group memcache
 *
 * @see \Drupal\memcache\Driver\MemcacheDriverFactory::checkServers()
 * @see \Drupal\memcache\Driver\MemcacheDriverFactory::isDisabled()
 * @see memcache_requirements()
 */
#[RunTestsInSeparateProcesses]
class MemcacheServerDiagnosticsTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'memcache'];

  /**
   * An address that no memcache server listens on.
   */
  const UNREACHABLE = 'memcache-unreachable.invalid:11211';

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
   * Builds a driver factory for the given server configuration.
   *
   * @param array $servers
   *   The cluster name, keyed by server address.
   * @param string $class
   *   The factory class to instantiate.
   *
   * @return \Drupal\memcache\Driver\MemcacheDriverFactory
   *   The configured factory.
   */
  protected function factoryForServers(array $servers, $class = MemcacheDriverFactory::class) {
    return $this->factoryForSettings([
      'servers' => $servers,
      'bins' => ['default' => 'default'],
    ], $class);
  }

  /**
   * Builds a driver factory for the given $settings['memcache'] array.
   *
   * @param array $settings
   *   The memcache settings; a key prefix is added to isolate the test run.
   * @param string $class
   *   The factory class to instantiate.
   *
   * @return \Drupal\memcache\Driver\MemcacheDriverFactory
   *   The configured factory.
   */
  protected function factoryForSettings(array $settings, $class = MemcacheDriverFactory::class) {
    $this->setSetting('memcache', $settings + ['key_prefix' => $this->databasePrefix]);

    return new $class(new MemcacheSettings(Settings::getInstance()));
  }

  /**
   * Tests that a reachable server is reported as reachable.
   */
  public function testReachableServer() {
    $server = $this->reachableServer();
    $factory = $this->factoryForServers([$server => 'default']);

    $this->assertSame([$server => TRUE], $factory->checkServers());
    $this->assertSame([$server => 'default'], $factory->getServers());
    $this->assertFalse($factory->isDisabled());
  }

  /**
   * Tests that an unreachable server is detected.
   *
   * \Memcached::addServer() accepts a host that does not resolve, so nothing
   * short of an actual round trip notices this.
   */
  public function testUnreachableServer() {
    $factory = $this->factoryForServers([self::UNREACHABLE => 'default']);

    $this->assertSame([self::UNREACHABLE => FALSE], $factory->checkServers());
  }

  /**
   * Tests that one dead server does not mask a healthy one, or vice versa.
   */
  public function testMixedServers() {
    $server = $this->reachableServer();
    $factory = $this->factoryForServers([
      $server => 'default',
      self::UNREACHABLE => 'default',
    ]);

    $this->assertSame([
      $server => TRUE,
      self::UNREACHABLE => FALSE,
    ], $factory->checkServers());
  }

  /**
   * Tests that a server which rejects "stats" is still reported as reachable.
   *
   * A proxy such as twemproxy answers every get and set but closes the
   * connection on "stats", so a probe built on statistics would report a
   * working cache as dead.
   */
  public function testServerWithoutStatsIsReachable() {
    $server = $this->reachableServer();
    $factory = $this->factoryForServers([$server => 'default'], StatslessMemcacheDriverFactory::class);

    // Make sure the double really does withhold statistics.
    $this->assertFalse($factory->get('default')->getMemcache()->getStats());

    $this->assertSame([$server => TRUE], $factory->checkServers());
  }

  /**
   * Tests that the probe still notices a dead server without "stats".
   */
  public function testUnreachableServerWithoutStats() {
    $factory = $this->factoryForServers([self::UNREACHABLE => 'default'], StatslessMemcacheDriverFactory::class);

    $this->assertSame([self::UNREACHABLE => FALSE], $factory->checkServers());
  }

  /**
   * Tests that the status report stays green behind a proxy without "stats".
   */
  public function testRequirementsWithServerWithoutStats() {
    $requirements = $this->runtimeRequirements([$this->reachableServer() => 'default'], NULL, NULL, StatslessMemcacheDriverFactory::class);

    $this->assertArrayHasKey('memcache_servers', $requirements);
    $this->assertSame(REQUIREMENT_OK, $requirements['memcache_servers']['severity']);
    $this->assertSame('1 server available', (string) $requirements['memcache_servers']['value']);
  }

  /**
   * Tests that the status report is happy when memcache is working.
   */
  public function testRequirementsWithReachableServer() {
    $requirements = $this->runtimeRequirements([$this->reachableServer() => 'default']);

    $this->assertArrayHasKey('memcache_servers', $requirements);
    $this->assertSame(REQUIREMENT_OK, $requirements['memcache_servers']['severity']);
  }

  /**
   * Tests that the status report reports an unreachable server as an error.
   */
  public function testRequirementsWithUnreachableServer() {
    $requirements = $this->runtimeRequirements([self::UNREACHABLE => 'default']);

    $this->assertArrayHasKey('memcache_servers', $requirements);
    $this->assertSame(REQUIREMENT_ERROR, $requirements['memcache_servers']['severity']);
    $this->assertStringContainsString(
      self::UNREACHABLE,
      (string) $requirements['memcache_servers']['description']
    );
  }

  /**
   * Tests that empty server and bin settings put the factory in disabled mode.
   *
   * This must hold whether or not a memcache extension is installed: the point
   * of the setting is to switch memcache off for an environment from
   * settings.local.php, and a developer with the extension installed should
   * get the same behaviour as one without it.
   */
  public function testDisabledSettings() {
    $factory = $this->factoryForSettings(['servers' => [], 'bins' => []]);

    $this->assertTrue($factory->isDisabled());
    $this->assertSame(MemcacheDriverFactory::EXTENSION_DISABLED, $factory->getExtension());
    $this->assertSame([], $factory->getServers());
    $this->assertSame([], $factory->checkServers());

    $this->expectException(MemcacheException::class);
    $this->expectExceptionMessage('disabled');
    $factory->get('default');
  }

  /**
   * Tests that disabled mode needs both settings to be explicitly empty.
   *
   * A missing setting means "use the default", not "switch memcache off", so
   * a missing extension keeps being reported instead of silently ignored.
   */
  public function testDisabledNeedsBothSettingsEmpty() {
    $this->assertFalse($this->factoryForSettings([])->isDisabled());
    $this->assertFalse($this->factoryForSettings(['servers' => []])->isDisabled());
    $this->assertFalse($this->factoryForSettings(['bins' => []])->isDisabled());
    $this->assertFalse($this->factoryForSettings([
      'servers' => [],
      'bins' => ['default' => 'default'],
    ])->isDisabled());
  }

  /**
   * Tests that the requirements report disabled mode instead of an error.
   *
   * Without this, an environment that has no memcache extension cannot install
   * a profile that lists the module, and its status report shows an error for
   * a situation the developer configured on purpose.
   */
  public function testRequirementsWhenDisabled() {
    $this->setSetting('memcache', ['servers' => [], 'bins' => []]);
    $this->container->get('module_handler')->loadInclude('memcache', 'install');

    foreach (['install', 'runtime'] as $phase) {
      foreach ([TRUE, FALSE] as $has_extension) {
        $requirements = _memcache_requirements($phase, $has_extension, $has_extension);
        $case = sprintf('%s phase, extension %s', $phase, $has_extension ? 'installed' : 'missing');

        $this->assertArrayHasKey('memcache_disabled', $requirements, $case);
        $this->assertSame(REQUIREMENT_INFO, $requirements['memcache_disabled']['severity'], $case);
        $this->assertArrayNotHasKey('memcache_extension', $requirements, $case);
        $this->assertArrayNotHasKey('memcache_servers', $requirements, $case);
      }
    }
  }

  /**
   * Tests that a missing extension is still an error when not disabled.
   */
  public function testRequirementsWithoutExtension() {
    $this->setSetting('memcache', ['servers' => [$this->reachableServer() => 'default']]);
    $this->container->get('module_handler')->loadInclude('memcache', 'install');

    foreach (['install', 'runtime'] as $phase) {
      $requirements = _memcache_requirements($phase, FALSE, FALSE);

      $this->assertArrayHasKey('memcache_extension', $requirements, $phase);
      $this->assertSame(REQUIREMENT_ERROR, $requirements['memcache_extension']['severity'], $phase);
      $this->assertArrayNotHasKey('memcache_disabled', $requirements, $phase);
    }
  }

  /**
   * Tests that a reachable server reports its statistics.
   */
  public function testServerStats() {
    $server = $this->reachableServer();
    $factory = $this->factoryForServers([
      $server => 'default',
      self::UNREACHABLE => 'default',
    ]);

    $stats = $factory->getServerStats();

    $this->assertSame([$server, self::UNREACHABLE], array_keys($stats));
    $this->assertFalse($stats[self::UNREACHABLE]);
    $this->assertIsArray($stats[$server]);
    $this->assertArrayHasKey('limit_maxbytes', $stats[$server]);
    $this->assertArrayHasKey('bytes', $stats[$server]);
    $this->assertArrayHasKey('evictions', $stats[$server]);
    $this->assertGreaterThan(0, (int) $stats[$server]['limit_maxbytes']);
  }

  /**
   * Tests that the status report shows the memory in use.
   */
  public function testRequirementsReportMemory() {
    $requirements = $this->runtimeRequirements([$this->reachableServer() => 'default']);

    $this->assertArrayHasKey('memcache_memory', $requirements);
    $this->assertContains($requirements['memcache_memory']['severity'], [REQUIREMENT_OK, REQUIREMENT_WARNING]);
    $this->assertStringContainsString('used', (string) $requirements['memcache_memory']['value']);
  }

  /**
   * Tests that memory use is reported per server without evictions.
   */
  public function testRequirementsWithoutEvictions() {
    $server = $this->reachableServer();
    $requirements = $this->runtimeRequirements([$server => 'default'], [
      $server => [
        'limit_maxbytes' => 64 * 1024 * 1024,
        'bytes' => 16 * 1024 * 1024,
        'evictions' => 0,
      ],
    ]);

    $memory = $requirements['memcache_memory'];
    $this->assertSame(REQUIREMENT_OK, $memory['severity']);
    $this->assertSame('16 MB of 64 MB used (25.0%)', (string) $memory['value']);
    $this->assertSame('item_list', $memory['description']['#theme']);
    $this->assertCount(1, $memory['description']['#items']);
    $this->assertSame("$server: 16 MB of 64 MB used (25.0%), 0 evictions", (string) $memory['description']['#items'][0]);
  }

  /**
   * Tests that evictions on any server produce a warning.
   */
  public function testRequirementsWithEvictions() {
    $server = $this->reachableServer();
    $requirements = $this->runtimeRequirements([$server => 'default'], [
      $server => [
        'limit_maxbytes' => 64 * 1024 * 1024,
        'bytes' => 63 * 1024 * 1024,
        'evictions' => 1234,
      ],
      'other:11211' => [
        'limit_maxbytes' => 64 * 1024 * 1024,
        'bytes' => 1024 * 1024,
        'evictions' => 0,
      ],
    ]);

    $memory = $requirements['memcache_memory'];
    $this->assertSame(REQUIREMENT_WARNING, $memory['severity']);
    $this->assertSame('64 MB of 128 MB used (50.0%)', (string) $memory['value']);
    $this->assertStringContainsString('evicted 1,234 items', (string) $memory['description']['#context']['message']);
    $this->assertStringContainsString('report_evictions', (string) $memory['description']['#context']['message']);
    $items = array_map('strval', $memory['description']['#context']['servers']['#items']);
    $this->assertSame([
      "$server: 63 MB of 64 MB used (98.4%), 1,234 evictions",
      'other:11211: 1 MB of 64 MB used (1.6%), 0 evictions',
    ], $items);
  }

  /**
   * Tests that the eviction warning can be turned off in settings.
   */
  public function testRequirementsEvictionWarningDisabled() {
    $server = $this->reachableServer();
    $this->setSetting('memcache', [
      'servers' => [$server => 'default'],
      'bins' => ['default' => 'default'],
      'key_prefix' => $this->databasePrefix,
      'report_evictions' => FALSE,
    ]);
    $factory = $this->factoryWithStats([
      $server => [
        'limit_maxbytes' => 64 * 1024 * 1024,
        'bytes' => 63 * 1024 * 1024,
        'evictions' => 1234,
      ],
    ]);

    $requirements = $this->runtimeRequirements([$server => 'default'], NULL, $factory);

    $memory = $requirements['memcache_memory'];
    $this->assertSame(REQUIREMENT_OK, $memory['severity']);
    $this->assertSame('item_list', $memory['description']['#theme']);
    $this->assertStringContainsString('1,234 evictions', (string) $memory['description']['#items'][0]);
  }

  /**
   * Tests that a server without statistics, such as a proxy, is not an error.
   */
  public function testRequirementsWithoutStats() {
    $server = $this->reachableServer();
    $requirements = $this->runtimeRequirements([$server => 'default'], [$server => FALSE]);

    $memory = $requirements['memcache_memory'];
    $this->assertSame(REQUIREMENT_OK, $memory['severity']);
    $this->assertSame('Not available', (string) $memory['value']);
    $this->assertStringContainsString('twemproxy', (string) $memory['description']);
  }

  /**
   * Builds a driver factory whose server statistics are fixed.
   *
   * @param array $stats
   *   The statistics to report, keyed by server address, as getServerStats()
   *   would return them.
   *
   * @return \Drupal\memcache\Driver\MemcacheDriverFactory
   *   The configured factory.
   */
  protected function factoryWithStats(array $stats) {
    return new class(new MemcacheSettings(Settings::getInstance()), $stats) extends MemcacheDriverFactory {

      /**
       * The statistics to report.
       *
       * @var array
       */
      protected $fixedStats;

      public function __construct(MemcacheSettings $settings, array $stats) {
        parent::__construct($settings);
        $this->fixedStats = $stats;
      }

      /**
       * {@inheritdoc}
       */
      public function getServerStats($timeout = NULL) {
        return $this->fixedStats;
      }

    };
  }

  /**
   * Tests that the extension version rows are labelled as PHP extensions.
   *
   * The values come from phpversion(), which is the client library version and
   * not the memcached server version; the row must say so.
   */
  public function testRequirementsExtensionVersionRows() {
    $requirements = $this->runtimeRequirements([$this->reachableServer() => 'default']);

    // Every row must be renderable: a description on a stray key produces a
    // row with no title or value on the status report.
    foreach ($requirements as $key => $requirement) {
      $this->assertArrayHasKey('title', $requirement, "Requirement '$key' has a title.");
      $this->assertArrayHasKey('value', $requirement, "Requirement '$key' has a value.");
      $this->assertArrayHasKey('severity', $requirement, "Requirement '$key' has a severity.");
    }

    $rows = 0;
    foreach (['memcache', 'memcached'] as $extension) {
      $key = $extension . '_extension_version';
      if (!extension_loaded($extension)) {
        $this->assertArrayNotHasKey($key, $requirements);
        continue;
      }
      $rows++;
      $this->assertSame(phpversion($extension), $requirements[$key]['value']);
      $this->assertStringContainsString('PHP extension', (string) $requirements[$key]['title']);
      $this->assertStringContainsString('not the memcached server', (string) $requirements[$key]['description']);
    }
    $this->assertGreaterThan(0, $rows, 'At least one memcache extension is loaded.');
  }

  /**
   * Tests that a memcached extension too old for the running PHP is an error.
   *
   * Releases of php-memcached before 3.2.0 flip the sign of bare float values
   * on PHP 8.1 and later, which breaks every bin deletion and cache tag
   * invalidation.
   *
   * @see _memcache_requirements_memcached_version()
   * @see https://github.com/php-memcached-dev/php-memcached/issues/500
   */
  public function testMemcachedVersionRequirement() {
    $this->container->get('module_handler')->loadInclude('memcache', 'install');

    // Extension version, PHP_VERSION_ID, expected severity.
    $cases = [
      ['3.1.5', 80100, REQUIREMENT_ERROR],
      ['3.1.5', 80200, REQUIREMENT_ERROR],
      ['3.2.0RC1', 80100, REQUIREMENT_ERROR],
      ['3.2.0', 80100, REQUIREMENT_OK],
      ['3.3.0', 80300, REQUIREMENT_OK],
      // Older PHP releases are not affected.
      ['3.1.5', 70400, REQUIREMENT_OK],
      ['3.1.5', 80000, REQUIREMENT_OK],
    ];

    foreach ($cases as [$version, $php_version_id, $severity]) {
      $requirement = _memcache_requirements_memcached_version($version, $php_version_id);
      $this->assertSame($severity, $requirement['severity'], "memcached $version on PHP_VERSION_ID $php_version_id");
      $this->assertSame($version, $requirement['value']);
      if ($severity === REQUIREMENT_ERROR) {
        $this->assertStringContainsString(MemcachedDriver::MINIMUM_EXTENSION_VERSION, (string) $requirement['description']);
      }
      else {
        $this->assertStringContainsString('not the memcached server', (string) $requirement['description']);
      }
    }

    // The real extension in the test environment must itself be supported,
    // otherwise the status report would be reporting an error.
    $requirements = $this->runtimeRequirements([$this->reachableServer() => 'default']);
    $this->assertSame(REQUIREMENT_OK, $requirements['memcached_extension_version']['severity']);
  }

  /**
   * Tests that the status report round trip stores and reads back a float.
   *
   * A float is used rather than a string because a broken extension build
   * flips the sign of bare floats, which is the case the check exists for.
   */
  public function testRequirementsFloatRoundTrip() {
    $requirements = $this->runtimeRequirements([$this->reachableServer() => 'default']);

    $this->assertSame(REQUIREMENT_OK, $requirements['memcache_servers']['severity']);

    // The same driver the requirements check used must round-trip a float
    // exactly, sign included.
    $driver = $this->container->get('memcache.factory')->get('memcache_requirements');
    $this->assertTrue($driver->set('memcache_requirements_float', -1.5));
    $this->assertSame(-1.5, $driver->get('memcache_requirements_float'));
    $driver->delete('memcache_requirements_float');
  }

  /**
   * Runs memcache_requirements() against the given server configuration.
   *
   * @param array $servers
   *   The cluster name, keyed by server address.
   * @param array|null $stats
   *   Statistics to report instead of asking the servers, keyed by server
   *   address, or NULL to ask the servers.
   * @param \Drupal\memcache\Driver\MemcacheDriverFactory|null $factory
   *   A factory to use instead of building one from $servers.
   * @param string $class
   *   The factory class to instantiate.
   *
   * @return array
   *   The requirements array.
   */
  protected function runtimeRequirements(array $servers, ?array $stats = NULL, ?MemcacheDriverFactory $factory = NULL, $class = MemcacheDriverFactory::class) {
    if (!$factory) {
      $factory = $this->factoryForServers($servers, $class);
      if ($stats !== NULL) {
        $factory = $this->factoryWithStats($stats);
      }
    }
    $this->container->set('memcache.factory', $factory);

    $this->container->get('module_handler')->loadInclude('memcache', 'install');

    return memcache_requirements('runtime');
  }

}
