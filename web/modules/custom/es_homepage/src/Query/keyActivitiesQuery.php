<?php
namespace Drupal\es_homepage\Query;

/**
 * Key Activity Queries
 *  Leading to Hire (answer483 IN 11658, 11659, 11661)
 *  After Hire (answer483 = 11660)
 *  Admin (answer483 = 11663)
 *  Non-Employment (answer483 = 11662)
 */


class keyActivitiesQuery extends HomePageQuery {
  const QUESTION_ID = 483;

  const ACTIVITIES = [
    'LeadingToHire' => [
      'label' => 'Leading to hire',
      'answerIDs' => [11658, 11659, 11661],
      'multiplier' => 1,
    ],
    'AfterHire' => [
      'label' => 'AFTER hire',
      'answerIDs' => 11660,
      'multiplier' => 0,
    ],
    'Administrative' => [
      'label' => 'Administrative',
      'answerIDs' => 11663,
      'multiplier' => -1,
    ],
    'NonEmployment' => [
      'label' => 'Non-employment',
      'answerIDs' => 11662,
      'multiplier' => -1,
    ],
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

      case 'Observer':
        $and = "AND (regcode <= 10000 OR regcode IS NULL)";
        break;


      default:
        $and = '';
    }

    foreach ($this::ACTIVITIES as $category => $info) {
      $value = $info['answerIDs'];
      $args = $commonArgs;
      $conditions = [];
      $alias = $category . $scope;

      if (is_array($value)) {
        $ind = $this->getValueIndex();
        $str_condition = sprintf('(answer%d IN (:value%d[]))', $this::QUESTION_ID, $ind);
        $args[':value' . $ind . '[]'] = $value;

        $sql = sprintf('sum(case when ((%s) %s) then 1 else 0 end)',
          $str_condition,
          $and
        );

        $this->query->addExpression($sql, $alias, $args);
      }
      else {
        $ind1 = $this->getValueIndex();
        $sql = sprintf('sum(case when answer%d = (:value%d) %s then 1 else 0 end)',
          $this::QUESTION_ID,
          $ind1,
          $and
        );

        $args[':value' . $ind1] = $value;

        $this->query->addExpression($sql, $alias, $args);
      }
    }
  }
}
