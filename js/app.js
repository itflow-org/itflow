/**
 * Delegated listener that binds exactly once.
 *
 * modal_footer.php re-loads this file every time an ajax modal opens, which is
 * why the jQuery originals used a namespaced .off().on() - without it the
 * handlers stacked up and fired once per modal ever opened. The named flag on
 * window is the vanilla equivalent. `this` is the matched element, matching
 * jQuery's delegation contract so the handler bodies are unchanged.
 */
/**
 * Run one initialiser in isolation.
 *
 * itflowInit() sets up eight independent libraries in sequence. Without this,
 * a throw in any one of them aborts the whole function and every initialiser
 * after it silently never runs - which is exactly the kind of failure that
 * looks like "everything is broken" while the console shows one error from a
 * library you were not looking at.
 */
function itflowStep(name, fn) {
    try {
        fn();
    } catch (e) {
        console.error('itflow init [' + name + '] failed:', e);
    }
}

/**
 * TinyMCE skin options for the current colour mode.
 *
 * TinyMCE renders its chrome in its own DOM and its content inside an iframe,
 * so it inherits nothing from the page - a dark page still got a white editor.
 * Both the oxide-dark UI skin and the dark content stylesheet are already
 * vendored under libs/tinymce/skins, so this only has to point at them.
 */
function itflowTinyMceSkin() {
    var dark = document.documentElement.getAttribute('data-bs-theme') === 'dark';
    var root = getComputedStyle(document.documentElement);
    var body = getComputedStyle(document.body);

    function v(name, fallback) {
        var value = root.getPropertyValue(name).trim();
        return value || fallback;
    }

    // The editing surface lives in an iframe, so no page CSS reaches it and
    // custom properties do not cross the boundary either - the values have to
    // be read here and passed through as literal text. Without this the editor
    // keeps TinyMCE's own blue-slate dark (#222f3e) next to the app's neutral
    // grey (#212529), which is close enough to look like a mistake.
    var contentStyle =
        'body{' +
            'background-color:' + v('--bs-body-bg', '#fff') + ';' +
            'color:' + v('--bs-body-color', '#212529') + ';' +
            'font-family:' + body.fontFamily + ';' +
            'font-size:' + body.fontSize + ';' +
        '}' +
        'a{color:' + v('--itflow-accent', '#007bff') + ';}' +
        'table td,table th{border-color:' + v('--bs-border-color', '#dee2e6') + ';}';

    return {
        skin: dark ? 'oxide-dark' : 'oxide',
        content_css: dark ? 'dark' : 'default',
        content_style: contentStyle
    };
}

/**
 * Chart.js defaults for the current colour mode.
 *
 * Charts are drawn to a <canvas>, so no CSS reaches them - every colour has to
 * be handed to Chart.js as a literal. The pages used to hardcode
 * Chart.defaults.color = '#292b2c', which was fine while the cards were
 * effectively light but is invisible on a real dark background.
 *
 * Returns the axis/grid colour too, since the charts set that per-scale.
 */
function itflowChartDefaults() {
    var root = getComputedStyle(document.documentElement);
    var body = getComputedStyle(document.body);

    function v(name, fallback) {
        return (root.getPropertyValue(name) || '').trim() || fallback;
    }

    var text = v('--bs-body-color', '#292b2c');
    var grid = v('--bs-border-color', 'rgba(0, 0, 0, .125)');

    if (window.Chart) {
        Chart.defaults.font.family = body.fontFamily;
        Chart.defaults.color = text;
        Chart.defaults.borderColor = grid;
    }

    return { text: text, grid: grid };
}

function itflowBindOnce(name, type, selector, handler) {
    window.itflowBound = window.itflowBound || {};
    if (window.itflowBound[name]) {
        return;
    }
    window.itflowBound[name] = true;
    document.addEventListener(type, function (e) {
        const match = e.target.closest(selector);
        if (match) {
            handler.call(match, e);
        }
    });
}

/**
 * Run fn once the DOM is ready, the way jQuery's .ready() did.
 *
 * js/ajax_modal.js injects a modal payload's <script> tags long after
 * DOMContentLoaded has fired, so a bare
 * document.addEventListener('DOMContentLoaded', ...) inside one of them
 * registers for an event that will never come again and the body silently
 * never runs. jQuery's .ready() invoked the callback immediately when the
 * document was already parsed, which is why that pattern worked before the
 * vanilla conversion. Modal payload scripts must use this instead.
 */
function itflowReady(fn) {
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', fn);
    } else {
        fn();
    }
}

function itflowInit() {
    // Prevents resubmit on forms
    if (window.history.replaceState) {
        window.history.replaceState(null, null, window.location.href);
    }

    // Fade the legacy #alert box out after 5s, then collapse it
    (function () {
        const alertEl = document.getElementById('alert');
        if (!alertEl) {
            return;
        }
        setTimeout(function () {
            alertEl.style.transition = 'opacity .5s linear';
            alertEl.style.opacity = '0';
            setTimeout(function () {
                alertEl.style.overflow = 'hidden';
                alertEl.style.transition = 'height .5s ease, margin .5s ease, padding .5s ease';
                alertEl.style.height = alertEl.offsetHeight + 'px';
                void alertEl.offsetHeight;
                alertEl.style.height = '0px';
                alertEl.style.marginTop = '0';
                alertEl.style.marginBottom = '0';
                alertEl.style.paddingTop = '0';
                alertEl.style.paddingBottom = '0';
                setTimeout(function () {
                    alertEl.style.display = 'none';
                }, 500);
            }, 500);
        }, 5000);
    })();

    // Enhance any select that is not enhanced yet. On a normal page load
    // js/tom_select.js has already done the work and every element here is
    // guarded, so this is a no-op; it earns its place on ajax modals, whose
    // markup does not exist until includes/modal_footer.php re-executes this
    // file. Scoped to `select.select2` deliberately - a bare '.select2' also
    // matches the .ts-wrapper divs left by earlier enhancement, because Tom
    // Select copies the source element's classes onto them. See
    // js/tom_select.js.
    itflowStep('tom-select', function () {
        document.querySelectorAll('select.select2').forEach(function (el) {
            initTomSelect(el);
        });
    });

    // Initialize TinyMCE
    tinymce.init({
        ...itflowTinyMceSkin(),
        selector: '.tinymce-simple',
        browser_spellcheck: true,
        contextmenu: false,
        resize: true,
        min_height: 300,
        max_height: 600,
        promotion: false,
        branding: false,
        menubar: false,
        statusbar: false,
        toolbar: [
            { name: 'styles', items: ['styles'] },
            { name: 'formatting', items: ['bold', 'italic', 'forecolor'] },
            { name: 'link', items: ['link'] },
            { name: 'lists', items: ['bullist', 'numlist'] },
            { name: 'alignment', items: ['alignleft', 'aligncenter', 'alignright', 'alignjustify'] },
            { name: 'indentation', items: ['outdent', 'indent'] },
            { name: 'table', items: ['table'] },
            { name: 'extra', items: ['code', 'fullscreen'] }
        ],
        mobile: {
            menubar: false,
            plugins: 'autosave lists autolink',
            toolbar: 'bold italic styles'
        },
        convert_urls: false,
        plugins: 'link image lists table code codesample fullscreen autoresize',
        setup: function (editor) {
            editor.on('init', function() {
                window.onbeforeunload = function() {
                    // If editor is dirty AND not inside a visible modal → warn
                    const inVisibleModal = editor.getContainer()?.closest('.modal.show');
                    if (!inVisibleModal && editor.isDirty()) {
                        return "You have unsaved changes. Are you sure you want to leave?";
                    }
                };

                // When the modal closes, mark editor clean
                const modal = editor.getContainer()?.closest('.modal');
                if (modal) {
                    modal.addEventListener('hidden.bs.modal', () => {
                        editor.undoManager.clear();
                        editor.setDirty(false);
                    });
                }
            });
        },
        license_key: 'gpl'
    });

    // Initialize TinyMCE with AI
    tinymce.init({
        ...itflowTinyMceSkin(),
        selector: '.tinymce',
        browser_spellcheck: true,
        contextmenu: false,
        resize: true,
        min_height: 300,
        max_height: 600,
        promotion: false,
        branding: false,
        menubar: false,
        statusbar: false,
        toolbar: [
            { name: 'styles', items: ['styles'] },
            { name: 'formatting', items: ['bold', 'italic', 'forecolor'] },
            { name: 'link', items: ['link'] },
            { name: 'lists', items: ['bullist', 'numlist'] },
            { name: 'alignment', items: ['alignleft', 'aligncenter', 'alignright', 'alignjustify'] },
            { name: 'indentation', items: ['outdent', 'indent'] },
            { name: 'table', items: ['table'] },
            { name: 'extra', items: ['code', 'fullscreen'] },
            { name: 'ai', items: ['reword', 'undo', 'redo'] }
        ],
        mobile: {
            menubar: false,
            plugins: 'autosave lists autolink',
            toolbar: 'bold italic styles'
        },
        convert_urls: false,
        plugins: 'link image lists table code codesample fullscreen autoresize',
        license_key: 'gpl',
        setup: function(editor) {
            editor.on('init', function() {
                window.onbeforeunload = function() {
                    // If editor is dirty AND not inside a visible modal → warn
                    const inVisibleModal = editor.getContainer()?.closest('.modal.show');
                    if (!inVisibleModal && editor.isDirty()) {
                        return "You have unsaved changes. Are you sure you want to leave?";
                    }
                };

                // When the modal closes, mark editor clean
                const modal = editor.getContainer()?.closest('.modal');
                if (modal) {
                    modal.addEventListener('hidden.bs.modal', () => {
                        editor.undoManager.clear();
                        editor.setDirty(false);
                    });
                }
            });

            var rewordButtonApi;

            editor.ui.registry.addButton('reword', {
                icon: 'ai',
                tooltip: 'Reword Text',
                onAction: function() {
                    var content = editor.getContent();

                    // Disable the Reword button
                    rewordButtonApi.setEnabled(false);

                    // Show the progress indicator
                    editor.setProgressState(true);

                    fetch('ajax.php?ai_reword', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({ text: content }),
                    })
                        .then(response => {
                            if (!response.ok) {
                                throw new Error('Network response was not ok');
                            }
                            return response.json();
                        })
                        .then(data => {
                            editor.setProgressState(false);
                            rewordButtonApi.setEnabled(true);

                            // Leave the user's text alone if the reword failed
                            if (data.error || !data.rewordedText) {
                                editor.notificationManager.open({
                                    text: data.error || 'Could not reword the text.',
                                    type: 'error',
                                    timeout: 8000
                                });
                                return;
                            }

                            editor.undoManager.transact(function() {
                                editor.setContent(data.rewordedText);
                            });

                            editor.notificationManager.open({
                                text: 'Text reworded successfully!',
                                type: 'success',
                                timeout: 3000
                            });
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            editor.setProgressState(false);
                            rewordButtonApi.setEnabled(true);
                            editor.notificationManager.open({
                                text: 'An error occurred while rewording the text.',
                                type: 'error',
                                timeout: 5000
                            });
                        });
                },
                onSetup: function(buttonApi) {
                    rewordButtonApi = buttonApi;
                    return function() {};
                }
            });
        }
    });

    // Initialize TinyMCE AI for Tickets
    tinymce.init({
        ...itflowTinyMceSkin(),
        selector: '.tinymceTicket',
        browser_spellcheck: true,
        contextmenu: false,
        resize: true,
        min_height: 200,
        max_height: 600,
        promotion: false,
        branding: false,
        menubar: false,
        statusbar: false,
        toolbar: [
            { name: 'styles', items: ['styles'] },
            { name: 'formatting', items: ['bold', 'italic', 'forecolor'] },
            { name: 'link', items: ['link'] },
            { name: 'lists', items: ['bullist', 'numlist'] },
            { name: 'indentation', items: ['outdent', 'indent'] },
            { name: 'ai', items: ['reword', 'undo', 'redo'] },
            { name: 'custom', items: ['redactButton'] },
            { name: 'code', items: ['code'] },
        ],
        mobile: {
            menubar: false,
            toolbar: [
                { name: 'styles', items: ['styles'] },
                { name: 'formatting', items: ['bold', 'italic', 'forecolor'] },
                { name: 'link', items: ['link'] },
                { name: 'lists', items: ['bullist', 'numlist'] },
                { name: 'indentation', items: ['outdent', 'indent'] },
                { name: 'ai', items: ['reword', 'undo', 'redo'] },
                { name: 'custom', items: ['redactButton'] },
                { name: 'code', items: ['code'] },
            ],
        },
        convert_urls: false,
        plugins: 'link image lists table code codesample fullscreen autoresize code',
        license_key: 'gpl',
        setup: function(editor) {
            editor.on('init', function() {
                window.onbeforeunload = function() {
                    // If editor is dirty AND not inside a visible modal → warn
                    const inVisibleModal = editor.getContainer()?.closest('.modal.show');
                    if (!inVisibleModal && editor.isDirty()) {
                        return "You have unsaved changes. Are you sure you want to leave?";
                    }
                };

                // When the modal closes, mark editor clean
                const modal = editor.getContainer()?.closest('.modal');
                if (modal) {
                    modal.addEventListener('hidden.bs.modal', () => {
                        editor.undoManager.clear();
                        editor.setDirty(false);
                    });
                }
            });

            var rewordButtonApi;

            editor.ui.registry.addButton('reword', {
                icon: 'ai',
                tooltip: 'Reword Text',
                onAction: function() {
                    var content = editor.getContent();
                    rewordButtonApi.setEnabled(false);
                    editor.setProgressState(true);

                    fetch('ajax.php?ai_reword&use_case=Tickets', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ text: content }),
                    })
                        .then(response => {
                            if (!response.ok) throw new Error('Network response was not ok');
                            return response.json();
                        })
                        .then(data => {
                            editor.setProgressState(false);
                            rewordButtonApi.setEnabled(true);

                            // Leave the user's text alone if the reword failed
                            if (data.error || !data.rewordedText) {
                                editor.notificationManager.open({
                                    text: data.error || 'Could not reword the text.',
                                    type: 'error',
                                    timeout: 8000
                                });
                                return;
                            }

                            editor.undoManager.transact(function() {
                                editor.setContent(data.rewordedText);
                            });
                            editor.notificationManager.open({
                                text: 'Text reworded successfully!',
                                type: 'success',
                                timeout: 3000
                            });
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            editor.setProgressState(false);
                            rewordButtonApi.setEnabled(true);
                            editor.notificationManager.open({
                                text: 'An error occurred while rewording the text.',
                                type: 'error',
                                timeout: 5000
                            });
                        });
                },
                onSetup: function(buttonApi) {
                    rewordButtonApi = buttonApi;
                    return function() {};
                }
            });

            editor.ui.registry.addButton('redactButton', {
                icon: 'permanent-pen',
                tooltip: 'Redact Text',
                onAction: function() {
                    var selectedText = editor.selection.getContent({ format: 'text' });
                    if (selectedText) {
                        var newContent = '<span style="font-weight: bold; color: red;">[REDACTED]</span>';
                        editor.selection.setContent(newContent);
                    } else {
                        alert('Please select a word to redact');
                    }
                }
            });
        }
    });

    // Initialize TinyMCE Redact-only
    tinymce.init({
        ...itflowTinyMceSkin(),
        selector: '.tinymceRedact',
        browser_spellcheck: true,
        contextmenu: false,
        resize: true,
        min_height: 300,
        max_height: 600,
        promotion: false,
        branding: false,
        menubar: false,
        statusbar: false,
        toolbar: 'redactButton',
        mobile: {
            menubar: false,
            plugins: 'autosave lists autolink',
            toolbar: 'redactButton'
        },
        convert_urls: false,
        plugins: 'link image lists table code fullscreen autoresize',
        license_key: 'gpl',
        setup: function(editor) {

            editor.on('init', function() {
                window.onbeforeunload = function() {
                    // If editor is dirty AND not inside a visible modal → warn
                    const inVisibleModal = editor.getContainer()?.closest('.modal.show');
                    if (!inVisibleModal && editor.isDirty()) {
                        return "You have unsaved changes. Are you sure you want to leave?";
                    }
                };

                // When the modal closes, mark editor clean
                const modal = editor.getContainer()?.closest('.modal');
                if (modal) {
                    modal.addEventListener('hidden.bs.modal', () => {
                        editor.undoManager.clear();
                        editor.setDirty(false);
                    });
                }
            });

            editor.on('keydown', function(e) {
                e.preventDefault();
            });

            editor.ui.registry.addButton('redactButton', {
                icon: 'permanent-pen',
                tooltip: 'Redact',
                text: 'REDACT',
                onAction: function() {
                    var selectedText = editor.selection.getContent({ format: 'text' });
                    if (selectedText) {
                        var newContent = '<span style="font-weight: bold; color: red;">[REDACTED]</span>';
                        editor.selection.setContent(newContent);
                    } else {
                        alert('Please select a word to redact');
                    }
                }
            });
        }
    });

    // DateTime
    document.querySelectorAll('.datetimepicker').forEach(function (el) {
        if (el._flatpickr) {
            return;
        }
        flatpickr(el, {
            enableTime: true,
            time_24hr: true,
            dateFormat: 'Y-m-d H:i',
            allowInput: true
        });
    });

    // Data Input Mask
    // IMask replaces jquery.inputmask. Only two aliases were ever used.
    document.querySelectorAll('[data-mask]').forEach(function (el) {
        if (el.dataset.imaskReady) {
            return;
        }
        el.dataset.imaskReady = '1';
        var spec = el.getAttribute('data-inputmask') || '';
        if (spec.indexOf('ip') !== -1) {
            // A pattern mask built from MaskedRange blocks will not advance past the dot
            // until the block reaches its maxLength, so 10.0.0.1 had to be entered as
            // 010.000.000.001. A regex mask has no per-block completeness rule - it tests
            // the whole value on every keystroke, so partial input like "10.0." is valid on
            // its own. Octets are still bounded to 0-255 and leading zeros are still
            // accepted, matching what jquery.inputmask's 'ip' alias allowed.
            //
            // interface_ip / asset_ip are varchar(200) and hold free text as well - 'DHCP'
            // is written there by the checkbox on these same modals. Masking a value like
            // that would strip it to nothing the moment the modal opened, so anything not
            // made of digits and dots is left unmasked instead.
            if (el.value && !/^[\d.]*$/.test(el.value)) {
                return;
            }
            var octet = '(25[0-5]|2[0-4]\\d|[01]?\\d\\d?)';
            var octet4 = octet + '?';
            var octet3 = '(' + octet + '(\\.' + octet4 + ')?)?';
            var octet2 = '(' + octet + '(\\.' + octet3 + ')?)?';
            IMask(el, {
                mask: new RegExp('^(' + octet + '(\\.' + octet2 + ')?)?$')
            });
        } else if (spec.indexOf('mac') !== -1) {
            IMask(el, {
                mask: 'HH:HH:HH:HH:HH:HH',
                definitions: { H: /[0-9a-fA-F]/ },
                prepare: function (s) { return s.toUpperCase(); },
                lazy: true
            });
        }
    });

    // Password reveal. Replaces Show-Hide-Passwords-Bootstrap-4, which has no Bootstrap 5
    // release. The data-toggle="password" contract stays on the INPUT, which is where all
    // 15 call sites write it and where the old plugin expected it - but the plugin built
    // its own toggle button, and the click has to land on the eye SPAN beside the field.
    // Binding it to the input instead meant the eye did nothing at all, and clicking into
    // the field to type your password switched it to type="text" while you typed it.
    itflowStep('password-reveal', function () {
        document.querySelectorAll('input[data-toggle="password"]').forEach(function (input) {
            var group = input.closest('.input-group');
            var icon = group && group.querySelector('.fa-eye, .fa-eye-slash');
            var toggle = icon && icon.closest('span, button, a');

            // includes/modal_footer.php re-executes this file on every ajax modal open, so
            // without a marker anything already on the page collects a second handler and
            // the next click toggles twice, which looks exactly like nothing happening.
            if (!toggle || toggle.dataset.itflowPasswordToggle) {
                return;
            }
            toggle.dataset.itflowPasswordToggle = '1';

            // The eye is a plain <span>, so it needs the role to get a pointer cursor and
            // the tabindex to be reachable at all without a mouse.
            toggle.setAttribute('role', 'button');
            toggle.setAttribute('tabindex', '0');
            toggle.setAttribute('aria-pressed', 'false');
            toggle.setAttribute('aria-label', 'Show password');

            function toggleReveal() {
                var hidden = input.type === 'password';
                input.type = hidden ? 'text' : 'password';
                icon.classList.toggle('fa-eye', !hidden);
                icon.classList.toggle('fa-eye-slash', hidden);
                toggle.setAttribute('aria-pressed', hidden ? 'true' : 'false');
                toggle.setAttribute('aria-label', hidden ? 'Hide password' : 'Show password');
            }

            toggle.addEventListener('click', toggleReveal);
            toggle.addEventListener('keydown', function (e) {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    toggleReveal();
                }
            });
        });
    });

    // Bootstrap 4 needed _enforceFocus patched out so ClipboardJS could reach
    // its textarea inside a modal. Bootstrap 5 registers no jQuery plugin, so
    // the old $.fn.modal line threw and killed everything below it. If copying
    // from inside a modal ever misbehaves, ClipboardJS's `container` option is
    // the lever, not a Bootstrap patch.

    // Clipboard
    itflowStep('clipboard', function () {
        var clipboard = new ClipboardJS('.clipboardjs');

        clipboard.on('success', function(e) {
            flashTooltip(e.trigger, 'Copied!');
        });

        clipboard.on('error', function(e) {
            flashTooltip(e.trigger, 'Failed!');
        });
    });

    // Enable Popovers
    itflowStep('popovers', function () {
        document.querySelectorAll('[data-bs-toggle="popover"]').forEach(function (el) {
            bootstrap.Popover.getOrCreateInstance(el);
        });
    });

    // Data Tables
    itflowStep('datatables', function () {
        new DataTable('.dataTables');
    });

    // Phone inputs
    // js/phone_inputs.js is only loaded where phone fields exist - the agent
    // footer and the portal profile page. Referencing the bare identifier here
    // would throw a ReferenceError anywhere it is absent (setup/index.php loads
    // app.js on its own), and that throw happens while evaluating the argument,
    // before itflowStep's try/catch can contain it - taking every step after
    // this one down with it.
    itflowStep('phone-inputs', function () {
        if (typeof initPhoneInputs === 'function') {
            initPhoneInputs();
        }
    });
}

// modal_footer.php re-loads this file on every ajax modal open, so run now if
// the document is already parsed, otherwise wait for it.
itflowReady(itflowInit);

/*
 * Calendar event modals - the All day switch shows or hides the time row.
 *
 * The form uses four separate fields (date from / date to / time from / time to),
 * so nothing here rewrites a value or changes an input type. An earlier version
 * flipped a single datetime-local input to type="date", which a browser answers by
 * silently discarding a value it now considers invalid - a fragile arrangement that
 * left the fields empty whenever this handler had not run.
 *
 * required tracks visibility: a hidden required field blocks form submission with an
 * unfocusable-element error that the user cannot act on.
 *
 * Delegated on document because the edit event modal is injected by ajax. The
 * namespaced .off() keeps this to a single handler - modal_footer.php re-loads this
 * file on every ajax modal open.
 */
/**
 * Initial focus for modals with no autofocus target.
 *
 * Bootstrap's focus trap sends focus to the FIRST focusable child whenever it
 * lands outside the trap, and in ITFlow's modals that is the header close
 * button - so opening any of the ~230 modals without an autofocus field parked
 * a focus ring on the X. Putting focus on the dialog itself is what Bootstrap
 * intends, keeps the trap and Escape working, and leaves screen readers
 * announcing the dialog rather than "Close".
 *
 * Deliberately does NOT auto-focus the first input: that would pop the
 * keyboard on mobile for every modal, which is a bigger change than the bug.
 */
itflowBindOnce('itflowModalFocus', 'shown.bs.modal', '.modal', function () {
    if (this.querySelector('[autofocus]')) {
        return;
    }
    this.focus();
});

itflowBindOnce('itflowAllDay', 'change', '.event-all-day-toggle', function () {

    const allDay = this.checked;
    const timeFields = document.getElementById(this.id.replace(/_all_day$/, '_time_fields'));

    if (!timeFields) {
        return;
    }

    timeFields.classList.toggle('d-none', allDay);

    timeFields.querySelectorAll('input').forEach(function (field) {
        if (allDay) {
            field.removeAttribute('required');
        } else {
            field.setAttribute('required', 'required');
        }
    });
});

/*
 * Keep the end date at or after the start date, without shortening a longer span
 * the user has already chosen.
 */
itflowBindOnce('itflowEventDate', 'change', '.event-start-date', function () {

    const endField = document.getElementById(this.id.replace(/_start_date$/, '_end_date'));

    if (!endField || !this.value) {
        return;
    }

    if (!endField.value || endField.value < this.value) {
        endField.value = this.value;
    }
});

/*
 * Default the end time to an hour after the start, leaving a longer span alone.
 * A start late in the evening rolls the end onto the following day rather than
 * wrapping round to an end that precedes the start.
 */
itflowBindOnce('itflowEventTime', 'change', '.event-start-time', function () {

    const prefix = this.id.replace(/_start_time$/, '');
    const endField = document.getElementById(prefix + '_end_time');

    if (!endField || !this.value) {
        return;
    }

    if (endField.value && endField.value > this.value) {
        return;
    }

    const parts = this.value.split(':');
    const end = new Date(2000, 0, 1, Number(parts[0]), Number(parts[1]));
    end.setHours(end.getHours() + 1);

    const pad = (n) => String(n).padStart(2, '0');
    endField.value = pad(end.getHours()) + ':' + pad(end.getMinutes());

    // Crossed midnight - carry the end date forward so the event still ends after
    // it starts
    if (end.getDate() !== 1) {
        const startDate = document.getElementById(prefix + '_start_date');
        const endDate = document.getElementById(prefix + '_end_date');

        if (startDate && endDate && startDate.value && endDate.value <= startDate.value) {
            const next = new Date(startDate.value + 'T00:00:00');
            next.setDate(next.getDate() + 1);
            endDate.value = next.getFullYear() + '-' + pad(next.getMonth() + 1) + '-' + pad(next.getDate());
        }
    }
});

/*
 * Bootstrap tooltips are only used for the brief "Copied!" flash on a copy
 * button. Everything else relies on the browser's own title attribute.
 *
 * This used to be `$('button').tooltip({trigger: 'click'})`, which attached a
 * click-toggled tooltip to EVERY button in the app. A click-triggered tooltip
 * is only dismissed by clicking the same button again - clicking anywhere else
 * leaves it on screen - and if the button was then removed from the DOM by a
 * modal closing or an ajax refresh, its tooltip was orphaned and stayed up
 * until the next page load.
 */
function flashTooltip(button, message) {
    const el = button instanceof Element ? button : document.querySelector(button);
    if (!el) {
        return;
    }
    bootstrap.Tooltip.getInstance(el)?.dispose();
    const tip = new bootstrap.Tooltip(el, {
        trigger: 'manual',
        placement: 'bottom',
        title: message
    });
    tip.show();

    setTimeout(function () {
        tip.dispose();
    }, 1000);
}

/*
 * The Tom Select layer (initTomSelect / refreshTomSelect / clearTomSelect /
 * setTomSelectValue) lives in js/tom_select.js, which footer.php loads right
 * after the library so the enhancement happens before the page's heavy libs
 * are parsed. Those functions are global; this file only calls them.
 */

/**
 * Show a Bootstrap toast from JavaScript.
 *
 * The PHP side renders its own toast markup in includes/inc_alert_feedback.php
 * so that user-supplied text never reaches a JS string literal. This helper is
 * for toasts raised by client-side code, where the caller controls the text.
 *
 * Replaces toastr.success() / .error() / .warning() / .info().
 */
function itflowToast(message, type) {
    var styles = {
        success: 'text-bg-success',
        info: 'text-bg-info',
        warning: 'text-bg-warning',
        alert: 'text-bg-warning',
        error: 'text-bg-danger',
        danger: 'text-bg-danger'
    };
    var style = styles[type] || styles.success;
    var darkText = type === 'warning' || type === 'alert' || type === 'info';

    var container = document.querySelector('.toast-container.itflow-toast-js');
    if (!container) {
        container = document.createElement('div');
        container.className = 'toast-container itflow-toast-js position-fixed top-0 end-0 p-3';
        container.style.zIndex = '1090';
        document.body.appendChild(container);
    }

    var toast = document.createElement('div');
    toast.className = 'toast fade align-items-center ' + style + ' border-0';
    toast.setAttribute('role', 'alert');
    toast.setAttribute('aria-live', 'assertive');
    toast.setAttribute('aria-atomic', 'true');

    var flex = document.createElement('div');
    flex.className = 'd-flex';

    var body = document.createElement('div');
    body.className = 'toast-body';
    body.textContent = message;

    var close = document.createElement('button');
    close.type = 'button';
    close.className = 'btn-close ' + (darkText ? '' : 'btn-close-white') + ' me-2 m-auto';
    close.setAttribute('data-bs-dismiss', 'toast');
    close.setAttribute('aria-label', 'Close');

    flex.appendChild(body);
    flex.appendChild(close);
    toast.appendChild(flex);
    container.appendChild(toast);

    toast.addEventListener('hidden.bs.toast', function () {
        toast.remove();
    });
    bootstrap.Toast.getOrCreateInstance(toast, {
        animation: true,
        autohide: true,
        delay: 5000
    }).show();
}

/*
 * Live IP check for the network IP add/edit modals (agent/modals/network/).
 *
 * Calls the same checkIpForNetwork() the POST handler uses, via
 * ajax.php?network_ip_check, so the field reports exactly the verdict the save
 * will reach. Advisory only - the handler re-checks on submit, and this never
 * blocks submission.
 *
 * Lives here rather than in the modal because includes/modal_footer.php
 * re-executes this file on every ajax modal open, so the function is already
 * defined by the time the modal's inline call runs.
 */
function itflowWatchNetworkIp(networkId, ipId) {

    var input = document.getElementById('networkIpAddress');
    var feedback = document.getElementById('networkIpFeedback');

    if (!input || !feedback) {
        return;
    }

    var timer = null;
    var lastChecked = null;

    function render(cls, html) {
        feedback.className = 'small mt-1 ' + cls;
        feedback.innerHTML = html;
    }

    function check() {

        var value = input.value.trim();

        if (value === '') {
            render('', '');
            input.classList.remove('is-valid', 'is-invalid');
            lastChecked = null;
            return;
        }

        if (value === lastChecked) {
            return;
        }

        lastChecked = value;

        itflowGet(
            'ajax.php',
            {network_ip_check: 'true', network_id: networkId, ip_id: ipId, ip: value},
            function (data) {

                var result;

                try {
                    result = JSON.parse(data);
                } catch (e) {
                    return;
                }

                // A slow earlier response must not paint over a newer one
                if (input.value.trim() !== lastChecked) {
                    return;
                }

                render(result.ok ? 'text-success' : 'text-danger', result.message);
                input.classList.toggle('is-valid', result.ok);
                input.classList.toggle('is-invalid', !result.ok);
            }
        );
    }

    input.addEventListener('input', function () {
        clearTimeout(timer);
        timer = setTimeout(check, 350);
    });

    input.addEventListener('blur', check);

}

