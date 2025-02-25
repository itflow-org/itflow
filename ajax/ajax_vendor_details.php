<?php

require_once '../includes/ajax_header.php';

$vendor_id = intval($_GET['id']);

$sql = mysqli_query($mysqli, "SELECT * FROM vendors WHERE vendor_id = $vendor_id LIMIT 1");
                     
$row = mysqli_fetch_array($sql);
$name = sanitizeInput($row['vendor_name']);
$description = sanitizeInput($row['vendor_description']);
$account_number = sanitizeInput($row['vendor_account_number']);
$contact_name = sanitizeInput($row['vendor_contact_name']);
$phone = preg_replace("/[^0-9]/", '',$row['vendor_phone']);
$extension = preg_replace("/[^0-9]/", '',$row['vendor_extension']);
$email = sanitizeInput($row['vendor_email']);
$website = sanitizeInput($row['vendor_website']);
$hours = sanitizeInput($row['vendor_hours']);
$sla = sanitizeInput($row['vendor_sla']);
$code = sanitizeInput($row['vendor_code']);
$notes = sanitizeInput($row['vendor_notes']);


// Generate the HTML form content using output buffering.
ob_start();
?>

<div class="modal-header">
    <h5 class="modal-title text-white"><i class="fas fa-fw fa-building mr-2"></i><?php echo $vendor_name; ?></h5>
    <button type="button" class="close text-white" data-dismiss="modal">
        <span>&times;</span>
    </button>
</div>
<div class="modal-body bg-white">
    <div>Description:</div><?php echo $vendor_description; ?></div>
</div>

<div class="modal-footer bg-white">
    <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fa fa-times mr-2"></i>Close</button>
</div>

<?php
require_once "../includes/ajax_footer.php";
