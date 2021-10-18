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

    $i = 0;
    $name_field = $form_state->get('num_hols');
    $config = $this->configFactory->get('surveycampaign.settings');
    $libconfig = $this->configFactory->get('surveycampaign.library_settings');
    $holdates = $config->get('def_holiday_date');
    $holnames = $config->get('def_holiday_name');
    $counthols = is_array($holdates) ? count($holdates) : 0;

    $defaultid = $config->get('defaultid');
    $secondaryid = $config->get('secondaryid');
    $datereturn = $this->formQuery('surveycampaign_campaigns','senddate',$defaultid,0);
    $datereturntomorrow = $this->formQuery('surveycampaign_campaigns','senddate',$defaultid,1);
    $secnddatereturn = $this->formQuery('surveycampaign_campaigns','senddate',$secondaryid,0);
    $secnddatereturntomorrow = $this->formQuery('surveycampaign_campaigns','senddate',$secondaryid,1);

    $form['#attached']['library'][] = 'admincss/csslib';
    $form['configuration'] = array(
      '#type' => 'vertical_tabs',
    );

    $form['configuration']['default_settings'] = array(
      '#type' => 'details',
      '#title' => t('Default survey settings'),
      '#group' => 'configuration',
    );
    $form['configuration']['default_settings']['survey_admin_mail'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Survey administrator email'),
      '#description' => $this->t('Automated emails from survey users will use this address'),
      '#default_value' => $config->get('survey_admin_mail'),
       '#size' => 60,
      '#maxlength' => 128,
      '#required' => TRUE,

    ];

    $form['configuration']['default_settings']['surveycampaign_def_survey'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Default survey id'),
      '#description' => $this->t('The default survey id number from SurveyGizmo'),
      '#default_value' => $defaultid,
       '#size' => 60,
      '#maxlength' => 128,
      '#required' => TRUE,

    ];
    $form['configuration']['default_settings']['default_disable'] = array(
      '#type' => 'radios',
      '#title' => t('Default survey Enable/disable'),
      '#description' => t('Send default survey or disable it.'),
      '#default_value' => $config->get('defaultenable'),
      '#options' => array(
        t('Enable'),
        t('Disable'),
      )
      );
      $form['configuration']['default_settings']['hour_range_low'] = [
        '#type' => 'select',
        '#title' => $this->t('Select earliest time to start random survey period. (The text message will be sent 1/2 hour after this time, at the end of the survey period.)'),
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

      $form['configuration']['default_settings']['hour_range_high'] = [
        '#type' => 'select',
        '#title' => $this->t('Select latest time to start random survey period'). (The text message will be sent 1/2 hour after this time, at the end of the survey period.),
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
      $form['configuration']['default_settings']['def_send_days'] = [
        '#type' => 'checkboxes',
        '#options' => ['Sunday' => $this->t('Sunday'), 'Monday' => $this->t('Monday'), 'Tuesday' => $this->t('Tuesday'), 'Wednesday' => $this->t('Wednesday'), 'Thursday' => $this->t('Thursday'), 'Friday' => $this->t('Friday'), 'Saturday' => $this->t('Saturday')],
        '#title' => $this->t('Days to send the default survey'),
        '#default_value' => $config->get('def_send_days'),

      ];

    $form['configuration']['default_settings']['shell'] = array(
      '#type' => 'fieldset',
      '#title' => t(''),
      '#tree' => TRUE,
    );

      $form['configuration']['default_settings']['shell']['holiday_fieldset'] = [
        '#type' => 'fieldset',
        '#title' => $this->t('Holiday suspension'),
        '#prefix' => "<div id='names-fieldset-wrapper'>",
        '#suffix' => '</div>',
      ];

      if (empty($name_field) || $counthols < 1) {
        $name_field = $counthols <= 1 ? $form_state->set('num_hols', 1) : $form_state->set('num_hols', $counthols);

      }


      for ($i = 0; $i < $form_state->get('num_hols'); $i++) {
        $thisdate = !empty($holdates) && $holdates[$i] ? $holdates[$i] : '';
        $thisname = !empty($holnames) && $holnames[$i] ? $holnames[$i] : '';
        $j = $i + 1;


        $form['configuration']['default_settings']['shell']['holiday_fieldset'][$i]['holiday_name'] = [
          '#type' => 'textfield',
          '#title' => $this->t('Holiday name'),
          '#maxlength' => 64,
          '#size' => 64,
          '#default_value' => $thisname,
          '#prefix' => "<div class='inner-fieldset'><legend><span class='fieldset-legend'>Holiday {$j}</span></legend>",
        ];
        $form['configuration']['default_settings']['shell']['holiday_fieldset'][$i]['holiday_date'] = [
          '#type' => 'date',
          '#title' => $this->t('Default survey: set a survey holiday.'),
          '#description' => t('Set a date in the future, holiday or otherwise, on which you wish the survey not to send.'),
          '#size' => 20,
          '#default_value' => $thisdate,
        ];

      }
        $form['configuration']['default_settings']['shell']['holiday_fieldset']['actions'] = [
          '#type' => 'actions',
        ];
        $form['configuration']['default_settings']['shell']['holiday_fieldset']['actions']['add_name'] = [
          '#type' => 'submit',
          '#value' => t('Add another holiday'),
          '#submit' => array('::addOne'),
          '#ajax' => [
            'callback' => '::addmoreCallback',
            'wrapper' => "names-fieldset-wrapper",
          ],
        ];
        if ($form_state->get('num_hols') > 1) {
          $form['configuration']['default_settings']['shell']['holiday_fieldset']['actions']['remove_name'] = [
            '#type' => 'submit',
            '#value' => t('Remove this holiday'),
            '#submit' => array('::removeCallback'),
            '#ajax' => [
              'callback' => '::addmoreCallback',
              'wrapper' => "names-fieldset-wrapper",
            ],
          ];
        }




    $form['configuration']['default_settings']['default_survey_todaytime'] = [
      '#type' => 'datetime',
      '#title' => $this->t('Default survey: set text send time of today\'s survey: format 00:00 (24 hour time). This is at the end of the half hour survey period.'),
      '#description' => t('This field will be populated automatically every day in early AM.  You can manually change the time of the survey here.  Don\'t change the date.'),
      '#size' => 20,
      // '#date_date_element' => 'none', // hide date element
      // '#date_time_element' => 'time', // you can use text element here as well

      '#default_value' => ($datereturn ? DrupalDateTime::createFromTimestamp(strtotime($datereturn)) : ""),
    ];
    $form['configuration']['default_settings']['default_survey_tomorrowtime'] = [
      '#type' => 'datetime',
      '#title' => $this->t('Default survey: Set text send time of tomorrow\'s survey: format 00:00 (24 hour time)'),

      '#description' => t('This field will be populated automatically tomorrow in early AM.  You can manually set the time of tomorrow\' survey here.  Only use tomorrow\'s date for now.'),
      '#size' => 20,
      // '#date_date_element' => 'none', // hide date element
      // '#date_time_element' => 'time', // you can use text element here as well

      '#default_value' => ($datereturntomorrow ? DrupalDateTime::createFromTimestamp(strtotime($datereturntomorrow)) : ""),
    ];
    $form['configuration']['default_settings']['first_text_body'] = [
      '#type' => 'text_format',
      '#title' => 'First text message body for default survey',
      '#description' => t('You can use these tokens to add personalized messages to the text: @name, @link, @starttime,@endtime'),
      '#format' => 'plain_text',
      '#default_value' => $config->get('first_text_body.value'),
      //'#format' => $config->get('first_text_body.format'),
    ];
    $form['configuration']['default_settings']['def_reminder_num'] = [
      '#type' => 'select',
      '#title' => $this->t('Number of reminders to follow up first default survey notification'),
      '#options' => [
        '0' => $this->t('0'),
        '1' => $this->t('1'),
        '2' => $this->t('2'),
      ],
      '#default_value' => $config->get('def_reminder_num'),
    ];
    $form['configuration']['default_settings']['def_survey_suspend_start_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('SG question id for default survey suspension start'),
      '#description' => $this->t('The question id from the default survey for the start date of a suspension.  Get this from the survey build mode.'),
      '#default_value' => $config->get('def_survey_suspend_start_id'),
       '#size' => 10,
      '#maxlength' => 10,
      '#required' => FALSE,

    ];

    $form['configuration']['default_settings']['def_survey_suspend_end_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('SG question id for default survey suspension end date'),
      '#description' => $this->t('The question id from the default survey for the end date of a suspension.  Get this from the survey build mode.'),
      '#default_value' => $config->get('def_survey_suspend_end_id'),
       '#size' => 10,
      '#maxlength' => 10,
      '#required' => FALSE,

    ];

    $form['configuration']['default_settings']['second_text_body'] = [
      '#type' => 'text_format',
      '#title' => 'First reminder text message body for default survey',
      '#description' => t('You can use these tokens to add personalized messages to the text: @name, @link, @starttime,@endtime'),
      '#format' => 'plain_text',
      '#default_value' => $config->get('second_text_body.value'),
     // '#format' => $config->get('second_text_body.format'),
    ];

    $form['configuration']['default_settings']['third_text_body'] = [
      '#type' => 'text_format',
      '#title' => 'Second reminder text message body for default survey',
      '#description' => t('You can use these tokens to add personalized messages to the text: @name, @link, @starttime,@endtime'),
      '#format' => 'plain_text',
      '#default_value' => $config->get('third_text_body.value'),
     // '#format' => $config->get('third_text_body.format'),
    ];



    $form['configuration']['default_settings']['def_warning_trigger'] = [
      '#type' => 'select',
      '#title' => $this->t('Select the number of days a user must be inactive to get a warning they will be deactivated'),
      '#options' => [
        '1' => $this->t('1'),
        '2' => $this->t('2'),
        '3' => $this->t('3'),
        '4' => $this->t('4'),
        '5' => $this->t('5'),
        '6' => $this->t('6'),
        '7' => $this->t('7'),
        '8' => $this->t('8'),
        '9' => $this->t('9'),
        '10' => $this->t('10'),
        '10' => $this->t('11'),
        '10' => $this->t('12'),
        '10' => $this->t('13'),
        '10' => $this->t('14'),
      ],
      '#default_value' => $config->get('def_warning_trigger'),
    ];
    $form['configuration']['default_settings']['def_inactive_trigger'] = [
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

    $form['configuration']['default_settings']['def_inactive_mode'] = [
      '#type' => 'select',
      '#title' => $this->t('Select mode by which the inactivity messages below will be received'),
      '#options' => [
        '0' => $this->t('Do not send'),
        '1' => $this->t('SMS text only'),
        '2' => $this->t('Email only'),
        '3' => $this->t('Email and SMS'),
      ],
      '#default_value' => $config->get('def_inactive_mode'),
    ];
    $form['configuration']['default_settings']['warning_text_body'] = [
      '#type' => 'text_format',
      '#title' => 'First reminder about non-response to survey',
      '#description' => t('You can use these tokens to add personalized messages to the text: @name,@warningdays, and @daystocutoff, @invitelink'),
      '#format' => 'plain_text',
      '#default_value' => $config->get('warning_text_body.value'),
     // '#format' => $config->get('warning_text_body.format'),
    ];

    $form['configuration']['default_settings']['cutoff_text_body'] = [
      '#type' => 'text_format',
      '#title' => 'Second notice about non-response to survey, with cutoff of delivery',
      '#description' => t('You can use these tokens to add personalized messages to the text: @name,@cutoffdays'),
      '#format' => 'plain_text',
      '#default_value' => $config->get('cutoff_text_body.value'),
     // '#format' => $config->get('cutoff_text_body.format'),
    ];



    $form['configuration']['default_settings']['sg_clos_ques_id'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Question ID of closing question from SG'),
      '#description' => $this->t('Look at the closing page question in Surveygizmo in build mode.  There should be an id number.'),
      '#default_value' => $libconfig->get('sg_clos_ques_id'),
        '#size' => 5,
      '#maxlength' => 128,
      '#required' => TRUE,

    );
    $form['configuration']['default_settings']['sg_clos_page_id'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Page ID of closing page from SG'),
      '#description' => $this->t('Look at the closing page in Surveygizmo in build mode.  If you mouse over the closing page edit symbol it will give you an sid number  That is the page id.'),
      '#default_value' => $libconfig->get('sg_clos_page_id'),
        '#size' => 5,
      '#maxlength' => 128,
      '#required' => TRUE,

    );
    $form['configuration']['default_settings']['finalpageheading'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Default final screen heading'),
      '#description' => $this->t('Default final screen heading if no library item chosen'),
      '#required' => FALSE,
      '#default_value' => $libconfig->get('finalpageheading'),
    );

    $form['configuration']['default_settings']['defaultlibrarytext'] = array(
      '#type' => 'text_format',
      '#title' => $this->t('Default final screen text'),
      '#description' => $this->t('Default final screen body text if no library item chosen.'),
      '#required' => TRUE,
      '#default_value' => $libconfig->get('defaultlibrarytext.value'),


      '#format' => $config->get('full_html'),

    );
    $form['configuration']['default_settings']['defaultfootertext'] = array(
      '#type' => 'text_format',
      '#title' => $this->t('Default final screen footer'),
      '#description' => $this->t('Default final screen footer if footer not disabled in library item'),
      '#required' => TRUE,
      '#default_value' => $libconfig->get('defaultfootertext.value'),


      '#format' => $config->get('full_html'),

    );





    $form['configuration']['second_settings'] = array(
      '#type' => 'details',
      '#title' => t('Secondary survey settings'),
      //'#collapsible' => TRUE,
      '#group' => 'configuration',
    );

    $form['configuration']['second_settings']['surveycampaign_alt_survey'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Secondary survey id'),
      '#description' => $this->t('The secondary survey id number from SurveyGizmo'),
      '#default_value' => $config->get('secondaryid'),
       '#size' => 60,
      '#maxlength' => 128,
      '#required' => FALSE,

    ];
    $form['configuration']['second_settings']['second_disable'] = array(
      '#type' => 'radios',
      '#title' => t('Secondary survey Enable/disable'),
      '#description' => t('Send secondary survey or disable it.'),
      '#default_value' => $config->get('secondenable'),
      '#options' => array(
        t('Enable'),
        t('Disable'),
      )
    );
    $form['configuration']['second_settings']['alt_repeat'] = array(
      '#type' => 'radios',
      '#title' => t('Secondary survey Repeating (daily) or One-time completion (e.g. baseline)'),
      '#description' => t('Set secondary survey to require daily completion, or one-time completion. If repeating users will get daily reminders to complete the survey every day.  If one-time, users will get daily reminders to complete the survey until they have completed it once, then no more reminders.'),
      '#default_value' => $config->get('alt_repeat'),
      '#options' => array(
        t('One-time (one-time campaign links will remain open until the survey is shut down.'),
        t('Daily (repeating)'),
      )
    );
    $form['configuration']['second_settings']['alt_delay_period'] = [
      '#type' => 'select',
      '#title' => $this->t('Secondary survey delay: Select the number of times a user must have responded to the daily survey before they will get the one-time survey. Must be a one-time survey.'),
      '#description' => $this->t('This setting will prevent new users from getting the follow-up survey on the designated day if they have not answered the daily survey x number of times. New users don\'t have any basis to evaluate the daily survey yet.'),
      '#options' => [
        '0' => $this->t('0'),
          '1' => $this->t('1'),
        '2' => $this->t('2'),
        '3' => $this->t('3'),
        '4' => $this->t('4'),
        '5' => $this->t('5'),
        '6' => $this->t('6'),
        '7' => $this->t('7'),
        '8' => $this->t('8'),
        '9' => $this->t('9'),
        '10' => $this->t('10'),
        '11' => $this->t('11'),
        '12' => $this->t('12'),
        '13' => $this->t('13'),
        '14' => $this->t('14'),
        '15' => $this->t('15'),
        '16' => $this->t('16'),
        '17' => $this->t('17'),
        '18' => $this->t('18'),
        '19' => $this->t('19'),
        '20' => $this->t('20'),
      ],
      '#default_value' => $config->get('alt_delay_period'),
    ];
    $form['configuration']['second_settings']['alt_hour_range_low'] = [
      '#type' => 'select',
      '#title' => $this->t('Select earliest time to send survey (1/2 hour before message sends). (The text message will be sent 1/2 hour after this time, at the end of the survey period.)'),
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
      '#default_value' => $config->get('alt_hour_range_low'),
    ];

    $form['configuration']['second_settings']['alt_hour_range_high'] = [
      '#type' => 'select',
      '#title' => $this->t('Select latest time for survey period (1/2 hour before message sends). (The text message will be sent 1/2 hour after this time, at the end of the survey period.)'),
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
      '#default_value' => $config->get('alt_hour_range_high'),
    ];
    $form['configuration']['second_settings']['alt_send_days'] = [
      '#type' => 'checkboxes',
      '#options' => ['Sunday' => $this->t('Sunday'), 'Monday' => $this->t('Monday'), 'Tuesday' => $this->t('Tuesday'), 'Wednesday' => $this->t('Wednesday'), 'Thursday' => $this->t('Thursday'), 'Friday' => $this->t('Friday'), 'Saturday' => $this->t('Saturday')],
      '#title' => $this->t('Days to send the secondary survey'),
      '#default_value' => $config->get('alt_send_days'),

    ];

    $form['configuration']['second_settings']['secondary_survey_todaytime'] = [
      '#type' => 'datetime',
      '#title' => $this->t('Secondary survey: set text send time of today\'s survey: format 00:00 (24 hour time)'),
      '#description' => t('This field will be populated automatically every day in early AM if the secondary survey is enabled.  You can manually change the time of the survey here.  Don\'t change the date.'),
      '#size' => 20,
      // '#date_date_element' => 'none', // hide date element
      // '#date_time_element' => 'time', // you can use text element here as well

      '#default_value' => ($secnddatereturn ? DrupalDateTime::createFromTimestamp(strtotime($secnddatereturn)) : ""),
    ];


    $form['configuration']['second_settings']['secondary_survey_tomorrowtime'] = [
      '#type' => 'datetime',
      '#title' => $this->t('Secondary survey: Set text send time of tomorrow\'s survey: format 00:00 (24 hour time)'),
      '#size' => 20,
      '#description' => t('This field will be populated automatically tomorrow in early AM.  You can manually set the time of tomorrow\' survey here.  Only use tomorrow\'s date for now.'),
      // '#date_date_element' => 'none', // hide date element
      // '#date_time_element' => 'time', // you can use text element here as well

      '#default_value' => ($secnddatereturntomorrow ? DrupalDateTime::createFromTimestamp(strtotime($secnddatereturntomorrow)) : ""),
    ];
    $form['configuration']['second_settings']['alt_first_text_body'] = [
      '#type' => 'text_format',
      '#title' => 'First text message body for secondary survey',
      '#description' => t('You can use these tokens to add personalized messages to the text: @name, @link, @starttime,@endtime'),
      '#format' => 'plain_text',
      '#default_value' => $config->get('alt_first_text_body.value'),
      //'#format' => $config->get('alt_first_text_body.format'),
    ];
    $form['configuration']['second_settings']['secondary_reminder_num'] = [
      '#type' => 'select',
      '#title' => $this->t('Number of reminders to follow up first secondary survey notification'),
      '#options' => [
        '0' => $this->t('0'),
        '1' => $this->t('1'),
        '2' => $this->t('2'),
      ],
      '#default_value' => $config->get('secondary_reminder_num'),
    ];

    $form['configuration']['second_settings']['alt_second_text_body'] = [
      '#type' => 'text_format',
      '#title' => 'First reminder text message body for secondary survey',
      '#description' => t('You can use these tokens to add personalized messages to the text: @name, @link, @starttime,@endtime'),
      '#format' => 'plain_text',
      '#default_value' => $config->get('alt_second_text_body.value'),
     // '#format' => $config->get('alt_second_text_body.format'),
    ];

    $form['configuration']['second_settings']['alt_third_text_body'] = [
      '#type' => 'text_format',
      '#title' => 'Second reminder text message body for secondary survey',
      '#description' => t('You can use these tokens to add personalized messages to the text: @name, @link, @starttime,@endtime'),
      '#format' => 'plain_text',
      '#default_value' => $config->get('alt_third_text_body.value'),
     // '#format' => $config->get('alt_third_text_body.format'),
    ];



    $form['configuration']['second_settings']['alt_sg_clos_ques_id'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Question ID of closing question from SG'),
      '#description' => $this->t('Look at the closing page question in Surveygizmo in build mode.  There should be an id number.'),
      '#default_value' => $libconfig->get('alt_sg_clos_ques_id'),
        '#size' => 5,
      '#maxlength' => 128,
      '#required' => TRUE,

    );
    $form['configuration']['second_settings']['alt_sg_clos_page_id'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Page ID of closing page from SG'),
      '#description' => $this->t('Look at the closing page in Surveygizmo in build mode.  If you mouse over the closing page edit symbol it will give you an sid number  That is the page id.'),
      '#default_value' => $libconfig->get('alt_sg_clos_page_id'),
        '#size' => 5,
      '#maxlength' => 128,
      '#required' => TRUE,

    );
    $form['configuration']['second_settings']['alt_finalpageheading'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Default final screen heading'),
      '#description' => $this->t('Default final screen heading if no library item chosen'),
      '#required' => FALSE,
      '#default_value' => $libconfig->get('alt_finalpageheading'),
    );

    $form['configuration']['second_settings']['alt_defaultlibrarytext'] = array(
      '#type' => 'text_format',
      '#title' => $this->t('Default final screen text for alternate survey'),
      '#description' => $this->t('Default final screen body text if no library item chosen.'),
      '#required' => TRUE,
      '#default_value' => $libconfig->get('alt_defaultlibrarytext.value'),


      '#format' => $config->get('full_html'),

    );

    $form['configuration']['second_settings']['alt_defaultfootertext'] = array(
      '#type' => 'text_format',
      '#title' => $this->t('Default final screen footer for alt survey'),
      '#description' => $this->t('Default final screen footer for alt survey if footer not disabled in library item'),
      '#required' => TRUE,
      '#default_value' => $libconfig->get('alt_defaultfootertext.value'),


      '#format' => $config->get('full_html'),

    );



    $form_state->setCached(FALSE);
    return parent::buildForm($form, $form_state);
  }
   /**
   * Callback for both ajax-enabled buttons.
   *
   * Selects and returns the fieldset with the names in it.
   */
  public function addmoreCallback(array &$form, FormStateInterface $form_state) {
    $name_field = $form_state->get('num_hols');
    return $form['configuration']['default_settings']['shell']['holiday_fieldset'];
  }

  /**
   * Submit handler for the "add-one-more" button.
   *
   * Increments the max counter and causes a rebuild.
   */
  public function addOne(array &$form, FormStateInterface $form_state) {
    $name_field = $form_state->get('num_hols');
    $add_button = $name_field + 1;
    $form_state->set('num_hols', $add_button);
    $form_state->setRebuild();
  }

  /**
   * Submit handler for the "remove one" button.
   *
   * Decrements the max counter and causes a form rebuild.
   */
  public function removeCallback(array &$form, FormStateInterface $form_state) {
    $name_field = $form_state->get('num_hols');
    if ($name_field > 1) {
      $remove_button = $name_field - 1;
      $form_state->set('num_hols', $remove_button);
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
    $namearray = array();
    $holarray = array();
    foreach ($form_state->getValue(array('shell','holiday_fieldset')) as $key => $value) {
      if(is_numeric($key)) $namearray[]= $form_state->getValue(array('shell','holiday_fieldset',$key, 'holiday_name'));
      if(is_numeric($key)) $holarray[]= $form_state->getValue(array('shell','holiday_fieldset',$key, 'holiday_date'));
    }
    $config = $this->configFactory->get('surveycampaign.settings');

    $this->configFactory->getEditable('surveycampaign.settings')
      ->set('defaultid', $form_state->getValue('surveycampaign_def_survey'))
      ->set('defaultenable', $form_state->getValue('default_disable'))
      ->set('secondaryid', $form_state->getValue('surveycampaign_alt_survey'))
      ->set('secondenable', $form_state->getValue('second_disable'))
      ->set('alt_repeat', $form_state->getValue('alt_repeat'))
      ->set('hour_range_low', $form_state->getValue('hour_range_low'))
      ->set('hour_range_high', $form_state->getValue('hour_range_high'))
        ->set('alt_delay_period', $form_state->getValue('alt_delay_period'))
      ->set('alt_hour_range_low', $form_state->getValue('alt_hour_range_low'))
      ->set('alt_hour_range_high', $form_state->getValue('alt_hour_range_high'))
      ->set('def_send_days', $form_state->getValue('def_send_days'))
      ->set('alt_send_days', $form_state->getValue('alt_send_days'))
      ->set('def_warning_trigger', $form_state->getValue('def_warning_trigger'))
      ->set('def_inactive_mode', $form_state->getValue('def_inactive_mode'))
      ->set('def_inactive_trigger', $form_state->getValue('def_inactive_trigger'))
      ->set('first_text_body', $form_state->getValue('first_text_body'))
      ->set('second_text_body', $form_state->getValue('second_text_body'))
      ->set('third_text_body', $form_state->getValue('third_text_body'))
      ->set('warning_text_body', $form_state->getValue('warning_text_body'))
      ->set('cutoff_text_body', $form_state->getValue('cutoff_text_body'))
      ->set('alt_first_text_body', $form_state->getValue('alt_first_text_body'))
      ->set('alt_second_text_body', $form_state->getValue('alt_second_text_body'))
      ->set('alt_third_text_body', $form_state->getValue('alt_third_text_body'))
      ->set('survey_admin_mail', $form_state->getValue('survey_admin_mail'))
      ->set('def_reminder_num',$form_state->getValue('def_reminder_num'))
      ->set('secondary_reminder_num',$form_state->getValue('secondary_reminder_num'))
      ->set('def_survey_suspend_start_id',$form_state->getValue('def_survey_suspend_start_id'))
      ->set('def_survey_suspend_end_id',$form_state->getValue('def_survey_suspend_end_id'))
      //->set('alt_survey_suspend_start_id',$form_state->getValue('alt_survey_suspend_start_id'))
      //->set('alt_survey_suspend_end_id',$form_state->getValue('alt_survey_suspend_end_id'))
      ->set('def_holiday_name',$namearray)
      ->set('def_holiday_date',$holarray)
      ->save();
     $this->configFactory->getEditable('surveycampaign.library_settings')
        ->set('defaultid', $form_state->getValue('libsg_def_survey'))
        ->set('sg_clos_ques_id', $form_state->getValue('sg_clos_ques_id'))
        ->set('sg_clos_page_id', $form_state->getValue('sg_clos_page_id'))
        ->set('alt_sg_clos_ques_id', $form_state->getValue('alt_sg_clos_ques_id'))
        ->set('alt_sg_clos_page_id', $form_state->getValue('alt_sg_clos_page_id'))
        ->set('finalpageheading', $form_state->getValue('finalpageheading'))
        ->set('defaultlibrarytext', $form_state->getValue('defaultlibrarytext'))
        ->set('defaultfootertext', $form_state->getValue('defaultfootertext'))
        ->set('alt_finalpageheading', $form_state->getValue('alt_finalpageheading'))
        ->set('alt_defaultlibrarytext', $form_state->getValue('alt_defaultlibrarytext'))
        ->set('alt_defaultfootertext', $form_state->getValue('alt_defaultfootertext'))
        ->save();
      //Future: this is how you remove a single value in an array
      //$this->configFactory()->getEditable('surveycampaign.settings')->clear('def_holiday_date.1')->save();
      //$this->configFactory()->getEditable('surveycampaign.settings')->clear('def_holiday_name.1')->save();



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
    return ['surveycampaign.settings','surveycampaign.library_settings'];
  }

}
