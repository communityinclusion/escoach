<?php

namespace Drupal\admin_can_login_anyuser\Form;

use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\Core\Session\SessionManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\user\UserStorageInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class ConfirmLoginSwitchForm.
 */
class ConfirmLoginSwitchForm extends ConfirmFormBase {

  /**
   * Drupal\Core\Session\SessionManagerInterface definition.
   *
   * @var \Drupal\Core\Session\SessionManagerInterface
   */
  protected $sessionManager;

  /**
   * Drupal\Core\Entity\EntityTypeManagerInterface definition.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * ID of the item to end session.
   *
   * @var int
   */
  protected $user;

  /**
   * The user storage.
   *
   * @var \Drupal\user\UserStorageInterface
   */
  protected $userStorage;

  /**
   * Use core services object.
   *
   * @param \Drupal\paypal\Form\SessionManagerInterface $session_manager
   *   User's session manager.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\user\UserStorageInterface $user_storage
   *   The user storage.
   */
  public function __construct(SessionManagerInterface $session_manager, EntityTypeManagerInterface $entity_type_manager, UserStorageInterface $user_storage) {
    $this->sessionManager = $session_manager;
    $this->entityTypeManager = $entity_type_manager;
    $this->userStorage = $user_storage;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('session_manager'),
      $container->get('entity_type.manager'),
      $container->get('entity_type.manager')->getStorage('user')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return "confirm_login_switch_form";
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    $name = $this->entityTypeManager->getStorage('user')->load($this->id)->label();
    return $this->t('Are you sure you want to login %name? account', ['%name' => $name]);
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return new Url('entity.user.collection');
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return $this->t('Login');
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, string $user = NULL) {
    $this->id = $user;
    if (empty($this->entityTypeManager->getStorage('user')->load($this->id))) {
      throw new NotFoundHttpException();
    }
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $currentLoginUserRoles = [];
    $currentLoginUserRoles = $this->currentUser()->getAccount()->getRoles();
    $user_id = $this->currentUser()->id();
    $this->sessionManager->delete($this->currentUser()->id());
    $name = $this->entityTypeManager->getStorage('user')->load($this->id)->label();
    $account = $this->userStorage->load($this->id);
    user_login_finalize($account);
    /*
     * Checked if user have 'administrator' roles assign
     */
    if (in_array('administrator', $currentLoginUserRoles)) {
      $tempstore_administrator = \Drupal::service('tempstore.private')->get('admin_can_login_anyuser');
      $tempstore_administrator->set('administrator_user_id', $user_id);
    }
    $this->messenger()->addMessage($this->t('%name account has been successfully logged in', ['%name' => $name]));
    $form_state->setRedirect('user.page');
  }

}
