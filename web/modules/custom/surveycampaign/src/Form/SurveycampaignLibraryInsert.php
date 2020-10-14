<?php

namespace Drupal\surveycampaign\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\State\StateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Datetime\DrupalDateTime;
use \DateTime;


/**
 * Configuration form definition for the survey interactions.
 */
class SurveycampaignLibraryInsert extends ConfigFormBase {

    /**
     * @var \Drupal\Core\Logger\LoggerChannelInterface
     */
    protected $logger;
  
    /**
     * SurveycampaignConfigurationForm constructor.
     *
     * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
     *   The factory for configuration objects.
     * @param \Drupal\Core\Logger\LoggerChannelInterface $logger
     *   The logger.
     */
    public function __construct(ConfigFactoryInterface $config_factory, LoggerChannelInterface $logger) {
      parent::__construct($config_factory);
      $this->logger = $logger;
    }
  
    /**
     * {@inheritdoc}
     */
    public static function create(ContainerInterface $container) {
      return new static(
        $container->get('config.factory'),
        $container->get('surveycampaign.logger.channel.surveycampaign')
      );
    }
  
  
    /**
     * {@inheritdoc}
     */
    protected function getEditableConfigNames() {
      return ['surveycampaign.library_settings'];
    }
  
    /**
     * {@inheritdoc}
     */
    public function getFormId() {
      return 'surveymessages_configuration_form';
    }
  
    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state) {
      $newdate = new DateTime();
      $todaydate = $newdate->format('Y-m-d H:i:s');
      $i = 0;
      $config = $this->config('surveycampaign.library_settings');
      $mainconfig = $this->config('surveycampaign.settings');
      $defaultid = $config->get('defaultid') || $config->get('defaultid') != "" ? $config->get('defaultid') : $mainconfig->get('defaultid');
      $nids = \Drupal::entityQuery('node')->condition('type','library_item')->execute();
      $nodes =  \Drupal\node\Entity\Node::loadMultiple($nids);
      $types = \Drupal::entityTypeManager()
        ->getStorage('node')
        ->loadMultiple();
      $database = \Drupal::database();
      $query = $database->select('surveycampaign_library_insert','sli')
        ->fields('sli', array('ID','nodeid','pagetitle','senddate','ordering','titlechoice'))
        ->condition('sli.surveyid', $defaultid)
        ->condition('sli.senddate', $todaydate ,'>=')
        ->orderBy('senddate', 'DESC');;
        $result = $query->execute();
        $result2 = $query->execute();
       
        
        $row = $result2->fetchAll();
        $countlibitems = count($row);
      $formarray = array();
      while($record = $result->fetchAssoc()) {
       
        $formarray[]= $record;
      }

      $name_field = $form_state->get('num_libs');
      foreach($nodes as $node) {
       $libraryitems[$node->id()] = $node->label();
      } 
      $form['libsg_def_survey'] = array(
          '#type' => 'textfield',
          '#title' => $this->t('Default survey id'),
          '#description' => $this->t('The default survey id number from SurveyGizmo'),
          '#default_value' => $defaultid,
           '#size' => 10,
          '#maxlength' => 128,
          '#required' => TRUE,
          
        );
      $form['sg_clos_ques_id'] = array(
        '#type' => 'textfield',
        '#title' => $this->t('Question ID of closing question from SG'),
        '#description' => $this->t('Look at the closing page question in Surveygizmo in build mode.  There should be an id number.'),
        '#default_value' => $config->get('sg_clos_ques_id'),
          '#size' => 5,
        '#maxlength' => 128,
        '#required' => TRUE,
        
      );
      $form['sg_clos_page_id'] = array(
        '#type' => 'textfield',
        '#title' => $this->t('Page ID of closing page from SG'),
        '#description' => $this->t('Look at the closing page in Surveygizmo in build mode.  If you mouse over the closing page edit symbol it will give you an sid number  That is the page id.'),
        '#default_value' => $config->get('sg_clos_page_id'),
          '#size' => 5,
        '#maxlength' => 128,
        '#required' => TRUE,
        
      );
      $form['shell'] = array(
        '#type' => 'fieldset',
        '#title' => t(''),
        '#tree' => TRUE,
      );

      $form['shell']['libchoice_fieldset'] = [
        '#type' => 'fieldset',
        '#title' => $this->t('Choose library items for final question'),
        '#prefix' => "<div id='names-fieldset-wrapper'>",
        '#suffix' => '</div>',
      ];
  
      if (empty($name_field) || $countlibitems < 1) {
        $name_field = $countlibitems <= 1 ? $form_state->set('num_libs', 1) : $form_state->set('num_libs', $countlibitems);

      }
      for ($i = 0; $i < $form_state->get('num_libs'); $i++) {
        $thisdate = substr($formarray[$i]['senddate'],0,10);
        $thistext = $formarray[$i]['nodeid'];
        $thisheading = $formarray[$i]['titlechoice'];
        $thiscustom = $formarray[$i]['pagetitle'] ? $formarray[$i]['pagetitle'] : null;
        $thisrow = $formarray[$i]['ID'] ? $formarray[$i]['ID'] : null;
        $j = $i + 1;
        
        
        
        
        $form['shell']['libchoice_fieldset'][$i]['library_choices'] = array(
          '#type' => 'radios',
          '#title' => 'Library items',
          '#description' => 'Select a library item to use on closing screen',
          '#default_value' => $thistext,
          '#required' => TRUE,
          '#options' => $libraryitems,
          '#prefix' => "<div class='inner-fieldset'><legend><span class='fieldset-legend'>Library Choice {$j}</span></legend>",
        );
          $form['shell']['libchoice_fieldset'][$i]['library_date'] = array(
            '#type' => 'date',
            '#title' => $this->t('Default survey: set a date to insert library item.'),
            '#description' => t('Set a date in the future, on which the library item above will be inserted in the final question.'),
            '#size' => 20,
            '#required' => TRUE,
            '#default_value' => $thisdate,
          );
          $form['shell']['libchoice_fieldset'][$i]['pageheadingchoice'] = array(
            '#type' => 'radios',
            '#title' => $this->t('Choose heading for final screen'),
            '#options' => array(2 => $this->t('Use the library item title'), 4 => $this->t('Add a custom heading below')),
            '#description' => $this->t('You can change the heading for the final screen or just use the title of the library item.'),
            '#default_value' => $thisheading,
            '#required' => TRUE,
            '#attributes' => array('class' => array('toggleHeading')),
          );
          $form['shell']['libchoice_fieldset'][$i]['custompageheading'] = array(
            '#type' => 'textfield',
            '#title' => $this->t('Customize the final screen heading'),
            '#description' => $this->t('Choose a heading different from the library item title'),
            '#default_value' => $thiscustom,
            '#attributes' => array('class' => array('customHeading','hideOption')),
          );
          $form['shell']['libchoice_fieldset'][$i]['row_ID'] = array(
            '#type' => 'hidden',
            '#default_value' => $thisrow,
          );
        
      }
        $form['shell']['libchoice_fieldset']['actions'] = [
          '#type' => 'actions',
        ];
        $form['shell']['libchoice_fieldset']['actions']['add_name'] = [
          '#type' => 'submit',
          '#value' => t('Add another library choice/date'),
          '#submit' => array('::addOne'),
          '#ajax' => [
            'callback' => '::addmoreCallback',
            'wrapper' => "names-fieldset-wrapper",
          ],
        ];
        if ($form_state->get('num_libs') > 1) {
          $form['shell']['libchoice_fieldset']['actions']['remove_name'] = [
            '#type' => 'submit',
            '#value' => t('Remove this library choice/date'),
            '#submit' => array('::removeCallback'),
            '#ajax' => [
              'callback' => '::addmoreCallback',
              'wrapper' => "names-fieldset-wrapper",
            ],
          ];
        }


        
      
  
      $form['finalpageheading'] = array(
        '#type' => 'textfield',
        '#title' => $this->t('Default final screen heading'),
        '#description' => $this->t('Default final screen heading if no library item chosen'),
        '#required' => TRUE,
        '#default_value' => $config->get('finalpageheading'),
      );

       

      $form['defaultlibrarytext'] = array(
        '#type' => 'text_format',
        '#title' => $this->t('Default final screen text'),
        '#description' => $this->t('Default final screen body text if no library item chosen.'),
        '#required' => TRUE,
        '#default_value' => $config->get('defaultlibrarytext.value'),
        
        
        '#format' => $config->get('first_text_body.format'),
      
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
      $name_field = $form_state->get('num_libs');
      return $form['shell']['libchoice_fieldset'];
    }

  /**
   * Submit handler for the "add-one-more" button.
   *
   * Increments the max counter and causes a rebuild.
   */
  public function addOne(array &$form, FormStateInterface $form_state) {
    $name_field = $form_state->get('num_libs');
    $add_button = $name_field + 1;
    $countlibitems = $name_field + 1;
    $form_state->set('num_libs', $add_button);
    $form_state->setRebuild();
  }

  /**
   * Submit handler for the "remove one" button.
   *
   * Decrements the max counter and causes a form rebuild.
   */
  public function removeCallback(array &$form, FormStateInterface $form_state) {
    $name_field = $form_state->get('num_libs');
    if ($name_field > 1) {
      $remove_button = $name_field - 1;
      $countlibitems = $name_field - 1;
      $form_state->set('num_libs', $remove_button);
    }
    $form_state->setRebuild();
  }

  
    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state) {
     
      $idarray = array();
      $surveyid = $form_state->getValue('libsg_def_survey');
      foreach ($form_state->getValue(array('shell','libchoice_fieldset')) as $key1 => $value1) {
        if(is_numeric($key1)) $rowid1 = $form_state->getValue(array('shell','libchoice_fieldset',$key1, 'row_ID')) ? $form_state->getValue(array('shell','libchoice_fieldset',$key1, 'row_ID')) : null;
        if($rowid1) $idarray[]= $rowid1;
      }
      if (!empty($idarray)) $this->itemCleaning($idarray);

      foreach ($form_state->getValue(array('shell','libchoice_fieldset')) as $key => $value) {
        if(is_numeric($key)) $nid = $form_state->getValue(array('shell','libchoice_fieldset',$key, 'library_choices'));
        
        if(is_numeric($key)) $date = $form_state->getValue(array('shell','libchoice_fieldset',$key, 'library_date'));
        if(is_numeric($key)) $titlechoice = $form_state->getValue(array('shell','libchoice_fieldset',$key, 'pageheadingchoice'));
        if(is_numeric($key) && $form_state->getValue(array('shell','libchoice_fieldset',$key, 'pageheadingchoice')) == '4') {$pagetitle = $form_state->getValue(array('shell','libchoice_fieldset',$key, 'custompageheading'));} 
        else { $pagetitle = ""; }
        if(is_numeric($key)) $rowid = $form_state->getValue(array('shell','libchoice_fieldset',$key, 'row_ID')) ? $form_state->getValue(array('shell','libchoice_fieldset',$key, 'row_ID')) : null;
        if(is_numeric($key)) $this->manageLibraryItem($nid,$surveyid,$date,$titlechoice,$pagetitle,$rowid);

      }
      $this->config('surveycampaign.library_settings')
        ->set('defaultid', $form_state->getValue('libsg_def_survey'))
        ->set('sg_clos_ques_id', $form_state->getValue('sg_clos_ques_id'))
        ->set('sg_clos_page_id', $form_state->getValue('sg_clos_page_id'))
        ->set('defaultlibrarytext', $form_state->getValue('defaultlibrarytext'))
        ->set('finalpageheading', $form_state->getValue('finalpageheading'))
        //->set('library_choice_text', $textarray)
        //->set('library_choice_dates', $datearray)
        //->set('library_choice_headings', $headingarray)
        //->set('library_choice_custom_headings', $customheadingsarray)
        ->save();
  
      parent::submitForm($form, $form_state);
  
      // $this->logger->info('The default final page heading is @message.', ['@message' => $form_state->getValue('finalpageheading')]);
    }
    function manageLibraryItem($nid,$surveyid,$date,$titlechoice,$pagetitle,$rowid) {
      
      $database = \Drupal::database();
      $result = null;
      // select query to see if row in library insert table exists
      if($rowid) {
        $result = $database->select('surveycampaign_library_insert','sli')
          ->fields('sli', array('ID'))
          ->condition('sli.ID', $rowid)
        ->countQuery()
        ->execute()
        ->fetchField();
      }
       
      if ($result && $result > 0) {
        //if entry already exists update
        $database->update('surveycampaign_library_insert')
        ->fields([
          'titlechoice' => $titlechoice,
          'pagetitle' => $pagetitle,
          'senddate' => $date,
          'nodeid' => $nid,
        ])
        ->condition('ID', $rowid)
        ->execute();
      } else {
        $insertdatebase = new DateTime("$date");
        $insertdate = $insertdatebase->format('Y-m-d H:i:s');
    
        //insert
        $database->insert('surveycampaign_library_insert')
            ->fields([
                'surveyid' => $surveyid,
                'nodeid' => $nid,
                'senddate' => $insertdate,
                'pagetitle' => $pagetitle,
                'titlechoice' => $titlechoice,
            ]) ->execute();
      }
      

      
      
    }
    function itemCleaning($idarray) {
      $database = \Drupal::database();
      $database->delete('surveycampaign_library_insert')
      ->condition('ID', $idarray, 'NOT IN')
      ->execute();
    }
    
    
  
  }