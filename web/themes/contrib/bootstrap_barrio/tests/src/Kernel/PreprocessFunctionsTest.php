<?php

declare(strict_types=1);

namespace Drupal\Tests\bootstrap_barrio\Kernel;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Drupal\Core\Template\Attribute;
use Drupal\KernelTests\KernelTestBase;

/**
 * Tests Bootstrap Barrio theme preprocess functions.
 *
 * @group bootstrap_barrio
 */
#[Group('bootstrap_barrio')]
#[RunTestsInSeparateProcesses]
class PreprocessFunctionsTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'node',
    'path_alias',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installConfig(['system']);
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('path_alias');

    $this->container->get('theme_installer')->install(['bootstrap_barrio']);

    // Include the theme file.
    require_once $this->root . '/themes/contrib/bootstrap_barrio/bootstrap_barrio.theme';
  }

  /**
   * Tests bootstrap_barrio_preprocess_html() adds layout classes.
   */
  public function testPreprocessHtmlLayoutClasses(): void {
    $variables = [
      'attributes' => [],
      'page' => [],
      'html_attributes' => new Attribute(),
      'path_info' => [],
    ];

    bootstrap_barrio_preprocess_html($variables);

    // Should have layout class.
    $this->assertContains('layout-no-sidebars', $variables['attributes']['class']);
  }

  /**
   * Tests layout classes with sidebar_first.
   */
  public function testPreprocessHtmlWithSidebarFirst(): void {
    $variables = [
      'attributes' => [],
      'page' => [
        'sidebar_first' => ['#markup' => 'Content'],
      ],
      'html_attributes' => new Attribute(),
      'path_info' => [],
    ];

    bootstrap_barrio_preprocess_html($variables);

    $this->assertContains('layout-one-sidebar', $variables['attributes']['class']);
    $this->assertContains('layout-sidebar-first', $variables['attributes']['class']);
  }

  /**
   * Tests layout classes with sidebar_second.
   */
  public function testPreprocessHtmlWithSidebarSecond(): void {
    $variables = [
      'attributes' => [],
      'page' => [
        'sidebar_second' => ['#markup' => 'Content'],
      ],
      'html_attributes' => new Attribute(),
      'path_info' => [],
    ];

    bootstrap_barrio_preprocess_html($variables);

    $this->assertContains('layout-one-sidebar', $variables['attributes']['class']);
    $this->assertContains('layout-sidebar-second', $variables['attributes']['class']);
  }

  /**
   * Tests layout classes with both sidebars.
   */
  public function testPreprocessHtmlWithBothSidebars(): void {
    $variables = [
      'attributes' => [],
      'page' => [
        'sidebar_first' => ['#markup' => 'Content'],
        'sidebar_second' => ['#markup' => 'Content'],
      ],
      'html_attributes' => new Attribute(),
      'path_info' => [],
    ];

    bootstrap_barrio_preprocess_html($variables);

    $this->assertContains('layout-two-sidebars', $variables['attributes']['class']);
  }

  /**
   * Tests featured_top class.
   */
  public function testPreprocessHtmlFeaturedTop(): void {
    $variables = [
      'attributes' => [],
      'page' => [
        'featured_top' => ['#markup' => 'Content'],
      ],
      'html_attributes' => new Attribute(),
      'path_info' => [],
    ];

    bootstrap_barrio_preprocess_html($variables);

    $this->assertContains('has-featured-top', $variables['attributes']['class']);
  }

  /**
   * Tests bootstrap_barrio_preprocess_page() sets page variables.
   */
  public function testPreprocessPage(): void {
    $variables = [
      '#attached' => ['library' => []],
      'page' => [],
    ];

    bootstrap_barrio_preprocess_page($variables);

    // Should have navbar attributes.
    $this->assertArrayHasKey('navbar_top_attributes', $variables);
    $this->assertArrayHasKey('navbar_attributes', $variables);
    $this->assertArrayHasKey('content_attributes', $variables);
    $this->assertArrayHasKey('sidebar_first_attributes', $variables);
    $this->assertArrayHasKey('sidebar_second_attributes', $variables);
  }

  /**
   * Tests bootstrap_barrio_preprocess_menu_local_tasks() sets nav style.
   */
  public function testPreprocessMenuLocalTasks(): void {
    $variables = [];

    bootstrap_barrio_preprocess_menu_local_tasks($variables);

    // Default should be nav-tabs.
    $this->assertEquals('nav-tabs', $variables['nav_style']);
  }

  /**
   * Tests bootstrap_barrio_preprocess_table() adds table classes.
   */
  public function testPreprocessTable(): void {
    $variables = [];

    bootstrap_barrio_preprocess_table($variables);

    // Should have table_class, hover, and thead_class variables.
    $this->assertArrayHasKey('table_class', $variables);
    $this->assertArrayHasKey('hover', $variables);
    $this->assertArrayHasKey('thead_class', $variables);
  }

  /**
   * Tests bootstrap_barrio_preprocess_views_view_table().
   */
  public function testPreprocessViewsViewTable(): void {
    $variables = [];

    bootstrap_barrio_preprocess_views_view_table($variables);

    // Should have same variables as table.
    $this->assertArrayHasKey('table_class', $variables);
    $this->assertArrayHasKey('hover', $variables);
    $this->assertArrayHasKey('thead_class', $variables);
  }

  /**
   * Tests bootstrap_barrio_preprocess_menu() adds clearfix.
   */
  public function testPreprocessMenu(): void {
    $variables = [
      'attributes' => ['class' => []],
    ];

    bootstrap_barrio_preprocess_menu($variables);

    $this->assertContains('clearfix', $variables['attributes']['class']);
  }

  /**
   * Tests bootstrap_barrio_preprocess_status_messages().
   */
  public function testPreprocessStatusMessages(): void {
    $variables = [
      'message_list' => [
        'status' => ['Test message'],
        'warning' => ['Test warning'],
        'error' => ['Test error'],
      ],
    ];

    bootstrap_barrio_preprocess_status_messages($variables);

    // Should have title_ids for each message type.
    $this->assertArrayHasKey('title_ids', $variables);
    $this->assertArrayHasKey('status', $variables['title_ids']);
    $this->assertArrayHasKey('warning', $variables['title_ids']);
    $this->assertArrayHasKey('error', $variables['title_ids']);
  }

}
