<?php
namespace Drupal\survey_dashboard\Query;

class What extends BaseQuery {

  const VID = 'what';
  const QUESTION_ID = 483;

  protected $zzvalueAliasMap = [
    'JobSeeker' => 11658,
    'Finding'=> 11659,
    'After' => 11660,
    'Before'=> 11661,
    'Day' => 11662,
    'Staff'=> 11663,
  ];

}
