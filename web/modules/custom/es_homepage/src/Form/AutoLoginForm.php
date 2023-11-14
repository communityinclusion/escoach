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

    $form['url'] = [
      '#type' => 'textfield',
      '#title' => 'Redirect URL',
      '#required' => TRUE,
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Generate CSV'),
    ];
    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    // 1.  Delete previous links
    $this->autoLoginService->deleteAllLinks();

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
