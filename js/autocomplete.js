/**
 * Suggestions under a text field as you type - what a line on an invoice is
 * called, an asset's operating system. The field stays free text: picking a
 * suggestion is optional.
 *
 * itflowAutocomplete(input, {
 *     source:    array of items, or fn(term) -> array
 *     minLength: default 1
 *     maxItems:  cap on rendered results, default 50
 *     match:     fn(item, term) -> bool     (default: substring over item.label)
 *     row:       fn(item) -> what a suggestion shows (default: { label: item.label })
 *                  label        the main line
 *                  description  a second, quieter line - cut short with an ellipsis
 *                  icon         an icon in front, "fas fa-wrench"; iconTitle says what it means
 *                  aside        a value at the end of the row, a price say
 *                  asideNote    a quieter line under it; asideTone "danger" colours it
 *     onSelect:  fn(item)
 * })
 *
 * It looks and moves like the select picker (js/itflow_select.js), whose
 * panel, list and option classes it is drawn with: the row under the mouse or
 * the arrow keys is the one picked out, what was typed is marked in each row,
 * and a long list says how many more there are. Up/Down to move, Enter to
 * pick, Escape to dismiss. Nothing is picked out until you move, so Enter on
 * what you typed still just sends the form.
 *
 * The list is capped to the room the window has and scrolls inside itself,
 * and opens above the field when there is more room there.
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
    var MAX_WIDTH = 520;
    var items = [];
    var rows = [];
    var active = -1;
    var isOpen = false;

    itflowAutocomplete.count = (itflowAutocomplete.count || 0) + 1;
    var id = 'itflow-ac-' + itflowAutocomplete.count;

    function make(tag, className, text) {
        var node = document.createElement(tag);
        if (className) {
            node.className = className;
        }
        if (text != null && text !== '') {
            node.textContent = text;
        }
        return node;
    }

    var panel = make('div', 'itflow-select-panel itflow-ac-panel');
    var list = make('div', 'itflow-select-list');
    list.id = id + '-list';
    list.setAttribute('role', 'listbox');
    panel.appendChild(list);

    function defaultMatch(item, term) {
        return String(item.label || '').toLowerCase().indexOf(term) !== -1;
    }

    /* Write text into node with what was typed in <mark>, as the select picker does. */
    function highlight(node, text, term) {
        text = String(text == null ? '' : text);
        var lower = text.toLowerCase();
        var at = term ? lower.indexOf(term) : -1;
        var pos = 0;
        while (at !== -1) {
            if (at > pos) {
                node.appendChild(document.createTextNode(text.slice(pos, at)));
            }
            node.appendChild(make('mark', '', text.slice(at, at + term.length)));
            pos = at + term.length;
            at = lower.indexOf(term, pos);
        }
        if (pos < text.length) {
            node.appendChild(document.createTextNode(text.slice(pos)));
        }
    }

    function close() {
        if (!isOpen) {
            return;
        }
        isOpen = false;
        panel.remove();
        list.textContent = '';
        list.style.maxHeight = '';
        rows = [];
        active = -1;
        input.setAttribute('aria-expanded', 'false');
        input.removeAttribute('aria-activedescendant');
    }

    /**
     * Size and place the panel against whatever room the viewport actually has.
     * Without this a long product list runs off the bottom of the page.
     */
    function place() {
        var r = input.getBoundingClientRect();
        var below = window.innerHeight - r.bottom - GAP - MARGIN;
        var above = r.top - GAP - MARGIN;
        var up = below < MIN_HEIGHT && above > below;
        var room = Math.max(up ? above : below, MIN_HEIGHT);

        panel.style.left = r.left + 'px';
        panel.style.minWidth = r.width + 'px';
        panel.style.maxWidth = Math.max(r.width, Math.min(MAX_WIDTH, window.innerWidth - (MARGIN * 2))) + 'px';

        // The list is as tall as the stylesheet lets it be, less whatever doesn't fit
        list.style.maxHeight = '';
        var height = panel.offsetHeight;
        if (height > room) {
            list.style.maxHeight = Math.max(96, list.offsetHeight - (height - room)) + 'px';
        }

        panel.classList.toggle('is-above', up);
        if (up) {
            panel.style.top = '';
            panel.style.bottom = (window.innerHeight - r.top + GAP) + 'px';
        } else {
            panel.style.bottom = '';
            panel.style.top = (r.bottom + GAP) + 'px';
        }

        // Keep it on screen horizontally too
        var width = panel.offsetWidth || r.width;
        if (r.left + width > window.innerWidth - MARGIN) {
            panel.style.left = Math.max(MARGIN, window.innerWidth - width - MARGIN) + 'px';
        }
    }

    function setActive(next, scroll) {
        if (!rows.length) {
            return;
        }
        if (active >= 0 && rows[active]) {
            rows[active].classList.remove('is-active');
        }
        active = next < 0 ? rows.length - 1 : (next >= rows.length ? 0 : next);
        rows[active].classList.add('is-active');
        input.setAttribute('aria-activedescendant', rows[active].id);
        if (scroll) {
            rows[active].scrollIntoView({ block: 'nearest' });
        }
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

    function draw(item, index, term) {
        var row = typeof opts.row === 'function' ? (opts.row(item) || {}) : { label: item.label };
        var node = make('div', 'itflow-select-option');
        node.id = id + '-o' + index;
        node.setAttribute('role', 'option');
        node.setAttribute('data-row', index);
        node.setAttribute('aria-selected', 'false');

        if (row.icon) {
            var icon = make('span', 'itflow-select-icon');
            icon.appendChild(make('i', row.icon));
            if (row.iconTitle) {
                icon.title = row.iconTitle;
            } else {
                icon.setAttribute('aria-hidden', 'true');
            }
            node.appendChild(icon);
        }

        var text = make('span', 'itflow-select-text');
        var label = make('span', 'itflow-select-label');
        highlight(label, row.label, term);
        text.appendChild(label);
        if (row.description) {
            var description = make('span', 'itflow-select-description');
            highlight(description, row.description, term);
            text.appendChild(description);
        }
        node.appendChild(text);

        if (row.aside || row.asideNote) {
            var aside = make('span', 'itflow-ac-aside');
            if (row.aside) {
                aside.appendChild(make('span', 'itflow-ac-aside-value', row.aside));
            }
            if (row.asideNote) {
                aside.appendChild(make('span', 'itflow-ac-aside-note' + (row.asideTone ? ' text-' + row.asideTone : ''), row.asideNote));
            }
            node.appendChild(aside);
        }
        return node;
    }

    function open(term) {
        var source = typeof opts.source === 'function' ? opts.source(term) : (opts.source || []);
        var matcher = typeof opts.match === 'function' ? opts.match : defaultMatch;
        var all = source.filter(function (item) {
            return matcher(item, term);
        });
        items = all.slice(0, maxItems);
        if (!items.length) {
            // Nothing like it - the field takes what was typed as it is
            close();
            return;
        }

        list.textContent = '';
        rows = items.map(function (item, i) {
            return list.appendChild(draw(item, i, term));
        });
        if (all.length > items.length) {
            list.appendChild(make('div', 'itflow-select-more', 'Showing ' + items.length + ' of ' + all.length.toLocaleString() + ' - type to narrow it down'));
        }
        active = -1;
        input.removeAttribute('aria-activedescendant');

        if (!isOpen) {
            isOpen = true;
            // Inside the window the field is in, as the select picker's panel is
            (input.closest('.modal, .offcanvas') || document.body).appendChild(panel);
            input.setAttribute('aria-expanded', 'true');
        }
        place();
        list.scrollTop = 0;
    }

    input.setAttribute('autocomplete', 'off');
    input.setAttribute('role', 'combobox');
    input.setAttribute('aria-autocomplete', 'list');
    input.setAttribute('aria-expanded', 'false');
    input.setAttribute('aria-controls', list.id);

    input.addEventListener('input', function () {
        var term = input.value.trim().toLowerCase();
        if (term.length < minLength) {
            close();
            return;
        }
        open(term);
    });

    input.addEventListener('keydown', function (e) {
        if (!isOpen) {
            return;
        }
        if (e.key === 'ArrowDown') {
            e.preventDefault();
            setActive(active + 1, true);
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            setActive(active - 1, true);
        } else if (e.key === 'Enter') {
            if (active >= 0) {
                e.preventDefault();
                choose(active);
            }
        } else if (e.key === 'Escape') {
            // Only the list - not the window the field is in as well
            e.stopPropagation();
            close();
        }
    });

    input.addEventListener('blur', function () {
        setTimeout(close, 150);
    });

    // mousedown, not click - the field would lose focus and close the list first
    panel.addEventListener('mousedown', function (e) {
        e.preventDefault();
        var node = e.target.closest('[data-row]');
        if (node && e.button === 0) {
            choose(parseInt(node.getAttribute('data-row'), 10));
        }
    });
    // The row under the mouse is the one picked out, as in the select picker
    list.addEventListener('mousemove', function (e) {
        var node = e.target.closest('[data-row]');
        var index = node ? parseInt(node.getAttribute('data-row'), 10) : -1;
        if (index >= 0 && index !== active) {
            setActive(index, false);
        }
    });

    function reposition(e) {
        // The list's own scrolling moves nothing
        if (e && e.target && e.target.nodeType === 1 && panel.contains(e.target)) {
            return;
        }
        if (!input.isConnected) {
            // The window the field was in has gone
            close();
            window.removeEventListener('resize', reposition);
            window.removeEventListener('scroll', reposition, true);
            document.removeEventListener('click', outside);
            document.removeEventListener('itflow:dismiss', dismiss);
            return;
        }
        if (isOpen) {
            place();
        }
    }
    function outside(e) {
        if (!input.isConnected) {
            reposition();
        } else if (e.target !== input && !panel.contains(e.target)) {
            close();
        }
    }
    function dismiss() {
        close();
    }
    window.addEventListener('resize', reposition);
    // position: fixed does not follow the page, and these lists open inside
    // scrollable modal bodies - so track scroll on the way up the tree too
    window.addEventListener('scroll', reposition, true);
    document.addEventListener('click', outside);
    // A click into a reply box, which this page never hears (itflowDismissPopups(), js/itflow_select.js)
    document.addEventListener('itflow:dismiss', dismiss);

    input.itflowAutocomplete = { close: close, reposition: reposition };
}
