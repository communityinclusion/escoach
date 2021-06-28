<?php

namespace Drupal\survey_dashboard\Query;

use Drupal\taxonomy\Entity\Term;

/**
 * Base Query class.
 */
class BaseQuery {
  const BASE_TABLE = 'surveycampaign_results';
  const VID = '';
  const QUESTION_ID = 0;

  /**
   * The query object.
   *
   * @var \Drupal\Core\Database\Query\SelectInterface
   */
  protected $query;

  /**
   * The email address for the current user.
   *
   * @var string
   */
  protected $email;

  /**
   * The provider name of the current user.
   *
   * @var string
   */
  protected $provider;

  /**
   * Array of aliases and field values for current dimension.
   *
   * @var array
   */
  protected $valueAliasMap;

  /**
   * The current placeholder index.
   *
   * @var int
   */
  private $valueIndex = 0;

  /**
   * Constructor.
   */
  public function __construct($email, $provider) {
    $this->email = $email;
    $this->provider = $provider;

    $this->initDimension();
    $database = \Drupal::database();
    $this->query = $database->select(self::BASE_TABLE, self::BASE_TABLE);

  }

  /**
   * Add all of the sums expresssions.
   */
  public function addSums() {
    $this->query->addExpression('count(*)', 'TotalAll');
    $this->addSumsAll();
    $this->addSumsMe();
    $this->addSumsProvider();
  }

  /**
   * Get the alias map for the current dimension.
   *
   * @return array
   */
  public function getAliasMap() {
    return $this->valueAliasMap;
  }

  /**
   * Get taxonomy terms for dimension.
   */
  public function initDimension() {

    $cid = 'survey_dashboard:aliasmap:' . static::VID;
    if ($cache = \Drupal::cache('data')->get($cid)) {
      $this->valueAliasMap = $cache->data;
      return;
    }

    $terms = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadTree(static::VID);
    foreach ($terms as $term) {
      if ($term->parents[0] == 0) {
        $termObj = Term::load($term->tid);
        if (!empty($termObj->field_alias->value)) {
          $response_id = $termObj->field_dashboard_response_id->getValue();
          if (is_array($response_id) && count($response_id) > 1) {
            $value = [];
            foreach ($response_id as $item) {
              $value[] = $item['value'];
            }
          }
          else {
            $value = $termObj->field_dashboard_response_id->value;
          }
          $this->valueAliasMap[$termObj->field_alias->value] = [
            'response_id' => $value,
            'title' => $term->name,
          ];
        }
      }
    }

    \Drupal::cache('data')->set($cid, $this->valueAliasMap);
  }

  /**
   * Keep a running count of the placeholder deltas.
   */
  protected function getValueIndex() {
    return $this->valueIndex++;
  }

  /**
   * Add the sums expressions for all users.
   */
  public function addSumsAll() {
    foreach ($this->valueAliasMap as $alias => $definition) {
      $value = $definition['response_id'];
      if (is_array($value)) {
        $ind1 = $this->getValueIndex();
        $ind2 = $this->getValueIndex();
        $sql = sprintf('sum(case when ((answer%d = :value%d) OR (answer%d = :value%d)) then 1 else 0 end)',
          static::QUESTION_ID[0],
          $ind1,
          static::QUESTION_ID[1],
          $ind2
        );

        $this->query->addExpression($sql, $alias . 'All', [
          ':value' . $ind1 => $value[0],
          ':value' . $ind2 => $value[1],
        ]);
      }
      else {
        $ind = $this->getValueIndex();
        $sql = sprintf('sum(case when answer%d IN (:value%d) then 1 else 0 end)', static::QUESTION_ID, $ind);
        $this->query->addExpression($sql, $alias . 'All', [
          ':value' . $ind => $value,
        ]);
      }
    }
  }

  /**
   * Add the sums expressions for the current user.
   */
  public function addSumsMe() {
    if (!$this->email) {
      return;
    }

    if (is_array(static::QUESTION_ID)) {
      $sql = sprintf("sum(case when (((answer%d IS NOT NULL) OR (answer%d IS NOT NULL)) AND email = :email) then 1 else 0 end)",
        static::QUESTION_ID[0],
        static::QUESTION_ID[1]
      );
    }
    else {
      $sql = sprintf('sum(case when ((answer%d IS NOT NULL)  AND (email = :email)) then 1 else 0 end)',
        static::QUESTION_ID
      );
    }

    $this->query->addExpression($sql, 'TotalMe', [
      ':email' => $this->email,
    ]);

    foreach ($this->valueAliasMap as $alias => $definition) {
      $value = $definition['response_id'];
      if (is_array($value)) {
        $ind1 = $this->getValueIndex();
        $ind2 = $this->getValueIndex();
        $sql = sprintf('sum(case when (((answer%d = :value%d) OR (answer%d = :value%d)) AND email = :email) then 1 else 0 end)',
          static::QUESTION_ID[0],
          $ind1,
          static::QUESTION_ID[1],
          $ind2
        );

        $this->query->addExpression($sql, $alias . 'Me', [
          ':value' . $ind1 => $value[0],
          ':value' . $ind2 => $value[1],
          ':email' => $this->email,
        ]);
      }
      else {
        $ind1 = $this->getValueIndex();
        $sql = sprintf('sum(case when answer%d IN (:value%d) AND email = :email then 1 else 0 end)',
          static::QUESTION_ID,
          $ind1
        );

        $this->query->addExpression($sql, $alias . 'Me', [
          ':value' . $ind1 => $value,
          ':email' => $this->email,
        ]);
      }
    }
  }

  /**
   * Add expressions for monthly trends queries.
   */
  public function addMonthlyParams() {
    $this->query->addExpression('MONTH(date_submitted)', 'month');
    $this->query->condition('date_submitted', 'DATE_SUB(NOW(), INTERVAL 1  YEAR)', '>=');
    $this->query->groupBy('MONTH(date_submitted)');
  }

  /**
   * Add expressions for quarterly trends queries.
   */
  public function addQuarterlyParams() {
    $this->query->addExpression('QUARTER(date_submitted)', 'quarter');
    $this->query->condition('date_submitted', 'DATE_SUB(NOW(), INTERVAL 1  YEAR)', '>=');
    $this->query->groupBy('QUARTER(date_submitted)');
  }

  /**
   * Add the sums expressions for provider of current user.
   */
  public function addSumsProvider() {
    if (!$this->provider) {
      return;
    }

    if (is_array(static::QUESTION_ID)) {
      $sql = sprintf("sum(case when (((answer%d IS NOT NULL) OR (answer%d IS NOT NULL)) AND provider = :provider) then 1 else 0 end)",
        static::QUESTION_ID[0],
        static::QUESTION_ID[1]
      );
    }
    else {
      $sql = sprintf('sum(case when ((answer%d IS NOT NULL)  AND (provider = :provider)) then 1 else 0 end)',
        static::QUESTION_ID
      );
    }

    $this->query->addExpression($sql, 'TotalProvider', [
      ':provider' => $this->provider,
    ]);

    foreach ($this->valueAliasMap as $alias => $definition) {
      $value = $definition['response_id'];
      if (is_array($value)) {
        $ind1 = $this->getValueIndex();
        $ind2 = $this->getValueIndex();
        $sql = sprintf('sum(case when (((answer%d = :value%d) OR (answer%d = :value%d)) AND provider = :provider) then 1 else 0 end)',
          static::QUESTION_ID[0],
          $ind1,
          static::QUESTION_ID[1],
          $ind2
        );
        $this->query->addExpression($sql, $alias . 'Provider', [
          ':value' . $ind1 => $value[0],
          ':value' . $ind2 => $value[1],
          ':provider' => $this->provider,
        ]);
      }
      else {
        $ind1 = $this->getValueIndex();
        $sql = sprintf('sum(case when answer%d IN (:value%d) AND provider = :provider then 1 else 0 end)',
          static::QUESTION_ID,
          $ind1
        );

        $this->query->addExpression($sql, $alias . 'Provider', [
          ':value' . $ind1 => $value,
          ':provider' => $this->provider,
        ]);
      }
    }
  }

  /**
   * Add a condition to the query.
   */
  public function addCondition($field, $value, $operator = '=') {
    if ($value === NULL) {
      if ($operator == '=') {
        $this->query->havingIsNull($field);
      }
      else {
        $this->query->havingIsNotNull($field);
      }
    }
    else {
      $this->query->condition($field, $value, $operator);
    }

  }

  /**
   * Add a "What" condition.
   */
  public function addWhatCondition(array $what) {
    if (count($what) > 1) {
      $qidMap = $this->flattenIds($what);
      if (count($qidMap) > 1) {
        $group = $this->query->orConditionGroup();
        foreach ($qidMap as $qid => $values) {
          $group->condition('answer' . $qid, $values, 'IN');
        }
        $this->query->condition($group);
      }
      else {
        $this->query->condition('answer' . key($qidMap), current($qidMap), 'IN');
      }
    }
    else {
      $this->query->condition('answer' . key($what), current($what), 'IN');
    }

  }

  /**
   * Add a "Who" condition.
   */
  public function addWhoCondition(array $values) {
    $group = $this->query->orConditionGroup();
    foreach ($values as $qid => $value) {
      $group->condition('answer' . $qid, $value, 'IN');
    }
    $this->query->condition($group);
  }

  /**
   * Add a "Where" condition.
   */
  public function addWhereCondition(array $value) {
    $this->query->condition('answer' . key($value), current($value), 'IN');
  }

  /**
   * Execute the query.
   */
  public function execute() {
    $results = $this->query->execute();
    $recordSet = [];
    foreach ($results as $result) {
      $recordSet[] = (array) $result;
    }

    return $recordSet;
  }

  protected function flattenIds($ids) {
    if (count($ids) == 1) {
      return $ids;
    }

    $return = [];
    foreach ($ids as $idx => $v) {
      $return[key($v)][] = current($v);
    }
    return $return;
  }

}
