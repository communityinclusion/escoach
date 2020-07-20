<?php

namespace Drupal\Tests\es_userform\Functional;

use Drupal\Core\Url;
use Drupal\Tests\BrowserTestBase;

/**
 * Verify tool block menu items and operability of all our routes.
 *
 * @group es_userform
 * @group examples
 *
 * @ingroup es_userform
 */
class EsUserformMenuTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = ['es_userform'];

  /**
   * The installation profile to use with this test.
   *
   * @var string
   */
  protected $profile = 'minimal';

  /**
   * Tests links.
   */
  public function testEsUserformLinks() {
    // Login a user that can access content.
    $this->drupalLogin(
      $this->createUser(['access content', 'access user profiles'])
    );

    $assertion = $this->assertSession();

    // Routes with menu links, and their form buttons.
    $routes_with_menu_links = [
      'es_userform.description' => [],
      'es_userform.simplest' => [],
      'es_userform.autotextfields' => ['Click Me'],
      'es_userform.submit_driven_ajax' => ['Submit'],
      'es_userform.dependent_dropdown' => ['Submit'],
      'es_userform.dynamic_form_sections' => ['Choose'],
      'es_userform.wizard' => ['Next step'],
      'es_userform.wizardnojs' => ['Next step'],
      'es_userform.ajax_link_render' => [],
      'es_userform.autocomplete_user' => ['Submit'],
    ];

    // Ensure the links appear in the tools menu sidebar.
    $this->drupalGet('');
    foreach (array_keys($routes_with_menu_links) as $route) {
      $assertion->linkByHrefExists(Url::fromRoute($route)->getInternalPath());
    }

    // All our routes with their form buttons.
    $routes = [
      'es_userform.ajax_link_callback' => [],
    ];

    // Go to all the routes and click all the buttons.
    $routes = array_merge($routes_with_menu_links, $routes);
    foreach ($routes as $route => $buttons) {
      $url = Url::fromRoute($route);
      if ($route == 'es_userform.ajax_link_callback') {
        $url = Url::fromRoute($route, ['nojs' => 'nojs']);
      }
      $this->drupalGet($url);
      $assertion->statusCodeEquals(200);
      foreach ($buttons as $button) {
        $this->drupalPostForm($url, [], $button);
        $assertion->statusCodeEquals(200);
      }
    }
  }

}
