<?php
namespace Drupal\es_homepage\Services;

use Drupal\es_homepage\Query\bestPractices;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountProxy;
use Drupal\es_homepage\Query\HomePageQuery;
use Drupal\es_homepage\Query\keyActivitiesQuery;
use Drupal\es_homepage\Query\ResponseRateQuery;
use Drupal\es_homepage\Query\StateQuery;


class HomePageService {

  const FULL_MONTH_NAME_FORMAT = 'F';
  const ABBR_MONTH_NAME_FORMAT = 'M';
  const CONSULTANT_ROLE = 'Employment consultant';
  const MANAGER_ROLE = 'Manager';
  const TA_ROLE = 'TA';
  const ANON_ROLE = 'ANON';
  const OTHER_ROLE = 'OTHER';

  private $stateValues = [
    'AL'  => 'Alabama',
    'AK' => 'Alaska',
    'AZ' => 'Arizona',
    'AR' => 'Arkansas',
    'CA' => 'California',
    'CO' => 'Colorado',
    'CT' => 'Connecticut',
    'DE' => 'Delaware',
    'FL' => 'Florida',
    'GA' => 'Georgia',
    'HI' => 'Hawaii',
    'ID' => 'Idaho',
    'IL' => 'Illinois',
    'IN' => 'Indiana',
    'IA' => 'Iowa',
    'KS' => 'Kansas',
    'KY' => 'Kentucky',
    'LA' => 'Louisiana',
    'ME' => 'Maine',
    'MD' => 'Maryland',
    'MA' => 'Massachusetts',
    'MI' => 'Michigan',
    'MN' => 'Minnesota',
    'MS' => 'Mississippi',
    'MO' => 'Missouri',
    'MT' => 'Montana',
    'NE' => 'Nebraska',
    'NV' => 'Nevada',
    'NH' => 'New Hampshire',
    'NJ' => 'New Jersey',
    'NM' => 'New Mexico',
    'NY' => 'New York',
    'NC' => 'North Carolina',
    'ND' => 'North Dakota',
    'OH' => 'Ohio',
    'OK' => 'Oklahoma',
    'OR' => 'Oregon',
    'PA' => 'Pennsylvania',
    'RI' => 'Rhode Island',
    'SC' => 'South Carolina',
    'SD' => 'South Dakota',
    'TN' => 'Tennessee',
    'TX' => 'Texas',
    'UT' => 'Utah',
    'VT' => 'Vermont',
    'VA' => 'Virginia',
    'WA' => 'Washington',
    'WV' => 'West Virginia',
    'WI' => 'Wisconsin',
    'WY' => 'Wyoming',
  ];
  private $year;

  private $month;

  private $monthName;

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
    $this->providerList = [];
  }

  /**
   * @return string|void
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getProvider() {

    if (!empty($this->provider)) {
      return $this->provider;
    }

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

  /**
   * @return string
   */
  public function getJobType() {
    return $this->job_type;
  }

  /**
   * @param $provider
   *
   * @return void
   */
  public function setCurrentProvider($provider) {
    $this->provider = $provider;
  }

  /**
   * @param $year
   * @param $month
   *
   * @return void
   */
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

    $this->monthName = $dt->format(self::ABBR_MONTH_NAME_FORMAT);

    $prev = $dt->sub( \DateInterval::createFromDateString('1 days'));

    $this->previousMonth = $prev->format('m');
    $this->previousYear = $prev->format('Y');
  }

  /**
   * @param $year
   * @param $month
   *
   * @return array
   */
  public function getStateList($year, $month) {
    if (!$this->stateList) {
      $query = new StateQuery($year, $month, '', '');
      $results = $query->execute();
      foreach ($results as $result)  {
        if (!empty($result['state'])) {
          $this->stateList[$result['state']] = $this->stateValues[ $result['state']];
        }
      }
      asort($this->stateList);
      reset($this->stateList);
    }
    return $this->stateList;
  }

  /**
   * @return void
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getProviderList() {
    if (!empty($this->providerList)) {
      return $this->providerList;
    }

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
    return $this->providerList;
  }

  /**
   * @param $year
   * @param $month
   * @param $state
   *
   * @return array
   */
  public function keyActivities($year, $month, $role, $state = NULL) : array {

    $return = [];

    $this->setDateRange($year, $month);
    $keyActivities = new keyActivitiesQuery($this->year, $this->month, $this->email, $this->provider);

    $return['activities'] = $keyActivities::ACTIVITIES;

    $return['title'] = sprintf('Key Activities in %s, %d for ', $this->monthName, $this->year );

    if (!empty($this->email)) {
      $keyActivities->buildSums('Me');
    }

    if (!empty($this->provider)) {
      $keyActivities->buildSums('Provider');
      $return['provider'] = $this->provider;
    }

    if (!empty($state)) {
      $keyActivities->buildSums('State', $state);
      $return['stateName'] = $this->stateValues[$state];
      $return['title'] .= $return['stateName'];
    }

    $keyActivities->buildSums('All');

    $lastMonthResults = $keyActivities->execute();
    $return['lastMonth'] = $this->processResults($lastMonthResults[0], array_keys($return['activities']), $role, TRUE);

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
    else {
      $return['title'] .= $this->provider;
    }

    $keyActivities->buildSums('All');

    $prevMonthResults = $keyActivities->execute();
    $return['prevMonth'] = $this->processResults($prevMonthResults[0], array_keys($return['activities']), $role);

    $this->compareMonths($return);

    $query = new ResponseRateQuery($this->year, $this->month, $this->email, $this->provider);
    $results = $query->execute();
    $return['responseRate']['All'] = $results[0];

    if ($role == self::CONSULTANT_ROLE) {
      $query->addMe();
      $results = $query->execute();
      $return['responseRate']['Me'] = $results[0];
    }

    $query = new ResponseRateQuery($this->year, $this->month, $this->email, $this->provider);

    if ($role == self::ANON_ROLE) {
      $query->addState($state);
      $results = $query->execute();
      $return['responseRate']['State'] = $results[0];
    }
    else {
      $query->addProvider();
      $results = $query->execute();
      $return['responseRate']['Provider'] = $results[0];
    }
    return $return;
  }

  private function compareMonths(&$data) {
    // -1 multiplier basically reverses the comparison for activities where more time spent is worse
    foreach ($data['lastMonth'] as $scope => $info) {
      foreach ($data['activities'] as $machine => $activity) {
        if ($scope == 'All') {
          continue;
        }
        $last = $info[$machine]['total'] * $activity['multiplier'];
        $prev = $data['prevMonth'][$scope][$machine]['total'] * $activity['multiplier'];
        if ($last > $prev) {
          $data['lastMonth'][$scope][$machine]['betterMonth'] = 1;
        }

        $last = $info[$machine]['avg'] * $activity['multiplier'];
        $all = $data['prevMonth']['All'][$machine]['avg'] * $activity['multiplier'];
        if ($last > $all) {
          $data['lastMonth'][$scope][$machine]['betterAll'] = 1;
        }
      }
    }
  }
  private function processResults($results, $activities, $role, $calcAvg = FALSE) {
    $return = [];

    foreach ($activities as $activity) {
      $return['All'][$activity]['total'] = $results[$activity . 'All'];
      $return['All'][$activity]['avg'] = $this->calculateAverage($results[$activity . 'All']);
      if ($role == self::ANON_ROLE) {
        $return['State'][$activity]['total'] = $results[$activity . 'State'];
        $return['State'][$activity]['avg'] = $this->calculateAverage($results[$activity . 'State']);
      }
      elseif ($role != self::OTHER_ROLE ) {
        $return['Provider'][$activity]['total'] = $results[$activity . 'Provider'];
        $return['Provider'][$activity]['avg'] = $this->calculateAverage($results[$activity . 'Provider']);
        if ($role == self::CONSULTANT_ROLE) {
          $return['Me'][$activity]['total'] = $results[$activity . 'Me'];
          $return['Me'][$activity]['avg'] = $this->calculateAverage($results[$activity . 'Me']);
        }
      }
    }

    return $return;
  }

  public function calculateAverage($val) {
    return $val;
  }

  /**
   * @param array $data
   * @param string $role
   *
   * @return array
   */
  public function buildChart(array $data, string $role) : array {
    $chart = [];

    $default_colors = [
      '#0000e1',
      '#228b22',
      '#d3d3d3',
      '#ffa500',
    ];

    $activityList = [

    ];

    $chart[] = ['Activities'];
    foreach ($data['activities'] as $machine => $info) {
      $title = $info['label'];
      $chart[0][] = $title;
    }

    switch ($role) {
      case self::ANON_ROLE:
        $chart[] = $this->buildRow('State', $data, $data['stateName'] );
        break;

      case self::CONSULTANT_ROLE:
        $chart[] = $this->buildRow('Me', $data);
        // Intentional Drop-thru
      case self::MANAGER_ROLE:
        $chart[] = $this->buildRow('Provider', $data, 'My Team');
        break;

      case self::TA_ROLE:
        $chart[] = $this->buildRow('Provider', $data, $this->provider);
        break;
    }

    $chart[] = $this->buildRow('All', $data);

    return [
      'chart' => $chart,
      'colors' => $default_colors,
    ];
  }

  private function buildRow($source, $data, $label = NULL) : array {

    $row = [$label ?? $source];
    foreach (array_keys($data['activities']) as $machine ) {
      $row[] = (int)(300 * $data['lastMonth'][$source][$machine]['total']);
    }
    return $row;
  }

  /**
   * @param $year
   * @param $month
   * @param $state
   *
   * @return array
   */
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
