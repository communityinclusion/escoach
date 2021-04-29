/**
 * @file
 * Global utilities.
 *
 */
(function ($, Drupal) {

  'use strict';

  Drupal.behaviors.escoach_boot4 = {
    attach: function (context, settings) {
      $( "select#edit-survey-participants-profiles-0-entity-field-provider",context ).on('change',function() {
        var provChoice = $(this).val();
        
        if (provChoice == "6") { 
          if(!$('#edit-survey-participants-profiles-0-entity-field-other-provider-wrapper').hasClass('showOther')) $('#edit-survey-participants-profiles-0-entity-field-other-provider-wrapper').addClass('showOther');
        } else {
          if($('#edit-survey-participants-profiles-0-entity-field-other-provider-wrapper').hasClass('showOther')) $('#edit-survey-participants-profiles-0-entity-field-other-provider-wrapper').removeClass('showOther');
        }


      });
      $( "select#edit-survey-participants-profiles-0-entity-field-provider",context ).once('providerchoice').each(function() {
        var provChoice = $(this).val();
        
        if (provChoice == "6") { 
          if(!$('#edit-survey-participants-profiles-0-entity-field-other-provider-wrapper').hasClass('showOther')) $('#edit-survey-participants-profiles-0-entity-field-other-provider-wrapper').addClass('showOther');
        } else {
          if($('#edit-survey-participants-profiles-0-entity-field-other-provider-wrapper').hasClass('showOther')) $('#edit-survey-participants-profiles-0-entity-field-other-provider-wrapper').removeClass('showOther');
        }


      });

    }
  };

  

})(jQuery, Drupal);
