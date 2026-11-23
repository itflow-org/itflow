/*
 * ITFlow - calls on your desk phone, popping up as they ring (screen pops, functions/phone.php).
 *
 * While a page is in view it asks agent/ajax.php?phone_pop every few seconds for the calls to show:
 * ringing or being dialled, answered, or just ended. Each is a card at the bottom right - who it is
 * (the contact, else the name the phone showed, else the number), their client and open tickets,
 * and New Ticket; answered, how long you have been talking; ended, Add to Ticket once the call's
 * record is in from the phone system. The x puts a call away in every tab (localStorage). A page
 * in the background doesn't ask - it asks again as soon as it is looked at. Everything shown is set
 * as text.
 *
 * Loaded from includes/footer.php on agent pages, for agents with a desk phone set in
 * Your account > Preferences.
 */
(function () {
    'use strict';

    const script = document.currentScript;
    const user = script ? script.dataset.user : '';
    if (!user || !window.fetch || window.itflowPhonePop) {
        return;
    }
    window.itflowPhonePop = true;

    const ENDPOINT = '/agent/ajax.php?phone_pop=1';
    const POLL_MS = 3000;
    const RETRY_MS = 30000;
    const HIDDEN = 'itflow-phone-pop-hidden-' + user;   // the calls put away: their keys
    const STATES = {
        ringing: { label: 'Incoming call', icon: 'fa-phone-volume', tone: 'primary' },
        outgoing: { label: 'Calling', icon: 'fa-phone', tone: 'secondary' },
        answered: { label: 'On a call', icon: 'fa-phone', tone: 'success' },
        ended: { label: 'Call ended', icon: 'fa-phone-slash', tone: 'secondary' }
    };

    let calls = [];
    let fetchedAt = 0;
    let shown = '';
    let timer = null;
    let asking = false;
    let stopped = false;
    const hiddenHere = [];   // put away in this tab, should storage say no
    let drawn = [];          // the keys on screen - a call new to it slides in

    const stack = document.createElement('div');
    stack.className = 'itflow-phone-pop-stack';
    stack.setAttribute('aria-live', 'polite');
    document.body.appendChild(stack);

    function hiddenKeys() {
        try {
            const keys = JSON.parse(localStorage.getItem(HIDDEN) || '[]');
            return Array.isArray(keys) ? keys : [];
        } catch (e) {
            return [];
        }
    }

    function hide(key) {
        const keys = hiddenKeys().filter(function (each) { return each !== key; });
        keys.push(key);
        try {
            localStorage.setItem(HIDDEN, JSON.stringify(keys.slice(-30)));
        } catch (e) { /* private window - put away in this tab only */ }
        hiddenHere.push(key);
        render(true);
    }

    function isHidden(key) {
        return hiddenHere.indexOf(key) !== -1 || hiddenKeys().indexOf(key) !== -1;
    }

    function el(tag, className, text) {
        const node = document.createElement(tag);
        if (className) {
            node.className = className;
        }
        if (text !== undefined && text !== null) {
            node.textContent = String(text);
        }
        return node;
    }

    function icon(name, extra) {
        const node = el('i', 'fas fa-fw ' + name + (extra ? ' ' + extra : ''));
        node.setAttribute('aria-hidden', 'true');
        return node;
    }

    function link(href, text, className) {
        const node = el('a', className || '', text);
        node.href = href;
        return node;
    }

    function modalButton(url, text, iconName, className, size) {
        const node = el('a', 'btn btn-sm ajax-modal ' + className);
        node.href = '#';
        node.dataset.modalUrl = url;
        if (size) {
            node.dataset.modalSize = size;
        }
        node.append(icon(iconName, 'me-1'), document.createTextNode(text));
        return node;
    }

    // 0:42, 4:07, 1:02:15
    function clock(seconds) {
        seconds = Math.max(0, Math.floor(seconds));
        const hours = Math.floor(seconds / 3600);
        const minutes = Math.floor(seconds % 3600 / 60);
        const rest = String(seconds % 60).padStart(2, '0');
        return hours ? hours + ':' + String(minutes).padStart(2, '0') + ':' + rest : minutes + ':' + rest;
    }

    // What the card's clock says - ringing for, talking for, or how long it was
    function timeText(call) {
        const since = (Date.now() - fetchedAt) / 1000;
        if (call.state === 'answered' && call.talk_seconds !== null) {
            return clock(call.talk_seconds + since);
        }
        if (call.state === 'ended') {
            return call.talk_seconds !== null ? 'Talked ' + clock(call.talk_seconds) : 'Not answered';
        }
        return clock(call.seconds + since);
    }

    function card(call) {
        const state = STATES[call.state] || STATES.ringing;
        const wrap = el('div', 'card shadow itflow-phone-pop is-' + call.state + (drawn.indexOf(call.key) === -1 ? ' is-new' : ''));
        wrap.dataset.phonePop = call.key;
        wrap.setAttribute('role', 'status');

        const head = el('div', 'card-header d-flex align-items-center gap-2 py-2 text-' + state.tone + '-emphasis bg-' + state.tone + '-subtle');
        head.append(icon(state.icon, call.state === 'ringing' ? 'itflow-phone-pop-ring' : ''));
        head.append(el('span', 'fw-semibold', state.label));
        const time = el('span', 'small ms-auto' + (call.state === 'ended' ? '' : ' font-monospace'), timeText(call));
        time.dataset.phonePopTime = '';
        head.append(time);
        const close = el('button', 'btn-close btn-sm ms-1');
        close.type = 'button';
        close.title = 'Put it away';
        close.setAttribute('aria-label', 'Put it away');
        close.addEventListener('click', function () { hide(call.key); });
        head.append(close);
        wrap.append(head);

        const body = el('div', 'card-body py-2');
        const who = el('div', 'fw-semibold text-truncate');
        if (call.contact) {
            who.append(link(call.contact.url, call.contact.name, 'text-reset'));
        } else {
            who.textContent = call.name || call.number_text || 'Unknown number';
        }
        body.append(who);

        const about = el('div', 'small text-body-secondary text-truncate');
        if (call.contact && call.contact.title) {
            about.append(document.createTextNode(call.contact.title + ' - '));
        }
        if (call.client) {
            about.append(link(call.client.url, call.client.name, 'text-reset'));
        } else if (!call.contact) {
            about.append(document.createTextNode('Not a contact in ITFlow'));
        }
        if (about.childNodes.length) {
            body.append(about);
        }
        if (call.number_text && (call.contact || call.name)) {
            body.append(el('div', 'small text-body-secondary', call.number_text));
        }

        // Their open tickets - while it's on; once it has ended, putting it on one is what's left
        if (call.tickets.length && call.state !== 'ended') {
            const list = el('ul', 'list-unstyled small mt-2 mb-0');
            call.tickets.forEach(function (ticket) {
                const item = el('li', 'text-truncate');
                item.append(icon('fa-life-ring', 'text-secondary me-1'), link(ticket.url, ticket.ref, 'fw-semibold me-1'), document.createTextNode(ticket.subject));
                list.append(item);
            });
            body.append(list);
        }
        wrap.append(body);

        const foot = el('div', 'card-footer d-flex flex-wrap align-items-center gap-2 py-2');
        if (call.state === 'ended') {
            if (call.add) {
                foot.append(modalButton(call.add, call.ticket ? 'Add to ' + call.ticket.ref : 'Add to Ticket', 'fa-life-ring', 'btn-primary'));
            } else if (call.record && call.record.on_ticket) {
                foot.append(el('span', 'small text-success-emphasis', 'On a ticket'));
            } else if (!call.record) {
                foot.append(el('span', 'small text-body-secondary', 'Waiting for the call\'s record...'));
            }
            if (call.ticket && !call.add) {
                foot.append(link(call.ticket.url, call.ticket.ref, 'btn btn-sm btn-outline-secondary'));
            }
        } else if (call.ticket) {
            const made = link(call.ticket.url, call.ticket.ref, 'btn btn-sm btn-outline-secondary');
            made.prepend(icon('fa-life-ring', 'me-1'));
            foot.append(made);
        } else if (call.new_ticket) {
            foot.append(modalButton(call.new_ticket, 'New Ticket', 'fa-plus', 'btn-primary', call.new_ticket.indexOf('ticket_add.php') !== -1 ? 'lg' : ''));
        }
        if (foot.childNodes.length) {
            wrap.append(foot);
        }
        return wrap;
    }

    // Drawn again only when something other than the clocks changed, so a button isn't swapped out under a click
    function render(force) {
        const showing = calls.filter(function (call) { return !isHidden(call.key); });
        const signature = JSON.stringify(showing.map(function (call) {
            return [call.key, call.state, call.contact, call.client, call.tickets, call.ticket, call.record, call.add, call.new_ticket, call.talk_seconds !== null];
        }));
        if (!force && signature === shown) {
            stack.querySelectorAll('[data-phone-pop]').forEach(function (node) {
                const call = showing.find(function (each) { return each.key === node.dataset.phonePop; });
                const time = node.querySelector('[data-phone-pop-time]');
                if (call && time) {
                    time.textContent = timeText(call);
                }
            });
            return;
        }
        shown = signature;
        stack.replaceChildren.apply(stack, showing.map(card));
        drawn = showing.map(function (call) { return call.key; });
    }

    function schedule(ms) {
        clearTimeout(timer);
        if (!stopped && document.visibilityState === 'visible') {
            timer = setTimeout(ask, ms);
        }
    }

    function ask() {
        if (asking || stopped) {
            return;
        }
        asking = true;
        fetch(ENDPOINT, { credentials: 'same-origin', cache: 'no-store' })
            .then(function (res) {
                if (!res.ok) {
                    throw new Error('HTTP ' + res.status);
                }
                return res.json();
            })
            .then(function (data) {
                asking = false;
                if (!data || data.line === false) {
                    // No desk phone any more - nothing to ask for until the next page
                    stopped = true;
                    calls = [];
                    render(true);
                    return;
                }
                calls = Array.isArray(data.calls) ? data.calls : [];
                fetchedAt = Date.now();
                render(false);
                schedule(POLL_MS);
            })
            .catch(function () {
                asking = false;
                schedule(RETRY_MS);
            });
    }

    // The clocks
    setInterval(function () {
        if (calls.length) {
            render(false);
        }
    }, 1000);

    document.addEventListener('visibilitychange', function () {
        if (document.visibilityState === 'visible') {
            ask();
        } else {
            clearTimeout(timer);
        }
    });
    window.addEventListener('storage', function (e) {
        if (e.key === HIDDEN) {
            render(true);
        }
    });

    if (document.visibilityState === 'visible') {
        ask();
    }
})();
