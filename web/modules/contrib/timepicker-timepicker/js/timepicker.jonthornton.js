/**
 * @file
 * Callback for jqueryJonthorntonTimepicker.
 */

(function ($) {
  'use strict';

  // Fix conflicts.
  $.fn.jonthorntonTimepicker = $.fn.timepicker;
  delete $.fn.timepicker;
  var initialized;

  function applyJonthornton(context, settings) {
    if (!initialized) {
      initialized = true;

      if (typeof settings.timepicker.jonthornton_timepicker !== 'undefined') {
        var timepickerSettings = settings.timepicker.jonthornton_timepicker;

        for (var fieldName in timepickerSettings) {
          if (timepickerSettings.hasOwnProperty(fieldName)) {
            var call = {}.hasOwnProperty.call(
              settings.timepicker.jonthornton_timepicker, fieldName
            );

            if (call) {
              // Find the element by field name.
              var $element = $(
                'input[name^="' + fieldName + '["][class^="form-time"]', context
              );

              // Build options object.
              var custom = settings.timepicker.jonthornton_timepicker[fieldName];

              // The Jonthornton Timepicker works with 'H:i:s' time format.
              var requiredOptions = {timeFormat: 'H:i:s'};
              var options = Object.assign(custom, requiredOptions);

              $element.jonthorntonTimepicker(options).attr('type', 'text');
            }
          }
        }
      }
    }
  }

  Drupal.behaviors.jqueryJonthorntonTimepicker = {
    attach: function (context, settings) {
      // Apply Jonthornton.
      applyJonthornton(context, settings);
    }
  };

}(jQuery));
