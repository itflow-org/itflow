/*
 * Admin > Settings (admin/settings*.php, functions/settings.php), in the
 * browser:
 *
 *   form[data-settings-form]      once something is changed, the save bar says
 *                                 so and leaving the page asks first; Undo
 *                                 changes puts it back. A form sent back with
 *                                 something wrong counts as changed.
 *   #setting=<name>               that setting is scrolled to, focused and lit
 *                                 up - on its tab, when the page has tabs
 *   [data-settings-find]          Find a setting, on the overview
 *   [data-settings-preview=<id>]  a picked picture shown in the <img> with
 *                                 that id before it is sent
 *   [data-settings-theme]         a theme colour shown across the page as it
 *                                 is picked
 *   [data-settings-sample]        money and the time now, as the picked
 *                                 language, currency and timezone write them
 *   [data-settings-next]          the number the next ticket, invoice... gets
 *   [data-settings-depends-on=name]  faded while that switch is off
 *
 * Loaded on every admin page (includes/footer.php); it does nothing where
 * none of these are.
 */
(function () {
    'use strict';

    // A form's fields as one string - what was there when the page opened, against what is there now
    function serialize(form) {
        var parts = [];
        Array.prototype.forEach.call(form.elements, function (el) {
            if (!el.name || el.disabled || el.name === 'csrf_token' || ['submit', 'button', 'reset'].indexOf(el.type) !== -1) {
                return;
            }
            if (el.type === 'file') {
                parts.push(el.name + '=' + (el.files && el.files.length ? el.files[0].name + ':' + el.files[0].size : ''));
            } else if (el.type === 'checkbox' || el.type === 'radio') {
                if (el.checked) {
                    parts.push(el.name + '=' + el.value);
                }
            } else if (el.multiple) {
                parts.push(el.name + '=' + Array.prototype.map.call(el.selectedOptions, function (o) { return o.value; }).join(','));
            } else {
                parts.push(el.name + '=' + el.value);
            }
        });
        return parts.join('&');
    }

    function initForm(form) {
        var bar = form.querySelector('[data-settings-savebar]');
        var refused = !!form.querySelector('[data-field-error]');
        var initial = null;
        var sending = false;

        function dirty() {
            return refused || (initial !== null && serialize(form) !== initial);
        }
        function show() {
            var is = dirty();
            form.classList.toggle('is-dirty', is);
            if (bar) {
                bar.classList.toggle('is-dirty', is);
            }
            return is;
        }

        // After the pickers, the phone field and the rest have set themselves up
        setTimeout(function () {
            initial = serialize(form);
            show();
        }, 0);

        form.addEventListener('input', show);
        form.addEventListener('change', show);
        form.addEventListener('submit', function (e) {
            if (!e.defaultPrevented) {
                sending = true;
            }
        });

        form.addEventListener('reset', function (e) {
            // Sent back with something wrong, the form's own values are the ones typed - back to what is saved
            if (refused) {
                e.preventDefault();
                sending = true;
                window.location.href = window.location.pathname + window.location.search;
                return;
            }
            setTimeout(function () {
                form.querySelectorAll('select').forEach(function (select) {
                    if (select.itflowSelect) {
                        select.itflowSelect.setValue(select.multiple ? Array.prototype.map.call(select.selectedOptions, function (o) { return o.value; }) : select.value);
                    }
                });
                form.querySelectorAll('input[data-itflow-phone]').forEach(function (input) {
                    var hidden = form.querySelector('input[name="' + input.dataset.itflowPhone + '"]');
                    var instance = window.intlTelInput && window.intlTelInput.getInstance ? window.intlTelInput.getInstance(input) : null;
                    if (instance && hidden && typeof isoForDialCode === 'function') {
                        var iso = isoForDialCode(hidden.value, '');
                        if (iso) {
                            instance.setSelectedCountry(iso);
                        }
                        hidden.value = hidden.defaultValue;
                    }
                });
                form.dispatchEvent(new CustomEvent('settings:reset'));
                show();
            }, 0);
        });

        window.addEventListener('beforeunload', function (e) {
            if (!sending && dirty()) {
                e.preventDefault();
                e.returnValue = '';
            }
        });
    }

    // #setting=<name> - the setting, scrolled to and lit up
    function goToSetting() {
        var match = /^#setting=([A-Za-z0-9_-]+)$/.exec(window.location.hash);
        if (!match) {
            return;
        }
        var name = match[1];
        var target = document.querySelector('[data-setting="' + name + '"]');
        if (!target) {
            var field = document.querySelector('[name="' + name + '"]');
            target = field ? (field.closest('.mb-3, .form-check, .col-md-6, .col-sm-6, .col') || field) : null;
        }
        if (!target) {
            return;
        }
        var pane = target.closest('.tab-pane');
        if (pane && !pane.classList.contains('active') && window.bootstrap) {
            var tab = document.querySelector('[data-bs-target="#' + pane.id + '"]');
            if (tab) {
                window.bootstrap.Tab.getOrCreateInstance(tab).show();
            }
        }
        setTimeout(function () {
            target.scrollIntoView({ block: 'center' });
            target.classList.add('itflow-settings-lit');
            var input = target.querySelector('input:not([type="hidden"]), select, textarea');
            if (input && input.offsetParent !== null) {
                (input.itflowSelect && input.itflowSelect.control ? input.itflowSelect.control : input).focus({ preventScroll: true });
            }
            setTimeout(function () {
                target.classList.remove('itflow-settings-lit');
            }, 2600);
        }, 150);
    }

    // Find a setting - every page and setting, searched as you type
    function initFind(box) {
        var input = box.querySelector('[data-settings-find-input]');
        var results = box.querySelector('[data-settings-results]');
        var index = [];
        try {
            index = JSON.parse(box.querySelector('[data-settings-index]').textContent);
        } catch (err) {
            index = [];
        }
        var active = -1;

        function score(entry, terms) {
            var label = entry.label.toLowerCase();
            var where = entry.where.toLowerCase();
            var total = 0;
            for (var i = 0; i < terms.length; i++) {
                var term = terms[i];
                if (label.indexOf(term) === 0) {
                    total += 6;
                } else if (label.indexOf(' ' + term) !== -1) {
                    total += 5;
                } else if (label.indexOf(term) !== -1) {
                    total += 4;
                } else if (where.indexOf(term) !== -1) {
                    total += 2;
                } else if (entry.words.indexOf(term) !== -1) {
                    total += 1;
                } else {
                    return 0;
                }
            }
            return total + (entry.page ? 0.5 : 0);
        }

        // The label, with what was typed marked in it
        function marked(text, terms) {
            var span = document.createElement('span');
            var lower = text.toLowerCase();
            var at = -1;
            var length = 0;
            terms.forEach(function (term) {
                var i = lower.indexOf(term);
                if (i !== -1 && (at === -1 || term.length > length)) {
                    at = i;
                    length = term.length;
                }
            });
            if (at === -1) {
                span.textContent = text;
                return span;
            }
            span.appendChild(document.createTextNode(text.slice(0, at)));
            var mark = document.createElement('mark');
            mark.textContent = text.slice(at, at + length);
            span.appendChild(mark);
            span.appendChild(document.createTextNode(text.slice(at + length)));
            return span;
        }

        function light() {
            results.querySelectorAll('[data-settings-result]').forEach(function (item, i) {
                item.classList.toggle('active', i === active);
            });
        }

        function render() {
            var query = input.value.toLowerCase().trim();
            results.innerHTML = '';
            active = -1;
            if (!query) {
                results.hidden = true;
                return;
            }
            var terms = query.split(/\s+/);
            var found = index.map(function (entry) {
                return [score(entry, terms), entry];
            }).filter(function (pair) {
                return pair[0] > 0;
            }).sort(function (a, b) {
                return b[0] - a[0];
            }).slice(0, 12);

            if (!found.length) {
                var none = document.createElement('div');
                none.className = 'list-group-item small text-body-secondary';
                none.setAttribute('data-settings-none', '');
                none.textContent = 'No setting matches "' + input.value.trim() + '".';
                results.appendChild(none);
            }
            found.forEach(function (pair) {
                var entry = pair[1];
                var item = document.createElement('a');
                item.className = 'list-group-item list-group-item-action d-flex gap-3 align-items-center';
                item.href = entry.url;
                item.setAttribute('data-settings-result', '');
                var icon = document.createElement('i');
                icon.className = 'fas fa-fw text-body-secondary ' + entry.icon;
                var text = document.createElement('span');
                text.className = 'min-w-0';
                var label = marked(entry.label, terms);
                label.className = 'd-block fw-semibold';
                var where = document.createElement('span');
                where.className = 'd-block small text-body-secondary';
                where.textContent = entry.where;
                text.appendChild(label);
                text.appendChild(where);
                item.appendChild(icon);
                item.appendChild(text);
                results.appendChild(item);
            });
            results.hidden = false;
            active = found.length ? 0 : -1;
            light();
        }

        input.addEventListener('input', render);
        input.addEventListener('keydown', function (e) {
            var items = results.querySelectorAll('[data-settings-result]');
            if (e.key === 'ArrowDown' && items.length) {
                e.preventDefault();
                active = (active + 1) % items.length;
                light();
            } else if (e.key === 'ArrowUp' && items.length) {
                e.preventDefault();
                active = (active - 1 + items.length) % items.length;
                light();
            } else if (e.key === 'Enter' && items[active]) {
                e.preventDefault();
                window.location.href = items[active].href;
            } else if (e.key === 'Escape') {
                input.value = '';
                render();
            }
        });
    }

    // A picked picture, shown before it is sent
    function initPreview(input) {
        var img = document.getElementById(input.dataset.settingsPreview);
        if (!img) {
            return;
        }
        var original = img.getAttribute('src');
        var empty = img.parentNode.querySelector('[data-settings-preview-empty]');
        var url = null;
        function show() {
            if (url) {
                URL.revokeObjectURL(url);
                url = null;
            }
            var file = input.files && input.files[0];
            if (file && /^image\//.test(file.type)) {
                url = URL.createObjectURL(file);
                img.src = url;
                img.hidden = false;
            } else if (original) {
                img.src = original;
            } else {
                img.removeAttribute('src');
                img.hidden = true;
            }
            if (empty) {
                empty.hidden = !img.hidden;
            }
        }
        input.addEventListener('change', show);
        if (input.form) {
            input.form.addEventListener('settings:reset', show);
        }
    }

    // A theme colour shown across the page as it is picked
    function initTheme(form) {
        function apply() {
            var picked = form.querySelector('[data-settings-theme]:checked');
            if (!picked) {
                return;
            }
            Array.prototype.slice.call(document.body.classList).forEach(function (name) {
                if (name.indexOf('theme-') === 0) {
                    document.body.classList.remove(name);
                }
            });
            document.body.classList.add('theme-' + picked.value);
            document.body.setAttribute('data-lte-primary', picked.value);
        }
        form.addEventListener('change', function (e) {
            if (e.target.matches('[data-settings-theme]')) {
                apply();
            }
        });
        form.addEventListener('settings:reset', apply);
    }

    // Money and the time now, as the picked language, currency and timezone write them
    function initSample(form) {
        var locale = form.querySelector('[name="company_locale"]');
        var currency = form.querySelector('[name="company_currency"]');
        var timezone = form.querySelector('[name="config_timezone"]');
        var sample = form.querySelector('[data-settings-sample]');
        if (!locale || !currency || !timezone || !sample) {
            return;
        }
        function draw() {
            var tag = (locale.value || '').replace(/_/g, '-');
            try {
                sample.querySelector('[data-settings-sample-money]').textContent = new Intl.NumberFormat(tag, { style: 'currency', currency: currency.value }).format(1234.56);
                sample.querySelector('[data-settings-sample-date]').textContent = new Intl.DateTimeFormat(tag, { dateStyle: 'full', timeStyle: 'short', timeZone: timezone.value || undefined }).format(new Date());
                sample.hidden = false;
            } catch (err) {
                sample.hidden = true;
            }
        }
        [locale, currency, timezone].forEach(function (select) {
            select.addEventListener('change', draw);
        });
        form.addEventListener('settings:reset', draw);
        draw();
    }

    // The number the next one will get - its prefix and number as they are typed
    function initNext(box) {
        var names = box.dataset.settingsNext.split(',');
        var form = box.closest('form');
        var prefix = form ? form.querySelector('[name="' + names[0] + '"]') : null;
        var number = form ? form.querySelector('[name="' + names[1] + '"]') : null;
        var out = box.querySelector('[data-settings-next-number]');
        if (!prefix || !number || !out) {
            return;
        }
        var clash = box.querySelector('[data-settings-clash]');
        var inUse = parseInt(box.dataset.settingsInUseNumber || '0', 10);
        function draw() {
            out.textContent = prefix.value + number.value;
            if (clash) {
                // Only known for the prefix as saved - another one is checked on saving
                clash.hidden = !(prefix.value === box.dataset.settingsInUsePrefix && number.value !== '' && parseInt(number.value, 10) <= inUse);
            }
        }
        prefix.addEventListener('input', draw);
        number.addEventListener('input', draw);
        form.addEventListener('settings:reset', draw);
    }

    // A switch whose fields mean nothing while it is off - shown faded, as a hint
    function initDepends(group) {
        var form = group.closest('form');
        var toggle = form ? form.querySelector('[name="' + group.dataset.settingsDependsOn + '"]') : null;
        if (!toggle) {
            return;
        }
        function sync() {
            group.classList.toggle('is-off', !toggle.checked);
        }
        toggle.addEventListener('change', sync);
        form.addEventListener('settings:reset', sync);
        sync();
    }

    function init() {
        document.querySelectorAll('[data-settings-next]').forEach(initNext);
        document.querySelectorAll('[data-settings-depends-on]').forEach(initDepends);
        document.querySelectorAll('form[data-settings-form]').forEach(initForm);
        document.querySelectorAll('[data-settings-find]').forEach(initFind);
        document.querySelectorAll('input[type="file"][data-settings-preview]').forEach(initPreview);
        document.querySelectorAll('form[data-settings-theme-form]').forEach(initTheme);
        document.querySelectorAll('form[data-settings-sample-form]').forEach(initSample);
        goToSetting();
    }

    window.addEventListener('hashchange', goToSetting);

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
