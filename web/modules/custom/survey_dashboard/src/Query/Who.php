<?php
namespace Drupal\survey_dashboard\Query;

class Who extends BaseQuery {

  const VID = 'who';
  const QUESTION_ID = [ 481, 590] ;

  protected $zzvalueAliasMap = [
    'Aperson' => [ 11640, 12118 ],
    'Family'=> [ 11641, 12119 ],
    'Disability' => [ 11643, 12121 ],
    'Someone'=> [ 11642, 12120 ],
    'Other' => [ 11752, 12122 ],
    'None'=> [ 11749, 12123 ],
  ];

}
