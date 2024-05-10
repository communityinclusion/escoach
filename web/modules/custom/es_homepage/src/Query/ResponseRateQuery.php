<?php
namespace Drupal\es_homepage\Query;

class ResponseRateQuery extends HomePageQuery {

  const BASE_TABLE = 'surveycampaign_mailer';

  protected $email;
  protected $provider;

  public function __construct($year, $month, $email, $provider, $exclude = 0) {

    // Not calling parent::__construct because it uses suverycampaign_results as the base table
    $database = \Drupal::database();
    $this->query = $database->select(self::BASE_TABLE, 'mailer');

    if ($exclude) {
      $excludeStr = ' AND results.regcode >= 10000 ';
    }
    $this->query->addExpression('count(*)', 'totalSurveysSent');
    $this->query->addExpression('count(distinct(email))', 'respondents');
    $this->query->addExpression('count(case when mailer.Complete =1 then 1 end)- count(case when results.answer482 = 11760 then 1 end)', 'netResponses');
    $this->query->addExpression("(count(case when mailer.Complete =1 $excludeStr then 1 end)- count(case when results.answer482 = 11760 $excludeStr then 1 end))/count(*)", 'responseRate');
    $this->query->addJoin('LEFT', 'surveycampaign_results', 'results', 'mailer.contactid = results.contact_id');
    $this->setDateRange($year, $month, 'mailer.senddate');
    $this->query->condition('mailer.surveyid', 5420562);
    $this->email = $email;
    $this->provider = $provider;
  }

  public function addMinRegCode($regCode = 10000) {
    $this->query->condition('results.regcode', $regCode, '>=');
  }

  public function addMe() {
    $tmpQuery = \Drupal::database()->select('surveycampaign_results', 'results');
    $tmpQuery->addExpression('distinct(name)', 'fullname');
    $tmpQuery->condition('email', $this->email);
    $result = $tmpQuery->execute();
    if ($result) {
      $val = $result->fetch();
      $this->query->condition('mailer.fullname', $val->fullname);
    }


  }

  public function addProvider() {
    $this->query->condition('mailer.provider', $this->provider);
  }

  public function addState($state) {
    $this->query->condition('results.state', $state);
  }
}
