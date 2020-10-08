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
      $i = 0;
      $name_field = $form_state->get('num_libs');
      $config = $this->config('surveycampaign.library_settings');
      $nids = \Drupal::entityQuery('node')->condition('type','library_item')->execute();
      $nodes =  \Drupal\node\Entity\Node::loadMultiple($nids);
      $types = \Drupal::entityTypeManager()
        ->getStorage('node')
        ->loadMultiple();

      foreach($nodes as $node) {
       $libraryitems[$node->id()] = $node->label();
      }    
      $librarydates = $config->get('library_choice_dates');
      $librarytext = $config->get('library_choice_text');
      $libraryheadings = $config->get('library_choice_headings');
      $librarycustomheds =  $config->get('library_choice_custom_headings');
      $countlibitems = is_array($librarydates) ? count($librarydates) : 0;
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
        $thisdate = !empty($librarydates) && $librarydates[$i] ? $librarydates[$i] : '';
        $thistext = !empty($librarytext) && $librarytext[$i] ? $librarytext[$i] : '';
        $thisheading = !empty($librarytext) && $librarytext[$i] ? $libraryheadings[$i] : '';
        $thiscustom = !empty($librarytext) && $librarytext[$i] ? $librarycustomheds[$i] : '';
        $j = $i + 1;
        
        

        
        $form['shell']['libchoice_fieldset'][$i]['library_choices'] = array(
          '#type' => 'radios',
          '#title' => 'Library items',
          '#description' => 'Select a library item to use on closing screen',
          '#default_value' => $thistext,
          '#options' => $libraryitems,
          '#prefix' => "<div class='inner-fieldset'><legend><span class='fieldset-legend'>Library Choice {$j}</span></legend>",
        );
          $form['shell']['libchoice_fieldset'][$i]['library_date'] = array(
            '#type' => 'date',
            '#title' => $this->t('Default survey: set a date to insert library item.'),
            '#description' => t('Set a date in the future, on which the library item above will be inserted in the final question.'),
            '#size' => 20,
            '#default_value' => $thisdate,
          );
          $form['shell']['libchoice_fieldset'][$i]['pageheadingchoice'] = array(
            '#type' => 'radios',
            '#title' => $this->t('Choose heading for final screen'),
            '#options' => array(2 => $this->t('Use the library item title'), 4 => $this->t('Add a custom heading below')),
            '#description' => $this->t('You can change the heading for the final screen or just use the title of the library item.'),
            '#default_value' => $thisheading,
            '#attributes' => array('class' => array('toggleHeading')),
          );
          $form['shell']['libchoice_fieldset'][$i]['custompageheading'] = array(
            '#type' => 'textfield',
            '#title' => $this->t('Customize the final screen heading'),
            '#description' => $this->t('Choose a heading different from the library item title'),
            '#default_value' => $thiscustom,
            '#attributes' => array('class' => array('customHeading','hideOption')),
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
        '#default_value' => $config->get('finalpageheading'),
      );

       

      $form['defaultlibrarytext'] = array(
        '#type' => 'textarea',
        '#title' => $this->t('Default final screen text'),
        '#description' => $this->t('Default final screen body text if no library item chosen.'),
        '#default_value' => $config->get('defaultlibrarytext'),
      
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
      $form_state->set('num_libs', $remove_button);
    }
    $form_state->setRebuild();
  }

  
    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state) {
      $textarray = array();
      $datearray = array();
      $headingarray = array();
      $customheadingsarray = array();
      foreach ($form_state->getValue(array('shell','libchoice_fieldset')) as $key => $value) {
        if(is_numeric($key)) $textarray[]= $form_state->getValue(array('shell','libchoice_fieldset',$key, 'library_choices'));
        if(is_numeric($key)) $datearray[]= $form_state->getValue(array('shell','libchoice_fieldset',$key, 'library_date'));
        if(is_numeric($key)) $headingarray[]= $form_state->getValue(array('shell','libchoice_fieldset',$key, 'pageheadingchoice'));
        if(is_numeric($key)) $customheadingsarray[]= $form_state->getValue(array('shell','libchoice_fieldset',$key, 'custompageheading'));
      }
      $this->config('surveycampaign.library_settings')
        ->set('defaultlibrarytext', $form_state->getValue('defaultlibrarytext'))
        ->set('finalpageheading', $form_state->getValue('finalpageheading'))
        ->set('library_choice_text', $textarray)
        ->set('library_choice_dates', $datearray)
        ->set('library_choice_headings', $headingarray)
        ->set('library_choice_custom_headings', $customheadingsarray)
        ->save();
  
      parent::submitForm($form, $form_state);
  
      $this->logger->info('The default final page heading is @message.', ['@message' => $form_state->getValue('finalpageheading')]);
    }
    
    
  
  }