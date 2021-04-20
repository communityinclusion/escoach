<?php
namespace Drupal\surveycampaign;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use \DateTime;
use Twilio\Rest\Client;
use Drupal\node\Entity\Node;
class SurveyResponsesService
{
    protected $entityTypeManager;
    public function __construct(EntityTypeManagerInterface $entity_type_manager)
    {
        $this->entityTypeManager = $entity_type_manager;
    }
    public function load($surveyid)
    {   
        $database = \Drupal::database();
        $gethighestquery = $database->select('surveycampaign_results', 'sr')
                     ->fields('sr', array(
                        'date_submitted'
                        )
                    )
                    ->orderBy('contact_id','DESC')
                    ->range(0, 1)
                    ->execute()
                    ->fetchField();
        $gethighest = $gethighestquery ? $gethighestquery : '2020-03-29 10:00:00';
        // make a call to SG, get number of pages, filter by date
        // URL calls to page through data
        include($_SERVER['SERVER_ADDR'] == '104.130.195.70' ? '/home/ici/escoach.communityinclusion.org/logins.php' : '/var/www/logins.php');
        $page = 1;
        $perpage = 50;
        $totalpages = 0;
        
        $lastdate = urlencode($gethighest);
        $responseurl = "https://api.alchemer.com/v5/survey/{$surveyid}/surveyresponse?resultsperpage={$perpage}&page={$page}&filter[field][0]=date_submitted&filter[operator][0]=>=&filter[value][0]={$lastdate}&api_token={$api_key}&api_token_secret={$api_secret}";
        
        
        $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $responseurl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            $output = curl_exec($ch);
        // decode
        $output= json_decode($output,true);
        //insert data into db if not already there
        if($output['result_ok'] == "true") {
            $totalpages = $output['total_pages'];
            //\Drupal::logger('pages')->notice($totalpages);
            for ($i = 1; $i <= $totalpages; $i++) {
                $page = $i;
                $responseurl = "https://api.alchemer.com/v5/survey/{$surveyid}/surveyresponse?resultsperpage={$perpage}&page={$page}&filter[field][0]=date_submitted&filter[operator][0]=>=&filter[value][0]={$lastdate}&api_token={$api_key}&api_token_secret={$api_secret}";
                
                $ch = curl_init();
                    curl_setopt($ch, CURLOPT_URL, $responseurl);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                    $output = curl_exec($ch);
                // decode
                $output= json_decode($output,true);
               
    
        
                foreach($output['data'] as $response) { 
                    $contactid = $response['contact_id'];
                    $id = $response['id'];
                    $checkexisting = $database->select('surveycampaign_results', 'sr')
                    ->condition('sr.surveyid', $surveyid)
                    //->condition('contact_id', $contactid)
                    ->condition('id',$id)
                    ->countQuery()
                    ->execute()
                    ->fetchField();

                    if($checkexisting < 1){
                       // \Drupal::logger('surveycampaign alert')->notice('Survey id: ' . $surveyid);
                
                        $id = $response['id'] ? $response['id'] : null;
                        $contactid = $response['contact_id'] ? $response['contact_id'] :1; 
                        $date_submitted = $response['date_submitted'] ? substr($response['date_submitted'],0,10) : null; 
                        $country = $response['country'] ? $response['country'] : '';
                        $state = $response['region'] ? $response['region'] : '';
                        $city = $response['city'] ? $response['city'] :'';
                        $postal = $response['postal'] ? $response['postal'] :'';
                        $latitude = $response['latitude'] ? $response['latitude'] : 0;
                        $provider = $surveyid == '5420562' ? ($response['survey_data'][595]['answer'] ? $response['survey_data'][595]['answer'] : 'no provider') : ($response['survey_data'][18]['answer'] ? $response['survey_data'][18]['answer'] : 'no provider') ;
                        $longitude = $response['longitude'] ? $response['longitude'] : 0;
                        $name = $surveyid == '5420562' ? ($response['survey_data'][544]['answer'] ? $response['survey_data'][544]['answer']  : 'no name') : ($response['survey_data'][19]['answer'] ? $response['survey_data'][19]['answer']  : 'no name');
                        $email = $surveyid == '5420562' ? ($response['survey_data'][520]['answer'] ? $response['survey_data'][520]['answer'] : '') : ($response['survey_data'][10]['answer'] ? $response['survey_data'][10]['answer'] : '');
                        $survey_data = $surveyid == '5420562' ? "not used" : json_encode($response['survey_data']);
                        if($surveyid == '5420562') {
                            $answer482 = $response['survey_data'][482]['answer_id'] ? $response['survey_data'][482]['answer_id'] : NULL;
                            $answer481 = $response['survey_data'][481]['answer_id'] ? $response['survey_data'][481]['answer_id'] : NULL;
                            $answer525 =  $response['survey_data'][525]['answer_id'] ? $response['survey_data'][525]['answer_id'] : NULL;
                            $answer526 = $response['survey_data'][526]['answer_id'] ? $response['survey_data'][526]['answer_id'] : NULL;
                            $answer590 = $response['survey_data'][590]['answer_id'] ? $response['survey_data'][590]['answer_id'] : NULL;
                            $answer591 = $response['survey_data'][591]['answer_id'] ? $response['survey_data'][591]['answer_id'] : NULL;
                            $answer592 = $response['survey_data'][592]['answer_id'] ? $response['survey_data'][592]['answer_id'] : NULL;
                            $answer483 = $response['survey_data'][483]['answer_id'] ? $response['survey_data'][483]['answer_id'] : NULL;
                            $answer537 = $response['survey_data'][537]['answer_id'] ? $response['survey_data'][537]['answer_id'] : NULL;
                            $answer538 = $response['survey_data'][538]['answer_id'] ? $response['survey_data'][538]['answer_id'] : NULL;
                            $answer539 = $response['survey_data'][539]['answer_id'] ? $response['survey_data'][539]['answer_id'] : NULL;
                            $answer540 =$response['survey_data'][540]['answer_id'] ? $response['survey_data'][540]['answer_id'] : NULL;
                            $answer541 = $response['survey_data'][541]['answer_id'] ? $response['survey_data'][541]['answer_id'] : NULL;
                            $answer542 = $response['survey_data'][542]['answer_id'] ? $response['survey_data'][542]['answer_id'] : NULL;
                        }
                        $status = $response['status'] ? $response['status'] : null; 
                        //$survey_data = json_encode($response['survey_data']);
                        $query = $database->insert('surveycampaign_results')
                        ->fields([
                            'surveyid', 'id','contact_id','date_submitted','country','region','city','postal','name','email','latitude','longitude','status','survey_data','provider','answer482','answer481','answer525','answer526','answer590','answer591','answer592','answer483','answer537','answer538','answer539','answer540','answer541','answer542'
                        ])
                        ->values(array($surveyid,$id,$contactid,$date_submitted,$country,$state,$city,$postal,$name,$email,$latitude,$longitude,$status,$survey_data,$provider,$answer482,$answer481,$answer525,$answer526,$answer590,$answer591,$answer592,$answer483,$answer537,$answer538,$answer539,$answer540,$answer541,$answer542
                        ));

                        $resultout = $query->execute();
                    }
   
                }
            }
        } else {
            return false;
        }
    }
   

    
}
