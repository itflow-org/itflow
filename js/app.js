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

    // Initialize Tom Select (replaces Select2). Every instance is reachable
    // afterwards as element.tomselect, which is how the helpers below reach it.
    itflowStep('tom-select', function () {
        document.querySelectorAll('.select2').forEach(function (el) {
            initTomSelect(el);
        });
    });

    // Initialize TinyMCE
    tinymce.init({
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
            IMask(el, {
                mask: 'a.b.c.d',
                blocks: {
                    a: { mask: IMask.MaskedRange, from: 0, to: 255 },
                    b: { mask: IMask.MaskedRange, from: 0, to: 255 },
                    c: { mask: IMask.MaskedRange, from: 0, to: 255 },
                    d: { mask: IMask.MaskedRange, from: 0, to: 255 }
                },
                lazy: true
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

    // Password reveal. Replaces Show-Hide-Passwords-Bootstrap-4, which has no
    // Bootstrap 5 release. Same data-toggle="password" contract as before.
    document.querySelectorAll('[data-toggle="password"]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var group = btn.closest('.input-group');
            var input = group && group.querySelector('input');
            if (!input) {
                return;
            }
            var hidden = input.type === 'password';
            input.type = hidden ? 'text' : 'password';
            var icon = btn.querySelector('i');
            if (icon) {
                icon.classList.toggle('fa-eye', !hidden);
                icon.classList.toggle('fa-eye-slash', hidden);
            }
            btn.setAttribute('aria-pressed', hidden ? 'true' : 'false');
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
}

// modal_footer.php re-loads this file on every ajax modal open, so run now if
// the document is already parsed, otherwise wait for it.
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', itflowInit);
} else {
    itflowInit();
}

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

/**
 * Tom Select integration.
 *
 * Replaces Select2. Tom Select is vanilla JS and exposes its instance on the
 * element as `el.tomselect`, so nothing here needs jQuery.
 *
 * Select2 concepts and their equivalents, for anyone reading this later:
 *   $(el).select2({tags:true})        -> create: true
 *   $(el).val(null).trigger('change') -> el.tomselect.clear()
 *   $(el).trigger('change.select2')   -> el.tomselect.sync()   (options replaced)
 *   $(el).on('select2:select', fn)    -> el.tomselect.on('change', fn)
 */
function initTomSelect(el, options) {
    if (!el || el.tomselect) {
        return el ? el.tomselect : null;
    }
    var settings = Object.assign({
        create: false,
        allowEmptyOption: true,
        plugins: el.multiple ? ['remove_button'] : [],
        placeholder: el.getAttribute('data-placeholder') || undefined
    }, options || {});
    return new TomSelect(el, settings);
}

/** Re-read the <option> list after it has been replaced server-side. */
function refreshTomSelect(el) {
    if (el && el.tomselect) {
        el.tomselect.sync();
    }
}

/** Clear a selection (single or multiple) without firing a server round-trip. */
function clearTomSelect(el) {
    if (el && el.tomselect) {
        el.tomselect.clear(true);
    }
}

/**
 * Set a select's value from code. A plain el.value = x (or jQuery .val()) does
 * not repaint a Tom Select widget - the underlying <select> changes but the
 * visible control does not. Falls back to a native change event when the
 * element was never enhanced.
 */
function setTomSelectValue(el, value) {
    if (!el) {
        return;
    }
    if (el.tomselect) {
        el.tomselect.setValue(value);
        return;
    }
    el.value = value;
    el.dispatchEvent(new Event('change', { bubbles: true }));
}

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
        container.className = 'toast-container itflow-toast-js position-fixed top-0 start-50 translate-middle-x p-3';
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
