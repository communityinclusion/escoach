<?php
namespace \Drupal\es_homepage\Query;

class adminActivity extends HomePageQuery {

  public function __construct($year, $month, $email = NULL, $provider = NULL) {
    parent::__construct($year, $month, $email, $provider);
    $this->addCondition('answer483', 11663);
  }

}
