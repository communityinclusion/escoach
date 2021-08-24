(function($, Drupal, drupalSettings){
    'use strict';
  
    Drupal.behaviors.common = {
      attach: function(context) {
        // Remove TID's onload.
        Drupal.common.remove_tid();
  
        // Remove TID's onchange.
        jQuery('body').find('.form-autocomplete').on('autocompleteclose', function() {
          Drupal.common.remove_tid();
        });
      }
    };
  
    Drupal.common = {
      remove_tid: function () {
        var field_autocomplete = jQuery('body').find('.form-autocomplete');
        field_autocomplete.each(function (event, node) {
          var val = $(this).val();
          var match = val.match(/\s\(.*?\)/g);
          if (match) {
            $(this).data('real-value', val);
            $(this).val(val.replace(/\s\(.*?\)/g, '' ));
          }
        });
      }
    };
  })(jQuery, Drupal, drupalSettings);