<?php

namespace Drupal\survey_dashboard\Query;

/**
 * Class for building "What" queries.
 */
class What extends BaseQuery {

  const VID = 'what';
  const QUESTION_ID = 483;

  /**
   * Add sum expressions for "What" queries.
   *
   * @param array $ids
   *   Ids of "what" items selected by user.
   */
  public function addSelectedWhatSums(array $ids) {
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

    $sql = sprintf('sum(case when ((answer%d IS NOT NULL)  AND (%s)) then 1 else 0 end)',
      static::QUESTION_ID,
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
  private function addSelectedSums(string $scope, array $ids) {
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

    $sql = sprintf('sum(case when answer%d IN (:value%d[]) %s then 1 else 0 end)',
      key($ids),
      $ind1,
      $and
    );

    $values = current($ids);
    if (!is_array($values)) {
      $values = [$values];
    }

    $args[':value' . $ind1 . '[]'] = $values;
    $this->query->addExpression($sql, 'Selected' . $scope, $args);
  }

}
