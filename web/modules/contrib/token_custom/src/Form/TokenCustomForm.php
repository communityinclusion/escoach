<?php

namespace Drupal\token_custom\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\token_custom\Entity\TokenCustomType;

/**
 * Form handler for the custom token edit forms.
 */
class TokenCustomForm extends ContentEntityForm {

  /**
   * The custom token storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $tokenCustomStorage;

  /**
   * The custom token type storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $tokenCustomTypeStorage;

  /**
   * The custom token entity.
   *
   * @var \Drupal\token_custom\TokenCustomInterface
   */
  protected $entity;

  /**
   * Constructs a TokenCustomForm object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Entity\EntityStorageInterface $token_custom_storage
   *   The custom token storage.
   * @param \Drupal\Core\Entity\EntityStorageInterface $token_custom_type_storage
   *   The custom token type storage.
   */
  public function __construct(EntityRepositoryInterface $entity_repository, EntityTypeBundleInfoInterface $entity_type_bundle_info, TimeInterface $time, EntityTypeManagerInterface $entity_type_manager) {
    parent::__construct($entity_repository, $entity_type_bundle_info, $time);
    $this->tokenCustomStorage = $entity_type_manager->getStorage('token_custom');
    $this->tokenCustomTypeStorage = $entity_type_manager->getStorage('token_custom_type');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity.repository'),
      $container->get('entity_type.bundle.info'),
      $container->get('datetime.time'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $token = $this->entity;

    $form = parent::form($form, $form_state);

    if ($this->operation == 'edit') {
      $form['#title'] = $this->t('Edit custom token %label', [
        '%label' => $token->label(),
      ]);
    }

    $types = TokenCustomType::loadMultiple();
    $options = [];
    foreach ($types as $type) {
      $options[$type->id()] = $type->label();
    }
    $form['type'] = [
      '#type'   => 'select',
      '#title' => 'Token type',
      '#description' => $this->t('The token type determines the availability of the token according to the data in the $data array (ex. a token of type <em>node</em> will need $data[node].'),
      '#options' => $options,
      '#maxlength' => 128,
      '#default_value' => $token->bundle(),
      '#weight' => -1,
    ];

    $form['machine_name']['widget'][0]['value']['#type'] = 'machine_name';
    $form['machine_name']['widget'][0]['value']['#machine_name'] = [
      'exists' => '\Drupal\token_custom\Entity\TokenCustom::load',
    ];

    $account = $this->currentUser();
    $form['machine_name']['#access'] = $account->hasPermission('administer custom tokens');
    $form['machine_name']['#disabled'] = !$token->isNew();
    $form['machine_name']['widget'][0]['value']['#required'] = TRUE;
    $form['machine_name']['#required'] = TRUE;

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $token = $this->entity;

    $insert = $token->isNew();
    $token->save();
    token_clear_cache();

    $context = [
      '@type' => $token->bundle(),
      '%info' => $token->label(),
    ];
    $logger = $this->logger('token_custom');
    $token_type = $this->tokenCustomTypeStorage->load($token->bundle());
    $t_args = [
      '@type' => $token_type->label(),
      '%info' => $token->label(),
    ];

    if ($insert) {
      $logger->notice('@type: added %info.', $context);
      $this->messenger()->addStatus($this->t('@type %info has been created.', $t_args));
    }
    else {
      $logger->notice('@type: updated %info.', $context);
      $this->messenger()->addStatus($this->t('@type %info has been updated.', $t_args));
    }

    if ($token->id()) {
      $form_state->setValue('id', $token->id());
      $form_state->set('id', $token->id());
      $form_state->setRedirectUrl($token->toUrl('collection'));
    }
    else {
      // In the unlikely case something went wrong on save, the token will be
      // rebuilt and token form redisplayed.
      $this->messenger()->addError($this->t('The token could not be saved.'));
      $form_state->setRebuild();
    }
  }

}
