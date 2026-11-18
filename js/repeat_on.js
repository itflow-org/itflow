/**
 * The Repeats on choice for monthly and yearly repeats (byDayField() in functions/by_day.php):
 * "On the 21st", or "On the third Wednesday" / "On the last Wednesday", worked out from the date
 * picked. It shows only for the month-based frequencies, and follows the date as it changes -
 * keeping the choice when the new date still offers it, the last staying the last when it can.
 *
 * Markup: <select data-repeat-on data-date="[name=start_date]" data-frequency="[name=frequency]"
 *          data-months="Monthly,Quarterly" data-yearly="Annually" data-current="3WE"> in the form.
 * Loaded by each form that has one, so it works in a window loaded later; it binds once.
 */
(function () {
    'use strict';

    const DAYS = ['SU', 'MO', 'TU', 'WE', 'TH', 'FR', 'SA'];
    const NAMES = {SU: 'Sunday', MO: 'Monday', TU: 'Tuesday', WE: 'Wednesday', TH: 'Thursday', FR: 'Friday', SA: 'Saturday'};
    const ORDINALS = {1: 'first', 2: 'second', 3: 'third', 4: 'fourth', '-1': 'last'};
    const MONTHS = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];

    const suffix = function (n) {
        if (n % 100 >= 11 && n % 100 <= 13) {
            return 'th';
        }
        return ['th', 'st', 'nd', 'rd'][n % 10] || 'th';
    };

    // [{value, text}] for a 'YYYY-MM-DD' date
    const choices = function (dateText, yearly) {
        const m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(dateText || '');
        if (!m) {
            return [];
        }
        const date = new Date(+m[1], +m[2] - 1, +m[3]);
        const day = date.getDate();
        const code = DAYS[date.getDay()];
        const daysInMonth = new Date(+m[1], +m[2], 0).getDate();
        const of = yearly ? ' of ' + MONTHS[date.getMonth()] : '';
        const out = [{value: '', text: yearly ? 'On ' + MONTHS[date.getMonth()] + ' ' + day : 'On the ' + day + suffix(day)}];
        const nth = Math.floor((day - 1) / 7) + 1;
        if (nth <= 4) {
            out.push({value: nth + code, text: 'On the ' + ORDINALS[nth] + ' ' + NAMES[code] + of});
        }
        if (day + 7 > daysInMonth) {
            out.push({value: '-1' + code, text: 'On the last ' + NAMES[code] + of});
        }
        return out;
    };

    const refresh = function (select) {
        const form = select.closest('form') || document;
        const dateInput = form.querySelector(select.dataset.date);
        const freqInput = form.querySelector(select.dataset.frequency);
        const wrap = select.closest('[data-repeat-on-wrap]') || select.parentNode;
        const freq = freqInput ? freqInput.value : '';
        const show = (select.dataset.months || '').split(',').indexOf(freq) !== -1;
        wrap.hidden = !show;
        select.disabled = !show;
        if (!show) {
            return;
        }
        const list = choices(dateInput ? dateInput.value : '', (select.dataset.yearly || '').split(',').indexOf(freq) !== -1);
        const values = list.map(function (o) { return o.value; });
        const wanted = select.dataset.current || '';
        let pick = values.indexOf(wanted) !== -1 ? wanted : '';
        if (!pick && wanted) {
            const lastNow = values.filter(function (v) { return v.indexOf('-1') === 0; })[0];
            const nthNow = values.filter(function (v) { return v && v.indexOf('-1') !== 0; })[0];
            pick = wanted.indexOf('-1') === 0 ? (lastNow || nthNow || '') : (nthNow || lastNow || '');
        }
        // Only redrawn when the choices change - so the picker isn't rebuilt under someone using it
        const key = JSON.stringify(list);
        if (select.dataset.drawn !== key) {
            select.dataset.drawn = key;
            select.textContent = '';
            list.forEach(function (o) {
                const option = document.createElement('option');
                option.value = o.value;
                option.textContent = o.text;
                select.appendChild(option);
            });
        }
        select.value = pick;
        select.dataset.current = pick;
    };

    const refreshAll = function (root) {
        (root || document).querySelectorAll('select[data-repeat-on]').forEach(refresh);
    };

    if (!window.itflowRepeatOnBound) {
        window.itflowRepeatOnBound = true;
        const changed = function (event) {
            const target = event.target;
            // A choice made in the field itself is the one to keep - "On the 15th" (no value) too
            if (target.matches && target.matches('select[data-repeat-on]')) {
                target.dataset.current = target.value;
                return;
            }
            const form = target.closest ? target.closest('form') : null;
            if (form && form.querySelector('select[data-repeat-on]')) {
                refreshAll(form);
            }
        };
        document.addEventListener('change', changed);
        document.addEventListener('input', changed);
        document.addEventListener('shown.bs.modal', function (event) {
            refreshAll(event.target);
        });
    }
    refreshAll();
})();
