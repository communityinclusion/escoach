<?php
namespace Drupal\surveycampaign;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use \DateTime;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\user\Entity\User;
use Drupal\taxonomy\Entity\Term;

class SurveyUsersService
{
    protected $entityTypeManager;
    public function __construct(EntityTypeManagerInterface $entity_type_manager)
    {
        $this->entityTypeManager = $entity_type_manager;
    }
    public function load()
    {

        $storage = $this->entityTypeManager->getStorage('user');
        $query = $storage->getQuery();
        $userids = $query->execute();
        $users = $storage->loadMultiple($userids);
        $userarray = array();

       foreach($userids as $user) {

            $storage = \Drupal::entityTypeManager()->getStorage('profile')
            ->loadByProperties([
                'uid' => $user,
                'type' => 'survey_participants',
                'is_default' => 1,
            ]);
            $userobj = \Drupal\user\Entity\User::load($user);
            $useremail = $userobj->getEmail();
            $userstatus = $userobj ->get('status')->value;
            $roles =$userobj->getRoles();


                foreach($storage as $profile) {
                    if ($userstatus != 0 && in_array('survey_participant',$roles)) {
                        $firstname = $profile->get('field_survey_first_name')->value ? $profile->get('field_survey_first_name')->value : '';
                        $lastname = $profile->get('field_survey_last_name')->value ? $profile->get('field_survey_last_name')->value : '';
                        $timezone = $profile->get('field_participant_time_zone')->value ? $profile->get('field_participant_time_zone')->value : '';
                        $cellphone = $profile->get('field_cell_phone')->value ? $profile->get('field_cell_phone')->value : '';
                        $suspension = $profile->get('field_partic_suspension_dates')->value ? $profile->get('field_partic_suspension_dates')->value : '';
                        $jobtype = $profile->get('field_job_type')->value ? $profile->get('field_job_type')->value : null;
                        $suspension = $profile->get('field_partic_suspension_dates')->value ? $profile->get('field_partic_suspension_dates')->value : '';
                        $suspension_end = $profile->get('field_partic_suspension_dates')->end_value ? $profile->get('field_partic_suspension_dates')->end_value : '';
                        $activstatus = $profile->get('field_set_surveys_to_inactive')->value ? $profile->get('field_set_surveys_to_inactive')->value : '';
                        $provider = $profile->get('field_provider')->target_id ? $profile->get('field_provider')->entity->getName() : 'unknown provider';


                        if($jobtype && $jobtype != 'Manager') $userarray[$user]= array($useremail,$firstname,$lastname,$cellphone,$timezone,$suspension,$suspension_end,$activstatus,$provider);
                    }
                }
       }
       //print_r($userarray);
        return $userarray;
    }
    public function handleSuspendDates($userphone,$startdate = null,$enddate = null) {
        $today = new DateTime();
        $today = $today->format('Y-m-d');
        \Drupal::logger('surveycampaign')->notice("start and end: " . $startdate . " / " . $enddate);

        $storage = \Drupal::entityTypeManager()->getStorage('profile')
            ->loadByProperties([
                'type' => 'survey_participants',
                'is_default' => 1,
                'field_cell_phone' => $userphone,
            ]);

        foreach($storage as $profile) {
            if( preg_replace('/\D+/', '',$userphone) == preg_replace('/\D+/', '',$profile->get('field_cell_phone')->value) && $startdate && $enddate) {

                $profile->set('field_partic_suspension_dates', array(
                    'value' => $startdate,
                    'end_value' => $enddate,
                    ));
                $profile->save();
            } elseif (preg_replace('/\D+/', '',$userphone) == preg_replace('/\D+/', '',$profile->get('field_cell_phone')->value) && !$startdate && !$enddate)
            {
                $suspension = $profile->get('field_partic_suspension_dates')->value ? $profile->get('field_partic_suspension_dates')->value : null ;
                $suspension_end =  $profile->get('field_partic_suspension_dates')->end_value ? $profile->get('field_partic_suspension_dates')->end_value : null;
                $inactive = $profile->get('field_set_surveys_to_inactive')->value == '2' ? 2 : null;
                return array($suspension,$suspension_end,$inactive);

            }
        }

    }
    public function checkInactive($userphone,$lastname) {
        $cleanphone = preg_replace('/\D+/', '',$userphone);


        $storage = \Drupal::entityTypeManager()->getStorage('profile')
            ->loadByProperties([
                'type' => 'survey_participants',
                'is_default' => 1,
                'field_cell_phone' => $userphone,
            ]);

        foreach($storage as $profile) {
            $user = $profile->getOwnerId();
            $userobj = \Drupal\user\Entity\User::load($user);
            $userstatus = $userobj ->get('status')->value;

            if(($userstatus == 0) || ($profile->get('field_cell_phone')->value && $cleanphone == preg_replace('/\D+/', '',$profile->get('field_cell_phone')->value) &&  $lastname == $profile->get('field_survey_last_name')->value && $profile->get('field_set_surveys_to_inactive')->value == '2')) {

            return true;
            }
            else
            return false;
        }
    }
    public function checkCancelled($userphone) {
        $cleanphone = preg_replace('/\D+/', '',$userphone);


        $storage = \Drupal::entityTypeManager()->getStorage('profile')
            ->loadByProperties([
                'type' => 'survey_participants',
                'is_default' => 1,
                'field_cell_phone' => $userphone,
            ]);

        foreach($storage as $profile) {
            $user = $profile->getOwnerId();
            $userobj = \Drupal\user\Entity\User::load($user);
            $userstatus = $userobj ->get('status')->value;

            if(($userstatus == 0) || ($profile->get('field_cell_phone')->value && $cleanphone == preg_replace('/\D+/', '',$profile->get('field_cell_phone')->value) && $profile->get('field_active_2_deactivated_3')->value == 3)) {

            return true;
            }
            else
            return false;
        }
    }


    public function setUserStatus($userphone,$setstatus,$setcancel) {

        $storage = \Drupal::entityTypeManager()->getStorage('profile')
            ->loadByProperties([
                'type' => 'survey_participants',
                'field_cell_phone' => $userphone,
            ]);

        foreach($storage as $profile) {


            if(preg_replace('/\D+/', '',$userphone) == preg_replace('/\D+/', '',$profile->get('field_cell_phone')->value) && $profile->get('field_set_surveys_to_inactive')->value != "$setstatus") {
                $user = $profile->getOwnerId();
                $userobj = \Drupal\user\Entity\User::load($user);
                $useremail = $userobj->getEmail();
                $firstname = $profile->get('field_survey_first_name')->value ? $profile->get('field_survey_first_name')->value : '';
                $lastname = $profile->get('field_survey_last_name')->value ? $profile->get('field_survey_last_name')->value : '';
                $profile->set('field_set_surveys_to_inactive', array(
                    'value' => "$setstatus"));
                $profile->set('field_active_2_deactivated_3', array(
                    'value' => $setcancel));
                $profile->save();
                return array($useremail,$firstname,$lastname);

            }
        }

    }


}
