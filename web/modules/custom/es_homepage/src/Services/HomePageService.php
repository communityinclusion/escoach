<?php
namespace Drupal\es_homepage\Services;

use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Site\Settings;
use Drupal\es_homepage\Query\bestPracticesQuery;
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
  const BETTER_YES = 'YES';
  const BETTER_NO = 'NO';

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

      return $this->provider;
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


    $return['title'] = sprintf('Key Activities in %s, %d for ', $this->monthName, $this->year );

    $lastMonthResults = $this->buildKeyActivities($this->year, $this->month, $state);
    $return['lastMonth'] = $this->processResults($lastMonthResults['records'][0], array_keys($lastMonthResults['activities']), $role, TRUE);

    $return['activities'] = $lastMonthResults['activities'];
    if (isset($lastMonthResults['stateName'])) {
      $return['title'] .= $lastMonthResults['stateName'];
    }
    elseif (isset($lastMonthResults['provider'])) {
      $return['title'] .= $lastMonthResults['provider'];
    }

    $prevMonthResults = $this->buildKeyActivities($this->previousYear, $this->previousMonth, $state);
    $return['prevMonth'] = $this->processResults($prevMonthResults['records'][0], array_keys($prevMonthResults['activities']), $role);

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

  private function getTotals(&$data) {
    foreach (['All', 'Me', 'Provider', 'State', 'Observer'] as $scope) {
      $total = 0;
      foreach ($data['activities'] as $activity => $info) {
        if (!empty($data['records'][0][$activity . $scope])) {
          $total+= $data['records'][0][$activity . $scope];
        }
      }
      $data['records'][0]['Total' . $scope] = $total;
    }
  }

  /**
   * @param $year
   * @param $month
   *
   * @return void
   */
  private function buildKeyActivities($year, $month, $state = NULL) {
    $results = [];

    $keyActivities = new keyActivitiesQuery($year, $month, $this->email, $this->provider);

    if (!empty($this->email)) {
      $keyActivities->buildSums('Me');
      $keyActivities->addSelectedSumsTotal('Me');
    }

    if (!empty($this->provider)) {
      $keyActivities->buildSums('Provider');
      $keyActivities->addSelectedSumsTotal('Provider');
      $results['provider'] = $this->provider;
    }

    if (!empty($state)) {
      $results['stateName'] = $this->stateValues[$state];
      $keyActivities->buildSums('State', $state);
      $keyActivities->addSelectedSumsTotal('State', $state);
    }

    $keyActivities->buildSums('Observer');
    $keyActivities->addSelectedSumsTotal('Observer');
    $keyActivities->addSelectedSumsTotal('All');
    $keyActivities->buildSums('All');

    $results['records'] = $keyActivities->execute();
    $results['activities'] = $keyActivities::ACTIVITIES;
    $this->getTotals($results);

    return $results;
  }

  /**
   * @param $data
   *
   * @return void
   */
  private function compareMonths(&$data) {
    // -1 multiplier reverses the comparison for activities where more time spent is worse
    foreach ($data['lastMonth'] as $scope => $info) {
      foreach ($data['activities'] as $machine => $activity) {
        if ($scope == 'All') {
          continue;
        }

        $data['lastMonth'][$scope][$machine]['betterMonth'] = 0;
        $data['lastMonth'][$scope][$machine]['betterAll'] = 0;

        // @todo - should this use avg instead of total?
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

  /**
   * @param $results
   * @param $activities
   * @param $role
   * @param $calcAvg
   *
   * @return array
   */
  private function processResults($results, $activities, $role, $calcAvg = FALSE) {
    $return = [];

    foreach ($activities as $activity) {
      $return['All'][$activity]['total'] = $results[$activity . 'All'];
      $return['All'][$activity]['avg'] = $this->calculateAverage($results, $activity, 'All');
      $return['All'][$activity]['formatted'] = $this->formatDuration($return['All'][$activity]['avg']);
      if ($role == self::ANON_ROLE) {
        $return['State'][$activity]['total'] = $results[$activity . 'State'];
        $return['State'][$activity]['avg'] = $this->calculateAverage($results, $activity, 'State');
        $return['State'][$activity]['formatted'] = $this->formatDuration($return['State'][$activity]['avg']);
      }
      elseif ($role != self::OTHER_ROLE ) {
        $return['Provider'][$activity]['total'] = $results[$activity . 'Provider'];
        $return['Provider'][$activity]['avg'] = $this->calculateAverage($results, $activity, 'Provider');
        $return['Provider'][$activity]['formatted'] = $this->formatDuration($return['Provider'][$activity]['avg']);
        if ($role == self::CONSULTANT_ROLE) {
          $return['Me'][$activity]['total'] = $results[$activity . 'Me'];
          $return['Me'][$activity]['avg'] = $this->calculateAverage($results, $activity,  'Me');
          $return['Me'][$activity]['formatted'] = $this->formatDuration($return['Me'][$activity]['avg']);
        }
      }
    }

    return $return;
  }

  /**
   * @param $results
   * @param $alias
   * @param $scope
   *
   * @return float|int|string
   */
  public function calculateAverage($results, $alias, $scope) {
    $totalCell = 'Total' . ucfirst($scope);
    $dataCell = $alias . ucfirst($scope);

    if (! isset($results[$totalCell]) || $results[$totalCell] == 0) {
      return '0';
    }

    $totalValue = $results[$totalCell];
    if ($scope == 'All') {
      $totalValue -= $results['TotalObserver']; // A - E
    }

    if ($totalValue == 0) {
      return 0;
    }

    $dayTotal = $results[$dataCell] / $totalValue * 8 / 24;

    if ($scope == 'All') {
        if ($alias == "Other") {
          $results[$alias . 'Observer'] = 0;
        }
        $dayTotal = ($results[$dataCell] - $results[$alias . 'Observer']) / $totalValue * 8 / 24;
    }

    return $dayTotal;
  }


  /**
   * @param float $time
   *
   * @return string
   */
  private function formatDuration(float $time) : string {
    $total_hours = $time * 24;
    $hour_part = floor($total_hours);
    $min_part = round(($total_hours - $hour_part) * 60);
    if ($min_part == 60) {
      $hour_part++;
      $min_part = 0;
    }
    return sprintf("%d:%02d", $hour_part, $min_part);
  }

  /**
   * @param $activityData
   * @param $bestPracticesData
   *
   * @return string
   */
  public function buildCSV($activityData, $bestPracticesData) {
    $role = $activityData['role'];
    $headers = ['Activities'];
    switch ($role) {
      case 'ANON':
        $state = $activityData['stateList'][$activityData['stateName']] ?? NULL;
        $headers[] =  $state ?? 'State';
        $headers[] = 'Better than Last Month';
        $headers[] = 'Better than All';
        break;
      case 'Employment consultant':
        $headers[] = 'Me';
        $headers[] = 'Better than Last Month';
        $headers[] = 'Better than All';
        $headers[] = 'My Team';
        $headers[] = 'Better than Last Month';
        $headers[] = 'Better than All';
        break;
      case 'Manager':
        $headers[] = 'My Team';
        $headers[] = 'Better than Last Month';
        $headers[] = 'Better than All';
        break;
      case 'TA':
        $headers[] = $activityData['provider'] ?? 'Provider';
        $headers[] = 'Better than Last Month';
        $headers[] = 'Better than All';
    }
    $headers[] = 'All';

    $return = implode(',', $headers) . "\n";
    $row = [];

    foreach ($activityData['activities'] as $machine => $info) {
      $row = [$info['label'] ];
      switch ($role) {
        case 'ANON':
          $row = array_merge($row, $this->_getDataByScope($activityData, 'State', $machine));
          break;
        case 'Employment consultant':
          $row = array_merge($row, $this->_getDataByScope($activityData, 'Me', $machine));
          $row = array_merge($row, $this->_getDataByScope($activityData, 'Provider', $machine));
          break;
        case 'Manager':
          $row = array_merge($row, $this->_getDataByScope($activityData, 'Provider', $machine));
          break;
        case 'TA':
          $row = array_merge($row, $this->_getDataByScope($activityData, 'Provider', $machine));
      }
      $row[] = $activityData['lastMonth']['All'][$machine]['formatted'];
      $return .= implode(',', $row) . "\n";
    }


    $return .= $this->buildResponseRateRows($activityData);

    foreach ($bestPracticesData['activities'] as $machine => $info) {
      $row = [$info['label'] ];
      switch ($role) {
        case 'ANON':
          $row = array_merge($row, $this->_getDataByScope($bestPracticesData, 'State', $machine));
          break;
        case 'Employment consultant':
          $row = array_merge($row, $this->_getDataByScope($bestPracticesData, 'Me', $machine));
          $row = array_merge($row, $this->_getDataByScope($bestPracticesData, 'Provider', $machine));
          break;
        case 'Manager':
          $row = array_merge($row, $this->_getDataByScope($bestPracticesData, 'Provider', $machine));
          break;
        case 'TA':
          $row = array_merge($row, $this->_getDataByScope($bestPracticesData, 'Provider', $machine));
      }
      $row[] = $bestPracticesData['lastMonth']['All'][$machine]['formatted'];
      $return .= implode(',', $row) . "\n";
    }


    /** @var \Drupal\file\FileRepositoryInterface $fileRepository */
    $fileRepository = \Drupal::service('file.repository');
    $filename = \Drupal::service('file_system')->tempnam('temporary://', 'tmp_', Settings::get('file_temporary_path'));
    $fileRepository->writeData($return, $filename, FileSystemInterface::EXISTS_REPLACE);
    return $filename;
  }

  /**
   * @param $activityData
   *
   * @return string
   */
  private function buildResponseRateRows($activityData) {
    $role = $activityData['role'];
    $row1 = ['Response Rate'];
    $row2 = ['# of Respondents'];
    switch ($role) {
      case 'ANON':
        $row1 = array_merge($row1, $this->_getResponseRateByScope($activityData, 'State', 'responseRate'));
        $row2 = array_merge($row2, $this->_getResponseRateByScope($activityData, 'State', 'netResponses'));
        break;
      case 'Employment consultant':
        $row1 = array_merge($row1, $this->_getResponseRateByScope($activityData, 'Me', 'responseRate'));
        $row2 = array_merge($row2, $this->_getResponseRateByScope($activityData, 'Me', 'netResponses'));

        $row1 = array_merge($row1, $this->_getResponseRateByScope($activityData, 'Provider', 'responseRate'));
        $row2 = array_merge($row2, $this->_getResponseRateByScope($activityData, 'Provider', 'netResponses'));
        break;
      case 'Manager':
        $row1 = array_merge($row1, $this->_getResponseRateByScope($activityData, 'Provider', 'responseRate'));
        $row2 = array_merge($row2, $this->_getResponseRateByScope($activityData, 'Provider', 'netResponses'));
        break;
      case 'TA':
        $row1 = array_merge($row1, $this->_getResponseRateByScope($activityData, 'Provider', 'responseRate'));
        $row2 = array_merge($row2, $this->_getResponseRateByScope($activityData, 'Provider', 'netResponses'));
    }

    $row1 = array_merge($row1, $this->_getResponseRateByScope($activityData, 'All', 'responseRate'));
    $row2 = array_merge($row2, $this->_getResponseRateByScope($activityData, 'All', 'netResponses'));

    $return = implode(',', $row1) . "\n";
    $return .= implode(',', $row2) . "\n";


    return $return;
  }

  /**
   * @param $data
   * @param $scope
   * @param $which
   *
   * @return array
   */
  private function _getResponseRateByScope($data, $scope, $which) {
    $val = $data['responseRate'][$scope][$which];
    if ($which == 'responseRate') {
      $val *= 100;
    }

    return [ $val,'','', ];
  }

  /**
   * @param $data
   * @param $scope
   * @param $machine
   *
   * @return array
   */
  private function _getDataByScope($data, $scope, $machine) : array {
    return [
      $data['lastMonth'][$scope][$machine]['formatted'],
      ($data['lastMonth'][$scope][$machine]['betterMonth'] ) ? self::BETTER_YES : self::BETTER_NO,
      ($data['lastMonth'][$scope][$machine]['betterAll'] ) ? self::BETTER_YES : self::BETTER_NO
    ];
  }

  /**
   * @param array $data
   *
   * @return array
   */
  public function buildChart(array $data) : array {

    $role = $data['role'];
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
        $stateName = $data['stateList'][$data['stateName']];
        $chart[] = $this->buildRow('State', $data, $stateName ?? $data['stateName'] );
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
      $row[] = (300 * $data['lastMonth'][$source][$machine]['avg']);
    }
    return $row;
  }

  private function buildBestPractices($year, $month, $state) {
    $results = [];
    $bestPractices = new bestPracticesQuery($this->year, $this->month, $this->email, $this->provider);

    if (!empty($this->email)) {
      $bestPractices->buildSums('Me');
      $bestPractices->addSelectedSumsTotal('Me');
    }

    if (!empty($this->provider)) {
      $bestPractices->buildSums('Provider');
      $bestPractices->addSelectedSumsTotal('Provider');
      $results['provider'] = $this->provider;
    }

    if (!empty($state)) {
      $bestPractices->buildSums('State', $state);
      $bestPractices->addSelectedSumsTotal('State', $state);
      $results['stateName'] = $this->stateValues[$state];
    }

    $bestPractices->buildSums('Observer');
    $bestPractices->addSelectedSumsTotal('Observer');
    $bestPractices->addSelectedSumsTotal('All');
    $bestPractices->buildSums('All');

    $results['records'] = $bestPractices->execute();
    $results['activities'] = $bestPractices::PRACTICES;
    $this->getTotals($results);

    return $results;
  }

  /**
   * @param $year
   * @param $month
   * @param $state
   *
   * @return array
   */
  public function bestPractices($year, $month, $role,  $state = NULL) : array {
    $return = [];

    $this->setDateRange($year, $month);

    $return['title'] = sprintf('Key Activities in %s, %d for ', $this->monthName, $this->year );

    $lastMonthResults = $this->buildBestPractices($this->year, $this->month, $state);
    $return['lastMonth'] = $this->processResults($lastMonthResults['records'][0], array_keys($lastMonthResults['activities']), $role, TRUE);

    $return['activities'] = $lastMonthResults['activities'];
    if (isset($lastMonthResults['stateName'])) {
      $return['title'] .= $lastMonthResults['stateName'];
    }
    elseif (isset($lastMonthResults['provider'])) {
      $return['title'] .= $lastMonthResults['provider'];
    }

    $prevMonthResults = $this->buildBestPractices($this->previousYear, $this->previousMonth, $state);
    $return['prevMonth'] = $this->processResults($prevMonthResults['records'][0], array_keys($prevMonthResults['activities']), $role);

    $this->compareMonths($return);


    return $return;

  }
}
