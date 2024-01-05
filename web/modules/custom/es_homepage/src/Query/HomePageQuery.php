<?php
namespace Drupal\es_homepage\Query;


class HomePageQuery extends BaseQuery {

  public function __construct($year, $month, $email, $provider) {
    // BaseQuery is centered around email and provider.  Not sure if that is needed here or not.
    parent::__construct($email, $provider);
    $this->setDateRange($year, $month);
  }

  public function setDateRange($year, $month, $dateField = 'date_submitted') {

    if (!$year || !$month) {
      $today = new \DateTime();
      $lastMonth = $today->modify('first day of last month');
      $month = $lastMonth->format('m');
      $year = $lastMonth->format('Y');
    }

    $fr_date = new \DateTime("$year-$month-01");
    $date = new \DateTime("$year-$month-01");
    $to_date = $date->modify('last day of this month');

    $this->query->condition($dateField,
      [
        $fr_date->format('Y-m-d 00:00:00'),
        $to_date->format('Y-m-d 23:59:59'),
      ],
      'BETWEEN');
  }

  public function distinctProviders() {
    $this->addExpression('distinct(provider)', 'provider');
    $this->query->orderBy('provider', 'ASC');
  }

  public function distinctUsers() {
    $this->addExpression('distinct(email)', 'email');
    $this->query->orderBy('email', 'ASC');
  }

  protected function getMachineName($string) {
    $transliterated = \Drupal::transliteration()->transliterate($string, LanguageInterface::LANGCODE_DEFAULT, '_');
    $transliterated = mb_strtolower($transliterated);

    return preg_replace('@[^a-z0-9_.]+@', '_', $transliterated);
  }
}
