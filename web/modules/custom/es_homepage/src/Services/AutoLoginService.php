<?php
namespace Drupal\es_homepage\Services;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountProxy;
use Drupal\profile\Entity\Profile;

class AutoLoginService {

  public function __construct() {

  }
  /**
   * @param $uid
   * @param $destination
   *
   * @return mixed
   */
  public function generateLinks($destination) {
    $pids = $this->getConsultantPIDs();
    $profiles = Profile::loadMultiple($pids);
    foreach ($profiles as $profile) {
      $url = auto_login_url_create($profile->uid->target_id, $destination, TRUE);
      $profile->field_auto_login_url = $url;
      $profile->save();
    }

  }

  private function getConsultantPIDs() {
    return \Drupal::entityQuery('profile')
      ->condition('type', ['survey_participants', 'Manager-consultant'], 'IN')
      ->condition('field_job_type', 'Employment consultant')
      ->accessCheck(TRUE)
      ->execute();
  }

  /**
   * @return void
   */
  public function deleteAllLinks() : void {
    $connection = \Drupal::database();
    $connection->delete('auto_login_url')
      ->execute();
  }
}
