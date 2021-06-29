<?php
namespace Drupal\survey_dashboard\Query;

class What extends BaseQuery {

  const VID = 'what';
  const QUESTION_ID = 483;

  /**
   * @param $ids
   */
  public function addSelectedWhatSums($ids) {
    $qidMap = $this->flattenIds($ids);
    $this->query->addExpression('count(*)', 'TotalAll');

    $this->addSelectedSums('All', $qidMap);
    $this->addSelectedSums('Me', $qidMap);
    $this->addSelectedSums('Provider', $qidMap);

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

    $sql = sprintf('sum(case when ((answer%d IS NOT NULL)  AND (%s)) then 1 else 0 end)',
      static::QUESTION_ID,
      $and
    );

    $this->query->addExpression($sql, 'Total' . $scope, $args);
  }

  /**
   * @param $ids
   * @param null $not
   */
  private function addSelectedSums($scope, $ids, $not = NULL) {
    $ind1 = $this->getValueIndex();
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

    $sql = sprintf('sum(case when answer%d %s IN (:value%d[]) %s then 1 else 0 end)',
      key($ids),
      $not,
      $ind1,
      $and
    );

    $values = current($ids);
    if (!is_array($values)) {
      $values = [$values];
    }

    $args[':value' . $ind1 . '[]'] = $values;

    $alias = (!$not) ? 'Selected' : 'Other';
    $this->query->addExpression($sql, $alias . $scope, $args);
  }

}
