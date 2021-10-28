<?php

namespace Drupal\Admincss\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Controller routines for page example routes.
 */
class AdmincssController extends ControllerBase {


  /**
   * {@inheritdoc}
   */
  protected function getModuleName() {
    return 'admincss';
  }
}
