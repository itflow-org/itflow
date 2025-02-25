<?php

require_once '../includes/ajax_header.php';

$software_id = intval($_GET['id']);

$sql = mysqli_query($mysqli, "SELECT * FROM software WHERE software_id = $software_id LIMIT 1");

$row = mysqli_fetch_array($sql);
$software_name = nullable_htmlentities($row['software_name']);
$software_description = nullable_htmlentities($row['software_description']);
$software_version = nullable_htmlentities($row['software_version']);
$software_type = nullable_htmlentities($row['software_type']);
$software_license_type = nullable_htmlentities($row['software_license_type']);
$software_key = nullable_htmlentities($row['software_key']);
$software_seats = nullable_htmlentities($row['software_seats']);
$software_purchase = nullable_htmlentities($row['software_purchase']);
$software_expire = nullable_htmlentities($row['software_expire']);
$software_notes = nullable_htmlentities($row['software_notes']);
$software_created_at = nullable_htmlentities($row['software_created_at']);
$software_vendor_id = intval($row['software_vendor_id']);
$client_id = intval($row['software_client_id']);
$seat_count = 0;

// Device Licenses
$asset_licenses_sql = mysqli_query($mysqli, "SELECT asset_id FROM software_assets WHERE software_id = $software_id");
$asset_licenses_array = array();
while ($row = mysqli_fetch_array($asset_licenses_sql)) {
    $asset_licenses_array[] = intval($row['asset_id']);
    $seat_count = $seat_count + 1;
}
$asset_licenses = implode(',', $asset_licenses_array);

// User Licenses
$contact_licenses_sql = mysqli_query($mysqli, "SELECT contact_id FROM software_contacts WHERE software_id = $software_id");
$contact_licenses_array = array();
while ($row = mysqli_fetch_array($contact_licenses_sql)) {
    $contact_licenses_array[] = intval($row['contact_id']);
    $seat_count = $seat_count + 1;
}
$contact_licenses = implode(',', $contact_licenses_array);

// Generate the HTML form content using output buffering.
ob_start();
?>
<div class="modal-header">
    <h5 class="modal-title"><i class="fa fa-fw fa-cube mr-2"></i>Editing license: <strong><?php echo $software_name; ?></strong></h5>
    <button type="button" class="close text-white" data-dismiss="modal">
        <span>&times;</span>
    </button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="software_id" value="<?php echo $software_id; ?>">
    <input type="hidden" name="client_id" value="<?php echo $client_id; ?>">
    <div class="modal-body bg-white">

        <ul class="nav nav-pills nav-justified mb-3">
            <li class="nav-item">
                <a class="nav-link active" data-toggle="pill" href="#pills-details<?php echo $software_id; ?>">Details</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-toggle="pill" href="#pills-license<?php echo $software_id; ?>">License</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-toggle="pill" href="#pills-device-licenses<?php echo $software_id; ?>">Devices</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-toggle="pill" href="#pills-user-licenses<?php echo $software_id; ?>">Users</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-toggle="pill" href="#pills-notes<?php echo $software_id; ?>">Notes</a>
            </li>
        </ul>

        <hr>

        <div class="tab-content" <?php if (lookupUserPermission('module_support') <= 1) { echo 'inert'; } ?>>

            <div class="tab-pane fade show active" id="pills-details<?php echo $software_id; ?>">

                <div class="form-group">
                    <label>Software Name <strong class="text-danger">*</strong></label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-cube"></i></span>
                        </div>
                        <input type="text" class="form-control" name="name" placeholder="Software name" maxlength="200" value="<?php echo $software_name; ?>" required>
                    </div>
                </div>

                <div class="form-group">
                    <label>Version</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-cube"></i></span>
                        </div>
                        <input type="text" class="form-control" name="version" placeholder="Software version" maxlength="200" value="<?php echo $software_version; ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label>Description</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-angle-right"></i></span>
                        </div>
                        <input type="text" class="form-control" name="description" placeholder="Short description" value="<?php echo $software_description; ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label>Vendor</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-building"></i></span>
                        </div>
                        <select class="form-control select2" name="vendor">
                            <option value="">- Select Vendor -</option>
                            <?php
                            $vendor_sql = mysqli_query($mysqli, "SELECT vendor_id, vendor_name FROM vendors WHERE vendor_client_id = $client_id AND vendor_archived_at IS NULL ORDER BY vendor_name ASC");
                                while ($row = mysqli_fetch_array($vendor_sql)) {
                                    $vendor_id = $row['vendor_id'];
                                    $vendor_name = $row['vendor_name'];
                                ?>
                                <option <?php if ($software_vendor_id == $vendor_id) { echo "selected"; } ?> value="<?php echo $vendor_id; ?>"><?php echo $vendor_name; ?></option>
                            <?php 
                            }
                            ?>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label>Type <strong class="text-danger">*</strong></label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-tag"></i></span>
                        </div>
                        <select class="form-control select2" name="type" required>
                            <?php foreach($software_types_array as $software_type_select) { ?>
                                <option <?php if ($software_type == $software_type_select) { echo "selected"; } ?>><?php echo $software_type_select; ?></option>
                            <?php } ?>
                        </select>
                    </div>
                </div>

            </div>

             <div class="tab-pane fade" id="pills-license<?php echo $software_id; ?>">

                <div class="form-group">
                    <label>License Type</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-cube"></i></span>
                        </div>
                        <select class="form-control select2" name="license_type">
                            <option value="">- Select a License Type -</option>
                            <?php foreach($license_types_array as $license_type_select) { ?>
                                <option <?php if ($license_type_select == $software_license_type) { echo "selected"; } ?>><?php echo $license_type_select; ?></option>
                            <?php } ?>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label>Seats</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-cube"></i></span>
                        </div>
                        <input type="text" class="form-control" inputmode="numeric" pattern="[0-9]*" name="seats" placeholder="Number of seats" value="<?php echo $software_seats; ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label>License Key</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-key"></i></span>
                        </div>
                        <input type="text" class="form-control" name="key" placeholder="License key" maxlength="200" value="<?php echo $software_key; ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label>Purchase Date</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-calendar-check"></i></span>
                        </div>
                        <input type="date" class="form-control" name="purchase" max="2999-12-31" value="<?php echo $software_purchase; ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label>Expire</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-calendar-times"></i></span>
                        </div>
                        <input type="date" class="form-control" name="expire" max="2999-12-31" value="<?php echo $software_expire; ?>">
                    </div>
                </div>

            </div>

            <div class="tab-pane fade" id="pills-device-licenses<?php echo $software_id; ?>">

                <ul class="list-group">

                    <li class="list-group-item bg-dark">
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input"
                                onclick="this.closest('.tab-pane').querySelectorAll('.asset-checkbox').forEach(checkbox => checkbox.checked = this.checked);"
                            >
                            <label class="form-check-label ml-3"><strong>Licensed Devices</strong></label>
                        </div>
                    </li>


                    <?php
                    $sql_assets_select = mysqli_query($mysqli, "SELECT * FROM assets LEFT JOIN contacts ON asset_contact_id = contact_id WHERE (asset_archived_at > '$software_created_at' OR asset_archived_at IS NULL) AND asset_client_id = $client_id ORDER BY asset_archived_at ASC, asset_name ASC");

                    while ($row = mysqli_fetch_array($sql_assets_select)) {
                        $asset_id_select = intval($row['asset_id']);
                        $asset_name_select = nullable_htmlentities($row['asset_name']);
                        $asset_type_select = nullable_htmlentities($row['asset_type']);
                        $asset_archived_at = nullable_htmlentities($row['asset_archived_at']);
                        if (empty($asset_archived_at)) {
                            $asset_archived_display = "";
                        } else {
                            $asset_archived_display = "Archived - ";
                        }
                        $contact_name_select = nullable_htmlentities($row['contact_name']);

                        ?>
                        <li class="list-group-item">
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input asset-checkbox" name="assets[]" value="<?php echo $asset_id_select; ?>" <?php if (in_array($asset_id_select, $asset_licenses_array)) { echo "checked"; } ?>>
                                <label class="form-check-label ml-2"><?php echo "$asset_archived_display$asset_name_select - $contact_name_select"; ?></label>
                            </div>
                        </li>

                    <?php } ?>

                </ul>

            </div>

            <div class="tab-pane fade" id="pills-user-licenses<?php echo $software_id; ?>">

                <ul class="list-group">

                    <li class="list-group-item bg-dark">
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" onclick="this.closest('.tab-pane').querySelectorAll('.user-checkbox').forEach(checkbox => checkbox.checked = this.checked);">
                            <label class="form-check-label ml-3"><strong>Licensed Users</strong></label>
                        </div>
                    </li>

                    <?php
                    $sql_contacts_select = mysqli_query($mysqli, "SELECT * FROM contacts WHERE (contact_archived_at > '$software_created_at' OR contact_archived_at IS NULL) AND contact_client_id = $client_id ORDER BY contact_archived_at ASC, contact_name ASC");

                    while ($row = mysqli_fetch_array($sql_contacts_select)) {
                        $contact_id_select = intval($row['contact_id']);
                        $contact_name_select = nullable_htmlentities($row['contact_name']);
                        $contact_email_select = nullable_htmlentities($row['contact_email']);
                        $contact_archived_at = nullable_htmlentities($row['contact_archived_at']);
                        if (empty($contact_archived_at)) {
                            $contact_archived_display = "";
                        } else {
                            $contact_archived_display = "Archived - ";
                        }

                        ?>
                        <li class="list-group-item">
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input user-checkbox" name="contacts[]" value="<?php echo $contact_id_select; ?>" <?php if (in_array("$contact_id_select", $contact_licenses_array)) { echo "checked"; } ?>>
                                <label class="form-check-label ml-2"><?php echo "$contact_archived_display$contact_name_select - $contact_email_select"; ?></label>
                            </div>
                        </li>

                    <?php } ?>

                </ul>

            </div>

            <div class="tab-pane fade" id="pills-notes<?php echo $software_id; ?>">

                <textarea class="form-control" rows="12" placeholder="Enter some notes" name="notes"><?php echo $software_notes; ?></textarea>

            </div>

        </div>

    </div>
    <div class="modal-footer bg-white">
        <button type="submit" name="edit_software" class="btn btn-primary text-bold"><i class="fa fa-check mr-2"></i>Save</button>
        <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fa fa-times mr-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once "../includes/ajax_footer.php";
