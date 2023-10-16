/**
 * @file
 * Global utilities.
 *
 */
(function ($, Drupal) {

  'use strict';

  Drupal.behaviors.homepageStateSelect = {
    attach: function (context, settings) {
      $('#state-select').once('select-state').on('change', function (evt) {
        if ($(this).val()) {
          let currentUrl = window.location.pathname;
          window.location.href = currentUrl + '?state=' + $(this).val() + '&t=' + Date.now();
        }
      });
    }
  };

})(jQuery, Drupal);
