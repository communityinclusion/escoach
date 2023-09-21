<?php

namespace Drupal\nodeaccess;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\user\RoleInterface;

/**
 * A helper service.
 */
class NodeAccessHelper {

  use StringTranslationTrait;

  /**
   * The database service.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Constructs the NodeAccessHelper object.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The database.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   */
  public function __construct(Connection $database, EntityTypeManagerInterface $entity_type_manager, ConfigFactoryInterface $config_factory) {
    $this->database = $database;
    $this->entityTypeManager = $entity_type_manager;
    $this->configFactory = $config_factory;
  }

  /**
   * Loads grants with realm `nodeaccess_role` from `node_access` table.
   *
   * @param \Drupal\Core\Config\ImmutableConfig $nodeaccess_settings
   *   The nodeaccess settings.
   * @param int $nid
   *   The nid.
   *
   * @return array
   *   An array of selected roles' grants. Keyed by the grant ID.
   */
  public function loadRolesGrants(ImmutableConfig $nodeaccess_settings, $nid) {
    $map_rid_gid = $nodeaccess_settings->get('map_rid_gid') ?? [];
    $grants = [];
    $role_ids = $this->selectedRoleIds($nodeaccess_settings);
    if (empty($role_ids)) {
      return $grants;
    }
    $grant_ids = array_map(function ($role_id) use ($map_rid_gid) {
      return $map_rid_gid[$role_id];
    }, $role_ids);
    $results = $this->database->select('node_access', 'n')
      ->fields('n', ['gid', 'grant_view', 'grant_update', 'grant_delete'])
      ->condition('n.realm', 'nodeaccess_role',)
      ->condition('n.gid', $grant_ids, 'IN')
      ->condition('n.nid', "$nid")
      ->execute()
      ->fetchAllAssoc('gid', \PDO::FETCH_ASSOC);

    // $roles_settings = $nodeaccess_settings->get('roles_settings');
    // $map_gid_rid = array_flip($map_rid_gid);
    foreach ($grant_ids as $grant_id) {
      // Convert to boolean for later use in form.
      $grants[$grant_id] = [
        'grant_view' => (boolean) ($results[$grant_id]['grant_view'] ?? FALSE),
        'grant_update' => (boolean) ($results[$grant_id]['grant_update'] ?? FALSE),
        'grant_delete' => (boolean) ($results[$grant_id]['grant_delete'] ?? FALSE),
      ];
    }
    return $grants;
  }

  /**
   * Loads grants with realm `nodeaccess_user` from `node_access` table.
   *
   * @param int $nid
   *   The node ID.
   *
   * @return array
   *   An array of users' grants. Keyed by the grant ID which is a user ID.
   */
  public function loadUsersGrants($nid) {
    // Load users from node_access.
    $grants = [];
    $results = $this->database->select('node_access', 'n')
      ->fields('n', ['gid', 'grant_view', 'grant_update', 'grant_delete'])
      ->condition('n.nid', "$nid")
      ->condition('n.realm', 'nodeaccess_user')
      ->execute()
      ->fetchAllAssoc('gid', \PDO::FETCH_ASSOC);
    // The gid is a user ID.
    foreach ($results as $gid => $grant) {
      // Convert to boolean for later use in form.
      $grants[$gid] = [
        'keep' => 1,
        'grant_view' => (boolean) $grant['grant_view'],
        'grant_update' => (boolean) $grant['grant_update'],
        'grant_delete' => (boolean) $grant['grant_delete'],
      ];
    }
    return $grants;
  }

  /**
   * Loads a user's grants from `node_access` table written by this module.
   *
   * @param \Drupal\Core\Config\ImmutableConfig $nodeaccess_settings
   *   The nodeaccess settings.
   * @param int $uid
   *   The user ID.
   * @param int $nid
   *   The node ID.
   *
   * @return array
   *   An array of the user's grants.
   */
  public function loadUserGrant(ImmutableConfig $nodeaccess_settings, $uid, $nid) {
    $map_rid_gid = $nodeaccess_settings->get('map_rid_gid') ?? [];
    $user = $this->entityTypeManager->getStorage('user')->load($uid);

    $grant = ['keep' => 1];

    $user_role_ids = $user->getRoles();
    $user_grant_ids = array_map(function ($role_id) use ($map_rid_gid) {
      return $map_rid_gid[$role_id];
    }, $user_role_ids);

    // @todo Take into account the `nodeaccess_author` grant.
    foreach (['grant_view', 'grant_update', 'grant_delete'] as $grant_type) {
      $query = $this->database->select('node_access', 'n');
      $or = $query->orConditionGroup();
      $and_nodeaccess_role = $query->andConditionGroup();
      $and_nodeaccess_user = $query->andConditionGroup();
      $and_nodeaccess_role
        ->condition('n.realm', 'nodeaccess_role')
        ->condition('n.gid', $user_grant_ids, 'IN');
      $and_nodeaccess_user
        ->condition('n.realm', 'nodeaccess_user')
        ->condition('n.gid', "$uid");
      $or
        ->condition($and_nodeaccess_role)
        ->condition($and_nodeaccess_user);
      $count = $query
        ->condition($or)
        ->condition('n.nid', "$nid")
        ->condition($grant_type, '1')
        ->countQuery()
        ->execute()
        ->fetchField();
      $grant[$grant_type] = (boolean) $count;
    }
    return $grant;
  }

  /**
   * Gets available role IDs on the grant tab.
   *
   * @param \Drupal\Core\Config\ImmutableConfig $nodeaccess_settings
   *   The nodeaccess settings.
   *
   * @return array
   *   An array of role IDs.
   */
  public function selectedRoleIds(ImmutableConfig $nodeaccess_settings) {
    $role_ids = [];
    $roles_settings = $nodeaccess_settings->get('roles_settings');
    foreach ($roles_settings as $role_id => $role_settings) {
      if ($role_settings['selected']) {
        $role_ids[] = $role_id;
      }
    }
    return $role_ids;
  }

  /**
   * Returns the reader array of form element for setting roles grants.
   *
   * @param \Drupal\Core\Config\ImmutableConfig $nodeaccess_settings
   *   The nodeaccess settings.
   * @param array $selected_role_ids
   *   The selected role IDs which is allowed to have per node grants.
   * @param array $values
   *   The form values of nodeaccess_role.
   *
   * @return array
   *   The render array.
   */
  public function rolesSettingsRender(ImmutableConfig $nodeaccess_settings, array $selected_role_ids, array $values) {
    $allowed_grant_operations = $nodeaccess_settings->get('allowed_grant_operations');
    $roles_settings = $nodeaccess_settings->get('roles_settings');
    $map_rid_gid = $nodeaccess_settings->get('map_rid_gid') ?? [];

    $header = [];
    $header[] = $this->t('Role');
    if ($allowed_grant_operations['grant_view']) {
      $header[] = $this->t('View');
    }
    if ($allowed_grant_operations['grant_update']) {
      $header[] = $this->t('Edit');
    }
    if ($allowed_grant_operations['grant_delete']) {
      $header[] = $this->t('Delete');
    }

    $render_array = [
      '#type' => 'table',
      '#header' => $header,
      '#tree' => TRUE,
    ];
    foreach ($selected_role_ids as $role_id) {
      $grant_id = $map_rid_gid[$role_id];
      $render_array[$grant_id]['name'] = [
        // @todo , remove the display name values.
        '#markup' => $roles_settings[$role_id]['display_name'],
      ];
      if ($allowed_grant_operations['grant_view']) {
        $render_array[$grant_id]['grant_view'] = [
          '#type' => 'checkbox',
          '#default_value' => $values[$grant_id]['grant_view'],
        ];
      }
      if ($allowed_grant_operations['grant_update']) {
        $render_array[$grant_id]['grant_update'] = [
          '#type' => 'checkbox',
          '#default_value' => $values[$grant_id]['grant_update'],
        ];
      }
      if ($allowed_grant_operations['grant_delete']) {
        $render_array[$grant_id]['grant_delete'] = [
          '#type' => 'checkbox',
          '#default_value' => $values[$grant_id]['grant_delete'],
        ];
      }
    }
    return $render_array;
  }

  /**
   * Returns the reader array of form element for setting users grants.
   *
   * @param \Drupal\Core\Config\ImmutableConfig $nodeaccess_settings
   *   The nodeaccess settings.
   * @param array $values
   *   The form values of nodeaccess_user.
   *
   * @return array
   *   The render array.
   */
  public function usersSettingsRender(ImmutableConfig $nodeaccess_settings, array $values) {
    $allowed_grant_operations = $nodeaccess_settings->get('allowed_grant_operations');

    $header = [];
    $header[] = $this->t('User');
    $header[] = $this->t('Keep?');
    if ($allowed_grant_operations['grant_view']) {
      $header[] = $this->t('View');
    }
    if ($allowed_grant_operations['grant_update']) {
      $header[] = $this->t('Edit');
    }
    if ($allowed_grant_operations['grant_delete']) {
      $header[] = $this->t('Delete');
    }
    $render_array = [
      '#type' => 'table',
      '#header' => $header,
    ];
    // Uids are grant IDs.
    $uids = array_keys($values);
    $usernames = $this->getUserNames($uids);
    foreach ($uids as $uid) {
      $render_array[$uid]['name'] = [
        '#markup' => $usernames[$uid] ?? '',
      ];
      $render_array[$uid]['keep'] = [
        '#type' => 'checkbox',
        '#default_value' => $values[$uid]['keep'] ?? FALSE,
      ];
      if ($allowed_grant_operations['grant_view']) {
        $render_array[$uid]['grant_view'] = [
          '#type' => 'checkbox',
          '#default_value' => $values[$uid]['grant_view'] ?? FALSE,
        ];
      }
      if ($allowed_grant_operations['grant_update']) {
        $render_array[$uid]['grant_update'] = [
          '#type' => 'checkbox',
          '#default_value' => $values[$uid]['grant_update'] ?? FALSE,
        ];
      }
      if ($allowed_grant_operations['grant_delete']) {
        $render_array[$uid]['grant_delete'] = [
          '#type' => 'checkbox',
          '#default_value' => $values[$uid]['grant_delete'] ?? FALSE,
        ];
      }
    }
    return $render_array;
  }

  /**
   * Gets usernames from user IDs.
   *
   * See code snippet below as an alternative.
   *
   * @code
   * if (empty($uids)) {
   *    return [];
   *  }
   *  $usernames = [];
   *  foreach (User::loadMultiple($uids) as $user) {
   *    $usernames[$user->id()] = $user->getDisplayName();
   *  }
   *  return $usernames;
   * @endcode
   *
   * @param array $uids
   *   The user IDS.
   *
   * @return array
   *   An array of usernames, keyed by user ID.
   */
  private function getUserNames(array $uids) {
    if (empty($uids)) {
      return [];
    }
    return $this->database->select('users_field_data', 'u')
      ->fields('u', ['uid', 'name'])
      ->condition('uid', $uids, 'IN')
      ->execute()->fetchAllKeyed();
  }

  /**
   * Adds role-related nodeaccess settings.
   *
   * @param \Drupal\user\RoleInterface $role
   *   The role.
   */
  public function addRoleRelateddSettings(RoleInterface $role) {
    $nodeaccess_settings = $this->configFactory->getEditable('nodeaccess.settings');
    $bundles_roles_grants = $nodeaccess_settings->get('bundles_roles_grants') ?? [];
    // Swap the role ID and grant ID for adding new item.
    $map_gid_rid = array_flip($nodeaccess_settings->get('map_rid_gid') ?? []);
    $role_id = $role->id();
    $role_name = $role->label();

    $bundles = array_keys($bundles_roles_grants);
    foreach ($bundles as $bundle) {
      $bundles_roles_grants[$bundle][$role_id] = [
        'grant_view' => 0,
        'grant_update' => 0,
        'grant_delete' => 0,
      ];
    }

    // Add the new one. Grant ID added implicitly.
    $map_gid_rid[] = $role_id;
    $roles_settings = $nodeaccess_settings->get('roles_settings');
    $roles_settings[$role_id] = [
      'display_name' => $role_name,
      'name' => $role_name,
      'weight' => 0,
      'selected' => FALSE,
    ];
    $nodeaccess_settings
      ->set('bundles_roles_grants', $bundles_roles_grants)
      ->set('map_rid_gid', array_flip($map_gid_rid))
      ->set('roles_settings', $roles_settings)
      ->save();
  }

  /**
   * Updates role-related nodeaccess settings.
   *
   * @param \Drupal\user\RoleInterface $role
   *   The role.
   */
  public function updateRoleRelatedSettings(RoleInterface $role) {
    $nodeaccess_settings = $this->configFactory->getEditable('nodeaccess.settings');
    $roles_settings = $nodeaccess_settings->get('roles_settings');
    $role_id = $role->id();
    $role_name = $role->label();

    // Usually, update `display_name` and `name`.
    if (isset($roles_settings[$role_id])) {
      $roles_settings[$role_id]['display_name'] = $role_name;
      $roles_settings[$role_id]['name'] = $role_name;
    }
    else {
      // This is a rare case or a case never be reached.
      $roles_settings[$role_id] = [
        'display_name' => $role_name,
        'name' => $role_name,
        'weight' => 0,
        'selected' => FALSE,
      ];
    }
    $nodeaccess_settings
      ->set('roles_settings', $roles_settings)
      ->save();
  }

  /**
   * Deletes role-related nodeaccess settings.
   *
   * @param \Drupal\user\RoleInterface $role
   *   The role.
   */
  public function deleteRoleRelatedSettings(RoleInterface $role) {
    $role_id = $role->id();
    $nodeaccess_settings = $this->configFactory->getEditable('nodeaccess.settings');
    $bundles_roles_grants = $nodeaccess_settings->get('bundles_roles_grants') ?? [];
    $map_rid_gid = $nodeaccess_settings->get('map_rid_gid') ?? [];
    $roles_settings = $nodeaccess_settings->get('roles_settings');

    $bundles = array_keys($bundles_roles_grants);
    foreach ($bundles as $bundle) {
      unset($bundles_roles_grants[$bundle][$role_id]);
    }
    unset($map_rid_gid[$role_id]);
    unset($roles_settings[$role_id]);

    $nodeaccess_settings
      ->set('bundles_roles_grants', $bundles_roles_grants)
      ->set('map_rid_gid', $map_rid_gid)
      ->set('roles_settings', $roles_settings)
      ->save();
  }

}
