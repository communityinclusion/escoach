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
        $gethighest = $database->select('surveycampaign_results', 'sr')
                     ->fields('sr', array(
                        'date_submitted'
                        )
                    )
                    ->orderBy('contact_id','DESC')
                    ->range(0, 1)
                    ->execute()
                    ->fetchField();
        
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
                    $checkexisting = $database->select('surveycampaign_results', 'sr')
                    ->condition('sr.surveyid', $surveyid)
                    ->condition('contact_id', $contactid)
                    ->countQuery()
                    ->execute()
                    ->fetchField();

                    if($checkexisting < 1){
                
                        $id =  $response['id'] ? $response['id'] : null;
                        $contactid = $response['contact_id'] ? $response['contact_id'] :1; 
                        $date_submitted = $response['date_submitted'] ? substr($response['date_submitted'],0,10) : null; 
                        $country = $response['country'] ? $response['country'] : '';
                        $state = $response['region'] ? $response['region'] : '';
                        $city = $response['city'] ? $response['city'] :'';
                        $postal = $response['postal'] ? $response['postal'] :'';
                        $latitude = $response['latitude'] ? $response['latitude'] : 0;
                        $longitude = $response['longitude'] ? $response['longitude'] : 0;
                        $name = $response['survey_data'][544]['answer'] ? $response['survey_data'][544]['answer'] : 'no name';
                        $email = $response['survey_data'][520]['answer'] ? $response['survey_data'][520]['answer'] : '';
                        $status = $response['status'] ? $response['status'] : null; 
                        $survey_data = json_encode($response['survey_data']);
                        $query = $database->insert('surveycampaign_results')
                        ->fields([
                            'surveyid', 'id','contact_id','date_submitted','country','region','city','postal','name','email','latitude','longitude','status','survey_data'
                        ])
                        ->values(array($surveyid,$id,$contactid,$date_submitted,$country,$state,$city,$postal,$name,$email,$latitude,$longitude,$status,$survey_data
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