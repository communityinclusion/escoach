<?php
namespace Drupal\surveycampaign;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Twilio\Rest\Client;
use \DateTime;
use Drupal\Core\Mail\MailManagerInterface;
use Drupal\mailgun\MailgunHandlerInterface;
class TwilioCoachService
{
    protected $entityTypeManager;
    public function __construct(EntityTypeManagerInterface $entity_type_manager)
    {
        $this->entityTypeManager = $entity_type_manager;
    }
    public function load($surveyid,$type = 1,$day = 0,$fixdate = null) {
     
        $user = 'oliver.lyons@umb.edu'; //Email address used to log in
        include('/var/www/logins.php');
        $config =  \Drupal::config('surveycampaign.settings');
        $defaultenable = $type == 1 ? $config->get('defaultenable') : $config->get('secondenable');
        require $_SERVER['SERVER_ADDR'] == '162.243.15.189' ? '/home/ici/escoach.communityinclusion.org/escoach/vendor/autoload.php' : '/var/www/es_coach/vendor/autoload.php';
        $survey = '5500151';//Survey to pull from
        $todaydate = date("Y-m-d");
        $tomorrowdate = new DateTime("$todaydate");
        $tomorrowdate->modify("+ $day day");
        $tomorrowdate = $tomorrowdate->format('Y-m-d');
        $gizmodate = $day == 0 ? $todaydate : $tomorrowdate;
        //Options Filter examples, uncomment to see theese in use
        $status = "&filter[field][1]=status&filter[operator][1]==&filter[value][1]=Complete";//Only show complete responses
        $datesubmitted = "&filter[field][0]=datesubmitted&filter[operator][0]=>=&filter[value][0]=$gizmodate+01:00:00&resultsperpage=150";//Submit date greater than today at 1:00 AM
        $loginslug = "api_token={$api_key}&api_token_secret={$api_secret}";
        
        //$k = array_rand($array);
        //$v = $array[$k];
        $senddays = $type == 1 ? $config->get('def_send_days') : $config->get('alt_send_days');
        $sendtoday = false;
        $sendtoday = $this->checkDayName($senddays);
        $lowrange = intval($config->get('hour_range_low'));
        $highrange = intval($config->get('hour_range_high'));
        $range = $this->hoursRange( $lowrange, $highrange, 60 * 30, 'g:i a' );
        //print_r($range);
        $k = array_rand($range);
        if($fixdate && $fixdate != '') $fixdate = new DateTime("$fixdate - 30 minutes"); 
        $firstdate = $fixdate ? $fixdate->format('g:i a') : $range[$k];
        $enddate = $day == 0 ? new DateTime( "$firstdate + 30 minutes"  ) : new DateTime( "$firstdate + 1470 minutes"  );
        $senddate = $enddate->format('Y-m-d H:i:s');
        $seconddate = $enddate->format('g:i a');
        //echo "<br /> $firstdate to $seconddate";
       


        //Restful API Call URL
        //show survey contacts
        //$url ="https://restapi.surveygizmo.com/v5/survey/{$survey}/surveycampaign/9862631/surveycontact?api_token={$api_key}&api_token_secret={$api_secret}";
        // echo $url;
        //add survey contact
        // $url ="https://restapi.surveygizmo.com/v5/survey/{$survey}/surveycampaign/9545746/surveycontact/?_method=PUT&email_address=paul.foos@umb.edu&first_name=Paul&last_name=Foos4&api_token={$api_key}&api_token_secret={$api_secret}";
        // delete survey contact
        //$url="https://restapi.surveygizmo.com/v5/survey/{$survey}/surveycampaign/9545746/surveycontact/102279429?_method=DELETE&api_token={$api_key}&api_token_secret={$api_secret}";
        //copy campaign

        //add contacts to new campaign
        //show contact lists
        //$url = "https://restapi.surveygizmo.com/v5/contactlist?api_token={$api_key}&api_token_secret={$api_secret}";
        //list contacts
        //$url = "https://restapi.surveygizmo.com/v5/contactlist/448/contactlistcontact?api_token={$api_key}&api_token_secret={$api_secret}";
        //create contact list
        //$url = "https://restapi.surveygizmo.com/v5/contactlist?_method=PUT&list_name=" . urlencode("New Trial List") . "&api_token={$api_key}&api_token_secret={$api_secret}";
        // create new campaign
        $url = "https://restapi.surveygizmo.com/v5/survey/{$surveyid}/surveycampaign?_method=PUT&type=email&linkdates[open]=" . urlencode("$gizmodate 03:00:00") . "&linkdates[close]=" . urlencode("$gizmodate 23:59:30") . "&name=" . urlencode("$gizmodate Campaign") . "&tokenvariables=" . urlencode("starttime=$firstdate&endtime=$seconddate") . "&api_token={$api_key}&api_token_secret={$api_secret}";
        //update campaign variables--open/close dates
        //$url = "https://restapi.surveygizmo.com/v5/survey/{$survey}/surveycampaign/9554492?_method=POST&linkdates[close]=" . urlencode('2020-03-13 12:00:18') . "&api_token={$api_key}&api_token_secret={$api_secret}";
        //update campaign variables: URL variables
        //$url = "https://restapi.surveygizmo.com/v5/survey/{$survey}/surveycampaign/9554492?_method=POST&tokenvariables=" . urlencode('starttime=11:30am&endtime=12:00pm') . "&api_token={$api_key}&api_token_secret={$api_secret}";
        //add contacts to contact list
        //$url = "https://restapi.surveygizmo.com/v5/contactlist/448/contactlistcontact?_method=PUT&email_address=paulfoos@zoho.com&first_name=Paul&last_name=Foos4&custom[timezone]=" . urlencode('PT') . "&api_token={$api_key}&api_token_secret={$api_secret}";
        // update a contact list contact
        //$url = "https://restapi.surveygizmo.com/v5/contactlist/448/contactlistcontact?_method=POST&email_address=paul.foos@umb.edu&custom[timezone]=" . urlencode('ET') . "&api_token={$api_key}&api_token_secret={$api_secret}";

        //add existing contact list to campaign
        //$url = "https://restapi.surveygizmo.com/v5/survey/{$survey}/surveycampaign/9576967?_method=POST&contact_list=448&api_token={$api_key}&api_token_secret={$api_secret}";
            //getListInfo(9583441,$survey,$api_key,$api_secret);
        //$twilio = twilioCall();
        //Curl callfunction 
        $alreadysched = $this->formQuery('surveycampaign_campaigns','senddate',$surveyid,$gizmodate, $day,1);
        //echo "defaultenable: $defaultenable,surveyid: $surveyid, gizmodate: $gizmodate alreadysched: $alreadysched";

        if ($defaultenable != '1' && $sendtoday && !$alreadysched ) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            $output = curl_exec($ch);
            print_r($output);
            //The standard return from the API is JSON, decode to php.
            $output= json_decode($output);
        
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
                    // $query = $database->query("INSERT INTO `surveycampaign_campaigns` (`surveyid`, `campaignid`,`senddate`,`text1`,`text2`,`text3`) VALUES($surveyid," . $response->id ,'$senddate',0,0,0)");
                
                }
                
            } 
        }
        else return false;
    }

   function textSchedule($surveyid, $campaignid) {
        //read mailer table
        include('/var/www/logins.php');
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
        // dump($query->__toString());
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
            
            foreach ($output as $contact) { //this is going to be slow.  Have to find a better way to run through this array
                if (!is_bool($contact)) {
                    
                    
                    
                    
                    if ($contact['id'] == $contactid && $contact["subscriber_status"] != "Complete") {
                        if($row['text1'] === '0' && ($senddate <= new DateTime()) ) { $sendit = $this->twilioCall($row['mobilephone'],$row['fullname'],$row['invitelink'],1,$formattedstarttime,$formattedendtime);
                        //set "text1" = 1
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
                        elseif($row['text1'] == '1'  && $row['text2'] === '0' && ($senddate2 <= new DateTime()) 
                        //&& (new DateTime() <= $senddate3) late check for send text 
                        ) { 
                            $sendit = $this->twilioCall($row['mobilephone'],$row['fullname'],$row['invitelink'],2,$formattedstarttime,$formattedendtime);
                            //set "text2" = 1
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
                        elseif($row['text1'] == '1'  && $row['text2'] == '1' && $row['text3'] === '0' && $senddate3 <= new DateTime()) { 
                            $sendit = $this->twilioCall($row['mobilephone'],$row['fullname'],$row['invitelink'],3,$formattedstarttime,$formattedendtime);
                            //set "text3" = 1
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
                    } elseif ($contact['id'] == $contactid && $contact["subscriber_status"] == "Complete") 
                    {
                        $database = \Drupal::database();
                        $result = $database->delete('surveycampaign_mailer')
                        ->condition('surveyid', $surveyid)
                            ->condition('campaignid',$campaignid)
                            ->condition('contactid',$contactid)
                            ->execute();
                        $suspenddates = $this->getResponseInfo($surveyid,$contactid,$api_key,$api_secret);
                        // check if user suspended survey.  If so get dates and enter in user profile
                        //delete this individual in this survey/campaign from surveycampaign_mailer 
                        
                        $startid = $surveyid == '5500151' ? 13 : 587;
                        $endid = $surveyid == '5500151' ? 14 : 588;
                        echo "Response begin: ";
                        print_r($suspenddates['data'][0]['survey_data'][587]);
                        echo "<br /> REsponse end: "; print_r($suspenddates['data'][0]['survey_data'][588]);
                        if ($suspenddates && $suspenddates['data'][0]['survey_data'][$startid]) {
                            if($suspenddates['data'][0]['survey_data'][$startid]['answer']) {
                                echo "Suspension start:" . $suspenddates['data'][0]['survey_data'][$startid]['answer'];
                                $startdate = new DateTime($suspenddates['data'][0]['survey_data'][$startid]['answer']);
                                $startdate = $startdate->format('Y-m-d');
                            }
                            if($suspenddates['data'][0]['survey_data'][$endid]['answer']) {
                                echo "<br />Suspension last day:" . $suspenddates['data'][0]['survey_data'][$endid]['answer'];$enddate = new DateTime($suspenddates['data'][0]['survey_data'][$endid]['answer']);
                                $enddate = $enddate->format('Y-m-d');
                            }


                            $setdates = \Drupal::service('surveycampaign.survey_users')->handleSuspendDates($mobilephone,$startdate,$enddate);
                        }
                    }
                        
                    } 
                }
            
         }
   }

   function getResponseInfo($surveyid,$contactid,$api_key,$api_secret) {
       $url = "https://restapi.surveygizmo.com/v5/survey/{$surveyid}/surveyresponse?filter[field][0]=contact_id&filter[operator][0]=%3E=&filter[value][0]={$contactid}&api_token={$api_key}&api_token_secret={$api_secret}";
       
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $output = curl_exec($ch);
        //print_r($output);
        //The standard return from the API is JSON, decode to php.
        $output= json_decode($output,true);
    
       return $output;
        


   }
   function twilioCall ($tonumber,$name,$link,$textno,$starttime,$endtime) {
        $config =  \Drupal::config('surveycampaign.settings');
        $firsttextconfig = $config->get('first_text_body.value');
        $firsttextbody = str_replace('@endtime',$endtime,str_replace('@starttime',$starttime,str_replace('@link', $link,str_replace("@name", $name, $firsttextconfig))));

       $bodytext = $textno == 1 ? $firsttextbody : ($textno ==2 ? " Hello $name.  This is a reminder to take the daily survey at this link: $link" : " Hello $name.  This is your second reminder to take the daily survey at this link: $link");
       include('/var/www/logins.php');
      
      // A Twilio number you own with SMS capabilities
      $twilio_number = "+16172497169";

      $client = new Client($account_sid, $auth_token);
      $client->messages->create(
         // Where to send a text message (your cell phone?)
         "+1$tonumber",
         array(
            'from' => $twilio_number,
            'body' => $bodytext,
         )
      );

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
        $recentcampaigns = array();

        $limit = intval($config->get('def_inactive_trigger'));
        $recentcampaigns = $this->getRecentCampaigns($surveyid,$limit);
        $contactarray = \Drupal::service('surveycampaign.survey_users')->load();
        foreach($contactarray as $contact) {
            print_r($contact);
            $email = urlencode($contact[0]);
            $firstname = urlencode($contact[1]);
            $lastname = urlencode($contact[2]);
            $fullname = $firstname . " " . $lastname;
            $mobilephone = urlencode($contact[3]);
            $timezone = urlencode($contact[4]);
            $sendtime = urlencode($seconddate);

            //echo "$campaignid,$email,$firstname,$lastname,$mobilephone";
            $url = "https://restapi.surveygizmo.com/v5/survey/{$surveyid}/surveycampaign/{$campaignid}/surveycontact/?_method=PUT&email_address={$email}&first_name={$firstname}&last_name={$lastname}&home_phone={$mobilephone}&customfield1={$timezone}&api_token={$api_key}&api_token_secret={$api_secret}";
            //echo $url;
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            $output = curl_exec($ch);
            //The standard return from the API is JSON, decode to php.
            $output= json_decode($output);
            //$didnotreply = false;
            $inactive = false;
            $inactive = \Drupal::service('surveycampaign.survey_users')->checkInactive($mobilephone,$lastname);
            $didnotreply = !empty($recentcampaigns) ?intval($this->checkNonReplies($surveyid,$mobilephone,$fullname,$recentcampaigns)) : false;
            if($didnotreply >= $limit && !$inactive) { $sendwarning = $this->mailNonReplyer($email,$firstname,$lastname,$mobilephone);}
            
            if (!is_bool($output)) {
                $senddate = new DateTime($transferdate);
                $truncdate = $senddate->format('Y-m-d');
                $comparedate = new DateTime($truncdate);

                //compare send date to suspend dates and cancel adding user if suspended
                $cancelsurvey = false;
                $checksuspend = \Drupal::service('surveycampaign.survey_users')->handleSuspendDates($mobilephone);
                $suspendstart = $checksuspend[0] ? new DateTime($checksuspend[0]) : false;
                $suspendend = $checksuspend[1] ? new DateTime($checksuspend[1]) : false;
                $inactive = $checksuspend[2];
                //if($suspendstart && ($suspendstart <= $comparedate)) $cancelsurvey = true;
                if($suspendstart && ($suspendstart <= $comparedate) && ($suspendend >= $comparedate)) $cancelsurvey = true;
                if($inactive) $cancelsurvey = true;
                


                $invitelink = $output->invitelink;
                $contactid = $output->id;
               
              
                   
                $senddate = $senddate->format('Y-m-d H:i:s');
                $checkalready = false;
                $checkalready =  $this->conditionCheck('surveycampaign_mailer',$surveyid,$senddate,$mobilephone);

                if(!$checkalready && !$cancelsurvey && $didnotreply < $limit) {
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
                 // $this->twilioCall ("+1" . $mobilephone,$fullname,$invitelink);
            }
            $senddate = new DateTime($transferdate);
  
        }
        $show = $this->getListInfo($campaignid,$surveyid,$api_key,$api_secret);

    }
    function updateCampaignTime($surveyid,$pastdate,$newdate,$day) {
        $todaydate = date("Y-m-d");
        $gizmodate = new DateTime("$todaydate");
        $gizmodate->modify("+ $day day");
        $conditiondate = $gizmodate->format('Y-m-d');
        $api_key = '586e061a200b69688552db140bca6d5be55403e0f8346d7655';
        $api_secret = 'A9vwBZC/pRVpg';
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
        return $output;
        
       


        foreach($output as $response)
            { if (!is_bool($response) && is_array($response)) { 
                    foreach ($response as $user) { print_r($user); echo "<br /><br />";
                        //echo "\n" . $user->home_phone . "\n" . $user->invitelink . "\n" . $user->first_name . " " . $user->last_name . "\n" .$user->timezone . "\n" . $user->subscriber_status . "\n";
                        $name = $user->first_name . " " . $user->last_name;
                       // $this->twilioCall ($user->home_phone,$name,$user->invitelink);
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
        echo "Campaignarray: "; print_r($campaignarray);
        $query =  $database->select('surveycampaign_mailer','sm')
        ->condition('sm.surveyid', $surveyid)
        ->condition('sm.mobilephone', $mobilephone)
        ->condition('sm.fullname', $fullname)
        ->condition('sm.campaignid', $campaignarray, 'IN')
        ->countQuery();
        $result = $query->execute()->fetchField();
        return $result;

    }
    protected function getRecentCampaigns($surveyid,$limitno) {
        $database = \Drupal::database();
        $limitno = intval($limitno);
        $query =  $database->select('surveycampaign_campaigns','sc')
        ->fields('sc', array(
        'campaignid'
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
    protected function mailNonReplyer($email,$firstname,$lastname,$mobilephone) {
        $config =  \Drupal::config('surveycampaign.settings');
        $mailManager = \Drupal::service('plugin.manager.mail');
        $module = 'surveycampaign';
        $key = 'mailgun';
        $usermail = urldecode($email);
        $siteemail = 'admin@rsmail.communityinclusion.org';
        $admin = $config->get('survey_admin_mail');
        $inactiveno =$config->get('def_inactive_trigger');
        $to = "Administrator <$admin>,$firstname $lastname <$usermail>";
        $params['title'] = t('Daily survey paused');
        $params['message'] = t("Dear $firstname $lastname, You have stopped receiving the daily survey from ES Coach because you have not replied to the survey in $inactiveno days.  Please email us at and tell us if you want to resume the survey at some future date or else be unsubscribed from it.");
        
        $langcode = "en";
        $checkinactive = false;
        $checkinactive = \Drupal::service('surveycampaign.survey_users')->checkInactive($mobilephone,$lastname);
        if(!$checkinactive) {
            $setinactive = \Drupal::service('surveycampaign.survey_users')->setUserStatus($mobilephone,2);
            $send = true;
            $result = $mailManager->mail($module, $key, $to, $langcode, $params, $siteemail, $send);
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
                $params['message'] = t("Dear $firstname $lastname, You sent a text message requesting that the ES Coach Daily Survey resume. If you did not send such a message contact escoach.");
                
                $langcode = "en";
                
                $send = true;
                $result = $mailManager->mail($module, $key, $to, $langcode, $params, $siteemail, $send);
            break;
            case 'stop':
                $usermail = urldecode($email);
                $siteemail = 'admin@rsmail.communityinclusion.org';
                $admin = $config->get('survey_admin_mail');
                $inactiveno =$config->get('def_inactive_trigger');
                $to = "Administrator <$admin>,Dummy <$usermail>";
                $params['title'] = t('Daily survey paused');
                $params['message'] = t("Dear $firstname $lastname, You sent a text message requesting that the ES Coach Daily Survey stop sending to you. If you did not send such a message contact escoach.");
                
                $langcode = "en";
                
                $send = true;
                $result = $mailManager->mail($module, $key, $to, $langcode, $params, $siteemail, $send);
                break;
            default:
            break;
        }
        
    }

}