/*
 * ITFlow - the search palette.
 *
 * Press Ctrl+K (Cmd+K on a Mac) anywhere, or click the search box in the top bar,
 * and a search window opens over the page. Results arrive as you type, grouped by
 * record type, from agent/ajax.php?global_search - the same search the full
 * results page runs (functions/search.php), so neither can show a record the
 * other would hide.
 *
 *   Up / Down        move through results
 *   Enter            open the highlighted result
 *   Ctrl/Cmd+Enter   open it in a new tab
 *   Esc              close
 *
 * With nothing typed, it lists the clients you opened most recently. The last
 * row is always "Search everything", which goes to the full results page and
 * also searches ticket conversations and document text.
 *
 * Everything the server sends is rendered with textContent, never innerHTML:
 * client names, ticket subjects and the like are user-entered.
 *
 * Loaded once per page from includes/footer.php. It is never re-run by ajax
 * modals, but the window flag keeps a second copy from binding twice anyway.
 */
(function () {
    'use strict';

    if (window.itflowGlobalSearch || typeof bootstrap === 'undefined') {
        return;
    }

    const ENDPOINT = '/agent/ajax.php';
    const RESULTS_PAGE = '/agent/global_search.php';
    const DEBOUNCE_MS = 150;
    const MIN_LENGTH = 2;
    const CACHE_SIZE = 50;
    const BADGE_VARIANTS = ['primary', 'secondary', 'success', 'danger', 'warning', 'info', 'light', 'dark'];
    const MOD_KEY = /Mac|iPhone|iPad/.test(navigator.platform || navigator.userAgent || '') ? '⌘' : 'Ctrl';

    let modalEl = null;
    let modal = null;
    let input = null;
    let list = null;
    let spinner = null;
    let announcer = null;

    let options = [];
    let active = -1;
    let renderedQuery = null;
    let lastQuery = '';
    let debounceTimer = null;
    let spinnerTimer = null;
    let controller = null;
    let requestSeq = 0;
    let pendingOpen = null;   // Enter pressed before the results for the current text arrived
    let optionSeq = 0;
    let returnFocus = null;   // what had focus before the palette opened
    let navbarBox = null;

    const cache = new Map();

    /* ---------- building blocks ---------- */

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

    function icon(name, extraClass) {
        const i = el('i', 'fas fa-fw fa-' + (/^[a-z0-9-]+$/.test(name || '') ? name : 'circle') + (extraClass ? ' ' + extraClass : ''));
        i.setAttribute('aria-hidden', 'true');
        return i;
    }

    // Only same-origin paths. The server builds every URL itself, so anything
    // else means something is wrong - fall back to a dead link, not a javascript: one.
    function safeUrl(url) {
        if (typeof url === 'string' && url.charAt(0) === '/' && url.charAt(1) !== '/' && url.charAt(1) !== '\\') {
            return url;
        }
        return '#';
    }

    function resultsPageUrl(query, groupKey) {
        return RESULTS_PAGE + '?query=' + encodeURIComponent(query) + (groupKey ? '#search-' + groupKey : '');
    }

    // The matched text in bold, built from text nodes so nothing is ever parsed as HTML
    function highlighted(text, query) {
        const frag = document.createDocumentFragment();
        const value = text === null || text === undefined ? '' : String(text);
        const needle = query.trim().toLowerCase();
        const hay = value.toLowerCase();

        // toLowerCase can change the length of some characters; then offsets
        // would not line up, so show the text plain rather than mark the wrong bit
        if (!needle || hay.length !== value.length) {
            frag.append(value);
            return frag;
        }

        let from = 0;
        let at;
        while ((at = hay.indexOf(needle, from)) !== -1) {
            frag.append(value.slice(from, at));
            frag.append(el('mark', null, value.slice(at, at + needle.length)));
            from = at + needle.length;
        }
        frag.append(value.slice(from));
        return frag;
    }

    function badge(spec) {
        if (!spec || !spec.text) {
            return null;
        }
        const node = el('span', 'badge rounded-pill', spec.text);
        if (spec.variant && BADGE_VARIANTS.indexOf(spec.variant) !== -1) {
            node.classList.add('text-bg-' + spec.variant);
        } else if (spec.hex && /^#[0-9a-f]{3,8}$/i.test(spec.hex)) {
            node.style.backgroundColor = spec.hex;
            node.classList.add('text-white');
        } else {
            node.classList.add('text-bg-secondary');
        }
        return node;
    }

    function option(url, iconName, query, parts, extraClass) {
        const link = el('a', 'itflow-search-item' + (extraClass ? ' ' + extraClass : ''));
        link.href = safeUrl(url);
        link.id = 'itflow-search-opt-' + (++optionSeq);
        link.tabIndex = -1;
        link.setAttribute('role', 'option');
        link.setAttribute('aria-selected', 'false');

        const body = el('span', 'itflow-search-item-body');
        const title = el('span', 'itflow-search-item-title');
        if (parts.ref) {
            const ref = el('span', 'itflow-search-ref');
            ref.append(highlighted(parts.ref, query));
            title.append(ref);
        }
        title.append(highlighted(parts.title, query));
        body.append(title);

        if (parts.subtitle) {
            const sub = el('span', 'itflow-search-item-sub');
            sub.append(highlighted(parts.subtitle, query));
            body.append(sub);
        }

        link.append(icon(iconName, 'itflow-search-item-icon'), body);

        const status = badge(parts.badge);
        if (status || parts.context) {
            const aside = el('span', 'itflow-search-item-aside');
            if (status) {
                aside.append(status);
            }
            if (parts.context) {
                aside.append(el('span', 'itflow-search-item-context', parts.context));
            }
            link.append(aside);
        }

        return link;
    }

    function heading(label, iconName) {
        const node = el('div', 'itflow-search-group');
        node.setAttribute('role', 'presentation');
        node.append(icon(iconName), document.createTextNode(label));
        return node;
    }

    /* ---------- the window ---------- */

    function build() {
        if (modalEl) {
            return;
        }

        // Static skeleton only - no data goes through this string.
        // No .fade: a search box should be there the instant the key is pressed.
        modalEl = el('div', 'modal itflow-search');
        modalEl.id = 'itflowSearch';
        modalEl.tabIndex = -1;
        modalEl.setAttribute('aria-label', 'Search');
        modalEl.innerHTML =
            '<div class="modal-dialog modal-lg modal-fullscreen-sm-down">' +
                '<div class="modal-content">' +
                    '<div class="itflow-search-bar">' +
                        '<i class="fas fa-search itflow-search-icon" aria-hidden="true"></i>' +
                        '<input type="text" class="itflow-search-input" autofocus autocomplete="off" spellcheck="false"' +
                            ' maxlength="100" role="combobox" aria-expanded="true" aria-autocomplete="list"' +
                            ' aria-controls="itflowSearchResults" aria-label="Search everything"' +
                            ' placeholder="Search clients, tickets, assets, credentials...">' +
                        '<span class="spinner-border spinner-border-sm itflow-search-spinner" hidden aria-hidden="true"></span>' +
                        '<kbd class="d-none d-sm-inline">Esc</kbd>' +
                        '<button type="button" class="btn btn-link btn-sm d-sm-none" data-bs-dismiss="modal">Cancel</button>' +
                    '</div>' +
                    '<div class="itflow-search-results" id="itflowSearchResults" role="listbox" aria-label="Search results"></div>' +
                    '<div class="itflow-search-footer d-none d-sm-flex">' +
                        '<span><kbd>&uarr;</kbd> <kbd>&darr;</kbd> to move</span>' +
                        '<span><kbd>&crarr;</kbd> to open</span>' +
                        '<span><kbd class="itflow-search-mod"></kbd> + <kbd>&crarr;</kbd> new tab</span>' +
                        '<span class="ms-auto"><kbd class="itflow-search-mod"></kbd> + <kbd>K</kbd> from any page</span>' +
                    '</div>' +
                    '<div class="visually-hidden" aria-live="polite"></div>' +
                '</div>' +
            '</div>';

        modalEl.querySelectorAll('.itflow-search-mod').forEach(function (k) {
            k.textContent = MOD_KEY;
        });

        document.body.appendChild(modalEl);

        input = modalEl.querySelector('.itflow-search-input');
        list = modalEl.querySelector('.itflow-search-results');
        spinner = modalEl.querySelector('.itflow-search-spinner');
        announcer = modalEl.querySelector('[aria-live]');

        modal = new bootstrap.Modal(modalEl);

        try {
            lastQuery = sessionStorage.getItem('itflowSearchLast') || '';
        } catch (e) {
            lastQuery = '';
        }

        // Bootstrap's focus trap parks focus on the dialog once it is shown;
        // the cursor belongs in the box
        modalEl.addEventListener('shown.bs.modal', function () {
            input.focus();
        });

        modalEl.addEventListener('hidden.bs.modal', function () {
            // Hand focus back to where it was rather than leave it on the now-hidden
            // box - except the top bar box, which would just open the palette again.
            input.blur();
            if (returnFocus && returnFocus !== navbarBox && document.contains(returnFocus) && typeof returnFocus.focus === 'function') {
                returnFocus.focus({ preventScroll: true });
            }
            returnFocus = null;

            lastQuery = input.value;
            // Kept for the tab, so the next page reopens on the same search
            try {
                sessionStorage.setItem('itflowSearchLast', lastQuery);
            } catch (e) {
                // storage blocked - the search just starts empty next page
            }
            clearTimeout(debounceTimer);
            if (controller) {
                controller.abort();
            }
            pendingOpen = null;
            setBusy(false);
        });

        input.addEventListener('input', function () {
            pendingOpen = null;
            schedule(input.value, false);
        });

        input.addEventListener('keydown', onInputKeydown);

        // Follow the pointer, but only when it actually moves - a list scrolling
        // under a still mouse must not steal the keyboard's place
        list.addEventListener('mousemove', function (e) {
            const item = e.target.closest('.itflow-search-item');
            if (item) {
                const index = options.indexOf(item);
                if (index !== -1 && index !== active) {
                    setActive(index, false);
                }
            }
        });
    }

    function isOpen() {
        return !!modalEl && modalEl.classList.contains('show');
    }

    function open(text) {
        build();
        const value = typeof text === 'string' ? text : lastQuery;
        input.value = value;
        if (!isOpen()) {
            returnFocus = document.activeElement;
            modal.show();
        }
        input.focus();
        input.select();
        if (value.trim() !== renderedQuery) {
            schedule(value, true);
        }
    }

    function go(url, newTab) {
        if (url === '#') {
            return;
        }
        if (newTab) {
            window.open(url, '_blank', 'noopener');
        } else {
            window.location.href = url;
        }
    }

    /* ---------- keyboard ---------- */

    function setActive(index, scroll) {
        if (active !== -1 && options[active]) {
            options[active].classList.remove('active');
            options[active].setAttribute('aria-selected', 'false');
        }

        active = options.length ? (index + options.length) % options.length : -1;

        if (active === -1) {
            input.removeAttribute('aria-activedescendant');
            return;
        }

        const current = options[active];
        current.classList.add('active');
        current.setAttribute('aria-selected', 'true');
        input.setAttribute('aria-activedescendant', current.id);
        if (scroll) {
            current.scrollIntoView({ block: 'nearest' });
        }
    }

    function onInputKeydown(e) {
        if (e.isComposing) {
            return;
        }

        if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
            e.preventDefault();
            setActive(active + (e.key === 'ArrowDown' ? 1 : -1), true);
            return;
        }

        if (e.key === 'Enter') {
            e.preventDefault();
            const newTab = e.ctrlKey || e.metaKey;

            // Typed "TCK-1042" and hit Enter straight away: the results for that
            // text are not back yet, so open the top one as soon as they are
            if (input.value.trim() !== renderedQuery) {
                pendingOpen = { newTab: newTab };
                schedule(input.value, true);
                return;
            }

            if (active !== -1 && options[active]) {
                go(options[active].getAttribute('href'), newTab);
            }
        }
    }

    function anotherModalOpen() {
        return !!document.querySelector('.modal.show:not(#itflowSearch)');
    }

    document.addEventListener('keydown', function (e) {
        if (e.defaultPrevented) {
            return;
        }

        if ((e.ctrlKey || e.metaKey) && !e.altKey && !e.shiftKey && (e.key === 'k' || e.key === 'K')) {
            if (isOpen()) {
                e.preventDefault();
                input.focus();
                input.select();
            } else if (!anotherModalOpen()) {
                e.preventDefault();
                open();
            }
        }
    });

    /* ---------- fetching ---------- */

    function setBusy(busy) {
        clearTimeout(spinnerTimer);
        if (!spinner) {
            return;
        }
        if (busy) {
            // Only for a slow answer; a spinner that flashes for 20ms is noise
            spinnerTimer = setTimeout(function () {
                spinner.hidden = false;
            }, 150);
        } else {
            spinner.hidden = true;
        }
    }

    function schedule(text, immediate) {
        clearTimeout(debounceTimer);
        if (immediate) {
            run(text);
        } else {
            debounceTimer = setTimeout(function () {
                run(text);
            }, DEBOUNCE_MS);
        }
    }

    function run(text) {
        const query = text.trim();

        // One character matches half the database; show the starting view until there is a second
        const key = query.length < MIN_LENGTH ? '' : query;

        if (cache.has(key)) {
            requestSeq++;
            if (controller) {
                controller.abort();
            }
            setBusy(false);
            render(query, key, cache.get(key));
            return;
        }

        if (controller) {
            controller.abort();
        }
        controller = new AbortController();
        const mine = ++requestSeq;
        setBusy(true);

        fetch(ENDPOINT + '?global_search=1&q=' + encodeURIComponent(key), {
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json' },
            signal: controller.signal
        })
            .then(function (res) {
                if (!res.ok) {
                    throw new Error('HTTP ' + res.status);
                }
                // An expired session is redirected to the login page, which is HTML
                if ((res.headers.get('Content-Type') || '').indexOf('application/json') === -1) {
                    throw new Error('session');
                }
                return res.json();
            })
            .then(function (data) {
                if (mine !== requestSeq) {
                    return;
                }
                remember(key, data);
                render(query, key, data);
            })
            .catch(function (err) {
                if (err.name === 'AbortError' || mine !== requestSeq) {
                    return;
                }
                renderMessage(err.message === 'session'
                    ? 'Your session has ended. Reload the page to sign in again.'
                    : 'Search is not responding. Try again in a moment.', 'exclamation-triangle', query);
            })
            .finally(function () {
                if (mine === requestSeq) {
                    setBusy(false);
                }
            });
    }

    function remember(key, data) {
        if (cache.size >= CACHE_SIZE) {
            cache.delete(cache.keys().next().value);
        }
        cache.set(key, data);
    }

    /* ---------- rendering ---------- */

    function finish(query, count) {
        options = Array.prototype.slice.call(list.querySelectorAll('.itflow-search-item'));
        active = -1;
        setActive(0, false);
        list.scrollTop = 0;
        renderedQuery = query;

        if (announcer) {
            announcer.textContent = query ? (count ? count + ' results' : 'No results') : '';
        }

        if (pendingOpen && options.length) {
            const newTab = pendingOpen.newTab;
            pendingOpen = null;
            go(options[0].getAttribute('href'), newTab);
        }
        pendingOpen = null;
    }

    function searchEverything(query, hasResults) {
        return option(resultsPageUrl(query), 'search', '', {
            title: 'Search everything for "' + query + '"',
            subtitle: hasResults
                ? 'See every match, including ticket conversations and document text'
                : 'Also searches ticket conversations and document text'
        }, 'itflow-search-all');
    }

    function renderMessage(text, iconName, query) {
        list.replaceChildren();
        const message = el('div', 'itflow-search-empty');
        message.append(icon(iconName), el('div', null, text));
        list.append(message);
        if (query && query.length >= MIN_LENGTH) {
            list.append(searchEverything(query, false));
        }
        finish(query, 0);
    }

    function render(query, key, data) {
        list.replaceChildren();
        let count = 0;

        // Nothing typed yet (or a single character): where you have been lately
        if (key === '') {
            const recent = (data && data.recent) || [];
            if (recent.length) {
                list.append(heading('Recent clients', 'history'));
                recent.forEach(function (item) {
                    list.append(option(item.url, 'user-friends', '', item));
                });
            }
            const hint = el('div', 'itflow-search-hint',
                'Type a name, email, phone number, IP, serial, or a ticket, invoice or quote number.');
            list.append(hint);
            finish('', recent.length);
            renderedQuery = query;
            return;
        }

        const pages = (data && data.pages) || [];
        if (pages.length) {
            list.append(heading('Go to', 'compass'));
            pages.forEach(function (page) {
                list.append(option(page.url, 'arrow-right', key, page));
            });
        }

        ((data && data.groups) || []).forEach(function (group) {
            list.append(heading(group.label, group.icon));
            group.items.forEach(function (item) {
                list.append(option(item.url, group.icon, key, item));
                count++;
            });
            if (group.more) {
                list.append(option(resultsPageUrl(key, group.key), 'ellipsis-h', '', {
                    title: 'See all matching ' + group.label.toLowerCase()
                }, 'itflow-search-more'));
            }
        });

        if (!count && !pages.length) {
            const message = el('div', 'itflow-search-empty');
            message.append(icon('search'), el('div', null, 'No quick matches for "' + key + '".'));
            list.append(message);
        }

        list.append(searchEverything(key, count > 0));
        finish(key, count);
    }

    /* ---------- the top bar box ---------- */

    // The box in the top bar becomes the way in: focusing it opens the window
    // with whatever it holds (the current query, on the results page). The form
    // still submits to the results page if this script never loads.
    function bindNavbar() {
        const box = document.getElementById('navbar-search-input');
        if (!box) {
            return;
        }
        navbarBox = box;
        const form = box.form;

        // The shortcut hint in the box: the markup says Ctrl K, a Mac gets Cmd
        const hint = form ? form.querySelector('.navbar-search-kbd') : null;
        if (hint) {
            hint.textContent = MOD_KEY + ' K';
        }

        box.addEventListener('mousedown', function (e) {
            e.preventDefault();
            open(box.value || undefined);
        });
        box.addEventListener('focus', function () {
            box.blur();
            open(box.value || undefined);
        });
        if (form) {
            form.addEventListener('submit', function (e) {
                e.preventDefault();
                open(box.value || undefined);
            });
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bindNavbar);
    } else {
        bindNavbar();
    }

    window.itflowGlobalSearch = { open: open };
})();
