<?php

/**
 * @file
 * Documentation for hooks provided by the Date Timepicker module.
 */

/**
 * Define libraries for the Date Timepicker module.
 */
function hook_timepicker_libraries() {
  return [
    'timepicker_jonthornton' => [
      'title' => 'Jonthornton jQuery Timepicker',
      'callback library' => [
        'extension' => 'timepicker',
        'name' => 'jonthornton-timepicker',
      ],
      'depended library' => [
        'extension' => 'timepicker',
        'name' => 'jonthornton-jquery-timepicker',
        'download url' =>
        'https://github.com/jonthornton/jquery-timepicker/zipball/master',
        'library path' => '/libraries/jonthornton-jquery-timepicker',
        'js options reference url' =>
        'https://github.com/jonthornton/jquery-timepicker#options',
      ],
    ],
  ];
}
