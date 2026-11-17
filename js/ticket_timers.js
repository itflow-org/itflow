/*
 * ITFlow - the ticket timers in this browser, in the top bar beside the bell
 * (includes/top_nav.php), so a timer left running on a ticket you moved away
 * from isn't lost.
 *
 * A ticket's timer lives in the browser (agent/js/ticket_time_tracking.js):
 *   "<ticket id>-startTime"            when it last started counting (ms), only while it runs
 *   "<ticket id>-pausedTime"           the seconds counted before that
 *   "ticket-timer-running-<ticket id>"
 * This reads and changes those same keys. On the ticket's own page it presses
 * that page's start / pause button instead, so the page and the list agree.
 * The tickets are looked up in agent/ajax.php (ticket_timer_tickets) - only
 * those the agent can open come back. Ticket text goes in with textContent.
 *
 * Loaded once per page from includes/footer.php.
 */
(function () {
    'use strict';

    const root = document.getElementById('itflowTimers');
    if (!root || window.itflowTicketTimers) {
        return;
    }
    window.itflowTicketTimers = true;

    const badge = root.querySelector('[data-timers-badge]');
    const list = root.querySelector('[data-timers-list]');
    const toggle = root.querySelector('[data-bs-toggle="dropdown"]');
    let tickets = {};      // ticket id -> {reference, subject, client, client_id, open}, or null when it can't be opened
    let fetchedAt = 0;
    let ticking = null;
    let confirming = null; // the ticket whose timer is being thrown away

    function store() {
        try {
            return window.localStorage;
        } catch (e) {
            return null;
        }
    }

    /* Every timer with time on it, or running - running first, then the most time. */
    function timers() {
        const ls = store();
        if (!ls) {
            return [];
        }
        const ids = {};
        for (let i = 0; i < ls.length; i++) {
            const match = /^(\d+)-(startTime|pausedTime)$/.exec(ls.key(i) || '');
            if (match) {
                ids[match[1]] = true;
            }
        }
        const out = [];
        Object.keys(ids).forEach(function (id) {
            const start = parseInt(ls.getItem(id + '-startTime') || '0', 10);
            const paused = parseInt(ls.getItem(id + '-pausedTime') || '0', 10);
            const seconds = paused + (start ? Math.max(0, Math.floor((Date.now() - start) / 1000)) : 0);
            if (start || seconds > 0) {
                out.push({ id: id, running: !!start, seconds: seconds });
            }
        });
        out.sort(function (a, b) {
            return (b.running - a.running) || (b.seconds - a.seconds);
        });
        return out;
    }

    function timerFor(id) {
        return timers().filter(function (t) { return t.id === id; })[0] || null;
    }

    function hms(total) {
        const h = Math.floor(total / 3600);
        const m = Math.floor((total % 3600) / 60);
        const s = total % 60;
        return h + ':' + (m < 10 ? '0' : '') + m + ':' + (s < 10 ? '0' : '') + s;
    }

    /* The ticket this page is, if it is a ticket page with its timer on it. */
    function pageButton(id) {
        const button = document.getElementById('startStopTimer');
        if (!button || !/\/agent\/ticket\.php$/.test(window.location.pathname)) {
            return null;
        }
        return new URLSearchParams(window.location.search).get('ticket_id') === id ? button : null;
    }

    // The page's own button - its click kept from reaching the page, where it would count as a click outside this list and shut it
    function press(button) {
        const holder = button.parentNode;
        const stop = function (e) {
            e.stopPropagation();
        };
        holder.addEventListener('click', stop);
        button.click();
        holder.removeEventListener('click', stop);
    }

    // The page's button toggles, so it is pressed only when the timer is as we expect - another tab may have changed it
    function pause(id) {
        const ls = store();
        const button = pageButton(id);
        if (button) {
            if (ls.getItem(id + '-startTime')) {
                press(button);
            }
            return;
        }
        const timer = timerFor(id);
        ls.setItem(id + '-pausedTime', String(timer ? timer.seconds : 0));
        ls.removeItem(id + '-startTime');
        ls.setItem('ticket-timer-running-' + id, 'false');
    }

    function resume(id) {
        const ls = store();
        const button = pageButton(id);
        if (button) {
            if (!ls.getItem(id + '-startTime')) {
                press(button);
            }
            return;
        }
        ls.setItem(id + '-startTime', String(Date.now()));
        ls.setItem('ticket-timer-running-' + id, 'true');
    }

    function discard(id) {
        const ls = store();
        ls.removeItem(id + '-startTime');
        ls.removeItem(id + '-pausedTime');
        ls.removeItem('ticket-timer-running-' + id);
        if (pageButton(id)) {
            // The page's own time fields still show it - start it afresh
            window.location.reload();
        }
    }

    /* The icon and badge: how many timers, and whether any is counting. */
    function refreshBadge() {
        const all = timers();
        const running = all.filter(function (t) { return t.running; }).length;
        root.hidden = all.length === 0;
        root.classList.toggle('is-running', running > 0);
        badge.textContent = all.length;
        badge.className = 'badge rounded-pill navbar-badge ' + (running ? 'bg-warning text-dark' : 'bg-light text-dark');
        const title = running
            ? running + ' ticket timer' + (running === 1 ? '' : 's') + ' running' + (all.length > running ? ', ' + (all.length - running) + ' paused' : '')
            : all.length + ' paused ticket timer' + (all.length === 1 ? '' : 's');
        toggle.title = title;
        toggle.setAttribute('aria-label', 'Ticket timers - ' + title);
        if (!all.length) {
            stopTicking();
        }
        return all;
    }

    function el(tag, className, text) {
        const node = document.createElement(tag);
        if (className) {
            node.className = className;
        }
        if (text !== undefined) {
            node.textContent = text;
        }
        return node;
    }

    function button(icon, label, className, onClick) {
        const b = el('button', 'btn btn-sm ' + className);
        b.type = 'button';
        b.title = label;
        b.setAttribute('aria-label', label);
        const i = el('i', 'fas fa-fw ' + icon);
        i.setAttribute('aria-hidden', 'true');
        b.appendChild(i);
        b.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            onClick();
            render();
        });
        return b;
    }

    function textButton(text, className, onClick) {
        const b = el('button', 'btn btn-sm ' + className, text);
        b.type = 'button';
        b.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            onClick();
            render();
        });
        return b;
    }

    function render() {
        const all = refreshBadge();
        list.textContent = '';
        if (!all.length) {
            list.appendChild(el('div', 'p-3 text-body-secondary small', 'No timers running.'));
            return;
        }
        all.forEach(function (t) {
            const info = tickets[t.id];
            const name = info ? info.reference : 'ticket #' + t.id;
            const row = el('div', 'itflow-timer-row');
            row.dataset.timer = t.id;

            const top = el('div', 'd-flex align-items-center gap-2');
            const text = el('div', 'flex-grow-1 min-w-0');
            const link = el('a', 'd-block text-truncate fw-semibold', info ? info.reference + ' - ' + info.subject : 'Ticket #' + t.id);
            link.href = '/agent/ticket.php?' + (info && info.client_id ? 'client_id=' + encodeURIComponent(info.client_id) + '&' : '') + 'ticket_id=' + encodeURIComponent(t.id);
            text.appendChild(link);
            const sub = el('div', 'small text-body-secondary text-truncate');
            if (info === null) {
                sub.textContent = 'Deleted, or not one you can open';
            } else if (info) {
                sub.textContent = info.client || 'No client';
                if (!info.open) {
                    sub.appendChild(el('span', 'badge text-bg-secondary ms-1', info.state || 'Closed'));
                }
            }
            text.appendChild(sub);
            top.appendChild(text);

            const time = el('span', 'itflow-timer-time ' + (t.running ? 'text-warning-emphasis' : 'text-body-secondary'), hms(t.seconds));
            time.dataset.timerTime = t.id;
            top.appendChild(time);

            if (confirming !== t.id) {
                top.appendChild(t.running
                    ? button('fa-pause', 'Pause', 'btn-outline-secondary', function () { pause(t.id); })
                    : button('fa-play', 'Resume', 'btn-outline-secondary', function () { resume(t.id); }));
                top.appendChild(button('fa-trash-alt', 'Throw this time away', 'btn-outline-danger', function () { confirming = t.id; }));
            }
            row.appendChild(top);

            // Throwing the time away, asked here rather than in a dialog
            if (confirming === t.id) {
                const ask = el('div', 'mt-2 small');
                ask.appendChild(el('div', 'mb-2', 'Throw away the time on ' + name + '? It hasn\'t been logged on the ticket.'));
                const choices = el('div', 'd-flex justify-content-end gap-2');
                choices.appendChild(textButton('Keep it', 'btn-outline-secondary', function () { confirming = null; }));
                choices.appendChild(textButton('Throw it away', 'btn-danger', function () { confirming = null; discard(t.id); }));
                ask.appendChild(choices);
                row.appendChild(ask);
            }
            list.appendChild(row);
        });
    }

    /* The tickets behind the timers - looked up again for any new one, or after a minute. */
    function lookup(callback) {
        const ids = timers().map(function (t) { return t.id; });
        const missing = ids.filter(function (id) { return !(id in tickets); });
        if (!ids.length || (!missing.length && Date.now() - fetchedAt < 60000) || typeof itflowGet !== 'function') {
            callback();
            return;
        }
        itflowGet('/agent/ajax.php', { ticket_timer_tickets: 1, ids: ids.join(',') }, function (text) {
            let found = {};
            try {
                found = JSON.parse(text) || {};
            } catch (e) {
                found = {};
            }
            ids.forEach(function (id) {
                tickets[id] = found[id] || null;
            });
            fetchedAt = Date.now();
            callback();
        }, function () {
            callback();
        });
    }

    function tick() {
        timers().forEach(function (t) {
            const time = list.querySelector('[data-timer-time="' + t.id + '"]');
            if (time) {
                time.textContent = hms(t.seconds);
            }
        });
    }

    function stopTicking() {
        clearInterval(ticking);
        ticking = null;
    }

    root.addEventListener('show.bs.dropdown', function () {
        confirming = null;
        render();
        lookup(render);
        ticking = ticking || setInterval(tick, 1000);
    });
    root.addEventListener('hidden.bs.dropdown', stopTicking);

    // Timers started, paused or thrown away in another tab, or on this page
    function changed() {
        if (list.closest('.dropdown-menu').classList.contains('show')) {
            render();
        } else {
            refreshBadge();
        }
    }
    window.addEventListener('storage', function (e) {
        if (!e.key || /(-startTime|-pausedTime)$|^ticket-timer-running-/.test(e.key)) {
            changed();
        }
    });
    setInterval(refreshBadge, 5000);
    refreshBadge();
})();
