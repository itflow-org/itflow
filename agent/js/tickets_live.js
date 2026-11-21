/*
 * ITFlow - a tickets list that keeps itself up to date (agent/ticket_list.php).
 *
 * Once a minute while the tab is in view, and straight away on coming back to it, the page
 * fetches itself again - same filters, same view, same page - and compares:
 *
 *   - a row whose data-ticket-sig changed (a reply, a new status, someone else assigned...) is
 *     swapped in where it is, unless it is ticked or has a menu open - those wait for Show;
 *   - tickets that came into this view, or left it, are counted in a bar above the list, and its
 *     Show button loads the list again;
 *   - the counts on the view pills are brought up to date.
 *
 * Rows aren't added or taken away under the pointer - only the bar says so. Everything in a
 * row is delegated on document (quick picks, quick looks, ticks, the last-reply popover), so a
 * swapped row works as before.
 */
(function () {
    'use strict';

    const POLL_MS = 60000;
    const bar = document.querySelector('[data-tickets-live-bar]');
    const table = document.querySelector('.itflow-ticket-list');
    if (!bar || !table || !window.fetch || !window.DOMParser) {
        return;
    }

    let lastPoll = Date.now();
    let busy = false;
    let stopped = false;

    function rowsById(root) {
        const rows = {};
        root.querySelectorAll('.itflow-ticket-list tbody > tr[data-ticket-id]').forEach(function (tr) {
            rows[tr.dataset.ticketId] = tr;
        });
        return rows;
    }

    // Ticked, a menu or picker open in it, or being typed in - left alone until Show
    function busyRow(tr) {
        return !!tr.querySelector('input.bulk-select:checked, .dropdown-menu.show, [aria-expanded="true"]')
            || tr.contains(document.activeElement);
    }

    function plural(n, one, many) {
        return n + ' ' + (n === 1 ? one : many);
    }

    function updatePills(doc) {
        // Not More (js/tab_fold.js) - it isn't in the fetched page
        const now = document.querySelectorAll('.itflow-asset-tabs .nav-link:not(.itflow-tabs-more-toggle)');
        const fresh = doc.querySelectorAll('.itflow-asset-tabs .nav-link');
        if (now.length !== fresh.length) {
            return;
        }
        now.forEach(function (link, i) {
            const badge = link.querySelector('.badge');
            const freshBadge = fresh[i].querySelector('.badge');
            if (badge && freshBadge && badge.textContent !== freshBadge.textContent) {
                badge.textContent = freshBadge.textContent;
                badge.className = freshBadge.className;
            }
        });
    }

    function compare(doc) {
        const now = rowsById(document);
        const fresh = rowsById(doc);
        let added = 0;
        let gone = 0;
        let waiting = 0;

        Object.keys(fresh).forEach(function (id) {
            const tr = now[id];
            if (!tr) {
                added++;
                return;
            }
            // Back in the view after leaving it
            tr.classList.remove('itflow-row-gone');
            if (tr.dataset.ticketSig === fresh[id].dataset.ticketSig) {
                return;
            }
            if (busyRow(tr)) {
                waiting++;
                return;
            }
            const replacement = document.importNode(fresh[id], true);
            replacement.classList.add('itflow-row-updated');
            tr.replaceWith(replacement);
        });
        Object.keys(now).forEach(function (id) {
            if (!fresh[id] && !now[id].classList.contains('itflow-row-gone')) {
                gone++;
                now[id].classList.add('itflow-row-gone');
            }
        });

        // Counted from the page as it stands, so a second poll doesn't count the same ones twice
        const goneTotal = document.querySelectorAll('.itflow-ticket-list tbody > tr.itflow-row-gone').length;
        const parts = [];
        if (added) {
            parts.push(plural(added, 'new ticket', 'new tickets'));
        }
        if (goneTotal) {
            parts.push(plural(goneTotal, 'ticket', 'tickets') + ' no longer in this view');
        }
        if (waiting) {
            parts.push(plural(waiting, 'ticked ticket has', 'ticked tickets have') + ' changed');
        }
        bar.querySelector('[data-tickets-live-text]').textContent = parts.join(', ');
        bar.hidden = !parts.length;

        updatePills(doc);
    }

    function poll() {
        if (busy || stopped || document.visibilityState !== 'visible') {
            return;
        }
        busy = true;
        lastPoll = Date.now();
        fetch(window.location.href, { credentials: 'same-origin' })
            .then(function (r) {
                // Signed out - the login page came back instead; stop rather than guess
                if (!r.ok || r.redirected) {
                    stopped = true;
                    return null;
                }
                return r.text();
            })
            .then(function (html) {
                if (!html) {
                    return;
                }
                const doc = new DOMParser().parseFromString(html, 'text/html');
                if (!doc.querySelector('.itflow-ticket-list')) {
                    stopped = true;
                    return;
                }
                compare(doc);
            })
            .catch(function () {})
            .finally(function () {
                busy = false;
            });
    }

    bar.querySelector('[data-tickets-live-show]').addEventListener('click', function () {
        window.location.reload();
    });

    setInterval(poll, POLL_MS);
    document.addEventListener('visibilitychange', function () {
        if (document.visibilityState === 'visible' && Date.now() - lastPoll > POLL_MS / 2) {
            poll();
        }
    });
})();
