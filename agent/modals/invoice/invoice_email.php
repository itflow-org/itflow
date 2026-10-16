<?php

/*
 * Send an invoice - or, for one still owed, a payment reminder - to the
 * client's contacts picked, with a note and the PDF. ?invoice_id= and
 * ?kind=reminder to open on the reminder. Posts email_invoice.
 */

require_once '../../../includes/modal_header.php';

enforceUserPermission('module_sales', 2);

$invoice_id = intval($_GET['invoice_id']);

$sql = mysqli_query($mysqli, "SELECT invoice_client_id, invoice_number, invoice_prefix, invoice_status
    FROM invoices WHERE invoice_id = $invoice_id LIMIT 1");
$row = mysqli_fetch_assoc($sql);
if (!$row) {
    exit(json_encode(['error' => 'That invoice no longer exists.']));
}
$invoice_prefix = escapeHtml($row['invoice_prefix']);
$invoice_number = intval($row['invoice_number']);
$invoice_status = escapeHtml($row['invoice_status']);
$client_id = intval($row['invoice_client_id']);

enforceClientAccess();

$doc = billingRow('invoice', $invoice_id);

// Billing-facing contacts only - see documentContactFilterSql(). Everyone
// shown is also checked by default, which is the behaviour the old one-click
// Send Email link had baked in; the checkboxes are here to drop one, not to
// go hunting for somebody.
$sql_contacts = mysqli_query(
    $mysqli,
    "SELECT contact_billing, contact_email, contact_id, contact_photo, contact_name, contact_primary, contact_title
    FROM contacts
    WHERE contact_client_id = $client_id
    AND contact_archived_at IS NULL
    AND contact_email IS NOT NULL
    AND contact_email != ''
    " . documentContactFilterSql('invoice') . "
    ORDER BY contact_primary DESC, contact_billing DESC, contact_name ASC"
);

$contact_count = mysqli_num_rows($sql_contacts);

// Which boxes open ticked. Read from the same helper Quick Send uses, so the
// modal and the one-click path can never disagree about who "the defaults" are.
$default_contact_ids = [];
$sql_defaults = mysqli_query(
    $mysqli,
    "SELECT contact_id FROM contacts
    WHERE contact_client_id = $client_id
    AND contact_archived_at IS NULL
    AND contact_email IS NOT NULL
    AND contact_email != ''
    " . documentDefaultContactFilterSql('invoice')
);
while ($row = mysqli_fetch_assoc($sql_defaults)) {
    $default_contact_ids[] = intval($row['contact_id']);
}

ob_start();

?>

<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fas fa-fw fa-paper-plane me-2"></i>Send Invoice <span class="fw-normal text-white-50 ms-1"><?= escapeHtml(billingNumber('invoice', $doc)) ?> &middot; <?= escapeHtml($doc['client_name']) ?></span></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="invoice_id" value="<?= $invoice_id ?>">

    <div class="modal-body">

        <?php if ($contact_count == 0) { ?>

            <p class="text-body-secondary mb-0">
                This client has no primary or billing contact with an email address, so there is
                nobody to send an invoice to. Flag a contact as billing, or use Mark Sent to record
                that the invoice went out some other way.
            </p>

        <?php } else { ?>

            <!-- What goes out -->
            <?php $doc_state = invoiceState($doc, invoicePaid($invoice_id));
            $can_remind = $doc_state['balance'] > 0 && in_array($doc['invoice_status'], invoiceOpenStatuses(), true);
            $kind = $can_remind && ($_GET['kind'] ?? '') === 'reminder' ? 'reminder' : 'invoice';
            $reminder_when = $can_remind ? billingReminderWhen(-$doc_state['days']) : ''; ?>
            <?php if ($can_remind) { ?>
                <div class="btn-group w-100 itflow-product-type mb-3" role="group" aria-label="What to send">
                    <input type="radio" class="btn-check" name="kind" value="invoice" id="invoiceEmailKindInvoice<?= $invoice_id ?>" autocomplete="off" <?= $kind === 'invoice' ? 'checked' : '' ?>>
                    <label class="btn btn-outline-secondary text-start d-flex align-items-center gap-2" for="invoiceEmailKindInvoice<?= $invoice_id ?>">
                        <i class="fas fa-fw fa-file-invoice-dollar fa-lg"></i>
                        <span><span class="fw-semibold d-block">The invoice</span><span class="small opacity-75 d-none d-sm-block">Here is your invoice</span></span>
                    </label>
                    <input type="radio" class="btn-check" name="kind" value="reminder" id="invoiceEmailKindReminder<?= $invoice_id ?>" autocomplete="off" <?= $kind === 'reminder' ? 'checked' : '' ?>>
                    <label class="btn btn-outline-secondary text-start d-flex align-items-center gap-2" for="invoiceEmailKindReminder<?= $invoice_id ?>">
                        <i class="fas fa-fw fa-bell fa-lg"></i>
                        <span><span class="fw-semibold d-block">A reminder</span><span class="small opacity-75 d-none d-sm-block">It is <?= escapeHtml($reminder_when) ?></span></span>
                    </label>
                </div>
            <?php } ?>
            <div class="d-flex align-items-center gap-3 p-3 mb-3 rounded bg-body-tertiary">
                <?= billingTile('invoice') ?>
                <div class="flex-grow-1 min-w-0">
                    <div class="fw-semibold"><?= escapeHtml(billingNumber('invoice', $doc)) ?><?= trim((string) $doc['invoice_scope']) !== '' ? ' - ' . escapeHtml($doc['invoice_scope']) : '' ?></div>
                    <div class="small text-body-secondary">An email with a link to view<?= billingOnlinePayments() && !in_array($doc['invoice_status'], ['Paid', 'Cancelled', 'Non-Billable'], true) ? ', pay' : '' ?> and download it as a PDF.</div>
                </div>
                <div class="text-end">
                    <div class="fw-semibold itflow-money"><?= escapeHtml(billingMoney($doc_state['balance'] > 0 ? $doc_state['balance'] : $doc['invoice_amount'], $doc['invoice_currency_code'])) ?></div>
                    <div class="small text-body-secondary"><?= $doc_state['balance'] > 0 ? 'due ' . escapeHtml($doc['invoice_due']) : 'total' ?></div>
                </div>
            </div>

            <label class="form-label">Send to <strong class="text-danger">*</strong></label>

            <div class="list-group mb-3">

                <?php

                while ($row = mysqli_fetch_assoc($sql_contacts)) {
                    $contact_id = intval($row['contact_id']);
                    $contact_name = escapeHtml($row['contact_name']);
                    $contact_email = escapeHtml($row['contact_email']);
                    $contact_title = escapeHtml($row['contact_title']);

                    $contact_checked = in_array($contact_id, $default_contact_ids, true);

                    ?>

                    <label class="list-group-item d-flex align-items-center gap-2" for="invoiceEmailContact<?= $contact_id ?>">
                        <input type="checkbox" class="form-check-input flex-shrink-0 m-0" name="contacts[]"
                            id="invoiceEmailContact<?= $contact_id ?>" value="<?= $contact_id ?>"
                            <?php if ($contact_checked) { echo "checked"; } ?>>
                        <?= contactAvatar($client_id, $contact_id, $row['contact_name'], $row['contact_photo'], 'sm') ?>
                        <span class="min-w-0">
                            <span class="d-flex flex-wrap align-items-center column-gap-1">
                                <strong><?= $contact_name ?></strong>
                                <?= contactFlagPills($row, false) ?>
                                <?php if ($contact_title) { ?><span class="text-body-secondary small"><?= $contact_title ?></span><?php } ?>
                            </span>
                            <span class="d-block small text-body-secondary text-truncate"><?= $contact_email ?></span>
                        </span>
                    </label>

                    <?php

                }

                ?>

            </div>

            <label class="form-label">Message <span class="text-body-secondary fw-normal small">in the email, above the invoice</span></label>
            <div class="input-group mb-3">
                <span class="input-group-text align-items-start pt-2"><i class="fas fa-fw fa-comment"></i></span>
                <textarea class="form-control" name="note" rows="3" maxlength="2000" placeholder="Optional - a word of your own: what it covers, a thank you, how to pay"></textarea>
            </div>

            <div class="form-check form-switch mb-3">
                <input class="form-check-input" type="checkbox" role="switch" name="attach_pdf" value="1" id="invoiceEmailPdf<?= $invoice_id ?>" <?= billingReminderRules()['attach_pdf'] ? 'checked' : '' ?>>
                <label class="form-check-label" for="invoiceEmailPdf<?= $invoice_id ?>">Attach the PDF</label>
            </div>

            <?php if ($invoice_status !== 'Draft') { ?>
                <p class="text-body-secondary small mb-0">
                    <i class="fa fa-fw fa-info-circle me-1"></i>This invoice is
                    <strong><?= $invoice_status ?></strong>, so sending it will not change its status.
                </p>
            <?php } ?>

        <?php } ?>

    </div>
    <div class="modal-footer">
        <?php if ($contact_count > 0) { ?>
            <button type="submit" name="email_invoice" class="btn btn-primary text-bold">
                <i class="fa fa-fw fa-paper-plane me-2"></i>Send
            </button>
        <?php } ?>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">
            <i class="fa fa-fw fa-times me-2"></i>Cancel
        </button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
