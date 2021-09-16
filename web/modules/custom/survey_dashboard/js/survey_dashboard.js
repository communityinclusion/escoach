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
        if ($('#edit-timeframe').val() == 'up-to-date' && $('input:checked[name^="what"]').length == 0 ) {
          if ($('select[name="where"]').prop('selectedIndex') > 1 || $('select[name="who"]').prop('selectedIndex') > 1) {
            evt.stopPropagation();
            evt.preventDefault();
            alert('Please select at least one "What" option');
          }
        }
      });

    }
  };

  Drupal.behaviors.surveyDashboardClearWhat = {
    attach: function (context, settings) {
      if (!$('#edit-what--wrapper legend').hasClass('escoach-processed')) {
        $('#edit-what--wrapper legend').append(' <span class="escoach-what-clear-wrapper">[<a href="#" class="escoach-what-clear">clear</a>]</span>').addClass('escoach-processed');
      }

      $('a.escoach-what-clear').on('click', function (evt) {
        $('#edit-what input:checkbox').prop('checked', false);
      });
    }
  };

})(jQuery, Drupal);
