<?php

/*
 * Invoice a ticket - its time as a line on a new draft invoice or one of the
 * client's drafts. The time logged on it (by agent), the hours that makes
 * (rounded the way Admin > Settings > Invoice says) and the client's rate
 * are filled in, ready to change. A ticket still open can be billed from
 * here too - time logged after that isn't billed. Write it (with AI, for
 * the Billing use case) says what was done in the description, from the
 * replies and notes, for the client. ?ticket_id=
 */

require_once '../../../includes/modal_header.php';

enforceUserPermission('module_sales', 2);

$ticket = ticketForModal($_GET['ticket_id'] ?? 0, 2);
$ticket_id = intval($ticket['ticket_id']);
$reference = ticketReference($ticket);
if (!$client_id) {
    exit(json_encode(['error' => 'Move the ticket to a client before invoicing it.']));
}
if (intval($ticket['ticket_invoice_id'])) {
    $on = billingRow('invoice', intval($ticket['ticket_invoice_id']));
    exit(json_encode(['error' => "$reference is already billed" . ($on ? ' on ' . billingNumber('invoice', $on) : '') . '.']));
}
if (!intval($ticket['ticket_billable'])) {
    exit(json_encode(['error' => "$reference isn't billable - mark it billable first."]));
}

$unbilled = billingUnbilledTickets($client_id, [$ticket_id], true);
$row = $unbilled[$ticket_id] ?? null;
// All covered by a contract's prepaid hours - there is nothing to bill unless it is put back
$prepaid_uses = !$row ? contractHoursTicketUses($ticket_id) : [];
if ($prepaid_uses) {
    exit(json_encode(['error' => "$reference is covered by prepaid hours on " . $prepaid_uses[0]['contract_name'] . " - put it back on the contract to bill it instead."]));
}
if (!$row) {
    exit(json_encode(['error' => "$reference can't be billed - it is archived."]));
}
$rules = billingTimeRules();
$open = !in_array(intval($row['ticket_status']), [TICKET_STATUS_RESOLVED, TICKET_STATUS_CLOSED], true);
$currency = (string) $session_company_currency;

// The time on it, by who logged it
$agents = [];
$sql = mysqli_query($mysqli, "SELECT user_name, COUNT(*) AS entries, SUM(TIME_TO_SEC(ticket_reply_time_worked)) AS seconds FROM ticket_replies
    LEFT JOIN users ON user_id = ticket_reply_by
    WHERE ticket_reply_ticket_id = $ticket_id AND ticket_reply_archived_at IS NULL AND ticket_reply_time_worked > '00:00:00'
    GROUP BY ticket_reply_by ORDER BY seconds DESC");
while ($agent = mysqli_fetch_assoc($sql)) {
    $agents[] = $agent;
}

$drafts = [];
$sql = mysqli_query($mysqli, "SELECT invoice_id, invoice_prefix, invoice_number, invoice_scope, invoice_amount FROM invoices
    WHERE invoice_client_id = $client_id AND invoice_status = 'Draft' AND invoice_archived_at IS NULL ORDER BY invoice_date DESC, invoice_id DESC");
while ($draft = mysqli_fetch_assoc($sql)) {
    $drafts[intval($draft['invoice_id'])] = $draft;
}
$taxes = billingTaxes();
$ai_describe = aiReady('Billing');
$scopes = ["Ticket $reference - " . $row['ticket_subject'], 'Support - ' . date('F Y')];

ob_start();

?>
<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fas fa-fw fa-file-invoice-dollar me-2"></i>Invoice <?= escapeHtml($reference) ?></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" autocomplete="off" id="ticketInvoiceForm">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="ticket_id" value="<?= $ticket_id ?>">

    <div class="modal-body">
        <?php if ($open) { ?>
            <div class="alert alert-warning small py-2">
                <i class="fas fa-exclamation-triangle me-1"></i><?= escapeHtml($reference) ?> is still open. It is billed once - time logged on it after this isn't billed.
            </div>
        <?php } ?>
        <?php if (intval($ticket['ticket_quote_id'] ?? 0)) { ?>
            <div class="alert alert-info small py-2">
                <i class="fas fa-comment-dollar me-1"></i>It has a quote - <a href="quote.php?quote_id=<?= intval($ticket['ticket_quote_id']) ?>">check it</a> before billing the time as well.
            </div>
        <?php } ?>

        <!-- The time on it -->
        <div class="d-flex flex-wrap align-items-center gap-3 p-3 mb-3 rounded bg-body-tertiary">
            <div class="me-auto">
                <div class="small text-body-secondary">Time logged</div>
                <div class="fs-5 fw-semibold"><?= $row['seconds'] ? billingDuration($row['seconds']) : 'None' ?></div>
            </div>
            <div class="small text-body-secondary flex-grow-1">
                <?php if ($agents) {
                    foreach ($agents as $agent) { ?>
                        <div><?= escapeHtml($agent['user_name'] ?: 'Someone removed') ?> - <?= billingDuration($agent['seconds']) ?> in <?= intval($agent['entries']) ?> <?= intval($agent['entries']) === 1 ? 'reply' : 'replies' ?></div>
                    <?php }
                } else { ?>
                    <div class="text-warning-emphasis">No time logged on it - type the hours to bill.</div>
                <?php } ?>
            </div>
            <div class="text-end">
                <div class="small text-body-secondary">To bill</div>
                <div class="fs-5 fw-semibold"><?= billingQuantity($row['hours']) ?> h</div>
                <?php if ($row['prepaid'] > 0) { ?>
                    <div class="small text-body-secondary" title="Prepaid hours on a contract covered part of it">less <?= escapeHtml(contractHoursText($row['prepaid'])) ?> prepaid</div>
                <?php } ?>
            </div>
        </div>

        <div class="row g-3">
            <div class="col-12">
                <label class="form-label">Invoice</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-file-invoice"></i></span>
                    <select class="form-select" name="invoice_id" data-ticket-invoice-target>
                        <option value="0">A new draft invoice</option>
                        <?php foreach ($drafts as $draft_id => $draft) { ?>
                            <option value="<?= $draft_id ?>">Add to <?= escapeHtml(billingNumber('invoice', $draft)) ?><?= trim((string) $draft['invoice_scope']) !== '' ? ' - ' . escapeHtml($draft['invoice_scope']) : '' ?> (<?= escapeHtml(billingMoney($draft['invoice_amount'], $currency)) ?>)</option>
                        <?php } ?>
                    </select>
                </div>
            </div>
            <div class="col-sm-6" data-ticket-invoice-new>
                <label class="form-label">Invoice date <strong class="text-danger">*</strong></label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-calendar"></i></span>
                    <input type="date" class="form-control" name="date" max="2999-12-31" value="<?= date('Y-m-d') ?>" required>
                </div>
            </div>
            <div class="col-sm-6" data-ticket-invoice-new>
                <label class="form-label">Category <strong class="text-danger">*</strong></label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-tag"></i></span>
                    <select class="form-select" name="category" required>
                        <option value="">- Category -</option>
                        <?= categoryOptionsHtml('Income') ?>
                    </select>
                    <?= categoryAddButtonHtml('Income', 'btn btn-secondary') ?>
                </div>
            </div>
            <div class="col-12" data-ticket-invoice-new>
                <label class="form-label">Scope</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-comment"></i></span>
                    <input type="text" class="form-control" name="scope" maxlength="255" value="<?= escapeHtml($scopes[0]) ?>" list="ticketInvoiceScopes">
                    <datalist id="ticketInvoiceScopes">
                        <?php foreach ($scopes as $scope) { ?>
                            <option value="<?= escapeHtml($scope) ?>"></option>
                        <?php } ?>
                    </datalist>
                </div>
            </div>
            <div class="col-12">
                <label class="form-label">Line <strong class="text-danger">*</strong></label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-cube"></i></span>
                    <input type="text" class="form-control" name="item_name" maxlength="200" value="<?= escapeHtml($rules['item_name']) ?>" required>
                </div>
            </div>
            <div class="col-12">
                <label class="form-label">Description</label>
                <div class="input-group">
                    <span class="input-group-text align-items-start pt-2"><i class="fa fa-fw fa-align-left"></i></span>
                    <textarea class="form-control" name="item_description" rows="<?= $ai_describe ? 3 : 2 ?>" data-ticket-invoice-description><?= escapeHtml(billingTimeDescription($row)) ?></textarea>
                    <?php if ($ai_describe) { ?>
                        <button type="button" class="btn btn-outline-secondary" data-ticket-invoice-ai title="Say what was done, from the replies and notes - for the client"><i class="fas fa-fw fa-magic"></i><span class="d-none d-sm-inline ms-1">Write it</span></button>
                    <?php } ?>
                </div>
                <?php if ($ai_describe) { ?>
                    <div class="form-text" data-ticket-invoice-ai-said>Write it - AI says what was done, from the replies and notes, for the client to read.</div>
                <?php } ?>
            </div>
            <div class="col-sm-4">
                <label class="form-label">Hours <strong class="text-danger">*</strong></label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-clock"></i></span>
                    <input type="text" class="form-control text-end" name="qty" inputmode="decimal" value="<?= billingQuantity($row['hours']) ?>" required data-ticket-invoice-hours>
                </div>
                <div class="form-text">Rounded <?= escapeHtml(billingTimeRulesText($rules)) ?>.</div>
            </div>
            <div class="col-sm-4">
                <label class="form-label">Rate <strong class="text-danger">*</strong></label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-dollar-sign"></i></span>
                    <input type="text" class="form-control text-end" name="price" inputmode="decimal" value="<?= number_format($row['rate'], 2, '.', '') ?>" required data-ticket-invoice-rate>
                </div>
                <div class="form-text"><?= $row['rate'] > 0 ? ($row['default_rate'] ? 'The default rate - the client has none.' : "The client's hourly rate.") : 'No rate is set for the client, or as a default.' ?></div>
            </div>
            <div class="col-sm-4">
                <label class="form-label">Tax</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-percent"></i></span>
                    <select class="form-select" name="tax_id" data-ticket-invoice-tax>
                        <?= taxOptionsHtml($taxes, taxDefaultFor($client_id, 'time')) ?>
                    </select>
                </div>
            </div>
        </div>
        <div class="d-flex justify-content-between align-items-center mt-3 pt-3 border-top">
            <span class="text-body-secondary">The line comes to</span>
            <span class="fs-5 fw-semibold itflow-money" data-ticket-invoice-total></span>
        </div>
    </div>
    <div class="modal-footer">
        <button type="submit" name="add_invoice_from_ticket" class="btn btn-primary text-bold"><i class="fas fa-check me-2"></i><span data-ticket-invoice-label>Make Invoice</span></button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fas fa-times me-2"></i>Cancel</button>
    </div>
</form>

<script>
(function () {
    var form = document.getElementById('ticketInvoiceForm');
    if (!form || form.dataset.wired) {
        return;
    }
    form.dataset.wired = '1';
    var currency = <?= json_encode($currency) ?>;
    var target = form.querySelector('[data-ticket-invoice-target]');
    var hours = form.querySelector('[data-ticket-invoice-hours]');
    var rate = form.querySelector('[data-ticket-invoice-rate]');
    var tax = form.querySelector('[data-ticket-invoice-tax]');
    var total = form.querySelector('[data-ticket-invoice-total]');
    var label = form.querySelector('[data-ticket-invoice-label]');
    function number(value) {
        var text = String(value || '').replace(/[\s,$]/g, '');
        return text === '' ? NaN : Number(text);
    }
    function update() {
        var making = target.value === '0';
        form.querySelectorAll('[data-ticket-invoice-new]').forEach(function (section) {
            section.hidden = !making;
            section.querySelectorAll('input, select').forEach(function (input) {
                input.disabled = !making;
            });
        });
        label.textContent = making ? 'Make Invoice' : 'Add to Invoice';
        var h = number(hours.value);
        var r = number(rate.value);
        if (isNaN(h) || isNaN(r)) {
            total.textContent = 'Not a number';
            return;
        }
        var option = tax.options[tax.selectedIndex];
        var amount = Math.round(h * r * 100) / 100;
        amount += Math.round(amount * Number(option ? option.getAttribute('data-percent') || 0 : 0)) / 100;
        try {
            total.textContent = new Intl.NumberFormat(undefined, { style: 'currency', currency: currency || 'USD' }).format(amount);
        } catch (e) {
            total.textContent = amount.toFixed(2);
        }
    }
    form.addEventListener('input', update);
    form.addEventListener('change', update);
    update();

    // Write it - the work done, from the replies and notes, in place of the description
    var ai = form.querySelector('[data-ticket-invoice-ai]');
    if (ai) {
        var description = form.querySelector('[data-ticket-invoice-description]');
        var said = form.querySelector('[data-ticket-invoice-ai-said]');
        var before = null;
        ai.addEventListener('click', function () {
            ai.disabled = true;
            said.className = 'form-text';
            said.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>AI is reading the ticket...';
            fetch('/agent/ajax.php?ai_ticket_invoice_description', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                credentials: 'same-origin',
                body: new URLSearchParams({ ticket_id: form.querySelector('[name="ticket_id"]').value }).toString()
            })
                .then(function (response) {
                    return response.json().catch(function () {
                        throw new Error('');
                    });
                })
                .then(function (answer) {
                    if (answer.error || !answer.text) {
                        throw new Error(answer.error || '');
                    }
                    if (before === null) {
                        before = description.value;
                    }
                    description.value = answer.text;
                    description.rows = Math.min(8, answer.text.split('\n').length + 1);
                    said.textContent = 'Written by AI from the replies and notes - check it before billing. ';
                    var undo = document.createElement('a');
                    undo.href = '#';
                    undo.textContent = 'Put back what was there';
                    undo.setAttribute('data-ticket-invoice-ai-undo', '');
                    undo.addEventListener('click', function (e) {
                        e.preventDefault();
                        description.value = before;
                        description.rows = 3;
                        before = null;
                        said.textContent = 'Put back.';
                    });
                    said.appendChild(undo);
                })
                .catch(function (error) {
                    said.className = 'form-text text-danger-emphasis';
                    said.textContent = error && error.message ? error.message : 'Could not write it - try again.';
                })
                .then(function () {
                    ai.disabled = false;
                });
        });
    }
})();
</script>

<?php
require_once '../../../includes/modal_footer.php';
