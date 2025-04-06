<?php

require_once '../includes/ajax_header.php';

$vendor_id = intval($_GET['id']);

$sql = mysqli_query($mysqli, "SELECT * FROM vendors WHERE vendor_id = $vendor_id LIMIT 1");

$row = mysqli_fetch_array($sql);
$vendor_name = nullable_htmlentities($row['vendor_name']);
$vendor_description = nullable_htmlentities($row['vendor_description']);
$vendor_account_number = nullable_htmlentities($row['vendor_account_number']);
$vendor_contact_name = nullable_htmlentities($row['vendor_contact_name']);
$vendor_phone_country_code = nullable_htmlentities($row['vendor_phone_country_code']);
$vendor_phone = nullable_htmlentities(formatPhoneNumber($row['vendor_phone'], $vendor_phone_country_code));
$vendor_extension = nullable_htmlentities($row['vendor_extension']);
$vendor_email = nullable_htmlentities($row['vendor_email']);
$vendor_website = nullable_htmlentities($row['vendor_website']);
$vendor_hours = nullable_htmlentities($row['vendor_hours']);
$vendor_sla = nullable_htmlentities($row['vendor_sla']);
$vendor_code = nullable_htmlentities($row['vendor_code']);
$vendor_notes = nullable_htmlentities($row['vendor_notes']);
$vendor_template_id = intval($row['vendor_template_id']);

// Generate the HTML form content using output buffering.
ob_start();
?>

<div class="modal-header">
    <h5 class="modal-title"><i class="fas fa-fw fa-building mr-2"></i>Editing vendor: <strong><?php echo $vendor_name; ?></strong></h5>
    <button type="button" class="close text-white" data-dismiss="modal">
        <span>&times;</span>
    </button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="vendor_id" value="<?php echo $vendor_id; ?>">
    <div class="modal-body bg-white">

        <ul class="nav nav-pills nav-justified mb-3">
            <li class="nav-item">
                <a class="nav-link active" data-toggle="pill" href="#pills-details<?php echo $vendor_id; ?>">Details</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-toggle="pill" href="#pills-support<?php echo $vendor_id; ?>">Support</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-toggle="pill" href="#pills-notes<?php echo $vendor_id; ?>">Notes</a>
            </li>
        </ul>

        <hr>

        <div class="tab-content">

            <div class="tab-pane fade show active" id="pills-details<?php echo $vendor_id; ?>">

                <div class="form-group">
                    <label>Vendor Name <strong class="text-danger">*</strong></label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-building"></i></span>
                        </div>
                        <input type="text" class="form-control" name="name" placeholder="Vendor Name" maxlength="200" value="<?php echo "$vendor_name"; ?>" required>
                    </div>
                </div>

                <div class="form-group">
                    <label>Description</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-angle-right"></i></span>
                        </div>
                        <input type="text" class="form-control" name="description" placeholder="Description" maxlength="200" value="<?php echo $vendor_description; ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label>Account Number</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-fingerprint"></i></span>
                        </div>
                        <input type="text" class="form-control" name="account_number" placeholder="Account number" maxlength="200" value="<?php echo $vendor_account_number; ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label>Account Manager</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-user"></i></span>
                        </div>
                        <input type="text" class="form-control" name="contact_name" maxlength="200" value="<?php echo $vendor_contact_name; ?>" placeholder="Vendor contact name">
                    </div>
                </div>

                <div class="form-group">
                    <label>Template Base</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-puzzle-piece"></i></span>
                        </div>
                        <select class="form-control select2" name="vendor_template_id">
                            <option value="0">- None -</option>
                            <?php

                            $sql_vendor_templates = mysqli_query($mysqli, "SELECT * FROM vendors WHERE vendor_template = 1 AND vendor_archived_at IS NULL ORDER BY vendor_name ASC");
                            while ($row = mysqli_fetch_array($sql_vendor_templates)) {
                                $vendor_template_id_select = $row['vendor_id'];
                                $vendor_template_name_select = nullable_htmlentities($row['vendor_name']); ?>
                                <option <?php if ($vendor_template_id == $vendor_template_id_select) { echo "selected"; } ?> value="<?php echo $vendor_template_id_select; ?>"><?php echo $vendor_template_name_select; ?></option>

                            <?php } ?>
                        </select>
                    </div>
                </div>

            </div>

            <div class="tab-pane fade" id="pills-support<?php echo $vendor_id; ?>">

                <label>Support Phone / <span class="text-secondary">Extension</span></label>
                <div class="form-row">
                    <div class="col-9">
                        <div class="form-group">
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text"><i class="fa fa-fw fa-phone"></i></span>
                                </div>
                                <input type="tel" class="form-control col-2" name="phone_country_code" value="<?php echo $vendor_phone_country_code; ?>" placeholder="+" maxlength="4">
                                <input type="tel" class="form-control" name="phone" value="<?php echo $vendor_phone; ?>" placeholder="Phone Number" maxlength="200">
                            </div>
                        </div>
                    </div>
                    <div class="col-3">
                        <div class="form-group">
                            <input type="text" class="form-control" name="extension" value="<?php echo $vendor_extension; ?>" placeholder="ext." maxlength="200">
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label>Support Hours</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-calendar"></i></span>
                        </div>
                        <input type="text" class="form-control" name="hours" placeholder="Support Hours" maxlength="200" value="<?php echo $vendor_hours; ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label>Support Email</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-envelope"></i></span>
                        </div>
                        <input type="email" class="form-control" name="email" placeholder="Support Email" maxlength="200" value="<?php echo $vendor_email; ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label>Support Website URL</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-globe"></i></span>
                        </div>
                        <input type="text" class="form-control" name="website" placeholder="Do not include http(s)://" maxlength="200" value="<?php echo $vendor_website; ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label>SLA</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-handshake"></i></span>
                        </div>
                        <input type="text" class="form-control" name="sla" placeholder="SLA Response Time" maxlength="200" value="<?php echo $vendor_sla; ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label>Pin/Code</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-key"></i></span>
                        </div>
                        <input type="text" class="form-control" name="code" placeholder="Access Code or Pin" maxlength="200" value="<?php echo $vendor_code; ?>">
                    </div>
                </div>

            </div>

            <div class="tab-pane fade" id="pills-notes<?php echo $vendor_id; ?>">

                <div class="form-group">
                    <textarea class="form-control" rows="12" placeholder="Enter some notes" name="notes"><?php echo $vendor_notes; ?></textarea>
                </div>

                <p class="text-muted text-right">Vendor ID: <?= $vendor_id ?></p>

            </div>

        </div>

    </div>
    <div class="modal-footer bg-white">
        <button type="submit" name="edit_vendor" class="btn btn-primary text-bold"><i class="fas fa-check mr-2"></i>Save</button>
        <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fa fa-times mr-2"></i>Cancel</button>
    </div>
</form>

<?php

require_once "../includes/ajax_footer.php";
