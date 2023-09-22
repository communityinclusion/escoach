<?php
namespace Drupal\es_home\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\es_homepage\Services\HomePageService;

class StateSelectionForm extends FormBase {

  /**
   * @var \Drupal\es_homepage\Services\HomePageService
   */
  private $homePageService;

  public function __construct(HomePageService $homePageService) {

    $this->homePageService = $homePageService;
  }

  public function getFormId() {
    return 'es_home.state_selection_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['state'] = [
      '#type' => 'select',
      '#options' => $this->homePageService->getStateList(),
      '#ajax' => [
        'callback' => [$this, 'selectState'],
        'wrapper' => '',
      ],
    ];

    return $form;
  }

  public function selectState(array $form, FormStateInterface $formState) {

  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    // TODO: Implement submitForm() method.
  }

}
