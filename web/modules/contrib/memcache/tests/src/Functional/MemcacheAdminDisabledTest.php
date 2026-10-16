<?php

namespace Drupal\Tests\memcache\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Tests that memcache_admin copes with memcache being disabled in settings.
 *
 * Empty 'servers' and 'bins' arrays switch memcache off for an environment.
 * The per-page statistics and the statistics report must then stay out of the
 * way instead of asking the driver factory for a connection that cannot exist.
 *
 * @group memcache
 *
 * @see \Drupal\memcache_admin\EventSubscriber\MemcacheAdminSubscriber
 * @see \Drupal\memcache_admin\Controller\MemcacheStatisticsController
 */
class MemcacheAdminDisabledTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['memcache', 'memcache_admin'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $settings['settings']['memcache'] = (object) [
      'value' => ['servers' => [], 'bins' => []],
      'required' => TRUE,
    ];
    $this->writeSettings($settings);

    // Per-page statistics are appended to every HTML response for a user with
    // this permission, so with memcache disabled every page would fail.
    $this->config('memcache_admin.settings')->set('show_memcache_statistics', TRUE)->save();
    $this->drupalLogin($this->drupalCreateUser(['access memcache statistics']));
  }

  /**
   * Tests that pages render and the report explains that memcache is off.
   */
  public function testPagesRenderWhenDisabled() {
    $this->drupalGet('<front>');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextNotContains('Memcache statistics');

    $this->drupalGet('admin/reports/memcache');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Memcache is disabled');
  }

}
