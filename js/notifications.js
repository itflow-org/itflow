/*
 * ITFlow - notifications.
 *
 * The bell in the top bar opens a panel (a Bootstrap dropdown) with the latest
 * notifications, Unread and All tabs, grouped by day. Clicking one opens what it
 * is about and marks it read; the button on each row flips read / unread; "Mark
 * all as read" clears the lot. The unread badge refreshes every minute while the
 * tab is visible, and straight away when you come back to it.
 *
 * The full page, agent/notifications.php, renders the same rows in PHP; the row
 * behaviour below is delegated on document so it covers both.
 *
 * Data comes from agent/ajax.php (functions/notifications.php) and is rendered
 * with textContent only - notification text carries client names, email
 * subjects and other user-entered values.
 *
 * Loaded once per page from includes/footer.php.
 */
(function () {
    'use strict';

    if (window.itflowNotifications || typeof bootstrap === 'undefined') {
        return;
    }

    const ENDPOINT = '/agent/ajax.php';
    const POLL_MS = 60000;
    const TONES = ['primary', 'secondary', 'success', 'danger', 'warning', 'info'];

    const root = document.getElementById('itflowNotify');
    const csrf = root ? root.dataset.csrf : '';

    let panel = null;
    let list = null;
    let view = 'unread';
    let loadSeq = 0;
    let lastPoll = 0;
    let pollingStopped = false;

    /* ---------- small helpers ---------- */

    function el(tag, className, text) {
        const node = document.createElement(tag);
        if (className) {
            node.className = className;
        }
        if (text !== undefined && text !== null) {
            node.textContent = text;
        }
        return node;
    }

    function icon(name) {
        const i = el('i', 'fas fa-fw fa-' + (/^[a-z0-9-]+$/.test(name || '') ? name : 'bell'));
        i.setAttribute('aria-hidden', 'true');
        return i;
    }

    // Same-site paths only; the server already filters, this is belt and braces
    function safeUrl(url) {
        return typeof url === 'string' && url.charAt(0) === '/' && url.charAt(1) !== '/' && url.charAt(1) !== '\\' ? url : null;
    }

    function sameDay(a, b) {
        return a.getFullYear() === b.getFullYear() && a.getMonth() === b.getMonth() && a.getDate() === b.getDate();
    }

    function relativeTime(date) {
        const now = new Date();
        const minutes = Math.round((now - date) / 60000);
        if (minutes < 1) {
            return 'now';
        }
        if (minutes < 60) {
            return minutes + 'm';
        }
        if (minutes < 24 * 60 && sameDay(date, now)) {
            return Math.floor(minutes / 60) + 'h';
        }
        const yesterday = new Date(now);
        yesterday.setDate(now.getDate() - 1);
        if (sameDay(date, yesterday)) {
            return 'Yesterday';
        }
        if (minutes < 6 * 24 * 60) {
            return date.toLocaleDateString(undefined, { weekday: 'short' });
        }
        return date.toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: date.getFullYear() === now.getFullYear() ? undefined : 'numeric' });
    }

    function dayGroup(date) {
        const now = new Date();
        const yesterday = new Date(now);
        yesterday.setDate(now.getDate() - 1);
        if (sameDay(date, now)) {
            return 'Today';
        }
        if (sameDay(date, yesterday)) {
            return 'Yesterday';
        }
        return 'Earlier';
    }

    function refreshTimes(scope) {
        (scope || document).querySelectorAll('.itflow-notify-item time[datetime]').forEach(function (node) {
            const date = new Date(node.getAttribute('datetime'));
            if (!isNaN(date)) {
                node.textContent = relativeTime(date);
            }
        });
    }

    /* ---------- server calls ---------- */

    function getJson(params) {
        return fetch(ENDPOINT + '?' + new URLSearchParams(params).toString(), {
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json' }
        }).then(function (res) {
            // An expired session is redirected to the login page, which is HTML
            if (!res.ok || (res.headers.get('Content-Type') || '').indexOf('application/json') === -1) {
                throw new Error(res.ok ? 'session' : 'HTTP ' + res.status);
            }
            return res.json();
        });
    }

    function markForm(ids, state, all) {
        const form = new FormData();
        form.append('notifications_mark', '1');
        form.append('csrf_token', csrf);
        if (all) {
            form.append('all', '1');
        } else {
            form.append('state', state);
            ids.forEach(function (id) {
                form.append('ids[]', id);
            });
        }
        return form;
    }

    function postMark(ids, state, all) {
        return fetch(ENDPOINT, {
            method: 'POST',
            credentials: 'same-origin',
            body: markForm(ids, state, all)
        }).then(function (res) {
            if (!res.ok) {
                throw new Error('HTTP ' + res.status);
            }
            return res.json();
        }).then(function (data) {
            setCount(data.unread_count);
            return data;
        });
    }

    /* ---------- badge and counts ---------- */

    function setCount(count) {
        count = Math.max(0, parseInt(count, 10) || 0);
        const label = count > 99 ? '99+' : String(count);

        document.querySelectorAll('[data-notify-badge]').forEach(function (badge) {
            badge.textContent = label;
            badge.hidden = count === 0;
        });
        document.querySelectorAll('[data-notify-count]').forEach(function (node) {
            node.textContent = label;
            node.hidden = count === 0;
        });
        document.querySelectorAll('[data-notify-all]').forEach(function (button) {
            button.disabled = count === 0;
        });

        const toggle = root && root.querySelector('[data-bs-toggle="dropdown"]');
        if (toggle) {
            toggle.setAttribute('aria-label', count ? 'Notifications, ' + count + ' unread' : 'Notifications');
        }
    }

    function poll(force) {
        if (pollingStopped || !root) {
            return;
        }
        const now = Date.now();
        if (!force && now - lastPoll < 10000) {
            return;
        }
        lastPoll = now;

        const before = currentCount();
        getJson({ notifications_count: 1 })
            .then(function (data) {
                setCount(data.unread_count);
                // Something new arrived while the panel is open: show it
                if (panel && root.querySelector('.dropdown-menu.show') && data.unread_count !== before) {
                    load();
                }
            })
            .catch(function (err) {
                if (err.message === 'session') {
                    pollingStopped = true;
                }
            });
    }

    function currentCount() {
        const badge = document.querySelector('[data-notify-badge]');
        if (!badge || badge.hidden) {
            return 0;
        }
        return parseInt(badge.textContent, 10) || 0;
    }

    /* ---------- one notification row ---------- */

    function renderItem(item) {
        const row = el('div', 'itflow-notify-item' + (item.unread ? ' is-unread' : ''));
        row.dataset.id = item.id;
        row.setAttribute('role', 'listitem');

        const url = safeUrl(item.url);
        const link = el('a', 'itflow-notify-link dropdown-item');
        link.href = url || '#';
        if (!url) {
            link.dataset.noLink = '1';
        }

        const tone = TONES.indexOf(item.tone) !== -1 ? item.tone : 'secondary';
        const badge = el('span', 'itflow-notify-icon bg-' + tone + '-subtle text-' + tone + '-emphasis');
        badge.append(icon(item.icon));

        const body = el('span', 'itflow-notify-body');
        const top = el('span', 'itflow-notify-top');
        top.append(el('span', 'itflow-notify-type', item.type));
        if (item.time) {
            const time = el('time', 'itflow-notify-time');
            time.setAttribute('datetime', item.time);
            time.title = item.time_full || '';
            time.textContent = relativeTime(new Date(item.time));
            top.append(time);
        }
        body.append(top, el('span', 'itflow-notify-text', item.text));
        if (item.client) {
            const client = el('span', 'itflow-notify-client');
            client.append(icon('building'), document.createTextNode(item.client));
            body.append(client);
        }

        link.append(badge, body);

        const toggle = el('button', 'btn btn-sm itflow-notify-toggle');
        toggle.type = 'button';
        setToggle(toggle, item.unread);
        toggle.append(icon(item.unread ? 'check' : 'undo'));

        row.append(link, toggle);
        return row;
    }

    function setToggle(button, unread) {
        const label = unread ? 'Mark as read' : 'Mark as unread';
        button.title = label;
        button.setAttribute('aria-label', label);
        const i = button.querySelector('i');
        if (i) {
            i.className = 'fas fa-fw fa-' + (unread ? 'check' : 'undo');
        }
    }

    function setRowRead(row, read) {
        row.classList.toggle('is-unread', !read);
        const button = row.querySelector('.itflow-notify-toggle');
        if (button) {
            setToggle(button, !read);
        }
    }

    /* ---------- the panel ---------- */

    function buildPanel() {
        if (panel || !root) {
            return;
        }
        panel = root.querySelector('.itflow-notify-panel');

        // Static skeleton only - no data goes through this string
        panel.innerHTML =
            '<div class="itflow-notify-head">' +
                '<span class="fw-semibold">Notifications</span>' +
                '<button type="button" class="btn btn-link btn-sm p-0 ms-auto text-decoration-none" data-notify-all>' +
                    '<i class="fas fa-check-double me-1" aria-hidden="true"></i>Mark all as read</button>' +
            '</div>' +
            '<div class="nav nav-underline itflow-notify-tabs" role="tablist">' +
                '<button type="button" class="nav-link active" role="tab" aria-selected="true" data-notify-view="unread">' +
                    'Unread <span class="badge rounded-pill text-bg-secondary ms-1" data-notify-count></span></button>' +
                '<button type="button" class="nav-link" role="tab" aria-selected="false" data-notify-view="all">All</button>' +
            '</div>' +
            '<div class="itflow-notify-list" role="list" aria-label="Notifications"></div>' +
            '<a class="itflow-notify-foot" href="/agent/notifications.php">View all notifications</a>';

        list = panel.querySelector('.itflow-notify-list');

        panel.querySelectorAll('[data-notify-view]').forEach(function (tab) {
            tab.addEventListener('click', function () {
                if (view === tab.dataset.notifyView) {
                    return;
                }
                view = tab.dataset.notifyView;
                panel.querySelectorAll('[data-notify-view]').forEach(function (t) {
                    const on = t === tab;
                    t.classList.toggle('active', on);
                    t.setAttribute('aria-selected', on ? 'true' : 'false');
                });
                load();
            });
        });

        setCount(currentCount());
    }

    function message(iconName, title, text, retry) {
        const box = el('div', 'itflow-notify-empty');
        const i = icon(iconName);
        i.classList.add('fa-2x', 'mb-2');
        box.append(i, el('div', 'fw-semibold', title));
        if (text) {
            box.append(el('div', 'small', text));
        }
        if (retry) {
            const button = el('button', 'btn btn-sm btn-outline-secondary mt-2', 'Try again');
            button.type = 'button';
            button.addEventListener('click', load);
            box.append(button);
        }
        return box;
    }

    function load() {
        if (!list) {
            return;
        }
        const mine = ++loadSeq;
        const spinner = setTimeout(function () {
            if (mine === loadSeq && !list.children.length) {
                list.replaceChildren(el('div', 'itflow-notify-empty', 'Loading…'));
            }
        }, 150);

        getJson({ notifications_feed: 1, view: view })
            .then(function (data) {
                if (mine !== loadSeq) {
                    return;
                }
                setCount(data.unread_count);
                render(data.items || []);
            })
            .catch(function (err) {
                if (mine !== loadSeq) {
                    return;
                }
                list.replaceChildren(err.message === 'session'
                    ? message('lock', 'Your session has ended', 'Reload the page to sign in again.')
                    : message('exclamation-triangle', 'Couldn’t load notifications', null, true));
            })
            .finally(function () {
                clearTimeout(spinner);
            });
    }

    function render(items) {
        list.replaceChildren();

        if (!items.length) {
            list.append(view === 'unread'
                ? message('check-circle', 'You’re all caught up', 'New notifications will show up here.')
                : message('bell-slash', 'No notifications yet'));
            return;
        }

        let group = null;
        items.forEach(function (item) {
            const label = item.time ? dayGroup(new Date(item.time)) : 'Earlier';
            if (label !== group) {
                group = label;
                const heading = el('div', 'itflow-notify-group', label);
                heading.setAttribute('role', 'presentation');
                list.append(heading);
            }
            list.append(renderItem(item));
        });
    }

    /* ---------- row behaviour, on the panel and the full page ---------- */

    document.addEventListener('click', function (e) {
        const toggle = e.target.closest('.itflow-notify-item .itflow-notify-toggle');
        if (toggle) {
            e.preventDefault();
            const row = toggle.closest('.itflow-notify-item');
            const makeRead = row.classList.contains('is-unread');
            setRowRead(row, makeRead);
            postMark([row.dataset.id], makeRead ? 'read' : 'unread').catch(function () {
                setRowRead(row, !makeRead);
                if (typeof itflowToast === 'function') {
                    itflowToast('Couldn’t update the notification', 'danger');
                }
            });
            return;
        }

        const link = e.target.closest('.itflow-notify-item .itflow-notify-link');
        if (!link) {
            return;
        }
        const row = link.closest('.itflow-notify-item');
        const unread = row.classList.contains('is-unread');

        if (link.dataset.noLink) {
            // Nothing to open - clicking it is how you acknowledge it
            e.preventDefault();
            if (unread) {
                setRowRead(row, true);
                postMark([row.dataset.id], 'read').catch(function () {
                    setRowRead(row, false);
                });
            }
            return;
        }

        if (!unread) {
            return;
        }

        const newTab = e.ctrlKey || e.metaKey || e.shiftKey || e.button === 1;
        setRowRead(row, true);
        if (newTab) {
            postMark([row.dataset.id], 'read').catch(function () {
                setRowRead(row, false);
            });
        } else if (navigator.sendBeacon) {
            // This page is about to unload; a beacon still gets there
            navigator.sendBeacon(ENDPOINT, markForm([row.dataset.id], 'read', false));
        } else {
            postMark([row.dataset.id], 'read');
        }
    });

    // Middle click does not fire "click"
    document.addEventListener('auxclick', function (e) {
        if (e.button !== 1) {
            return;
        }
        const link = e.target.closest('.itflow-notify-item .itflow-notify-link');
        if (link && !link.dataset.noLink) {
            const row = link.closest('.itflow-notify-item');
            if (row.classList.contains('is-unread')) {
                setRowRead(row, true);
                postMark([row.dataset.id], 'read').catch(function () {
                    setRowRead(row, false);
                });
            }
        }
    });

    document.addEventListener('click', function (e) {
        const button = e.target.closest('[data-notify-all]');
        if (!button || button.disabled) {
            return;
        }
        e.preventDefault();
        button.disabled = true;
        postMark([], 'read', true)
            .then(function () {
                document.querySelectorAll('.itflow-notify-item.is-unread').forEach(function (row) {
                    setRowRead(row, true);
                });
                // The full page lists unread by default; reload so it matches
                if (button.dataset.notifyReload) {
                    window.location.reload();
                }
            })
            .catch(function () {
                button.disabled = false;
                if (typeof itflowToast === 'function') {
                    itflowToast('Couldn’t mark notifications as read', 'danger');
                }
            });
    });

    /* ---------- wiring ---------- */

    if (root) {
        root.addEventListener('show.bs.dropdown', function () {
            buildPanel();
            load();
        });

        setInterval(function () {
            if (document.visibilityState === 'visible') {
                poll(true);
            }
        }, POLL_MS);

        document.addEventListener('visibilitychange', function () {
            if (document.visibilityState === 'visible') {
                poll(false);
            }
        });
    }

    refreshTimes(document);
    setInterval(function () {
        refreshTimes(document);
    }, 60000);

    window.itflowNotifications = { refresh: function () { poll(true); } };
})();
