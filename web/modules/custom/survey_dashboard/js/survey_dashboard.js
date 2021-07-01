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

      $('input[type="submit"]').once('validate-timeframe').on('click', function (evt) {
        if ($('#edit-timeframe').val() != 'up-to-date' && $('input:checked[name^="what"]').length == 0 ) {
          evt.stopPropagation();
          evt.preventDefault();
          alert('Please select at least one "What" option');
        }
      });

    }
  };

})(jQuery, Drupal);
