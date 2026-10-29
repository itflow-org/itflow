/*
 * Client portal behaviour - everything the portal pages need from script, in
 * one file, because the portal's Content-Security-Policy (default-src 'self')
 * turns inline scripts off:
 *
 *  - Dark mode, the contact's own choice, kept in a cookie the header reads
 *  - The editor on any .tinymce textarea, in the light or dark skin
 *  - Attachment drop zones: drop files on [data-portal-drop] and they go into
 *    its file input, listed by name and size
 *  - A form marked data-portal-once can't be sent twice
 *  - Print buttons ([data-portal-print]) - an onclick would be blocked
 *  - Pay invoices: the total and the Pay button follow the invoices ticked
 *  - Tables a page left unwrapped scroll sideways on a phone instead of the page
 *
 * Taking a card through Stripe is js/portal_stripe.js, on those pages only.
 */
(function () {
    'use strict';

    // ---------- Dark mode ----------
    document.addEventListener('click', function (e) {
        var toggle = e.target.closest('[data-portal-theme-toggle]');
        if (!toggle) {
            return;
        }
        e.preventDefault();
        var dark = document.documentElement.getAttribute('data-bs-theme') !== 'dark';
        document.cookie = 'itflow_portal_theme=' + (dark ? 'dark' : 'light') + '; path=/; max-age=31536000; SameSite=Lax';
        // The editor draws its own chrome and content, so a reload is what
        // brings it round with the page
        window.location.reload();
    });

    // ---------- The editor ----------
    function editorSkin() {
        var dark = document.documentElement.getAttribute('data-bs-theme') === 'dark';
        var root = getComputedStyle(document.documentElement);
        var body = getComputedStyle(document.body);
        function v(name, fallback) {
            return root.getPropertyValue(name).trim() || fallback;
        }
        return {
            skin: dark ? 'oxide-dark' : 'oxide',
            content_css: dark ? 'dark' : 'default',
            content_style: 'body{background-color:' + v('--bs-body-bg', '#fff') + ';color:' + v('--bs-body-color', '#212529') + ';'
                + 'font-family:' + body.fontFamily + ';font-size:' + body.fontSize + ';}'
                + 'a{color:' + v('--itflow-accent', '#007bff') + ';}'
                + '.mce-content-body[data-mce-placeholder]:not(.mce-visualblocks)::before{color:' + v('--bs-secondary-color', '#6c757d') + ';}'
        };
    }

    if (window.tinymce && document.querySelector('textarea.tinymce')) {
        var skin = editorSkin();
        tinymce.init({
            selector: 'textarea.tinymce',
            browser_spellcheck: true,
            resize: true,
            min_height: 220,
            max_height: 600,
            promotion: false,
            branding: false,
            menubar: false,
            statusbar: false,
            license_key: 'gpl',
            skin: skin.skin,
            content_css: skin.content_css,
            content_style: skin.content_style,
            toolbar: 'bold italic forecolor | bullist numlist | link table | removeformat',
            mobile: {
                menubar: false,
                plugins: 'autosave lists autolink',
                toolbar: 'bold italic bullist numlist'
            },
            plugins: 'link image lists table autoresize',
            setup: function (editor) {
                // A required field the editor hides can't take the browser's
                // own "fill this in" - so the form is checked here instead
                editor.on('init', function () {
                    var textarea = editor.getElement();
                    textarea.removeAttribute('required');
                    var form = textarea.form;
                    if (form && textarea.hasAttribute('data-required') && !form.hasAttribute('data-editor-checked')) {
                        form.setAttribute('data-editor-checked', '');
                        form.addEventListener('submit', function (ev) {
                            tinymce.triggerSave();
                            var empty = !editor.getContent({format: 'text'}).trim() && !/<img/i.test(editor.getContent());
                            if (empty) {
                                ev.preventDefault();
                                ev.stopImmediatePropagation();
                                var holder = editor.getContainer();
                                holder.classList.add('is-invalid');
                                var note = form.querySelector('[data-editor-required-note]');
                                if (note) {
                                    note.hidden = false;
                                }
                                editor.focus();
                            }
                        }, true);
                        editor.on('input change', function () {
                            editor.getContainer().classList.remove('is-invalid');
                            var note = form.querySelector('[data-editor-required-note]');
                            if (note) {
                                note.hidden = true;
                            }
                        });
                    }
                });
            }
        });
    }

    // ---------- Attachments ----------
    function formatSize(bytes) {
        if (bytes >= 1048576) {
            return (bytes / 1048576).toFixed(1) + ' MB';
        }
        return Math.max(1, Math.round(bytes / 1024)) + ' KB';
    }

    function listFiles(zone) {
        var input = zone.querySelector('input[type=file]');
        var list = zone.querySelector('[data-portal-files]');
        if (!input || !list) {
            return;
        }
        list.replaceChildren();
        Array.prototype.forEach.call(input.files, function (file) {
            var chip = document.createElement('span');
            chip.className = 'itflow-portal-file-chip';
            var icon = document.createElement('i');
            icon.className = 'fas fa-paperclip';
            var name = document.createElement('span');
            name.className = 'text-truncate';
            name.textContent = file.name;
            var size = document.createElement('span');
            size.className = 'text-body-secondary';
            size.textContent = formatSize(file.size);
            chip.append(icon, name, size);
            list.append(chip);
        });
        list.hidden = input.files.length === 0;
    }

    document.querySelectorAll('[data-portal-drop]').forEach(function (zone) {
        var input = zone.querySelector('input[type=file]');
        if (!input) {
            return;
        }
        input.addEventListener('change', function () {
            listFiles(zone);
        });
        ['dragenter', 'dragover'].forEach(function (type) {
            zone.addEventListener(type, function (e) {
                e.preventDefault();
                zone.classList.add('is-dragging');
            });
        });
        ['dragleave', 'drop'].forEach(function (type) {
            zone.addEventListener(type, function (e) {
                e.preventDefault();
                if (type === 'dragleave' && zone.contains(e.relatedTarget)) {
                    return;
                }
                zone.classList.remove('is-dragging');
            });
        });
        zone.addEventListener('drop', function (e) {
            if (!e.dataTransfer || !e.dataTransfer.files.length) {
                return;
            }
            // Add to what was already picked rather than replace it
            var merged = new DataTransfer();
            Array.prototype.forEach.call(input.files, function (f) { merged.items.add(f); });
            Array.prototype.forEach.call(e.dataTransfer.files, function (f) { merged.items.add(f); });
            input.files = merged.files;
            listFiles(zone);
        });
    });

    // ---------- Sent once ----------
    document.querySelectorAll('form[data-portal-once]').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            if (e.defaultPrevented) {
                return;
            }
            form.querySelectorAll('button[type=submit], button:not([type])').forEach(function (button) {
                // Disabled after this tick, so the button's own name=value still goes with the form
                setTimeout(function () { button.disabled = true; }, 0);
            });
        });
    });

    // ---------- Print ----------
    document.addEventListener('click', function (e) {
        if (e.target.closest('[data-portal-print]')) {
            e.preventDefault();
            window.print();
        }
    });

    // ---------- Paying invoices ----------
    // The ticks on Pay invoices: the total, how many, and the button's words
    // follow them, and a total over the card limit is stopped here as well as
    // on the server
    document.querySelectorAll('form[data-portal-pay]').forEach(function (form) {
        var boxes = Array.prototype.slice.call(form.querySelectorAll('input[data-pay-cents]'));
        var all = form.querySelector('[data-pay-all]');
        var button = form.querySelector('[data-pay-button]');
        var limit = parseInt(form.getAttribute('data-pay-limit') || '0', 10);
        var max = parseInt(form.getAttribute('data-pay-max') || '0', 10);
        var money;
        try {
            money = new Intl.NumberFormat(form.getAttribute('data-pay-locale') || undefined, {style: 'currency', currency: form.getAttribute('data-pay-currency') || 'USD'});
        } catch (err) {
            money = new Intl.NumberFormat(undefined, {style: 'currency', currency: 'USD'});
        }

        function text(selector, value) {
            var el = form.querySelector(selector);
            if (el) {
                el.textContent = value;
            }
        }

        function update() {
            var cents = 0;
            var count = 0;
            boxes.forEach(function (box) {
                if (box.checked) {
                    cents += parseInt(box.getAttribute('data-pay-cents'), 10) || 0;
                    count++;
                }
                var row = box.closest('li');
                if (row) {
                    row.classList.toggle('is-selected', box.checked);
                }
            });
            var amount = money.format(cents / 100);
            var method = form.querySelector('input[name=method]:checked');
            var over = limit > 0 && cents >= limit;
            var tooMany = max > 0 && count > max;
            text('[data-pay-total]', amount);
            text('[data-pay-count]', count + (count === 1 ? ' invoice' : ' invoices'));
            text('[data-pay-label]', !count ? 'Tick an invoice to pay' : (tooMany ? 'Pay up to ' + max + ' invoices at a time'
                : (method && method.value === 'new' ? 'Continue to pay ' : 'Pay ') + amount));
            var note = form.querySelector('[data-pay-limit-note]');
            if (note) {
                note.hidden = !over;
            }
            button.disabled = !count || over || tooMany;
            if (all) {
                all.checked = count === boxes.length;
                all.indeterminate = count > 0 && count < boxes.length;
            }
        }

        if (all) {
            all.addEventListener('change', function () {
                boxes.forEach(function (box) {
                    box.checked = all.checked;
                });
                update();
            });
        }
        boxes.forEach(function (box) {
            box.addEventListener('change', update);
        });
        form.querySelectorAll('input[name=method]').forEach(function (radio) {
            radio.addEventListener('change', update);
        });
        update();
    });

    // ---------- Tables ----------
    document.querySelectorAll('.itflow-portal-main table.table').forEach(function (table) {
        if (table.closest('.table-responsive')) {
            return;
        }
        var wrap = document.createElement('div');
        wrap.className = 'table-responsive';
        table.parentNode.insertBefore(wrap, table);
        wrap.appendChild(table);
    });
})();
