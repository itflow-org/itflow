<?php

require_once '../../../includes/modal_header.php';

$vendor_id = intval($_GET['id']);

$sql = mysqli_query($mysqli, "SELECT * FROM vendors WHERE vendor_id = $vendor_id LIMIT 1");

$row = mysqli_fetch_assoc($sql);
$name = escapeHtml($row['vendor_name']);
$description = escapeHtml($row['vendor_description']);
$account_number = escapeHtml($row['vendor_account_number']);
$contact_name = escapeHtml($row['vendor_contact_name']);
$phone = preg_replace("/[^0-9]/", '',$row['vendor_phone']);
$extension = preg_replace("/[^0-9]/", '',$row['vendor_extension']);
$email = escapeHtml($row['vendor_email']);
$website = escapeHtml($row['vendor_website']);
$hours = escapeHtml($row['vendor_hours']);
$sla = escapeHtml($row['vendor_sla']);
$code = escapeHtml($row['vendor_code']);
$notes = escapeHtml($row['vendor_notes']);
$client_id = intval($row['vendor_client_id']);

if ($client_id) {
    enforceClientAccess();
}

ob_start();

?>

<div class="modal-header bg-dark text-white">
    <div class="d-flex align-items-center">
        <i class="fas fa-fw fa-building fa-2x mr-3"></i>
        <div>
            <h5 class="modal-title mb-0"><?php echo $name; ?></h5>
            <div class="text-muted"><?= $description ?: '-'; ?></div>
        </div>
    </div>
    <button type="button" class="close text-white" data-dismiss="modal">
        <span>&times;</span>
    </button>
</div>

<div class="modal-body bg-light">

    <!-- Vendor Info Card -->
    <div class="card mb-3 shadow-sm rounded">
        <div class="card-body">
            <h6 class="text-secondary"><i class="fas fa-info-circle mr-2"></i>Vendor Details</h6>
            <div class="row">
                <div class="col-sm-6">
                    <div><strong>Account Number:</strong> <?= $account_number ?: '-'; ?></div>
                    <div><strong>Hours:</strong> <?= $hours ?: '-'; ?></div>
                    <div><strong>SLA:</strong> <?= $sla ?: '-'; ?></div>
                </div>
                <div class="col-sm-6">
                    <div><strong>Code:</strong> <?= $code ?: '-'; ?></div>
                    <div><strong>Website:</strong> <?php echo !empty($website) ? '<a href="https://' . $website . '" target="_blank" class="text-primary">' . $website . '</a>' : '<span class="text-muted">Not Available</span>'; ?></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Contact Info Card -->
    <div class="card mb-3 shadow-sm rounded">
        <div class="card-body">
            <h6 class="text-secondary"><i class="fas fa-user mr-2"></i>Contact Information</h6>
            <div class="row">
                <div class="col-sm-6">
                    <div><strong>Contact Name:</strong> <?= $contact_name ?: '-'; ?></div>
                    <div><strong>Phone:</strong> <?= $phone ?: '-'; ?></div>
                </div>
                <div class="col-sm-6">
                    <div><strong>Email:</strong> <?php echo !empty($email) ? '<a href="mailto:' . $email . '" class="text-primary">' . $email . '</a>' : '<span class="text-muted">Not Available</span>'; ?></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Notes Card -->
    <div class="card mb-3 shadow-sm rounded">
        <div class="card-body">
            <h6 class="text-secondary"><i class="fas fa-sticky-note mr-2"></i>Notes</h6>
            <div>
                <?= $notes ?: '-'; ?>
            </div>
        </div>
    </div>

</div>

<?php
require_once '../../../includes/modal_footer.php';
