<?php
namespace Drupal\es_homepage\Services;

use Drupal\es_homepage\Query\bestPractices;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountProxy;
use Drupal\es_homepage\Query\HomePageQuery;
use Drupal\es_homepage\Query\keyActivitiesQuery;
use Drupal\es_homepage\Query\StateQuery;


class HomePageService {

  private $year;

  private $month;

  private $email;

  /**
   * @var string
   */
  private $provider;

  /**
   * @var array
   */
  private $providerList;

  /**
   * @var string
   */
  private $previousMonth;

  /**
   * @var string
   */
  private $previousYear;

  /**
   * @var \Drupal\Core\Session\AccountProxy
   */
  private $currentUser;

  /**
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  private $entityTypeManager;

  private $job_type;

  /**
   * @var array
   */
  private $stateList;

  public function __construct(AccountProxy $currentUser, EntityTypeManagerInterface $entityTypeManager) {
    $this->email = $currentUser->getEmail();
    $this->currentUser = $currentUser;
    $this->entityTypeManager = $entityTypeManager;

    // Default values
    $this->job_type = 'Other';
    $this->provider = '';
  }

  public function getProvider() {
    $profiles = $this->entityTypeManager->getStorage('profile')
      ->loadByProperties([
        'uid' => $this->currentUser->id(),
        'type' => 'survey_participants',
        'is_default' => 1,
      ]);

    if ($profiles) {
      $profile = current($profiles);
    }
    if (isset($profile)) {
      if (isset($profile->field_provider->entity)) {
        $this->provider = $profile->field_provider->entity->getName();
      }
      if (isset($profile->field_job_type->value)) {
        $this->job_type = $profile->field_job_type->value;
      }
    }
  }

  public function setCurrentProvider($provider) {
    $this->provider = $provider;
  }

  public function setDateRange($year = NULL, $month = NULL) {

    if (!$year || !$month) {
      $today = new \DateTime();
      $lastMonth = $today->modify('first day of last month');
      $month = $lastMonth->format('m');
      $year = $lastMonth->format('Y');
    }

    $this->year = $year;
    $this->month = $month;

    $month = sprintf('%02d', $month);
    $dt = new \DateTime("$year-$month-01 00:00:00");
    $prev = $dt->sub( \DateInterval::createFromDateString('1 days'));

    $this->previousMonth = $prev->format('m');
    $this->previousYear = $prev->format('Y');
  }

  public function getStateList($year, $month) {
    $query = new StateQuery($year, $month, '', '');
    $this->stateList = $query->execute();
  }

  public function getProviderList() {
    $profiles = $this->entityTypeManager->getStorage('profile')
      ->loadByProperties([
        'uid' => $this->currentUser->id(),
        'type' => 'technical_assistant',
        'is_default' => 1,
      ]);

    if ($profiles) {
      $profile = current($profiles);
    }
    if (isset($profile)) {
      if (isset($profile->field_providers)) {
        foreach ($profile->field_providers as $provider) {
          $this->providerList[] = $provider->entity->getName();
        }
      }
    }
  }

  public function keyActivities($year, $month, $state = NULL) : array {
    $this->setDateRange($year, $month);
    $keyActivities = new keyActivitiesQuery($this->year, $this->month, $this->email, $this->provider);

    if (!empty($this->email)) {
      $keyActivities->buildSums('Me');
    }

    if (!empty($this->provider)) {
      $keyActivities->buildSums('Provider');
    }

    if (!empty($state)) {
      $keyActivities->buildSums('State', $state);
    }

    $keyActivities->buildSums('All');

    $lastMonthResults = $keyActivities->execute();

    $keyActivities = new keyActivitiesQuery($this->previousYear, $this->previousMonth, $this->email, $this->provider);

    if (!empty($this->email)) {
      $keyActivities->buildSums('Me');
    }
    if (!empty($this->provider)) {
      $keyActivities->buildSums('Provider');
    }
    if (!empty($state)) {
      $keyActivities->buildSums('State', $state);
    }

    $keyActivities->buildSums('All');

    $prevMonthResults = $keyActivities->execute();

    return [
      'activities' => array_keys($keyActivities::ACTIVITIES),
      'lastMonth' => $lastMonthResults[0],
      'prevMonth' => $prevMonthResults[0],
    ];
  }

  public function bestPractices($year, $month, $state = NULL) : array {
    $this->setDateRange($year, $month);
    $bestPractices = new bestPractices($this->year, $this->month, $this->email, $this->provider);

    if (!empty($this->email)) {
      $bestPractices->buildSums('Me');
    }

    if (!empty($this->provider)) {
      $bestPractices->buildSums('Provider');
    }

    if (!empty($state)) {
      $bestPractices->buildSums('State', $state);
    }

    $bestPractices->buildSums('All');

    $lastMonthResults = $bestPractices->execute();

    $bestPractices = new bestPractices($this->previousYear, $this->previousMonth, $this->email, $this->provider);

    if (!empty($this->email)) {
      $bestPractices->buildSums('Me');
    }

    if (!empty($this->provider)) {
      $bestPractices->buildSums('Provider');
    }

    if (!empty($state)) {
      $bestPractices->buildSums('State', $state);
    }

    $bestPractices->buildSums('All');

    $prevMonthResults = $bestPractices->execute();
    return [
      'lastMonth' => $lastMonthResults,
      'prevMonth' => $prevMonthResults
    ];
  }
}
