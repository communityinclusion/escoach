<?php
namespace Drupal\survey_dashboard\Query;

class BaseQuery {
  const BASE_TABLE = 'surveycampaign_results';
  const QUESTION_ID = 0;

  private $query;
  private $email;
  private $provider;
  protected $valueAliasMap;
  private $valueIndex = 0;

  public function __construct($email, $provider) {
    $this->email = $email;
    $this->provider = $provider;

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

  private function getValueIndex() {
    return $this->valueIndex++;
  }

  public function addSumsAll() {
    foreach ($this->valueAliasMap as $alias => $value) {
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
    foreach ($this->valueAliasMap as $alias => $value) {
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

  public function addSumsProvider() {
    foreach ($this->valueAliasMap as $alias => $value) {
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
