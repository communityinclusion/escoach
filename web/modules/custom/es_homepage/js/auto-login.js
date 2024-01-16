/**
 * @file
 * Global utilities.
 *
 */
(function ($, Drupal) {

  'use strict';

  Drupal.behaviors.autoLogin = {
    attach: function (context, settings) {
      $(once('select-clear', '#edit-clear', context)).each(function () {
        $(this).on('click', function (evt) {
          if ($(this).is(':checked')) {
            $('#edit-submit').val('CLEAR ALL URLS');
          }
          else {
            $('#edit-submit').val('Generate CSV');
          }
        });
      });
    }
  };

})(jQuery, Drupal);
