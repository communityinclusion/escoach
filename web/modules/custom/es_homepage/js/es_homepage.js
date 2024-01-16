/**
 * @file
 * Global utilities.
 *
 */
(function ($, Drupal) {
  'use strict';

  Drupal.behaviors.tooltips = {
    attach: function (context, settings) {
      $(once('body', 'body', context)).each(function (evt) {
        const tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"]');
        const tooltipList = [...tooltipTriggerList].map(tooltipTriggerEl => new bootstrap.Tooltip(tooltipTriggerEl));
      });
    }
  };

  Drupal.behaviors.es_home_menu = {
    attach: function (context, settings) {
      $(once('menu-links', 'nav.menu--homepage ul li a', context)).each(function () {
        var baseURL = $(this).attr('href');
        if (baseURL != '/dashboard') {
          if ($('#state-select').length === 1) {
            baseURL += '?state=' + $('#state-select').val();
          }
          else if ($('#provider-select').length === 1) {
            baseURL += '?provider=' + encodeURIComponent($('#provider-select').val());
          }
          $(this).attr('href', baseURL);
        }
      });
    }
  };

  Drupal.behaviors.download = {
    attach: function (context, settings) {
      $(once('download', '#download-button', context)).each(function (evt) {
        $(this).on('click', function (evt) {
          var baseURL = '/home/download';

          if ($('#state-select').length === 1) {

            baseURL += '?state=' + encodeURIComponent($('#state-select').val());
          }
          else if ($('#provider-select').length === 1) {
            baseURL += '?provider=' + encodeURIComponent($('#provider-select').val());
          }

          window.location.href = baseURL;
        });
      });
    }
  };

  Drupal.behaviors.homePageChart = {
    attach: function (context, settings) {

      $(once('bind-ajax-success', document, context)).each(function (evt) {
        $(this).on('ajaxSuccess', function (evt, data) {

          google.charts.load('current', {packages: ['corechart', chartType]});
          google.charts.setOnLoadCallback(drawBasic);

          for (var i in data.responseJSON) {
            var setting = data.responseJSON[i];
            if (setting.settings.es_homepage.chart) {
              drupalSettings.es_homepage.chart = setting.settings.es_homepage.chart;
              break;
            }
          }

        });
      });

      $(once('chart', '#google-charts', context)).each(function (evt) {
        google.charts.load('current', {packages: ['corechart', 'bar']});
        google.charts.setOnLoadCallback(drawBasic);

        $(window).on('resize', function () {
          drawBasic();
        });
      });

    function drawBasic() {

      if (!drupalSettings.es_homepage) {
        console.log('Homepage settings not available');
        return;
      }
      if (!google.visualization) {
        console.log('Google Visualization object not available');
        return;
      }
      // var last = drupalSettings.es_homepage.chart[0].pop();
      // drupalSettings.es_homepage.chart[0].push(last);
      // if (! last.role) {
      //   drupalSettings.es_homepage.chart[0].push( {role: 'annotation'});
      // }

      var data = google.visualization.arrayToDataTable(drupalSettings.es_homepage.chart);

      var options = {
        title: 'Monthly percentage of time in activities',
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

      if (drupalSettings.es_homepage.colors) {
        options.colors = drupalSettings.es_homepage.colors;
      }

      var chart = new google.visualization.BarChart(document.getElementById('google-charts'));

      chart.draw(data, options);
    }
  }
};
})(jQuery, Drupal);
