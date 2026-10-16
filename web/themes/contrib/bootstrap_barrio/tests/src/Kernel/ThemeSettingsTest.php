<?php

declare(strict_types=1);

namespace Drupal\Tests\bootstrap_barrio\Kernel;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Drupal\KernelTests\KernelTestBase;

/**
 * Tests Bootstrap Barrio theme settings.
 *
 * @group bootstrap_barrio
 */
#[Group('bootstrap_barrio')]
#[RunTestsInSeparateProcesses]
class ThemeSettingsTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
  ];

  /**
   * The theme handler.
   *
   * @var \Drupal\Core\Extension\ThemeHandlerInterface
   */
  protected $themeHandler;

  /**
   * The theme installer.
   *
   * @var \Drupal\Core\Extension\ThemeInstallerInterface
   */
  protected $themeInstaller;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installConfig(['system']);
    $this->themeHandler = $this->container->get('theme_handler');
    $this->themeInstaller = $this->container->get('theme_installer');
    $this->themeInstaller->install(['bootstrap_barrio']);
  }

  /**
   * Tests that the theme is installed correctly.
   */
  public function testThemeInstalled(): void {
    $this->assertTrue($this->themeHandler->themeExists('bootstrap_barrio'));
  }

  /**
   * Tests that theme info is correct.
   */
  public function testThemeInfo(): void {
    $themes = $this->themeHandler->listInfo();
    $this->assertArrayHasKey('bootstrap_barrio', $themes);

    $theme = $themes['bootstrap_barrio'];
    $this->assertEquals('Bootstrap Barrio', $theme->info['name']);
    $this->assertEquals('theme', $theme->info['type']);
  }

  /**
   * Tests that theme regions are defined.
   */
  public function testThemeRegions(): void {
    $themes = $this->themeHandler->listInfo();
    $theme = $themes['bootstrap_barrio'];
    $regions = $theme->info['regions'];

    // Test essential regions exist.
    $expected_regions = [
      'top_header',
      'header',
      'primary_menu',
      'secondary_menu',
      'content',
      'sidebar_first',
      'sidebar_second',
      'footer_first',
      'footer_second',
      'footer_third',
      'footer_fourth',
      'footer_fifth',
      'breadcrumb',
      'highlighted',
      'featured_top',
      'featured_bottom_first',
      'featured_bottom_second',
      'featured_bottom_third',
    ];

    foreach ($expected_regions as $region) {
      $this->assertArrayHasKey($region, $regions, "Region '$region' should exist");
    }
  }

  /**
   * Tests default theme settings values.
   */
  public function testDefaultThemeSettings(): void {
    // Get theme settings.
    $config = $this->config('bootstrap_barrio.settings');

    // Theme should have settings available.
    $this->assertNotNull($config);
  }

  /**
   * Tests that theme libraries are defined.
   */
  public function testThemeLibraries(): void {
    $themes = $this->themeHandler->listInfo();
    $theme = $themes['bootstrap_barrio'];

    // Check that libraries are defined.
    $this->assertArrayHasKey('libraries', $theme->info);
    $this->assertContains('bootstrap_barrio/global-styling', $theme->info['libraries']);
  }

  /**
   * Tests that libraries-extend is properly configured.
   */
  public function testLibrariesExtend(): void {
    $themes = $this->themeHandler->listInfo();
    $theme = $themes['bootstrap_barrio'];

    $this->assertArrayHasKey('libraries-extend', $theme->info);

    $libraries_extend = $theme->info['libraries-extend'];

    // Test that core libraries are extended.
    $extended_libraries = [
      'core/drupal.dropbutton',
      'file/drupal.file',
      'node/drupal.node.preview',
      'core/drupal.message',
      'core/drupal.tabledrag',
    ];

    foreach ($extended_libraries as $library) {
      $this->assertArrayHasKey($library, $libraries_extend, "Library '$library' should be extended");
    }
  }

  /**
   * Tests Drupal core version requirement.
   */
  public function testCoreVersionRequirement(): void {
    $themes = $this->themeHandler->listInfo();
    $theme = $themes['bootstrap_barrio'];

    $this->assertArrayHasKey('core_version_requirement', $theme->info);
    // Theme requires Drupal 11.2+.
    $this->assertStringContainsString('11', $theme->info['core_version_requirement']);
  }

}
