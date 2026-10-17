<?php

/*
 * Add Tickets to an invoice - the client's billable tickets that are
 * resolved or closed and not on an invoice yet. Each comes with the hours
 * its time bills (rounded the way Admin > Settings > Invoice says), ready to
 * change, and goes on as a line at the rate - or, with Bill their time off,
 * goes on without a line, for work already on the invoice. ?invoice_id=
 */

require_once '../../../includes/modal_header.php';

enforceUserPermission('module_sales', 2);

$invoice_id = intval($_GET['invoice_id'] ?? 0);
$invoice = billingRow('invoice', $invoice_id);
if (!$invoice) {
    exit(json_encode(['error' => 'That invoice no longer exists.']));
}
$client_id = intval($invoice['client_id']);
enforceClientAccess();
if (!billingCanEditItems('invoice', $invoice)) {
    exit(json_encode(['error' => billingNumber('invoice', $invoice) . " can't take more lines - it is paid, cancelled or non-billable."]));
}

$tickets = billingUnbilledTickets($client_id);
$rules = billingTimeRules();
$first = $tickets ? reset($tickets) : null;
$rate = $first ? $first['rate'] : billingTimeRate(getFieldById('clients', $client_id, 'client_rate'));
$currency = (string) $invoice['invoice_currency_code'];
$taxes = billingTaxes();

ob_start();

?>
<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fas fa-fw fa-life-ring me-2"></i>Add Tickets <span class="fw-normal text-white-50 ms-1"><?= escapeHtml(billingNumber('invoice', $invoice)) ?></span></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" autocomplete="off" id="invoiceAddTickets">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="invoice_id" value="<?= $invoice_id ?>">

    <div class="modal-body p-0">
        <?php if ($tickets) { ?>
            <div class="px-3 py-2 small text-body-secondary border-bottom">
                <?= escapeHtml($invoice['client_name']) ?>'s billable tickets, resolved or closed, that aren't on an invoice. The hours are the time logged, rounded <?= escapeHtml(billingTimeRulesText($rules)) ?> - change them before adding.
            </div>
            <div class="list-group list-group-flush" style="max-height: 20rem; overflow-y: auto;">
                <?php foreach ($tickets as $ticket_id => $ticket) { ?>
                    <div class="list-group-item d-flex align-items-center gap-3">
                        <input class="form-check-input flex-shrink-0 mt-0" type="checkbox" name="ticket_ids[]" value="<?= $ticket_id ?>" id="addTicket<?= $ticket_id ?>" data-add-ticket>
                        <label class="flex-grow-1 min-w-0 mb-0" for="addTicket<?= $ticket_id ?>">
                            <span class="d-block text-truncate"><span class="font-monospace small text-body-secondary me-2"><?= escapeHtml($ticket['ticket_prefix'] . $ticket['ticket_number']) ?></span><?= escapeHtml($ticket['ticket_subject']) ?></span>
                            <span class="small text-body-secondary">
                                <i class="far fa-clock me-1"></i><?= $ticket['seconds'] ? billingDuration($ticket['seconds']) . ' logged' : '<span class="text-warning-emphasis">No time logged</span>' ?>
                                &middot; done <?= escapeHtml(date('Y-m-d', strtotime((string) ($ticket['ticket_resolved_at'] ?: $ticket['ticket_closed_at'])))) ?>
                            </span>
                        </label>
                        <div class="input-group input-group-sm flex-shrink-0" style="width: 6.5rem;" data-add-ticket-hours>
                            <input type="text" class="form-control text-end" name="hours[<?= $ticket_id ?>]" value="<?= billingQuantity($ticket['hours']) ?>" inputmode="decimal" aria-label="Hours to bill">
                            <span class="input-group-text">h</span>
                        </div>
                    </div>
                <?php } ?>
            </div>
            <div class="p-3 border-top">
                <div class="form-check form-switch mb-3">
                    <input class="form-check-input" type="checkbox" role="switch" name="bill_time" value="1" id="addTicketsBill" checked data-add-ticket-bill>
                    <label class="form-check-label" for="addTicketsBill">Bill their time - add lines at the hourly rate</label>
                </div>
                <div class="row g-3" data-add-ticket-lines>
                    <div class="col-12">
                        <label class="form-label">Line</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-cube"></i></span>
                            <input type="text" class="form-control" name="item_name" maxlength="200" value="<?= escapeHtml($rules['item_name']) ?>" required>
                        </div>
                    </div>
                    <div class="col-sm-4">
                        <label class="form-label">Rate <strong class="text-danger">*</strong></label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-dollar-sign"></i></span>
                            <input type="text" class="form-control text-end" name="price" inputmode="decimal" value="<?= number_format($rate, 2, '.', '') ?>" required data-add-ticket-rate>
                        </div>
                    </div>
                    <div class="col-sm-4">
                        <label class="form-label">Tax</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-percent"></i></span>
                            <select class="form-select" name="tax_id" data-add-ticket-tax>
                                <?= taxOptionsHtml($taxes, taxDefaultFor($client_id, 'time')) ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-sm-4">
                        <label class="form-label">Lines</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-list"></i></span>
                            <select class="form-select" name="lines">
                                <option value="each">One each</option>
                                <option value="one">One for all</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="form-text" data-add-ticket-nolines hidden>The tickets go on the invoice and stop being unbilled - nothing is added to its lines. For work already billed on it by hand.</div>
            </div>
        <?php } else { ?>
            <div class="itflow-asset-empty">
                <i class="fas fa-life-ring fa-2x mb-2 opacity-50"></i>
                <div>No unbilled tickets - <?= escapeHtml($invoice['client_name']) ?>'s billable tickets are all on an invoice, or still open.</div>
            </div>
        <?php } ?>
    </div>
    <div class="modal-footer">
        <?php if ($tickets) { ?>
            <span class="me-auto small text-body-secondary" data-add-ticket-summary></span>
            <button type="submit" name="add_ticket_to_invoice" class="btn btn-primary text-bold" disabled data-add-ticket-submit><i class="fas fa-check me-2"></i>Add</button>
        <?php } ?>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fas fa-times me-2"></i><?= $tickets ? 'Cancel' : 'Close' ?></button>
    </div>
</form>

<script>
(function () {
    var form = document.getElementById('invoiceAddTickets');
    if (!form || form.dataset.wired) {
        return;
    }
    form.dataset.wired = '1';
    var currency = <?= json_encode($currency) ?>;
    var bill = form.querySelector('[data-add-ticket-bill]');
    var lines = form.querySelector('[data-add-ticket-lines]');
    var noLines = form.querySelector('[data-add-ticket-nolines]');
    var rate = form.querySelector('[data-add-ticket-rate]');
    var tax = form.querySelector('[data-add-ticket-tax]');
    var summary = form.querySelector('[data-add-ticket-summary]');
    var submit = form.querySelector('[data-add-ticket-submit]');
    if (!bill) {
        return;
    }
    function number(value) {
        var n = Number(String(value || '').replace(/[\s,$]/g, ''));
        return isNaN(n) ? 0 : n;
    }
    function money(amount) {
        try {
            return new Intl.NumberFormat(undefined, { style: 'currency', currency: currency || 'USD' }).format(amount);
        } catch (e) {
            return amount.toFixed(2);
        }
    }
    function update() {
        var count = 0;
        var hours = 0;
        form.querySelectorAll('[data-add-ticket]').forEach(function (box) {
            var row = box.closest('.list-group-item');
            var input = row.querySelector('[data-add-ticket-hours] input');
            input.disabled = !bill.checked;
            if (box.checked) {
                count++;
                hours += number(input.value);
            }
        });
        lines.hidden = !bill.checked;
        lines.querySelectorAll('input, select').forEach(function (input) {
            input.disabled = !bill.checked;
        });
        noLines.hidden = bill.checked;
        var option = tax.options[tax.selectedIndex];
        var amount = Math.round(hours * number(rate.value) * 100) / 100;
        amount += Math.round(amount * Number(option ? option.getAttribute('data-percent') || 0 : 0)) / 100;
        hours = Math.round(hours * 100) / 100;
        summary.textContent = count ? count + ' ticket' + (count === 1 ? '' : 's') + (bill.checked ? ' · ' + hours + ' h · ' + money(amount) : '') : 'Tick the tickets to add';
        submit.disabled = count === 0;
    }
    form.addEventListener('change', update);
    form.addEventListener('input', update);
    update();
})();
</script>

<?php
require_once '../../../includes/modal_footer.php';
