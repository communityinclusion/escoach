/**
 * @file
 * Global utilities.
 *
 */
(function ($, Drupal) {

  'use strict';

  Drupal.behaviors.surveyDashboard = {
    attach: function (context, settings) {
      $('input[name="who"]').once('who-select').on('change', function (evt) {
        $('input[name="where"]').prop('checked', false);
      });

      $('input[name="where"]').once('where-select').on('change', function (evt) {
        $('input[name="who"]').prop('checked', false);
      });
    }
  };

})(jQuery, Drupal);
