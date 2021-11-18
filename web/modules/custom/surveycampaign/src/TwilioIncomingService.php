<?php
namespace Drupal\surveycampaign;

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
         if($_REQUEST['Body']) {$bodytext = str_replace(' ', '',$_REQUEST['Body']);}

        if($_REQUEST && (strtoupper(str_replace(' ', '',$bodytext)) == 'STOP' || strtoupper($bodytext) == 'STOPALL' || strtoupper($bodytext) == 'UNSUBSCRIBE' || strtoupper($bodytext) == 'CANCEL' || strtoupper($bodytext) == 'END' || strtoupper($bodytext) == 'QUIT' )) {
            $userphone = substr($_REQUEST['From'],2);
            $setinactive = \Drupal::service('surveycampaign.survey_users')->setUserStatus($userphone,'2',3);
            $email = $setinactive[0];
            $firstname = $setinactive[1];
            $lastname = $setinactive[2];


            $sendemail = \Drupal::service('surveycampaign.twilio_coach')->twilioRespond($email,$firstname,$lastname,'stop');
        }
        elseif($_REQUEST && (strtoupper($bodytext) == 'START' || strtoupper($bodytext) == 'YES' || strtoupper($bodytext) == 'UNSTOP' )) {
            $userphone = substr($_REQUEST['From'],2);
            $setactive = \Drupal::service('surveycampaign.survey_users')->setUserStatus($userphone,'1',2);
            $email = $setactive[0];
            $firstname = $setactive[1];
            $lastname = $setactive[2];
            $sendemail = \Drupal::service('surveycampaign.twilio_coach')->twilioRespond($email,$firstname,$lastname,'start');
        }
      else return;
    }

}
