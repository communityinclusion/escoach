<?php

namespace Drupal\Tests\memcache\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Tests the memcache_admin statistics page when a server is down.
 *
 * @group memcache
 *
 * @see \Drupal\memcache_admin\Controller\MemcacheStatisticsController
 */
class MemcacheStatisticsPageTest extends BrowserTestBase {

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
   * The message the page falls back to when it has no statistics at all.
   */
  const NO_STATISTICS = 'There may be a problem with your Memcache configuration.';

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

    $this->live = getenv('MEMCACHED_HOST') ?: '127.0.0.1:11211';
    $this->drupalLogin($this->drupalCreateUser(['access memcache statistics']));
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
        // Do not let the dead server hold the page up.
        'status_connect_timeout' => 100,
      ],
      'required' => TRUE,
    ];

    $this->writeSettings($settings);
  }

  /**
   * Tests that a healthy server is reported.
   */
  public function testLiveServerStatistics() {
    $this->setMemcacheServers([$this->live => 'default']);

    $this->drupalGet('admin/reports/memcache');

    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Uptime');
    $this->assertSession()->pageTextContains($this->live);
    $this->assertSession()->pageTextNotContains(self::NO_STATISTICS);
  }

  /**
   * Tests that one dead server neither hides the live ones nor goes unnoticed.
   */
  public function testDeadServerIsFlagged() {
    $this->setMemcacheServers([
      $this->live => 'default',
      self::UNREACHABLE => 'default',
    ]);

    $this->drupalGet('admin/reports/memcache');

    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Failed to connect to the memcache server(s) at ' . self::UNREACHABLE);
    $this->assertSession()->pageTextContains('Uptime');
    $this->assertSession()->pageTextContains($this->live);
    $this->assertSession()->pageTextNotContains(self::NO_STATISTICS);
  }

}
