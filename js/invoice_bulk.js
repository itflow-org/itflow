/*
 * ITFlow - the invoices list's Mark Sent, Send by Email, Mark Paid, Cancel and
 * Delete (functions/invoice_actions.php). The ticked invoices are handed to the
 * server first and kept there under a short key, so the window's address and
 * its form carry the key, not hundreds of ids - then the window opens.
 */
document.addEventListener('click', function (event) {
    var item = event.target.closest('[data-invoice-bulk]');
    if (!item) {
        return;
    }
    event.preventDefault();
    var ids = Array.from(document.querySelectorAll('input.bulk-select[name="invoice_ids[]"]:checked')).map(function (box) {
        return box.value;
    });
    if (!ids.length) {
        return;
    }
    var menu = item.closest('[data-csrf]');
    var body = new FormData();
    body.append('csrf_token', menu ? menu.getAttribute('data-csrf') : '');
    body.append('invoice_bulk_set', ids.join(','));
    fetch('ajax.php', { method: 'POST', body: body, credentials: 'same-origin' })
        .then(function (response) { return response.json(); })
        .then(function (json) {
            if (!json.set) {
                throw new Error(json.error || 'No set');
            }
            var open = document.createElement('a');
            open.href = '#';
            open.className = 'ajax-modal d-none';
            open.setAttribute('data-modal-url', 'modals/invoice/invoice_bulk.php?action=' + encodeURIComponent(item.getAttribute('data-invoice-bulk')) + '&set=' + json.set);
            document.body.appendChild(open);
            open.click();
            open.remove();
        })
        .catch(function () {
            if (typeof itflowDialog !== 'undefined') {
                itflowDialog({ icon: 'error', title: 'That didn\'t work', text: 'The invoices ticked could not be handed over - reload the page and try again.', confirmButtonText: 'OK', showCancelButton: false });
            }
        });
});
