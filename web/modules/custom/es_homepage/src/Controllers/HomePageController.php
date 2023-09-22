<?php
namespace Drupal\es_homepage\Controllers;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Session\AccountProxy;
use Drupal\es_homepage\Services\HomePageService;
use Symfony\Component\DependencyInjection\ContainerInterface;

class HomePageController extends ControllerBase {


  /**
   * @var HomePageService
   */
  private $homePageService;

  /**
   * @param \Drupal\es_homepage\Services\HomePageService $homePageService
   * @param \Drupal\Core\Session\AccountProxy $currentUser
   */
  public function __construct(HomePageService $homePageService, AccountProxy $currentUser) {
    $this->homePageService = $homePageService;
    $this->currentUser = $currentUser;
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('es_homepage.home_page_service'),
      $container->get('current_user'),
    );
  }

  public function activities($year = NULL, $month = NULL) : array {

    $this->homePageService->setDateRange($year, $month);

    if ($this->currentUser->isAnonymous()) {
      $this->homePageService->getStateList($year, $month);
    }
    else {
      $roles = $this->currentUser->getRoles();
      if (in_array('TA', $roles)) {
        $this->homePageService->getProviderList();
      }
      elseif (in_array('survey_participant', $roles)) {
        $this->homePageService->getProvider();
      }
      else {
        // Who else is left???
      }
    }

    $data = $this->homePageService->keyActivities($year, $month, $state);

    return [
      '#theme' => 'key_activities',
      '#data' => $data,
    ];
  }

  public function bestPractices($year = NULL, $month = NULL, $state = NULL) : array {

    $data = $this->homePageService->bestPractices($year, $month, $state);
    return [
      '#theme' => 'best_practices',
      '#data' => $data,
    ];
  }

}
