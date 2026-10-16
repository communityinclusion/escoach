<?php

namespace Drupal\Tests\memcache\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Tests the memcache_admin statistics report.
 *
 * @group memcache
 *
 * @see \Drupal\memcache_admin\EventSubscriber\MemcacheServerStatsSubscriber
 */
class MemcacheAdminStatisticsTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['memcache', 'memcache_admin'];

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

    $this->drupalLogin($this->drupalCreateUser(['access memcache statistics']));
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
   * Points the test site's memcache settings at the given servers.
   *
   * @param array $servers
   *   The cluster name, keyed by server address.
   */
  protected function setMemcacheServers(array $servers) {
    $settings['settings']['memcache'] = (object) [
      'value' => [
        'servers' => $servers,
        'bins' => ['default' => 'default'],
        'key_prefix' => $this->databasePrefix,
      ],
      'required' => TRUE,
    ];

    $this->writeSettings($settings);
  }

  /**
   * Tests that a working server's statistics are shown without complaint.
   */
  public function testStatisticsShown() {
    $server = $this->reachableServer();
    $this->setMemcacheServers([$server => 'default']);

    $this->drupalGet('admin/reports/memcache');

    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Uptime');
    $this->assertSession()->pageTextContains($server);
    $this->assertSession()->pageTextNotContains('There may be a problem with your Memcache configuration');
    $this->assertSession()->pageTextNotContains('Failed to connect');
    $this->assertSession()->pageTextNotContains('do not answer the "stats" command');
  }

  /**
   * Tests a server that works but rejects "stats", as twemproxy does.
   *
   * The report cannot show statistics for it, but it must say why instead of
   * claiming the configuration is broken.
   */
  public function testServerWithoutStats() {
    $server = $this->reachableServer();
    $this->setMemcacheServers([$server => 'default']);
    $this->container->get('module_installer')->install(['memcache_test']);

    $this->drupalGet('admin/reports/memcache');

    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('The memcache server(s) at ' . $server . ' answer cache reads and writes but do not answer the "stats" command');
    $this->assertSession()->pageTextContains('twemproxy');
    $this->assertSession()->pageTextNotContains('There may be a problem with your Memcache configuration');
    $this->assertSession()->pageTextNotContains('Failed to connect');
  }

  /**
   * Tests that a server which is actually down is reported as such.
   */
  public function testUnreachableServer() {
    $this->setMemcacheServers([self::UNREACHABLE => 'default']);

    $this->drupalGet('admin/reports/memcache');

    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Failed to connect to the memcache server(s) at ' . self::UNREACHABLE);
    $this->assertSession()->pageTextNotContains('There may be a problem with your Memcache configuration');
    $this->assertSession()->pageTextNotContains('do not answer the "stats" command');
  }

}
