<?php
namespace Drupal\survey_dashboard\Query;

class What extends BaseQuery {

  const VID = 'what';
  const QUESTION_ID = 483;

  /**
   * @param $ids
   */
  public function addSelectedSums($ids) {
    $qidMap = $this->flattenIds($ids);
    $this->query->addExpression('count(*)', 'TotalAll');
    $this->addSelectedSumsAll($qidMap);
    $this->addSelectedSumsMe($qidMap);
    $this->addSelectedSumsProvider($qidMap);

    $this->addSelectedSumsAll($qidMap, 'NOT');
    $this->addSelectedSumsMe($qidMap, 'NOT');
    $this->addSelectedSumsProvider($qidMap, 'NOT');

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

  private function buildExpression($ids, $not = NULL, $email = NULL, $provider = NULL) {

    if ($not) {
      $alias = 'Other';
      $logical_op = ' AND ';
    }
    else {
      $alias = 'Selected';
      $logical_op = ' OR ';
    }

    $conditions = [];
    foreach ($ids as $qid => $resp_ids) {
      $ind = $this->getValueIndex();
      $placeholders[ ':value' . $ind . '[]' ] = $resp_ids;
      $str = '(answer%d %s IN (:value%d[]))';
      $conditions[] = sprintf( $str, $qid, $not, $ind);
    }

    $scope = 'All';

    $sql = 'sum(case when ((' . implode($logical_op, $conditions) . ')';
    if ($email) {
      $scope = 'Me';
      $placeholders[':email'] = $email;
      $sql .= ' AND (email = :email)';
    }
    elseif ($provider) {
      $scope = 'Provider';
      $placeholders[':provider'] = $provider;
      $sql .= ' AND (provider = :provider)';
    }

    $sql .= ') then 1 else 0 end)';

    $this->query->addExpression($sql, $alias . $scope, $placeholders);
  }

  /**
   * @param $ids
   * @param null $not
   */
  private function addSelectedSumsAll($ids, $not = NULL) {
    $this->buildExpression($ids,$not);
    return;

    $ind1 = $this->getValueIndex();
    $sql = sprintf('sum(case when answer%d %s IN (:value%d[])  then 1 else 0 end)',
      key($ids),
      $not,
      $ind1
    );

    $alias = (!$not) ? 'SelectedAll' : 'OtherAll';
    $this->query->addExpression($sql, $alias, [
      ':value' . $ind1 . '[]' => current($ids),
    ]);
  }

  /**
   * @param $ids
   * @param null $not
   */
  private function addSelectedSumsMe($ids, $not = NULL) {
    $this->buildExpression($ids,$not,$this->email);
    return;
    $ind1 = $this->getValueIndex();
    $sql = sprintf('sum(case when answer%d %s IN (:value%d[]) AND email = :email then 1 else 0 end)',
      key($ids),
      $not,
      $ind1
    );

    $alias = (!$not) ? 'SelectedMe' : 'OtherMe';
    $this->query->addExpression($sql, $alias, [
      ':value' . $ind1 . '[]' => current($ids),
      ':email' => $this->email,
    ]);
  }

  /**
   * @param $ids
   * @param null $not
   */
  private function addSelectedSumsProvider($ids, $not = NULL) {
    $this->buildExpression($ids,$not,NULL, $this->provider);
    return;

    $ind1 = $this->getValueIndex();
    $sql = sprintf('sum(case when answer%d %s IN (:value%d[]) AND provider = :provider then 1 else 0 end)',
      key($ids),
      $not,
      $ind1
    );

    $alias = (!$not) ? 'SelectedProvider' : 'OtherProvider';
    $this->query->addExpression($sql, $alias, [
      ':value' . $ind1 . '[]' => current($ids),
      ':provider' => $this->provider,
    ]);
  }

  private function flattenIds($ids) {
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
