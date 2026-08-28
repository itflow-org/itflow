<?php

require_once '../../../includes/modal_header.php';

enforceUserPermission('module_sales', 2);

$client_id = intval($_GET['client_id']);

enforceClientAccess();

$sql = mysqli_query($mysqli, "SELECT client_currency_code, client_name FROM clients WHERE client_id = $client_id LIMIT 1");
$row = mysqli_fetch_assoc($sql);
$client_name = escapeHtml($row['client_name']);
$client_currency_code = escapeHtml($row['client_currency_code']);

if (empty($client_currency_code)) {
    $client_currency_code = $session_company_currency;
}

// What the client owes right now, shown as context so the agent knows roughly
// what is about to go out. Deliberately not filtered by the date range below -
// this is the standing balance, not a preview of the statement.
$row = mysqli_fetch_assoc(mysqli_query(
    $mysqli,
    "SELECT IFNULL(SUM(invoice_amount), 0) - IFNULL(SUM(amount_paid), 0) AS balance,
        COUNT(invoice_id) AS invoice_count FROM invoices
    LEFT JOIN (
        SELECT payment_invoice_id, SUM(payment_amount) AS amount_paid FROM payments
        WHERE payment_archived_at IS NULL
        GROUP BY payment_invoice_id
    ) AS invoice_payments ON payment_invoice_id = invoice_id
    WHERE invoice_client_id = $client_id
    AND invoice_status NOT IN ('Draft', 'Cancelled', 'Non-Billable')"
));
$outstanding_balance = floatval($row['balance']);

// Statement recipients follow the invoice rules - a statement is a billing
// document, so the same people who would get the invoice get the statement.
$sql_contacts = mysqli_query(
    $mysqli,
    "SELECT contact_billing, contact_email, contact_id, contact_name, contact_primary, contact_title
    FROM contacts
    WHERE contact_client_id = $client_id
    AND contact_archived_at IS NULL
    AND contact_email IS NOT NULL
    AND contact_email != ''
    " . documentContactFilterSql('invoice') . "
    ORDER BY contact_primary DESC, contact_billing DESC, contact_name ASC"
);

$contact_count = mysqli_num_rows($sql_contacts);

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
    <h5 class="modal-title text-white">
        <i class="fa fa-fw fa-file-alt me-2"></i>Send Account Statement
        <span class="text-muted ms-1"><?= $client_name ?></span>
    </h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="client_id" value="<?= $client_id ?>">

    <div class="modal-body">

        <?php if ($contact_count == 0) { ?>

            <p class="text-muted mb-0">
                This client has no primary or billing contact with an email address, so there is
                nobody to send a statement to. Flag a contact as billing first.
            </p>

        <?php } else { ?>

            <div class="alert alert-secondary">
                <i class="fa fa-fw fa-balance-scale me-2"></i>Current outstanding balance:
                <strong><?= numfmt_format_currency($currency_format, $outstanding_balance, $client_currency_code) ?></strong>
            </div>

            <h6 class="text-bold">Statement Options</h6>

            <div class="row g-2">
                <div class="col-sm-6 mb-3">
                    <label>From</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fa fa-fw fa-calendar"></i></span>
                        <input type="date" class="form-control" name="dtf" max="2999-12-31">
                    </div>
                    <small class="text-muted">Leave blank for all history</small>
                </div>
                <div class="col-sm-6 mb-3">
                    <label>To</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fa fa-fw fa-calendar"></i></span>
                        <input type="date" class="form-control" name="dtt" max="2999-12-31" value="<?= date("Y-m-d") ?>">
                    </div>
                </div>
            </div>

            <div class="mb-3">
                <div class="form-check">
                    <input type="checkbox" class="form-check-input" id="statementIncludePaid" name="include_paid" value="1">
                    <label class="form-check-label" for="statementIncludePaid">
                        Include paid invoices
                        <span class="text-muted">- off means outstanding items only</span>
                    </label>
                </div>
            </div>

            <hr>

            <h6 class="text-bold">Email Options</h6>

            <label class="mb-2">Send to <strong class="text-danger">*</strong></label>

            <div class="list-group mb-0">

                <?php

                while ($row = mysqli_fetch_assoc($sql_contacts)) {
                    $contact_id = intval($row['contact_id']);
                    $contact_name = escapeHtml($row['contact_name']);
                    $contact_email = escapeHtml($row['contact_email']);
                    $contact_title = escapeHtml($row['contact_title']);
                    $contact_primary = intval($row['contact_primary']);
                    $contact_billing = intval($row['contact_billing']);

                    $contact_checked = in_array($contact_id, $default_contact_ids, true);

                    ?>

                    <label class="list-group-item" for="statementContact<?= $contact_id ?>">
                        <input type="checkbox" class="form-check-input me-2" name="contacts[]"
                            id="statementContact<?= $contact_id ?>" value="<?= $contact_id ?>"
                            <?php if ($contact_checked) { echo "checked"; } ?>>
                        <strong><?= $contact_name ?></strong>
                        <?php if ($contact_primary == 1) { ?>
                            <span class="badge text-bg-primary ms-1">Primary</span>
                        <?php } ?>
                        <?php if ($contact_billing == 1) { ?>
                            <span class="badge text-bg-success ms-1">Billing</span>
                        <?php } ?>
                        <?php if (!empty($contact_title)) { ?>
                            <span class="text-muted ms-1"><?= $contact_title ?></span>
                        <?php } ?>
                        <br>
                        <span class="text-muted ms-4"><?= $contact_email ?></span>
                    </label>

                    <?php

                }

                ?>

            </div>

        <?php } ?>

    </div>
    <div class="modal-footer">
        <?php if ($contact_count > 0) { ?>
            <button type="submit" name="send_statement" class="btn btn-primary text-bold">
                <i class="fa fa-fw fa-paper-plane me-2"></i>Send Statement
            </button>
        <?php } ?>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">
            <i class="fa fa-fw fa-times me-2"></i>Cancel
        </button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
