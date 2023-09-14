<?php
namespace \Drupal\es_homepage\Query;

class withFamiliesBestPractice extends HomePageQuery {

  public function __construct($year, $month, $email = NULL, $provider = NULL) {
    parent::__construct($year, $month, $email, $provider);
    // Leading to Hire
    $this->addCondition('answer483', [11658, 11659, 11661], 'IN');

    $group = $this->query->orConditionGroup();
    $group->condition('answer481', 11641);
    $group->condition('answer590', 12119);
    $this->query->condition($group);
  }

}
