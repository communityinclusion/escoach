<?php
namespace Drupal\es_homepage\Query;

class StateQuery extends HomePageQuery {

  const VID = NULL;

  public function __construct($year, $month, $email, $provider) {
    parent::__construct($year, $month, $email, $provider);
    $this->query->addExpression('DISTINCT(state)', 'state');
  }

}
