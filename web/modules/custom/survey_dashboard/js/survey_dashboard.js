/**
 * @file
 * Global utilities.
 *
 */
(function ($, Drupal) {

  'use strict';

  Drupal.behaviors.surveyDashboard = {
    attach: function (context, settings) {
      $('select[name="who"]').once('who-select').on('change', function (evt) {
        $('select[name="where"]').val('_none');
      });

      $('select[name="where"]').once('where-select').on('change', function (evt) {
        $('select[name="who"]').val('_none');
      });
    }
  };

})(jQuery, Drupal);
