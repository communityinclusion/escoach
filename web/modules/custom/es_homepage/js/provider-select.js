/**
 * @file
 * Global utilities.
 *
 */
(function ($, Drupal) {

  'use strict';

  Drupal.behaviors.homepageProviderSelect = {
    attach: function (context, settings) {
      $(once( 'select-provider', '#provider-select', context)).each(function () {
        $(this).on('change', function (evt) {
          if ($(this).val()) {
            let currentUrl = window.location.pathname;
            window.location.href = currentUrl + '?provider=' + encodeURIComponent($(this).val()) + '&t=' + Date.now();
          }
        });
      });
    }
  };

})(jQuery, Drupal);
