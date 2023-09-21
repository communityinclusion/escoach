<?php

namespace Drupal\nodeaccess\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Component\Utility\Html;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Builds the configuration form.
 */
final class SettingsForm extends ConfigFormBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a grants form object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entitytype_manager
   *   The entity type manager.
   */
  public function __construct(ConfigFactoryInterface $config_factory, EntityTypeManagerInterface $entitytype_manager) {
    parent::__construct($config_factory);
    $this->entityTypeManager = $entitytype_manager;
  }

  /**
   * {@inheritDoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getEditableConfigNames() {
    return ['nodeaccess.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'nodeaccess_admin_settings';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $settings = $this->config('nodeaccess.settings');

    $roles = $this->entityTypeManager->getStorage('user_role')->loadMultiple();

    // Set roles_settings.
    // Select roles the permissions of which you want to allow users to
    // view and edit, and the aliases and weights of those roles.
    $form['roles_settings'] = [
      '#type' => 'details',
      '#open' => TRUE,
      '#title' => $this->t('Roles settings -- select roles and set role display names'),
      '#tree' => TRUE,
      '#description' => $this->t('The selected roles will be listed on individual node grants. If you wish for certain roles to be hidden from users on the node grants tab, make sure they are not selected here. You may also provide a display name (alias) for each role to be displayed to the user and a weight to order them by. This is useful if your roles have machine-readable names not intended for human users.'),
    ];
    $form['roles_settings']['settings'] = [
      '#type' => 'table',
      '#header' => [
        $this->t('Available on grant tab?'),
        $this->t('Display name (alias)'),
        $this->t('Weight'),
      ],
      '#tabledrag' => [
        [
          'action' => 'order',
          'relationship' => 'sibling',
          'group' => 'role-weight',
        ],
      ],
    ];
    $roles_settings = $settings->get('roles_settings');
    foreach ($roles as $role_id => $role) {
      $role_settings = $roles_settings[$role_id] ?? [];
      $form['roles_settings']['settings'][$role_id]['selected'] = [
        '#type' => 'checkbox',
        '#title' => Html::escape($role->label()),
        '#default_value' => $role_settings['selected'] ?? FALSE,
      ];
      $form['roles_settings']['settings'][$role_id]['display_name'] = [
        '#type' => 'textfield',
        '#default_value' => $role_settings['display_name'] ?? Html::escape($role->label()),
        '#size' => 50,
        '#maxlength' => 50,
      ];
      $form['roles_settings']['settings'][$role_id]['weight'] = [
        '#type' => 'weight',
        '#default_value' => $role_settings['weight'] ?? 0,
        '#delta' => 10,
        '#attributes' => ['class' => ['role-weight']],
      ];
      $form['roles_settings']['settings'][$role_id]['name'] = [
        '#type' => 'hidden',
        '#value' => $role->label(),
      ];
      $form['roles_settings']['settings'][$role_id]['#weight'] = $role_settings['weight'] ?? 0;
      $form['roles_settings']['settings'][$role_id]['#attributes']['class'][] = 'draggable';
    }

    // Grants per bundle settings.
    // Set grants_tab_availability Set bundles_roles_grants.
    $grants_tab_availability = $settings->get('grants_tab_availability');
    $bundles_roles_grants = $settings->get('bundles_roles_grants');
    $node_types = $this->entityTypeManager->getStorage('node_type')->loadMultiple();

    $form['bundles_roles_grants'] = [
      '#type' => 'details',
      '#open' => TRUE,
      '#title' => $this->t('Grants per content type settings'),
      '#tree' => TRUE,
      '#description' => $this->t('Grants per content type settings'),
    ];

    // Generate fieldsets for each bundle.
    foreach ($node_types as $bundle => $node_type) {
      $bundle_roles_grants = $bundles_roles_grants[$bundle];
      $form['bundles_roles_grants'][$bundle] = [
        '#type' => 'details',
        '#open' => FALSE,
        '#title' => $node_type->label(),
        '#tree' => TRUE,
        '#description' => $this->t('Note: the settings selected for the node author will define what permissions the node author has. This cannot be changed on individual node grants.'),
      ];

      $form['bundles_roles_grants'][$bundle]['show_grant_tab'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Show grant tab for this content type'),
        '#default_value' => $grants_tab_availability[$bundle] ?? FALSE,
      ];

      $form['bundles_roles_grants'][$bundle]['settings'] = [
        '#type' => 'table',
        '#header' => [
          $this->t('Role name'),
          $this->t('View'),
          $this->t('Edit'),
          $this->t('Delete'),
        ],
      ];

      // Set default role permissions for bundle.
      foreach ($roles as $role_id => $role) {
        $form['bundles_roles_grants'][$bundle]['settings'][$role_id] = [
          'label' => [
            '#plain_text' => $role->label(),
          ],
          'grant_view' => [
            '#type' => 'checkbox',
            '#default_value' => $bundle_roles_grants[$role_id]['grant_view'] ?? 0,
          ],
          'grant_update' => [
            '#type' => 'checkbox',
            '#default_value' => $bundle_roles_grants[$role_id]['grant_update'] ?? 0,
          ],
          'grant_delete' => [
            '#type' => 'checkbox',
            '#default_value' => $bundle_roles_grants[$role_id]['grant_delete'] ?? 0,
          ],
        ];
      }
      $form['bundles_roles_grants'][$bundle]['settings']['author'] = [
        'label' => [
          '#plain_text' => $this->t('Author'),
        ],
        'grant_view' => [
          '#type' => 'checkbox',
          '#default_value' => $bundles_roles_grants[$bundle]['author']['grant_view'] ?? 0,
        ],
        'grant_update' => [
          '#type' => 'checkbox',
          '#default_value' => $bundles_roles_grants[$bundle]['author']['grant_update'] ?? 0,
        ],
        'grant_delete' => [
          '#type' => 'checkbox',
          '#default_value' => $bundles_roles_grants[$bundle]['author']['grant_delete'] ?? 0,
        ],
      ];
    }

    // Set allowed_grant_operations.
    // Select permissions/grant operations you want to allow users to view and
    // edit.
    $allowed_grant_operations = $settings->get('allowed_grant_operations');
    $form['allowed_grant_operations'] = [
      '#type' => 'details',
      '#open' => TRUE,
      '#title' => $this->t('Allowed grants operations'),
      '#tree' => TRUE,
      '#description' => '<small>' . $this->t('The selected grant operations will be listed on individual node grants. If you wish for certain grants to be hidden from users on the node grants tab, make sure they are not selected here.') . '</small>',
    ];
    $form['allowed_grant_operations']['grant_view'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('View'),
      '#default_value' => $allowed_grant_operations['grant_view'],
    ];
    $form['allowed_grant_operations']['grant_update'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Edit'),
      '#default_value' => $allowed_grant_operations['grant_update'],
    ];
    $form['allowed_grant_operations']['grant_delete'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Delete'),
      '#default_value' => $allowed_grant_operations['grant_delete'],
    ];
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    $bundles_roles_grants_values = $values['bundles_roles_grants'] ?? [];
    $bundles_roles_grants = [];
    $grants_tab_availability = [];
    if (!empty($bundles_roles_grants_values)) {
      foreach ($bundles_roles_grants_values as $bundle => $roles_grants_values) {
        $grants_tab_availability[$bundle] = $roles_grants_values['show_grant_tab'];
        $bundles_roles_grants[$bundle] = $roles_grants_values['settings'];
      }
    }

    $this->config('nodeaccess.settings')
      ->set('allowed_grant_operations', $values['allowed_grant_operations'] ?? [])
      ->set('bundles_roles_grants', $bundles_roles_grants)
      ->set('grants_tab_availability', $grants_tab_availability)
      ->set('roles_settings', $values['roles_settings']['settings'] ?? [])
      ->save();
    node_access_needs_rebuild(TRUE);
    parent::submitForm($form, $form_state);
  }

}
