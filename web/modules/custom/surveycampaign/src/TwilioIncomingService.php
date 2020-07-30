<?php
namespace Drupal\surveycampaign;
// Get the PHP helper library from https://twilio.com/docs/libraries/php
require_once $_SERVER['SERVER_ADDR'] == '162.243.15.189' ? '/home/ici/escoach.communityinclusion.org/escoach/vendor/autoload.php' : '/var/www/es_coach/vendor/autoload.php';
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Twilio\Rest\Client;
use \DateTime;
use Drupal\Core\Mail\MailManagerInterface;
use Drupal\mailgun\MailgunHandlerInterface;
use Twilio\Twiml\MessagingResponse;
class TwilioIncomingService
{
    protected $entityTypeManager;
    public function __construct(EntityTypeManagerInterface $entity_type_manager)
    {
        $this->entityTypeManager = $entity_type_manager;
    }
    public function sendResponseMail() {
         //if($_REQUEST) $var = print_r($_REQUEST, true);
         //STOP, STOPALL, UNSUBSCRIBE, CANCEL, END, or QUIT
         //START, YES and UNSTOP
       
        if($_REQUEST && (strtoupper($_REQUEST['Body']) == 'STOP' || strtoupper($_REQUEST['Body']) == 'STOPALL' || strtoupper($_REQUEST['Body']) == 'UNSUBSCRIBE' || strtoupper($_REQUEST['Body']) == 'CANCEL' || strtoupper($_REQUEST['Body']) == 'END' || strtoupper($_REQUEST['Body']) == 'QUIT' )) { 
            $userphone = substr($_REQUEST['From'],2);
            $setinactive = \Drupal::service('surveycampaign.survey_users')->setUserStatus($userphone,'2');
            $email = $setinactive[0];
            $firstname = $setinactive[1];
            $lastname = $setinactive[2];


            $sendemail = \Drupal::service('surveycampaign.twilio_coach')->twilioRespond($email,$firstname,$lastname,'stop');
        }
        elseif($_REQUEST && (strtoupper($_REQUEST['Body']) == 'START' || strtoupper($_REQUEST['Body']) == 'YES' || strtoupper($_REQUEST['Body']) == 'UNSTOP' )) {
            $userphone = substr($_REQUEST['From'],2);
            $setactive = \Drupal::service('surveycampaign.survey_users')->setUserStatus($userphone,'1');
            $email = $setactive[0];
            $firstname = $setactive[1];
            $lastname = $setactive[2];
            $sendemail = \Drupal::service('surveycampaign.twilio_coach')->twilioRespond($email,$firstname,$lastname,'start');
        } 
      else return; 
    }

}