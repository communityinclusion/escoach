<?php

namespace Drupal\survey_dashboard\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountProxy;
use Drupal\survey_dashboard\Query\BaseQuery;
use Drupal\survey_dashboard\Query\What;
use Drupal\survey_dashboard\Query\Where;
use Drupal\survey_dashboard\Query\Who;

/**
 * Query builder service.
 */
class QueryBuilder {

  const BASE_TABLE = 'surveycampaign_results';

  /**
   * Value from who field.
   *
   * @var int
   */
  protected $who;

  /**
   * Value from where field.
   *
   * @var int
   */
  protected $where;

  /**
   * Value from what field.
   *
   * @var int
   */
  protected $what;

  /**
   * Value from timeframe field.
   *
   * @var int
   */
  protected $timeframe;

  /**
   * Value from dataframe field.
   *
   * @var int
   */
  protected $dataframe;

  /**
   * The email address of the current user.
   *
   * @var int
   */
  protected $email;

  /**
   * The provider name of the current user.
   *
   * @var int
   */
  protected $provider;
  /**
   * The current_user service.
   *
   * @var \Drupal\Core\Session\AccountProxy
   */
  private $currentUser;
  /**
   * The entitytypeManager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  private $entityTypeManager;

  /**
   * The theme used to render the results.
   *
   * @var string
   */
  private $theme;

  /**
   * The title for the results.
   */
  private $title;

  /**
   * The title(s) of the selected WHAT options.
   *
   * @var array
   */
  private $whatTitles;

  /**
   * The title(s) of the selected WHO options.
   *
   * @var array
   */
  private $whoTitles;

  /**
   * The title(s) of the selected WHERE options.
   *
   * @var array
   */
  private $whereTitles;

  /**
   * Constructor.
   */
  public function __construct(AccountProxy $currentUser, EntityTypeManagerInterface $entityTypeManager) {

    $this->currentUser = $currentUser;
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * Get response IDs for selected terms.
   */
  private function getTaxonomyValue($vid, $tid) {
    if (!$tid) {
      return NULL;
    }

    if ($vid == 'what' && count($tid) == 1) {
      $tid = current($tid);
    }

    $titleProp = $vid . 'Titles';
    if (is_array($tid)) {
      $return = [];
      $titles = [];
      $terms = $this->entityTypeManager->getStorage('taxonomy_term')->loadMultiple($tid);
      foreach ($terms as $term) {
        $titles[] = $term->label();
        $return[] = [
          $term->field_dashboard_question_id->value => $term->field_dashboard_response_id->value,
        ];
      }

      $this->$titleProp = $titles;
      return $return;
    }
    else {
      $term = $this->entityTypeManager->getStorage('taxonomy_term')->load($tid);
    }

    if (!$term) {
      return NULL;
    }

    $this->$titleProp = [$term->label()];

    if ($vid != 'who') {
      return [
        $term->field_dashboard_question_id->value => $term->field_dashboard_response_id->value,
      ];
    }
    else {
      $return = [];
      $qidMap = [];
      $idx = 0;
      $max = count($term->field_dashboard_question_id);
      foreach ($term->field_dashboard_response_id as $resp) {
        $qid = ($idx < $max ) ? $idx : $idx % $max;
        $qidMap[$term->field_dashboard_question_id[$qid]->value][] = $resp->value;
        $idx++;
      }
      foreach ($qidMap as $qid => $resp_ids) {
        $return[] = [
          $qid => $resp_ids,
        ];
      }
      return $return;

      return [
        $term->field_dashboard_question_id[0]->value => $term->field_dashboard_response_id[0]->value,
        $term->field_dashboard_question_id[1]->value => $term->field_dashboard_response_id[1]->value,
      ];
    }
  }

  /**
   * Primary controller.
   */
  public function process($params = []) {

    $this->getProvider();
    $this->timeframe = $params['timeframe'];
    $this->dataframe = $params['dataframe'];

    $this->who = ($params['who'] == 'any') ? 'any' : $this->getTaxonomyValue('who', $params['who']);
    $this->what = ($params['what'] == 'any') ? 'any' : $this->getTaxonomyValue('what', $params['what']);
    $this->where = ($params['where'] == 'any') ? 'any' : $this->getTaxonomyValue('where', $params['where']);
    $this->email = $this->currentUser->getEmail();

    /** @var \Drupal\survey_dashboard\Query\BaseQuery $query */
    if ($this->timeframe == 'monthly' || $this->timeframe == 'quarterly') {
      $query = $this->buildTrendsQuery();
      $this->theme = $this->timeframe . '-trends';
      $this->title = ucfirst($this->timeframe) . ' Trends';
      $trends = TRUE;
    }
    else {
      $query = $this->buildQuery();
      $trends = FALSE;

      if (!$query) {
        return [
          '#markup' => t('Invalid selection.  Please select at least one "What" option'),
        ];
      }
    }

    return [
      '#theme' => $this->theme,
      '#data' => ($trends) ? $this->processResultsTrends($query) : $this->processResultsSummary($query),
    ];
  }

  /**
   *
   */
  private function buildTrendsQuery() {

    $query = $this->selectedActivities();

    switch ($this->timeframe) {
      case 'quarterly':
        $this->theme = 'quarterly-trends';
        $query->addQuarterlyParams();
        break;

      case 'monthly':
        $this->theme = 'monthly-trends';
        $query->addMonthlyParams();
        break;
    }

    return $query;
  }

  /**
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  private function getProvider() {
    $profiles = $this->entityTypeManager->getStorage('profile')
      ->loadByProperties([
        'uid' => $this->currentUser->id(),
        'type' => 'survey_participants',
        'is_default' => 1,
      ]);

    if ($profiles) {
      $profile = current($profiles);
    }
    if ($profile && $profile->field_provider->entity) {
      $this->provider = $profile->field_provider->entity->getName();
    }

  }

  /**
   *
   */
  private function getTrendAliasMap() {
    if ($this->timeframe == 'monthly') {
      return [
        1 => [
          'title' => 'January',
        ],
        2 => [
          'title' => 'February',
        ],
        3 => [
          'title' => 'March',
        ],
        4 => [
          'title' => 'April',
        ],
        5 => [
          'title' => 'May',
        ],
        6 => [
          'title' => 'June',
        ],
        7 => [
          'title' => 'July',
        ],
        8 => [
          'title' => 'August',
        ],
        9 => [
          'title' => 'September',
        ],
        10 => [
          'title' => 'October',
        ],
        11 => [
          'title' => 'November',
        ],
        12 => [
          'title' => 'December',
        ],
      ];
    }

    if ($this->timeframe == 'quarterly') {
      return [
        1 => [
          'title' => 'Q1',
          ],
        2 => [
          'title' => 'Q2',
        ],
        3 => [
          'title' => 'Q3',
        ],
        4 => [
          'title' => 'Q4',
        ],
      ];
    }

    return [];
  }

  /**
   *
   */
  private function processResultsSummary(BaseQuery $query) {

    $result = $query->execute();

    if ($this->theme == 'selected-activities') {
      $this->calculateOther($result);
    }

    $return = [
      'title' => $this->title,
      'aliasMap' => $query->getAliasMap(),
      'results' => [
        'all' => [
          'total' => $result[0]['TotalAll'],
        ],
        'me' => [],
        'provider' => [
          'total' => $result[0]['TotalProvider'],
        ],
      ],
    ];

    if (!empty($this->whatTitles)) {
      $return['what'] = (count($this->whatTitles) > 1) ? 'Selected Whats' : $this->whatTitles[0];
    }

    foreach (array_keys($return['aliasMap']) as $alias) {
      foreach (['all', 'me', 'provider'] as $scope) {
        $return['results'][$scope][$alias]['day'] = $this->calculateHrs($result[0], $alias, $scope, 'day');
        $return['results'][$scope][$alias]['week'] = $this->calculateHrs($result[0], $alias, $scope, 'week');

      }
    }

    return $return;
  }

  /**
   *
   */
  private function calculateOther(&$result) {
    $result[0]['OtherAll'] = $result[0]['TotalAll'] - $result[0]['SelectedAll'];
    $result[0]['OtherMe'] = $result[0]['TotalMe'] - $result[0]['SelectedMe'];
    $result[0]['OtherProvider'] = $result[0]['TotalProvider'] - $result[0]['SelectedProvider'];
  }

  /**
   * Calculate the number of hours for the given cell in the results table.
   *
   * @param array $results
   * @param string $alias
   * @param string $who
   * @param string $term
   */
  private function calculateHrs($results, $alias, $who, $term) {
    $totalCell = 'Total' . ucfirst($who);
    $dataCell = $alias . ucfirst($who);

    if ($results[$totalCell] == 0) {
      return '0:00';
    }

    $dayTotal = $results[$dataCell] / $results[$totalCell] * 8 / 24;
    return ($term == 'day') ?
      $this->formatDuration($dayTotal) :
      $this->formatDuration($dayTotal * 5);
  }

  /**
   *
   */
  private function formatDuration(float $time) {
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
   *
   */
  private function processResultsTrends($query) : array {

    $result = $query->execute();

    $return = [
      'title' => $this->title,
      'aliasMap' => $this->getTrendAliasMap(),
      'results' => [
        'all' => [],
        'me' => [],
        'provider' => [],
      ],
    ];

    $unit = ($this->timeframe == 'monthly') ? 'month' : 'quarter';
    foreach ($result as $record) {

      foreach (['all', 'me', 'provider'] as $scope) {
        $return['results'][$scope][$record[$unit]]['Selected']['day'] = $this->calculateHrs($record, 'Selected', $scope, 'day');
        $return['results'][$scope][$record[$unit]]['Selected']['week'] = $this->calculateHrs($record, 'Selected', $scope, 'week');
      }
    }
    return $return;
  }

  /**
   * Build query.
   */
  protected function buildQuery() {
    if (!$this->what && !$this->who && !$this->where) {
      $this->theme = 'what-summary';
      return $this->whatSummary();
    }
    elseif ($this->who == 'any' && !$this->where) {
      $this->theme = 'who-summary';
      return $this->whoSummary();
    }
    elseif ($this->where == 'any' && !$this->who) {
      $this->theme = 'where-summary';
      return $this->whereSummary();
    }
    elseif ($this->what) {
      $this->theme = 'selected-activities';
      return $this->selectedActivities();
    }
    else {
      return NULL;
    }
  }

  /**
   * Execute query using selected activities.
   */
  protected function selectedActivities() {
    $query = new What($this->email, $this->provider);
    $query->addSelectedWhatSums($this->what);
    if ($this->where && $this->where != 'any') {
      $query->addWhereCondition($this->where);
    }
    elseif ($this->who && $this->who != 'any') {
      $query->addWhoCondition($this->who);
    }

    $query->addCondition('answer' . $query::QUESTION_ID, 'NULL', '!=');

    return $query;
  }

  /**
   * Execute what summary query.
   */
  protected function whatSummary() {
    $query = new What($this->email, $this->provider);
    $query->addSums();
    if ($this->where && $this->where != 'any') {
      $query->addWhereCondition($this->where);
    }
    elseif ($this->who && $this->who != 'any') {
      $query->addWhoCondition($this->who);
    }

    $query->addCondition('answer' . $query::QUESTION_ID, 'NULL', '!=');
    return $query;
  }

  /**
   * Execute who summary query.
   */
  protected function whoSummary() {
    $query = new Who($this->email, $this->provider);
    $query->addSums();
    if ($this->where && $this->where != 'any') {
      $query->addWhereCondition($this->where);
    }
    elseif ($this->what && is_array($this->what)) {
      $query->addWhatCondition($this->what);
    }

    $query->addCondition('answer' . What::QUESTION_ID, 'NULL', '!=');
    return $query;
  }

  /**
   * Execute where summary query.,.
   */
  protected function whereSummary() {
    $query = new Where($this->email, $this->provider);
    $query->addSums();
    if ($this->what && $this->what != 'any') {
      $query->addWhatCondition($this->what);
    }
    elseif ($this->who && is_array($this->who)) {
      $query->addWhoCondition($this->who);
    }

    $query->addCondition('answer' . What::QUESTION_ID, 'NULL', '!=');
    return $query;
  }

}
