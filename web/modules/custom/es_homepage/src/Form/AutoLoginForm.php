<?php
namespace Drupal\es_homepage\Form;

use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Site\Settings;
use Drupal\es_homepage\Services\AutoLoginService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

class AutoLoginForm extends FormBase {

  /**
   * @var \Drupal\es_homepage\Services\AutoLoginService
   */
  private $autoLoginService;

  public function __construct(AutoLoginService $autoLoginService) {
    $this->autoLoginService = $autoLoginService;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    // Instantiates this form class.
    return new static(
      $container->get('es_homepage.auto_login_service'),
    );
  }

  public function getFormId() {
    return 'es_homepage.auto_login_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state) {

    $form['clear'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Clear all URLs and clear the Autologin URL field in profile without setting a new URL')
    ];

    $form['url'] = [
      '#type' => 'textfield',
      '#title' => 'Redirect URL',
      '#description' => $this->t('Use fully-qualified URLs. (e.g. https://escoach.com/dashboard)'),
      '#required' => FALSE,
      '#maxlength' => 1000,
      '#size' => 80,
      '#states' => [
        'visible' => [
          ':input[name="clear"]' => ['checked' => FALSE]
        ],
        'required' => [
          ':input[name="clear"]' => ['checked' => FALSE]
        ]
      ]
    ];

    $form['delete'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Delete ALL  previously generated auto login links.'),
      '#states' => [
        'visible' => [
          ':input[name="clear"]' => ['checked' => FALSE]
        ],
      ]
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Generate CSV'),
    ];

    $form['#attached']['library'] = ['es_homepage/auto_login'];
    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {

    // 1.  Delete previous links
    $delete = $form_state->getValue('delete');
    $clear = $form_state->getValue('clear');
    if ($delete == 1 || $clear == 1) {
      $this->autoLoginService->deleteAllLinks();
    }

    if ($clear == 1) {
      // 2.  Clear all auto login url fields in profiles
      $this->autoLoginService->clearLinks();
      \Drupal::messenger()->addMessage('All auto login links have been cleared');
      $form_state->setRebuild(TRUE);
    }
    else {
      // 2.  Generate new links/CSV
      $data = $this->autoLoginService->generateLinks($form_state->getValue('url'));

      $filename = 'user-links.csv';
      $response = new BinaryFileResponse($data);
      $response->setContentDisposition(
        ResponseHeaderBag::DISPOSITION_ATTACHMENT,
        $filename
      );
      $form_state->setResponse($response);
    }

  }

}
