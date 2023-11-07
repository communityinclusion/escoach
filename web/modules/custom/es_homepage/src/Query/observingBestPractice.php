<?php

namespace Drupal\es_homepage\Query;

class observingBestPractice extends HomePageQuery {

  public function __construct($year, $month, $email = NULL, $provider = NULL) {
    parent::__construct($year, $month, $email, $provider);
    $this->addCondition('answer537', [11838, 11899, 12115], 'IN');
  }

}
