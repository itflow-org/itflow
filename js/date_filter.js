(function ($, window) {
  "use strict";

  $(function () {
    var $input = $('#dateFilter');
    if (!$input.length) return; // nothing to initialize

    var $canned = $('#canned_date');
    var $dtf = $('#dtf');
    var $dtt = $('#dtt');

    // Default to "All Time" if nothing provided
    var hasValues =
      ($dtf.val() && $dtt.val()) ||
      ($canned.val() && $canned.val() !== '');

    if (!hasValues) {
      $canned.val('alltime');
      $dtf.val('1970-01-01');
      $dtt.val('2099-12-31');
    }

    var initialStart = moment($dtf.val(), "YYYY-MM-DD");
    var initialEnd = moment($dtt.val(), "YYYY-MM-DD");

    function setDisplay(start, end, label) {
      // Special display for All Time
      if (
        label === 'All Time' ||
        (start.format('YYYY-MM-DD') === '1970-01-01' &&
         end.format('YYYY-MM-DD') === '2099-12-31')
      ) {
        $input.val('All Time');
      } else {
        $input.val(start.format('YYYY-MM-DD') + " — " + end.format('YYYY-MM-DD'));
      }
    }

    var cannedMap = {
      "Today": "today",
      "Yesterday": "yesterday",
      "This Week": "thisweek",
      "Last Week": "lastweek",
      "This Month": "thismonth",
      "Last Month": "lastmonth",
      "This Year": "thisyear",
      "Last Year": "lastyear",
      "All Time": "alltime"
    };

    $input.daterangepicker({
      startDate: initialStart,
      endDate: initialEnd,
      autoUpdateInput: true,
      opens: 'left',
      locale: {
        format: 'YYYY-MM-DD',
        firstDay: 1
      },
      ranges: {
        'Today': [moment(), moment()],
        'Yesterday': [moment().subtract(1, 'day'), moment().subtract(1, 'day')],
        'This Week': [moment().startOf('isoWeek'), moment()],
        'Last Week': [
          moment().subtract(1, 'week').startOf('isoWeek'),
          moment().subtract(1, 'week').endOf('isoWeek')
        ],
        'This Month': [moment().startOf('month'), moment()],
        'Last Month': [
          moment().subtract(1, 'month').startOf('month'),
          moment().subtract(1, 'month').endOf('month')
        ],
        'This Year': [moment().startOf('year'), moment()],
        'Last Year': [
          moment().subtract(1, 'year').startOf('year'),
          moment().subtract(1, 'year').endOf('year')
        ],
        'All Time': [
          moment('1970-01-01', 'YYYY-MM-DD'),
          moment('2099-12-31', 'YYYY-MM-DD')
        ]
      }
    }, setDisplay);

    // Show initial label
    setDisplay(initialStart, initialEnd);

    $input.on('apply.daterangepicker', function (ev, picker) {
      var label = picker.chosenLabel || '';
      var canned = cannedMap[label];

      if (canned) {
        $canned.val(canned);
        $dtf.val('');
        $dtt.val('');
      } else {
        $canned.val('custom');
        $dtf.val(picker.startDate.format('YYYY-MM-DD'));
        $dtt.val(picker.endDate.format('YYYY-MM-DD'));
      }

      setDisplay(picker.startDate, picker.endDate, label);

      // Auto-submit form
      this.form.submit();
    });
  });
})(jQuery, window);