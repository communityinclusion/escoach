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
    if (!$tid ) {
      return NULL;
    }

    $titleProp = $vid . 'Titles';
    if (is_array($tid)) {
      $return = [];
      $titles = [];
      $terms = $this->entityTypeManager->getStorage('taxonomy_term')->loadMultiple($tid);
      foreach ($terms as $term) {
        $titles[] = $term->label();
        $return[] = $term->field_dashboard_response_id->value;
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

    $this->$titleProp = [ $term->label() ];

    if ($vid != 'who') {
      return $term->field_dashboard_response_id->value;
    }
    else {
      return [
        $term->field_dashboard_response_id[0]->value,
        $term->field_dashboard_response_id[1]->value,
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

    $this->who = ( $params['who'] == 'any' ) ? 'any' : $this->getTaxonomyValue('who', $params['who']);
    $this->what = $this->getTaxonomyValue('what', $params['what']);
    $this->where = ( $params['where'] == 'any' ) ? 'any' : $this->getTaxonomyValue('where', $params['where']);
    $this->email = $this->currentUser->getEmail();

    /** @var \Drupal\survey_dashboard\Query\BaseQuery $query */
    $query = $this->buildQuery();

    switch ($this->timeframe) {
      case 'quarterly':
        $this->theme = 'quarterly-trends';
        $query->addQuarterlyParams();
        $trends = TRUE;
        break;

      case 'monthly':
        $this->theme = 'monthly-trends';
        $query->addMonthlyParams();
        $trends = TRUE;
        break;

      default:
        $trends = FALSE;
    }

    return [
      '#theme' => $this->theme,
      '#data' => ($trends) ? $this->processResultsTrends($query) : $this->processResultsSummary($query),
    ];
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

    if ( $profiles) {
      $profile = current($profiles);
    }
    if ( $profile && $profile->field_provider->entity ) {
      $this->provider = $profile->field_provider->entity->getName();
    }

  }
  private function processResultsSummary(BaseQuery $query) {

    $result = $query->execute();

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
      ]
    ];

    if (!empty($this->whatTitles)) {
      $return['what'] = (count($this->whatTitles) > 1) ? 'Selected Whats' : $this->whatTitles[0];
    }

    foreach (array_keys($return['aliasMap']) as $alias ) {
      foreach (['all', 'me', 'provider'] as $scope) {
        $return['results'][$scope][$alias]['day'] = $this->calculateHrs($result[0], $alias, $scope,'day');
        $return['results'][$scope][$alias]['week'] = $this->calculateHrs($result[0], $alias, $scope, 'week');

      }
    }

    return  $return;
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

    if ($results[$totalCell] == 0 ) {
      return '0:00';
    }

    $dayTotal = $results[$dataCell] / $results[$totalCell] * 8 / 24;
    return ($term == 'day') ?
      $this->formatDuration($dayTotal)  :
      $this->formatDuration($dayTotal * 5);
  }

  private function formatDuration(float $time) {
    $total_hours = $time * 24;
    $hour_part = floor($total_hours);
    $min_part = round(($total_hours - $hour_part) * 60);
    return sprintf("%d:%02d", $hour_part, $min_part);
  }
  private function processResultsTrends($results) : array {
    $return = [];

    return  $return;
  }
  /**
   * Build query.
   */
  protected function buildQuery() {
    if (!$this->what) {
      $this->theme = 'what-summary';
      return $this->whatSummary();
    }
    elseif ($this->who == 'any') {
      $this->theme = 'who-summary';
      return $this->whoSummary();
    }
    elseif ($this->where == 'any') {
      $this->theme = 'where-summary';
      return $this->whereSummary();
    }
    else {
      $this->theme = 'selected-activities';
      return $this->selectedActivities();
    }
  }

  /**
   * Execute query using selected activities.
   */
  protected function selectedActivities() {
    $query = new What($this->email, $this->provider);
    $query->addSelectedSums($this->what);
    if ($this->where && $this->where != 'any') {
      $query->addWhereCondition($this->where);
    }
    elseif ($this->who && $this->who != 'any') {
      $query->addWhoCondition($this->who);
    }

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
    elseif ($this->what && $this->what != 'any') {
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
    elseif ($this->who && $this->who != 'any') {
      $query->addWhoCondition($this->who);
    }

    $query->addCondition('answer' . What::QUESTION_ID, 'NULL', '!=');
    return $query;
  }

}
