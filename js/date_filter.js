/**
 * Date range filter.
 *
 * Rebuilt on Flatpickr, replacing daterangepicker (jQuery) and moment.
 *
 * Flatpickr has no preset-ranges feature, so the nine presets daterangepicker
 * gave us for free are built here as a panel injected into the calendar via
 * the onReady hook. Behaviour is unchanged: picking a preset writes its canned
 * value and clears dtf/dtt; picking a custom range writes 'custom' plus both
 * dates; either way the form auto-submits.
 *
 * Date maths is plain Date - weeks start Monday, matching the old isoWeek.
 */
(function () {
    'use strict';

    var ALL_TIME_START = '1970-01-01';
    var ALL_TIME_END = '2099-12-31';

    function ymd(d) {
        var m = String(d.getMonth() + 1).padStart(2, '0');
        var day = String(d.getDate()).padStart(2, '0');
        return d.getFullYear() + '-' + m + '-' + day;
    }

    function parseYmd(s) {
        var parts = String(s || '').split('-');
        if (parts.length !== 3) {
            return null;
        }
        var d = new Date(Number(parts[0]), Number(parts[1]) - 1, Number(parts[2]));
        return isNaN(d.getTime()) ? null : d;
    }

    function addDays(d, n) {
        var c = new Date(d.getTime());
        c.setDate(c.getDate() + n);
        return c;
    }

    /** Monday-based start of week, matching moment's isoWeek. */
    function startOfWeek(d) {
        var c = new Date(d.getTime());
        var dow = (c.getDay() + 6) % 7;
        return addDays(c, -dow);
    }

    function buildRanges() {
        var now = new Date();
        var lastWeekStart = addDays(startOfWeek(now), -7);

        return [
            { label: 'Today', canned: 'today', start: now, end: now },
            { label: 'Yesterday', canned: 'yesterday',
              start: addDays(now, -1), end: addDays(now, -1) },
            { label: 'This Week', canned: 'thisweek',
              start: startOfWeek(now), end: now },
            { label: 'Last Week', canned: 'lastweek',
              start: lastWeekStart, end: addDays(lastWeekStart, 6) },
            { label: 'This Month', canned: 'thismonth',
              start: new Date(now.getFullYear(), now.getMonth(), 1), end: now },
            { label: 'Last Month', canned: 'lastmonth',
              start: new Date(now.getFullYear(), now.getMonth() - 1, 1),
              end: new Date(now.getFullYear(), now.getMonth(), 0) },
            { label: 'This Year', canned: 'thisyear',
              start: new Date(now.getFullYear(), 0, 1), end: now },
            { label: 'Last Year', canned: 'lastyear',
              start: new Date(now.getFullYear() - 1, 0, 1),
              end: new Date(now.getFullYear() - 1, 11, 31) },
            { label: 'All Time', canned: 'alltime',
              start: parseYmd(ALL_TIME_START), end: parseYmd(ALL_TIME_END) }
        ];
    }

    document.addEventListener('DOMContentLoaded', function () {
        var input = document.getElementById('dateFilter');
        if (!input || input._flatpickr) {
            return;
        }

        var canned = document.getElementById('canned_date');
        var dtf = document.getElementById('dtf');
        var dtt = document.getElementById('dtt');
        if (!canned || !dtf || !dtt) {
            return;
        }

        // Default to All Time when the page arrives with nothing set
        if (!((dtf.value && dtt.value) || canned.value)) {
            canned.value = 'alltime';
            dtf.value = ALL_TIME_START;
            dtt.value = ALL_TIME_END;
        }

        var ranges = buildRanges();
        var submitting = false;
        var presetClicked = false;

        function setDisplay(start, end, label) {
            if (label === 'All Time' ||
                (ymd(start) === ALL_TIME_START && ymd(end) === ALL_TIME_END)) {
                input.value = 'All Time';
            } else {
                input.value = ymd(start) + ' \u2014 ' + ymd(end);
            }
        }

        function apply(start, end, range) {
            if (submitting) {
                return;
            }
            submitting = true;
            if (range) {
                canned.value = range.canned;
                dtf.value = '';
                dtt.value = '';
            } else {
                canned.value = 'custom';
                dtf.value = ymd(start);
                dtt.value = ymd(end);
            }
            setDisplay(start, end, range ? range.label : undefined);
            if (input.form) {
                input.form.submit();
            }
        }

        var initialStart = parseYmd(dtf.value) || parseYmd(ALL_TIME_START);
        var initialEnd = parseYmd(dtt.value) || parseYmd(ALL_TIME_END);
        var initialKey = ymd(initialStart) + '|' + ymd(initialEnd);

        // 1970-01-01 / 2099-12-31 are SQL sentinels for "no bound", not dates a
        // user picked - and they are also the server's fallback, so most pages
        // arrive in this state. Seeding the calendar with them opened it on
        // January 1970 every time. Treat it as "nothing selected" instead: the
        // calendar opens on the current month with no range highlighted, so the
        // first click starts a fresh selection.
        var allTimeActive = initialKey === ALL_TIME_START + '|' + ALL_TIME_END;

        flatpickr(input, {
            mode: 'range',
            dateFormat: 'Y-m-d',
            defaultDate: allTimeActive ? null : [initialStart, initialEnd],
            locale: { firstDayOfWeek: 1 },
            allowInput: false,
            onReady: function (selectedDates, dateStr, instance) {
                // The preset panel daterangepicker used to provide
                var panel = document.createElement('div');
                panel.className = 'itflow-fp-ranges';
                ranges.forEach(function (r) {
                    var btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = 'itflow-fp-range';
                    btn.textContent = r.label;
                    if (r.canned === canned.value ||
                        (allTimeActive && r.canned === 'alltime' && !canned.value)) {
                        btn.classList.add('active');
                    }
                    btn.addEventListener('click', function () {
                        // close() fires onClose synchronously - flag it first
                        // or onClose applies the stale range as a custom one
                        presetClicked = true;
                        instance.close();
                        apply(r.start, r.end, r);
                    });
                    panel.appendChild(btn);
                });
                instance.calendarContainer.classList.add('itflow-fp-with-ranges');
                instance.calendarContainer.appendChild(panel);
            },
            onClose: function (selectedDates, dateStr, instance) {
                // A preset closed the calendar itself; its handler applies.
                if (presetClicked) {
                    presetClicked = false;
                    return;
                }
                // Abandoned half-selection - put the box back how it was.
                if (selectedDates.length !== 2) {
                    if (allTimeActive) {
                        instance.clear(false);
                    } else {
                        instance.setDate([initialStart, initialEnd], false);
                    }
                    setDisplay(initialStart, initialEnd);
                    return;
                }
                // Dismissed without actually changing the range - daterangepicker
                // only applied on its Apply button, so don't resubmit here.
                if (ymd(selectedDates[0]) + '|' + ymd(selectedDates[1]) === initialKey) {
                    setDisplay(initialStart, initialEnd);
                    return;
                }
                apply(selectedDates[0], selectedDates[1], null);
            }
        });

        setDisplay(initialStart, initialEnd);
    });
})();
