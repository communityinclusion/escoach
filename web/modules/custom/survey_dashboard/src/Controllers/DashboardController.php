<?php
namespace Drupal\survey_dashboard\Controllers;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Form\FormState;

class DashboardController extends ControllerBase {

  public function dashboardForm() : array {
    $form_state = new FormState();
    $form_state->setMethod('get');
    $form_state->setAlwaysProcess(TRUE);
    $form_state->setRebuild();

    $form = \Drupal::formBuilder()->buildForm('Drupal\survey_dashboard\Form\DashboardForm', $form_state);
    unset($form['form_build_id']);
    unset($form['form_id']);

    return $form;
  }
}
