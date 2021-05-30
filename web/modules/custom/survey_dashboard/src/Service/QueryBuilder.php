<?php

namespace Drupal\survey_dashboard\Service;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\survey_dashboard\Query\What;
use Drupal\survey_dashboard\Query\Where;
use Drupal\survey_dashboard\Query\Who;
use Drupal\taxonomy\Entity\Term;
use Drupal\user\Entity\User;

class QueryBuilder {

  const BASE_TABLE = 'surveycampaign_results';
  protected $who;
  protected $where;
  protected $what;
  protected $timeframe;
  protected $email;
  protected $provider;

  private function getTaxonomyValue($vid, $tid) {
    if (!$tid || !is_numeric($tid)) {
      return NULL;
    }

    $term = Term::load($tid);
    if (!$term) {
      return NULL;
    }
    if ($vid != 'who') {
      return $term->field_dashboard_response_id->value;
    }
    else {

    }
  }

  private function getUserInfo() {
    $this->email = \Drupal::currentUser()->getEmail();
    $this->provider = \Drupal::currentUser()->getAccount()->field_provider->value;
  }

  public function process($params = []) {

    $acct = User::load(\Drupal::currentUser()->id());

    $this->timeframe = $params['timeframe'];
    $this->who = $this->getTaxonomyValue('who', $params['who']);
    $this->what = $this->getTaxonomyValue('what', $params['what']);
    $this->where = $this->getTaxonomyValue('where', $params['where']);
    $this->email = \Drupal::currentUser()->getEmail();
    $this->provider = $acct->field_provider->value;

    switch ($this->timeframe) {
      case 'quarterly':
        $result = $this->process_quarterly();

      case 'monthly':
        $result = $this->process_monthly();

      default:
      case 'up-to-date':
        $result = $this->process_up_to_date();
    }

    return $result;
  }

  protected function process_up_to_date() {
    if (!$this->what) {
      return $this->whatSummary();
    }
    elseif ($this->who == 'any') {
      return $this->whoSummary();
    }
    elseif ($this->where == 'any') {
      return $this->whereSummary();
    }
    else {
      return $this->selectedActivities();
    }
  }

  protected function selectedActivities() {
    $query = new What($this->email, $this->provider);
    $query->addWhatCondition($this->what);
    if ($this->where && $this->where != 'any') {
      $query->addWhereCondition($this->where);
    }
    elseif ($this->who && $this->who != 'any') {
      $query->addWhoCondition($this->who);
    }

    return $query->execute();
  }

  protected function whatSummary() {
    $query = new What($this->email, $this->provider);
    if ($this->where && $this->where != 'any') {
      $query->addWhereCondition($this->where);
    }
    elseif ($this->who && $this->who != 'any') {
      $query->addWhoCondition($this->who);
    }

    return $query->execute();
  }

  protected function whoSummary() {
    $query = new Who($this->email, $this->provider);
    if ($this->where && $this->where != 'any') {
      $query->addWhereCondition($this->where);
    }
    elseif ($this->what && $this->what != 'any') {
      $query->addWhatCondition($this->what);
    }
    return $query->execute();
  }

  protected function whereSummary() {
    $query = new Where($this->email, $this->provider);
    if ($this->what && $this->what != 'any') {
      $query->addWhatCondition($this->where);
    }
    elseif ($this->who && $this->who != 'any') {
      $query->addWhoCondition($this->who);
    }
    return $query->execute();
  }

  protected function process_quarterly() {

  }

  protected function process_monthly() {

  }
}
