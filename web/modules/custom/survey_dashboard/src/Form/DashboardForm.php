<?php

namespace Drupal\survey_dashboard\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\taxonomy\Entity\Term;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class DashboardForm.
 */
class DashboardForm extends FormBase {

  /** @var \Drupal\survey_dashboard\Service\QueryBuilder */
  private $surveyDashboardQueryBuilder;
  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->surveyDashboardQueryBuilder = $container->get('survey_dashboard.query_builder');
    return $instance;
  }
  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'dashboard_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['what'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('What'),
      '#options' => $this->getTerms('what'),
      '#weight' => '0',
      '#default_value' => $form_state->get('what') ?? [],
    ];
    $form['who'] = [
      '#type' => 'radios',
      '#title' => $this->t('Who'),
      '#options' => $this->getTerms('who', 'any', 'Any Interaction'),
      '#weight' => '1',
      '#default_value' => $form_state->get('who') ?? NULL,
      '#empty_value' => '_none',
      '#empty_option' => 'None',
      '#required' => FALSE,
    ];
    $form['where'] = [
      '#type' => 'radios',
      '#title' => $this->t('Where'),
      '#options' => $this->getTerms('where', 'any', 'Anyplace'),
      '#weight' => '2',
      '#default_value' => $form_state->get('where') ?? NULL,
      '#empty_value' => '_none',
      '#empty_option' => 'None',
      '#required' => FALSE,
    ];

    $form['timeframe'] = [
      '#type' => 'radios',
      '#title' => $this->t('Time Frame'),
      '#options' => [
        'up-to-date' => $this->t('Up-to-date'),
        'quarterly' => $this->t('Quarterly Trends'),
        'monthly' => $this->t('Monthly Trends')
      ],
      '#default_value' => $form_state->get('time_frame') ?? 'up-to-date',
      '#weight' => '4',
    ];

    $input = $form_state->getUserInput();

    $params = [
      'timeframe' => $input['timeframe'] ?? '',
      'dataframe' => $input['dataframe'] ?? '',
      'who' => $input['who'] ?? NULL,
      'what' => $input['what'] ?? NULL,
      'where' => $input['where'] ?? NULL,
    ];

    $results = $this->surveyDashboardQueryBuilder->process($params);

    $form['results'] = [
      '#type' => 'container',
      '#weight' => 5,
      '#attributes' => [
        'id' => 'results',
      ],
      'widget' => [
        '#type' => 'markup',
        '#markup' => render($results) ,
      ]
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit'),
      '#weight' => 6,
      '#ajax' => [
        'event' => 'click',
        'callback' => '::submitCallback',
        'progress' => [
          'type' => 'throbber',
          'message' => 'Working...',
        ],
        'wrapper' => 'results',
      ],
    ];

    $form['#attached']['library'][] = 'survey_dashboard/dashboard';
    $form['#theme'] = ['dashboard-form'];

    // Don't cache the form
    $form['#cache']['max-age'] = 0;
    return $form;
  }

  public function submitCallback(&$form, $form_state) {
    return $form['results'];
  }

  private function getTerms($vid, $empty_value = NULL, $emty_option = NULL) {
    $term_data = [];

    if (!is_null($empty_value)) {
      $term_data[$empty_value] = $this->t($emty_option);
    }
    $terms = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadTree($vid);

    foreach ($terms as $term) {
      if ($vid == 'what' ) {
        if ($term->parents[0] == 0) {
          $term_name = $this->t('<strong>' . $term->name . '</strong>');
        }
        else {
          $term_name = '-- ' . $term->name;
        }
      }
      else {
        $term_name = $term->name;
      }

      $term_data[$term->tid] = $term_name;
    }

    return $term_data;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    foreach ($form_state->getValues() as $key => $value) {
      // @TODO: Validate fields.
    }
    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Display result.

  }

}