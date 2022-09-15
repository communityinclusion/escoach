<?php

namespace Drupal\admin_can_login_anyuser\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\Core\Session\SessionManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\user\UserStorageInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\Core\TempStore\PrivateTempStoreFactory;

/**
 * Class AdminBackLoginLinkController.
 */
class AdminBackLoginLinkController extends ControllerBase {

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
   * The tempstore.
   *
   * @var \Drupal\Core\TempStore\SharedTempStore
   */
  protected $tempStore;

  /**
   * Use core services object.
   *
   * @param \Drupal\paypal\Form\SessionManagerInterface $session_manager
   *   User's session manager.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\user\UserStorageInterface $user_storage
   *   The user storage.
   * @param \Drupal\Core\TempStore\PrivateTempStoreFactory $temp_store_factory
   *   The tempstore factory.
   */
  public function __construct(SessionManagerInterface $session_manager, EntityTypeManagerInterface $entity_type_manager, UserStorageInterface $user_storage, PrivateTempStoreFactory $temp_store_factory) {
    $this->sessionManager = $session_manager;
    $this->entityTypeManager = $entity_type_manager;
    $this->userStorage = $user_storage;
    $this->tempStore = $temp_store_factory->get('admin_can_login_anyuser');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('session_manager'),
      $container->get('entity_type.manager'),
      $container->get('entity_type.manager')->getStorage('user'),
      $container->get('tempstore.private')
    );
  }

  /**
   * BackAdminLogin.
   */
  public function backAdminLogin($user) {
    $administrator_user_id = $this->tempStore->get('administrator_user_id');
    $this->tempStore->delete('administrator_user_id');

    if (!empty($administrator_user_id)) {
      if ($user == $administrator_user_id) {
        $this->sessionManager->delete($this->currentUser()->id());
        $name = $this->entityTypeManager->getStorage('user')->load($user)->label();
        $account = $this->userStorage->load($user);
        user_login_finalize($account);
        $this->messenger()->addMessage($this->t('%name account has been successfully logged in', ['%name' => $name]));
        $redirect_url = Url::fromUri('internal:/admin/people');
        $response = new RedirectResponse($redirect_url->toString());
        $response->send();
      }
    }
    else {
      $url = Url::fromRoute('system.403');
      $response = new RedirectResponse($url->toString());
      $response->send();
    }
  }

}
