<?php
namespace Drupal\es_homepage\Controllers;

use Drupal\Core\Controller\ControllerBase;
use Drupal\es_homepage\Services\HomePageService;
use Symfony\Component\DependencyInjection\ContainerInterface;

class HomePageController extends ControllerBase {


  /**
   * @var HomePageService
   */
  private $homePageService;

  public function __construct(HomePageService $homePageService) {
    $this->homePageService = $homePageService;
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('es_homepage.home_page_service')
    );
  }

  public function activities($year = NULL, $month = NULL, $state = NULL) : array {

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
