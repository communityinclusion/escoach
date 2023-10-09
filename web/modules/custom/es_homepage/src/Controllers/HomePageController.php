<?php
namespace Drupal\es_homepage\Controllers;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Session\AccountProxy;
use Drupal\es_homepage\Services\HomePageService;
use Symfony\Component\DependencyInjection\ContainerInterface;

class HomePageController extends ControllerBase {

  const TA_ROLE = 'ta_admin';

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

    $libraries = ['es_homepage/charts'];
    $data = [];

    $this->homePageService->setDateRange($year, $month);
    $data['role'] = 'ANON';
    if ($this->currentUser->isAnonymous()) {
      $data['stateList'] = $this->homePageService->getStateList($year, $month);
      $state = \Drupal::request()->get('state') ?? array_keys($data['stateList'])[0] ?? '';
      $data['stateName'] = $state;
      $libraries[] = 'es_homepage/states';
    }
    else {
      $state = NULL;
      $roles = $this->currentUser->getRoles();
      if (in_array(self::TA_ROLE, $roles)) {
        $data['role'] = 'TA';
        $data['providerList'] = $this->homePageService->getProviderList();
        $libraries[] = 'es_homepage/providers';
        $provider = \Drupal::request()->get('provider') ?? $data['providerList'][0];
        $this->homePageService->setCurrentProvider($provider);
      }
      elseif (in_array('survey_participant', $roles)) {
        $this->homePageService->getProvider();
        $data['role'] = $this->homePageService->getJobType();
      }
      else {
        $data['role'] = 'OTHER';
        // Who else is left???
      }
    }

    $data += $this->homePageService->keyActivities($year, $month, $data['role'], $state);
    $chart = $this->homePageService->buildChart($data);

    return [
      '#cache' => [
        'contexts' => [
          'url.query_args:state',
          'url.query_args:provider',
          'user',
        ]
      ],
      '#theme' => 'key_activities',
      '#attached' => [
        'library' => $libraries,
        'drupalSettings' => [
          'es_homepage' => [
            'chart' => $chart['chart'],
            'colors' => $chart['colors'],
            'chart_type' =>  'bar',
          ],
        ],
      ],
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
