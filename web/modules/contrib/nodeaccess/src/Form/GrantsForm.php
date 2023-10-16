<?php

namespace Drupal\nodeaccess\Form;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Builds the Grants form.
 */
class GrantsForm extends FormBase {

  /**
   * The current database.
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
   * The node grant storage.
   *
   * @var \Drupal\node\NodeGrantDatabaseStorageInterface
   */
  protected $nodeGrantStorage;

  /**
   * The nodeaccess helper.
   *
   * @var \Drupal\nodeaccess\NodeAccessHelper
   */
  protected $nodeAccessHelper;

  /**
   * {@inheritDoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->database = $container->get('database');
    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->nodeGrantStorage = $container->get('node.grant_storage');
    $instance->nodeAccessHelper = $container->get('nodeaccess.helper');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'nodeaccess_grants_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, NodeInterface $node = NULL) {
    $form_values = $form_state->getValues();
    $nodeaccess_settings = $this->configFactory()->get('nodeaccess.settings');

    $nid = $node->id();
    $user = $this->currentUser();

    if (empty($form_values)) {
      $form_values['nodeaccess_role'] = $this->nodeAccessHelper->loadRolesGrants($nodeaccess_settings, $nid);
      $form_values['nodeaccess_user'] = $this->nodeAccessHelper->loadUsersGrants($nid);
    }
    else {
      $form_values['nodeaccess_role'] = $form_values['nodeaccess_role'] ?? [];
      $form_values['nodeaccess_user'] = $form_values['nodeaccess_user'] ?? [];
      if (empty($form_state->getErrors()) && isset($form_values['search_uid'])) {
        $uids = [];
        if (!empty($form_values['nodeaccess_user'])) {
          $uids = array_keys($form_values['nodeaccess_user']);
        }
        $search_uid = $form_values['search_uid'];

        if (is_array($search_uid)) {
          $search_uids = array_column($search_uid, 'target_id');
          // @todo Refactor here.
          foreach ($search_uids as $user_id) {
            // @todo Display a message if the user/user ID is added already.
            if (!in_array($user_id, $uids)) {
              // Append the user's grant to the form values.
              $form_values['nodeaccess_user'][$user_id] = $this->nodeAccessHelper->loadUserGrant($nodeaccess_settings, $user_id, $nid);
            }
          }
        }
      }
    }

    // Available role IDs for grants settings with nodeaccess_role realm per
    // node.
    $selected_role_ids = $this->nodeAccessHelper->selectedRoleIds($nodeaccess_settings);
    if (!empty($selected_role_ids)) {
      $form['nodeaccess_role'] = $this->nodeAccessHelper->rolesSettingsRender($nodeaccess_settings, $selected_role_ids, $form_values['nodeaccess_role']);
    }

    if ($user->hasPermission('access user profiles')) {
      $form['search_uid'] = [
        '#title' => $this->t('Users'),
        '#default_value' => $form_values['search_uid'] ?? NULL,
        '#type' => 'entity_autocomplete',
        '#target_type' => 'user',
        '#selection_settings' => [
          'include_anonymous' => FALSE,
        ],
        '#tags' => TRUE,
        '#attributes' => ['placeholder' => 'Separate users with a comma'],
        '#prefix' => '<p><div class="container-inline">',
      ];
    }
    else {
      $form['search_uid'] = [
        '#title' => $this->t('User IDs'),
        '#default_value' => $form_values['search_uid'] ?? NULL,
        '#size' => 40,
        '#type' => 'textfield',
        '#attributes' => ['placeholder' => 'Separate user IDs with a comma'],
        '#prefix' => '<p><div class="container-inline">',
        '#element_validate' => ['::validateSearchUid'],
      ];
    }
    // @todo , `Search` could be `Load user(s)`?
    // @todo , The newly loaded one on the top?
    // @todo , Allow grant access per node for Author.
    // @todo , Add description to explain how it works?
    $form['search'] = [
      '#type' => 'submit',
      '#value' => $this->t('Search'),
      '#submit' => ['::searchUser'],
      '#suffix' => '</div></p>',
    ];
    if (count($form_values['nodeaccess_user'])) {
      $form['nodeaccess_user'] = $this->nodeAccessHelper->usersSettingsRender($nodeaccess_settings, $form_values['nodeaccess_user']);
      $form['nodeaccess_user']['#element_validate'] = ['::validateGrants'];
    }

    $form_state->set('node', $node);

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save Grants'),
    ];
    return $form;
  }

  /**
   * Validates the search_uid.
   *
   * @param array $element
   *   The search_uid form render array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public function validateSearchUid(array $element, FormStateInterface $form_state) {
    // @todo , `keep` checked but none of `View`, `Edit` and `Delete` checked.
    // $form_state->isValueEmpty() takes 0 as an empty value.
    if ($form_state->getValue('search_uid') === '0') {
      $form_state->setError($element, $this->t("0 is not an allowed user ID."));
    }
    // Ignore empty value.
    if ($form_state->isValueEmpty('search_uid')) {
      return;
    }

    $input = $form_state->getValue('search_uid');
    $uids = explode(',', $input);
    $sanitized_uids = [];
    foreach ($uids as $uid) {
      $trimmed_uid = trim($uid);
      if (empty($trimmed_uid)) {
        $form_state->setError($element, $this->t("Your @input can not be parsed correctly.", ['@input' => $input]));
        return;
      }
      $trimmed_uid_int = (int) $trimmed_uid;
      if ("$trimmed_uid_int" !== $trimmed_uid || $trimmed_uid_int <= 0) {
        $form_state->setError($element, $this->t("@uid is not a valid user ID.", ['@uid' => $trimmed_uid]));
        return;
      }
      $sanitized_uids[] = $trimmed_uid;
    }

    $users = $this->entityTypeManager->getStorage('user')->loadMultiple($sanitized_uids);

    if (empty($users)) {
      $form_state->setError($element, $this->t("No users found for your input @input.", ['@input' => $input]));
    }

    $value = [];
    foreach (array_keys($users) as $uid) {
      $value[] = ['target_id' => $uid];
    }
    $form_state->setValueForElement($element, $value);
  }

  /**
   * Validates grants.
   *
   * @param array $element
   *   The search_uid form render array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public function validateGrants(array $element, FormStateInterface $form_state) {
    if ($form_state->isValueEmpty('nodeaccess_user')) {
      return;
    }
    $nodeaccess_user = $form_state->getValue('nodeaccess_user');
    if (is_array($nodeaccess_user)) {
      $users_with_no_grants = [];
      foreach ($nodeaccess_user as $uid => $grant) {
        if ($grant['keep'] && !$grant['grant_view'] && !$grant['grant_update'] &&!$grant['grant_delete']) {
          $users_with_no_grants[] = $uid;
        }
      }
      if (!empty($users_with_no_grants)) {
        $users = $this->entityTypeManager->getStorage('user')->loadMultiple($users_with_no_grants);
        $usernames = [];
        foreach ($users as $user) {
          $usernames[] = $this->t("user @username (@uid)", [
            '@username' => $user->label(),
            '@uid' => $user->id(),
          ]);
        }
        $form_state->setError($element, $this->t('Error: @usernames @predicate kept, but no permissions granted, uncheck "Keep?" or grant at least one permission of View, Edit and Delete.', [
          '@usernames' => implode(', ', $usernames),
          '@predicate' => $this->formatPlural(count($usernames), $this->t('is'), $this->t('are')),
        ]));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $nodeaccess_user = $form_state->getValue('nodeaccess_user');
    // Delete unkept users.
    if (!empty($nodeaccess_user) && is_array($nodeaccess_user)) {
      foreach ($nodeaccess_user as $uid => $row) {
        if (!$row['keep']) {
          unset($nodeaccess_user[$uid]);
        }
      }
      $form_state->setValue('nodeaccess_user', $nodeaccess_user);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $form_values = $form_state->getValues();
    $grants = [];
    /** @var \Drupal\node\Entity\Node $node */
    $node = $form_state->get('node');
    $nid = $node->id();
    foreach (['nodeaccess_user', 'nodeaccess_role'] as $realm) {
      if (isset($form_values[$realm]) && is_array($form_values[$realm])) {
        foreach ($form_values[$realm] as $grant_id => $values) {
          $grant = [
            'gid' => $grant_id,
            'realm' => $realm,
            'grant_view' => empty($values['grant_view']) ? 0 : $values['grant_view'],
            'grant_update' => empty($values['grant_update']) ? 0 : $values['grant_update'],
            'grant_delete' => empty($values['grant_delete']) ? 0 : $values['grant_delete'],
          ];
          // Grants with all 0 values, which are discarded and won't be written
          // into the `node_access` table, so that they are discarded here and
          // won't be written into the `nodeaccess` table further too.
          if ($grant['grant_view'] || $grant['grant_update'] || $grant['grant_delete']) {
            $grants[] = $grant;
          }
        }
      }
    }
    $this->database->delete('nodeaccess')
      ->condition('nid', $nid)
      ->execute();
    // Save role and user grants to our own table.
    foreach ($grants as $grant) {
      $this->database->insert('nodeaccess')
        ->fields([
          'nid' => $nid,
          'gid' => $grant['gid'],
          'realm' => $grant['realm'],
          'grant_view' => $grant['grant_view'],
          'grant_update' => $grant['grant_update'],
          'grant_delete' => $grant['grant_delete'],
        ])
        ->execute();
    }
    /** @var \Drupal\node\NodeAccessControlHandler $node_access_control_handler */
    $node_access_control_handler = $this->entityTypeManager->getAccessControlHandler('node');
    $grants = $node_access_control_handler->acquireGrants($node);
    $this->nodeGrantStorage->write($node, $grants);
    $this->messenger()->addMessage($this->t('Grants saved.'));
    Cache::invalidateTags($node->getCacheTagsToInvalidate());
  }

  /**
   * Helper function to search uids/usernames.
   */
  public function searchUser(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    $form_state->setRebuild();
    $form_state->setStorage($values);
  }

  /**
   * Checks access to the `Grants` tab.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The account to verify access.
   * @param \Drupal\node\NodeInterface $node
   *   The node to check access against.
   *
   * @return \Drupal\Core\Access\AccessResultAllowed|\Drupal\Core\Access\AccessResultForbidden
   *   The access result.
   */
  public function access(AccountInterface $account, NodeInterface $node) {
    $nodeaccess_settings = $this->configFactory()->get('nodeaccess.settings');
    $grants_tab_availability = $nodeaccess_settings->get('grants_tab_availability');
    $bundle = $node->bundle();
    $allowed = $grants_tab_availability[$bundle] ?? FALSE;
    if ($allowed && ($account->hasPermission("nodeaccess grant $bundle permissions") || $account->hasPermission('administer nodeaccess'))) {
      return AccessResult::Allowed();
    }
    return AccessResult::forbidden();
  }

}
