<?php

namespace Drupal\Tests\nodeaccess\Functional;

use Drupal\node\Entity\NodeType;
use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\node\Traits\ContentTypeCreationTrait;
use Drupal\user\Entity\Role;

/**
 * Test the Nodeaccess settings form.
 *
 * @group nodeaccess
 * @covers \Drupal\nodeaccess\Form\SettingsForm
 */
class SettingsFormTest extends BrowserTestBase {

  use ContentTypeCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['nodeaccess'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * The user has the `administer nodeaccess` permission.
   *
   * @var \Drupal\user\Entity\User
   */
  protected $nodeAccessAdmin;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->nodeAccessAdmin = $this->createUser(['administer nodeaccess']);
  }

  /**
   * Tests default values of nodeaccess.settings after installation.
   *
   * @covers nodeaccess_install
   */
  public function testDefaultNodeaccessSettings() {
    $nodeaccess_settings = $this->config('nodeaccess.settings');
    $allowed_grant_operations = $nodeaccess_settings->get('allowed_grant_operations');
    $bundles_roles_grants = $nodeaccess_settings->get('bundles_roles_grants');
    $grants_tab_availability = $nodeaccess_settings->get('grants_tab_availability');
    $map_rid_gid = $nodeaccess_settings->get('map_rid_gid');
    $roles_settings = $nodeaccess_settings->get('roles_settings');
    $this->assertTrue($allowed_grant_operations['grant_view']);
    $this->assertTrue($allowed_grant_operations['grant_update']);
    $this->assertTrue($allowed_grant_operations['grant_delete']);
    $this->assertEmpty(NodeType::loadMultiple(), 'No content type');
    $this->assertEmpty($bundles_roles_grants, 'No content type, no values here');
    $this->assertEmpty($grants_tab_availability, 'No content type, no values here');
    foreach (Role::loadMultiple() as $role_id => $role) {
      $this->assertArrayHasKey($role_id, $map_rid_gid);
      $this->assertIsInt($map_rid_gid[$role_id], 'Grant ID is an integer');
      $this->assertEquals($role->label(), $roles_settings[$role_id]['display_name']);
      $this->assertEquals($role->label(), $roles_settings[$role_id]['name']);
      $this->assertEquals(0, $roles_settings[$role_id]['weight']);
      $this->assertFalse($roles_settings[$role_id]['selected']);
    }
  }

  /**
   * Tests interacting with the Nodeaccess settings form without node type.
   */
  public function testNodeaccessSettingsFormNoNodeType() {
    $this->drupalLogin($this->nodeAccessAdmin);
    $this->drupalGet('/admin/config/people/nodeaccess');
    $session = $this->assertSession();
    $session->pageTextContains('Nodeaccess settings');

    $role_ids = $this->nodeAccessAdmin->getRoles();
    $role_ids = array_flip($role_ids);
    unset($role_ids['authenticated']);
    $role_ids = array_flip($role_ids);
    $role_id = array_pop($role_ids);
    $this->assertCount(0, $role_ids);
    $values = [
      'allowed_grant_operations[grant_view]' => 1,
      'allowed_grant_operations[grant_update]' => 1,
      'allowed_grant_operations[grant_delete]' => 0,
      'roles_settings[settings][anonymous][display_name]' => 'Anonymous',
      'roles_settings[settings][anonymous][selected]' => 1,
      'roles_settings[settings][authenticated][display_name]' => 'Authenticated user',
      'roles_settings[settings][authenticated][selected]' => 0,
      "roles_settings[settings][$role_id][display_name]" => 'A role alias',
      "roles_settings[settings][$role_id][selected]" => 1,
    ];
    $this->submitForm($values, 'Save configuration');
    $this->assertSession()->pageTextContains('The configuration options have been saved.');
    $nodeaccess_settings = $this->config('nodeaccess.settings');
    $allowed_grant_operations = $nodeaccess_settings->get('allowed_grant_operations');
    $bundles_roles_grants = $nodeaccess_settings->get('bundles_roles_grants');
    $grants_tab_availability = $nodeaccess_settings->get('grants_tab_availability');
    $map_rid_gid = $nodeaccess_settings->get('map_rid_gid');
    $roles_settings = $nodeaccess_settings->get('roles_settings');
    $this->assertTrue($allowed_grant_operations['grant_view']);
    $this->assertTrue($allowed_grant_operations['grant_update']);
    $this->assertFalse($allowed_grant_operations['grant_delete']);
    $this->assertEmpty(NodeType::loadMultiple(), 'No content type');
    $this->assertEmpty($bundles_roles_grants, 'No content type, no values here');
    $this->assertEmpty($grants_tab_availability, 'No content type, no values here');
    $this->assertIsInt($map_rid_gid[$role_id], 'Grant ID is an integer');
    $this->assertEquals('A role alias', $roles_settings[$role_id]['display_name']);
    $this->assertNotEmpty($roles_settings[$role_id]['name']);
    $this->assertTrue($roles_settings[$role_id]['selected']);
    $this->assertEquals('Anonymous', $roles_settings['anonymous']['display_name']);
    $this->assertNotEmpty($roles_settings['anonymous']['name']);
    $this->assertTrue($roles_settings['anonymous']['selected']);
    $this->assertEquals('Authenticated user', $roles_settings['authenticated']['display_name']);
    $this->assertNotEmpty($roles_settings['authenticated']['name']);
    $this->assertFalse($roles_settings['authenticated']['selected']);
  }

  /**
   * Tests interacting with the Nodeaccess settings form with a node type.
   */
  public function testNodeaccessSettingsFormWithNodeType() {
    $this->drupalCreateContentType(['type' => 'foo', 'name' => 'Foo']);
    $nodeaccess_settings = $this->config('nodeaccess.settings');
    $bundles_roles_grants = $nodeaccess_settings->get('bundles_roles_grants');
    $grants_tab_availability = $nodeaccess_settings->get('grants_tab_availability');

    $this->assertCount(1, NodeType::loadMultiple(), '1 content type');
    $this->assertNotEmpty($bundles_roles_grants);
    $this->assertFalse($grants_tab_availability['foo']);

    $role_ids = $this->nodeAccessAdmin->getRoles();
    $role_ids = array_flip($role_ids);
    unset($role_ids['authenticated']);
    $role_ids = array_flip($role_ids);
    $role_id = array_pop($role_ids);

    foreach ([$role_id, 'anonymous', 'authenticated', 'author'] as $key) {
      foreach (['grant_view', 'grant_update', 'grant_delete'] as $operation) {
        $this->assertEquals(0, $bundles_roles_grants['foo'][$key][$operation]);
      }
    }

    $this->drupalLogin($this->nodeAccessAdmin);
    $this->drupalGet('/admin/config/people/nodeaccess');
    $session = $this->assertSession();
    $session->pageTextContains('Nodeaccess settings');

    $role_ids = $this->nodeAccessAdmin->getRoles();
    $role_ids = array_flip($role_ids);
    unset($role_ids['authenticated']);
    $role_ids = array_flip($role_ids);
    $role_id = array_pop($role_ids);
    $this->assertCount(0, $role_ids);
    $values = [
      'allowed_grant_operations[grant_view]' => 1,
      'allowed_grant_operations[grant_update]' => 1,
      'allowed_grant_operations[grant_delete]' => 0,
      'roles_settings[settings][anonymous][display_name]' => 'Anonymous',
      'roles_settings[settings][anonymous][selected]' => 1,
      'roles_settings[settings][authenticated][display_name]' => 'Authenticated user',
      'roles_settings[settings][authenticated][selected]' => 0,
      "roles_settings[settings][$role_id][display_name]" => 'A role alias',
      "roles_settings[settings][$role_id][selected]" => 1,
    ];
    $values += [
      'bundles_roles_grants[foo][show_grant_tab]' => 1,
      'bundles_roles_grants[foo][settings][anonymous][grant_view]' => 0,
      'bundles_roles_grants[foo][settings][anonymous][grant_update]' => 0,
      'bundles_roles_grants[foo][settings][anonymous][grant_delete]' => 0,
      'bundles_roles_grants[foo][settings][authenticated][grant_view]' => 0,
      'bundles_roles_grants[foo][settings][authenticated][grant_update]' => 0,
      'bundles_roles_grants[foo][settings][authenticated][grant_delete]' => 0,
      'bundles_roles_grants[foo][settings][author][grant_view]' => 1,
      'bundles_roles_grants[foo][settings][author][grant_update]' => 1,
      'bundles_roles_grants[foo][settings][author][grant_delete]' => 1,
      "bundles_roles_grants[foo][settings][$role_id][grant_view]" => 1,
      "bundles_roles_grants[foo][settings][$role_id][grant_update]" => 1,
      "bundles_roles_grants[foo][settings][$role_id][grant_delete]" => 1,
    ];
    $this->submitForm($values, 'Save configuration');
    $this->assertSession()->pageTextContains('The configuration options have been saved.');
    $nodeaccess_settings = $this->config('nodeaccess.settings');
    $bundles_roles_grants = $nodeaccess_settings->get('bundles_roles_grants');
    $grants_tab_availability = $nodeaccess_settings->get('grants_tab_availability');
    $this->assertTrue($grants_tab_availability['foo']);
    foreach (['anonymous', 'authenticated'] as $key) {
      foreach (['grant_view', 'grant_update', 'grant_delete'] as $operation) {
        $this->assertEquals(0, $bundles_roles_grants['foo'][$key][$operation]);
      }
    }
    foreach ([$role_id, 'author'] as $key) {
      foreach (['grant_view', 'grant_update', 'grant_delete'] as $operation) {
        $this->assertEquals(1, $bundles_roles_grants['foo'][$key][$operation]);
      }
    }
  }

}
