<?php

namespace Drupal\Tests\nodeaccess\Functional;

use Drupal\Core\Url;
use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\node\Traits\ContentTypeCreationTrait;

/**
 * Test node access.
 *
 * @group nodeaccess
 * @covers \Drupal\nodeaccess\Form\SettingsForm
 * @covers \Drupal\nodeaccess\Form\GrantsForm
 */
class NodeAccessTest extends BrowserTestBase {

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
   * Tests node access against author and published node.
   *
   * Author's node access is controlled via the nodeaccess settings page. It's
   * can not be controlled via the Grants tab page.
   *
   * @todo Separate nodeaccess settings into Global settings and Grants tab page
   *   settings.
   */
  public function testPublishedNodeAccessAuthor() {
    $assert_session = $this->assertSession();
    $this->drupalLogin($this->nodeaccessAdmin);
    $this->drupalGet('/admin/config/people/nodeaccess');
    $assert_session->pageTextContains('Nodeaccess settings');
    $this->submitForm(['bundles_roles_grants[foo][settings][author][grant_view]' => 1], 'Save configuration');
    $this->assertSession()->pageTextContains('The configuration options have been saved.');
    node_access_rebuild();

    $node = $this->drupalCreateNode(['type' => 'foo']);
    $node_view_url = $node->toUrl()->toString();
    $node_edit_url = $node->toUrl('edit-form')->toString();
    $node_delete_url = $node->toUrl('delete-form')->toString();

    $this->assertTrue($node->isPublished());
    $this->assertSame($this->nodeaccessAdmin->id(), $node->getOwnerId());
    $this->drupalGet($node_view_url);
    $assert_session->pageTextContains($node->label());
    $this->drupalGet($node_edit_url);
    $assert_session->statusCodeEquals(403);
    $this->drupalGet($node_delete_url);
    $assert_session->statusCodeEquals(403);

    $this->drupalGet('/admin/config/people/nodeaccess');
    $assert_session->pageTextContains('Nodeaccess settings');
    $this->submitForm([
      'bundles_roles_grants[foo][settings][author][grant_view]' => 0,
      'bundles_roles_grants[foo][settings][author][grant_update]' => 1,
      'bundles_roles_grants[foo][settings][author][grant_delete]' => 1,
    ], 'Save configuration');
    $this->assertSession()->pageTextContains('The configuration options have been saved.');
    node_access_rebuild();
    $this->drupalGet($node_view_url);
    $assert_session->statusCodeEquals(403);
    $this->drupalGet($node_edit_url);
    $assert_session->pageTextContains($node->label());
    $this->drupalGet($node_delete_url);
    $assert_session->pageTextContains($node->label());
  }

  /**
   * Tests node access against admin user and published node.
   *
   * Admin users have the access to any nodes. Node access settings from this
   * module are being bypassed.
   */
  public function testPublishedNodeAccessAdmin() {
    $assert_session = $this->assertSession();

    $admin_role_id = $this->createAdminRole();
    $admin_user = $this->createUser();
    $admin_user->addRole($admin_role_id);
    $admin_user->save();

    $this->drupalLogin($this->nodeaccessAdmin);
    $this->drupalGet('/admin/config/people/nodeaccess');
    $assert_session->pageTextContains('Nodeaccess settings');
    $this->submitForm([
      "bundles_roles_grants[foo][settings][$admin_role_id][grant_view]" => 0,
      "bundles_roles_grants[foo][settings][$admin_role_id][grant_update]" => 0,
      "bundles_roles_grants[foo][settings][$admin_role_id][grant_delete]" => 0,
      'bundles_roles_grants[foo][show_grant_tab]' => 1,
      "roles_settings[settings][$admin_role_id][selected]" => 1,
    ], 'Save configuration');
    $assert_session->pageTextContains('The configuration options have been saved.');
    node_access_rebuild();

    $node = $this->drupalCreateNode(['type' => 'foo']);
    $node_view_url = $node->toUrl()->toString();
    $node_edit_url = $node->toUrl('edit-form')->toString();
    $node_delete_url = $node->toUrl('delete-form')->toString();
    $grant_url = Url::fromRoute('entity.node.grants', ['node' => $node->id()])->toString();
    $nodeaccess_settings = $this->config('nodeaccess.settings');
    $map_rid_gid = $nodeaccess_settings->get('map_rid_gid');
    $grant_id_admin_user = $map_rid_gid[$admin_role_id];
    $this->drupalGet($grant_url);
    $this->submitForm([
      "nodeaccess_role[$grant_id_admin_user][grant_view]" => 0,
      "nodeaccess_role[$grant_id_admin_user][grant_update]" => 0,
      "nodeaccess_role[$grant_id_admin_user][grant_delete]" => 0,
    ], 'Save Grants');
    // @todo Load the admin user and disable all access.
    $this->drupalLogin($admin_user);
    $this->drupalGet($node_view_url);
    $assert_session->statusCodeEquals(200);
    $this->drupalGet($node_edit_url);
    $assert_session->statusCodeEquals(200);
    $this->drupalGet($node_delete_url);
    $assert_session->statusCodeEquals(200);
  }

  /**
   * Tests node access against anonymous user and published node.
   */
  public function testPublishedNodeAccessAnonymous() {
    $assert_session = $this->assertSession();

    $this->drupalLogin($this->nodeaccessAdmin);
    $this->drupalGet('/admin/config/people/nodeaccess');
    $assert_session->pageTextContains('Nodeaccess settings');
    $this->submitForm([
      "bundles_roles_grants[foo][settings][anonymous][grant_view]" => 0,
      "bundles_roles_grants[foo][settings][anonymous][grant_update]" => 0,
      "bundles_roles_grants[foo][settings][anonymous][grant_delete]" => 0,
      'bundles_roles_grants[foo][show_grant_tab]' => 1,
      "roles_settings[settings][anonymous][selected]" => 1,
    ], 'Save configuration');
    $assert_session->pageTextContains('The configuration options have been saved.');
    node_access_rebuild();
    $node = $this->drupalCreateNode(['type' => 'foo']);
    $node_view_url = $node->toUrl()->toString();
    $node_edit_url = $node->toUrl('edit-form')->toString();
    $node_delete_url = $node->toUrl('delete-form')->toString();
    $grant_url = Url::fromRoute('entity.node.grants', ['node' => $node->id()])->toString();

    $this->drupalLogout();
    $this->drupalGet($node_view_url);
    $assert_session->statusCodeEquals(403);
    $this->drupalGet($node_edit_url);
    $assert_session->statusCodeEquals(403);
    $this->drupalGet($node_delete_url);
    $assert_session->statusCodeEquals(403);

    $this->drupalLogin($this->nodeaccessAdmin);
    $this->drupalGet($grant_url);
    $nodeaccess_settings = $this->config('nodeaccess.settings');
    $map_rid_gid = $nodeaccess_settings->get('map_rid_gid');

    $anonymous_grant_id = $map_rid_gid['anonymous'];

    $this->submitForm([
      "nodeaccess_role[$anonymous_grant_id][grant_view]" => 1,
      "nodeaccess_role[$anonymous_grant_id][grant_update]" => 1,
      "nodeaccess_role[$anonymous_grant_id][grant_delete]" => 1,
    ], 'Save Grants');
    $this->drupalLogout();

    $admin = $this->createUser();
    $admin_rid = $this->createAdminRole();
    $admin->addRole($admin_rid);
    $admin->save();
    $this->drupalLogin($admin);
    $this->drupalGet(Url::fromRoute('entity.user_role.edit_permissions_form', ['user_role' => 'anonymous'])->toString());
    $this->submitForm(['anonymous[access content]' => 1], 'Save permissions');
    $this->drupalLogout();

    $this->drupalGet($node_view_url);
    $assert_session->statusCodeNotEquals(403);
    $this->drupalGet($node_edit_url);
    $assert_session->statusCodeNotEquals(403);
    $this->drupalGet($node_delete_url);
    $assert_session->statusCodeNotEquals(403);
  }

  /**
   * Tests node access against authenticated user and published node.
   */
  public function testPublishedNodeAccessAuthenticated() {
    $assert_session = $this->assertSession();
    $this->drupalLogin($this->nodeaccessAdmin);
    $this->drupalGet('/admin/config/people/nodeaccess');
    $assert_session->pageTextContains('Nodeaccess settings');
    $this->submitForm([
      "bundles_roles_grants[foo][settings][authenticated][grant_view]" => 0,
      "bundles_roles_grants[foo][settings][authenticated][grant_update]" => 0,
      "bundles_roles_grants[foo][settings][authenticated][grant_delete]" => 0,
      'bundles_roles_grants[foo][show_grant_tab]' => 1,
      "roles_settings[settings][authenticated][selected]" => 1,
    ], 'Save configuration');
    $assert_session->pageTextContains('The configuration options have been saved.');
    node_access_rebuild();
    $node = $this->drupalCreateNode(['type' => 'foo']);
    $node_view_url = $node->toUrl()->toString();
    $node_edit_url = $node->toUrl('edit-form')->toString();
    $node_delete_url = $node->toUrl('delete-form')->toString();
    $grant_url = Url::fromRoute('entity.node.grants', ['node' => $node->id()])->toString();

    $authenticated_user = $this->createUser();
    $this->assertTrue($authenticated_user->hasRole('authenticated'));
    $this->drupalLogin($authenticated_user);
    $this->drupalGet($node_view_url);
    $assert_session->statusCodeEquals(403);
    $this->drupalGet($node_edit_url);
    $assert_session->statusCodeEquals(403);
    $this->drupalGet($node_delete_url);
    $assert_session->statusCodeEquals(403);

    $this->drupalLogin($this->nodeaccessAdmin);
    $this->drupalGet($grant_url);
    $nodeaccess_settings = $this->config('nodeaccess.settings');
    $map_rid_gid = $nodeaccess_settings->get('map_rid_gid');

    $authenticated_grant_id = $map_rid_gid['authenticated'];

    $this->submitForm([
      "nodeaccess_role[$authenticated_grant_id][grant_view]" => 1,
      "nodeaccess_role[$authenticated_grant_id][grant_update]" => 1,
      "nodeaccess_role[$authenticated_grant_id][grant_delete]" => 1,
    ], 'Save Grants');
    $this->drupalLogin($authenticated_user);
    $this->drupalGet($node_view_url);
    $assert_session->statusCodeNotEquals(403);
    $this->drupalGet($node_edit_url);
    $assert_session->statusCodeNotEquals(403);
    $this->drupalGet($node_delete_url);
    $assert_session->statusCodeNotEquals(403);
  }

  /**
   * Tests node access against unpublished node.
   *
   * @todo this test is incomplete.
   */
  public function testUnpublishedNodeAccess() {
    $assert_session = $this->assertSession();
    $this->drupalLogin($this->nodeaccessAdmin);
    $node = $this->drupalCreateNode(['type' => 'foo']);
    $node->setUnpublished()->save();
    $this->assertFalse($node->isPublished());
    $this->assertSame($this->nodeaccessAdmin->id(), $node->getOwnerId());

    $node_view_url = $node->toUrl()->toString();
    $node_edit_url = $node->toUrl('edit-form')->toString();
    $node_delete_url = $node->toUrl('delete-form')->toString();

    $this->drupalGet('/admin/config/people/nodeaccess');
    $assert_session->pageTextContains('Nodeaccess settings');
    $this->submitForm(['bundles_roles_grants[foo][settings][author][grant_view]' => 1], 'Save configuration');
    $this->assertSession()->pageTextContains('The configuration options have been saved.');
    node_access_rebuild();

    $this->drupalGet($node_view_url);
    $assert_session->pageTextContains($node->label());
    $this->drupalGet($node_edit_url);
    $assert_session->statusCodeEquals(403);
    $this->drupalGet($node_delete_url);
    $assert_session->statusCodeEquals(403);

    $this->drupalGet('/admin/config/people/nodeaccess');
    $assert_session->pageTextContains('Nodeaccess settings');
    $this->submitForm([
      'bundles_roles_grants[foo][settings][author][grant_view]' => 0,
      'bundles_roles_grants[foo][settings][author][grant_update]' => 1,
      'bundles_roles_grants[foo][settings][author][grant_delete]' => 1,
    ], 'Save configuration');
    $this->assertSession()->pageTextContains('The configuration options have been saved.');
    node_access_rebuild();
    $this->drupalGet($node_view_url);
    $assert_session->statusCodeEquals(403);
    $this->drupalGet($node_edit_url);
    $assert_session->pageTextContains($node->label());
    $this->drupalGet($node_delete_url);
    $assert_session->pageTextContains($node->label());
  }

}
