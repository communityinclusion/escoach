<?php
namespace Drupal\es_homepage\Query;

use Drupal\survey_dashboard\Query\BaseQuery;

class HomePageQuery extends BaseQuery {

  public function __construct($year, $month, $email, $provider) {
    // BaseQuery is centered around email and provider.  Not sure if that is needed here or not.
    parent::__construct($email, $provider);
    $this->setDateRange($year, $month);
  }

  public function setDateRange($year, $month) {

    $this->query->addExpression('MONTH(date_submitted)', 'month');

    $fr_date = new \DateTime("$year-$month-01");
    $date = new \DateTime("$year-$month-01");
    $to_date = $date->modify('last day of this month');

    $this->query->condition('date_submitted',
      [
        $fr_date->format('Y-m-d 00:00:00'),
        $to_date->format('Y-m-d 23:59:59'),
      ],
      'BETWEEN');
  }

}
