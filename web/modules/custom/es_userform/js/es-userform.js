/**
 * @file
 * JavaScript for es_userform.
 */

(function ($) {

  // Re-enable form elements that are disabled for non-ajax situations.
  Drupal.behaviors.enableFormItemsForAjaxForms = {
    attach: function () {
      // If ajax is enabled, we want to hide items that are marked as hidden in
      // our example.
      if (Drupal.ajax) {
        $('.es-userform-hide').hide();
      }
    }
  };

})(jQuery);
