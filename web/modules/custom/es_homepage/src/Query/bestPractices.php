<?php
namespace Drupal\es_homepage\Query;

/**
 * Best Practice Queries
 *  In the Community (answer483 IN 11658, 11659, 11661, answer482 = 11652) - + residence = 11650, + business = 11651
 *  With Families    (answer483 IN 11658, 11659, 11661, [answer481 = 11641 OR answer590 = 12119])
 *  Observing (answer537 IN 11838, 11899, 12115 )
 *  Networking (answer538 IN 11845, 11844, 11843 )
 *  Natural supports (answer540 IN 11863, 11862 )
 */

class bestPractices extends HomePageQuery {
  const QUESTION_ID = 483;
  const PRACTICES = [
    'inTheCommunity' => [
      483 => [
        11658,
        11659,
        11661
      ],
      482 => 11652
    ],
    'withFamilies' => [
      483 => [
        11658,
        11659,
        11661
      ],
      'OR' => [
        481 => 11641,
        590 => 12119
      ]
    ],
    'observing' => [
      537 => [
        11838,
        11899,
        12115
      ]
    ],
    'networking' => [
      538 => [
        11845,
        11844,
        11843
      ]
    ],
    'naturalSupports' => [
      540 => [
        11863,
        11862
      ]
    ]
  ];

  public function __construct($year, $month, $email, $provider) {
    parent::__construct($year, $month, $email, $provider);
  }

  public function buildSums($scope, $state = NULL) {
    $commonArgs = [];

    switch ($scope) {
      case 'Me':
        $and = 'AND email = :email';
        $commonArgs[':email'] = $this->email;
        break;

      case 'Provider':
        $and = 'AND provider = :provider';
        $commonArgs[':provider'] = $this->provider;
        break;

      case 'State':
        $and = 'AND state = :state';
        $commonArgs[':state'] = $state;
        break;

      default:
        $and = '';
    }

    foreach ($this::PRACTICES as $category => $value) {
      $args = $commonArgs;
      $conditions = [];
      $alias = $category . $scope;

      foreach ($value as $qid => $values) {
        if ($qid == 'OR') {
          $idx = 0;
          foreach ($values as $qid => $respIDs) {
            $conditions[] = sprintf( 'answer%d IN (:who%s%d[])', $qid, $scope, $idx);
            if (!is_array($respIDs)) {
              $respIDs = [$respIDs];
            }
            $args[ ':who' . $scope . $idx++ . '[]'] = $respIDs;
          }

          $and .= sprintf(' AND (%s)', implode(' OR ', $conditions) );
        }
        else {
          if (is_array($values)) {
            $ind = $this->getValueIndex();
            $str_condition = sprintf('(answer%d IN (:value%d[]))', $qid, $ind);
            $args[':value' . $ind . '[]'] = $values;

            $sql = sprintf('sum(case when ((%s) %s) then 1 else 0 end)',
              $str_condition,
              $and
            );

            $this->query->addExpression($sql, $alias, $args);
          }
          else {
            $ind1 = $this->getValueIndex();
            $sql = sprintf('sum(case when answer%d IN (:value%d) %s then 1 else 0 end)',
              $qid,
              $ind1,
              $and
            );

            $args[':value' . $ind1] = $values;
          }
        }
      }
      $this->query->addExpression($sql, $alias, $args);
    }
  }
}
