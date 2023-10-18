/**
 * @file
 * Global utilities.
 *
 */
(function ($, Drupal) {

  'use strict';

  Drupal.behaviors.homepageStateSelect = {
    attach: function (context, settings) {
      $(once('select-state', '#state-select', context)).each(function () {
        $(this).on('change', function (evt) {
          if ($(this).val()) {
            let currentUrl = window.location.pathname;
            window.location.href = currentUrl + '?state=' + $(this).val() + '&t=' + Date.now();
          }
        });
      });
    }
  };

})(jQuery, Drupal);
