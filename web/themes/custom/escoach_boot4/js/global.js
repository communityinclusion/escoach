/**
 * @file
 * Global utilities.
 *
 */
(function ($, Drupal) {

  'use strict';

  Drupal.behaviors.escoach_boot4 = {
    attach: function (context, settings) {
      var dateFieldofChoice = form.find('#edit-field-date-oferta-und-0-value-datepicker-popup-0');
  
      if (dateFieldofChoice.length) {
        dateFieldofChoice.datepicker({ minDate: 0, maxDate: "+1D" });
      } 

    }
  };
  Drupal.behaviors.datepickerMaxDate = {
    attach: function (context, settings) {
      var dateFieldofChoice = form.find('#edit-default-survey-todaytime-date');
  
      if (dateFieldofChoice.length) {
        dateFieldofChoice.datepicker({ minDate: 0, maxDate: "+7D" });
      } 
    }
  };

  

})(jQuery, Drupal);
