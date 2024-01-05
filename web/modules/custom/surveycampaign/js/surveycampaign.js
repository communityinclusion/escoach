/**
 * @file
 * Global utilities.
 *
 */
(function ($, Drupal) {

    'use strict';


    Drupal.behaviors.surveyconfig = {
      attach: function (context, settings) {
        once('scanFields','input.toggleHeading', context).forEach(function(value,i) {
            var inputName = $(this).attr('name');
                var checkedVal = $('input[name="' + inputName + '"]:checked').val();
                var optionalText = $(this).closest('.inner-fieldset').find('.customHeading').attr('id');
                if(checkedVal == '4') {if($('#' + optionalText).hasClass('hideOption')) {$('#' + optionalText).removeClass('hideOption');} }

        });



        $(once('toggleHeading','input.toggleHeading', context)).change(function() {

                var inputName = $(this).attr('name');
                var checkedVal = $('input[name="' + inputName + '"]:checked').val();
                var optionalText = $(this).closest('.inner-fieldset').find('.customHeading').attr('id');
                if(checkedVal == '4') {
                if($('#' + optionalText).hasClass('hideOption')) {$('#' + optionalText).removeClass('hideOption');}

                } else { $('#' + optionalText).val('');
                     if(!$('#' + optionalText).hasClass('hideOption')) {$('#' + optionalText).addClass('hideOption');

                    }
                }


          });

      }
    };





  })(jQuery, Drupal);
