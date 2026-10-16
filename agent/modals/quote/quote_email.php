<?php

/*
 * Send a quote to the client's contacts picked, with a note and the PDF.
 * ?quote_id=. Posts email_quote.
 */

require_once '../../../includes/modal_header.php';

enforceUserPermission('module_sales', 2);

$quote_id = intval($_GET['quote_id']);

$sql = mysqli_query($mysqli, "SELECT quote_client_id, quote_number, quote_prefix, quote_status
    FROM quotes WHERE quote_id = $quote_id LIMIT 1");
$row = mysqli_fetch_assoc($sql);
if (!$row) {
    exit(json_encode(['error' => 'That quote no longer exists.']));
}
$quote_prefix = escapeHtml($row['quote_prefix']);
$quote_number = intval($row['quote_number']);
$quote_status = escapeHtml($row['quote_status']);
$client_id = intval($row['quote_client_id']);

enforceClientAccess();

$doc = billingRow('quote', $quote_id);

// Wider than the invoice picker - see documentContactFilterSql(). A quote is
// read by whoever scoped the work as often as by whoever signs it.
$sql_contacts = mysqli_query(
    $mysqli,
    "SELECT contact_billing, contact_email, contact_id, contact_photo, contact_important, contact_name,
        contact_primary, contact_technical, contact_title
    FROM contacts
    WHERE contact_client_id = $client_id
    AND contact_archived_at IS NULL
    AND contact_email IS NOT NULL
    AND contact_email != ''
    " . documentContactFilterSql('quote') . "
    ORDER BY contact_primary DESC, contact_billing DESC, contact_technical DESC, contact_name ASC"
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
    " . documentDefaultContactFilterSql('quote')
);
while ($row = mysqli_fetch_assoc($sql_defaults)) {
    $default_contact_ids[] = intval($row['contact_id']);
}

ob_start();

?>

<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fas fa-fw fa-paper-plane me-2"></i>Send Quote <span class="fw-normal text-white-50 ms-1"><?= escapeHtml(billingNumber('quote', $doc)) ?> &middot; <?= escapeHtml($doc['client_name']) ?></span></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="quote_id" value="<?= $quote_id ?>">

    <div class="modal-body">

        <?php if ($contact_count == 0) { ?>

            <p class="text-body-secondary mb-0">
                This client has no primary, billing, technical or important contact with an email
                address, so there is nobody to send a quote to. Flag a contact with one of those
                roles, or use Mark Sent to record that the quote went out some other way.
            </p>

        <?php } else { ?>

            <!-- What goes out -->
            <div class="d-flex align-items-center gap-3 p-3 mb-3 rounded bg-body-tertiary">
                <?= billingTile('quote') ?>
                <div class="flex-grow-1 min-w-0">
                    <div class="fw-semibold"><?= escapeHtml(billingNumber('quote', $doc)) ?><?= trim((string) $doc['quote_scope']) !== '' ? ' - ' . escapeHtml($doc['quote_scope']) : '' ?></div>
                    <div class="small text-body-secondary">An email with a link to view it, accept or decline it, and download it as a PDF.</div>
                </div>
                <div class="text-end">
                    <div class="fw-semibold itflow-money"><?= escapeHtml(quoteAmountText(quoteGroups($doc), $doc['quote_currency_code'])) ?></div>
                    <div class="small text-body-secondary">valid until <?= escapeHtml($doc['quote_expire']) ?></div>
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

                    <label class="list-group-item d-flex align-items-center gap-2" for="quoteEmailContact<?= $contact_id ?>">
                        <input type="checkbox" class="form-check-input flex-shrink-0 m-0" name="contacts[]"
                            id="quoteEmailContact<?= $contact_id ?>" value="<?= $contact_id ?>"
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

            <label class="form-label">Message <span class="text-body-secondary fw-normal small">in the email, above the quote</span></label>
            <div class="input-group mb-3">
                <span class="input-group-text align-items-start pt-2"><i class="fas fa-fw fa-comment"></i></span>
                <textarea class="form-control" name="note" rows="3" maxlength="2000" placeholder="Optional - a word of your own: what it covers, what happens next"></textarea>
            </div>

            <div class="form-check form-switch mb-3">
                <input class="form-check-input" type="checkbox" role="switch" name="attach_pdf" value="1" id="quoteEmailPdf<?= $quote_id ?>" <?= billingReminderRules()['attach_pdf'] ? 'checked' : '' ?>>
                <label class="form-check-label" for="quoteEmailPdf<?= $quote_id ?>">Attach the PDF</label>
            </div>

            <?php if ($quote_status !== 'Draft') { ?>
                <p class="text-body-secondary small mb-0">
                    <i class="fa fa-fw fa-info-circle me-1"></i>This quote is
                    <strong><?= $quote_status ?></strong>, so sending it will not change its status.
                </p>
            <?php } ?>

        <?php } ?>

    </div>
    <div class="modal-footer">
        <?php if ($contact_count > 0) { ?>
            <button type="submit" name="email_quote" class="btn btn-primary text-bold">
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
