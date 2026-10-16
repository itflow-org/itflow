<?php

/*
 * Invoice an accepted quote. Its one-time lines go on a draft invoice -
 * dated as given, due on the client's terms - and its lines billed each
 * month, quarter or year on a recurring invoice for each: a new one from a
 * start date, or added to one of the client's. The first period can go on
 * the invoice too. The quote is marked invoiced. ?quote_id=
 */

require_once '../../../includes/modal_header.php';

enforceUserPermission('module_sales', 2);

$doc = billingRow('quote', intval($_GET['quote_id'] ?? 0));
if (!$doc) {
    exit(json_encode(['error' => 'That quote no longer exists.']));
}
$client_id = intval($doc['client_id']);
enforceClientAccess();
$doc_number = billingNumber('quote', $doc);
if ($doc['quote_status'] !== 'Accepted') {
    exit(json_encode(['error' => $doc['quote_status'] === 'Invoiced' ? "$doc_number is already invoiced." : "$doc_number has to be accepted before it is invoiced."]));
}

$quote_id = intval($doc['quote_id']);
$currency = (string) $doc['quote_currency_code'];
$money = function ($amount) use ($currency) {
    return escapeHtml(billingMoney($amount, $currency));
};
$groups = quoteGroups($doc);
$recurring = quoteHasRecurring($groups);
$one_time = $groups[''] ?? null;
$terms = intval($doc['client_net_terms']);
$frequencies = recurringInvoiceFrequencies();

// The client's recurring invoices each part could go on
$existing = [];
$sql = mysqli_query($mysqli, "SELECT recurring_invoice_id, recurring_invoice_prefix, recurring_invoice_number, recurring_invoice_scope, recurring_invoice_frequency, recurring_invoice_amount
    FROM recurring_invoices WHERE recurring_invoice_client_id = $client_id AND recurring_invoice_archived_at IS NULL ORDER BY recurring_invoice_number DESC");
while ($row = mysqli_fetch_assoc($sql)) {
    $existing[$row['recurring_invoice_frequency']][intval($row['recurring_invoice_id'])] = $row;
}

ob_start();

?>
<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fas fa-fw fa-file-invoice-dollar me-2"></i><?= $recurring ? 'Start Billing' : 'Make Invoice' ?> <span class="fw-normal text-white-50 ms-1"><?= escapeHtml($doc_number) ?> &middot; <?= escapeHtml($doc['client_name']) ?></span></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" autocomplete="off" id="quoteToInvoice">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="quote_id" value="<?= $quote_id ?>">

    <div class="modal-body">
        <div class="d-flex align-items-center gap-3 mb-3 p-3 rounded bg-body-tertiary">
            <?= billingTile('quote') ?>
            <i class="fas fa-arrow-right text-body-secondary"></i>
            <?php if ($one_time && ($one_time['items'] || !$recurring)) { ?><?= billingTile('invoice') ?><?php } ?>
            <?php if ($recurring) { ?><?= billingTile('recurring') ?><?php } ?>
            <div class="small text-body-secondary">
                <?= escapeHtml($doc_number) ?> comes to <?= escapeHtml(quoteAmountText($groups, $currency)) ?>.
                <?= $recurring ? 'What is billed once goes on a draft invoice, the rest on recurring invoices.' : "A draft invoice with its lines, discount and notes." ?>
                It is marked invoiced.
            </div>
        </div>

        <!-- What is billed once -->
        <div data-quote-invoice <?= $one_time && $one_time['items'] || !$recurring ? '' : 'hidden' ?>>
            <h6 class="fw-semibold mb-2">
                <i class="fas fa-fw fa-file-invoice text-secondary me-1"></i>Invoice
                <span class="fw-normal text-body-secondary small ms-1">
                    <?= $one_time && $one_time['items'] ? count($one_time['items']) . ' one-time line' . (count($one_time['items']) === 1 ? '' : 's') . ' - ' . $money($one_time['total']) : ($recurring ? 'The first period' : '') ?>
                </span>
            </h6>
            <label class="form-label">Invoice date <strong class="text-danger">*</strong></label>
            <div class="input-group">
                <span class="input-group-text"><i class="fa fa-fw fa-calendar"></i></span>
                <input type="date" class="form-control" name="date" max="2999-12-31" value="<?= date('Y-m-d') ?>" required>
            </div>
            <div class="form-text"><?= $terms ? "Due $terms days after it" : 'Due on receipt' ?> - the client's terms.</div>
        </div>

        <?php foreach ($groups as $frequency => $group) {
            if ($frequency === '') {
                continue;
            }
            $period = recurringInvoicePeriodWord($frequency);
            $options = $existing[$frequency] ?? []; ?>
            <!-- Billed each <?= $period ?> -->
            <div class="border-top pt-3 mt-3" data-quote-recurring="<?= $frequency ?>">
                <h6 class="fw-semibold mb-2">
                    <i class="fas fa-fw fa-redo-alt text-secondary me-1"></i>Each <?= $period ?>
                    <span class="fw-normal text-body-secondary small ms-1"><?= count($group['items']) ?> line<?= count($group['items']) === 1 ? '' : 's' ?> - <?= $money($group['total']) ?> a <?= $period ?><?= $group['discount'] != 0 ? ', after the discount' : '' ?></span>
                </h6>
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label">Bill it on</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-redo-alt"></i></span>
                            <select class="form-select" name="recurring[<?= $frequency ?>][to]" data-quote-recurring-to>
                                <option value="0">A new recurring invoice, billed <?= strtolower($frequencies[$frequency]) ?></option>
                                <?php foreach ($options as $recurring_id => $option) { ?>
                                    <option value="<?= $recurring_id ?>">Add to <?= escapeHtml(billingNumber('recurring', $option)) ?><?= trim((string) $option['recurring_invoice_scope']) !== '' ? ' - ' . escapeHtml($option['recurring_invoice_scope']) : '' ?> (<?= $money($option['recurring_invoice_amount']) ?> a <?= $period ?>)</option>
                                <?php } ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-sm-6" data-quote-recurring-new>
                        <label class="form-label">First invoice <strong class="text-danger">*</strong></label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-calendar-day"></i></span>
                            <input type="date" class="form-control" name="recurring[<?= $frequency ?>][start]" max="2999-12-31" value="<?= quoteRecurringStart() ?>" required>
                        </div>
                    </div>
                    <div class="col-sm-6" data-quote-recurring-new>
                        <label class="form-label">Its invoices</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-paper-plane"></i></span>
                            <select class="form-select" name="recurring[<?= $frequency ?>][auto_send]">
                                <option value="1">Emailed as they are made</option>
                                <option value="0">Left as drafts to check</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>
        <?php } ?>

        <?php if ($recurring) { ?>
            <div class="form-check form-switch mt-3 pt-3 border-top">
                <input class="form-check-input" type="checkbox" role="switch" name="first_period" value="1" id="quoteFirstPeriod" data-quote-first>
                <label class="form-check-label" for="quoteFirstPeriod">Bill the first <?= escapeHtml(implode(' and ', array_map('recurringInvoicePeriodWord', array_keys(array_diff_key($groups, ['' => true]))))) ?> on the invoice now</label>
                <div class="form-text">The recurring lines go on the invoice as well. Start the recurring invoice a period later, so the first one isn't billed twice.</div>
            </div>
        <?php } ?>
    </div>
    <div class="modal-footer">
        <button type="submit" name="add_quote_to_invoice" class="btn btn-primary text-bold"><i class="fas fa-check me-2"></i><?= $recurring ? 'Start Billing' : 'Make Invoice' ?></button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fas fa-times me-2"></i>Cancel</button>
    </div>
</form>

<script>
(function () {
    var form = document.getElementById('quoteToInvoice');
    if (!form || form.dataset.wired) {
        return;
    }
    form.dataset.wired = '1';
    var invoice = form.querySelector('[data-quote-invoice]');
    var first = form.querySelector('[data-quote-first]');
    var hasOneTime = <?= $one_time && $one_time['items'] ? 'true' : 'false' ?>;
    var hasRecurring = <?= $recurring ? 'true' : 'false' ?>;
    function show(section, on) {
        section.hidden = !on;
        section.querySelectorAll('input, select').forEach(function (input) {
            input.disabled = !on;
        });
    }
    function update() {
        // The invoice is made for what is billed once, or for the first period
        show(invoice, hasOneTime || !hasRecurring || (first && first.checked));
        form.querySelectorAll('[data-quote-recurring]').forEach(function (part) {
            var to = part.querySelector('[data-quote-recurring-to]');
            part.querySelectorAll('[data-quote-recurring-new]').forEach(function (section) {
                show(section, to.value === '0');
            });
        });
    }
    form.addEventListener('change', update);
    update();
})();
</script>

<?php
require_once '../../../includes/modal_footer.php';
