<?php

require_once '../../../includes/modal_header.php';

enforceUserPermission('module_client');

$vendor_id = intval($_GET['id']);

$sql = mysqli_query($mysqli, "SELECT vendor_account_number, vendor_client_id, vendor_code, vendor_contact_name,
    vendor_description, vendor_email, vendor_extension, vendor_hours, vendor_name,
    vendor_notes, vendor_phone, vendor_sla, vendor_website FROM vendors WHERE vendor_id = $vendor_id LIMIT 1");

$row = mysqli_fetch_assoc($sql);
$name = escapeHtml($row['vendor_name']);
$description = escapeHtml($row['vendor_description']);
$account_number = escapeHtml($row['vendor_account_number']);
$contact_name = escapeHtml($row['vendor_contact_name']);
$phone = preg_replace("/[^0-9]/", '', (string) $row['vendor_phone']);
$extension = preg_replace("/[^0-9]/", '', (string) $row['vendor_extension']);
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

// Licenses bought through this vendor - soonest renewal first
$vendor_licenses = [];
if (lookupUserPermission('module_support') >= 1) {
    $sql_vendor_licenses = mysqli_query($mysqli, "SELECT software_id AS id, software_name AS name, software_type, software_version, software_license_type,
            software_seats, software_expire, " . softwareSeatsUsedSql('software.software_id') . " AS seats_used
        FROM software
        WHERE software_vendor_id = $vendor_id AND software_archived_at IS NULL " . clientScopeSql('software_client_id') . "
        ORDER BY software_expire IS NULL, software_expire ASC, software_name ASC");
    while ($vendor_license = mysqli_fetch_assoc($sql_vendor_licenses)) {
        $vendor_licenses[] = $vendor_license;
    }
}

ob_start();

?>

<div class="modal-header bg-dark text-white">
    <div class="d-flex align-items-center">
        <i class="fas fa-fw fa-building fa-2x me-3"></i>
        <div>
            <h5 class="modal-title mb-0"><?= $name ?></h5>
            <div class="text-muted"><?= $description ?: '-'; ?></div>
        </div>
    </div>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>

<div class="modal-body bg-light">

    <!-- Vendor Info Card -->
    <div class="card mb-3 shadow-sm rounded">
        <div class="card-body">
            <h6 class="text-secondary"><i class="fas fa-info-circle me-2"></i>Vendor Details</h6>
            <div class="row">
                <div class="col-sm-6">
                    <div><strong>Account Number:</strong> <?= $account_number ?: '-'; ?></div>
                    <div><strong>Hours:</strong> <?= $hours ?: '-'; ?></div>
                    <div><strong>SLA:</strong> <?= $sla ?: '-'; ?></div>
                </div>
                <div class="col-sm-6">
                    <div><strong>Code:</strong> <?= $code ?: '-'; ?></div>
                    <div><strong>Website:</strong> <?= !empty($website) ? '<a href="https://' . $website . '" target="_blank" class="text-primary">' . $website . '</a>' : '<span class="text-muted">Not Available</span>' ?></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Contact Info Card -->
    <div class="card mb-3 shadow-sm rounded">
        <div class="card-body">
            <h6 class="text-secondary"><i class="fas fa-user me-2"></i>Contact Information</h6>
            <div class="row">
                <div class="col-sm-6">
                    <div><strong>Contact Name:</strong> <?= $contact_name ?: '-'; ?></div>
                    <div><strong>Phone:</strong> <?= $phone ?: '-'; ?></div>
                </div>
                <div class="col-sm-6">
                    <div><strong>Email:</strong> <?= !empty($email) ? '<a href="mailto:' . $email . '" class="text-primary">' . $email . '</a>' : '<span class="text-muted">Not Available</span>' ?></div>
                </div>
            </div>
        </div>
    </div>

    <?php if ($vendor_licenses) { ?>
        <!-- Licenses from this vendor -->
        <div class="card mb-3 shadow-sm rounded">
            <div class="card-body">
                <h6 class="text-secondary"><i class="fas fa-cube me-2"></i>Licenses <span class="badge rounded-pill text-bg-secondary ms-1"><?= count($vendor_licenses) ?></span></h6>
                <ul class="list-unstyled mb-0 itflow-links">
                    <?php foreach ($vendor_licenses as $vendor_license) { ?>
                        <li class="itflow-link-row"><?= linkRowHtml('software', $vendor_license, $client_id) ?></li>
                    <?php } ?>
                </ul>
            </div>
        </div>
    <?php } ?>

    <!-- Notes Card -->
    <div class="card mb-3 shadow-sm rounded">
        <div class="card-body">
            <h6 class="text-secondary"><i class="fas fa-sticky-note me-2"></i>Notes</h6>
            <div>
                <?= $notes ?: '-'; ?>
            </div>
        </div>
    </div>

</div>

<?php
require_once '../../../includes/modal_footer.php';
