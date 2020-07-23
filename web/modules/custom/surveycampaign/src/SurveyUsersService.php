<?php
namespace Drupal\surveycampaign;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use \DateTime;
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
        
       // foreach ($users as $user) $userarray[]= $user->getEmail() ;
       foreach($userids as $user) {
            $storage = \Drupal::entityTypeManager()->getStorage('profile')
            ->loadByProperties([
                'uid' => $user,
                'type' => 'survey_participants',
            ]);
            $userobj = \Drupal\user\Entity\User::load($user);
            $useremail = $userobj->getEmail();
           
            foreach($storage as $profile) {
                $firstname = $profile->get('field_survey_first_name')->value ? $profile->get('field_survey_first_name')->value : '';
                $lastname = $profile->get('field_survey_last_name')->value ? $profile->get('field_survey_last_name')->value : '';
                $timezone = $profile->get('field_participant_time_zone')->value ? $profile->get('field_participant_time_zone')->value : '';
                $cellphone = $profile->get('field_cell_phone')->value ? $profile->get('field_cell_phone')->value : '';
                $suspension = $profile->get('field_partic_suspension_dates')->value ? $profile->get('field_partic_suspension_dates')->value : 'fumme';
                $suspension_end = $profile->get('field_partic_suspension_dates')->end_value ? $profile->get('field_partic_suspension_dates')->end_value : 'fuyou';
                $activstatus = $profile->get('field_set_surveys_to_inactive')->value ? $profile->get('field_set_surveys_to_inactive')->value : '';
               /* $profile->set('field_partic_suspension_dates', array(
                    'value' => "2020-08-08",
                    'end_value' => "2020-09-09",
                    ));
                    $profile->save(); */

                $userarray[$user]= array($useremail,$firstname,$lastname,$cellphone,$timezone,$suspension,$suspension_end,$activstatus);

            }
       }
       //print_r($userarray);
        return $userarray;
    }
    public function handleSuspendDates($userphone,$startdate = null,$enddate = null) {
        $today = new DateTime();
        $today = $today->format('Y-m-d');

        $storage = \Drupal::entityTypeManager()->getStorage('profile')
            ->loadByProperties([
                'type' => 'survey_participants',
                'field_cell_phone' => $userphone,
            ]);
        
        foreach($storage as $profile) {
            if($userphone == $profile->get('field_cell_phone')->value && $startdate && $enddate) {

                $profile->set('field_partic_suspension_dates', array(
                    'value' => $startdate,
                    'end_value' => $enddate,
                    ));
                $profile->save();
            } elseif ($userphone == $profile->get('field_cell_phone')->value && !$startdate && !$enddate)
            {
                $suspension = $profile->get('field_set_surveys_to_inactive')->value == '2' ? $today : ($profile->get('field_partic_suspension_dates')->value ? $profile->get('field_partic_suspension_dates')->value : null );
                $suspension_end = $profile->get('field_partic_suspension_dates')->end_value ? $profile->get('field_partic_suspension_dates')->end_value : null;
                return array($suspension,$suspension_end);
            }
        }

    }
    public function setUserInactive($firstname,$lastname,$userphone) {

        $storage = \Drupal::entityTypeManager()->getStorage('profile')
            ->loadByProperties([
                'type' => 'survey_participants',
                'field_cell_phone' => $userphone,
                'field_survey_last_name' => $lastname,
            ]);
        
        foreach($storage as $profile) {
            if($userphone == $profile->get('field_cell_phone')->value && $lastname == $profile->get('field_survey_last_name')->value) {

                $profile->set('field_set_surveys_to_inactive', array(
                    'value' => '2'));
                $profile->save();
            }
        }

    }
    
}
