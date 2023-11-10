/**
 * @file
 * Global utilities.
 *
 */
(function ($, Drupal) {

  'use strict';

  Drupal.behaviors.surveyDashboard = {
    attach: function (context, settings) {
      $(once('who-select', 'select[name="who"]', context)).each(function () {
        $(this).on('change', function (evt) {
          $('select[name="where"]').val('_none');
          Drupal.behaviors.surveyDashboard.validateSelection(this);
        });
      });

      $(once('where-select', 'select[name="where"]', context)).each(function () {
        $(this).on('change', function (evt) {
          $('select[name="who"]').val('_none');
          Drupal.behaviors.surveyDashboard.validateSelection(this);
        });
      });

      $(once('timeframe', '#edit-timeframe', context)).each(function () {
        $(this).on('change', function (evt) {
          if ($(this).val() != 'up-to-date') {
            if ($('select[name="where"]').val() == 'any') {
              $('select[name="where"]').val('_none');
            }
            if ($('select[name="who"]').val() == 'any') {
              $('select[name="who"]').val('_none');
            }

          }
        });
      });

      $(once('validate-timeframe', 'input[type="submit"]', context)).each(function () {
        $(this).on('click', function (evt) {
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
      });

      $(once('select-children', 'input.what-parent', context)).each(function () {
        $(this).change(function () {
          $('input.what-parent-' + $(this).val()).prop('checked', $(this).prop('checked'));
        });
      });

      $(once('clear-parent', 'input.what-child', context)).each(function () {
        $(this).change(function () {
          if (!$(this).prop('checked')) {
            $('input#edit-what-' + $(this).data('parent-id')).prop('checked', false);
          }
        });
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

      $(once('school-name', document, context)).each(function () {
        $(this).on('ajaxSuccess', function (evt, data) {
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
      });

      $(once('chart', '#google-charts', context)).each(function () {

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
            title: 'Percentage of time',
            titleTextStyle: {
              color: 'black',
              fontSize: 14,
              bold: false
            },
            format: 'percent',
            ticks: [
              {v:0, f:''},
              {v:.1, f:''},
              {v:.2, f:'20'},
              {v:.3, f:''},
              {v:.4, f:'40'},
              {v:.5, f:''},
              {v:.6, f:'60'},
              {v:.7, f:''},
              {v:.8, f:'80'},
              {v:.9, f:''},
              {v:1, f:'100'}
            ]
          },
          hAxes: {
            0: { title: 'Percentage of time'}
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
            title: '',
            slantedText:true,
            slantedTextAngle:45
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
