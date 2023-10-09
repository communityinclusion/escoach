<?php
namespace Drupal\es_homepage\Query;

class StateQuery extends HomePageQuery {

  public function __construct($year, $month, $email, $provider) {
    parent::__construct($year, $month, $email, $provider);
    $this->query->addExpression('DISTINCT(state)', 'state');
  }

}
