<?php
namespace Drupal\es_homepage\Services;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Session\AccountProxy;
use Drupal\Core\Site\Settings;
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
    $data = '';

    /** @var \Drupal\file\FileRepositoryInterface $fileRepository */
    $fileRepository = \Drupal::service('file.repository');
    $filename = \Drupal::service('file_system')->tempnam('temporary://', 'tmp_', Settings::get('file_temporary_path'));
    $fp = fopen($filename, 'w');
    if (!$fp) {
      return NULL;
    }

    $headers = [
      'First Name',
      'Last Name',
      'Phone #',
      'Email',
      'Login Link',
    ];

    fputcsv($fp, $headers);

    $profiles = Profile::loadMultiple($pids);
    foreach ($profiles as $profile) {
      $url = auto_login_url_create($profile->uid->target_id, $destination, TRUE);
      $profile->field_auto_login_url = $url;
      $profile->save();
      $rec = [
        $profile->field_survey_first_name->value,
        $profile->field_survey_last_name->value,
        $profile->field_cell_phone->value,
        $profile->uid->entity->mail->value,
        $url,
      ];

      fputcsv($fp, $rec);
    }

    return $filename;

  }

  private function getConsultantPIDs() {
    return \Drupal::entityQuery('profile')
      ->condition('type', ['survey_participants'] )
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
