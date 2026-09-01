<?php

require_once '../../includes/modal_header.php';

$email_id = intval($_GET['id']);

//Initialize the HTML Purifier to prevent XSS
require "../../../libs/htmlpurifier/HTMLPurifier.standalone.php";

$purifier_config = HTMLPurifier_Config::createDefault();
$purifier_config->set('Cache.DefinitionImpl', null); // Disable cache by setting a non-existent directory or an invalid one
$purifier_config->set('URI.AllowedSchemes', ['data' => true, 'src' => true, 'http' => true, 'https' => true]);
$purifier = new HTMLPurifier($purifier_config);

$sql = mysqli_query($mysqli, "SELECT email_attempts, email_content, email_failed_at, email_from, email_from_name,
    email_queued_at, email_recipient, email_recipient_name, email_sent_at, email_status,
    email_subject FROM email_queue WHERE email_id = $email_id LIMIT 1");
$row = mysqli_fetch_assoc($sql);

$email_from = escapeHtml($row['email_from']);
$email_from_name = escapeHtml($row['email_from_name']);
$email_recipient = escapeHtml($row['email_recipient']);
$email_recipient_name = escapeHtml($row['email_recipient_name']);
$email_subject = escapeHtml($row['email_subject']);
$email_content = $purifier->purify($row['email_content']);
$email_attempts = intval($row['email_attempts']);
$email_queued_at = escapeHtml($row['email_queued_at']);
$email_failed_at = escapeHtml($row['email_failed_at']);
$email_sent_at = escapeHtml($row['email_sent_at']);
$email_status = intval($row['email_status']);
if ($email_status == 0) {
    $email_status_display = "<div class='text-primary'>Queued</div>";
} elseif($email_status == 1) {
    $email_status_display = "<div class='text-warning'>Sending</div>";
} elseif($email_status == 2) {
    $email_status_display = "<div class='text-danger'>Failed</div><small class='text-secondary'>$email_failed_at</small>";
} else {
    $email_status_display = "<div class='text-success'>Sent</div><small class='text-secondary'>$email_sent_at</small>";
}

// Generate the HTML form content using output buffering.
ob_start();
?>
<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class='fas fa-fw fa-envelope-open me-2'></i><strong><?= $email_subject ?></strong></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<div class="modal-body">
    <div class="row">
        <div class="col-md-1">
            <span class="text-secondary">From:</span>
        </div>
        <div class="col-md-10">
            <?= "<strong>$email_from_name</strong> ($email_from)" ?>
        </div>
    </div>
    <hr class="my-2">
    <div class="row">
        <div class="col-md-1">
            <span class="text-secondary">To:</span>
        </div>
        <div class="col-md-10">
            <?= "<strong>$email_recipient_name</strong> ($email_recipient)" ?>
        </div>
    </div>
    <hr class="my-2">
    <div class="prettyContent">
        <?php if ($email_status == 3 && $email_content === '') { ?>
            <em class="text-secondary">Message content was cleared on delivery.</em>
        <?php } else { echo $email_content; } ?>
    </div>
</div>

<script src="../../js/pretty_content.js"></script>

<?php
require_once '../../../includes/modal_footer.php';
