<?php
namespace Drupal\survey_dashboard\Query;

use Drupal\taxonomy\Entity\Term;

class BaseQuery {
  const BASE_TABLE = 'surveycampaign_results';
  const VID = '';
  const QUESTION_ID = 0;

  private $query;
  private $email;
  private $provider;
  protected $valueAliasMap;
  private $valueIndex = 0;

  public function __construct($email, $provider) {
    $this->email = $email;
    $this->provider = $provider;

    $this->initDimension();
    $database = \Drupal::database();
    $this->query = $database->select(self::BASE_TABLE, self::BASE_TABLE);
    $this->addSums();
  }

  public function addSums() {
      $this->query->addExpression('count(*)', 'TotalAll');
      $this->addSumsAll();
      $this->addSumsMe();
      $this->addSumsProvider();
  }

  public function initDimension() {

    $cid = 'survey_dashboard:aliasmap:' . static::VID;
    if ( $cache = \Drupal::cache('data')->get($cid)) {
      $this->valueAliasMap = $cache->data;
      return;
    }

    $terms = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadTree(static::VID);
    foreach ($terms as $term) {
      if ($term->parents[0] == 0) {
        $termObj = Term::load($term->tid);
        if ( !empty($termObj->field_alias->value)) {
          $response_id = $termObj->field_dashboard_response_id->getValue();
          if (is_array($response_id)) {
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

  private function getValueIndex() {
    return $this->valueIndex++;
  }

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

        $this->query->addExpression( $sql, $alias . 'All', [
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

  public function addSumsMe() {
    if ( !$this->email) {
      return;
    }
    foreach ($this->valueAliasMap as $alias => $definition) {
      $value = $definition['response_id'];
      if (is_array($value)) {
        $ind1 = $this->getValueIndex();
        $ind2 = $this->getValueIndex();
        $sql = sprintf('sum(case when ((answer%d = :value%d) OR (answer%d = :value%d) AND email = :email) then 1 else 0 end)',
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

  public function addMonthlyParams() {
    $this->query->addExpression('MONTH(date_submitted)', 'month');
    $this->query->condition('date_submitted', 'DATE_SUB(NOW(), INTERVAL 1  YEAR)', '>=');
    $this->query->groupBy('MONTH(date_submitted)');
  }

  public function addQuarterlyParams() {
    $this->query->addExpression('QUARTER(date_submitted)', 'quarter');
    $this->query->condition('date_submitted', 'DATE_SUB(NOW(), INTERVAL 1  YEAR)', '>=');
    $this->query->groupBy('QUARTER(date_submitted)');
  }

  public function addSumsProvider() {
    if (! $this->provider ) {
      return;
    }

    foreach ($this->valueAliasMap as $alias => $definition) {
      $value = $definition['response_id'];
      if (is_array($value)) {
        $ind1 = $this->getValueIndex();
        $ind2 = $this->getValueIndex();
        $sql = sprintf('sum(case when ((answer%d = :value%d) OR (answer%d = :value%d) AND provider = :provider) then 1 else 0 end)',
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

  public function addWhatCondition($value) {
    $this->query->condition('answer' . What::QUESTION_ID, $value, 'IN');
  }

  public function addWhoCondition($values) {
    $group = $this->query->orConditionGroup()
      ->condition('answer' . Who::QUESTION_ID[0], $values[0], 'IN')
      ->condition('answer' . Who::QUESTION_ID[1], $values[1], 'IN');

    $this->query->condition($group);
  }

  public function addWhereCondition($value) {
    $this->query->condition('answer' . Where::QUESTION_ID, $value, 'IN');
  }

  public function execute() {
    $results = $this->query->execute();
    $recordSet = [];
    foreach ($results as $result) {
      $recordSet[] = (array)$result;
    }

    return json_encode($recordSet);
  }

}
