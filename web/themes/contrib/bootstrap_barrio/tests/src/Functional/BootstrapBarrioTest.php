<?php

declare(strict_types=1);

namespace Drupal\Tests\bootstrap_barrio\Functional;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Drupal\Tests\BrowserTestBase;

/**
 * Tests the Bootstrap Barrio theme.
 *
 * @group bootstrap_barrio
 */
#[Group('bootstrap_barrio')]
#[RunTestsInSeparateProcesses]
class BootstrapBarrioTest extends BrowserTestBase {

  /**
   * Modules to enable.
   *
   * Install the shortcut module so that bootstrap_barrio.settings has its schema
   * checked. There's currently no way for Bootstrap Barrio to provide a default
   * and have valid configuration as themes cannot react to a module install.
   *
   * @var string[]
   */
  protected static $modules = [
    'shortcut',
    'node',
    'block',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'bootstrap_barrio';

  /**
   * Tests Bootstrap Barrio's configuration schema.
   */
  public function testConfigSchema(): void {
    $this->drupalLogin($this->rootUser);
    $this->drupalGet('admin/appearance/settings/' . $this->defaultTheme);
    $this->submitForm([], 'Save configuration');
    $this->assertSession()->statusCodeEquals(200);
  }

  /**
   * Tests that the theme renders the front page correctly.
   */
  public function testFrontPage(): void {
    $this->drupalGet('<front>');
    $this->assertSession()->statusCodeEquals(200);

    // Check for basic HTML structure.
    $this->assertSession()->elementExists('css', 'html');
    $this->assertSession()->elementExists('css', 'body');
  }

  /**
   * Tests that theme settings page loads correctly.
   */
  public function testThemeSettingsPage(): void {
    $this->drupalLogin($this->rootUser);
    $this->drupalGet('admin/appearance/settings/' . $this->defaultTheme);
    $this->assertSession()->statusCodeEquals(200);

    // Check for Bootstrap settings vertical tabs.
    $this->assertSession()->pageTextContains('Bootstrap settings');
  }

  /**
   * Tests that color settings are available.
   */
  public function testColorSettings(): void {
    $this->drupalLogin($this->rootUser);
    $this->drupalGet('admin/appearance/settings/' . $this->defaultTheme);

    // Check for color scheme settings.
    $this->assertSession()->pageTextContains('Colors');
    $this->assertSession()->fieldExists('bootstrap_barrio_enable_color');
  }

  /**
   * Tests that library settings are available.
   */
  public function testLibrarySettings(): void {
    $this->drupalLogin($this->rootUser);
    $this->drupalGet('admin/appearance/settings/' . $this->defaultTheme);

    // Check for library selection.
    $this->assertSession()->pageTextContains('Load library');
  }

  /**
   * Tests that layout settings are available.
   */
  public function testLayoutSettings(): void {
    $this->drupalLogin($this->rootUser);
    $this->drupalGet('admin/appearance/settings/' . $this->defaultTheme);

    // These settings should be present.
    $this->assertSession()->fieldExists('bootstrap_barrio_fluid_container');
  }

  /**
   * Tests that navbar settings are available.
   */
  public function testNavbarSettings(): void {
    $this->drupalLogin($this->rootUser);
    $this->drupalGet('admin/appearance/settings/' . $this->defaultTheme);

    // Navigation settings should be available.
    $this->assertSession()->pageTextContains('Navigation');
  }

  /**
   * Tests that form settings are available.
   */
  public function testFormSettings(): void {
    $this->drupalLogin($this->rootUser);
    $this->drupalGet('admin/appearance/settings/' . $this->defaultTheme);

    // Form settings should be available.
    $this->assertSession()->pageTextContains('Forms');
  }

  /**
   * Tests body classes based on layout.
   */
  public function testBodyClasses(): void {
    $this->drupalGet('<front>');

    // Body should have layout class.
    $body = $this->assertSession()->elementExists('css', 'body');
    $classes = $body->getAttribute('class');

    // Should have a layout-related class.
    $this->assertMatchesRegularExpression('/layout-(no-sidebars|one-sidebar|two-sidebars)/', $classes);
  }

  /**
   * Tests that regions can be rendered.
   */
  public function testRegionsRender(): void {
    // Place a block in the content region.
    $this->drupalPlaceBlock('system_main_block', ['region' => 'content']);

    $this->drupalGet('<front>');
    $this->assertSession()->statusCodeEquals(200);
  }

  /**
   * Tests saving color scheme settings.
   */
  public function testSaveColorScheme(): void {
    $this->drupalLogin($this->rootUser);
    $this->drupalGet('admin/appearance/settings/' . $this->defaultTheme);

    // Enable color scheme.
    $edit = [
      'bootstrap_barrio_enable_color' => TRUE,
    ];
    $this->submitForm($edit, 'Save configuration');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('The configuration options have been saved.');
  }

  /**
   * Tests saving navbar configuration.
   */
  public function testSaveNavbarConfig(): void {
    $this->drupalLogin($this->rootUser);
    $this->drupalGet('admin/appearance/settings/' . $this->defaultTheme);

    $edit = [
      'bootstrap_barrio_navbar_position' => 'fixed-top',
    ];
    $this->submitForm($edit, 'Save configuration');
    $this->assertSession()->statusCodeEquals(200);
  }

  /**
   * Tests that maintenance page renders with theme.
   */
  public function testMaintenancePage(): void {
    // Enable maintenance mode.
    \Drupal::state()->set('system.maintenance_mode', TRUE);

    $this->drupalGet('<front>');

    // Anonymous users should see maintenance page.
    $this->assertSession()->statusCodeEquals(503);

    // Disable maintenance mode.
    \Drupal::state()->set('system.maintenance_mode', FALSE);
  }

  /**
   * Tests that 404 page renders correctly.
   */
  public function testNotFoundPage(): void {
    $this->drupalGet('this-page-does-not-exist-' . $this->randomMachineName());
    $this->assertSession()->statusCodeEquals(404);
  }

  /**
   * Tests that 403 page renders correctly.
   */
  public function testAccessDeniedPage(): void {
    $this->drupalGet('admin');
    $this->assertSession()->statusCodeEquals(403);
  }

  /**
   * Tests viewport meta tag.
   */
  public function testViewportMetaTag(): void {
    $this->drupalGet('<front>');

    // Check for viewport meta tag.
    $this->assertSession()->elementAttributeContains(
      'css',
      'meta[name="viewport"]',
      'content',
      'width=device-width'
    );
  }

}
