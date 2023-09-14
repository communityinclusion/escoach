<?php
namespace \Drupal\es_homepage\Query;

class naturalSupportsBestPractice extends HomePageQuery {

  public function __construct($year, $month, $email = NULL, $provider = NULL) {
    parent::__construct($year, $month, $email, $provider);
    $this->addCondition('answer540', [11863, 11862], 'IN');
  }

}
