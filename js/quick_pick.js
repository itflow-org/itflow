/*
 * Quick pickers - a ticket's status, priority, assignee and billable, a
 * project's manager, a recurring ticket's assignee, an asset's status,
 * location and contact, and a contact's location, changed where you see them
 * (ticket list and page, projects and project page, recurring tickets, the
 * assets and contacts lists and their pages and quick looks) instead of in a modal.
 *
 * Click the value and a small menu opens under it (a sheet from the bottom
 * on a phone): statuses and priorities with their colours, agents and
 * contacts with their pictures (contacts' initials on their own colour, as
 * contactAvatar() draws them) and a search box, or Billable / Not billable.
 * Pick one and it saves straight
 * away through agent/ajax.php, which runs the same code as the old modals
 * (functions/ticket_fields.php - history, audit log, SLA, notifications) and
 * sends back the redrawn value. Assigning a New ticket opens it, so the
 * ticket's status redraws too, wherever it is marked with
 * data-ticket-status-for="<ticket id>".
 *
 * Where the choices depend on the client (an asset's location and contact)
 * the button carries data-quick-scope="<client id>", and the choices are
 * fetched and kept per client. Anything else on the page that shows the
 * value comes back as "related" HTML for [data-quick-related="<key>"] - the
 * asset page's contact details under its Assigned picker.
 *
 * Markup comes from quickPick() in functions/ticket_fields.php. Styled
 * with the select picker's panel classes, like the tag picker.
 */
(function () {
    'use strict';

    if (window.itflowQuickPick) {
        return; // re-run by an ajax modal - the listeners are already in place
    }

    var sheetMedia = window.matchMedia('(max-width: 575.98px)');
    var finePointer = window.matchMedia('(pointer: fine)');
    var titles = {
        priority: 'Priority', assignee: 'Assign to', billable: 'Billing', status: 'Status', project_manager: 'Project manager', recurring_assignee: 'Assign to',
        asset_status: 'Status', asset_location: 'Location', asset_contact: 'Assign to', contact_location: 'Location'
    };
    // People and places get a wider menu
    var wideFields = ['assignee', 'project_manager', 'recurring_assignee', 'asset_location', 'asset_contact', 'contact_location'];
    var cache = {};
    var open = null;
    var tones = ['primary', 'success', 'info', 'warning', 'secondary'];

    function make(tag, className, text) {
        var node = document.createElement(tag);
        if (className) {
            node.className = className;
        }
        if (text) {
            node.textContent = text;
        }
        return node;
    }

    function fold(text) {
        return String(text || '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();
    }

    function lead(option) {
        var node;
        if (option.image) {
            node = make('img', 'itflow-select-avatar');
            node.src = option.image;
            node.alt = '';
        } else if (option.initials) {
            // A contact carries their own colour, on its soft shade like their avatar
            node = make('span', 'itflow-select-avatar ' + (tones.indexOf(option.tone) !== -1 ? 'bg-' + option.tone + '-subtle text-' + option.tone + '-emphasis' : 'text-bg-secondary'), option.initials);
        } else if (option.color) {
            node = make('span', 'itflow-select-dot');
            node.style.setProperty('--itflow-select-color', option.color);
        } else if (option.icon) {
            node = make('span', 'itflow-select-icon');
            node.appendChild(make('i', option.icon));
        } else {
            return null;
        }
        node.setAttribute('aria-hidden', 'true');
        return node;
    }

    function options(field, scope, done, failed) {
        var key = field + '|' + scope;
        if (cache[key]) {
            done(cache[key]);
            return;
        }
        var query = { quick_pick_options: 1, field: field };
        if (scope) {
            query.scope = scope;
        }
        itflowGet('ajax.php', query, function (text) {
            var list;
            try {
                list = JSON.parse(text).options;
            } catch (e) {
                list = null;
            }
            if (!Array.isArray(list)) {
                failed();
                return;
            }
            cache[key] = list;
            done(list);
        }, failed);
    }

    function Picker(button) {
        this.button = button;
        this.field = button.dataset.quickPick;
        this.ids = [button.dataset.quickId];
        this.current = button.dataset.quickCurrent;
        this.scope = button.dataset.quickScope || '';
        this.csrf = button.dataset.csrf || '';
        this.query = '';
        this.rows = [];
        this.active = -1;
    }

    Picker.prototype.show = function () {
        var self = this;
        open = this;

        var panel = this.panel = make('div', 'itflow-select-panel itflow-quick-panel');
        panel.setAttribute('role', 'dialog');
        panel.setAttribute('aria-label', titles[this.field] || 'Change');

        var head = make('div', 'itflow-select-sheet-head');
        head.appendChild(make('span', 'itflow-select-sheet-title', titles[this.field] || 'Change'));
        var done = make('button', 'btn btn-sm btn-link itflow-select-done', 'Cancel');
        done.type = 'button';
        done.addEventListener('click', function () {
            self.close(false);
        });
        head.appendChild(done);
        panel.appendChild(head);

        var search = this.searchRow = make('div', 'itflow-select-search');
        search.hidden = true;
        search.appendChild(make('i', 'fas fa-search'));
        var input = this.input = make('input');
        input.type = 'text';
        input.autocomplete = 'off';
        input.spellcheck = false;
        input.placeholder = 'Search';
        input.setAttribute('aria-label', 'Search');
        input.addEventListener('input', function () {
            self.query = input.value;
            self.draw();
        });
        input.addEventListener('keydown', function (e) {
            self.key(e);
        });
        search.appendChild(input);
        panel.appendChild(search);

        var list = this.list = make('div', 'itflow-select-list');
        list.setAttribute('role', 'listbox');
        list.setAttribute('aria-label', titles[this.field] || 'Choices');
        list.appendChild(make('div', 'itflow-select-empty', 'Loading...'));
        list.addEventListener('click', function (e) {
            var node = e.target.closest('[data-row]');
            if (node) {
                self.pick(parseInt(node.getAttribute('data-row'), 10));
            }
        });
        list.addEventListener('mousemove', function (e) {
            var node = e.target.closest('[data-row]');
            if (node) {
                self.setActive(parseInt(node.getAttribute('data-row'), 10), false);
            }
        });
        panel.appendChild(list);

        panel.addEventListener('mousedown', function (e) {
            if (e.target !== input && !e.target.closest('a, button')) {
                e.preventDefault();
            }
        });
        // With no search box, the keys work on the button that opened it
        this.onButtonKey = function (e) {
            self.key(e);
        };
        this.button.addEventListener('keydown', this.onButtonKey);

        this.sheet = sheetMedia.matches;
        panel.classList.toggle('is-sheet', this.sheet);
        this.backdrop = make('div', 'itflow-select-backdrop');
        var host = this.button.closest('.modal, .offcanvas') || document.body;
        if (this.sheet) {
            host.appendChild(this.backdrop);
        }
        host.appendChild(panel);
        this.button.setAttribute('aria-expanded', 'true');
        this.place();

        options(this.field, this.scope, function (list) {
            if (open !== self) {
                return;
            }
            self.options = list;
            self.searchRow.hidden = list.length < 8;
            self.draw();
            if (!self.searchRow.hidden && finePointer.matches) {
                self.input.focus({ preventScroll: true });
            }
        }, function () {
            self.fail('Could not load the choices.');
            self.close(false);
        });
    };

    Picker.prototype.close = function (refocus) {
        if (open !== this) {
            return;
        }
        open = null;
        this.panel.remove();
        this.backdrop.remove();
        this.button.removeEventListener('keydown', this.onButtonKey);
        this.button.setAttribute('aria-expanded', 'false');
        if (refocus && this.button.isConnected) {
            this.button.focus({ preventScroll: true });
        }
    };

    Picker.prototype.fail = function (message) {
        if (typeof itflowToast === 'function') {
            itflowToast(message, 'error');
        }
    };

    Picker.prototype.place = function () {
        if (this.sheet || open !== this) {
            return;
        }
        if (!this.button.isConnected) {
            this.close(false);
            return;
        }
        var rect = this.button.getBoundingClientRect();
        var viewWidth = document.documentElement.clientWidth;
        var viewHeight = window.innerHeight;
        var width = Math.min(wideFields.indexOf(this.field) !== -1 ? 264 : 224, viewWidth - 16);
        var panel = this.panel;
        panel.style.width = width + 'px';
        panel.style.left = Math.min(Math.max(8, rect.left), viewWidth - width - 8) + 'px';
        this.list.style.maxHeight = '';
        var height = panel.offsetHeight;
        var below = viewHeight - rect.bottom - 12;
        var above = rect.top - 12;
        var up = height > below && above > below;
        var room = up ? above : below;
        if (height > room) {
            this.list.style.maxHeight = Math.max(96, this.list.offsetHeight - (height - room)) + 'px';
            height = panel.offsetHeight;
        }
        panel.style.top = (up ? rect.top - 4 - height : rect.bottom + 4) + 'px';
        panel.classList.toggle('is-above', up);
    };

    Picker.prototype.draw = function () {
        var self = this;
        var terms = fold(this.query.trim()).split(/\s+/).filter(Boolean);
        var list = this.list;
        list.textContent = '';
        this.rows = [];

        this.options.forEach(function (option) {
            var label = fold(option.label);
            if (terms.length && !terms.every(function (t) { return label.indexOf(t) !== -1; })) {
                return;
            }
            var selected = String(option.value) === String(self.current);
            var index = self.rows.length;
            var node = make('div', 'itflow-select-option' + (selected ? ' is-selected' : '') + (option.none ? ' is-none' : ''));
            node.id = 'itflow-quick-option-' + index;
            node.setAttribute('role', 'option');
            node.setAttribute('data-row', index);
            node.setAttribute('aria-selected', selected ? 'true' : 'false');
            var visual = lead(option);
            if (visual) {
                node.appendChild(visual);
            }
            var text = make('span', 'itflow-select-text');
            text.appendChild(make('span', 'itflow-select-label', option.label));
            if (option.description) {
                text.appendChild(make('span', 'itflow-select-description', option.description));
            }
            node.appendChild(text);
            node.appendChild(make('i', 'fas fa-check itflow-select-tick'));
            list.appendChild(node);
            self.rows.push({ node: node, option: option });
        });

        if (!this.rows.length) {
            list.appendChild(make('div', 'itflow-select-empty', 'No matches'));
        }

        var active = 0;
        this.rows.forEach(function (row, i) {
            if (!terms.length && String(row.option.value) === String(self.current)) {
                active = i;
            }
        });
        this.setActive(this.rows.length ? active : -1, true);
        this.place();
    };

    Picker.prototype.setActive = function (index, scroll) {
        var old = this.rows[this.active];
        if (old) {
            old.node.classList.remove('is-active');
        }
        this.active = index;
        var row = this.rows[index];
        var owner = this.searchRow.hidden ? this.button : this.input;
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

    Picker.prototype.key = function (e) {
        switch (e.key) {
            case 'ArrowDown':
                e.preventDefault();
                this.setActive(Math.min(this.active + 1, this.rows.length - 1), true);
                break;
            case 'ArrowUp':
                e.preventDefault();
                this.setActive(Math.max(this.active - 1, 0), true);
                break;
            case 'Enter':
            case ' ':
                if (e.key === ' ' && e.target === this.input) {
                    return;
                }
                e.preventDefault();
                this.pick(this.active);
                break;
            case 'Escape':
                // Stop here, or a modal behind the picker closes too
                e.preventDefault();
                e.stopPropagation();
                this.close(true);
                break;
            case 'Tab':
                this.close(true);
                break;
        }
    };

    /* Save the pick, close, and redraw the value from what the server says. */
    Picker.prototype.pick = function (index) {
        var self = this;
        var row = this.rows[index];
        if (!row) {
            return;
        }
        var value = String(row.option.value);
        this.close(true);
        if (value === String(this.current)) {
            return;
        }

        var button = this.button;
        var field = this.field;
        button.classList.add('is-saving');

        itflowPost('ajax.php', {
            quick_pick_set: 1,
            csrf_token: this.csrf,
            field: field,
            value: value,
            ids: this.ids
        }, function (text) {
            button.classList.remove('is-saving');
            var result;
            try {
                result = JSON.parse(text);
            } catch (e) {
                result = null;
            }
            if (!result || !result.records) {
                self.fail((result && result.error) || 'That change was not saved - try again.');
                return;
            }
            Object.keys(result.records).forEach(function (id) {
                var record = result.records[id];
                document.querySelectorAll('[data-quick-pick="' + field + '"][data-quick-id="' + id + '"]').forEach(function (spot) {
                    var style = spot.dataset.quickStyle === 'detail' ? 'detail' : 'list';
                    spot.dataset.quickCurrent = record.current;
                    var holder = spot.querySelector('.itflow-quick-pick-value');
                    if (holder) {
                        holder.innerHTML = record.html[style];
                    }
                    spot.classList.add('is-saved');
                    setTimeout(function () {
                        spot.classList.remove('is-saved');
                    }, 900);
                });
                Object.keys(record.related || {}).forEach(function (key) {
                    document.querySelectorAll('[data-quick-related="' + key + '"]').forEach(function (spot) {
                        spot.innerHTML = record.related[key];
                    });
                });
                if (record.status_id === undefined) {
                    return; // not a ticket
                }
                // The status shows elsewhere too (assigning a New ticket opens it).
                // Where the status is its own picker, redraw inside the picker
                document.querySelectorAll('[data-ticket-status-for="' + id + '"]').forEach(function (spot) {
                    var style = spot.dataset.quickStyle === 'detail' ? 'detail' : 'list';
                    if (spot.dataset.statusId === String(record.status_id)) {
                        return;
                    }
                    spot.dataset.statusId = record.status_id;
                    var statusPick = spot.querySelector('[data-quick-pick="status"]');
                    if (statusPick) {
                        statusPick.dataset.quickCurrent = record.status_id;
                        statusPick.querySelector('.itflow-quick-pick-value').innerHTML = record.status[style];
                    } else {
                        spot.innerHTML = record.status[style];
                    }
                });
            });
            // On the ticket page billing decides the Quote / Invoice buttons, and
            // assigning a New ticket opens it - both show elsewhere on the page,
            // so load it again rather than leave those out of date
            var statusChanged = Object.keys(result.records).some(function (id) {
                return String(result.records[id].status_id) !== String(button.dataset.statusId || result.records[id].status_id);
            });
            if (button.dataset.quickStyle === 'detail' && (field === 'billable' || (field === 'assignee' && statusChanged))) {
                window.location.reload();
                return;
            }
            if (typeof itflowToast === 'function' && result.message) {
                itflowToast(result.message, 'success');
            }
        }, function () {
            button.classList.remove('is-saving');
            self.fail('That change was not saved - try again.');
        });
    };

    /* ---------- wiring ---------- */

    document.addEventListener('click', function (e) {
        var button = e.target.closest('[data-quick-pick]');
        if (!button) {
            return;
        }
        e.preventDefault();
        e.stopPropagation();
        var wasOpen = open && open.button === button;
        if (open) {
            open.close(false);
        }
        if (!wasOpen) {
            new Picker(button).show();
        }
    });

    // Clicking off the menu closes it - and that click does nothing else
    var swallow = false;
    function outside(e) {
        if (open && !open.panel.contains(e.target) && !e.target.closest('[data-quick-pick]')) {
            open.close(false);
            swallow = true;
        }
    }
    document.addEventListener('mousedown', outside, true);
    document.addEventListener('touchstart', outside, { capture: true, passive: true });
    document.addEventListener('click', function (e) {
        if (swallow) {
            swallow = false;
            e.preventDefault();
            e.stopPropagation();
        }
    }, true);
    document.addEventListener('pointerdown', function (e) {
        if (!open && !e.target.closest('[data-quick-pick]')) {
            swallow = false;
        }
    }, true);

    var placing = false;
    function replace() {
        if (!open || placing) {
            return;
        }
        placing = true;
        requestAnimationFrame(function () {
            placing = false;
            if (open) {
                open.place();
            }
        });
    }
    window.addEventListener('resize', replace);
    document.addEventListener('scroll', function (e) {
        if (open && open.panel.contains(e.target)) {
            return;
        }
        replace();
    }, true);

    window.itflowQuickPick = true;
})();
