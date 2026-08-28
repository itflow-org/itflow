<?php

require_once '../../../includes/modal_header.php';

enforceUserPermission('module_sales', 2);

$quote_id = intval($_GET['quote_id']);

$sql = mysqli_query($mysqli, "SELECT quote_client_id, quote_number, quote_prefix, quote_status
    FROM quotes WHERE quote_id = $quote_id LIMIT 1");
$row = mysqli_fetch_assoc($sql);
$quote_prefix = escapeHtml($row['quote_prefix']);
$quote_number = intval($row['quote_number']);
$quote_status = escapeHtml($row['quote_status']);
$client_id = intval($row['quote_client_id']);

enforceClientAccess();

// Wider than the invoice picker - see documentContactFilterSql(). A quote is
// read by whoever scoped the work as often as by whoever signs it.
$sql_contacts = mysqli_query(
    $mysqli,
    "SELECT contact_billing, contact_email, contact_id, contact_important, contact_name,
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
    <h5 class="modal-title text-white">
        <i class="fa fa-fw fa-paper-plane me-2"></i>Email Quote <?= "$quote_prefix$quote_number" ?>
    </h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="quote_id" value="<?= $quote_id ?>">

    <div class="modal-body">

        <?php if ($contact_count == 0) { ?>

            <p class="text-muted mb-0">
                This client has no primary, billing, technical or important contact with an email
                address, so there is nobody to send a quote to. Flag a contact with one of those
                roles, or use Mark Sent to record that the quote went out some other way.
            </p>

        <?php } else { ?>

            <label class="mb-2">Send to <strong class="text-danger">*</strong></label>

            <div class="list-group mb-3">

                <?php

                while ($row = mysqli_fetch_assoc($sql_contacts)) {
                    $contact_id = intval($row['contact_id']);
                    $contact_name = escapeHtml($row['contact_name']);
                    $contact_email = escapeHtml($row['contact_email']);
                    $contact_title = escapeHtml($row['contact_title']);
                    $contact_primary = intval($row['contact_primary']);
                    $contact_billing = intval($row['contact_billing']);
                    $contact_technical = intval($row['contact_technical']);
                    $contact_important = intval($row['contact_important']);

                    $contact_checked = in_array($contact_id, $default_contact_ids, true);

                    ?>

                    <label class="list-group-item" for="quoteEmailContact<?= $contact_id ?>">
                        <input type="checkbox" class="form-check-input me-2" name="contacts[]"
                            id="quoteEmailContact<?= $contact_id ?>" value="<?= $contact_id ?>"
                            <?php if ($contact_checked) { echo "checked"; } ?>>
                        <strong><?= $contact_name ?></strong>
                        <?php if ($contact_primary == 1) { ?>
                            <span class="badge text-bg-primary ms-1">Primary</span>
                        <?php } ?>
                        <?php if ($contact_billing == 1) { ?>
                            <span class="badge text-bg-success ms-1">Billing</span>
                        <?php } ?>
                        <?php if ($contact_technical == 1) { ?>
                            <span class="badge text-bg-info ms-1">Technical</span>
                        <?php } ?>
                        <?php if ($contact_important == 1) { ?>
                            <span class="badge text-bg-warning ms-1">Important</span>
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

            <?php if ($quote_status !== 'Draft') { ?>
                <p class="text-muted mb-0">
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
