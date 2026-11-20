/*
 * ITFlow - add a missing contact, location or vendor from the window that needs it. The + in a
 * picker's input group (quickAddButton(), [data-quick-add="contact|location|vendor"]) opens the
 * usual New Contact, New Location or New Vendor window over the form, for the client picked
 * (&inline=1). Its Create saves in the background (inlineSaveAnswer() in the add handlers) and the
 * new one is put in that picker, picked, leaving the form underneath as it was - as the + beside a
 * category picker does (js/category_form.js).
 *
 * The client is the button's data-quick-add-client, or the form's client_id - the picker New Ticket
 * and the asset form change - so it follows the client picked.
 *
 * Loaded on every agent page from includes/footer.php; delegated, so it works in windows opened later.
 */
(function () {
    'use strict';

    if (window.itflowQuickAdd) {
        return;
    }
    window.itflowQuickAdd = true;

    const WINDOWS = {
        contact: '/agent/modals/contact/contact_add.php',
        location: '/agent/modals/location/location_add.php',
        vendor: '/agent/modals/vendor/vendor_add.php'
    };
    const SAVE_BUTTONS = { contact: 'add_contact', location: 'add_location', vendor: 'add_vendor' };

    // The picker the open window was opened from
    let target = null;

    function toast(message, type) {
        if (typeof itflowToast === 'function') {
            itflowToast(message, type);
        }
    }

    function clientOf(button) {
        if (button.dataset.quickAddClient) {
            return button.dataset.quickAddClient;
        }
        const form = button.closest('form');
        const fields = form ? form.querySelectorAll('[name="client_id"]') : [];
        for (let i = 0; i < fields.length; i++) {
            if (!fields[i].disabled && fields[i].value && fields[i].value !== '0') {
                return fields[i].value;
            }
        }
        for (let i = 0; i < fields.length; i++) {
            if (fields[i].value && fields[i].value !== '0') {
                return fields[i].value;
            }
        }
        return '';
    }

    document.addEventListener('click', function (e) {
        const button = e.target.closest('[data-quick-add]');
        const url = button && WINDOWS[button.dataset.quickAdd];
        if (!url) {
            return;
        }
        e.preventDefault();
        const group = button.closest('.input-group');
        const select = group && group.querySelector('select');
        if (!select) {
            return;
        }
        const client = clientOf(button);
        if (!client) {
            toast('Pick the client first', 'warning');
            return;
        }
        target = { select: select, kind: button.dataset.quickAdd };

        // Opened as any window is (js/ajax_modal.js) - over this one
        const opener = document.createElement('a');
        opener.className = 'ajax-modal d-none';
        opener.href = '#';
        opener.dataset.modalUrl = url + '?client_id=' + encodeURIComponent(client) + '&inline=1';
        opener.dataset.modalSize = 'lg';
        document.body.appendChild(opener);
        opener.click();
        opener.remove();
    });

    // On the document, so it runs after the form's own submit handlers (phone numbers are tidied
    // on submit) and sends what they leave
    document.addEventListener('submit', function (e) {
        const form = e.target.closest('form[data-quick-add-inline]');
        if (!form || e.defaultPrevented) {
            return;
        }
        const from = target;
        if (!from || !from.select.isConnected) {
            // Nothing to put it in - a plain post, back to the page
            const flag = form.querySelector('input[name="inline"]');
            if (flag) {
                flag.remove();
            }
            return;
        }
        e.preventDefault();

        const error = form.querySelector('[data-quick-add-error]');
        const save = form.querySelector('button[type="submit"]');
        if (save) {
            save.disabled = true;
        }
        const data = new FormData(form);
        data.append(SAVE_BUTTONS[from.kind], '1');

        fetch(form.action, { method: 'POST', body: data, credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
            .then(function (res) {
                if (!res.ok) {
                    throw new Error('HTTP ' + res.status);
                }
                return res.json();
            })
            .then(function (result) {
                if (!result.ok) {
                    throw new Error(result.error || "It wasn't saved");
                }
                target = null;
                const picker = from.select;
                let option = Array.prototype.find.call(picker.options, function (o) {
                    return o.value === String(result.value);
                });
                if (!option) {
                    option = new Option(result.label, result.value);
                    picker.add(option);
                }
                if (!picker.multiple) {
                    Array.prototype.forEach.call(picker.options, function (o) {
                        o.selected = false;
                    });
                }
                option.selected = true;
                picker.dispatchEvent(new Event('input', { bubbles: true }));
                picker.dispatchEvent(new Event('change', { bubbles: true }));
                const modal = form.closest('.modal');
                if (modal && window.bootstrap) {
                    bootstrap.Modal.getOrCreateInstance(modal).hide();
                }
                toast(result.message, result.warning ? 'warning' : 'success');
            })
            .catch(function (err) {
                if (save) {
                    save.disabled = false;
                }
                if (error) {
                    // An answer that isn't JSON, or a failed request: the save page answered with something else
                    error.textContent = err instanceof SyntaxError || err.message.indexOf('HTTP ') === 0
                        ? "It couldn't be saved - reload the page and try again."
                        : err.message;
                    error.hidden = false;
                    error.scrollIntoView({ block: 'nearest' });
                }
            });
    });
})();
