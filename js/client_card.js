/*
 * ITFlow - a client at a glance. Rest the pointer on a client's name - a link to its overview, or
 * one marked [data-client-card] on the lists - and a card shows its primary contact and how to
 * reach them, its main site's phone and address, and its open tickets
 * (agent/ajax.php?client_card). The card stays while the pointer is on it, so its links can be
 * clicked. Built with textContent - the values are what people typed.
 *
 * Loaded on every agent page from includes/footer.php; delegated, so names in windows work too.
 */
(function () {
    'use strict';

    if (window.itflowClientCard || !window.matchMedia('(hover: hover)').matches) {
        return;
    }
    window.itflowClientCard = true;

    const SHOW_MS = 500;
    const HIDE_MS = 250;
    const cache = {};
    let showTimer = null;
    let hideTimer = null;
    let current = null;
    let popover = null;

    function clientOf(link) {
        if (link.dataset.clientCard) {
            return link.dataset.clientCard;
        }
        const match = (link.getAttribute('href') || '').match(/client_overview\.php\?client_id=(\d+)/);
        return match ? match[1] : '';
    }

    // Names in the menus, the path and the client switcher are where you go - no card there
    function eligible(link) {
        return !link.closest('.app-sidebar, .breadcrumb, .dropdown-menu, .nav, .popover, .itflow-client-card');
    }

    function el(tag, className, text) {
        const node = document.createElement(tag);
        if (className) {
            node.className = className;
        }
        if (text) {
            node.textContent = text;
        }
        return node;
    }

    function line(icon, text, href) {
        const row = el('div', 'itflow-client-card-line');
        const i = el('i', 'fas fa-fw ' + icon + ' text-body-secondary');
        i.setAttribute('aria-hidden', 'true');
        row.appendChild(i);
        if (href) {
            const a = el('a', '', text);
            a.href = href;
            row.appendChild(a);
        } else {
            row.appendChild(el('span', '', text));
        }
        return row;
    }

    function build(card) {
        const box = el('div', 'itflow-client-card');
        const head = el('a', 'itflow-client-card-name', card.name);
        head.href = '/agent/' + card.url;
        box.appendChild(head);
        if (card.type || card.archived) {
            box.appendChild(el('div', 'small text-body-secondary', [card.type, card.archived ? 'Archived' : ''].filter(Boolean).join(' · ')));
        }
        if (card.contact) {
            const who = el('div', 'itflow-client-card-section');
            const name = el('a', 'fw-semibold', card.contact.name);
            name.href = '/agent/' + card.contact.url;
            who.appendChild(name);
            if (card.contact.title) {
                who.appendChild(el('span', 'small text-body-secondary ms-1', card.contact.title));
            }
            if (card.contact.phone) {
                who.appendChild(line('fa-phone', card.contact.phone, card.contact.dial ? 'tel:' + card.contact.dial : ''));
            }
            if (card.contact.email) {
                who.appendChild(line('fa-envelope', card.contact.email, 'mailto:' + card.contact.email));
            }
            box.appendChild(who);
        }
        if (card.site && (card.site.phone || card.site.address)) {
            const site = el('div', 'itflow-client-card-section');
            if (card.site.phone) {
                site.appendChild(line('fa-building', card.site.phone, card.site.dial ? 'tel:' + card.site.dial : ''));
            }
            if (card.site.address) {
                site.appendChild(line('fa-map-marker-alt', card.site.address));
            }
            box.appendChild(site);
        }
        if (card.open_tickets !== null && card.open_tickets !== undefined) {
            const tickets = el('div', 'itflow-client-card-section');
            tickets.appendChild(line('fa-life-ring', card.open_tickets === 1 ? '1 open ticket' : card.open_tickets + ' open tickets', '/agent/' + card.tickets_url));
            box.appendChild(tickets);
        }
        return box;
    }

    function hide() {
        clearTimeout(showTimer);
        clearTimeout(hideTimer);
        if (popover) {
            popover.dispose();
            popover = null;
        }
        if (current && current.dataset.clientCardTitle !== undefined) {
            current.setAttribute('title', current.dataset.clientCardTitle);
            delete current.dataset.clientCardTitle;
        }
        current = null;
    }

    function show(link, card) {
        if (current !== link || !card || !card.ok || typeof bootstrap === 'undefined') {
            return;
        }
        popover = new bootstrap.Popover(link, {
            trigger: 'manual',
            placement: 'bottom',
            fallbackPlacements: ['top', 'right', 'left'],
            container: 'body',
            html: true,
            sanitize: false,
            content: build(card),
            customClass: 'itflow-client-card-popover'
        });
        popover.show();
        const tip = popover.tip;
        if (tip) {
            tip.addEventListener('mouseenter', function () { clearTimeout(hideTimer); });
            tip.addEventListener('mouseleave', function () { hideTimer = setTimeout(hide, HIDE_MS); });
        }
    }

    document.addEventListener('mouseover', function (e) {
        const link = e.target.closest('a[data-client-card], a[href*="client_overview.php?client_id="]');
        if (!link || !eligible(link)) {
            return;
        }
        if (link === current) {
            clearTimeout(hideTimer);
            return;
        }
        hide();
        const id = clientOf(link);
        if (!id) {
            return;
        }
        current = link;
        if (link.hasAttribute('title')) {
            link.dataset.clientCardTitle = link.getAttribute('title');
            link.removeAttribute('title');
        }
        showTimer = setTimeout(function () {
            if (cache[id]) {
                show(link, cache[id]);
                return;
            }
            fetch('/agent/ajax.php?client_card=' + encodeURIComponent(id), { credentials: 'same-origin' })
                .then(function (r) { return r.ok ? r.json() : null; })
                .then(function (card) {
                    if (card && card.ok) {
                        cache[id] = card;
                    }
                    show(link, card);
                })
                .catch(function () {});
        }, SHOW_MS);
    });

    document.addEventListener('mouseout', function (e) {
        if (!current || !current.contains(e.target) || current.contains(e.relatedTarget)) {
            return;
        }
        clearTimeout(showTimer);
        hideTimer = setTimeout(hide, HIDE_MS);
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && popover) {
            hide();
        }
    });
    document.addEventListener('scroll', function (e) {
        if (popover && !(popover.tip && popover.tip.contains(e.target))) {
            hide();
        }
    }, true);
})();
