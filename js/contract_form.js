// New Contract, Copy and Edit (agent/modals/contract/contract_form.php).
// Picking a template fills the type, term, renewal, notice, cover and terms
// from it. Picking the client shows only that client's contacts. The end
// date follows the start and the term - a term's length less a day - until
// it is changed by hand, and the line under the term says when notice is
// due. The server checks it all again.
//
// Wrapped in an IIFE because a modal can be opened, closed and opened again
// in one page load, which re-runs this file.

(function () {

    // 'Y-m-d' a term's length after $start, less a day - contractTermEnd() in functions/contract.php
    function termEnd(start, months) {
        const m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(start || '');
        if (!m || !(months > 0)) {
            return '';
        }
        const year = Number(m[1]);
        const month = Number(m[2]) - 1 + Number(months);
        const day = Number(m[3]);
        // The next term starts on the same day of the month - or the last day of a month too short for it
        // (Jan 31 a month on is Feb 28) - and this one ends the day before
        const last = new Date(Date.UTC(year, month + 1, 0)).getUTCDate();
        const end = new Date(Date.UTC(year, month, Math.min(day, last)));
        end.setUTCDate(end.getUTCDate() - 1);
        return end.toISOString().slice(0, 10);
    }

    function niceDate(ymd) {
        const d = new Date(ymd + 'T00:00:00Z');
        return isNaN(d) ? ymd : d.toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric', timeZone: 'UTC' });
    }

    document.querySelectorAll('[data-contract-form]:not([data-wired])').forEach(function (form) {
        form.setAttribute('data-wired', '1');

        const templateData = form.querySelector('[data-contract-templates]');
        const templates = templateData ? JSON.parse(templateData.textContent) : {};
        const template = form.querySelector('[data-contract-template]');
        const client = form.querySelector('[data-contract-client]');
        const contact = form.querySelector('[data-contract-contact]');
        const start = form.querySelector('[data-contract-start]');
        const term = form.querySelector('[data-contract-term]');
        const end = form.querySelector('[data-contract-end]');
        const noticeNote = form.querySelector('[data-contract-notice-note]');
        const field = function (name) {
            return form.querySelector('[data-contract-field="' + name + '"]');
        };

        function drawNotice() {
            const days = Number(field('notice_days').value || 0);
            if (!end.value) {
                noticeNote.textContent = 'No end date - it runs until it is cancelled.';
                return;
            }
            const notice = new Date(end.value + 'T00:00:00Z');
            notice.setUTCDate(notice.getUTCDate() - days);
            const renews = field('auto_renew').checked;
            noticeNote.textContent = (renews ? 'Renews itself after ' : 'Ends on ') + niceDate(end.value)
                + (days ? ' - notice ' + (renews ? 'to stop it ' : '') + 'by ' + niceDate(notice.toISOString().slice(0, 10)) + '.' : '.');
        }

        function followTerm() {
            if (!end.dataset.touched && start.value) {
                end.value = termEnd(start.value, Number(term.value));
            }
            drawNotice();
        }

        // Only the client's own contacts
        function filterContacts() {
            if (!client || !contact) {
                return;
            }
            const id = client.value;
            Array.prototype.forEach.call(contact.options, function (option) {
                if (option.value === '0') {
                    return;
                }
                const mine = option.dataset.client === id;
                option.hidden = !mine;
                if (!mine && option.selected) {
                    contact.value = '0';
                }
            });
        }

        function setValue(name, value) {
            const input = field(name);
            if (!input) {
                return;
            }
            if (input.type === 'checkbox') {
                input.checked = Boolean(Number(value));
            } else if (input.tagName === 'TEXTAREA' && window.tinymce) {
                const editor = tinymce.get().filter(function (ed) { return ed.getElement() === input; })[0];
                if (editor) {
                    editor.setContent(value || '');
                }
                input.value = value || '';
            } else {
                input.value = value === null || value === undefined ? '' : value;
            }
            input.dispatchEvent(new Event('change', { bubbles: true }));
        }

        if (template) {
            template.addEventListener('change', function () {
                const t = templates[template.value];
                if (!t) {
                    return;
                }
                // The template's name only when nothing is written yet
                if (!field('name').value.trim()) {
                    setValue('name', t.name);
                }
                ['type', 'auto_renew', 'notice_days', 'rate_standard', 'rate_after_hours', 'net_terms', 'support_hours', 'details',
                    'sla_low', 'sla_medium', 'sla_high', 'sla_urgent', 'monthly_hours', 'hours_alert'].forEach(function (name) {
                    if (name in t) {
                        setValue(name, t[name]);
                    }
                });
                // A term the list doesn't have is added to it
                if (!Array.prototype.some.call(term.options, function (o) { return Number(o.value) === t.term_months; })) {
                    term.add(new Option(t.term_months + ' months', t.term_months));
                }
                term.value = String(t.term_months);
                followTerm();
            });
        }
        if (client && client.tagName === 'SELECT') {
            client.addEventListener('change', filterContacts);
            // Select2 changes the select without a native event
            if (window.jQuery) {
                window.jQuery(client).on('change', filterContacts);
            }
            filterContacts();
        }
        start.addEventListener('change', followTerm);
        term.addEventListener('change', followTerm);
        end.addEventListener('input', function () {
            end.dataset.touched = '1';
            drawNotice();
        });
        field('notice_days').addEventListener('input', drawNotice);
        field('auto_renew').addEventListener('change', drawNotice);

        drawNotice();
    });
})();
