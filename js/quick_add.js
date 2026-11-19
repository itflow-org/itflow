/*
 * ITFlow - add a missing contact, location or vendor from the window that needs it. A + button
 * ([data-quick-add="contact|location|vendor"]) in a picker's input group asks for the few things
 * it needs, adds it (agent/ajax.php quick_add_record) and picks it - no closing the window, adding
 * it elsewhere and starting again.
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

    const FIELDS = {
        contact: { title: 'New contact', fields: [['name', 'Name', 'text', true], ['email', 'Email', 'email'], ['title', 'Title', 'text']] },
        location: { title: 'New location', fields: [['name', 'Name', 'text', true], ['address', 'Address', 'text'], ['city', 'City', 'text']] },
        vendor: { title: 'New vendor', fields: [['name', 'Name', 'text', true], ['phone', 'Phone', 'tel']] }
    };

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
        const kind = button && FIELDS[button.dataset.quickAdd];
        if (!kind || typeof Swal === 'undefined') {
            return;
        }
        e.preventDefault();
        const select = button.closest('.input-group') && button.closest('.input-group').querySelector('select');
        const client = clientOf(button);
        if (!select) {
            return;
        }
        if (!client) {
            if (typeof itflowToast === 'function') {
                itflowToast('Pick the client first', 'warning');
            }
            return;
        }
        const csrf = button.dataset.csrf;

        // Static markup - nothing typed by anyone goes through this string
        const html = kind.fields.map(function (field) {
            return '<label class="form-label d-block text-start small mb-1 mt-2" for="itflowQuickAdd_' + field[0] + '">' + field[1] + (field[3] ? ' <strong class="text-danger">*</strong>' : '') + '</label>'
                + '<input class="form-control" id="itflowQuickAdd_' + field[0] + '" type="' + field[2] + '" maxlength="200" autocomplete="off">';
        }).join('');

        Swal.fire({
            title: kind.title,
            html: html,
            target: button.closest('.modal') || 'body',
            showCancelButton: true,
            confirmButtonText: 'Add',
            focusConfirm: false,
            returnFocus: false,
            didOpen: function () {
                const first = document.getElementById('itflowQuickAdd_name');
                if (first) {
                    first.focus();
                }
            },
            preConfirm: function () {
                const body = new FormData();
                body.append('quick_add_record', button.dataset.quickAdd);
                body.append('client_id', client);
                body.append('csrf_token', csrf);
                kind.fields.forEach(function (field) {
                    body.append(field[0], document.getElementById('itflowQuickAdd_' + field[0]).value);
                });
                if (!body.get('name').trim()) {
                    Swal.showValidationMessage('It needs a name.');
                    return false;
                }
                return fetch('/agent/ajax.php', { method: 'POST', body: body, credentials: 'same-origin' })
                    .then(function (r) { return r.json(); })
                    .then(function (answer) {
                        if (!answer.ok) {
                            Swal.showValidationMessage(answer.error || "Couldn't add it.");
                            return false;
                        }
                        return answer;
                    })
                    .catch(function () {
                        Swal.showValidationMessage("Couldn't add it.");
                        return false;
                    });
            }
        }).then(function (result) {
            if (!result.isConfirmed || !result.value) {
                return;
            }
            const option = new Option(result.value.label, result.value.id, true, true);
            select.add(option);
            select.value = String(result.value.id);
            select.dispatchEvent(new Event('change', { bubbles: true }));
            if (typeof itflowToast === 'function') {
                itflowToast(result.value.label + ' added', 'success');
            }
        });
    });
})();
