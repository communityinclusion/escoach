<?php
namespace Drupal\es_homepage\Services;

use Drupal\es_homepage\Query\bestPractices;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountProxy;
use Drupal\es_homepage\Query\keyActivities;


class HomePageService {

  private $year;

  private $month;

  private $email;

  private $provider;

  /**
   * @var string
   */
  private $previousMonth;

  /**
   * @var string
   */
  private $previousYear;

  /**
   * @var \Drupal\Core\Session\AccountProxy
   */
  private $currentUser;

  /**
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  private $entityTypeManager;

  public function __construct(AccountProxy $currentUser, EntityTypeManagerInterface $entityTypeManager) {
    $this->email = $currentUser->getEmail();
    $this->currentUser = $currentUser;
    $this->entityTypeManager = $entityTypeManager;

    $this->getProvider();
  }

  private function getProvider() {
    $profiles = $this->entityTypeManager->getStorage('profile')
      ->loadByProperties([
        'uid' => $this->currentUser->id(),
        'type' => 'survey_participants',
        'is_default' => 1,
      ]);

    if ($profiles) {
      $profile = current($profiles);
    }
    if (isset($profile)) {
      if (isset($profile->field_provider->entity)) {
        $this->provider = $profile->field_provider->entity->getName();
      }
    }

  }

  public function setDateRange($year, $month) {

    if (!$year || !$month) {
      $today = new \DateTime();
      $lastMonth = $today->modify('first day of last month');
      $month = $lastMonth->format('m');
      $year = $lastMonth->format('Y');
    }

    $this->year = $year;
    $this->month = $month;

    $month = sprintf('%02d', $month);
    $dt = new \DateTime("$year-$month-01 00:00:00");
    $prev = $dt->sub( \DateInterval::createFromDateString('1 days'));

    $this->previousMonth = $prev->format('m');
    $this->previousYear = $prev->format('Y');
  }


  public function keyActivities($year, $mont, $state = NULL) : array {
    $this->setDateRange($year, $month);
    $keyActivities = new keyActivities($this->year, $this->month, $this->email, $this->provider);

    if (!empty($this->email)) {
      $keyActivities->buildSums('Me');
    }

    if (!empty($this->provider)) {
      $keyActivities->buildSums('Provider');
    }

    if (!empty($state)) {
      $keyActivities->buildSums('State', $state);
    }

    $keyActivities->buildSums('All');

    $lastMonthResults = $keyActivities->execute();

    $keyActivities = new keyActivities($this->previousYear, $this->previousMonth, $this->email, $this->provider);

    if (!empty($this->email)) {
      $keyActivities->buildSums('Me');
    }
    if (!empty($this->provider)) {
      $keyActivities->buildSums('Provider');
    }
    $keyActivities->buildSums('All');

    $prevMonthResults = $keyActivities->execute();

    return [
      'lastMonth' => $lastMonthResults,
      'prevMonth' => $prevMonthResults
    ];
  }

  public function bestPractices($year, $month, $state = NULL) : array {
    $this->setDateRange($year, $month);
    $bestPractices = new bestPractices($this->year, $this->month, $this->email, $this->provider);

    if (!empty($this->email)) {
      $bestPractices->buildSums('Me');
    }

    if (!empty($this->provider)) {
      $bestPractices->buildSums('Provider');
    }

    if (!empty($state)) {
      $bestPractices->buildSums('State', $state);
    }

    $bestPractices->buildSums('All');

    $lastMonthResults = $bestPractices->execute();

    $bestPractices = new bestPractices($this->previousYear, $this->previousMonth, $this->email, $this->provider);

    if (!empty($this->email)) {
      $bestPractices->buildSums('Me');
    }

    if (!empty($this->provider)) {
      $bestPractices->buildSums('Provider');
    }

    if (!empty($state)) {
      $bestPractices->buildSums('State', $state);
    }

    $bestPractices->buildSums('All');

    $prevMonthResults = $bestPractices->execute();
    return [
      'lastMonth' => $lastMonthResults,
      'prevMonth' => $prevMonthResults
    ];
  }
}
