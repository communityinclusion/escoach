<?php

namespace Drupal\survey_dashboard\Query;

use Drupal\taxonomy\Entity\Term;
use function GuzzleHttp\Psr7\str;

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
    $this->addSumsTotal('Me');
    $this->addSumsTotal('Provider');
    $this->addSumsByScope('Me');
    $this->addSumsByScope('Provider');
    $this->addSumsByScope('All');
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
          $question_id = $termObj->field_dashboard_question_id->getValue();

          if (is_array($response_id) && count($response_id) > 1) {
            $value = [];
            $qid = [];
            foreach ($response_id as $item) {
              $value[] = $item['value'];
            }
            foreach ($question_id as $item) {
              $qid[] = $item['value'];
            }
          }
          else {
            $value = $termObj->field_dashboard_response_id->value;
            $qid = $termObj->field_dashboard_question_id->value;
          }
          $this->valueAliasMap[$termObj->field_alias->value] = [
            'question_id' => $qid,
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

  public function toString() {
    return (string)$this->query;
  }

  public function getArguments() {
    $expressions = $this->query->getExpressions();
    $args = [];
    foreach ($expressions as $alias => $expression) {
      foreach ($expression['arguments'] as $placeholder => $value) {
        $args[$placeholder] = $value;
      }
    }

    $args += $this->query->arguments();

    return print_r($args, TRUE);
  }

  public function addSumsByScope($scope) {

    switch ($scope) {
      case 'Me':
        $and = 'AND email = :email';
        $args[':email'] = $this->email;
        break;

      case 'Provider':
        $and = 'AND provider = :provider';
        $args[':provider'] = $this->provider;
        break;

      default:
        $and = '';
    }

    foreach ($this->valueAliasMap as $alias => $definition) {
      $value = $definition['response_id'];
      $qid = $definition['question_id'];

      if (is_array($value)) {
        $args = [];
        $qids = [];
        foreach ($qid as $idx => $id) {
          $qids[$id][] = $value[$idx];
        }

        $conditions = [];
        foreach (array_keys($qids) as  $qid) {
          $ind = $this->getValueIndex();
          $conditions[] = sprintf('(answer%d IN (:value%d[]))', $qid, $ind);
          $args[':value' . $ind . '[]'] = $qids[$qid];
        }

        $str_condition = implode(' OR ', $conditions);
        $sql = sprintf('sum(case when ((%s) %s) then 1 else 0 end)',
          $str_condition,
          $and
        );

        $this->query->addExpression($sql, $alias . $scope, $args);
      }
      else {
        $ind1 = $this->getValueIndex();
        $sql = sprintf('sum(case when answer%d IN (:value%d) %s then 1 else 0 end)',
          static::QUESTION_ID,
          $ind1,
          $and
        );

        $args[':value' . $ind1] = $value;

        $this->query->addExpression($sql, $alias . $scope, $args);
      }
    }
  }

  public function addSumsTotal($scope) {

    $args = [];
    if ($scope == 'Me' && !empty($this->email)) {
      $and = 'email = :email';
      $args[':email'] = $this->email;
    }
    elseif ($scope == 'Provider' && !empty($this->provider)) {
      $and = 'provider = :provider';
      $args[':provider'] = $this->provider;
    }
    else {
      return;
    }

    if (is_array(static::QUESTION_ID)) {
      $sql = sprintf("sum(case when (((answer%d IS NOT NULL) OR (answer%d IS NOT NULL)) AND %s) then 1 else 0 end)",
        static::QUESTION_ID[0],
        static::QUESTION_ID[1],
        $and
      );
    }
    else {
      $sql = sprintf('sum(case when ((answer%d IS NOT NULL)  AND (%s)) then 1 else 0 end)',
        static::QUESTION_ID,
        $and
      );
    }

    $this->query->addExpression($sql, 'Total' . $scope, $args);
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
   * Add a condition to the query.
   */
  public function addCondition($field, $value, $operator = '=') {
    if ($value === NULL) {
      if ($operator == '=') {
        $this->query->condition($field, $value, "IS NULL");
      }
      else {
        $this->query->condition($field, $value, "IS NOT NULL");
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
    foreach ($values as $idx => $value) {
      $qid = key($value);
      $rids = $value[$qid];
      $group->condition('answer' . $qid . '[]', $rids, 'IN');
    }
    $this->query->condition($group);
  }

  /**
   * Add a "Where" condition.
   */
  public function addWhereCondition(array $value) {
    $this->query->condition('answer' . key($value[0]) . '[]', current($value[0]), 'IN');
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

  public function flattenIds($ids) {
    if (count($ids) == 1) {
      return $ids;
    }

    $return = [];
    foreach ($ids as $idx => $v) {
      $return[key($v)][] = current($v);
    }
    return $return;
  }

  /**
   * Add sum expressions for "What" queries.
   *
   * @param array $ids
   *   Ids of "who/where" items selected by user.
   *
   * @param array$what
   *   Ids of what items selected by user
   */
  public function buildSelectedSums(array $ids, array $what) {
    $this->query->addExpression('count(*)', 'TotalAll');

    $this->addSelectedSums('All', $ids, $what);
    $this->addSelectedSums('Me', $ids, $what);
    $this->addSelectedSums('Provider', $ids, $what);

    $this->addSelectedSumsTotal('Me');
    $this->addSelectedSumsTotal('Provider');

    $this->valueAliasMap = [
      'Selected' => [
        'title' => 'Selected Activities',
      ],
      'Other' => [
        'title' => 'Other Activities',
      ],
    ];
  }

  public function addNSums($what) {
    $this->addNSum('All', $what);
    $this->addNSum('Provider', $what);
    $this->addNSum('Me', $what);
  }

  private function addNSum($scope, $what) {
    $args = [];
    $and = '';
    if ($scope == 'Me') {
      $and = ' AND email = :email';
      $args[':email'] = $this->email;
    }
    elseif ($scope == 'Provider') {
      $and = ' AND provider = :provider';
      $args[':provider'] = $this->provider;
    }

    $ids = $this->flattenIds($what);

    $conditions = [];
    foreach ($ids as $qid => $values) {
      $ind = $this->getValueIndex();
      $conditions[] = sprintf( '(answer%d in (:value%d[]) )', $qid, $ind );
      if (!is_array($values)) {
        $values = [$values];
      }
      $args[':value' . $ind . '[]'] = $values;
    }

    $sql = sprintf('sum(case when (%s) %s  then 1 else 0 end)', implode(' OR ', $conditions), $and);
    $this->query->addExpression($sql, 'n' . $scope, $args);
  }

  /**
   * Add total sums for me and provider.
   */
  private function addSelectedSumsTotal($scope) {
    $args = [];
    if ($scope == 'Me') {
      $and = 'email = :email';
      $args[':email'] = $this->email;
    }
    elseif ($scope == 'Provider') {
      $and = 'provider = :provider';
      $args[':provider'] = $this->provider;
    }
    else {
      return;
    }

    $sql = sprintf('sum(case when (%s) then 1 else 0 end)',
      $and
    );

    $this->query->addExpression($sql, 'Total' . $scope, $args);
  }

  /**
   * Build sum expression.
   *
   * @param string $scope
   *   Which grouping - All, Me, or Provider.
   * @param array $ids
   *   "What" items selected by user.
   */
  private function addSelectedSums(string $scope, array $ids, array $what) {
    $args = [];

    switch ($scope) {
      case 'Me':
        $and = 'AND email = :email';
        $args[':email'] = $this->email;
        break;

      case 'Provider':
        $and = 'AND provider = :provider';
        $args[':provider'] = $this->provider;
        break;

      default:
        $and = '';
    }

    if ($what) {
      $whatIds = $this->flattenIds($what);
      $conditions = [];
      $idx = 0;
      foreach ($whatIds as $qid => $respIDs) {
        $conditions[] = sprintf( 'answer%d IN (:what%s%d)', $qid, $scope, $idx);
        if (!is_array($respIDs)) {
          $respIDs = [$respIDs];
        }
        $args[ ':what' . $scope . $idx++] = implode(',', $respIDs);
      }
      $and .= sprintf(' AND (%s)', implode(' OR ', $conditions) );
    }

    $conditions = [];
    foreach ($ids as $qid => $values) {
      $ind = $this->getValueIndex();
      $conditions[] = sprintf( '(answer%d in (:value%d[]) )', $qid, $ind );
      if (!is_array($values)) {
        $values = [$values];
      }
      $args[':value' . $ind . '[]'] = $values;
    }

    $str_condition = implode(' OR ', $conditions);
    $sql = sprintf('sum(case when (%s) %s then 1 else 0 end)',
      $str_condition,
      $and
    );

    $this->query->addExpression($sql, 'Selected' . $scope, $args);
  }

}
