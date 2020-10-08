<?php

namespace Drupal\surveycampaign\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Controller routines for page example routes.
 */
class SurveycampaignController extends ControllerBase {


  /**
   * {@inheritdoc}
   */
  protected function getModuleName() {
    return 'surveycampaign';
  }
  
  /**
   * Constructs a simple page.
   *
   * The router _controller callback, maps the path
   * 'examples/surveycampaign/simple' to this method.
   *
   * _controller callbacks return a renderable array for the content area of the
   * page. The theme system will later render and surround the content with the
   * appropriate blocks, navigation, and styling.
   */
  public function simple() {
    $userinfo = \Drupal::service('surveycampaign.survey_users')->load();
    $userguy = "";
   // return [
     // '#markup' => '<p>' . $this->t('Simple page: The quick brown fox jumps over the lazy dog.') . '</p>'
     foreach($userinfo as $userman) {$userguy.= "<p>Name: " . $userman[1] . " " . $userman[2] . "<br /> Email: " . $userman[0] . "<br /> Cell phone: " . $userman[3] . "<br />Time zone: " . $userman[4] . ($userman[5] && $userman[5] != '' ? "<br />Suspension dates: " . $userman[5] . ($userman[6] && $userman[6] != '' ? " to " . $userman[6] : ""): "") . "</p>"; }
     return ['#markup' => '<p>' .  $userguy . '</p>',];
     //print_r($userinfo);
     //,
    //];
  }
  
  public function surveyusers() {
   // $name = $request->request->get('name');
    \Drupal::service('surveycampaign.survey_users')->load();
  }
  /**
   * @param string $first
   *   A string to use, should be a number
   * @param string $second
   *   Another string to use, should be a number.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException
   *   If the parameters are invalid.
   */
  public function startsurvey($type,$day) {
   // Make sure you don't trust the URL to be safe! Always check for exploits.
    if (!is_numeric($type) && !is_numeric($day)) {
      // We will just show a standard "access denied" page in this case.
      throw new AccessDeniedHttpException();
    }
    $config = \Drupal::config('surveycampaign.settings');
    $surveyid = $type == 1 ? $config->get('defaultid') : $config->get('secondaryid');
  

  
    \Drupal::service('surveycampaign.twilio_coach')->load($surveyid,$type,$day);
  }
  public function sendtext($surveyid, $campaignid) {
    // Make sure you don't trust the URL to be safe! Always check for exploits.
     if (!is_numeric($surveyid) && !is_numeric($campaignid)) {
       // We will just show a standard "access denied" page in this case.
       throw new AccessDeniedHttpException();
     }
     \Drupal::service('surveycampaign.twilio_coach')->textSchedule($surveyid,$campaignid);
  }
  public function handleincoming() {
      $response = \Drupal::service('surveycampaign.twilio_incoming')->sendResponseMail();
  }

  /**
   * A more complex _controller callback that takes arguments.
   *
   * This callback is mapped to the path
   * 'examples/surveycampaign/arguments/{first}/{second}'.
   *
   * The arguments in brackets are passed to this callback from the page URL.
   * The placeholder names "first" and "second" can have any value but should
   * match the callback method variable names; i.e. $first and $second.
   *
   * This function also demonstrates a more complex render array in the returned
   * values. Instead of rendering the HTML with theme('item_list'), content is
   * left un-rendered, and the theme function name is set using #theme. This
   * content will now be rendered as late as possible, giving more parts of the
   * system a chance to change it if necessary.
   *
   * Consult @link http://drupal.org/node/930760 Render Arrays documentation
   * @endlink for details.
   *
   * @param string $first
   *   A string to use, should be a number.
   * @param string $second
   *   Another string to use, should be a number.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException
   *   If the parameters are invalid.
   */
  public function arguments($first, $second) {
    // Make sure you don't trust the URL to be safe! Always check for exploits.
    if (!is_numeric($first) || !is_numeric($second)) {
      // We will just show a standard "access denied" page in this case.
      throw new AccessDeniedHttpException();
    }

    $list[] = $this->t("First number was @number.", ['@number' => $first]);
    $list[] = $this->t("Second number was @number.", ['@number' => $second]);
    $list[] = $this->t('The total was @number.', ['@number' => $first + $second]);

    $render_array['surveycampaign_arguments'] = [
      // The theme function to apply to the #items.
      '#theme' => 'item_list',
      // The list itself.
      '#items' => $list,
      '#title' => $this->t('Argument Information'),
    ];
    return $render_array;
  }

}
