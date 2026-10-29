/*
 * ITFlow - client switcher.
 *
 * The client card at the top of the client side nav opens a dropdown listing
 * the agent's clients, most recently opened first, with a box to filter them.
 * The list comes from ajax.php?get_active_clients the first time it opens and
 * is filtered here as you type. Picking a client goes to the same page for
 * that client - the side nav works out which page that is (data-switch-url).
 *
 * Keys: type to filter, Down to move into the list, Enter to open the first
 * match, Esc to close.
 */
(function () {
    'use strict';

    const root = document.querySelector('[data-client-switcher]');
    if (!root) {
        return;
    }

    const input = root.querySelector('[data-client-switcher-search]');
    const list = root.querySelector('[data-client-switcher-list]');
    const currentId = parseInt(root.dataset.clientId, 10);
    const switchUrl = root.dataset.switchUrl;
    const tones = ['primary', 'success', 'info', 'warning', 'secondary'];
    const limit = 50;

    let clients = null;
    let loading = null;

    function message(text) {
        list.textContent = '';
        const div = document.createElement('div');
        div.className = 'itflow-client-switcher-empty';
        div.textContent = text;
        list.appendChild(div);
    }

    function item(c) {
        const id = parseInt(c.client_id, 10);
        const link = document.createElement('a');
        link.className = 'dropdown-item itflow-client-switcher-item';
        link.href = switchUrl + id;
        if (id === currentId) {
            link.classList.add('active');
            link.setAttribute('aria-current', 'true');
        }

        const tile = document.createElement('span');
        const tone = tones.indexOf(c.client_tone) !== -1 ? c.client_tone : 'secondary';
        tile.className = 'itflow-client-avatar itflow-client-avatar-xs text-bg-' + tone;
        tile.setAttribute('aria-hidden', 'true');
        tile.textContent = c.client_initials;

        const name = document.createElement('span');
        name.className = 'text-truncate';
        name.textContent = c.client_name;

        link.append(tile, name);
        return link;
    }

    function render() {
        if (clients === null) {
            message('Loading clients...');
            return;
        }

        const typed = input.value.trim();
        const term = typed.toLowerCase();
        const matches = clients.filter(function (c) {
            return !term || c.client_name.toLowerCase().indexOf(term) !== -1 || c.client_initials.toLowerCase() === term;
        });

        if (!matches.length) {
            message(term ? 'No clients match "' + typed + '"' : 'No clients');
            return;
        }

        list.textContent = '';
        matches.slice(0, limit).forEach(function (c) {
            list.appendChild(item(c));
        });
        if (matches.length > limit) {
            const more = document.createElement('div');
            more.className = 'itflow-client-switcher-empty';
            more.textContent = 'Keep typing to narrow down ' + (matches.length - limit) + ' more';
            list.appendChild(more);
        }
    }

    function load() {
        if (clients !== null || loading) {
            return;
        }
        loading = fetch('/agent/ajax.php?get_active_clients', { credentials: 'same-origin', headers: { Accept: 'application/json' } })
            .then(function (response) {
                if (!response.ok) {
                    throw new Error(response.status);
                }
                return response.json();
            })
            .then(function (data) {
                clients = (data && Array.isArray(data.clients)) ? data.clients : [];
                render();
            })
            .catch(function () {
                loading = null;
                message('Could not load clients - close and try again');
            });
    }

    root.addEventListener('show.bs.dropdown', function () {
        input.value = '';
        render();
        load();
    });

    root.addEventListener('shown.bs.dropdown', function () {
        input.focus();
    });

    input.addEventListener('input', render);

    input.addEventListener('keydown', function (event) {
        const first = list.querySelector('.itflow-client-switcher-item');
        if (event.key === 'Enter') {
            event.preventDefault();
            if (first) {
                window.location.href = first.href;
            }
        } else if (event.key === 'ArrowDown' && first) {
            event.preventDefault();
            first.focus();
        }
    });
})();
