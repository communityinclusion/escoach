/**
 * @file
 * Global utilities.
 *
 */
(function ($, Drupal) {

  'use strict';

  Drupal.behaviors.homepageProviderSelect = {
    attach: function (context, settings) {
      $('#provider-select').once('select-provider').on('change', function (evt) {
        if ($(this).val()) {
          let currentUrl = window.location.pathname;
          window.location.href = currentUrl + '?provider=' + $(this).val() + '&t=' + Date.now();
        }
      });
    }
  };

})(jQuery, Drupal);
