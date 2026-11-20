/**
 * Inline notes.
 *
 * Replaces the invoice / quote / recurring-invoice note modals with a textarea
 * that saves on blur, the same way the client overview Quick Notes field works.
 *
 * Markup contract:
 *   <textarea class="itflow-inline-note"
 *             data-endpoint="invoice_set_notes"
 *             data-id-field="invoice_id"
 *             data-id="42"
 *             data-csrf="...">notes</textarea>
 *
 * Losing an explicit Save button means the user needs telling that the save
 * happened, so each field reports its own state next to the label rather than
 * saving silently.
 */
function itflowInlineNotes(textarea) {
    if (!textarea || textarea.dataset.inlineNoteReady) {
        return;
    }
    textarea.dataset.inlineNoteReady = '1';

    // Saving only when the text actually changed avoids a write on every
    // click-through, which would otherwise spam the audit log.
    let lastSaved = textarea.value;

    const status = document.createElement('span');
    status.className = 'itflow-note-status small ms-2 d-print-none';
    const label = document.querySelector('[data-note-status-for="' + textarea.id + '"]');
    (label || textarea.parentNode).appendChild(status);

    function setStatus(text, cls) {
        status.textContent = text;
        status.className = 'itflow-note-status small ms-2 d-print-none ' + cls;
    }

    function save() {
        if (textarea.value === lastSaved) {
            return;
        }
        const payload = {
            csrf_token: textarea.dataset.csrf,
            note: textarea.value
        };
        payload[textarea.dataset.endpoint] = 'TRUE';
        payload[textarea.dataset.idField] = textarea.dataset.id;

        setStatus('Saving...', 'text-muted');

        itflowPost('ajax.php', payload, function () {
            lastSaved = textarea.value;
            setStatus('Saved', 'text-success');
            setTimeout(function () {
                if (status.textContent === 'Saved') {
                    setStatus('', '');
                }
            }, 2000);
        }, function () {
            setStatus('Not saved - check your connection', 'text-danger');
        });
    }

    textarea.addEventListener('blur', save);

    // Ctrl/Cmd+Enter saves without leaving the field
    textarea.addEventListener('keydown', function (e) {
        if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
            e.preventDefault();
            save();
        }
    });

    // Leaving mid-edit saves it on the way out rather than asking first
    window.addEventListener('pagehide', function () {
        if (textarea.value === lastSaved || !navigator.sendBeacon) {
            return;
        }
        const data = new FormData();
        data.append('csrf_token', textarea.dataset.csrf);
        data.append('note', textarea.value);
        data.append(textarea.dataset.endpoint, 'TRUE');
        data.append(textarea.dataset.idField, textarea.dataset.id);
        navigator.sendBeacon('ajax.php', data);
        lastSaved = textarea.value;
    });
}

document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.itflow-inline-note').forEach(itflowInlineNotes);
});
