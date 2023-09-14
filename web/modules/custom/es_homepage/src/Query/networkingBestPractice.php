<?php
namespace \Drupal\es_homepage\Query;

class networkingBestPractice extends HomePageQuery {

  public function __construct($year, $month, $email = NULL, $provider = NULL) {
    parent::__construct($year, $month, $email, $provider);
    $this->addCondition('answer538', [11845, 11844, 11843], 'IN');
  }

}
