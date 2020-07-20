/**
 * @file
 * Callback for jqueryAddonTimepicker.
 */


(function ($) {
  'use strict';

  // Fix conflicts.
  $.fn.addonTimePicker = $.fn.timepicker;
  delete $.fn.timepicker;
  var initialized;

  function applyAddonTimepicker(context, settings) {
    if (!initialized) {
      initialized = true;

      if (typeof settings.timepicker.jquery_addon_timepicker !== 'undefined') {
        var timepickerSettings = settings.timepicker.jquery_addon_timepicker;

        for (var fieldName in timepickerSettings) {
          if (timepickerSettings.hasOwnProperty(fieldName)) {
            var call = {}.hasOwnProperty.call(
              settings.timepicker.jquery_addon_timepicker, fieldName
            );

            if (call) {
              // Find the element by field name.
              var $element = $(
                'input[name^="' + fieldName + '["][class^="form-time"]', context
              );

              // Build options object.
              var custom = settings.timepicker.jquery_addon_timepicker[fieldName];

              // Set default hour and minute for the AddonTimepicker.
              var timeValues = $element.val().split(':');
              var requiredOptions = {
                hour: timeValues[0],
                minute: timeValues[1]
              };
              var options = Object.assign(custom, requiredOptions);

              // Apply the AddonTimepicker.
              $element.addonTimePicker(options);
            }
          }
        }
      }
    }
  }

  Drupal.behaviors.jqueryAddonTimepicker = {
    attach: function (context, settings) {

      // Apply Jonthornton.
      applyAddonTimepicker(context, settings);
    }
  };
}(jQuery));
