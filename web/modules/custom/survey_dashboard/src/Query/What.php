<?php
namespace Drupal\survey_dashboard\Query;

class What extends BaseQuery {

  const VID = 'what';
  const QUESTION_ID = 483;

  /**
   * @param $ids
   */
  public function addSelectedSums($ids) {
    $this->query->addExpression('count(*)', 'TotalAll');
    $this->addSelectedSumsAll($ids);
    $this->addSelectedSumsMe($ids);
    $this->addSelectedSumsProvider($ids);

    $this->addSelectedSumsAll($ids, 'NOT');
    $this->addSelectedSumsMe($ids, 'NOT');
    $this->addSelectedSumsProvider($ids, 'NOT');

    $sql = sprintf('sum(case when ((answer%d IS NOT NULL)  AND (email = :email)) then 1 else 0 end)',
      static::QUESTION_ID
    );

    $this->query->addExpression($sql, 'TotalMe', [
    ':email' => $this->email,
    ]);

    $sql = sprintf('sum(case when ((answer%d IS NOT NULL)  AND (provider = :provider)) then 1 else 0 end)',
      static::QUESTION_ID
    );

    $this->query->addExpression($sql, 'TotalProvider', [
      ':provider' => $this->provider,
    ]);

    $this->valueAliasMap = [
      'Selected' => [
        'title' => 'Selected Activities',
      ],
      'Other' => [
        'title' => 'Other Activities',
      ],
    ];
  }

  /**
   * @param $ids
   * @param null $not
   */
  private function addSelectedSumsAll($ids, $not = NULL) {
    $ind1 = $this->getValueIndex();
    $sql = sprintf('sum(case when answer%d %s IN (:value%d[])  then 1 else 0 end)',
      self::QUESTION_ID,
      $not,
      $ind1
    );

    $alias = (!$not) ? 'SelectedAll' : 'OtherAll';
    $this->query->addExpression($sql, $alias, [
      ':value' . $ind1 . '[]' => $ids,
    ]);
  }

  /**
   * @param $ids
   * @param null $not
   */
  private function addSelectedSumsMe($ids, $not = NULL) {
    $ind1 = $this->getValueIndex();
    $sql = sprintf('sum(case when answer%d %s IN (:value%d[]) AND email = :email then 1 else 0 end)',
      self::QUESTION_ID,
      $not,
      $ind1
    );

    $alias = (!$not) ? 'SelectedMe' : 'OtherMe';
    $this->query->addExpression($sql, $alias, [
      ':value' . $ind1 . '[]' => $ids,
      ':email' => $this->email,
    ]);
  }

  /**
   * @param $ids
   * @param null $not
   */
  private function addSelectedSumsProvider($ids, $not = NULL) {
    $ind1 = $this->getValueIndex();
    $sql = sprintf('sum(case when answer%d %s IN (:value%d[]) AND provider = :provider then 1 else 0 end)',
      self::QUESTION_ID,
      $not,
      $ind1
    );

    $alias = (!$not) ? 'SelectedProvider' : 'OtherProvider';
    $this->query->addExpression($sql, $alias, [
      ':value' . $ind1 . '[]' => $ids,
      ':provider' => $this->provider,
    ]);
  }
}
