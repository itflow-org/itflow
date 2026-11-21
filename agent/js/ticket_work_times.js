/*
 * When a note's time was worked - the date, start and end under Time worked
 * in the reply box (agent/ticket.php; functions/ticket.php keeps them).
 *
 * Until they are typed they follow the timer (agent/js/ticket_time_tracking.js,
 * which writes #hours / #minutes / #seconds): the work ends now and started the
 * time worked ago. Typing a time pauses the timer and sets the time worked from
 * the start and end; changing the time worked after that moves the start, and
 * the end stays. "Use the timer" goes back to following it.
 *
 * The times are company time: "now" is the server's clock as the page was
 * drawn (#workTimes data-now), moved on by this browser's, so an agent whose
 * computer is in another time zone still gets the company's.
 *
 * A note with time worked needs a date, start and end that make sense to go -
 * checked as the form is sent, before the timer resets.
 */
document.addEventListener('DOMContentLoaded', function () {
    'use strict';

    const box = document.getElementById('workTimes');
    const h = document.getElementById('hours');
    const m = document.getElementById('minutes');
    const s = document.getElementById('seconds');
    const date = document.getElementById('work_date');
    const start = document.getElementById('work_start');
    const end = document.getElementById('work_end');
    const form = box ? box.closest('form') : null;
    if (!box || !h || !m || !date || !start || !end || !form) {
        return;
    }
    const mode = form.querySelector('[data-work-times-mode]');
    const problemLine = form.querySelector('[data-work-times-problem]');

    // Company time: the page's clock, moved on by this browser's
    const drawn = Date.parse(box.dataset.now || '');
    const shift = isNaN(drawn) ? 0 : drawn - Date.now();
    const now = () => new Date(Date.now() + shift);

    let typed = false; // the agent set the times themselves

    const pad = (n) => String(n).padStart(2, '0');
    const ymd = (d) => d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate());
    const hm = (d) => pad(d.getHours()) + ':' + pad(d.getMinutes());

    function worked() {
        return (parseInt(h.value, 10) || 0) * 3600 + (parseInt(m.value, 10) || 0) * 60 + (s ? parseInt(s.value, 10) || 0 : 0);
    }

    // The typed start to end in seconds - an end before the start runs past midnight - or null
    function typedLength() {
        if (!date.value || !start.value || !end.value) {
            return null;
        }
        const from = new Date(date.value + 'T' + start.value);
        const to = new Date(date.value + 'T' + end.value);
        if (to <= from) {
            // The next day by the calendar - adding a day's seconds is an hour out on the nights the clocks change
            to.setDate(to.getDate() + 1);
        }
        const secs = (to - from) / 1000;
        return secs > 0 && secs < 86400 ? secs : null;
    }

    function setWorked(secs) {
        h.value = pad(Math.floor(secs / 3600));
        m.value = pad(Math.floor((secs % 3600) / 60));
        if (s) {
            s.value = '00';
        }
        // The timer takes the new length from its fields
        [h, m, s].forEach((el) => el && el.dispatchEvent(new Event('change', { bubbles: true })));
    }

    function pauseTimer() {
        const button = document.getElementById('startStopTimer');
        if (button && button.querySelector('.fa-pause')) {
            button.click();
        }
    }

    // Following the timer: ends now, started the time worked ago
    function follow() {
        if (typed) {
            return;
        }
        const secs = worked();
        const to = now();
        if (!secs) {
            date.value = ymd(to);
            start.value = '';
            end.value = '';
            return;
        }
        // At least a minute, so a few seconds on the timer still has a start before its end
        const from = new Date(to.getTime() - Math.max(1, Math.round(secs / 60)) * 60000);
        date.value = ymd(from);
        start.value = hm(from);
        end.value = hm(to);
    }

    function showMode() {
        mode.textContent = '';
        if (typed) {
            mode.append('Typed by you · ');
            const back = document.createElement('a');
            back.href = '#';
            back.textContent = 'use the timer';
            back.addEventListener('click', function (e) {
                e.preventDefault();
                typed = false;
                showMode();
                follow();
                showProblem('');
            });
            mode.append(back);
        } else {
            const icon = document.createElement('i');
            icon.className = 'fas fa-fw fa-link me-1';
            mode.append(icon, 'From the timer - type a time to set it yourself');
        }
    }

    function problem() {
        if (!worked()) {
            return '';
        }
        if (!date.value || !start.value || !end.value) {
            return 'Add the date and the start and end of this work.';
        }
        if (typedLength() === null) {
            return 'The end has to be after the start, within a day.';
        }
        return '';
    }

    function showProblem(text) {
        problemLine.textContent = text;
        problemLine.hidden = !text;
        [date, start, end].forEach((el) => el.classList.toggle('is-invalid', !!text && !el.value));
    }

    [date, start, end].forEach(function (el) {
        el.addEventListener('input', function () {
            if (!typed) {
                typed = true;
                pauseTimer();
                showMode();
            }
            const secs = typedLength();
            if (secs !== null) {
                setWorked(secs);
            }
            showProblem('');
        });
    });

    // A new time worked once the times are typed: the end stays, the start moves
    [h, m, s].forEach(function (el) {
        if (!el) {
            return;
        }
        el.addEventListener('input', function () {
            if (typed && date.value && end.value) {
                const to = new Date(date.value + 'T' + end.value);
                const from = new Date(to.getTime() - Math.round(worked() / 60) * 60000);
                date.value = ymd(from);
                start.value = hm(from);
            } else {
                follow();
            }
            showProblem('');
        });
    });

    // First, as the form goes - before the timer resets, which it does only for a reply that went
    form.addEventListener('submit', function (e) {
        follow();
        const why = problem();
        if (why) {
            e.preventDefault();
            e.stopImmediatePropagation();
            showProblem(why);
            (date.value ? start : date).focus();
        }
    }, true);

    showMode();
    follow();
    // The timer writes its fields without events - follow it while it runs, and the clock
    setInterval(follow, 5000);
});
