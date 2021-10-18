<?php
namespace Drupal\surveycampaign;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Twilio\Rest\Client;
use \DateTime;
use Drupal\Core\Mail\MailManagerInterface;
use Drupal\mailgun\MailgunHandlerInterface;
use Drupal\node\Entity\Node;
class TwilioCoachService
{
    protected $entityTypeManager;
    public function __construct(EntityTypeManagerInterface $entity_type_manager)
    {
        $this->entityTypeManager = $entity_type_manager;
    }
    public function load($surveyid,$type = 1,$day = 0,$fixdate = null) {

        $user = 'oliver.lyons@umb.edu'; //Email address used to log in
        include($_SERVER['SERVER_ADDR'] == '104.130.195.70' ? '/home/ici/escoach.communityinclusion.org/logins.php' : '/var/www/logins.php');
        $config =  \Drupal::config('surveycampaign.settings');
        $onetime = $type == '2'  && $config->get('alt_repeat') == '0' ? true : false;
        $libconfig =  \Drupal::config('surveycampaign.library_settings');
        // If lib config date array includes today, get the closing screen page heading and text
        //Else use the default heading and text for the final screen, from the lib settings page defaults.
        // call the manage closing screen function (if today's date/default to do the work of changing things in SG
        $defaultenable = $type == 1 ? $config->get('defaultenable') : $config->get('secondenable');
        require $_SERVER['SERVER_ADDR'] == '162.243.15.189' || $_SERVER['SERVER_ADDR'] == '104.130.195.70' ? '/home/ici/escoach.communityinclusion.org/escoach/vendor/autoload.php' : '/var/www/es_coach/vendor/autoload.php';
        $survey = '5500151';//Survey to pull from
        $todaydate = date("Y-m-d");
        $tomorrowdate = new DateTime("$todaydate");
        $tomorrowdate->modify("+ $day day");
        if($onetime) $tomorrowdate->modify("+ 1 day");
        $tomorrowdate = $tomorrowdate->format('Y-m-d');
        $gizmodate = $day == 0 ? $todaydate : $tomorrowdate;
        //Options Filter examples, uncomment to see these in use..
        $status = "&filter[field][1]=status&filter[operator][1]==&filter[value][1]=Complete";//Only show complete responses
        $datesubmitted = "&filter[field][0]=datesubmitted&filter[operator][0]=>=&filter[value][0]=$gizmodate+01:00:00&resultsperpage=150";//Submit date greater than today at 1:00 AM
        $loginslug = "api_token={$api_key}&api_token_secret={$api_secret}";

        //$k = array_rand($array);
        //$v = $array[$k];
        $senddays = $type == 1 ? $config->get('def_send_days') : $config->get('alt_send_days');
        $sendtoday = false;
        $sendtoday = $this->checkDayName($senddays);
        $holiday = $config->get('def_holiday_date');
        foreach ($holiday as $key => $value) {
            if (date($value) == $todaydate) {
                $sendtoday = false;
                break;
            }
        }
        $lowrange = $type == 1 ? intval($config->get('hour_range_low')) : intval($config->get('alt_hour_range_low'));
        $highrange = $type == 1 ? intval($config->get('hour_range_high')) : intval($config->get('alt_hour_range_high'));
        $range = $this->hoursRange( $lowrange, $highrange, 60 * 30, 'g:i a' );

        $k = array_rand($range);
        if($fixdate && $fixdate != '') $fixdate = new DateTime("$fixdate - 30 minutes");
        $firstdate = $fixdate ? $fixdate->format('g:i a') : $range[$k];
        $enddate = $day == 0 ? new DateTime( "$firstdate + 30 minutes"  ) : new DateTime( "$firstdate + 1470 minutes"  );
        $senddate = $enddate->format('Y-m-d H:i:s');
        $seconddate = $enddate->format('g:i a');
        // create new campaign
        // IF this is a one-timer, make the expire data tomorrow.
        $url = "https://restapi.surveygizmo.com/v5/survey/{$surveyid}/surveycampaign?_method=PUT&type=email&linkdates[open]=" . urlencode("$gizmodate 03:00:00") . ($onetime ? "" : "&linkdates[close]=" . urlencode("$gizmodate 23:59:30")) . "&name=" . urlencode("$gizmodate Campaign") . "&tokenvariables=" . urlencode("starttime=$firstdate&endtime=$seconddate") . "&api_token={$api_key}&api_token_secret={$api_secret}";



        //Curl callfunction
        $alreadysched = $this->formQuery('surveycampaign_campaigns','senddate',$surveyid,$gizmodate, $day,1);
        if ($defaultenable != '1' && $sendtoday) {
            $libid = $libconfig->get('defaultid');
            $libraryid = $libid == $surveyid ? $libid : null;
        /*    if($libraryid) {
              $changeclosing = $this->manageClosingScreen($libid,$gizmodate,$api_key,$api_secret);
            } */
        }


        if ($defaultenable != '1' && $sendtoday && !$alreadysched ) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            $output = curl_exec($ch);
            //print_r($output);
            //The standard return from the API is JSON, decode to php.
            $output= json_decode($output);
            if(!$output) \Drupal::logger('surveycampaign alert')->notice('No Output from Alchemer: ');


            foreach($output as $response)
            { if (!is_bool($response)) {


                        //$this->addContactList($response->id,$surveyid,$api_key,$api_secret,$listid);
                        //$senddate = strtotime($senddate);
                        \Drupal::database()->insert('surveycampaign_campaigns')
                        ->fields([
                            'surveyid', 'campaignid','senddate','text1','text2','text3','type'
                        ])
                        ->values(array(
                            $surveyid, $response->id,"$senddate",0,0,0,$type
                        ))
                        ->execute();
                        $this->addContacts($response->id,$surveyid,$api_key,$api_secret,$seconddate,$senddate);
                        print_r($response);

                }

            }
        }
        else return false;

    }
   function manageClosingScreen($surveyid,$date) {
        $config =  \Drupal::config('surveycampaign.settings');
        $surveytype = $surveyid == $config->get('defaultid') ? 'default' : ($surveyid == $config->get('secondaryid') ? 'alt' : null) ;
        $defaultdisable = $config->get('defaultenable');
        $seconddisable = $config->get('secondenable');
        //\Drupal::logger('surveycampaign alert')->notice('Surveyid: ' . $surveyid . ' Surveytype: ' . $surveytype);
        if(!$surveytype) return false;
        if(($surveytype == 'default' && $defaultdisable == '1') || ($surveytype == 'alt' && $seconddisable == '1')) return false;
        $libconfig =  \Drupal::config('surveycampaign.library_settings');
        $finalpageid = $surveytype == 'default' ?  $libconfig->get('sg_clos_page_id') :  $libconfig->get('alt_sg_clos_page_id');
        $finalquestionid = $surveytype == 'default' ? $libconfig->get('sg_clos_ques_id') : $libconfig->get('alt_sg_clos_ques_id');
        include($_SERVER['SERVER_ADDR'] == '104.130.195.70' ? '/home/ici/escoach.communityinclusion.org/logins.php' : '/var/www/logins.php');
        $entity = \Drupal::entityTypeManager()->getStorage('node');
        $query = $entity->getQuery();

        $ids = $query->condition('status', 1)
        ->condition('type', 'library_item')
        ->execute();

        // Load multiples or single item load($id)
        $libcontent = $entity->loadMultiple($ids);
        $todayinsert = false;
        $finaltitle = null;
        $finaltext = null;
        foreach($libcontent as $libitem) {
            if($libitem->get('field_publish_to_survey_date_s_')->value) {

                foreach($libitem->get('field_publish_to_survey_date_s_')->getValue() as $showdate) {
                    if($showdate['value'] == $date) {
                        $finaltitle = $libitem->get('field_heading_for_closing_screen')->value == 'custom' ? urlencode($libitem->get('field_custom_heading_for_closing')->value) : ($libitem->get('field_heading_for_closing_screen')->value == 'title' ? urlencode($libitem->get('title')->value): urlencode(' '));
                        $finaltext = urlencode($libitem->get('field_short_version')->value);
                        $finalfooter = $libitem->get('field_use_standard_footer_')->value != "No" ? ($surveytype == 'default' ? urlencode($libconfig->get('defaultfootertext.value')) : urlencode($libconfig->get('alt_defaultfootertext.value'))) : '';
                        $finaltext .= $finalfooter;
                        $todayinsert = true;
                        break;
                    }
                }
            }
        }

        if (!$todayinsert) {
            $finaltitle = $surveytype == 'default' ? urlencode($libconfig->get('finalpageheading')) : urlencode($libconfig->get('alt_finalpageheading'));
            $finaltext = $surveytype == 'default' ? urlencode($libconfig->get('defaultlibrarytext.value')) : urlencode($libconfig->get('alt_defaultlibrarytext.value'));
            $finalfooter = $surveytype == 'default' ? urlencode($libconfig->get('defaultfootertext.value')) : urlencode($libconfig->get('alt_defaultfootertext.value'));
            $finaltext .= $finalfooter;
            $titleurl = "https://restapi.surveygizmo.com/v4/survey/{$surveyid}/surveypage/{$finalpageid}?_method=POST&title={$finaltitle}&api_token={$api_key}&api_token_secret={$api_secret}";
            $texturl = "https://restapi.surveygizmo.com/v5/survey/{$surveyid}/surveyquestion/{$finalquestionid}?_method=POST&title={$finaltext}&&api_token={$api_key}&api_token_secret={$api_secret}";
            // \Drupal::logger('surveycampaign alert')->notice('Library Text URL: ' . $texturl);

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $titleurl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            $output = curl_exec($ch);
            $ch2 = curl_init();
            curl_setopt($ch2, CURLOPT_URL, $texturl);
            curl_setopt($ch2, CURLOPT_RETURNTRANSFER, 1);
            $output = curl_exec($ch2);
        }
        else
        {

            $titleurl = "https://restapi.surveygizmo.com/v4/survey/{$surveyid}/surveypage/{$finalpageid}?_method=POST&title={$finaltitle}&api_token={$api_key}&api_token_secret={$api_secret}";
            $texturl = "https://restapi.surveygizmo.com/v5/survey/{$surveyid}/surveyquestion/{$finalquestionid}?_method=POST&title={$finaltext}&api_token={$api_key}&api_token_secret={$api_secret}";
            // \Drupal::logger('surveycampaign alert')->notice('Library Title URL: ' . $titleurl);


            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $titleurl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            $output = curl_exec($ch);
            $ch2 = curl_init();
            curl_setopt($ch2, CURLOPT_URL, $texturl);
            curl_setopt($ch2, CURLOPT_RETURNTRANSFER, 1);
            $output2 = curl_exec($ch2);



        }

    }
   function checkCompletedOnce($surveyid,$phone) {
       $completedonce = false;
        $database = \Drupal::database();
        $query =  $database->select('surveycampaign_mailer','sm')
        ->fields('sm', array(
        'Complete',
            )
        )
        ->condition('sm.Complete', 1)
        ->condition('sm.surveyid', $surveyid)
        ->condition('sm.mobilephone', "$phone")
        ->execute();
        $results = $query->fetchAllAssoc('Complete');
        $completedonce = !empty($results) ? true: false;
        return $completedonce;
   }
   function checkDelaySetting($surveyid,$mobilephone) {
     $config =  \Drupal::config('surveycampaign.settings');
     $defaultid = $config->get('defaultid');
     $delayperiod = $config->get('alt_delay_period');
     $delayineffect = false;
      $database = \Drupal::database();
      $query =  $database->select('surveycampaign_mailer','sm')
      ->condition('sm.Complete', 1)
      ->condition('sm.surveyid', $defaultid)
      ->condition('sm.mobilephone', "$mobilephone") ->countQuery()
      ->execute();
      $results = $query->fetchField();
      //\Drupal::logger('surveycampaign')->notice("Count completed: " . $results . " Delay period: " . $delayperiod);
      $delayineffect = $results > $delayperiod ? false: true;
      return $delayineffect;

   }


   function textSchedule($surveyid, $campaignid) {
        $config =  \Drupal::config('surveycampaign.settings');
        $isprimary = $surveyid == $config->get('defaultid') ? true :false;
        $onetime = false;
        $onetime = !$isprimary && $config->get('alt_repeat') == '0' ? true : false;
        //read mailer table
        include($_SERVER['SERVER_ADDR'] == '104.130.195.70' ? '/home/ici/escoach.communityinclusion.org/logins.php' : '/var/www/logins.php');
        $todaydate = date("Y-m-d");
        $database = \Drupal::database();
        $query =  $database->select('surveycampaign_mailer','sm')
        ->fields('sm', array(
        'mobilephone','fullname','invitelink','contactid','timezone','text1','text2','text3','senddate'
          )
        )
        ->condition('sm.surveyid', $surveyid)
        ->condition('sm.campaignid', $campaignid)
        ->condition('senddate', $database->escapeLike($todaydate) . '%', 'LIKE');
        $result = $query->execute();
        foreach ($result as $row) {
            //turn an object into an array by json encoding then decoding it
            $row = json_decode(json_encode($row), true);
            //print_r($row);
            $timezone =$row['timezone'];
            $senddate = new DateTime($row['senddate']);
            $formatdate =  new DateTime($row['senddate']);
            $senddate2 = new DateTime($row['senddate']);
            $senddate3 = new DateTime($row['senddate']);
            $formattedendtime =$formatdate->format('g:i a');
            $formattedstart = $formatdate->modify("- 30 minutes");
            $formattedstarttime = $formattedstart->format('g:i a');
            $contactid = $row['contactid'];
            $mobilephone = $row['mobilephone'];
            switch($timezone) {
                case 'ET':
                    $senddate = $senddate;
                    $senddate2 = $senddate2->modify("+ 30 minutes");
                    $senddate3 = $senddate3->modify("+ 60 minutes");

                    break;
                case 'CT':
                    $senddate = $senddate->modify("+ 60 minutes");
                    $senddate2 = $senddate2->modify("+ 90 minutes");
                    $senddate3 = $senddate3->modify("+ 120 minutes");
                break;
                case 'MT':
                    $senddate = $senddate->modify("+ 120 minutes");
                    $senddate2 = $senddate2->modify("+ 150 minutes");
                    $senddate3 = $senddate3->modify("+ 180 minutes");
                break;
                case 'PT':
                    $senddate = $senddate->modify("+ 180 minutes");
                    $senddate2 = $senddate2->modify("+ 210 minutes");
                    $senddate3 = $senddate3->modify("+ 240 minutes");
                break;
                default:
                    $senddate = $senddate;
                    $senddate2 = $senddate2->modify(" + 30 minutes");
                    $senddate3 = $senddate3->modify(" + 60 minutes");
                break;
            }


            $output = $this->getListInfo($campaignid,$surveyid,$api_key,$api_secret,$contactid);


            $remindnum = $isprimary ? intval($config->get('def_reminder_num')) : intval($config->get('secondary_reminder_num'));
            print_r($output);
            foreach ($output as $contact) { //this is going to be slow.  Have to find a better way to run through this array
                if (!is_bool($contact)) {
                    $showme = print_r($contact, true);
                    \Drupal::logger('surveycampaign')->notice("contact array" . $showme);
                    //setting for secondary survey to send only once.
                    $checkcompletedonce = false;

                    if($onetime) {
                        $checkcompletedonce = $this->checkCompletedOnce($surveyid,$row['mobilephone']);
                    }
                    if($checkcompletedonce) return;

                    $sendit = false;
                    if ($contact['id'] == $contactid && $contact["subscriber_status"] != "Complete") {
                        if($row['text1'] === '0' && ($senddate <= new DateTime()) ) { $sendit = $this->twilioCall($row['mobilephone'],$row['fullname'],$row['invitelink'],1,$formattedstarttime,$formattedendtime,$isprimary);
                        //set "text1" = 1
                        if($sendit) {
                                $database = \Drupal::database();
                                $result = $database->update('surveycampaign_mailer')
                                ->fields([
                                'text1' => 1,
                                ])
                                ->condition('surveyid', $surveyid)
                                ->condition('campaignid',$campaignid)
                                ->condition('contactid',$contactid)
                                ->execute();
                        }

                        }
                        elseif($row['text1'] == '1'  && $row['text2'] === '0' && ($senddate2 <= new DateTime() && $remindnum > 0)
                        //&& (new DateTime() <= $senddate3) late check for send text
                        ) {
                            $sendit = $this->twilioCall($row['mobilephone'],$row['fullname'],$row['invitelink'],2,$formattedstarttime,$formattedendtime,$isprimary);
                            //set "text2" = 1
                            if($sendit) {
                                $database = \Drupal::database();
                                $result = $database->update('surveycampaign_mailer')
                                ->fields([
                                'text2' => 1,
                                ])
                                ->condition('surveyid', $surveyid)
                                ->condition('campaignid',$campaignid)
                                ->condition('contactid',$contactid)
                                ->execute();
                            }

                        }

                        elseif($row['text1'] == '1'  && $row['text2'] == '1' && $row['text3'] === '0' && $senddate3 <= new DateTime() && $remindnum > 1) {
                            $sendit = $this->twilioCall($row['mobilephone'],$row['fullname'],$row['invitelink'],3,$formattedstarttime,$formattedendtime,$isprimary);
                            //set "text3" = 1
                            if($sendit) {
                                $database = \Drupal::database();
                                $result = $database->update('surveycampaign_mailer')
                                ->fields([
                                'text3' => 1,
                                ])
                                ->condition('surveyid', $surveyid)
                                ->condition('campaignid',$campaignid)
                                ->condition('contactid',$contactid)
                                ->execute();
                            }

                        }
                    } elseif ($contact['id'] == $contactid && $contact["subscriber_status"] == "Complete")
                    {

                        $suspenddates = $this->getResponseInfo($surveyid,$contactid,$contact['email_address'],$api_key,$api_secret);
                        //echo "Suspendarray: "; print_r($suspenddates);
                        // check if user suspended survey.  If so get dates and enter in user profile
                        //delete this individual in this survey/campaign from surveycampaign_mailer

                        // Suspension choices on secondary survey seem to be interfering with setting them from primary survey
                        //$startid = $isprimary ? $config->get('def_survey_suspend_start_id') : $config->get('alt_survey_suspend_start_id');
                        $startid = $config->get('def_survey_suspend_start_id');
                        //$endid = $isprimary ? $config->get('def_survey_suspend_end_id') : $config->get('alt_survey_suspend_end_id');
                        $endid =  $config->get('def_survey_suspend_end_id');
                        $startdate = null;
                        $enddate = null;
                        if ($suspenddates['data'][0] && $suspenddates['data'][0]['survey_data'][$startid]) {
                            if($suspenddates['data'][0]['survey_data'][$startid]['answer']) {
                                //echo "Suspension start:" . $suspenddates['data'][0]['survey_data'][$startid]['answer'];
                                //echo "Suspenddates: "; print_r($suspenddates);
                                $startdate = new DateTime($suspenddates['data'][0]['survey_data'][$startid]['answer']);
                                $startdate = $startdate->format('Y-m-d');
                            }
                            if($suspenddates['data'][0]['survey_data'][$endid]['answer']) {
                                //echo "<br />Suspension last day:" . $suspenddates['data'][0]['survey_data'][$endid]['answer'];
                                $enddate = new DateTime($suspenddates['data'][0]['survey_data'][$endid]['answer']);
                                $enddate->modify("- 1 day");
                                $enddate = $enddate->format('Y-m-d');
                            }

                            //\Drupal::logger('surveycampaign')->notice("start and end: " . $startdate . " / " . $enddate);

                            if ( $mobilephone && $startdate) $setdates = \Drupal::service('surveycampaign.survey_users')->handleSuspendDates($mobilephone,$startdate,$enddate);
                        }
                        if($suspenddates['data'][0]) {
                            $database = \Drupal::database();
                            $result = $database->update('surveycampaign_mailer')
                                ->fields([
                                'Complete' => '1'
                                ])
                                ->condition('surveyid', $surveyid)
                                    ->condition('campaignid',$campaignid)
                                    ->condition('contactid',$contactid)
                                    ->execute();
                        }
                    }

                }
            }

         }
   }

   function getResponseInfo($surveyid,$contactid,$email,$api_key,$api_secret) {
       //contact_id was deprecated as of SG v. 5.  Stopped working.
       $email = urlencode($email);
       $todaydate = date("Y-m-d");
       $filterdate = urlencode("$todaydate 01:00:00");
       $url = "https://restapi.surveygizmo.com/v5/survey/{$surveyid}/surveyresponse?filter[field][0]=date_submitted&filter[operator][0]=>=&filter[value][0]={$filterdate}&filter[field][1]=[question(545)]&filter[operator][1]==&filter[value][1]={$email}&api_token={$api_key}&api_token_secret={$api_secret}";
       //\Drupal::logger('surveycampaign')->notice("Response URL: " . $url);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $output = curl_exec($ch);
        //The standard return from the API is JSON, decode to php.
        $output= json_decode($output,true);

       return $output;



   }
   function twilioCall($tonumber,$name,$link,$textno,$starttime,$endtime,$isprimary) {
        $config =  \Drupal::config('surveycampaign.settings');
        $firsttextconfig = $isprimary ? $config->get('first_text_body.value') : $config->get('alt_first_text_body.value');
        $secondtextconfig = $isprimary ? $config->get('second_text_body.value') : $config->get('alt_second_text_body.value') ;
        $thirdtextconfig = $isprimary ? $config->get('third_text_body.value') : $config->get('alt_third_text_body.value');
        $warningtextconfig = $config->get('warning_text_body.value');
        $cutofftextconfig = $config->get('cutoff_text_body.value');

        $firsttextbody = str_replace('@endtime',$endtime,str_replace('@starttime',$starttime,str_replace('@link', $link,str_replace("@name", $name, $firsttextconfig))));
        $secondtextbody = str_replace('@endtime',$endtime,str_replace('@starttime',$starttime,str_replace('@link', $link,str_replace("@name", $name, $secondtextconfig))));
        $thirdtextbody = str_replace('@endtime',$endtime,str_replace('@starttime',$starttime,str_replace('@link', $link,str_replace("@name", $name, $thirdtextconfig))));
        $warningtextbody = str_replace("@name", $name,str_replace('@warningdays', $starttime,str_replace("@daystocutoff", $endtime,str_replace('@invitelink', $link, $warningtextconfig))));
        $cutofftextbody = str_replace("@name", $name,str_replace('@cutoffdays', $starttime, $cutofftextconfig));
        switch($textno) {
            case 1 :
                $bodytext = $firsttextbody;
                break;
            case 2 :
                $bodytext = $secondtextbody;
                break;
            case 3 :
                $bodytext = $thirdtextbody;
                break;
            case 4 :
                $bodytext = $warningtextbody;
                break;
            case 5 :
                $bodytext = $cutofftextbody;
                break;
            default :
                $bodytext = $firsttextbody;
                break;
        }
       include($_SERVER['SERVER_ADDR'] == '104.130.195.70' ? '/home/ici/escoach.communityinclusion.org/logins.php' : '/var/www/logins.php');

      // A Twilio number you own with SMS capabilities
      $twilio_number = "+16172497169";

      $client = new Client($account_sid, $auth_token);
      try {
                $client->messages->create(
                    // Where to send a text message (your cell phone?)
                    "+1$tonumber",
                    array(
                        'from' => $twilio_number,
                        'body' => $bodytext,
                    )
                );

        } catch (\Twilio\Exceptions\RestException $e) {
            return false;
        }
        return true;

    }
    function hoursRange( $lower = 0, $upper = 86400, $step = 3600, $format = '' ) {
      $times = array();

      if ( empty( $format ) ) {
          $format = 'g:i a';
        }

      foreach ( range( $lower, $upper, $step ) as $increment ) {
          $increment = gmdate( 'H:i', $increment );

          list( $hour, $minutes ) = explode( ':', $increment );

          $date = new DateTime( $hour . ':' . $minutes );

          $times[(string) $increment] = $date->format( $format );
        }

      return $times;
    }

    function addContacts($campaignid,$surveyid,$api_key,$api_secret,$seconddate,$transferdate = null) {
        $config =  \Drupal::config('surveycampaign.settings');
        $cutoffcampaigns = array();
        $warning = intval($config->get('def_warning_trigger'));
        $cutoff = intval($config->get('def_inactive_trigger'));
        $cutoffcampaigns = $this->getRecentCampaigns($surveyid,$cutoff);
        $warningcampaigns = array_slice($cutoffcampaigns, 0, $warning);
        $isprimary = $surveyid == $config->get('defaultid') ? true :false;
        $onetime = false;
        $onetime = !$isprimary && $config->get('alt_repeat') == '0' ? true : false;
        $contactarray = \Drupal::service('surveycampaign.survey_users')->load();
        foreach($contactarray as $contact) {
            $email = urlencode($contact[0]);
            $firstname = urlencode($contact[1]);
            $lastname = urlencode($contact[2]);
            $fullname = $firstname . " " . $lastname;
            $urlphone = urlencode( preg_replace('/\D+/', '',$contact[3]));
            $mobilephone = $contact[3];
            $timezone = urlencode($contact[4]);
            $provider = $contact[8] ? urlencode($contact[8]) : 'no provider';
            $sendtime = urlencode($seconddate);
            $checkcompletedonce = false;
            $delayineffect = false;
            if($onetime) {
                $checkcompletedonce = $this->checkCompletedOnce($surveyid,$mobilephone);
                if (!$checkcompletedonce && !$isprimary)  $delayineffect = $this->checkDelaySetting($surveyid,$mobilephone);
            }
            if(!$checkcompletedonce && !$delayineffect)
            {

                //echo "$campaignid,$email,$firstname,$lastname,$mobilephone";
                $url = "https://restapi.surveygizmo.com/v5/survey/{$surveyid}/surveycampaign/{$campaignid}/surveycontact/?_method=PUT&email_address={$email}&first_name={$firstname}&last_name={$lastname}&home_phone={$urlphone}&customfield1={$timezone}&customfield2={$provider}&api_token={$api_key}&api_token_secret={$api_secret}";
                //echo $url;
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                $output = curl_exec($ch);
                //The standard return from the API is JSON, decode to php.
                $output= json_decode($output);
                $didnotreply = false;
                $inactive = false;
                $inactive = \Drupal::service('surveycampaign.survey_users')->checkInactive($mobilephone,$contact[2]);
                // if($inactive && $isprimary) $this->checkSuspendedReminder($mobilephone,$fullname,$email,$surveyid);
                // $didnotreplyshort = !empty($warningcampaigns) ? intval($this->checkNonReplies($surveyid,$mobilephone,$fullname,$warningcampaigns)) : false;
                $didnotreply = !empty($cutoffcampaigns) ? intval($this->checkNonReplies($surveyid,$mobilephone,$fullname,$cutoffcampaigns)) : false;
                $warningcount = !empty($warningcampaigns) ? intval($this->checkNonReplies($surveyid,$mobilephone,$fullname,$warningcampaigns)) :false;

                $todaylink = null;
                if($didnotreply >= $cutoff && !$inactive) {

                    $sendwarning = $this->mailNonReplyer($email,$firstname,$lastname,$mobilephone,2,$todaylink,$isprimary);
                    }
                elseif ($didnotreply == $warning && $warningcount == $warning && !$inactive)
                {

                    if (!is_bool($output)) {
                        $todaylink = $output->invitelink;
                    //    \Drupal::logger('surveycampaign')->notice("Today link: " . $todaylink);
                        $sendwarning = $this->mailNonReplyer($email,$firstname,$lastname,$mobilephone,1,$todaylink,$isprimary);
                    }

                }


                if (!is_bool($output)) {
                    $senddate = new DateTime($transferdate);
                    $truncdate = $senddate->format('Y-m-d');
                    $comparedate = new DateTime($truncdate);

                    //compare send date to suspend dates and cancel adding user if suspended
                    $cancelsurvey = false;
                    if ($mobilephone)
                    {
                        $checksuspend = \Drupal::service('surveycampaign.survey_users')->handleSuspendDates($mobilephone);
                        $suspendstart = $checksuspend[0] ? new DateTime($checksuspend[0]) : false;
                        $suspendend = $checksuspend[1] ? new DateTime($checksuspend[1]) : false;
                        $inactive = $checksuspend[2];
                    }
                    //if($suspendstart && ($suspendstart <= $comparedate)) $cancelsurvey = true;
                    if($suspendstart && ($suspendstart <= $comparedate) && ($suspendend >= $comparedate)) $cancelsurvey = true;
                    if($inactive) $cancelsurvey = true;



                    $invitelink = $output->invitelink;
                    $contactid = $output->id;



                    $senddate = $senddate->format('Y-m-d H:i:s');
                    $checkalready = false;
                    $checkalready =  $this->conditionCheck('surveycampaign_mailer',$surveyid,$senddate,$mobilephone);

                    if(!$checkalready && !$cancelsurvey && $didnotreply < $cutoff) {
                        $database = \Drupal::database();
                        $result = $database->insert('surveycampaign_mailer')
                        ->fields([
                            'surveyid' => $surveyid,
                            'campaignid' => $campaignid,
                            'senddate' => $senddate,
                            'mobilephone' => $mobilephone,
                            'timezone' => $timezone,
                            'fullname' => $fullname,
                            'invitelink' => $invitelink,
                            'contactid' => $contactid,
                        ]) ->execute();
                    }

                }
                $senddate = new DateTime($transferdate);
            }

        }
        $show = $this->getListInfo($campaignid,$surveyid,$api_key,$api_secret);

    }
    function updateCampaignTime($surveyid,$pastdate,$newdate,$day) {
        include($_SERVER['SERVER_ADDR'] == '104.130.195.70' ? '/home/ici/escoach.communityinclusion.org/logins.php' : '/var/www/logins.php');
        $todaydate = date("Y-m-d");
        $gizmodate = new DateTime("$todaydate");
        $gizmodate->modify("+ $day day");
        $conditiondate = $gizmodate->format('Y-m-d');
        $newdate = new DateTime($newdate);
        $seconddate = $newdate->format('g:i a');
        $minusdate = new DateTime( "$seconddate - 30 minutes"  );
        $firstdate = $minusdate->format('g:i a');
        $insertdate = $newdate->format('Y-m-d H:i:s');


        $campaignid = $this->formQuery('surveycampaign_campaigns','campaignid',$surveyid,$pastdate,$day);



        $database = \Drupal::database();
        $database->update('surveycampaign_campaigns')
        ->fields([
          'senddate' => $insertdate
        ])
        ->condition('senddate', $database->escapeLike($conditiondate) . '%', 'LIKE')
        ->condition('surveyid', $surveyid)
        ->execute();


        $database->update('surveycampaign_mailer')
        ->fields([
          'senddate' => $insertdate
        ])
        ->condition('senddate', $database->escapeLike($conditiondate) . '%', 'LIKE')
        ->condition('surveyid', $surveyid)
        ->execute();



        $url = "https://restapi.surveygizmo.com/v5/survey/{$surveyid}/surveycampaign/{$campaignid}?_method=POST" . "&tokenvariables=" . urlencode("starttime={$firstdate}&endtime={$seconddate}") . "&api_token={$api_key}&api_token_secret={$api_secret}";
        $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            $output = curl_exec($ch);
            //The standard return from the API is JSON, decode to php.
            $output= json_decode($output);


    }
    function formQuery($table,$var,$survid,$senddate,$day,$like = 0) {
        $todaydate = date("Y-m-d");
        $database = \Drupal::database();
        $result = $like == 0 ? $database->select($table, 'ta')
        ->fields('ta', array(
        "$var"
          )
        )
        ->condition('ta.surveyid', $survid)
        ->condition('senddate', $senddate)
        ->execute()->fetchField()
        :
        $database->select($table, 'ta')
        ->fields('ta', array(
        "$var"
          )
        )
        ->condition('ta.surveyid', $survid)
        ->condition('senddate', $database->escapeLike($senddate) . '%', 'LIKE')
        ->execute()->fetchField();
        $number_of_rows = count($result);

        return $number_of_rows > 0 ? $result : ($like== 0 ? $result : false);

    }

    function addContactList($campaignid,$surveyid,$api_key,$api_secret,$listid) {


        $url = "https://restapi.surveygizmo.com/v5/survey/{$surveyid}/surveycampaign/$campaignid?_method=POST&contact_list={$listid}&api_token={$api_key}&api_token_secret={$api_secret}";
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $output = curl_exec($ch);
        //The standard return from the API is JSON, decode to php.
        $output= json_decode($output);


        foreach($output as $response)
        { if (!is_bool($response)) {
            print_r($response);
            $this->getListInfo($campaignid,$surveyid,$api_key,$api_secret);
            }


        }

    }

    function getListInfo($campaignid,$surveyid,$api_key,$api_secret,$contactid = null) {
        $url ="https://restapi.surveygizmo.com/v5/survey/{$surveyid}/surveycampaign/$campaignid/surveycontact" . ($contactid ? "/{$contactid}" : ""). "?api_token={$api_key}&api_token_secret={$api_secret}";
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $output = curl_exec($ch);
        //The standard return from the API is JSON, decode to php.
        $output= json_decode($output,true);
        print_r($output);
        return $output;




        foreach($output as $response)
            { if (!is_bool($response) && is_array($response)) {
                    foreach ($response as $user) { print_r($user); echo "<br /><br />";
                        $name = $user->first_name . " " . $user->last_name;

                        }
                }


        }

    }
    function conditionCheck($table,$surveyid,$senddate,$mobilephone) {
                    $database = \Drupal::database();
                    $senddate = new DateTime("$senddate");
                    $senddate = $senddate->format('Y-m-d');
                    $result = $database->select($table, 'ta')
                   //->fields('ta', array('senddate','mobilephone'))
                    ->condition('ta.surveyid', $surveyid)
                   // ->condition('ta.campaignid', $campaignid)
                   ->condition('senddate', $database->escapeLike($senddate) . '%', 'LIKE')
                    ->condition('mobilephone', $mobilephone)
                    ->countQuery()
                    ->execute()
                    ->fetchField();

                    $return = $result > 0 ? true : false;
                    return $return;

    }

  protected function checkDayName($dayarray) {
        $today = date('w');
        $days = array('Sunday', 'Monday', 'Tuesday', 'Wednesday','Thursday','Friday', 'Saturday');



        if ($dayarray[$days[$today]] !== 0 ) {


            return true;
        }

        else { return false; }
    }
    protected function checkNonReplies($surveyid,$mobilephone,$fullname,$campaignarray) {
        $database = \Drupal::database();
        $query =  $database->select('surveycampaign_mailer','sm')
        ->condition('sm.surveyid', $surveyid)
        ->condition('sm.mobilephone', $mobilephone)
        ->condition('sm.fullname', $fullname)
        ->condition('sm.campaignid', $campaignarray, 'IN')
        ->condition('sm.Complete', '0')
        ->countQuery();
        $result = $query->execute()->fetchField();
        return $result;

    }
    protected function getRecentCampaigns($surveyid,$limitno) {
        $database = \Drupal::database();
        $limitno = intval($limitno);
        $query =  $database->select('surveycampaign_campaigns','sc')
        ->fields('sc', array(
        'senddate'
          )
        )
        ->condition('sc.surveyid', $surveyid)
        ->orderBy('campaignid','DESC')
        ->range(1, $limitno);
        $result = $query->execute()->fetchAll();
        $result = json_decode(json_encode($result), true);
        $campaignarray = array();
        foreach($result as $key => $bundle) {
            foreach($bundle as $key => $value) {
                $campaignarray[]= $value;
            }
        }
        return $campaignarray;
    }
    protected function checkSuspendedReminder($mobilephone,$fullname,$email,$surveyid) {
      $database = \Drupal::database();
      $config =  \Drupal::config('surveycampaign.settings');
      $dayspastinactive = $config->get('def_days_past_inactive');

      $query =  $database->select('surveycampaign_mailer','sm')
      ->fields('sc', array(
      'campaignid')
      )
      ->condition('sm.surveyid', $surveyid)
      ->condition('sm.mobilephone', $mobilephone)
      ->condition('sm.fullname', $fullname)
      ->orderBy('senddate','DESC')
      ->range(1, $limitno);
      $result = $query->execute()->fetchField();
      $lastsurveydate = substr($result, 0, 10);  // abcd
      $todaydate = date("Y-m-d");
      $triggerdate = new DateTime("$lastsurveydate");
      $triggerdate->modify("+ $dayspastinactive days");
      $triggerdate = $triggerdate->format('Y-m-d');
      if($triggerdate == $todaydate) {
        //send the reminder
      }
      //$lastactivedate = "select  DATE_FORMAT(senddate, "%Y-%m-%d") truncatedate from surveycampaign_mailer where mobilephone = 6125015804 AND surveyid =  5420562 ORDER BY senddate DESC LIMIT 1";

    }
    protected function mailNonReplyer($email,$firstname,$lastname,$mobilephone,$noreplylevel,$invitelink,$isprimary) {
        $send = false;
        $config =  \Drupal::config('surveycampaign.settings');
        $mailManager = \Drupal::service('plugin.manager.mail');
        $module = 'surveycampaign';
        $key = 'mailgun';
        $usermail = urldecode($email);
        $siteemail = 'admin@rsmail.communityinclusion.org';
        $admin = $config->get('survey_admin_mail');
        $warningmode =$config->get('def_inactive_mode');
        $warningno =$config->get('def_warning_trigger');
        $inactiveno =$config->get('def_inactive_trigger');
        $warningdays = $inactiveno - $warningno;
        $to = "Administrator <$admin>,$firstname $lastname <$usermail>";
        $warningtextconfig = $config->get('warning_text_body.value');
        $cutofftextconfig = $config->get('cutoff_text_body.value');
        if($noreplylevel == 2 && $isprimary) {

             $cutofftextbody = str_replace("@name", "$firstname $lastname",str_replace('@cutoffdays', $dayno, $cutofftextconfig));
            $dayno = $inactiveno;
            $params['title'] = t('Daily survey paused');
            $params['message'] = t("$cutofftextbody");

            $langcode = "en";
            $checkinactive = false;
            $checkinactive = \Drupal::service('surveycampaign.survey_users')->checkInactive($mobilephone,$lastname);
            if(!$checkinactive) {
                $setinactive = \Drupal::service('surveycampaign.survey_users')->setUserStatus($mobilephone,2);
                $send = true;
                $textno = 5;
            }
        }
        elseif($noreplylevel == 1 && $isprimary)
        {
            $dayno = $warningno;
            $warningtextbody = str_replace("@name", "$firstname $lastname",str_replace('@invitelink', $invitelink,str_replace('@warningdays', $dayno,str_replace("@daystocutoff", $warningdays, $warningtextconfig))));
            $params['title'] = t('Daily survey non-response warning');
            $params['message'] = t("$warningtextbody");


                $send = true;
                $textno = 4;

        }

        $langcode = "en";

        if($send) {
            if(($warningmode == '2' || $warningmode == '3') && $isprimary) {
                $result = $mailManager->mail($module, $key, $to, $langcode, $params, $siteemail, $send);
            }
            if(($warningmode == '1' || $warningmode == '3') && $isprimary) {
                $this->twilioCall($mobilephone,"$firstname $lastname",$invitelink,$textno,$dayno,$warningdays,$isprimary);

            }
        }
    }
    public function twilioRespond($email,$firstname,$lastname,$responseaction) {
        $config =  \Drupal::config('surveycampaign.settings');
        $mailManager = \Drupal::service('plugin.manager.mail');
        $module = 'surveycampaign';
        $key = 'mailgun';
        switch ($responseaction) {
            case 'start':
                $usermail = urldecode($email);
                $siteemail = 'admin@rsmail.communityinclusion.org';
                $admin = $config->get('survey_admin_mail');
                $inactiveno =$config->get('def_inactive_trigger');
                $to = "Administrator <$admin>,$firstname $lastname <$usermail>";
                $params['title'] = t('Daily survey restarted');
                $params['message'] = t("Dear $firstname $lastname, You sent a text message requesting that the ES Coach Daily Survey resume. If you want to stop getting the daily survey sign in to your account on http://escoach.communityinclusion.org/user and set your status to inactive; or send a text message with just the word \"STOP\". If you did not send such a message contact escoach. ");

                $langcode = "en";

                $send = true;
                $result = $mailManager->mail($module, $key, $to, $langcode, $params, $siteemail, $send);
            break;
            case 'stop':
                $usermail = urldecode($email);
                $siteemail = 'admin@rsmail.communityinclusion.org';
                $admin = $config->get('survey_admin_mail');
                $inactiveno =$config->get('def_inactive_trigger');
                $to = "Administrator <$admin>,$firstname $lastname <$usermail>";
                $params['title'] = t('Daily survey paused');
                $params['message'] = t("Dear $firstname $lastname, You sent a text message requesting that the ES Coach Daily Survey stop sending to you. If you want to resume getting the daily survey send a text message with just the word \"START\". If you did not send such a message contact escoach. ");

                $langcode = "en";

                $send = true;
                $result = $mailManager->mail($module, $key, $to, $langcode, $params, $siteemail, $send);
                break;
            default:
            break;
        }

    }

}
