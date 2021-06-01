<?php
namespace Drupal\survey_dashboard\Query;

class Where extends BaseQuery {

  const VID = 'where';
  const QUESTION_ID = 482;

  protected $zzvalueAliasMap = [
    'Office' => 11649,
    'Residence'=> 11650,
    'Business' => 11651,
    'Vehicle'=> 11653,
    'Other' => 11753,
  ];

}
