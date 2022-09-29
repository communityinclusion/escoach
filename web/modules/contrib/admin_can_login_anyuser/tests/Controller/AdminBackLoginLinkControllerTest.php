<?php

namespace Drupal\admin_can_login_anyuser\Tests;

use Drupal\simpletest\WebTestBase;

/**
 * Provides automated tests for the admin_can_login_anyuser module.
 */
class AdminBackLoginLinkControllerTest extends WebTestBase {

  /**
   * {@inheritdoc}
   */
  public static function getInfo() {
    return [
      'name' => "admin_can_login_anyuser AdminBackLoginLinkController's controller functionality",
      'description' => 'Test Unit for module admin_can_login_anyuser and controller AdminBackLoginLinkController.',
      'group' => 'Other',
    ];
  }

  /**
   * Tests admin_can_login_anyuser functionality.
   */
  public function testAdminBackLoginLinkController() {
    // Check that the basic functions of module admin_can_login_anyuser.
    $this->assertEquals(TRUE, TRUE, 'Test Unit Generated via Drupal Console.');
  }

}
