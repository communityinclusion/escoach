<?php
namespace Drupal\surveycampaign;
// Get the PHP helper library from https://twilio.com/docs/libraries/php
require_once '/var/www/es_coach/vendor/autoload.php'; // Loads the library
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
        if($_REQUEST) print_r($_REQUEST);

        $sendemail = \Drupal::service('surveycampaign.twilio_coach')->twilioRespond('paul.foos@umb.edu');
    }

}