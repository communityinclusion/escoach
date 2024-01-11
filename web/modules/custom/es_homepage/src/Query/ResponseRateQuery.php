<?php
namespace Drupal\es_homepage\Query;

class ResponseRateQuery extends HomePageQuery {

  const BASE_TABLE = 'surveycampaign_mailer';

  protected $email;
  protected $provider;

  public function __construct($year, $month, $email, $provider) {

    // Not calling parent::__construct because it uses suverycampaign_results as the base table
    $database = \Drupal::database();
    $this->query = $database->select(self::BASE_TABLE, 'mailer');

    $this->query->addExpression('count(*)', 'totalSurveysSent');
    $this->query->addExpression('count(distinct(fullname))', 'respondents');
    $this->query->addExpression('count(case when mailer.Complete =1 then 1 end)- count(case when results.answer482 = 11760 then 1 end)', 'netResponses');
    $this->query->addExpression('(count(case when mailer.Complete =1 then 1 end)- count(case when results.answer482 = 11760 then 1 end))/count(*)', 'responseRate');
    $this->query->addJoin('LEFT', 'surveycampaign_results', 'results', 'mailer.contactid = results.contact_id');
    $this->setDateRange($year, $month, 'mailer.senddate');
    $this->query->condition('mailer.surveyid', 5420562);
    $this->email = $email;
    $this->provider = $provider;
  }

  public function addMe() {
    $this->query->condition('results.email', $this->email);
  }

  public function addProvider() {
    $this->query->condition('mailer.provider', $this->provider);
  }

  public function addState($state) {
    $this->query->condition('results.state', $state);
  }
}
