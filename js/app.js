
  // Set new default font family and font color to mimic Bootstrap's default styling
Chart.defaults.global.defaultFontFamily = '-apple-system,system-ui,BlinkMacSystemFont,"Segoe UI",Roboto,"Helvetica Neue",Arial,sans-serif';
Chart.defaults.global.defaultFontColor = '#292b2c';

// Area Chart Example
var ctx = document.getElementById("myAreaChart");
var myLineChart = new Chart(ctx, {
  type: 'line',
  data: {
    labels: ["Mar 1", "Mar 2", "Mar 3", "Mar 4", "Mar 5", "Mar 6", "Mar 7", "Mar 8", "Mar 9", "Mar 10", "Mar 11", "Mar 12", "Mar 13"],
    datasets: [{
      label: "Sessions",
      lineTension: 0.3,
      backgroundColor: "rgba(2,117,216,0.2)",
      borderColor: "rgba(2,117,216,1)",
      pointRadius: 5,
      pointBackgroundColor: "rgba(2,117,216,1)",
      pointBorderColor: "rgba(255,255,255,0.8)",
      pointHoverRadius: 5,
      pointHoverBackgroundColor: "rgba(2,117,216,1)",
      pointHitRadius: 50,
      pointBorderWidth: 2,
      data: [10000, 30162, 26263, 18394, 18287, 28682, 31274, 33259, 25849, 24159, 32651, 31984, 38451],
    }],
  },
  options: {
    scales: {
      xAxes: [{
        time: {
          unit: 'date'
        },
        gridLines: {
          display: false
        },
        ticks: {
          maxTicksLimit: 7
        }
      }],
      yAxes: [{
        ticks: {
          min: 0,
          max: 40000,
          maxTicksLimit: 5
        },
        gridLines: {
          color: "rgba(0, 0, 0, .125)",
        }
      }],
    },
    legend: {
      display: false
    }
  }
});



//Prevents resubmit on refresh or back
if ( window.history.replaceState ) {
    window.history.replaceState( null, null, window.location.href );
}

//Data Tables Options
$('#dt').dataTable( {
    "aaSorting": [],
    "order": [],
    language: {
        search: '_INPUT_',
        searchPlaceholder: "Enter search...",
        sLengthMenu: "_MENU_",
        sInfo: "<strong>records:</strong> _START_-_END_ of _TOTAL_",
        paginate: {
        	previous: '<i class="fa fa-angle-left"></i>',
            next: '<i class="fa fa-angle-right"></i>'
        }
    }
});

// Call the dataTables jQuery plugin

$('#dataTable').DataTable();

$('#dT').dataTable( {
  "order": [],
  "columnDefs": [
    { "orderable": false, "targets":[""]}
  ],
  "orderCellsTop": true,
  language: {
      search: "<i class='fa fa-search'></i>_INPUT_",
      searchPlaceholder: "Search",
      sLengthMenu: "_MENU_",
      sInfo: "_START_-_END_ of _TOTAL_",
      paginate: {
        previous: '<i class="fa fa-angle-left"></i>',
          next: '<i class="fa fa-angle-right"></i>'
      }
  }

});

// Highlight the active nav link on side nav
$(function() {
    var url = window.location.pathname;
    var filename = url.substr(url.lastIndexOf('/') + 1);
    $('.navbar-nav a[href$="' + filename + '"]').parent().addClass("active");
});

new EasyMDE({
  autoDownloadFontAwesome: false,
  element: document.getElementById('addClientNote')
});

new EasyMDE({
    autoDownloadFontAwesome: false,
    element: document.getElementById('editClientNote')
});