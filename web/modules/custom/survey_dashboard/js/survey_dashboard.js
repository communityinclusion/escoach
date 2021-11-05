/**
 * @file
 * Global utilities.
 *
 */
(function ($, Drupal) {

  'use strict';

  Drupal.behaviors.surveyDashboard = {
    attach: function (context, settings) {
      $('select[name="who"]').once('who-select').on('change', function (evt) {
        $('select[name="where"]').val('_none');
        Drupal.behaviors.surveyDashboard.validateSelection(this);
      });

      $('select[name="where"]').once('where-select').on('change', function (evt) {
        $('select[name="who"]').val('_none');
        Drupal.behaviors.surveyDashboard.validateSelection(this);
      });

      $('#edit-timeframe').once('timeframe').on('change', function (evt) {
        if ($(this).val() != 'up-to-date') {
          if ($('select[name="where"]').val() == 'any') {
            $('select[name="where"]').val('_none');
          }
          if ($('select[name="who"]').val() == 'any') {
            $('select[name="who"]').val('_none');
          }

        }
      });

      $('input[type="submit"]').once('validate-timeframe').on('click', function (evt) {
        if ($('#edit-timeframe').val() != 'up-to-date' && $('input:checked[name^="what"]').length == 0 ) {
          evt.stopPropagation();
          evt.preventDefault();
          alert('Please select at least one "What" option');
        }
        if ($('#edit-timeframe').val() == 'up-to-date' && $('input:checked[name^="what"]').length == 0 ) {
          if ($('select[name="where"]').prop('selectedIndex') > 1 || $('select[name="who"]').prop('selectedIndex') > 1) {
            evt.stopPropagation();
            evt.preventDefault();
            alert('Please select at least one "What" option');
          }
        }
      });

    },
    validateSelection: function (element) {
      if ($(element).attr('name') == 'who' || $(element).attr('name') == 'where') {
        if ($('#edit-timeframe').val() != 'up-to-date' && $(element).val() == 'any') {
          $(element).val('_none');
          window.alert('Not applicable to trend data.');
        }
      }
    }
  };

  Drupal.behaviors.surveyDashboardClearWhat = {
    attach: function (context, settings) {
      if (!$('#edit-what--wrapper legend').hasClass('escoach-processed')) {
        $('#edit-what--wrapper legend').append(' <span class="escoach-what-clear-wrapper">[<a href="#" class="escoach-what-clear">clear</a>]</span>').addClass('escoach-processed');
      }

      $('a.escoach-what-clear').on('click', function (evt) {
        $('#edit-what input:checkbox').prop('checked', false);
      });
    }
  };

  Drupal.behaviors.surveyDashboardChart = {
    attach: function (context, settings) {

      $(document).once('school-name').on('ajaxSuccess', function (evt, data) {
        var chartType = 'bar';
        if (drupalSettings.survey_dashboard &&
          drupalSettings.survey_dashboard.chart_type) {
          chartType = drupalSettings.survey_dashboard.chart_type;
        }
        google.charts.load('current', {packages: ['corechart', chartType]});
        if (chartType == 'bar') {
          google.charts.setOnLoadCallback(drawBasic);
        }
        else {
          google.charts.setOnLoadCallback(drawLineChart);
        }

        for( var i in data.responseJSON) {
          var setting = data.responseJSON[i];
          if (setting.settings.survey_dashboard.chart) {
            drupalSettings.survey_dashboard.chart = setting.settings.survey_dashboard.chart;
            break;
          }
        }
      });

      $('#google-charts').once('chart').each(function () {
        var chartType = 'bar';
        if (drupalSettings.survey_dashboard && drupalSettings.survey_dashboard.chart_type) {
          chartType = drupalSettings.survey_dashboard.chart_type;
        }
        google.charts.load('current', {packages: ['corechart', chartType]});
        if (chartType == 'bar') {
          google.charts.setOnLoadCallback(drawBasic);
        }
        else {
          google.charts.setOnLoadCallback(drawLineChart);
        }

      });

      function drawBasic() {

        if (!drupalSettings.survey_dashboard) {
          console.log('Dashboard settings not available');
          return;
        }
        if (!google.visualization) {
          console.log('Google Visualization object not available');
          return;
        }
        var last = drupalSettings.survey_dashboard.chart[0].pop();
        drupalSettings.survey_dashboard.chart[0].push(last);
        if (! last.role) {
          drupalSettings.survey_dashboard.chart[0].push( {role: 'annotation'});
        }

        var data = google.visualization.arrayToDataTable(drupalSettings.survey_dashboard.chart);

        var options = {
          width: '100%',
          height: 300,
          legend: {position: 'top', maxLines:4},
          bar: { groupWidth: '75%' },
          isStacked: 'percent',
          chartArea:{left:100,top:100,width:'100%',height:'100%'},
          hAxis: {
            minValue: 0,
            format: 'percent',
            ticks: [ 0, .1, .2, .3, .4, .5, .6, .7, .8, .9, 1]
          }
        };

        if (drupalSettings.survey_dashboard.colors) {
          options.colors = drupalSettings.survey_dashboard.colors;
        }

        var chart = new google.visualization.BarChart(document.getElementById('google-charts'));

        chart.draw(data, options);
      }

      function drawLineChart() {
        if (!drupalSettings.survey_dashboard) {
          console.log('Dashboard settings not available');
          return;
        }
        if (!google.visualization) {
          console.log('Google Visualization object not available');
          return;
        }

        var data = google.visualization.arrayToDataTable(drupalSettings.survey_dashboard.chart);

        var options = {
          width: '100%',
          height: 300,
          lineWidth: 3,
          pointSize: 7,
          legend: {position: 'top'},
          hAxis: {
            title: ''
          },
          vAxis: {
            title: '% Time'
          },
          series: {
            0: { pointShape: { type: 'square' } },
            1: { pointShape: { type: 'circle' } },
            2: { pointShape: { type: 'triangle' } },
          }
        };

        if (drupalSettings.survey_dashboard.colors) {
          options.colors = drupalSettings.survey_dashboard.colors;
        }

        var chart = new google.visualization.LineChart(document.getElementById('google-charts'));
        chart.draw(data, options);
      }
    }
  };
})(jQuery, Drupal);
