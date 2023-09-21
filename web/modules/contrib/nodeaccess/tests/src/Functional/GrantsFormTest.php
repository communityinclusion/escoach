<?php

namespace Drupal\Tests\nodeaccess\Functional;

use Drupal\Core\Url;
use Drupal\node\Entity\NodeType;
use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\node\Traits\ContentTypeCreationTrait;
use Drupal\user\Entity\Role;

/**
 * Test the Grants form.
 *
 * @group nodeaccess
 * @covers \Drupal\nodeaccess\Form\SettingsForm
 */
class GrantsFormTest extends BrowserTestBase {

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
  protected $nodeaccessAdmin;

  /**
   * The user has the `nodeaccess grant foo permissions`.
   *
   * @var \Drupal\user\Entity\User
   */
  protected $fooBundleAdmin;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->drupalCreateContentType(['type' => 'foo', 'name' => 'Foo']);
    $this->nodeaccessAdmin = $this->createUser([
      'administer nodeaccess',
      'access user profiles',
      'create foo content',
    ]);
    $this->fooBundleAdmin = $this->createUser(['nodeaccess grant foo permissions']);
  }

  /**
   * Tests setup as expected.
   */
  public function testAfterSetup() {
    $this->assertCount(1, NodeType::loadMultiple(), '1 content type');
    $this->assertCount(4, Role::loadMultiple(), '1 role for each user = 2 roles, + 2 locked roles');
    $this->assertCount(1, $this->nodeaccessAdmin->getRoles(TRUE), '1 role, besides authenticated');
    $this->assertCount(1, $this->fooBundleAdmin->getRoles(TRUE), '1 role, besides authenticated');
    $this->assertNotEquals($this->nodeaccessAdmin->getRoles(TRUE)[0], $this->fooBundleAdmin->getRoles(TRUE)[0]);
  }

  /**
   * Tests access to the Grant tab.
   */
  public function testGrantTabAccess() {
    $assert_session = $this->assertSession();

    // Add a node type and a node for testing.
    $this->drupalLogin($this->nodeaccessAdmin);
    $node = $this->drupalCreateNode(['type' => 'foo']);
    $this->assertNotNull($node);
    $grant_url = Url::fromRoute('entity.node.grants', ['node' => $node->id()])->toString();

    // All users has no access unless `show_grant_tab` of `foo` is checked on
    // the nodeaccess settings page.
    $this->drupalGet($grant_url);
    $assert_session->statusCodeEquals(403);
    $this->drupalLogin($this->fooBundleAdmin);
    $this->drupalGet($grant_url);
    $assert_session->statusCodeEquals(403);
    $user = $this->createUser();
    $this->drupalLogin($user);
    $this->drupalGet($grant_url);
    $assert_session->statusCodeEquals(403);
    $admin = $this->createUser();
    $admin->addRole($this->createAdminRole());
    $admin->save();
    $this->drupalLogin($admin);
    $this->drupalGet($grant_url);
    $assert_session->statusCodeEquals(403);
    // Test anonymous.
    $this->drupalLogout();
    $this->drupalGet($grant_url);
    $assert_session->statusCodeEquals(403);

    // Update nodeaccess settings to allow showing the Grants tab.
    $this->drupalLogin($this->nodeaccessAdmin);
    $this->drupalGet('/admin/config/people/nodeaccess');
    $assert_session->pageTextContains('Nodeaccess settings');
    $this->submitForm(['bundles_roles_grants[foo][show_grant_tab]' => 1], 'Save configuration');
    $this->assertSession()->pageTextContains('The configuration options have been saved.');

    // Admin users and users with `administer nodeaccess` or/and
    // `nodeaccess grant foo permissions` permission have the access to the
    // Grants tab.
    $this->drupalGet($grant_url);
    $assert_session->pageTextContains('Grants');
    $this->drupalLogin($this->fooBundleAdmin);
    $this->drupalGet($grant_url);
    $assert_session->pageTextContains('Grants');
    $this->drupalLogin($user);
    $this->drupalGet($grant_url);
    $assert_session->statusCodeEquals(403);
    $this->drupalLogin($admin);
    $this->drupalGet($grant_url);
    $this->assertTrue($admin->hasPermission('administer nodeaccess'));
    $this->assertTrue($admin->hasPermission('nodeaccess grant foo permissions'));
    // Test anonymous.
    $this->drupalLogout();
    $this->drupalGet($grant_url);
    $assert_session->statusCodeEquals(403);

  }

  /**
   * Tests the form on the Grants tab.
   */
  public function testGrantForm() {
    $assert_session = $this->assertSession();

    // Add a node type and a node for testing. A node is required for accessing
    // its Grants tab.
    $this->drupalLogin($this->nodeaccessAdmin);
    $node = $this->drupalCreateNode(['type' => 'foo']);
    $this->assertNotNull($node);

    // Update nodeaccess settings to allow showing the Grants tab.
    $this->drupalLogin($this->nodeaccessAdmin);
    $this->drupalGet('/admin/config/people/nodeaccess');
    $assert_session->pageTextContains('Nodeaccess settings');
    $this->submitForm(['bundles_roles_grants[foo][show_grant_tab]' => 1], 'Save configuration');
    $this->assertSession()->pageTextContains('The configuration options have been saved.');

    $nodeaccess_admin_uid = $this->nodeaccessAdmin->id();
    // $nodeaccess_admin_username = $this->nodeaccessAdmin->getAccountName();
    $foo_bundle_admin_uid = $this->fooBundleAdmin->id();
    $foo_bundle_admin_usename = $this->fooBundleAdmin->getAccountName();
    $grant_url = Url::fromRoute('entity.node.grants', ['node' => $node->id()])->toString();
    $this->drupalGet($grant_url);
    // The logged-in user is nodeaccessAdmin who has the `access user profiles`
    // permission, so the input with name `search_uid` using the entity
    // reference widget.
    $this->submitForm(['search_uid' => "$foo_bundle_admin_usename ($foo_bundle_admin_uid)"], 'Search');
    $this->submitForm([], 'Search');
    $assert_session->fieldValueEquals("nodeaccess_user[$foo_bundle_admin_uid][keep]", 1);
    $this->submitForm([], 'Save Grants');
    // @todo The word search seems an inappropriate word for its function，Load
    //   user(s)?
    $this->drupalLogin($this->fooBundleAdmin);
    $this->drupalGet($grant_url);
    $assert_session->pageTextContains('Grants');
    // User fooBundleAdmin does not have the `access user profiles` permission,
    // so the input with name `search_uid` uses the plain textfield to accept a
    // user ID.
    $this->submitForm(['search_uid' => $nodeaccess_admin_uid], 'Search');
    $assert_session->fieldValueNotEquals("nodeaccess_user[$nodeaccess_admin_uid][grant_view]", 1);
    $this->submitForm(["nodeaccess_user[$nodeaccess_admin_uid][grant_view]" => 1], 'Search');
    $this->submitForm([], 'Save Grants');
    $assert_session->fieldValueEquals("nodeaccess_user[$nodeaccess_admin_uid][grant_view]", 1);
    $assert_session->fieldValueEquals("nodeaccess_user[$nodeaccess_admin_uid][keep]", 1);
    $this->submitForm(["nodeaccess_user[$nodeaccess_admin_uid][grant_view]" => 0], 'Search');
    $this->submitForm([], 'Search');
    $assert_session->pageTextContains(sprintf('Error: user %s (%s) is kept, but no permissions granted, uncheck "Keep?" or grant at least one permission of View, Edit and Delete.', $this->nodeaccessAdmin->label(), $nodeaccess_admin_uid));

    // Test roles based grants settings per node.
    $this->drupalLogin($this->nodeaccessAdmin);
    $this->drupalGet('/admin/config/people/nodeaccess');
    $assert_session->pageTextContains('Nodeaccess settings');
    $role_id_nodeaccess_admin = $this->nodeaccessAdmin->getRoles(TRUE)[0];
    $role_id_foo_bundle_admin = $this->fooBundleAdmin->getRoles(TRUE)[0];
    $this->submitForm([
      "roles_settings[settings][$role_id_nodeaccess_admin][selected]" => 1,
      "roles_settings[settings][$role_id_foo_bundle_admin][selected]" => 1,
    ], 'Save configuration');
    $this->drupalGet($grant_url);

    $nodeaccess_settings = $this->config('nodeaccess.settings');
    $map_rid_gid = $nodeaccess_settings->get('map_rid_gid');
    $grant_id_nodeaccess_admin = $map_rid_gid[$role_id_nodeaccess_admin];
    $grant_id_foo_bundle_admin = $map_rid_gid[$role_id_foo_bundle_admin];
    $assert_session->fieldValueNotEquals("nodeaccess_role[$grant_id_foo_bundle_admin][grant_view]", 1);
    $this->submitForm(["nodeaccess_role[$grant_id_foo_bundle_admin][grant_view]" => 1], 'Save Grants');
    $assert_session->fieldValueEquals("nodeaccess_role[$grant_id_foo_bundle_admin][grant_view]", 1);
    $assert_session->fieldValueNotEquals("nodeaccess_role[$grant_id_nodeaccess_admin][grant_view]", 1);

    // Users specific settings are still there.
    $assert_session->fieldValueEquals("nodeaccess_user[$nodeaccess_admin_uid][grant_view]", 1);
    $assert_session->fieldValueEquals("nodeaccess_user[$nodeaccess_admin_uid][keep]", 1);

    $this->drupalLogin($this->fooBundleAdmin);
    $this->drupalGet($grant_url);
    $this->submitForm(['search_uid' => 911], 'Search');
    $assert_session->pageTextContains('No users found for your input 911.');
    $this->submitForm(['search_uid' => 'Drupal'], 'Search');
    $assert_session->pageTextContains('Drupal is not a valid user ID.');

    $user1 = $this->createUser();
    $user2 = $this->createUser();
    $assert_session->pageTextNotContains($user1->label());
    $assert_session->pageTextNotContains($user2->label());
    $this->submitForm(['search_uid' => $user1->id() . ', ' . $user2->id()], 'Search');
    $assert_session->pageTextContains($user1->label());
    $assert_session->pageTextContains($user2->label());
    $this->submitForm([], 'Search');
    $assert_session->pageTextContains(sprintf('Error: user %s (%s), user %s (%s) are kept, but no permissions granted, uncheck "Keep?" or grant at least one permission of View, Edit and Delete.', $user1->label(), $user1->id(), $user2->label(), $user2->id()));

    $this->drupalLogin($this->nodeaccessAdmin);
    $this->drupalGet($grant_url);
    $assert_session->pageTextNotContains($user1->label());
    $assert_session->pageTextNotContains($user2->label());
    $this->submitForm(['search_uid' => sprintf('%s (%s), %s (%s)', $user1->label(), $user1->id(), $user2->label(), $user2->id())], 'Search');
    $assert_session->pageTextContains($user1->label());
    $assert_session->pageTextContains($user2->label());
  }

}
