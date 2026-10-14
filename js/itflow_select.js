/*
 * ITFlow select - the picker behind every <select class="select2">.
 *
 * Replaces Tom Select. Written for this app, vanilla, no dependencies.
 *
 * THE NATIVE <select> IS THE ONLY STATE
 *
 * The picker keeps no copy of the options or the value. Every time it draws it
 * reads them off the real <select>, and every pick is written straight back to
 * it (option.selected) followed by real input/change events. So the form posts
 * exactly what you see, inline onchange="this.form.submit()" handlers fire,
 * and any other script can keep treating the field as an ordinary <select>:
 *
 *   select.innerHTML = ...      redraws by itself (MutationObserver)
 *   select.value = 4            redraws by itself (value setter, see below)
 *   option.selected = true      redraws by itself
 *   form.reset()                redraws by itself
 *
 * There is nothing to "sync" or "refresh" by hand, which is where the old
 * widget kept drifting out of step with the form.
 *
 * WHAT IT LOOKS LIKE
 *
 * The field is a .form-select, so it is the same height, border, chevron and
 * focus ring as every other control and drops into .input-group as before.
 * Clicking it opens a panel with a search box (long lists, multi selects and
 * data-tags fields), the options, and a tick on what is chosen. Multi selects
 * show chips in the field and a checklist in the panel that stays open while
 * you pick; their change event fires once, when the panel closes, so a filter
 * that submits on change submits once with everything you picked. On a phone
 * the panel comes up from the bottom of the screen instead.
 *
 * OPTION EXTRAS - all optional, set on the <option>:
 *
 *   data-image="/uploads/.."   round picture (users, contacts)
 *   data-initials="RDG"        initials tile; data-tone="primary" colours it,
 *                              data-shape="circle" makes it round (people),
 *                              data-soft="1" puts it on the soft shade of
 *                              that colour (contacts - their avatar's look)
 *   data-color="#e74c3c"       colour dot (tags, categories, statuses)
 *   data-icon="fas fa-laptop"  icon; with data-color it sits on that colour
 *   data-description=".."      second, quieter line under the label
 *   data-keywords=".."         extra words the search should match
 *
 * functions/format.php has PHP helpers that write these for clients, users,
 * contacts, tags and categories. Labels written as "- Unassigned -" show
 * without the dashes, in a quieter colour, as the "nothing" choice.
 *
 * ON THE <select>:
 *
 *   data-placeholder=".."      text while nothing is chosen
 *   data-tags="true"           typed text that matches nothing can be added
 *   data-search="true|false"   force the search box on or off
 *   data-avatar="initials"     initials tiles for options that have no
 *                              picture (for lists filled in by JS)
 *
 * FROM CODE: select.itflowSelect is the picker - .open(), .close(),
 * .clear(), .setValue(v). itflowSelectAll(root) enhances anything not yet
 * done; it also happens by itself for selects added to the page later, and
 * again whenever includes/modal_footer.php re-runs this file for a modal.
 *
 * Loaded first in includes/footer.php, ahead of the heavy libraries, so the
 * swap happens before the page has finished drawing - see the note there.
 */
(function () {
    'use strict';

    // Re-run by every ajax modal. The second time there is nothing to set up,
    // just the modal's own selects to pick up.
    if (window.itflowSelectAll) {
        window.itflowSelectAll(document);
        return;
    }

    var SEARCH_FROM = 9;      // options before a search box is worth showing
    var RESULT_LIMIT = 200;   // rows drawn at once; typing narrows the rest
    var CHIP_LIMIT = 10;      // chips in the field before "+N more"
    var TONES = ['primary', 'success', 'info', 'warning', 'secondary'];
    var NONE_LABEL = /^\s*-\s*(.*?)\s*-\s*$/;

    var sheetMedia = window.matchMedia('(max-width: 575.98px)');
    var finePointer = window.matchMedia('(pointer: fine)');
    var counter = 0;
    var current = null;       // the picker whose panel is open

    /* ---------- small helpers ---------- */

    function make(tag, className, text) {
        var node = document.createElement(tag);
        if (className) {
            node.className = className;
        }
        if (text !== undefined && text !== null && text !== '') {
            node.textContent = text;
        }
        return node;
    }

    function fold(text) {
        return String(text || '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();
    }

    function initialsOf(text) {
        var words = String(text).replace(/[^\p{L}\p{N}\s]/gu, ' ').trim().split(/\s+/);
        return ((words[0] || '').charAt(0) + (words.length > 1 ? words[1].charAt(0) : (words[0] || '').charAt(1))).toUpperCase();
    }

    function printable(e) {
        return e.key.length === 1 && !e.ctrlKey && !e.metaKey && !e.altKey;
    }

    function selectOf(option) {
        var parent = option.parentNode;
        if (parent && parent.tagName === 'OPTGROUP') {
            parent = parent.parentNode;
        }
        return parent && parent.tagName === 'SELECT' ? parent : null;
    }

    function labelText(select) {
        var label = select.labels && select.labels[0];
        if (!label) {
            var box = select.closest('.mb-3, .form-group, [class^="col"], [class*=" col"]');
            label = box && box.querySelector('label');
        }
        return label ? label.textContent.replace(/\*/g, '').trim() : '';
    }

    /* Everything the picker draws for one <option>, read fresh each time. */
    function describe(option, select) {
        var data = option.dataset;
        var raw = (option.label || option.textContent || '').trim();
        var none = NONE_LABEL.exec(raw);
        var group = option.parentNode.tagName === 'OPTGROUP' ? option.parentNode : null;
        var icon = data.icon || '';
        var item = {
            option: option,
            value: option.value,
            label: none ? none[1] : raw,
            none: !!none || option.value === '',
            description: data.description || '',
            icon: icon && icon.indexOf('fa-') === -1 ? 'fas fa-' + icon : icon,
            image: data.image || '',
            color: data.color || '',
            initials: data.initials || '',
            tone: data.tone || '',
            shape: data.shape || '',
            soft: data.soft === '1',
            disabled: option.disabled || !!(group && group.disabled),
            group: group ? group.label : ''
        };
        if (select.dataset.avatar === 'initials' && !item.none && !item.image && !item.initials && item.label) {
            item.initials = initialsOf(item.label);
            item.shape = item.shape || 'circle';
            item.tone = item.tone || TONES[Math.abs(parseInt(item.value, 10) || 0) % TONES.length];
        }
        item.search = fold(item.label + ' ' + item.description + ' ' + (data.keywords || ''));
        return item;
    }

    /* The picture, tile, dot or icon in front of a label - or nothing. */
    function lead(item) {
        var node, tone;
        if (item.image) {
            node = make('img', 'itflow-select-avatar');
            node.src = item.image;
            node.alt = '';
            node.loading = 'lazy';
        } else if (item.initials) {
            tone = TONES.indexOf(item.tone) === -1 ? 'secondary' : item.tone;
            node = make('span', 'itflow-select-avatar ' + (item.soft ? 'bg-' + tone + '-subtle text-' + tone + '-emphasis' : 'text-bg-' + tone) + (item.shape === 'circle' ? '' : ' is-tile'), item.initials);
        } else if (item.icon && item.color) {
            node = make('span', 'itflow-select-badge');
            node.style.setProperty('--itflow-select-color', item.color);
            node.appendChild(make('i', item.icon));
        } else if (item.color) {
            node = make('span', 'itflow-select-dot');
            node.style.setProperty('--itflow-select-color', item.color);
        } else if (item.icon) {
            node = make('span', 'itflow-select-icon');
            node.appendChild(make('i', item.icon));
        } else {
            return null;
        }
        node.setAttribute('aria-hidden', 'true');
        return node;
    }

    /* Write text into node with the searched-for words in <mark>. */
    function highlight(node, text, terms) {
        if (!terms.length) {
            node.textContent = text;
            return;
        }
        var lower = text.toLowerCase();
        var ranges = [];
        terms.forEach(function (term) {
            var at = lower.indexOf(term);
            while (term && at !== -1) {
                ranges.push([at, at + term.length]);
                at = lower.indexOf(term, at + term.length);
            }
        });
        if (!ranges.length) {
            node.textContent = text;
            return;
        }
        ranges.sort(function (a, b) { return a[0] - b[0]; });
        var pos = 0;
        ranges.forEach(function (range) {
            if (range[1] <= pos) {
                return;
            }
            var start = Math.max(range[0], pos);
            if (start > pos) {
                node.appendChild(document.createTextNode(text.slice(pos, start)));
            }
            node.appendChild(make('mark', '', text.slice(start, range[1])));
            pos = range[1];
        });
        if (pos < text.length) {
            node.appendChild(document.createTextNode(text.slice(pos)));
        }
    }

    /* ---------- the picker ---------- */

    function Picker(select) {
        var self = this;

        this.select = select;
        this.id = 'itflow-select-' + (++counter);
        this.multiple = select.multiple;
        this.tags = select.dataset.tags === 'true';
        this.label = labelText(select);
        this.isOpen = false;
        this.query = '';
        this.rows = [];
        this.active = -1;
        this.dirty = false;
        this.queued = false;

        var wrap = this.wrap = make('div', 'itflow-select' + (this.multiple ? ' is-multiple' : ''));
        var control = this.control = make('div', 'itflow-select-control form-select');
        control.setAttribute('role', 'combobox');
        control.setAttribute('aria-haspopup', 'listbox');
        control.setAttribute('aria-expanded', 'false');
        control.setAttribute('aria-controls', this.id + '-list');
        if (this.label || select.dataset.placeholder) {
            control.setAttribute('aria-label', this.label || select.dataset.placeholder);
        }
        if (select.required) {
            control.setAttribute('aria-required', 'true');
        }

        select.parentNode.insertBefore(wrap, select);
        wrap.appendChild(control);
        wrap.appendChild(select);
        select.classList.add('itflow-select-native');
        select.tabIndex = -1;
        select.setAttribute('aria-hidden', 'true');
        select.itflowSelect = this;

        control.addEventListener('click', function (e) {
            if (self.isDisabled()) {
                return;
            }
            var remove = e.target.closest('[data-remove]');
            if (remove) {
                self.unpick(remove.getAttribute('data-remove'));
                return;
            }
            if (e.target.closest('.itflow-select-clear')) {
                self.clear();
                control.focus();
                return;
            }
            if (self.isOpen) {
                self.close(true, true);
            } else {
                self.open();
            }
        });
        control.addEventListener('keydown', function (e) {
            self.key(e, false);
        });

        select.addEventListener('change', function () {
            self.queue();
        });
        select.addEventListener('invalid', function () {
            wrap.classList.add('is-invalid');
        });

        this.observer = new MutationObserver(function () {
            self.queue();
        });
        this.observer.observe(select, {
            childList: true,
            subtree: true,
            characterData: true,
            attributes: true,
            attributeFilter: ['disabled', 'required', 'label', 'selected', 'data-placeholder', 'class']
        });

        this.draw();
    }

    Picker.prototype.isDisabled = function () {
        return this.select.disabled || !!this.select.closest('fieldset:disabled');
    };

    Picker.prototype.hasSearch = function () {
        var forced = this.select.dataset.search;
        if (forced === 'true' || forced === 'false') {
            return forced === 'true';
        }
        return this.tags || this.select.options.length >= SEARCH_FROM;
    };

    Picker.prototype.placeholder = function () {
        var text = this.select.dataset.placeholder;
        if (text) {
            var none = NONE_LABEL.exec(text);
            return none ? none[1] : text;
        }
        var first = this.select.options[0];
        if (first && first.value === '') {
            var item = describe(first, this.select);
            if (item.label) {
                return item.label;
            }
        }
        return this.multiple ? 'Choose' : 'Select';
    };

    /* The option that means "nothing" - an empty value, or a "- None -" label. */
    Picker.prototype.noneOption = function () {
        var options = this.select.options;
        for (var i = 0; i < options.length; i++) {
            if (options[i].value === '' || NONE_LABEL.test(options[i].label || options[i].textContent || '')) {
                return options[i];
            }
        }
        return null;
    };

    Picker.prototype.clearable = function () {
        if (this.select.required || this.isDisabled()) {
            return false;
        }
        if (this.multiple) {
            return false;
        }
        var none = this.noneOption();
        var chosen = this.select.selectedOptions[0];
        return !!(none && chosen && chosen !== none && !NONE_LABEL.test(chosen.label || ''));
    };

    Picker.prototype.chosen = function () {
        return Array.prototype.filter.call(this.select.selectedOptions, function (option) {
            return option.value !== '';
        });
    };

    /* Redraw on the next microtask - a burst of changes draws once. */
    Picker.prototype.queue = function () {
        var self = this;
        if (this.queued) {
            return;
        }
        this.queued = true;
        Promise.resolve().then(function () {
            self.queued = false;
            self.draw();
            if (self.isOpen) {
                self.filter(true);
            }
        });
    };

    /* The field itself. */
    Picker.prototype.draw = function () {
        var select = this.select;
        var control = this.control;
        var disabled = this.isDisabled();

        control.textContent = '';
        control.removeAttribute('title');
        this.wrap.classList.toggle('is-disabled', disabled);
        control.tabIndex = disabled ? -1 : 0;
        control.setAttribute('aria-disabled', disabled ? 'true' : 'false');
        if (this.wrap.classList.contains('is-invalid') && select.validity.valid) {
            this.wrap.classList.remove('is-invalid');
        }
        this.wrap.classList.toggle('is-invalid-class', select.classList.contains('is-invalid'));

        if (this.multiple) {
            var chosen = this.chosen();
            if (!chosen.length) {
                control.appendChild(make('span', 'itflow-select-placeholder', this.placeholder()));
            }
            chosen.slice(0, CHIP_LIMIT).forEach(function (option) {
                var item = describe(option, select);
                var chip = make('span', 'itflow-select-chip');
                var visual = lead(item);
                if (visual) {
                    chip.appendChild(visual);
                    chip.classList.add('has-lead');
                }
                chip.appendChild(make('span', 'itflow-select-chip-label', item.label));
                if (!disabled) {
                    var remove = make('button', 'itflow-select-chip-remove');
                    remove.type = 'button';
                    remove.tabIndex = -1;
                    remove.setAttribute('data-remove', item.value);
                    remove.setAttribute('aria-label', 'Remove ' + item.label);
                    remove.appendChild(make('i', 'fas fa-times'));
                    chip.appendChild(remove);
                }
                control.appendChild(chip);
            });
            if (chosen.length > CHIP_LIMIT) {
                control.appendChild(make('span', 'itflow-select-chip is-more', '+' + (chosen.length - CHIP_LIMIT) + ' more'));
            }
        } else {
            var option = select.selectedOptions[0];
            var item = option ? describe(option, select) : null;
            var value = make('span', 'itflow-select-value');
            if (!item || item.value === '' || !item.label) {
                value.appendChild(make('span', 'itflow-select-placeholder', this.placeholder()));
            } else {
                var visual = lead(item);
                if (visual) {
                    value.appendChild(visual);
                }
                value.appendChild(make('span', 'itflow-select-label' + (item.none ? ' is-none' : ''), item.label));
                control.title = item.label;
            }
            control.appendChild(value);
        }

        var clearable = this.clearable();
        this.wrap.classList.toggle('is-clearable', clearable);
        if (clearable) {
            var clear = make('button', 'itflow-select-clear');
            clear.type = 'button';
            clear.tabIndex = -1;
            clear.setAttribute('aria-label', 'Clear');
            clear.appendChild(make('i', 'fas fa-times'));
            control.appendChild(clear);
        }

        if (this.footerCount) {
            var count = this.chosen().length;
            this.footerCount.textContent = count ? count + ' selected' : 'None selected';
            this.footerClear.disabled = !count;
        }

        if (this.isOpen) {
            this.place();
        }
    };

    /* ---------- the panel ---------- */

    Picker.prototype.build = function () {
        if (this.panel) {
            return;
        }
        var self = this;
        var panel = this.panel = make('div', 'itflow-select-panel' + (this.multiple ? ' is-multiple' : ''));
        panel.id = this.id + '-panel';

        var head = make('div', 'itflow-select-sheet-head');
        head.appendChild(make('span', 'itflow-select-sheet-title', this.label || this.placeholder()));
        var done = make('button', 'btn btn-sm btn-link itflow-select-done', 'Done');
        done.type = 'button';
        done.addEventListener('click', function () {
            self.close(true, false);
        });
        head.appendChild(done);
        panel.appendChild(head);

        var search = this.searchRow = make('div', 'itflow-select-search');
        search.appendChild(make('i', 'fas fa-search'));
        var input = this.input = make('input');
        input.type = 'text';
        input.autocomplete = 'off';
        input.spellcheck = false;
        input.placeholder = this.tags ? 'Search or add' : 'Search';
        input.setAttribute('aria-label', 'Search ' + (this.label || 'options'));
        input.setAttribute('aria-controls', this.id + '-list');
        input.setAttribute('aria-autocomplete', 'list');
        input.addEventListener('input', function () {
            self.query = input.value;
            self.filter(false);
        });
        input.addEventListener('keydown', function (e) {
            self.key(e, true);
        });
        search.appendChild(input);
        panel.appendChild(search);

        var list = this.list = make('div', 'itflow-select-list');
        list.id = this.id + '-list';
        list.setAttribute('role', 'listbox');
        if (this.label) {
            list.setAttribute('aria-label', this.label);
        }
        if (this.multiple) {
            list.setAttribute('aria-multiselectable', 'true');
        }
        panel.appendChild(list);

        if (this.multiple) {
            var footer = make('div', 'itflow-select-footer');
            this.footerCount = make('span', '');
            this.footerClear = make('button', 'btn btn-sm btn-link', 'Clear');
            this.footerClear.type = 'button';
            this.footerClear.addEventListener('click', function () {
                self.clear();
            });
            footer.appendChild(this.footerCount);
            footer.appendChild(this.footerClear);
            panel.appendChild(footer);
        }

        // Keep focus where it is (the search box, or the field) while picking
        panel.addEventListener('mousedown', function (e) {
            if (e.target !== self.input && !e.target.closest('button')) {
                e.preventDefault();
            }
        });
        list.addEventListener('click', function (e) {
            var node = e.target.closest('[data-row]');
            if (node) {
                self.pick(parseInt(node.getAttribute('data-row'), 10));
            }
        });
        list.addEventListener('mousemove', function (e) {
            if (e.clientX === self.lastX && e.clientY === self.lastY) {
                return; // the list scrolled under a still pointer
            }
            self.lastX = e.clientX;
            self.lastY = e.clientY;
            var node = e.target.closest('[data-row]');
            if (node) {
                self.setActive(parseInt(node.getAttribute('data-row'), 10), false);
            }
        });

        this.backdrop = make('div', 'itflow-select-backdrop');
    };

    Picker.prototype.open = function (typed) {
        if (this.isOpen || this.isDisabled()) {
            return;
        }
        if (current && current !== this) {
            current.close(true, false);
        }
        this.build();
        current = this;
        this.isOpen = true;
        this.sheet = sheetMedia.matches;
        this.searching = this.hasSearch();
        this.searchRow.hidden = !this.searching;
        this.query = this.searching ? typed || '' : '';
        this.input.value = this.query;

        var host = this.control.closest('.modal, .offcanvas') || document.body;
        this.panel.classList.toggle('is-sheet', this.sheet);
        if (this.sheet) {
            host.appendChild(this.backdrop);
        }
        host.appendChild(this.panel);

        this.wrap.classList.add('is-open');
        this.control.setAttribute('aria-expanded', 'true');
        this.draw();
        this.filter(false);
        this.place();

        if (this.searching && (finePointer.matches || typed)) {
            this.input.focus({ preventScroll: true });
            var end = this.input.value.length;
            this.input.setSelectionRange(end, end);
        } else {
            this.control.focus({ preventScroll: true });
        }
        this.scrollToActive();
    };

    Picker.prototype.close = function (commit, refocus) {
        if (!this.isOpen) {
            return;
        }
        if (commit && this.tags && this.searching && this.query.trim()) {
            this.add(this.query.trim(), false);
        }
        this.isOpen = false;
        if (current === this) {
            current = null;
        }
        this.panel.remove();
        this.backdrop.remove();
        this.panel.style.cssText = '';
        this.list.style.maxHeight = '';
        this.wrap.classList.remove('is-open');
        this.control.setAttribute('aria-expanded', 'false');
        this.control.removeAttribute('aria-activedescendant');
        if (refocus) {
            this.control.focus({ preventScroll: true });
        }
        if (this.dirty) {
            this.dirty = false;
            this.emit();
        }
    };

    Picker.prototype.emit = function () {
        this.select.dispatchEvent(new Event('input', { bubbles: true }));
        this.select.dispatchEvent(new Event('change', { bubbles: true }));
    };

    /* Under the field, or over it when there is more room above. */
    Picker.prototype.place = function () {
        var panel = this.panel;
        if (!this.isOpen) {
            return;
        }
        if (!this.control.isConnected) {
            this.close(false, false);
            return;
        }
        if (this.sheet) {
            return;
        }
        var rect = this.control.getBoundingClientRect();
        var viewWidth = document.documentElement.clientWidth;
        var viewHeight = window.innerHeight;
        var edge = 8;
        var gap = 4;
        if (rect.bottom < 0 || rect.top > viewHeight) {
            this.close(false, false);
            return;
        }
        var width = Math.min(Math.max(rect.width, 240), viewWidth - edge * 2);
        panel.style.width = width + 'px';
        panel.style.left = Math.min(Math.max(edge, rect.left), viewWidth - width - edge) + 'px';

        this.list.style.maxHeight = '';
        var height = panel.offsetHeight;
        var below = viewHeight - rect.bottom - gap - edge;
        var above = rect.top - gap - edge;
        var up = height > below && above > below;
        var room = up ? above : below;
        if (height > room) {
            this.list.style.maxHeight = Math.max(96, this.list.offsetHeight - (height - room)) + 'px';
            height = panel.offsetHeight;
        }
        panel.style.top = (up ? rect.top - gap - height : rect.bottom + gap) + 'px';
        panel.classList.toggle('is-above', up);
    };

    /* Work out which options match the search and draw them. */
    Picker.prototype.filter = function (keep) {
        var select = this.select;
        var query = this.query.trim();
        var terms = fold(query).split(/\s+/).filter(Boolean);
        var hideEmpty = this.multiple || select.required;
        var view = [];
        var total = 0;
        var exact = false;
        var options = select.options;

        for (var i = 0; i < options.length; i++) {
            var item = describe(options[i], select);
            if (item.value === '' && (hideEmpty || !item.label)) {
                continue;
            }
            if (terms.length) {
                if (item.none) {
                    continue;
                }
                var hit = true;
                for (var t = 0; t < terms.length; t++) {
                    if (item.search.indexOf(terms[t]) === -1) {
                        hit = false;
                        break;
                    }
                }
                if (!hit) {
                    continue;
                }
                if (fold(item.label) === fold(query)) {
                    exact = true;
                }
            }
            total++;
            if (view.length < RESULT_LIMIT) {
                view.push(item);
            }
        }

        this.terms = query.toLowerCase().split(/\s+/).filter(Boolean);
        this.view = view;
        this.total = total;
        this.creatable = this.tags && query && !exact ? query : '';
        this.drawList(keep);
    };

    Picker.prototype.drawList = function (keep) {
        var self = this;
        var list = this.list;
        var before = keep && this.rows[this.active] ? this.rows[this.active].option : null;
        var scroll = list.scrollTop;
        var frag = document.createDocumentFragment();
        var group = null;

        list.textContent = '';
        this.rows = [];

        this.view.forEach(function (item) {
            if (item.group !== group) {
                group = item.group;
                if (group) {
                    frag.appendChild(make('div', 'itflow-select-group', group));
                }
            }
            var index = self.rows.length;
            var selected = item.option.selected;
            var node = make('div', 'itflow-select-option' + (selected ? ' is-selected' : '') + (item.none ? ' is-none' : '') + (item.disabled ? ' is-disabled' : ''));
            node.id = self.id + '-o' + index;
            node.setAttribute('role', 'option');
            node.setAttribute('data-row', index);
            node.setAttribute('aria-selected', selected ? 'true' : 'false');
            if (item.disabled) {
                node.setAttribute('aria-disabled', 'true');
            }
            if (self.multiple) {
                var box = make('span', 'itflow-select-checkbox');
                box.appendChild(make('i', 'fas fa-check'));
                node.appendChild(box);
            }
            var visual = lead(item);
            if (visual) {
                node.appendChild(visual);
            }
            var text = make('span', 'itflow-select-text');
            var label = make('span', 'itflow-select-label');
            highlight(label, item.label, self.terms);
            text.appendChild(label);
            if (item.description) {
                var description = make('span', 'itflow-select-description');
                highlight(description, item.description, self.terms);
                text.appendChild(description);
            }
            node.appendChild(text);
            if (!self.multiple) {
                node.appendChild(make('i', 'fas fa-check itflow-select-tick'));
            }
            frag.appendChild(node);
            self.rows.push({ node: node, option: item.option, disabled: item.disabled, item: item });
        });

        if (this.creatable) {
            var index = this.rows.length;
            var create = make('div', 'itflow-select-option itflow-select-create');
            create.id = this.id + '-o' + index;
            create.setAttribute('role', 'option');
            create.setAttribute('data-row', index);
            create.setAttribute('aria-selected', 'false');
            var plus = make('span', 'itflow-select-icon');
            plus.appendChild(make('i', 'fas fa-plus'));
            create.appendChild(plus);
            var createText = make('span', 'itflow-select-text');
            createText.appendChild(make('span', 'itflow-select-label', 'Add "' + this.creatable + '"'));
            create.appendChild(createText);
            frag.appendChild(create);
            this.rows.push({ node: create, create: this.creatable });
        }

        if (!this.rows.length) {
            frag.appendChild(make('div', 'itflow-select-empty', this.query.trim() ? 'No matches' : 'Nothing to choose from'));
        } else if (this.total > this.view.length) {
            frag.appendChild(make('div', 'itflow-select-more', 'Showing ' + this.view.length + ' of ' + this.total.toLocaleString() + ' - type to narrow it down'));
        }

        list.appendChild(frag);

        var active = -1;
        if (before) {
            active = this.rowOf(before);
        }
        if (active === -1 && !this.terms.length && !this.multiple) {
            active = this.rowOf(this.select.selectedOptions[0]);
        }
        if (active === -1) {
            active = this.step(-1, 1);
        }
        if (keep) {
            list.scrollTop = scroll;
        }
        this.setActive(active, false);
        this.draw();
    };

    Picker.prototype.rowOf = function (option) {
        for (var i = 0; option && i < this.rows.length; i++) {
            if (this.rows[i].option === option && !this.rows[i].disabled) {
                return i;
            }
        }
        return -1;
    };

    /* The next pickable row from `from` in direction `dir`, or -1. */
    Picker.prototype.step = function (from, dir) {
        for (var i = from + dir; i >= 0 && i < this.rows.length; i += dir) {
            if (!this.rows[i].disabled) {
                return i;
            }
        }
        return -1;
    };

    Picker.prototype.setActive = function (index, scroll) {
        var old = this.rows[this.active];
        if (old) {
            old.node.classList.remove('is-active');
        }
        this.active = index;
        var row = this.rows[index];
        var owner = this.searching ? this.input : this.control;
        if (!row) {
            owner.removeAttribute('aria-activedescendant');
            return;
        }
        row.node.classList.add('is-active');
        owner.setAttribute('aria-activedescendant', row.node.id);
        if (scroll) {
            row.node.scrollIntoView({ block: 'nearest' });
        }
    };

    Picker.prototype.scrollToActive = function () {
        var row = this.rows[this.active];
        if (row) {
            row.node.scrollIntoView({ block: 'nearest' });
        }
    };

    Picker.prototype.move = function (dir, count) {
        var index = this.active;
        for (var n = 0; n < (count || 1); n++) {
            var next = this.step(index, dir);
            if (next === -1) {
                break;
            }
            index = next;
        }
        if (index === -1) {
            index = this.step(-1, 1);
        }
        this.setActive(index, true);
    };

    /* ---------- choosing ---------- */

    Picker.prototype.pick = function (index) {
        var row = this.rows[index];
        if (!row || row.disabled) {
            return;
        }
        if (row.create) {
            this.add(row.create, true);
            return;
        }
        if (this.multiple) {
            row.option.selected = !row.option.selected;
            this.dirty = true;
            this.setActive(index, false);
            this.filter(true);
            return;
        }
        if (!row.option.selected) {
            row.option.selected = true;
            this.dirty = true;
        }
        this.close(false, true);
    };

    /* A typed value for a data-tags field: the matching option, or a new one. */
    Picker.prototype.add = function (text, fromRow) {
        var select = this.select;
        var match = null;
        for (var i = 0; i < select.options.length; i++) {
            if (fold(describe(select.options[i], select).label) === fold(text) && select.options[i].value !== '') {
                match = select.options[i];
                break;
            }
        }
        if (!match) {
            match = new Option(text, text);
            select.appendChild(match);
        }
        if (!match.selected) {
            match.selected = true;
            this.dirty = true;
        }
        this.query = '';
        this.input.value = '';
        if (fromRow && !this.multiple) {
            this.close(false, true);
        } else if (fromRow) {
            this.filter(true);
        }
    };

    Picker.prototype.unpick = function (value) {
        var options = this.select.options;
        for (var i = 0; i < options.length; i++) {
            if (options[i].value === value && options[i].selected) {
                options[i].selected = false;
                break;
            }
        }
        if (this.isOpen) {
            this.dirty = true;
            this.filter(true);
        } else {
            this.emit();
        }
    };

    Picker.prototype.clear = function () {
        var select = this.select;
        var changed = false;
        if (this.multiple) {
            Array.prototype.forEach.call(select.options, function (option) {
                if (option.selected) {
                    option.selected = false;
                    changed = true;
                }
            });
        } else {
            var none = this.noneOption();
            if (none && !none.selected) {
                none.selected = true;
                changed = true;
            }
        }
        if (!changed) {
            return;
        }
        if (this.isOpen) {
            this.dirty = true;
            this.filter(true);
        } else {
            this.emit();
        }
    };

    /* Choose by value from code - fires change like a real pick. */
    Picker.prototype.setValue = function (value) {
        var values = Array.isArray(value) ? value.map(String) : [String(value)];
        Array.prototype.forEach.call(this.select.options, function (option) {
            option.selected = values.indexOf(option.value) !== -1;
        });
        this.emit();
    };

    /* With no search box, typing jumps to the next option starting with it. */
    Picker.prototype.typeAhead = function (key) {
        var now = Date.now();
        var again = now - (this.typedAt || 0) < 700;
        this.typed = (again ? this.typed || '' : '') + fold(key);
        this.typedAt = now;

        var options = this.select.options;
        var count = options.length;
        var from = this.select.selectedIndex;
        if (this.isOpen && this.rows[this.active] && this.rows[this.active].option) {
            from = this.rows[this.active].option.index;
        }
        // One letter moves on to the next match; a longer run of letters may
        // still match where we already are
        var first = this.typed.length > 1 ? from : from + 1;

        for (var n = 0; n < count; n++) {
            var option = options[(Math.max(first, 0) + n) % count];
            var item = describe(option, this.select);
            if (item.disabled || item.value === '' || fold(item.label).indexOf(this.typed) !== 0) {
                continue;
            }
            if (this.isOpen) {
                this.setActive(this.rowOf(option), true);
            } else if (!this.multiple && !option.selected) {
                option.selected = true;
                this.emit();
            }
            return;
        }
    };

    Picker.prototype.key = function (e, inSearch) {
        var key = e.key;

        if (!this.isOpen) {
            if (key === 'ArrowDown' || key === 'ArrowUp' || key === 'Enter' || key === ' ' || key === 'F4') {
                e.preventDefault();
                this.open();
            } else if (key === 'Backspace' && this.multiple) {
                var chosen = this.chosen();
                if (chosen.length) {
                    e.preventDefault();
                    this.unpick(chosen[chosen.length - 1].value);
                }
            } else if ((key === 'Backspace' || key === 'Delete') && this.clearable()) {
                e.preventDefault();
                this.clear();
            } else if (printable(e)) {
                e.preventDefault();
                if (this.hasSearch()) {
                    this.open(key);
                } else {
                    this.typeAhead(key);
                }
            }
            return;
        }

        switch (key) {
            case 'ArrowDown':
                e.preventDefault();
                this.move(1);
                break;
            case 'ArrowUp':
                e.preventDefault();
                this.move(-1);
                break;
            case 'PageDown':
                e.preventDefault();
                this.move(1, 8);
                break;
            case 'PageUp':
                e.preventDefault();
                this.move(-1, 8);
                break;
            case 'Home':
            case 'End':
                if (!inSearch) {
                    e.preventDefault();
                    this.setActive(key === 'Home' ? this.step(-1, 1) : this.step(this.rows.length, -1), true);
                }
                break;
            case 'Enter':
                e.preventDefault();
                this.pick(this.active);
                break;
            case ' ':
                if (!inSearch) {
                    e.preventDefault();
                    this.pick(this.active);
                }
                break;
            case 'Escape':
                // Stop here, or a Bootstrap modal behind the picker closes too
                e.preventDefault();
                e.stopPropagation();
                this.close(false, true);
                break;
            case 'Tab':
                // Back to the field first, so Tab carries on from there
                this.control.focus({ preventScroll: true });
                this.close(true, false);
                break;
            default:
                if (!inSearch && printable(e)) {
                    e.preventDefault();
                    if (this.searching) {
                        // Opened by touch, so the search box was not focused - it is now
                        this.input.focus({ preventScroll: true });
                        this.input.value += key;
                        this.query = this.input.value;
                        this.filter(false);
                    } else {
                        this.typeAhead(key);
                    }
                }
        }
    };

    /* ---------- wiring ---------- */

    function init(select) {
        if (!select || select.tagName !== 'SELECT') {
            return null;
        }
        if (select.itflowSelect) {
            return select.itflowSelect;
        }
        // A copy of an enhanced field (cloned markup) comes with an empty
        // shell around it - drop the shell and start again
        var shell = select.parentNode;
        if (shell && shell.classList && shell.classList.contains('itflow-select')) {
            shell.parentNode.insertBefore(select, shell);
            shell.remove();
            select.classList.remove('itflow-select-native');
            select.removeAttribute('aria-hidden');
            select.removeAttribute('tabindex');
        }
        return new Picker(select);
    }

    function initAll(root) {
        var scope = root && root.querySelectorAll ? root : document;
        if (scope.matches && scope.matches('select.select2')) {
            init(scope);
        }
        scope.querySelectorAll('select.select2').forEach(function (select) {
            try {
                init(select);
            } catch (e) {
                console.error('itflow select: could not enhance', select, e);
            }
        });
    }

    /*
     * Picks made from code redraw by themselves. These are the browser's own
     * setters with a nudge added - the value still lands exactly as before.
     */
    function watch(proto, prop, owner) {
        var native = Object.getOwnPropertyDescriptor(proto, prop);
        if (!native || !native.set) {
            return;
        }
        Object.defineProperty(proto, prop, {
            configurable: true,
            enumerable: native.enumerable,
            get: native.get,
            set: function (value) {
                native.set.call(this, value);
                var select = owner(this);
                if (select && select.itflowSelect) {
                    select.itflowSelect.queue();
                }
            }
        });
    }
    watch(HTMLSelectElement.prototype, 'value', function (select) { return select; });
    watch(HTMLSelectElement.prototype, 'selectedIndex', function (select) { return select; });
    watch(HTMLOptionElement.prototype, 'selected', selectOf);

    // Outside click closes - and keeps what was typed into a data-tags field
    document.addEventListener('mousedown', function (e) {
        if (current && !current.wrap.contains(e.target) && !current.panel.contains(e.target)) {
            current.close(true, false);
        }
    }, true);
    document.addEventListener('touchstart', function (e) {
        if (current && !current.wrap.contains(e.target) && !current.panel.contains(e.target)) {
            current.close(true, false);
        }
    }, { capture: true, passive: true });

    var placing = false;
    function replace() {
        if (!current || placing) {
            return;
        }
        placing = true;
        requestAnimationFrame(function () {
            placing = false;
            if (current) {
                current.place();
            }
        });
    }
    window.addEventListener('resize', replace);
    document.addEventListener('scroll', function (e) {
        if (current && current.panel.contains(e.target)) {
            return; // the list itself scrolling
        }
        replace();
    }, true);

    document.addEventListener('hide.bs.modal', function (e) {
        if (current && e.target.contains(current.wrap)) {
            current.close(false, false);
        }
    });

    // <label for> lands on the hidden select; send it to the field instead
    document.addEventListener('click', function (e) {
        var label = e.target.closest && e.target.closest('label');
        var control = label && label.control;
        if (control && control.itflowSelect && !control.itflowSelect.isDisabled()) {
            e.preventDefault();
            control.itflowSelect.control.focus();
        }
    });

    document.addEventListener('reset', function (e) {
        setTimeout(function () {
            e.target.querySelectorAll('select.itflow-select-native').forEach(function (select) {
                if (select.itflowSelect) {
                    select.itflowSelect.queue();
                }
            });
        });
    }, true);

    // Selects added later (ajax modals, rows added by script) enhance themselves
    var pending = [];
    new MutationObserver(function (records) {
        records.forEach(function (record) {
            record.addedNodes.forEach(function (node) {
                if (node.nodeType === 1 && (node.matches('select.select2') || node.querySelector('select.select2'))) {
                    pending.push(node);
                }
            });
        });
        if (pending.length) {
            var nodes = pending;
            pending = [];
            nodes.forEach(initAll);
        }
    }).observe(document.documentElement, { childList: true, subtree: true });

    window.itflowSelect = init;
    window.itflowSelectAll = initAll;

    initAll(document);
})();
