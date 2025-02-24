<?php

require_once "includes/inc_all_admin.php";

//Initialize the HTML Purifier to prevent XSS
require "plugins/htmlpurifier/HTMLPurifier.standalone.php";

$purifier_config = HTMLPurifier_Config::createDefault();
$purifier_config->set('Cache.DefinitionImpl', null); // Disable cache by setting a non-existent directory or an invalid one
$purifier_config->set('URI.AllowedSchemes', ['data' => true, 'src' => true, 'http' => true, 'https' => true]);
$purifier = new HTMLPurifier($purifier_config);

if (isset($_GET['email_id'])) {
	$email_id = intval($_GET['email_id']);
} else {
	echo "You dont belong here";
	exit();
}

$sql = mysqli_query($mysqli, "SELECT * FROM email_queue WHERE email_id = $email_id");

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

?>

<ol class="breadcrumb d-print-none">
  <li class="breadcrumb-item">
    <a href="admin_user.php"><i class="fas fa-fw fa-user-shield mr-2"></i>Admin</a>
  </li>
  <li class="breadcrumb-item">
    <a href="admin_mail_queue.php"><i class="fas fa-fw fa-mail-bulk mr-2"></i>Mail Queue</a>
  </li>
  <li class="breadcrumb-item active"><i class="fas fa-fw fa-envelope-open mr-2"></i><?php echo $email_subject; ?></li>
</ol>

<div class="row">

  <div class="col-md-12">
    <div class="card">
      <div class="card-header bg-dark">
        <div>From: <?php echo "$email_from_name <small>($email_from)</small>"; ?></div>
        <div>To: <?php echo "$email_recipient_name <small>($email_recipient)</small>"; ?></div>
        <div>Subject: <?php echo $email_subject; ?></div>
      </div>
      <div class="card-body prettyContent">
        <?php echo $email_content; ?>
      </div>
    </div>
  </div>

</div>

<script src="js/pretty_content.js"></script>

<?php

require_once "includes/footer.php";
