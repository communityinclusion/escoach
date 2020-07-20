/**
 * @file
 * Callback for jqueryUiTimepicker.
 */

(function ($) {
  'use strict';

  // Fix conflicts.
  $.fn.uiTimepicker = $.fn.timepicker;
  delete $.fn.timepicker;
  var initialized;

  function applyUITimepicker(context, settings) {
    if (!initialized) {
      initialized = true;

      if (typeof settings.timepicker.jquery_ui_timepicker !== 'undefined') {
        var timepickerSettings = settings.timepicker.jquery_ui_timepicker;

        for (var fieldName in timepickerSettings) {
          if (timepickerSettings.hasOwnProperty(fieldName)) {
            var call = {}.hasOwnProperty.call(
              settings.timepicker.jquery_ui_timepicker, fieldName
            );

            if (call) {
              // Find the element by field name.
              var $element = $(
                'input[name^="' + fieldName + '["][class^="form-time"]', context
              );

              // Build options object.
              var custom = settings.timepicker.jquery_ui_timepicker[fieldName];

              // Set default hour and minute for the jQueryUITimepicker.
              var requiredOptions = {};
              var options = Object.assign(custom, requiredOptions);

              $element.uiTimepicker(options);
            }
          }
        }
      }
    }
  }

  Drupal.behaviors.jqueryUiTimepicker = {
    attach: function (context, settings) {
      // Apply Jonthornton.
      applyUITimepicker(context, settings);
    }
  };
}(jQuery));
