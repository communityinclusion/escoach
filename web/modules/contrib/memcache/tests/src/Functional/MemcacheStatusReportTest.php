<?php

namespace Drupal\Tests\memcache\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Tests that memcache server problems surface on the status report page.
 *
 * @group memcache
 *
 * @see memcache_requirements()
 */
class MemcacheStatusReportTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['memcache'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * An address that no memcache server listens on.
   */
  const UNREACHABLE = 'memcache-unreachable.invalid:11211';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->drupalLogin($this->drupalCreateUser(['administer site configuration']));
  }

  /**
   * Points the test site's memcache settings at the given servers.
   *
   * @param array $servers
   *   The cluster name, keyed by server address.
   */
  protected function setMemcacheServers(array $servers) {
    $this->writeMemcacheSettings([
      'servers' => $servers,
      'bins' => ['default' => 'default'],
    ]);
  }

  /**
   * Writes the test site's $settings['memcache'] array.
   *
   * @param array $memcache_settings
   *   The memcache settings; a key prefix is added to isolate the test run.
   */
  protected function writeMemcacheSettings(array $memcache_settings) {
    $settings['settings']['memcache'] = (object) [
      'value' => $memcache_settings + ['key_prefix' => $this->databasePrefix],
      'required' => TRUE,
    ];

    $this->writeSettings($settings);
  }

  /**
   * Tests that a working memcache server is reported as available.
   */
  public function testReachableServerReported() {
    $host = getenv('MEMCACHED_HOST') ?: '127.0.0.1:11211';
    $this->setMemcacheServers([$host => 'default']);

    $this->drupalGet('admin/reports/status');

    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Memcache servers');
    $this->assertSession()->pageTextContains('1 server available');
    $this->assertSession()->pageTextContains('Connected to ' . $host);
    $this->assertSession()->pageTextContains('Memcache memory');
    $this->assertSession()->pageTextMatches('/' . preg_quote($host, '/') . ': [\d.]+ \w+ of [\d.]+ \w+ used \([\d.]+%\), [\d,]+ evictions/');
  }

  /**
   * Tests that an unreachable memcache server is reported as an error.
   *
   * This is the case the module was silent about: \Memcached::addServer()
   * accepts a host that does not resolve, so nothing before the status report
   * notices that the cache is not working.
   */
  public function testUnreachableServerReported() {
    $this->setMemcacheServers([self::UNREACHABLE => 'default']);

    $this->drupalGet('admin/reports/status');

    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Memcache servers');
    $this->assertSession()->pageTextContains('1 of 1 unreachable');
    $this->assertSession()->pageTextContains('Failed to connect to the memcache server(s) at ' . self::UNREACHABLE);
  }

  /**
   * Tests that memcache switched off in settings is reported, not an error.
   *
   * Empty 'servers' and 'bins' arrays are how a local or development
   * environment disables memcache without uninstalling the module.
   */
  public function testDisabledReported() {
    $this->writeMemcacheSettings(['servers' => [], 'bins' => []]);

    $this->drupalGet('admin/reports/status');

    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Memcache');
    $this->assertSession()->pageTextContains('Disabled');
    $this->assertSession()->pageTextNotContains('Extensions not available');
    $this->assertSession()->pageTextNotContains('Memcache servers');
  }

  /**
   * Tests that a healthy server is not masked by an unreachable one.
   */
  public function testMixedServersReported() {
    $host = getenv('MEMCACHED_HOST') ?: '127.0.0.1:11211';
    $this->setMemcacheServers([
      $host => 'default',
      self::UNREACHABLE => 'default',
    ]);

    $this->drupalGet('admin/reports/status');

    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('1 of 2 unreachable');
    $this->assertSession()->pageTextContains(self::UNREACHABLE);
  }

}
