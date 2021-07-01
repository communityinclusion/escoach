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

      $('#edit-timeframe').once('disable-trends-options').each(function () {
        Drupal.behaviors.surveyDashboard.checkWhats();
      });

      $('input[name^="what"]').once('update-timeframe').on('click', function () {
        Drupal.behaviors.surveyDashboard.checkWhats();
      });
    },

    checkWhats: function () {
      if ( $('input:checked[name^="what"]').length > 0 ) {
        $('#edit-timeframe option[value="quarterly"]').prop('disabled', '');
        $('#edit-timeframe option[value="monthly"]').prop('disabled', '');
      }
      else {
        $('#edit-timeframe option[value="quarterly"]').prop('disabled', 'disabled');
        $('#edit-timeframe option[value="monthly"]').prop('disabled', 'disabled');
        $('#edit-timeframe').val('up-to-date');
      }
    }
  };

})(jQuery, Drupal);
