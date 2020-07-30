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
        $userphone = substr($_REQUEST['From'],2);
        if($_REQUEST && ($_REQUEST['Body'] == strtolower('stop') || $_REQUEST['Body'] == strtolower('stopall') || $_REQUEST['Body'] == strtolower('unsubscribe') || $_REQUEST['Body'] == strtolower('cancel') || $_REQUEST['Body'] == strtolower('end') || $_REQUEST['Body'] == strtolower('quit') )) {
            $setinactive = \Drupal::service('surveycampaign.survey_users')->setUserStatus($userphone,2);
            $email = $setinactive[0];
            $firstname = $setinactive[1];
            $lastname = $setinactive[2];


            $sendemail = \Drupal::service('surveycampaign.twilio_coach')->twilioRespond($email,$firstname,$lastname,'stop');
        }
        elseif($_REQUEST && ($_REQUEST['Body'] == strtolower('start') || $_REQUEST['Body'] == strtolower('yes') || $_REQUEST['Body'] == strtolower('unstop') )) {
            $setactive = \Drupal::service('surveycampaign.survey_users')->setUserStatus($userphone,1);
            $email = $setactive[0];
            $firstname = $setactive[1];
            $lastname = $setactive[2];
            $sendemail = \Drupal::service('surveycampaign.twilio_coach')->twilioRespond($email,$firstname,$lastname,'start');
        }
    }

}