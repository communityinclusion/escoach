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
class SurveycampaignConfigurationForm extends ConfigFormBase {

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
    $todaydate = date("Y-m-d");
    $tomorrowdate = new DateTime("$todaydate");
    $tomorrowdate->modify('+ 1 day');
    $tomorrowdate = $tomorrowdate->format('Y-m-d');
    $daydate = $day == 0 ? $todaydate : $tomorrowdate;
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
    $config = $this->configFactory->get('surveycampaign.settings');
    $defaultid = $config->get('defaultid');
    $secondaryid = $config->get('secondaryid');
    $datereturn = $this->formQuery('surveycampaign_campaigns','senddate',$defaultid,0);
    $datereturntomorrow = $this->formQuery('surveycampaign_campaigns','senddate',$defaultid,1);
    $secnddatereturn = $this->formQuery('surveycampaign_campaigns','senddate',$secondaryid,0);
    $secnddatereturntomorrow = $this->formQuery('surveycampaign_campaigns','senddate',$secondaryid,1);
    
    

    $form['configuration']['survey_admin_mail'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Survey administrator email'),
      '#description' => $this->t('Automated emails from survey users will use this address'),
      '#default_value' => $config->get('survey_admin_mail'),
       '#size' => 60,
      '#maxlength' => 128,
      '#required' => TRUE,
      
    ];
   
    $form['configuration']['surveycampaign_def_survey'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Default survey id'),
      '#description' => $this->t('The default survey id number from SurveyGizmo'),
      '#default_value' => $defaultid,
       '#size' => 60,
      '#maxlength' => 128,
      '#required' => TRUE,
      
    ];
    $form['configuration']['default_disable'] = array(
      '#type' => 'radios',
      '#title' => t('Default survey Enable/disable'),
      '#description' => t('Send default survey or disable it.'),
      '#default_value' => $config->get('defaultenable'),
      '#options' => array(
        t('Enable'),
        t('Disable'),
      )
      );
      $form['configuration']['hour_range_low'] = [
        '#type' => 'select',
        '#title' => $this->t('Select earliest time to start random survey period'),
        '#options' => [
          '21600' => $this->t('6:00 AM'),
          '23400' => $this->t('6:30 AM'),
          '25200' => $this->t('7:00 AM'),
          '27000' => $this->t('7:30 AM'),
          '28800' => $this->t('8:00 AM'),
          '30600' => $this->t('8:30 AM'),
          '32400' => $this->t('9:00 AM'),
          '34200' => $this->t('9:30 AM'),
          '36000' => $this->t('10:00 AM'),
          '37800' => $this->t('10:30 AM'),
          '39600' => $this->t('11:00 AM'),
          '41400' => $this->t('11:30 AM'),
          '43200' => $this->t('12:00 PM'),
          '45000' => $this->t('12:30 PM'),
          '46800' => $this->t('1:00 PM'),
          '48600' => $this->t('1:30 PM'),
          '50400' => $this->t('2:00 PM'),
          '52200' => $this->t('2:30 PM'),
          '54000' => $this->t('3:00 PM'),
          '55800' => $this->t('3:30 PM'),
          '57600' => $this->t('4:00 PM'),
          '59400' => $this->t('4:30 PM'),
          '61200' => $this->t('5:00 PM'),
          '63000' => $this->t('5:30 PM'),
        ],
        '#default_value' => $config->get('hour_range_low'),
      ];
    
      $form['configuration']['hour_range_high'] = [
        '#type' => 'select',
        '#title' => $this->t('Select latest time to start random survey period'),
        '#options' => [
          '25200' => $this->t('7:00 AM'),
          '27000' => $this->t('7:30 AM'),
          '28800' => $this->t('8:00 AM'),
          '30600' => $this->t('8:30 AM'),
          '32400' => $this->t('9:00 AM'),
          '34200' => $this->t('9:30 AM'),
          '36000' => $this->t('10:00 AM'),
          '37800' => $this->t('10:30 AM'),
          '39600' => $this->t('11:00 AM'),
          '41400' => $this->t('11:30 AM'),
          '43200' => $this->t('12:00 PM'),
          '45000' => $this->t('12:30 PM'),
          '46800' => $this->t('1:00 PM'),
          '48600' => $this->t('1:30 PM'),
          '50400' => $this->t('2:00 PM'),
          '52200' => $this->t('2:30 PM'),
          '54000' => $this->t('3:00 PM'),
          '55800' => $this->t('3:30 PM'),
          '57600' => $this->t('4:00 PM'),
          '59400' => $this->t('4:30 PM'),
          '61200' => $this->t('5:00 PM'),
          '63000' => $this->t('5:30 PM'),
          '64800' => $this->t('6:00 PM'),
          '66600' => $this->t('6:30 PM'),
          '68400' => $this->t('7:00 PM'),
          '70200' => $this->t('7:30 PM'),
          '72000' => $this->t('8:00 PM'),
        ],
        '#default_value' => $config->get('hour_range_high'),
      ];
      $form['configuration']['def_send_days'] = [
        '#type' => 'checkboxes',
        '#options' => ['Sunday' => $this->t('Sunday'), 'Monday' => $this->t('Monday'), 'Tuesday' => $this->t('Tuesday'), 'Wednesday' => $this->t('Wednesday'), 'Thursday' => $this->t('Thursday'), 'Friday' => $this->t('Friday'), 'Saturday' => $this->t('Saturday')],
        '#title' => $this->t('Days to send the default survey'),
        '#default_value' => $config->get('def_send_days'),
        
      ];
      $form['configuration']['def_inactive_trigger'] = [
        '#type' => 'select',
        '#title' => $this->t('Select the number of days a user must be inactive to deactivate the default survey'),
        '#options' => [
          '2' => $this->t('2'),
          '3' => $this->t('3'),
          '4' => $this->t('4'),
          '5' => $this->t('5'),
          '6' => $this->t('6'),
          '7' => $this->t('7'),
          '8' => $this->t('8'),
          '9' => $this->t('9'),
          '10' => $this->t('10'),
        ],
        '#default_value' => $config->get('def_inactive_trigger'),
      ];
    $form['configuration']['default_survey_todaytime'] = [
      '#type' => 'datetime',
      '#title' => $this->t('Default survey: set text send time of today\'s survey: format 00:00 (24 hour time). This is at the end of the half hour survey period.'),
      '#description' => t('This field will be populated automatically every day in early AM.  You can manually change the time of the survey here.  Don\'t change the date.'),
      '#size' => 20,
      // '#date_date_element' => 'none', // hide date element
      // '#date_time_element' => 'time', // you can use text element here as well
      
      '#default_value' => ($datereturn ? DrupalDateTime::createFromTimestamp(strtotime($datereturn)) : ""),
    ];
    $form['configuration']['default_survey_tomorrowtime'] = [
      '#type' => 'datetime',
      '#title' => $this->t('Default survey: Set text send time of tomorrow\'s survey: format 00:00 (24 hour time)'),

      '#description' => t('This field will be populated automatically tomorrow in early AM.  You can manually set the time of tomorrow\' survey here.  Only use tomorrow\'s date for now.'),
      '#size' => 20,
      // '#date_date_element' => 'none', // hide date element
      // '#date_time_element' => 'time', // you can use text element here as well
      
      '#default_value' => ($datereturntomorrow ? DrupalDateTime::createFromTimestamp(strtotime($datereturntomorrow)) : ""),
    ];
    $form['configuration']['def_reminder_num'] = [
      '#type' => 'select',
      '#title' => $this->t('Number of reminders to follow up first default survey notification'),
      '#options' => [
        '0' => $this->t('0'),
        '1' => $this->t('1'),
        '2' => $this->t('2'),
      ],
      '#default_value' => $config->get('def_reminder_num'),
    ];
    $form['configuration']['first_text_body'] = [
      '#type' => 'text_format',
      '#title' => 'First text message body for default survey',
      '#description' => t('You can use these tokens to add personalized messages to the text: @name, @link, @starttime,@endtime'),
      //'#format' => 'plain_text',
      '#default_value' => $config->get('first_text_body.value'),
      '#format' => $config->get('first_text_body.format'),
    ];

    $form['configuration']['surveycampaign_alt_survey'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Secondary survey id'),
      '#description' => $this->t('The secondary survey id number from SurveyGizmo'),
      '#default_value' => $config->get('secondaryid'),
       '#size' => 60,
      '#maxlength' => 128,
      '#required' => FALSE,
      
    ];
    $form['configuration']['second_disable'] = array(
      '#type' => 'radios',
      '#title' => t('Secondary survey Enable/disable'),
      '#description' => t('Send secondary survey or disable it.'),
      '#default_value' => $config->get('secondenable'),
      '#options' => array(
        t('Enable'),
        t('Disable'),
      )
    );
    $form['configuration']['alt_send_days'] = [
      '#type' => 'checkboxes',
      '#options' => ['Sunday' => $this->t('Sunday'), 'Monday' => $this->t('Monday'), 'Tuesday' => $this->t('Tuesday'), 'Wednesday' => $this->t('Wednesday'), 'Thursday' => $this->t('Thursday'), 'Friday' => $this->t('Friday'), 'Saturday' => $this->t('Saturday')],
      '#title' => $this->t('Days to send the secondary survey'),
      '#default_value' => $config->get('alt_send_days'),
      
    ];
  
    $form['configuration']['secondary_survey_todaytime'] = [
      '#type' => 'datetime',
      '#title' => $this->t('Secondary survey: set text send time of today\'s survey: format 00:00 (24 hour time)'),
      '#description' => t('This field will be populated automatically every day in early AM if the secondary survey is enabled.  You can manually change the time of the survey here.  Don\'t change the date.'),
      '#size' => 20,
      // '#date_date_element' => 'none', // hide date element
      // '#date_time_element' => 'time', // you can use text element here as well
      
      '#default_value' => ($secnddatereturn ? DrupalDateTime::createFromTimestamp(strtotime($secnddatereturn)) : ""),
    ];
   

    $form['configuration']['secondary_survey_tomorrowtime'] = [
      '#type' => 'datetime',
      '#title' => $this->t('Secondary survey: Set text send time of tomorrow\'s survey: format 00:00 (24 hour time)'),
      '#size' => 20,
      '#description' => t('This field will be populated automatically tomorrow in early AM.  You can manually set the time of tomorrow\' survey here.  Only use tomorrow\'s date for now.'),
      // '#date_date_element' => 'none', // hide date element
      // '#date_time_element' => 'time', // you can use text element here as well
      
      '#default_value' => ($secnddatereturntomorrow ? DrupalDateTime::createFromTimestamp(strtotime($secnddatereturntomorrow)) : ""),
    ];
   


    return parent::buildForm($form, $form_state);
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
      $item->created = REQUEST_TIME;
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
    $todaydate = date("Y-m-d");
    $tomorrowdate = new DateTime("$todaydate");
    $tomorrowdate->modify('+ 1 day');
    $tomorrowdate = $tomorrowdate->format('Y-m-d');
    $this->configFactory->getEditable('surveycampaign.settings')
      ->set('defaultid', $form_state->getValue('surveycampaign_def_survey'))
      ->set('defaultenable', $form_state->getValue('default_disable'))
      ->set('secondaryid', $form_state->getValue('surveycampaign_alt_survey'))
      ->set('secondenable', $form_state->getValue('second_disable'))
      ->set('hour_range_low', $form_state->getValue('hour_range_low'))
      ->set('hour_range_high', $form_state->getValue('hour_range_high'))
      ->set('def_send_days', $form_state->getValue('def_send_days'))
      ->set('alt_send_days', $form_state->getValue('alt_send_days'))
      ->set('def_inactive_trigger', $form_state->getValue('def_inactive_trigger'))
      ->set('first_text_body', $form_state->getValue('first_text_body'))
      ->set('survey_admin_mail', $form_state->getValue('survey_admin_mail'))
      ->set('def_reminder_num',$form_state->getValue('def_reminder_num'))
      ->save();
      $defaultid = $form_state->getValue('surveycampaign_def_survey');
      $secondaryid = $form_state->getValue('surveycampaign_alt_survey');
      $datereturn = $this->formQuery('surveycampaign_campaigns','senddate',$defaultid,0);
      $datereturntomorrow = $this->formQuery('surveycampaign_campaigns','senddate',$defaultid,1);
      $formupdatetoday = new DateTime($form_state->getValue('default_survey_todaytime'));
      $formupdatetoday = $formupdatetoday->format('Y-m-d H:i:s');
      $formupdatetomorrow = new DateTime($form_state->getValue('default_survey_tomorrowtime'));
      $formupdatetomorrow = $formupdatetomorrow->format('Y-m-d H:i:s');
      $secnddatereturn = $this->formQuery('surveycampaign_campaigns','senddate',$secondaryid,0);
      $secnddatereturntomorrow = $this->formQuery('surveycampaign_campaigns','senddate',$secondaryid,1);
      // set up variables for creating new survey on form
      if($form_state->getValue('default_survey_tomorrowtime') && $form_state->getValue('default_survey_tomorrowtime') != '') {
        $defupdatetomorrow = new DateTime($form_state->getValue('default_survey_tomorrowtime'));
        $defupdatetomorrow = $defupdatetomorrow->format('Y-m-d H:i:s');
      }
      if($form_state->getValue('secondary_survey_todaytime') && $form_state->getValue('secondary_survey_todaytime') != '') {
        $secndformupdatetoday = new DateTime($form_state->getValue('secondary_survey_todaytime'));
        $secndformupdatetoday = $secndformupdatetoday->format('Y-m-d H:i:s');
      
      }
      if($form_state->getValue('secondary_survey_tomorrowtime') && $form_state->getValue('secondary_survey_tomorrowtime') != '') {
        $secndformupdatetomorrow = new DateTime($form_state->getValue('secondary_survey_tomorrowtime'));
        $secndformupdatetomorrow = $secndformupdatetomorrow->format('Y-m-d H:i:s');
      }

      //set up conditions for update time or create new survey

      if($form_state->getValue('default_survey_todaytime') != $datereturn) {
        
        $defupdatetoday = \Drupal::service('surveycampaign.twilio_coach')->updateCampaignTime($defaultid,$datereturn,$formupdatetoday,0);

      }
      if($datereturntomorrow && $form_state->getValue('default_survey_tomorrowtime') != $datereturntomorrow) {
      
        $defupdatetomorrow = \Drupal::service('surveycampaign.twilio_coach')->updateCampaignTime($defaultid,$datereturntomorrow,$formupdatetomorrow,1);
   
      }
      elseif(!$datereturntomorrow && $defupdatetomorrow && $defupdatetomorrow != '') {
        $newdeftomorrow = \Drupal::service('surveycampaign.twilio_coach')->load($form_state->getValue('surveycampaign_def_survey'),1,1,$defupdatetomorrow);
      }
      if($secnddatereturn && $secndformupdatetoday && $secndformupdatetoday != $secnddatereturn) {
      
          $altupdatetoday = \Drupal::service('surveycampaign.twilio_coach')->updateCampaignTime($secondaryid,$secnddatereturn,$secndformupdatetoday,0);
   
          }
      elseif(!$secnddatereturn && $secndformupdatetoday && $secndformupdatetoday != '') {
        $newalttoday = \Drupal::service('surveycampaign.twilio_coach')->load($form_state->getValue('surveycampaign_alt_survey'),2,0,$secndformupdatetoday);
      }
      if($secnddatereturntomorrow && $secndformupdatetomorrow != $secnddatereturntomorrow) {
  
        $altupdatetomorrow = \Drupal::service('surveycampaign.twilio_coach')->updateCampaignTime($secondaryid,$secnddatereturntomorrow,$secndformupdatetomorrow,1);
  
        
      }
      elseif(!$secnddatereturntomorrow && $secndformupdatetomorrow && $secndformupdatetomorrow != '') {
        $newalttomorrow = \Drupal::service('surveycampaign.twilio_coach')->load($form_state->getValue('surveycampaign_alt_survey'),2,1,$secndformupdatetomorrow);
      }
      

    parent::submitForm($form, $form_state); 
  }
  protected function timzoneAdjust($basetime){
    $senddate = new DateTime($basetime);
                $invitelink = $output->invitelink;
               
                switch($timezone) {
                    case 'ET':
                        $senddate = $senddate;
                
                        break;
                    case 'CT': 
                        $senddate = $senddate->modify("+ 1 hour");
                    break;
                    case 'MT':
                        $senddate = $senddate->modify("+ 2 hours");
                    break;
                    case 'PT':
                        $senddate = $senddate->modify("+ 3 hours");
                    break;
                    default:
                        $senddate = $senddate;
                    break;
                }
                   
                $senddate = $senddate->format('Y-m-d H:i:s');

  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['surveycampaign.settings'];
  }

}
