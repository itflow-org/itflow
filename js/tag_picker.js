/*
 * Tag picker - the "+" at the end of a record's tags, and "Assign Tags" in
 * a list's bulk Action menu. Replaces the Assign Tags modals.
 *
 * Opens a panel under the button (a sheet from the bottom on a phone) with
 * every tag this kind of record can have, ticked if it has it. Ticking or
 * unticking saves straight away through agent/ajax.php and the pills on the
 * page redraw from what the server sends back, so the list never reloads and
 * you can tick several in a row. Admins can type a new tag name and make it
 * on the spot; "Manage tags" goes to Admin > Tags.
 *
 * In bulk, a tag some of the selected records have shows a dash: ticking it
 * gives it to all of them, unticking takes it off all of them.
 *
 * Markup comes from tagPills() in functions/tags.php:
 *   <button data-tag-picker data-tag-entity="contact" data-tag-ids="12" data-csrf="...">
 * or for bulk, data-tag-bulk instead of data-tag-ids (the ticked .bulk-select
 * boxes on the page are the records).
 *
 * Styled with the select picker's panel classes (css: "Select picker"), so
 * the two look and behave alike.
 */
(function () {
    'use strict';

    if (window.itflowTagPicker) {
        return; // re-run by an ajax modal - the listeners are already in place
    }

    var sheetMedia = window.matchMedia('(max-width: 575.98px)');
    var finePointer = window.matchMedia('(pointer: fine)');
    var open = null; // the one open picker

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

    function csrfFor(button) {
        if (button.dataset.csrf) {
            return button.dataset.csrf;
        }
        var input = document.querySelector('input[name="csrf_token"]');
        return input ? input.value : '';
    }

    function bulkIds() {
        return Array.prototype.map.call(document.querySelectorAll('input.bulk-select:checked'), function (box) {
            return box.value;
        });
    }

    /* Swap in the pills the server sent, wherever that record is on the page */
    function redraw(entity, pills) {
        Object.keys(pills).forEach(function (id) {
            document.querySelectorAll('.itflow-tags[data-tag-entity="' + entity + '"][data-tag-record="' + id + '"]').forEach(function (old) {
                var holder = document.createElement('div');
                holder.innerHTML = pills[id];
                var fresh = holder.firstElementChild;
                if (fresh) {
                    // Keep this spot's own filter links
                    fresh.setAttribute('data-tag-filter', old.getAttribute('data-tag-filter') || fresh.getAttribute('data-tag-filter'));
                    // The open picker hangs off the + in the pills being replaced -
                    // move it to the new +, or the next redraw finds it detached
                    // and closes the panel
                    if (open && old.contains(open.anchor)) {
                        var plus = fresh.querySelector('[data-tag-picker]');
                        if (plus) {
                            plus.setAttribute('aria-expanded', 'true');
                            open.anchor = plus;
                            open.button = plus;
                        }
                    }
                    old.replaceWith(fresh);
                }
            });
        });
    }

    function filterFor(entity, ids) {
        var spot = document.querySelector('.itflow-tags[data-tag-entity="' + entity + '"][data-tag-record="' + ids[0] + '"]');
        return spot ? spot.getAttribute('data-tag-filter') || '' : '';
    }

    function Picker(button) {
        this.button = button;
        this.entity = button.dataset.tagEntity;
        this.bulk = button.hasAttribute('data-tag-bulk');
        this.ids = this.bulk ? bulkIds() : String(button.dataset.tagIds || '').split(',').filter(Boolean);
        this.csrf = csrfFor(button);
        // Bulk opens from a menu item that closes - hang the panel off the Action button
        this.anchor = this.bulk ? (document.querySelector('#bulkActionButton > button') || button) : button;
        this.query = '';
        this.rows = [];
        this.active = -1;
        this.busy = 0;
    }

    Picker.prototype.build = function () {
        var self = this;
        var panel = this.panel = make('div', 'itflow-select-panel is-multiple itflow-tag-panel');
        panel.setAttribute('role', 'dialog');
        panel.setAttribute('aria-label', 'Tags');

        var head = make('div', 'itflow-select-sheet-head');
        head.appendChild(make('span', 'itflow-select-sheet-title', 'Tags'));
        var done = make('button', 'btn btn-sm btn-link itflow-select-done', 'Done');
        done.type = 'button';
        done.addEventListener('click', function () {
            self.close(false);
        });
        head.appendChild(done);
        panel.appendChild(head);

        if (this.bulk) {
            panel.appendChild(make('div', 'itflow-tag-panel-note', this.ids.length + ' selected - ticked tags go on all of them, unticked come off'));
        }

        var search = make('div', 'itflow-select-search');
        search.appendChild(make('i', 'fas fa-search'));
        var input = this.input = make('input');
        input.type = 'text';
        input.autocomplete = 'off';
        input.spellcheck = false;
        input.setAttribute('aria-label', 'Search tags');
        input.addEventListener('input', function () {
            self.query = input.value;
            self.draw(false);
        });
        input.addEventListener('keydown', function (e) {
            self.key(e);
        });
        search.appendChild(input);
        panel.appendChild(search);

        var list = this.list = make('div', 'itflow-select-list');
        list.setAttribute('role', 'listbox');
        list.setAttribute('aria-multiselectable', 'true');
        list.setAttribute('aria-label', 'Tags');
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

        var footer = this.footer = make('div', 'itflow-select-footer');
        panel.appendChild(footer);

        panel.addEventListener('mousedown', function (e) {
            if (e.target !== input && !e.target.closest('a, button')) {
                e.preventDefault();
            }
        });

        this.backdrop = make('div', 'itflow-select-backdrop');
    };

    Picker.prototype.show = function () {
        var self = this;
        if (!this.entity || !this.ids.length) {
            return;
        }
        this.build();
        open = this;
        this.sheet = sheetMedia.matches;
        this.panel.classList.toggle('is-sheet', this.sheet);
        this.input.placeholder = 'Loading...';
        this.list.appendChild(make('div', 'itflow-select-empty', 'Loading tags...'));

        var host = this.anchor.closest('.modal, .offcanvas') || document.body;
        if (this.sheet) {
            host.appendChild(this.backdrop);
        }
        host.appendChild(this.panel);
        this.anchor.setAttribute('aria-expanded', 'true');
        this.place();

        itflowGet('ajax.php', { tag_picker: 1, entity: this.entity, ids: this.ids }, function (text) {
            var data;
            try {
                data = JSON.parse(text);
            } catch (e) {
                data = null;
            }
            if (open !== self) {
                return;
            }
            if (!data || !data.tags) {
                self.fail('Could not load the tags.');
                self.close(false);
                return;
            }
            self.data = data;
            self.input.placeholder = data.create ? 'Search or make a tag' : 'Search tags';
            self.draw(false);
            if (finePointer.matches) {
                self.input.focus({ preventScroll: true });
            }
        }, function () {
            self.fail('Could not load the tags.');
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
        this.anchor.setAttribute('aria-expanded', 'false');
        if (refocus && this.anchor.isConnected) {
            this.anchor.focus({ preventScroll: true });
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
        var anchor = this.anchor.isConnected ? this.anchor : null;
        if (!anchor) {
            this.close(false);
            return;
        }
        var rect = anchor.getBoundingClientRect();
        var viewWidth = document.documentElement.clientWidth;
        var viewHeight = window.innerHeight;
        var width = Math.min(288, viewWidth - 16);
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

    /* The checklist, filtered by the search, and the "make it" row. */
    Picker.prototype.draw = function (keep) {
        var self = this;
        var data = this.data;
        if (!data) {
            return;
        }
        var list = this.list;
        var scroll = list.scrollTop;
        var activeId = keep && this.rows[this.active] ? this.rows[this.active].id : null;
        var terms = fold(this.query.trim()).split(/\s+/).filter(Boolean);
        var exact = false;

        list.textContent = '';
        this.rows = [];

        data.tags.forEach(function (tag) {
            var name = fold(tag.name);
            var haystack = name + ' ' + fold(tag.description);
            if (terms.length && !terms.every(function (t) { return haystack.indexOf(t) !== -1; })) {
                return;
            }
            if (name === fold(self.query.trim())) {
                exact = true;
            }
            var all = tag.chosen >= data.records;
            var some = tag.chosen > 0 && !all;
            var index = self.rows.length;
            var node = make('div', 'itflow-select-option' + (all ? ' is-selected' : '') + (some ? ' is-mixed' : ''));
            node.setAttribute('role', 'option');
            node.setAttribute('data-row', index);
            node.setAttribute('aria-selected', all ? 'true' : 'false');
            node.id = 'itflow-tag-option-' + tag.id;

            var box = make('span', 'itflow-select-checkbox');
            box.appendChild(make('i', some ? 'fas fa-minus' : 'fas fa-check'));
            node.appendChild(box);

            var badge = make('span', 'itflow-select-badge');
            badge.style.setProperty('--itflow-select-color', tag.color || 'var(--bs-secondary)');
            badge.appendChild(make('i', 'fas fa-' + tag.icon));
            badge.setAttribute('aria-hidden', 'true');
            node.appendChild(badge);

            var text = make('span', 'itflow-select-text');
            text.appendChild(make('span', 'itflow-select-label', tag.name));
            var about = [tag.description || '', some ? tag.chosen + ' of ' + data.records : ''].filter(Boolean).join(' · ');
            if (about) {
                text.appendChild(make('span', 'itflow-select-description', about));
            }
            node.appendChild(text);
            list.appendChild(node);
            self.rows.push({ node: node, id: tag.id, tag: tag });
        });

        var typed = this.query.trim();
        if (data.create && typed && !exact) {
            var index = this.rows.length;
            var create = make('div', 'itflow-select-option itflow-select-create');
            create.setAttribute('role', 'option');
            create.setAttribute('data-row', index);
            create.id = 'itflow-tag-option-new';
            var plus = make('span', 'itflow-select-icon');
            plus.appendChild(make('i', 'fas fa-plus'));
            create.appendChild(plus);
            var createText = make('span', 'itflow-select-text');
            createText.appendChild(make('span', 'itflow-select-label', 'Make tag "' + typed + '"'));
            create.appendChild(createText);
            list.appendChild(create);
            this.rows.push({ node: create, create: typed });
        }

        if (!this.rows.length) {
            list.appendChild(make('div', 'itflow-select-empty', typed ? 'No tags match' : 'No tags yet' + (data.create ? ' - type a name to make one' : '')));
        }

        // Footer: how many the record has, and where tags are managed
        this.footer.textContent = '';
        var chosen = data.tags.filter(function (tag) { return tag.chosen >= data.records; }).length;
        this.footer.appendChild(make('span', 'me-auto', chosen ? chosen + ' tagged' : 'No tags'));
        if (data.manage) {
            var manage = make('a', 'btn btn-sm btn-link', 'Manage tags');
            manage.href = data.manage;
            this.footer.appendChild(manage);
        }
        // Saving happens on every tick, so this only closes - no reaching
        // past the panel to click off it
        var done = make('button', 'btn btn-sm btn-primary itflow-tag-done', 'Done');
        done.type = 'button';
        done.addEventListener('click', function () {
            self.close(true);
        });
        this.footer.appendChild(done);

        var active = -1;
        if (activeId !== null) {
            this.rows.forEach(function (row, i) {
                if (row.id === activeId) {
                    active = i;
                }
            });
        }
        if (keep) {
            list.scrollTop = scroll;
        }
        this.setActive(active === -1 ? (this.rows.length ? 0 : -1) : active, false);
        this.place();
    };

    Picker.prototype.setActive = function (index, scroll) {
        var old = this.rows[this.active];
        if (old) {
            old.node.classList.remove('is-active');
        }
        this.active = index;
        var row = this.rows[index];
        if (!row) {
            this.input.removeAttribute('aria-activedescendant');
            return;
        }
        row.node.classList.add('is-active');
        this.input.setAttribute('aria-activedescendant', row.node.id);
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

    /* Tick or untick a tag - or make the typed one - and save it. */
    Picker.prototype.pick = function (index) {
        var self = this;
        var row = this.rows[index];
        if (!row) {
            return;
        }
        var data = this.data;
        var post = { csrf_token: this.csrf, entity: this.entity, ids: this.ids, filter: filterFor(this.entity, this.ids) };
        var before = null;

        if (row.create) {
            post.tag_picker_create = 1;
            post.name = row.create;
        } else {
            // A mixed tag goes on everywhere; a full one comes off everywhere
            var on = row.tag.chosen < data.records;
            post.tag_picker_set = 1;
            post.tag_id = row.id;
            post.on = on ? 1 : 0;
            before = row.tag.chosen;
            row.tag.chosen = on ? data.records : 0;
            this.draw(true);
        }

        this.busy++;
        itflowPost('ajax.php', post, function (text) {
            self.busy--;
            var result;
            try {
                result = JSON.parse(text);
            } catch (e) {
                result = null;
            }
            if (!result || !result.pills) {
                failed();
                return;
            }
            redraw(self.entity, result.pills);
            if (row.create && open === self) {
                // Load the list again so the new tag sits in its place, ticked
                self.query = '';
                self.input.value = '';
                itflowGet('ajax.php', { tag_picker: 1, entity: self.entity, ids: self.ids }, function (fresh) {
                    try {
                        self.data = JSON.parse(fresh);
                    } catch (e) {
                        return;
                    }
                    if (open === self) {
                        self.draw(false);
                    }
                });
            }
        }, failed);

        function failed() {
            self.busy--;
            if (before !== null) {
                row.tag.chosen = before;
                if (open === self) {
                    self.draw(true);
                }
            }
            self.fail('The tag change was not saved - try again.');
        }
    };

    /* ---------- wiring ---------- */

    document.addEventListener('click', function (e) {
        var button = e.target.closest('[data-tag-picker]');
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

    /*
     * Clicking off the panel closes it - and that click does nothing else.
     * Otherwise closing the picker by clicking beside it could land on a
     * row link, a pill or a button and take you somewhere.
     */
    var swallow = false;
    function outside(e) {
        if (open && !open.panel.contains(e.target) && !e.target.closest('[data-tag-picker]')) {
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
        // A fresh press that did not close a picker clears any leftover flag
        // (a mousedown whose click never came, e.g. a drag off the page)
        if (!open && !e.target.closest('[data-tag-picker]')) {
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
    document.addEventListener('hide.bs.modal', function (e) {
        if (open && e.target.contains(open.anchor)) {
            open.close(false);
        }
    });

    window.itflowTagPicker = true;
})();
