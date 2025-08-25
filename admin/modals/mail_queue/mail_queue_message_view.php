<?php

require_once '../../../includes/modal_header.php';

if (!isset($session_is_admin) || !$session_is_admin) {
    exit(WORDING_ROLECHECK_FAILED . "<br>Tell your admin: Your role does not have admin access.");
}

$email_id = intval($_GET['id']);

//Initialize the HTML Purifier to prevent XSS
require "../../../plugins/htmlpurifier/HTMLPurifier.standalone.php";

$purifier_config = HTMLPurifier_Config::createDefault();
$purifier_config->set('Cache.DefinitionImpl', null); // Disable cache by setting a non-existent directory or an invalid one
$purifier_config->set('URI.AllowedSchemes', ['data' => true, 'src' => true, 'http' => true, 'https' => true]);
$purifier = new HTMLPurifier($purifier_config);

$sql = mysqli_query($mysqli, "SELECT * FROM email_queue WHERE email_id = $email_id LIMIT 1");
$row = mysqli_fetch_array($sql);

$email_from = nullable_htmlentities($row['email_from']);
$email_from_name = nullable_htmlentities($row['email_from_name']);
$email_recipient = nullable_htmlentities($row['email_recipient']);
$email_recipient_name = nullable_htmlentities($row['email_recipient_name']);
$email_subject = nullable_htmlentities($row['email_subject']);
$email_content = $purifier->purify($row['email_content']);
$email_attempts = intval($row['email_attempts']);
$email_queued_at = nullable_htmlentities($row['email_queued_at']);
$email_failed_at = nullable_htmlentities($row['email_failed_at']);
$email_sent_at = nullable_htmlentities($row['email_sent_at']);
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
    <h5 class="modal-title"><i class='fas fa-fw fa-envelope-open mr-2'></i><strong><?php echo $email_subject; ?></strong></h5>
    <button type="button" class="close text-light" data-dismiss="modal">
        <span>&times;</span>
    </button>
</div>
<div class="modal-body">
    <div class="row">
        <div class="col-md-1">
            <span class="text-secondary">From:</span>
        </div>
        <div class="col-md-10">
            <?php echo "<strong>$email_from_name</strong> ($email_from)"; ?>
        </div>
    </div>
    <hr class="my-2">
    <div class="row">
        <div class="col-md-1">
            <span class="text-secondary">To:</span>
        </div>
        <div class="col-md-10">
            <?php echo "<strong>$email_recipient_name</strong> ($email_recipient)"; ?>
        </div>
    </div>
    <hr class="my-2">
    <div class="prettyContent">
        <?php echo $email_content; ?>
    </div>
</div>

<script src="../../js/pretty_content.js"></script>

<?php
require_once '../../../includes/modal_footer.php';
