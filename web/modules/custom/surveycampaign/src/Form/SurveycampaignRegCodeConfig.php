<?php

namespace Drupal\surveycampaign\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\CronInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\State\StateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Datetime\DrupalDateTime;
use \DateTime;

/**
 * Form with examples on how to use cron.
 */
class SurveycampaignRegCodeConfig extends ConfigFormBase {

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * The cron service.
   *
   * @var \Drupal\Core\CronInterface
   */
  protected $cron;

  /**
   * The queue object.
   *
   * @var \Drupal\Core\Queue\QueueFactory
   */
  protected $queue;

  /**
   * The state keyvalue collection.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * {@inheritdoc}
   */
  public function __construct(ConfigFactoryInterface $config_factory, AccountInterface $current_user, CronInterface $cron, QueueFactory $queue, StateInterface $state) {
    parent::__construct($config_factory);
    $this->currentUser = $current_user;
    $this->cron = $cron;
    $this->queue = $queue;
    $this->state = $state;

  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $form = new static(
      $container->get('config.factory'),
      $container->get('current_user'),
      $container->get('cron'),
      $container->get('queue'),
      $container->get('state')
    );
    $form->setMessenger($container->get('messenger'));
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'surveycampaign';
  }

  /**
   * {@inheritdoc}
   */
  public function formQuery($table,$var,$survid,$day = 0) {
    $database = \Drupal::database();
    $result = $database->select($table, 'ta')
    ->fields('ta', array(
    "$var"
      )
    )
    ->condition('ta.surveyid', $survid)
    ->condition('senddate', $database->escapeLike($daydate) . '%', 'LIKE')
    ->execute()->fetchField();
    return $result;

  }
  public function buildForm(array $form, FormStateInterface $form_state) {

    $i = 0;
    $name_field = $form_state->get('num_codes');
    $config = $this->configFactory->get('surveycampaign.settings');
    $provcodes = $config->get('def_provider_code');
    $provnames = $config->get('def_provider_name');
    $countcodes = is_array($provcodes) ? count($provcodes) : 0;

    $defaultid = $config->get('defaultid');
    $secondaryid = $config->get('secondaryid');


    $form['#attached']['library'][] = 'admincss/csslib';
    $form['configuration'] = array(
      '#type' => 'vertical_tabs',
    );

    $form['configuration']['default_settings'] = array(
      '#type' => 'details',
      '#title' => t('Default survey settings'),
      '#group' => 'configuration',
    );

    $form['configuration']['default_settings']['shell'] = array(
      '#type' => 'fieldset',
      '#title' => t(''),
      '#tree' => TRUE,
    );

      $form['configuration']['default_settings']['shell']['provider_fieldset'] = [
        '#type' => 'fieldset',
        '#title' => $this->t('Provider code'),
        '#prefix' => "<div id='names-fieldset-wrapper'>",
        '#suffix' => '</div>',
      ];

      if (empty($name_field) || $countcodes < 1) {
        $name_field = $countcodes <= 1 ? $form_state->set('num_codes', 1) : $form_state->set('num_codes', $countcodes);

      }


      for ($i = 0; $i < $form_state->get('num_codes'); $i++) {
        $thiscode = !empty($provcodes) && $provcodes[$i] ? $provcodes[$i] : '';
        $thisname = !empty($provnames) && $provnames[$i] ? $provnames[$i][0]['target_id'] : null;
        $term = \Drupal\taxonomy\Entity\Term::load($thisname);
        $j = $i + 1;


        /* $form['configuration']['default_settings']['shell']['provider_fieldset'][$i]['provider_name'] = [
          '#type' => 'textfield',
          '#title' => $this->t('Holiday name'),
          '#maxlength' => 64,
          '#size' => 64,
          '#default_value' => $thisname,
          '#prefix' => "<div class='inner-fieldset'><legend><span class='fieldset-legend'>Holiday {$j}</span></legend>",
        ]; */


        $form['configuration']['default_settings']['shell']['provider_fieldset'][$i]['provider_name'] = [
            '#type' => 'entity_autocomplete',
            '#target_type' => 'taxonomy_term',
            '#title' => $this->t('Provider name'),
            '#description' => $this->t('Enter your provider or agency name.'),
            '#tags' => TRUE,
            '#default_value' => $term,
            '#selection_settings' => [
            'target_bundles' => ['providers'],
            ],
            '#weight' => '0',
            '#prefix' => "<div class='inner-fieldset' style=\"margin-bottom: 25px;\"><legend><h3 class='fieldset-legend'>Provider {$j}</h3></legend>",
            '#suffix' => '</div>',
        ];


        $form['configuration']['default_settings']['shell']['provider_fieldset'][$i]['provider_code'] = [
            '#type' => 'textfield',
            '#title' => $this->t('Provider code'),
            '#maxlength' => 64,
            '#size' => 64,
            '#default_value' => $thiscode,
            '#suffix' => '<hr />',

          ];

      }
        $form['configuration']['default_settings']['shell']['provider_fieldset']['actions'] = [
          '#type' => 'actions',
        ];
        $form['configuration']['default_settings']['shell']['provider_fieldset']['actions']['add_name'] = [
          '#type' => 'submit',
          '#value' => t('Add another provider code'),
          '#submit' => array('::addOne'),
          '#ajax' => [
            'callback' => '::addmoreCallback',
            'wrapper' => "names-fieldset-wrapper",
          ],
        ];
        if ($form_state->get('num_codes') > 1) {
          $form['configuration']['default_settings']['shell']['provider_fieldset']['actions']['remove_name'] = [
            '#type' => 'submit',
            '#value' => t('Remove this provider code'),
            '#submit' => array('::removeCallback'),
            '#ajax' => [
              'callback' => '::addmoreCallback',
              'wrapper' => "names-fieldset-wrapper",
            ],
          ];
        }









    $form_state->setCached(FALSE);
    return parent::buildForm($form, $form_state);
  }
   /**
   * Callback for both ajax-enabled buttons.
   *
   * Selects and returns the fieldset with the names in it.
   */
  public function addmoreCallback(array &$form, FormStateInterface $form_state) {
    $name_field = $form_state->get('num_codes');
    return $form['configuration']['default_settings']['shell']['provider_fieldset'];
  }

  /**
   * Submit handler for the "add-one-more" button.
   *
   * Increments the max counter and causes a rebuild.
   */
  public function addOne(array &$form, FormStateInterface $form_state) {
    $name_field = $form_state->get('num_codes');
    $add_button = $name_field + 1;
    $form_state->set('num_codes', $add_button);
    $form_state->setRebuild();
  }

  /**
   * Submit handler for the "remove one" button.
   *
   * Decrements the max counter and causes a form rebuild.
   */
  public function removeCallback(array &$form, FormStateInterface $form_state) {
    $name_field = $form_state->get('num_codes');
    if ($name_field > 1) {
      $remove_button = $name_field - 1;
      $form_state->set('num_codes', $remove_button);
    }
    $form_state->setRebuild();
  }


  /**
   * Allow user to directly execute cron, optionally forcing it.
   */
  public function cronRun(array &$form, FormStateInterface &$form_state) {
    $config = $this->configFactory->getEditable('surveycampaign.settings');

    $cron_reset = $form_state->getValue('cron_reset');
    if (!empty($cron_reset)) {
      $this->state->set('surveycampaign.next_execution', 0);
    }

    // Use a state variable to signal that cron was run manually from this form.
    $this->state->set('surveycampaign_show_status_message', TRUE);
    if ($this->cron->run()) {
      $this->messenger()->addMessage($this->t('Cron ran successfully.'));
    }
    else {
      $this->messenger()->addError($this->t('Cron run failed.'));
    }
  }

  /**
   * Add the items to the queue when signaled by the form.
   */
  public function addItems(array &$form, FormStateInterface &$form_state) {
    $values = $form_state->getValues();
    $queue_name = $form['cron_queue_setup']['queue'][$values['queue']]['#title'];
    $num_items = $form_state->getValue('num_items');
    // Queues are defined by a QueueWorker Plugin which are selected by their
    // id attritbute.
    // @see \Drupal\surveycampaign\Plugin\QueueWorker\ReportWorkerOne
    $queue = $this->queue->get($values['queue']);

    for ($i = 1; $i <= $num_items; $i++) {
      // Create a new item, a new data object, which is passed to the
      // QueueWorker's processItem() method.
      $item = new \stdClass();
      $item->created = \Drupal::time()->getRequestTime();
      $item->sequence = $i;
      $queue->createItem($item);
    }

    $args = [
      '%num' => $num_items,
      '%queue' => $queue_name,
    ];
    $this->messenger()->addMessage($this->t('Added %num items to %queue', $args));
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    $namearray = array();
    $holarray = array();
    foreach ($form_state->getValue(array('shell','provider_fieldset')) as $key => $value) {
      if(is_numeric($key)) $namearray[]= $form_state->getValue(array('shell','provider_fieldset',$key, 'provider_name'));
      if(is_numeric($key)) $holarray[]= $form_state->getValue(array('shell','provider_fieldset',$key, 'provider_code'));
    }
    $config = $this->configFactory->get('surveycampaign.settings');

    $this->configFactory->getEditable('surveycampaign.settings')
      ->set('def_provider_name',$namearray)
      ->set('def_provider_code',$holarray)
      ->save();
      //Future: this is how you remove a single value in an array
      //$this->configFactory()->getEditable('surveycampaign.settings')->clear('def_provider_code.1')->save();
      //$this->configFactory()->getEditable('surveycampaign.settings')->clear('def_provider_name.1')->save();






    //set up conditions for update time or create new survey




    parent::submitForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['surveycampaign.settings',];
  }

}
