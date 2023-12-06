<?php
namespace Drupal\es_homepage\Query;

use Drupal\survey_dashboard\Query\What;

/**
 * Best Practice Queries
 *  In the Community (answer483 IN 11658, 11659, 11661, answer482 = 11652) - + residence = 11650, + business = 11651
 *  With Families    (answer483 IN 11658, 11659, 11661, [answer481 = 11641 OR answer590 = 12119])
 *  Observing (answer537 IN 11838, 11899, 12115 )
 *  Networking (answer538 IN 11845, 11844, 11843 )
 *  Natural supports (answer540 IN 11863, 11862 )
 */

class bestPracticesQuery extends HomePageQuery {
  const QUESTION_ID = 483;
  const PRACTICES = [
    'inTheCommunity' => [
      'label' => 'In the Community',
      'multiplier' => 1,
      'answerIDs' => [
        483 => [
          11658,
          11659,
          11661
        ],
        482 => [
          11650,
          11651,
          11652,
        ]
      ]
    ],
    'withFamilies' => [
      'label' => 'With Families',
      'multiplier' => 1,
      'answerIDs' => [
        483 => [
          11658,
          11659,
          11661
        ],
        'OR' => [
          481 => 11641,
          590 => 12119
        ]
      ]
    ],
    'observing' => [
      'label' => 'Observing',
      'multiplier' => 1,
      'answerIDs' => [
        537 => [
          11838,
          11899,
          12115
        ]
      ]
    ],
    'networking' => [
      'label' => 'Networking',
      'multiplier' => 1,
      'answerIDs' => [
        538 => [
          11845,
          11844,
          11843
        ]
      ]
    ],
    'naturalSupports' => [
      'label' => 'Natural Supports',
      'multiplier' => 1,
      'answerIDs' => [
        540 => [
          11863,
          11862
        ]
      ]
    ]
  ];

  public function __construct($year, $month, $email, $provider) {
    parent::__construct($year, $month, $email, $provider);
    $this->query->condition('answer' . WHAT::QUESTION_ID, null, 'IS NOT');
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

      case 'Observer':
        $and = "AND (regcode <= 10000 OR regcode IS NULL)";
        break;

      default:
        $and = '';
    }

    foreach ($this::PRACTICES as $category => $info) {
      $value = $info['answerIDs'];
      $args = $commonArgs;
      $conditions = [];
      $alias = $category . $scope;
      $when = [];

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

          $when[] = sprintf('(%s)', implode(' OR ', $conditions) );
        }
        else {
          if (is_array($values)) {
            $ind = $this->getValueIndex();
            $when[] = sprintf('(answer%d IN (:value%d[]))', $qid, $ind);
            $args[':value' . $ind . '[]'] = $values;

          }
          else {
            $ind1 = $this->getValueIndex();
            $when[] = sprintf('answer%d IN (:value%d)',
              $qid,
              $ind1,
            );

            $args[':value' . $ind1] = $values;
          }
        }
      }

      $sql = sprintf('sum(case when (%s) %s then 1 else 0 end)', implode(' AND ', $when), $and);
      $this->query->addExpression($sql, $alias, $args);
    }
  }
}
