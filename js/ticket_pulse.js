/*
 * ITFlow - your open tickets in the browser tab's title, and desktop notifications.
 *
 * Every agent page asks agent/ajax.php?ticket_pulse once a minute - one tab per browser asks, the
 * others hear the answer through localStorage - and puts the number of open tickets assigned to
 * you (the Mine count) in front of the tab's title: "(3) Ravenwood MSP - Tickets". It keeps going
 * in the background, which is the point: the count shows on the tab while you are in another one.
 *
 * Desktop notifications are each browser's own choice, turned on and off from the bell's panel
 * ([data-desktop-notify] - js/notifications.js draws it), which asks the browser's permission.
 * Once on, a ticket newly assigned to you and a client's reply on one of yours show as desktop
 * notifications - but only while no ITFlow tab has the focus: when you are already in ITFlow the
 * bell and the title say enough. Clicking one opens the ticket. Text only, from textContent-safe
 * strings - a notification shows its title and body as plain text anyway.
 *
 * Loaded from includes/footer.php for agents who can see tickets.
 */
(function () {
    'use strict';

    const script = document.currentScript;
    const user = script ? script.dataset.user : '';
    if (!user || !window.fetch || window.itflowPulse) {
        return;
    }

    const ENDPOINT = '/agent/ajax.php?ticket_pulse=1';
    const POLL_MS = 60000;
    const KEY = 'itflow-pulse-' + user;                  // the last answer: {at, count, ids, replyMax}
    const LOCK = 'itflow-pulse-lock-' + user;            // a tab is asking right now
    const DESKTOP = 'itflow-desktop-notify-' + user;     // '1' - desktop notifications on in this browser
    const FOCUS = 'itflow-focus-at';                     // when an ITFlow tab last had the focus
    const baseTitle = document.title;
    let timer = null;

    function store(key, value) {
        try {
            localStorage.setItem(key, typeof value === 'string' ? value : JSON.stringify(value));
        } catch (e) { /* private window - each tab asks for itself */ }
    }

    function read(key) {
        try {
            return localStorage.getItem(key);
        } catch (e) {
            return null;
        }
    }

    function lastAnswer() {
        try {
            return JSON.parse(read(KEY) || 'null');
        } catch (e) {
            return null;
        }
    }

    function setTitle(count) {
        count = Number(count) || 0;
        document.title = (count > 0 ? '(' + count + ') ' : '') + baseTitle;
    }

    function supported() {
        return 'Notification' in window;
    }

    function desktopOn() {
        return read(DESKTOP) === '1' && supported() && Notification.permission === 'granted';
    }

    function inItflow() {
        return document.hasFocus() || Date.now() - Number(read(FOCUS) || 0) < 10000;
    }

    function show(title, body, tag, url) {
        try {
            const icon = document.querySelector('link[rel~="icon"]');
            const n = new Notification(title, { body: body, tag: tag, icon: icon ? icon.href : undefined });
            n.onclick = function () {
                window.focus();
                if (url) {
                    window.location.href = url;
                }
                n.close();
            };
        } catch (e) { /* a phone browser wants a service worker for these - none here */ }
    }

    function notify(before, answer) {
        // A first answer, or one after a long gap (the computer slept), only sets where things stand
        if (!before || !Array.isArray(before.ids) || Date.now() - before.at > 10 * 60000 || inItflow()) {
            return;
        }

        (answer.replies || []).slice(0, 3).forEach(function (reply) {
            show((reply.who || 'The client') + ' replied - ' + reply.ref, reply.subject + (reply.text ? '\n' + reply.text : ''), 'itflow-reply-' + reply.id, reply.url);
        });

        const known = new Set(before.ids);
        const fresh = (answer.ids || []).filter(function (id) {
            return !known.has(id);
        });
        if (!fresh.length) {
            return;
        }
        if (fresh.length > 5) {
            show('Assigned to you', fresh.length + ' tickets', 'itflow-assigned-many', '/agent/tickets.php?show=mine');
            return;
        }
        fetch(ENDPOINT + '&about=' + fresh.join(','), { credentials: 'same-origin' })
            .then(function (r) { return r.ok ? r.json() : null; })
            .then(function (about) {
                ((about && about.about) || []).forEach(function (ticket) {
                    show('Assigned to you - ' + ticket.ref, ticket.subject + '\n' + ticket.client, 'itflow-assigned-' + ticket.id, ticket.url);
                });
            })
            .catch(function () {});
    }

    function ask(force) {
        const now = Date.now();
        const before = lastAnswer();
        // Another tab asked a moment ago - its answer is the one to show
        if (!force && before && now - before.at < POLL_MS - 5000) {
            setTitle(before.count);
            return;
        }
        if (now - Number(read(LOCK) || 0) < 15000) {
            return;
        }
        store(LOCK, String(now));

        const desktop = desktopOn();
        const url = ENDPOINT + (desktop ? '&desktop=1&since_reply=' + ((before && before.replyMax) || 0) : '');
        fetch(url, { credentials: 'same-origin' })
            .then(function (r) {
                // Signed out - the login page came back; anything else, try again next time
                if (r.redirected) {
                    return { ok: false };
                }
                return r.ok ? r.json() : null;
            })
            .then(function (answer) {
                if (!answer) {
                    return;
                }
                if (!answer.ok) {
                    clearInterval(timer);
                    setTitle(0);
                    return;
                }
                store(KEY, {
                    at: Date.now(),
                    count: answer.count,
                    ids: desktop ? answer.ids : null,
                    replyMax: desktop ? answer.reply_max : 0
                });
                setTitle(answer.count);
                if (desktop) {
                    notify(before, answer);
                }
            })
            .catch(function () {})
            .finally(function () {
                store(LOCK, '0');
            });
    }

    /* ---------- the switch in the bell's panel ---------- */

    function label(button) {
        if (!supported()) {
            button.hidden = true;
            return;
        }
        button.hidden = false;
        button.textContent = '';
        const i = document.createElement('i');
        i.className = 'fas fa-fw fa-desktop me-1';
        i.setAttribute('aria-hidden', 'true');
        button.append(i, desktopOn() ? 'Turn off desktop notifications'
            : (Notification.permission === 'denied' ? 'Desktop notifications are blocked in this browser' : 'Turn on desktop notifications'));
    }

    function toast(text, type) {
        if (typeof itflowToast === 'function') {
            itflowToast(text, type || 'success');
        }
    }

    document.addEventListener('shown.bs.dropdown', function (e) {
        const bell = e.target.closest && e.target.closest('#itflowNotify');
        const button = bell && bell.querySelector('[data-desktop-notify]');
        if (button) {
            label(button);
        }
    });

    document.addEventListener('click', function (e) {
        const button = e.target.closest('[data-desktop-notify]');
        if (!button || !supported()) {
            return;
        }
        e.preventDefault();
        if (desktopOn()) {
            store(DESKTOP, '0');
            label(button);
            toast('Desktop notifications are off in this browser');
            return;
        }
        if (Notification.permission === 'denied') {
            toast('This browser blocks notifications from ITFlow - allow them in its settings for this site, then try again', 'warning');
            return;
        }
        Notification.requestPermission().then(function (permission) {
            if (permission === 'granted') {
                store(DESKTOP, '1');
                toast('Desktop notifications are on in this browser');
                show('Desktop notifications are on', 'Tickets assigned to you and client replies on yours show here while you are away from ITFlow.', 'itflow-desktop-on');
                ask(true);
            }
            label(button);
        });
    });

    /* ---------- wiring ---------- */

    function beat() {
        if (document.hasFocus()) {
            store(FOCUS, String(Date.now()));
        }
    }
    window.addEventListener('focus', beat);
    setInterval(beat, 5000);
    beat();

    // Another tab's answer
    window.addEventListener('storage', function (e) {
        if (e.key === KEY && e.newValue) {
            try {
                setTitle(JSON.parse(e.newValue).count);
            } catch (x) { /* ignore */ }
        }
    });

    const known = lastAnswer();
    if (known && Date.now() - known.at < 5 * 60000) {
        setTitle(known.count);
    }
    // A moment apart, so tabs opened together don't all ask at once
    setTimeout(ask, 300 + Math.floor(Math.random() * 1500));
    timer = setInterval(ask, 15000);

    window.itflowPulse = { ask: ask };
})();
