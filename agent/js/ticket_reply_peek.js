/*
 * ITFlow - the last reply on a ticket, on the tickets list (agent/ticket_list.php).
 *
 * Rest the pointer on a ticket's Activity cell ([data-reply-peek]) and a popover shows the last
 * reply - who, when, and what it said, as text - from agent/ajax.php?ticket_reply_peek. With no
 * replies yet it shows what the ticket says. Text only: it goes in with textContent.
 *
 * Delegated on document, so rows agent/js/tickets_live.js swaps in work too.
 */
(function () {
    'use strict';

    // Loaded ahead of the footer's Bootstrap - it is looked for when a popover is wanted
    if (!window.matchMedia('(hover: hover)').matches) {
        return;
    }

    const DELAY_MS = 350;
    const cache = {};
    let timer = null;
    let current = null;
    let popover = null;

    function hide() {
        clearTimeout(timer);
        timer = null;
        if (popover) {
            popover.dispose();
            popover = null;
        }
        if (current && current.dataset.peekTitle !== undefined) {
            // The cell's own tooltip back
            const titled = current.querySelector('[data-peek-titled]');
            if (titled) {
                titled.setAttribute('title', current.dataset.peekTitle);
                titled.removeAttribute('data-peek-titled');
            }
            delete current.dataset.peekTitle;
        }
        current = null;
    }

    function content(peek) {
        const box = document.createElement('div');
        const head = document.createElement('div');
        head.className = 'itflow-reply-peek-head';
        head.textContent = peek.label + (peek.who ? ' - ' + peek.who : '') + ' · ' + peek.when;
        head.title = peek.at;
        const body = document.createElement('div');
        body.className = 'itflow-reply-peek-text';
        body.textContent = peek.text || 'Nothing written - a picture or a file.';
        box.append(head, body);
        return box;
    }

    function show(cell, peek) {
        if (current !== cell || !peek || !peek.ok || typeof bootstrap === 'undefined') {
            return;
        }
        popover = new bootstrap.Popover(cell, {
            trigger: 'manual',
            placement: 'left',
            fallbackPlacements: ['bottom', 'top'],
            container: 'body',
            html: true,
            sanitize: false,
            content: content(peek),
            customClass: 'itflow-reply-peek'
        });
        popover.show();
    }

    function load(cell) {
        const id = cell.dataset.replyPeek;
        const row = cell.closest('tr');
        const key = id + ':' + (row ? row.dataset.ticketSig || '' : '');
        if (cache[key]) {
            show(cell, cache[key]);
            return;
        }
        fetch('/agent/ajax.php?ticket_reply_peek=' + encodeURIComponent(id), { credentials: 'same-origin' })
            .then(function (r) { return r.ok ? r.json() : null; })
            .then(function (peek) {
                if (peek && peek.ok) {
                    cache[key] = peek;
                }
                show(cell, peek);
            })
            .catch(function () {});
    }

    document.addEventListener('mouseover', function (e) {
        const cell = e.target.closest('[data-reply-peek]');
        if (cell === current) {
            return;
        }
        hide();
        if (!cell) {
            return;
        }
        current = cell;
        // The native tooltip on the time would sit on top of the popover
        const titled = cell.querySelector('[title]');
        if (titled) {
            cell.dataset.peekTitle = titled.getAttribute('title');
            titled.setAttribute('data-peek-titled', '');
            titled.removeAttribute('title');
        }
        timer = setTimeout(function () {
            load(cell);
        }, DELAY_MS);
    });

    document.addEventListener('mouseleave', function (e) {
        if (e.target === document.documentElement) {
            hide();
        }
    }, true);

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            hide();
        }
    });

    document.addEventListener('scroll', hide, true);
    window.addEventListener('blur', hide);
})();
