<?php
namespace Drupal\es_homepage\Controllers;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Session\AccountProxy;
use Drupal\es_homepage\Services\HomePageService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

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

    $return = $this->setup('key_activities', $year, $month);
    $data = $return['#data'];
    $data += $this->homePageService->keyActivities($year, $month, $data['role'], $data['stateName'] ?? '');
    $chart = $this->homePageService->buildChart($data);
    $return['#attached']['drupalSettings']['es_homepage']['chart'] = $chart['chart'];
    $return['#attached']['drupalSettings']['es_homepage']['colors'] = $chart['colors'];
    $return['#data'] = $data;
    return $return;
  }

  public function bestPractices($year = NULL, $month = NULL, $state = NULL) : array {

    $return = $this->setup('best_practices', $year, $month);
    $data = $return['#data'];
    $data += $this->homePageService->bestPractices($year, $month, $data['role'], $data['stateName'] ?? '');
    $chart = $this->homePageService->buildChart($data);
    $return['#attached']['drupalSettings']['es_homepage']['chart'] = $chart['chart'];
    $return['#attached']['drupalSettings']['es_homepage']['colors'] = $chart['colors'];
    $return['#data'] = $data;
    return $return;

  }

  public function downloadUserCSV($year = NULL, $month = NULL) {
    $data = $this->homePageService->buildUserCSV($year, $month);
    $filename = 'user-data.csv';

    $response = new BinaryFileResponse($data);
    $response->setContentDisposition(
      ResponseHeaderBag::DISPOSITION_ATTACHMENT,
      $filename
    );

    return $response;
  }

  public function downloadProviderCSV($year = NULL, $month = NULL) {
    $data = $this->homePageService->buildProviderCSV($year, $month);
    $filename = 'provider-data.csv';

    $response = new BinaryFileResponse($data);
    $response->setContentDisposition(
      ResponseHeaderBag::DISPOSITION_ATTACHMENT,
      $filename
    );

    return $response;
  }

  public function downloadCSV($year = NULL, $month = NULL) {
    $activities = $this->activities($year, $month);
    $practices = $this->bestPractices($year, $month);
    $data = $this->homePageService->buildCSV($activities['#data'], $practices['#data']);

    $filename = 'activity-download';
    if ($provider = $this->homePageService->getProvider()) {
      $filename .= '-' . $provider;
    }
    elseif (!empty($activities['#data']['stateName'])) {
      $stateList = $this->homePageService->getStateList($year, $month);
      $filename .= '-' . $stateList[$activities['#data']['stateName']];
    }
    $filename .= '.csv';

    $response = new BinaryFileResponse($data);
    $response->setContentDisposition(
      ResponseHeaderBag::DISPOSITION_ATTACHMENT,
      $filename
    );

    return $response;
  }

  private function setup($which, $year, $month) {
    $libraries = ['es_homepage/charts'];
    $data = [];

    $this->homePageService->setDateRange($year, $month);
    $data['role'] = 'ANON';
    if ($this->currentUser->isAnonymous()) {
      $data['stateList'] = $this->homePageService->getStateList($year, $month);
      $states = $data['stateList'] ?? [];
      $state = \Drupal::request()->get('state') ?? array_keys($states)[0] ?? '';
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
        $data['provider'] = $provider;
      }
      elseif (in_array('survey_participant', $roles)) {
        $this->homePageService->getProvider();
        $data['role'] = $this->homePageService->getJobType();
      }
      else {
        $data['role'] = 'ANON';
        // Who else is left???
      }
    }

    return [
      '#cache' => [
        'contexts' => [
          'url.query_args:state',
          'url.query_args:provider',
          'user',
        ],
      ],
      '#data' => $data,
      '#theme' => $which,
      '#attached' => [
        'library' => $libraries,
        'drupalSettings' => [
          'es_homepage' => [
            'chart_type' =>  'bar',
          ],
        ],
      ],

    ];
  }

}
