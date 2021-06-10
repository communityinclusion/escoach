<?php

namespace Drupal\survey_dashboard\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountProxy;
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

    if (is_array($tid)) {
      $return = [];
      $terms = $this->entityTypeManager->getStorage('taxonomy_term')->loadMultiple($tid);
      foreach ($terms as $term) {
        $return[] = $term->field_dashboard_response_id->value;
      }
      return $return;
    }
    else {
      $term = $this->entityTypeManager->getStorage('taxonomy_term')->load($tid);
    }

    if (!$term) {
      return NULL;
    }
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

    $profiles = $this->entityTypeManager->getStorage('profile')
      ->loadByProperties([
        'uid' => $this->currentUser->id(),
        'type' => 'survey_participants',
      ]);

    if ( $profiles) {
      $profile = current($profiles);
    }

    $this->timeframe = $params['timeframe'];
    $this->dataframe = $params['dataframe'];

    $this->who = ( $params['who'] == 'any' ) ? 'any' : $this->getTaxonomyValue('who', $params['who']);
    $this->what = $this->getTaxonomyValue('what', $params['what']);
    $this->where = ( $params['where'] == 'any' ) ? 'any' : $this->getTaxonomyValue('where', $params['where']);
    $this->email = $this->currentUser->getEmail();

    if ( $profile && $profile->field_provider->entity ) {
      $this->provider = $profile->field_provider->entity->getName();
    }

    /** @var \Drupal\survey_dashboard\Query\BaseQuery $query */
    $query = $this->buildQuery();

    switch ($this->timeframe) {
      case 'quarterly':
        $query->addQuarterlyParams();
        break;

      case 'monthly':
        $query->addMonthlyParams();
        break;
    }

    $result = $query->execute();
    return $result;
  }

  /**
   * Build query.
   */
  protected function buildQuery() {
    if (!$this->what) {
      return $this->whatSummary();
    }
    elseif ($this->who == 'any') {
      return $this->whoSummary();
    }
    elseif ($this->where == 'any') {
      return $this->whereSummary();
    }
    else {
      return $this->selectedActivities();
    }
  }

  /**
   * Execute query using selected activities.
   */
  protected function selectedActivities() {
    $query = new What($this->email, $this->provider);
    $query->addWhatCondition($this->what);
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
    if ($this->where && $this->where != 'any') {
      $query->addWhereCondition($this->where);
    }
    elseif ($this->what && $this->what != 'any') {
      $query->addWhatCondition($this->what);
    }
    return $query;
  }

  /**
   * Execute where summary query.,.
   */
  protected function whereSummary() {
    $query = new Where($this->email, $this->provider);
    if ($this->what && $this->what != 'any') {
      $query->addWhatCondition($this->where);
    }
    elseif ($this->who && $this->who != 'any') {
      $query->addWhoCondition($this->who);
    }
    return $query;
  }

}
