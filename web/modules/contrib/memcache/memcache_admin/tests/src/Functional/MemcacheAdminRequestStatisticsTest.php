<?php

namespace Drupal\Tests\memcache_admin\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Tests the per-page memcache statistics memcache_admin appends to pages.
 *
 * @group memcache
 *
 * @see https://www.drupal.org/project/memcache/issues/2996054
 */
class MemcacheAdminRequestStatisticsTest extends BrowserTestBase {

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

    $host = getenv('MEMCACHED_HOST') ?: '127.0.0.1:11211';
    $settings['settings']['memcache'] = (object) [
      'value' => [
        'servers' => [$host => 'default'],
        'bins' => ['default' => 'default'],
        // Keep this site's keys apart from other runs sharing the daemon.
        'key_prefix' => $this->databasePrefix,
        'collect_statistics' => TRUE,
      ],
      'required' => TRUE,
    ];
    // Serve the site's caches from memcache so that page requests produce
    // operations to report on.
    $settings['settings']['cache'] = (object) [
      'value' => ['default' => 'cache.backend.memcache'],
      'required' => TRUE,
    ];
    $this->writeSettings($settings);
  }

  /**
   * Tests that the statistics table is appended for permitted users only.
   */
  public function testStatisticsAppendedToPage(): void {
    // This has to happen before the first request to the test site: the test
    // runner writes configuration through its own database-backed caches,
    // while the site serves its caches from memcache, so a later change would
    // not be seen there.
    $this->config('memcache_admin.settings')->set('show_memcache_statistics', TRUE)->save();

    // Without the permission the table is never shown.
    $this->drupalLogin($this->drupalCreateUser());
    $this->drupalGet('<front>');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->elementNotExists('css', '#memcache-devel');

    $this->drupalLogin($this->drupalCreateUser(['access memcache statistics']));
    $this->drupalGet('<front>');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->elementExists('css', '#memcache-devel');
    $this->assertSession()->elementTextContains('css', '#memcache-devel', 'Memcache statistics');
    // Every page load reads cache items, so the totals must list getMulti.
    $this->assertSession()->elementTextContains('css', '#memcache-devel', 'getMulti');
    $this->assertSession()->elementTextContains('css', '#memcache-devel', 'hit');
  }

}
