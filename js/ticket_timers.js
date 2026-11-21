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
 * One more timer has no ticket yet - for a call that may or may not become
 * one: "call-startTime" / "call-pausedTime", the same way, kept apart from the
 * tickets' keys. It goes to a new ticket through Quick ticket (?call=1, which
 * carries it on and clears it - functions/quick_ticket.php), or onto one
 * found here (ticket_timer_search), its seconds added to that ticket's timer.
 * The panel also shows the time you logged today and this week (my_time_logged).
 *
 * Loaded once per page from includes/footer.php.
 */
(function () {
    'use strict';

    const root = document.getElementById('itflowTimers');
    if (!root || window.itflowTicketTimers) {
        return;
    }

    const badge = root.querySelector('[data-timers-badge]');
    const list = root.querySelector('[data-timers-list]');
    const callBox = root.querySelector('[data-timers-call]');
    const logged = root.querySelector('[data-timers-logged]');
    const toggle = root.querySelector('[data-bs-toggle="dropdown"]');
    const quickTicket = root.dataset.quickTicket || '';
    let tickets = {};      // ticket id -> {reference, subject, client, client_id, open}, or null when it can't be opened
    let fetchedAt = 0;
    let loggedAt = 0;
    let ticking = null;
    let confirming = null; // the ticket whose timer is being thrown away - 'call' for the one without a ticket
    let finding = false;   // looking for the ticket to give the call's time to

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

    /* The timer without a ticket, or null when there is none. */
    function callTimer() {
        const ls = store();
        if (!ls) {
            return null;
        }
        const start = parseInt(ls.getItem('call-startTime') || '0', 10);
        const paused = parseInt(ls.getItem('call-pausedTime') || '0', 10);
        const seconds = paused + (start ? Math.max(0, Math.floor((Date.now() - start) / 1000)) : 0);
        return start || seconds > 0 ? { id: 'call', running: !!start, seconds: seconds } : null;
    }

    function callStart() {
        const ls = store();
        ls.setItem('call-startTime', String(Date.now()));
    }

    function callPause() {
        const ls = store();
        const call = callTimer();
        ls.setItem('call-pausedTime', String(call ? call.seconds : 0));
        ls.removeItem('call-startTime');
    }

    function callDiscard() {
        const ls = store();
        ls.removeItem('call-startTime');
        ls.removeItem('call-pausedTime');
    }

    /* The call's time added to a ticket's timer, which then counts if the call was counting. */
    function giveCall(id) {
        const ls = store();
        const call = callTimer();
        if (!ls || !call) {
            return;
        }
        id = String(id);
        const start = parseInt(ls.getItem(id + '-startTime') || '0', 10);
        const paused = parseInt(ls.getItem(id + '-pausedTime') || '0', 10);
        ls.setItem(id + '-pausedTime', String(paused + call.seconds));
        if (!start && call.running) {
            ls.setItem(id + '-startTime', String(Date.now()));
        }
        ls.setItem('ticket-timer-running-' + id, start || call.running ? 'true' : 'false');
        callDiscard();
    }
    window.itflowTicketTimers = { giveCall: giveCall };

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

    /* The icon and badge: how many timers, and whether any is counting - the stopwatch is always there, to start one without a ticket. */
    function refreshBadge() {
        const all = timers();
        const call = callTimer();
        const count = all.length + (call ? 1 : 0);
        const running = all.filter(function (t) { return t.running; }).length + (call && call.running ? 1 : 0);
        root.classList.toggle('is-running', running > 0);
        badge.hidden = count === 0;
        badge.textContent = count;
        badge.className = 'badge rounded-pill navbar-badge ' + (running ? 'bg-warning text-dark' : 'text-bg-light');
        const title = !count
            ? 'Timers'
            : running
                ? running + ' timer' + (running === 1 ? '' : 's') + ' running' + (count > running ? ', ' + (count - running) + ' paused' : '')
                : count + ' paused timer' + (count === 1 ? '' : 's');
        toggle.title = title;
        toggle.setAttribute('aria-label', count ? 'Timers - ' + title : title);
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

    /* The timer without a ticket: start one, or give it to a new ticket or one found here. */
    function renderCall() {
        const call = callTimer();
        callBox.textContent = '';
        const row = el('div', 'itflow-timer-row');
        row.dataset.timer = 'call';
        const top = el('div', 'd-flex align-items-center gap-2');
        const text = el('div', 'flex-grow-1 min-w-0');
        text.appendChild(el('div', 'fw-semibold', call ? 'No ticket yet' : 'A timer without a ticket'));
        text.appendChild(el('div', 'small text-body-secondary', call ? 'Give it to a ticket once you know which' : 'For a call - give it to a ticket after'));
        top.appendChild(text);
        if (!call) {
            top.appendChild(button('fa-play', 'Start a timer without a ticket', 'btn-outline-secondary', callStart));
            row.appendChild(top);
            callBox.appendChild(row);
            return;
        }
        const time = el('span', 'itflow-timer-time ' + (call.running ? 'text-warning-emphasis' : 'text-body-secondary'), hms(call.seconds));
        time.dataset.timerTime = 'call';
        top.appendChild(time);
        if (confirming !== 'call') {
            top.appendChild(call.running
                ? button('fa-pause', 'Pause', 'btn-outline-secondary', callPause)
                : button('fa-play', 'Resume', 'btn-outline-secondary', callStart));
            top.appendChild(button('fa-trash', 'Throw this time away', 'btn-outline-danger', function () { confirming = 'call'; finding = false; }));
        }
        row.appendChild(top);

        if (confirming === 'call') {
            const ask = el('div', 'mt-2 small');
            ask.appendChild(el('div', 'mb-2', 'Throw away the time without a ticket? It isn\'t on any ticket.'));
            const choices = el('div', 'd-flex justify-content-end gap-2');
            choices.appendChild(textButton('Keep It', 'btn-outline-secondary', function () { confirming = null; }));
            choices.appendChild(textButton('Throw It Away', 'btn-danger', function () { confirming = null; callDiscard(); }));
            ask.appendChild(choices);
            row.appendChild(ask);
        } else {
            const give = el('div', 'd-flex flex-wrap gap-2 mt-2');
            if (quickTicket) {
                give.appendChild(textButton('New Ticket', 'btn-outline-primary', newTicket));
            }
            give.appendChild(textButton(finding ? 'Close' : 'Add to a ticket', 'btn-outline-secondary', function () { finding = !finding; }));
            row.appendChild(give);
            if (finding) {
                row.appendChild(finder());
            }
        }
        callBox.appendChild(row);
    }

    /* Quick ticket, its timer carrying on from this one */
    function newTicket() {
        const dropdown = window.bootstrap && bootstrap.Dropdown.getInstance(toggle);
        if (dropdown) {
            dropdown.hide();
        }
        const open = document.createElement('a');
        open.href = '#';
        open.className = 'ajax-modal d-none';
        open.dataset.modalUrl = quickTicket + (quickTicket.indexOf('?') === -1 ? '?' : '&') + 'call=1';
        document.body.appendChild(open);
        open.click();
        open.remove();
    }

    /* Find the ticket - your own open ones until something is typed */
    function finder() {
        const box = el('div', 'mt-2');
        const input = el('input', 'form-control form-control-sm');
        input.type = 'search';
        input.placeholder = 'Ticket number, subject or client';
        input.setAttribute('aria-label', 'Find the ticket to give this time to');
        input.dataset.timerFind = '1';
        const results = el('div', 'list-group list-group-flush mt-1 itflow-timer-find');
        box.appendChild(input);
        box.appendChild(results);
        let wait = null;
        let asked = 0;
        function search() {
            const ask = ++asked;
            if (typeof itflowGet !== 'function') {
                return;
            }
            itflowGet('/agent/ajax.php', { ticket_timer_search: 1, q: input.value.trim() }, function (text) {
                if (ask !== asked) {
                    return;
                }
                let found = [];
                try {
                    found = JSON.parse(text) || [];
                } catch (e) {
                    found = [];
                }
                results.textContent = '';
                if (!found.length) {
                    results.appendChild(el('div', 'small text-body-secondary py-1', input.value.trim() ? 'No open ticket found.' : 'None assigned to you - type to find one.'));
                    return;
                }
                found.forEach(function (t) {
                    const pick = el('button', 'list-group-item list-group-item-action px-1 py-1 small');
                    pick.type = 'button';
                    pick.dataset.timerGive = t.id;
                    pick.appendChild(el('div', 'fw-semibold text-truncate', t.reference + ' - ' + t.subject));
                    pick.appendChild(el('div', 'text-body-secondary text-truncate', t.client || 'No client'));
                    pick.addEventListener('click', function (e) {
                        e.preventDefault();
                        e.stopPropagation();
                        giveCall(t.id);
                        finding = false;
                        window.location.href = '/agent/ticket.php?' + (t.client_id ? 'client_id=' + encodeURIComponent(t.client_id) + '&' : '') + 'ticket_id=' + encodeURIComponent(t.id);
                    });
                    results.appendChild(pick);
                });
            });
        }
        input.addEventListener('input', function () {
            clearTimeout(wait);
            wait = setTimeout(search, 250);
        });
        input.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
            }
        });
        search();
        setTimeout(function () { input.focus(); }, 0);
        return box;
    }

    /* The time you logged on tickets - looked up when the panel opens, at most once a minute */
    function renderLogged() {
        if (!logged || typeof itflowGet !== 'function' || Date.now() - loggedAt < 60000) {
            return;
        }
        itflowGet('/agent/ajax.php', { my_time_logged: 1 }, function (text) {
            let t = null;
            try {
                t = JSON.parse(text);
            } catch (e) {
                t = null;
            }
            if (!t) {
                return;
            }
            loggedAt = Date.now();
            const hm = function (total) {
                total = Math.max(0, parseInt(total, 10) || 0);
                return Math.floor(total / 3600) + ':' + String(Math.floor(total % 3600 / 60)).padStart(2, '0');
            };
            logged.textContent = '';
            logged.appendChild(el('span', '', 'You logged '));
            logged.appendChild(el('strong', 'itflow-timer-time', hm(t.today)));
            logged.appendChild(el('span', '', ' today, '));
            logged.appendChild(el('strong', 'itflow-timer-time', hm(t.week)));
            logged.appendChild(el('span', '', ' this week'));
            logged.hidden = false;
        });
    }

    function render() {
        const all = refreshBadge();
        // Typing in the search box isn't lost to the call row being redrawn
        const typing = document.activeElement && document.activeElement.dataset && document.activeElement.dataset.timerFind;
        if (!(finding && typing && callBox.contains(document.activeElement))) {
            renderCall();
        }
        list.textContent = '';
        if (!all.length) {
            list.appendChild(el('div', 'p-3 text-body-secondary small', 'No ticket timers running.'));
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
                    sub.appendChild(el('span', 'badge rounded-pill text-bg-secondary ms-1', info.state || 'Closed'));
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
                top.appendChild(button('fa-trash', 'Throw this time away', 'btn-outline-danger', function () { confirming = t.id; }));
            }
            row.appendChild(top);

            // Throwing the time away, asked here rather than in a dialog
            if (confirming === t.id) {
                const ask = el('div', 'mt-2 small');
                ask.appendChild(el('div', 'mb-2', 'Throw away the time on ' + name + '? It hasn\'t been logged on the ticket.'));
                const choices = el('div', 'd-flex justify-content-end gap-2');
                choices.appendChild(textButton('Keep It', 'btn-outline-secondary', function () { confirming = null; }));
                choices.appendChild(textButton('Throw It Away', 'btn-danger', function () { confirming = null; discard(t.id); }));
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
        timers().concat(callTimer() || []).forEach(function (t) {
            const time = root.querySelector('[data-timer-time="' + t.id + '"]');
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
        finding = false;
        render();
        lookup(render);
        renderLogged();
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
