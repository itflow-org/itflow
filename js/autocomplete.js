/**
 * Minimal autocomplete, replacing jQuery UI's.
 *
 * itflowAutocomplete(input, {
 *     source:    array of items, or fn(term) -> array
 *     minLength: default 1
 *     maxItems:  cap on rendered results, default 50
 *     match:     fn(item, term) -> bool     (default: substring over item.label)
 *     render:    fn(item) -> HTML string    (default: escaped item.label)
 *     onSelect:  fn(item)
 * })
 *
 * Keyboard: up/down to move, Enter to pick, Escape to dismiss.
 * The menu is capped to the space actually available in the viewport and
 * scrolls internally, and flips above the input when there is more room there.
 */
function itflowEscapeHtml(value) {
    var div = document.createElement('div');
    div.textContent = value == null ? '' : String(value);
    return div.innerHTML;
}

function itflowAutocomplete(input, options) {
    if (!input || input.itflowAutocomplete) {
        return;
    }
    var opts = options || {};
    var minLength = opts.minLength == null ? 1 : opts.minLength;
    var maxItems = opts.maxItems == null ? 50 : opts.maxItems;
    var GAP = 4;        // breathing room against the input
    var MARGIN = 8;     // never touch the viewport edge
    var MIN_HEIGHT = 120;
    var items = [];
    var active = -1;

    var menu = document.createElement('div');
    menu.className = 'itflow-ac-menu dropdown-menu p-0';
    menu.setAttribute('role', 'listbox');
    document.body.appendChild(menu);

    function defaultMatch(item, term) {
        return String(item.label || '').toLowerCase().indexOf(term) !== -1;
    }

    function close() {
        menu.classList.remove('show');
        menu.innerHTML = '';
        active = -1;
        input.setAttribute('aria-expanded', 'false');
    }

    /**
     * Size and place the menu against whatever room the viewport actually has.
     * Without this a long product list runs off the bottom of the page.
     */
    function position() {
        var r = input.getBoundingClientRect();
        var below = window.innerHeight - r.bottom - GAP - MARGIN;
        var above = r.top - GAP - MARGIN;
        var flip = below < MIN_HEIGHT && above > below;
        var room = Math.max(flip ? above : below, MIN_HEIGHT);

        menu.style.position = 'fixed';
        menu.style.left = r.left + 'px';
        menu.style.minWidth = r.width + 'px';
        menu.style.maxWidth = Math.max(r.width, Math.min(520, window.innerWidth - (MARGIN * 2))) + 'px';
        menu.style.maxHeight = room + 'px';
        menu.style.overflowY = 'auto';
        menu.style.overflowX = 'hidden';
        menu.style.zIndex = '2000';

        if (flip) {
            menu.style.top = '';
            menu.style.bottom = (window.innerHeight - r.top + GAP) + 'px';
        } else {
            menu.style.bottom = '';
            menu.style.top = (r.bottom + GAP) + 'px';
        }

        // Keep it on screen horizontally too
        var width = menu.offsetWidth || r.width;
        if (r.left + width > window.innerWidth - MARGIN) {
            menu.style.left = Math.max(MARGIN, window.innerWidth - width - MARGIN) + 'px';
        }
    }

    function highlight(next) {
        var nodes = menu.querySelectorAll('.itflow-ac-item');
        if (!nodes.length) {
            return;
        }
        if (active >= 0 && nodes[active]) {
            nodes[active].classList.remove('active');
        }
        active = next < 0 ? nodes.length - 1 : (next >= nodes.length ? 0 : next);
        nodes[active].classList.add('active');
        nodes[active].scrollIntoView({ block: 'nearest' });
    }

    function choose(index) {
        var item = items[index];
        if (!item) {
            return;
        }
        close();
        if (typeof opts.onSelect === 'function') {
            opts.onSelect(item);
        }
    }

    function open(term) {
        var source = typeof opts.source === 'function' ? opts.source(term) : (opts.source || []);
        var matcher = typeof opts.match === 'function' ? opts.match : defaultMatch;
        var all = source.filter(function (item) {
            return matcher(item, term);
        });
        items = all.slice(0, maxItems);
        if (!items.length) {
            close();
            return;
        }
        menu.innerHTML = '';
        items.forEach(function (item, i) {
            var el = document.createElement('button');
            el.type = 'button';
            el.className = 'itflow-ac-item dropdown-item text-wrap';
            el.setAttribute('role', 'option');
            el.innerHTML = typeof opts.render === 'function'
                ? opts.render(item)
                : itflowEscapeHtml(item.label);
            el.addEventListener('mousedown', function (e) {
                // mousedown, not click - blur would close the menu first
                e.preventDefault();
                choose(i);
            });
            menu.appendChild(el);
        });
        if (all.length > items.length) {
            var more = document.createElement('div');
            more.className = 'itflow-ac-more small text-muted px-3 py-2 border-top';
            more.textContent = 'Showing ' + items.length + ' of ' + all.length + ' - keep typing to narrow';
            menu.appendChild(more);
        }
        menu.classList.add('show');
        input.setAttribute('aria-expanded', 'true');
        active = -1;
        position();
        menu.scrollTop = 0;
    }

    input.setAttribute('autocomplete', 'off');
    input.setAttribute('aria-autocomplete', 'list');

    input.addEventListener('input', function () {
        var term = input.value.trim().toLowerCase();
        if (term.length < minLength) {
            close();
            return;
        }
        open(term);
    });

    input.addEventListener('keydown', function (e) {
        if (!menu.classList.contains('show')) {
            return;
        }
        if (e.key === 'ArrowDown') {
            e.preventDefault();
            highlight(active + 1);
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            highlight(active - 1);
        } else if (e.key === 'Enter') {
            if (active >= 0) {
                e.preventDefault();
                choose(active);
            }
        } else if (e.key === 'Escape') {
            close();
        }
    });

    input.addEventListener('blur', function () {
        setTimeout(close, 150);
    });

    function reposition() {
        if (menu.classList.contains('show')) {
            position();
        }
    }
    window.addEventListener('resize', reposition);
    // position: fixed does not follow the page, and these menus open inside
    // scrollable modal bodies - so track scroll on the way up the tree too
    window.addEventListener('scroll', reposition, true);

    document.addEventListener('click', function (e) {
        if (e.target !== input && !menu.contains(e.target)) {
            close();
        }
    });

    input.itflowAutocomplete = { close: close, reposition: reposition };
}
