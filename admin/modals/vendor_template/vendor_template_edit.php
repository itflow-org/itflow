<?php

require_once '../../../includes/modal_header.php';

$vendor_template_id = intval($_GET['id']);

$sql = mysqli_query($mysqli, "SELECT * FROM vendor_templates WHERE vendor_template_id = $vendor_template_id LIMIT 1");
$row = mysqli_fetch_array($sql);
$vendor_name = nullable_htmlentities($row['vendor_template_name']);
$vendor_description = nullable_htmlentities($row['vendor_template_description']);
$vendor_account_number = nullable_htmlentities($row['vendor_template_account_number']);
$vendor_contact_name = nullable_htmlentities($row['vendor_template_contact_name']);
$vendor_phone_country_code = intval($row['vendor_template_phone_country_code']);
$vendor_phone = formatPhoneNumber($row['vendor_template_phone'], $vendor_phone_country_code);
$vendor_extension = nullable_htmlentities($row['vendor_template_extension']);
$vendor_email = nullable_htmlentities($row['vendor_template_email']);
$vendor_website = nullable_htmlentities($row['vendor_template_website']);
$vendor_hours = nullable_htmlentities($row['vendor_template_hours']);
$vendor_sla = nullable_htmlentities($row['vendor_template_sla']);
$vendor_code = nullable_htmlentities($row['vendor_template_code']);
$vendor_notes = nullable_htmlentities($row['vendor_template_notes']);

// Generate the HTML form content using output buffering.
ob_start();
?>

<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fas fa-fw fa-building mr-2"></i>Editing vendor template: <strong><?php echo $vendor_name; ?></strong></h5>
    <button type="button" class="close text-white" data-dismiss="modal">
        <span>&times;</span>
    </button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="vendor_template_id" value="<?php echo $vendor_template_id; ?>">
    <div class="modal-body">

        <ul class="nav nav-pills nav-justified mb-3">
            <li class="nav-item">
                <a class="nav-link active" data-toggle="pill" href="#pills-details<?php echo $vendor_template_id; ?>">Details</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-toggle="pill" href="#pills-support<?php echo $vendor_template_id; ?>">Support</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-toggle="pill" href="#pills-notes<?php echo $vendor_template_id; ?>">Notes</a>
            </li>
        </ul>

        <hr>

        <div class="alert alert-info">Check the fields you would like to update globally</div>

        <div class="tab-content">

            <div class="tab-pane fade show active" id="pills-details<?php echo $vendor_template_id; ?>">


                <div class="form-group">
                    <label>Vendor Name <strong class="text-danger">*</strong></label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-building"></i></span>
                        </div>
                        <input type="text" class="form-control" name="name" placeholder="Vendor Name" maxlength="200" value="<?php echo "$vendor_name"; ?>" required>
                        <div class="input-group-append">
                            <div class="input-group-text">
                                <input type="checkbox" name="global_update_vendor_name" value="1">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label>Description</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-angle-right"></i></span>
                        </div>
                        <input type="text" class="form-control" name="description" placeholder="Description" maxlength="200" value="<?php echo $vendor_description; ?>">
                        <div class="input-group-append">
                            <div class="input-group-text">
                                <input type="checkbox" name="global_update_vendor_description" value="1">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label>Account Number</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-fingerprint"></i></span>
                        </div>
                        <input type="text" class="form-control" name="account_number" placeholder="Account number" maxlength="200" value="<?php echo $vendor_account_number; ?>">
                        <div class="input-group-append">
                            <div class="input-group-text">
                                <input type="checkbox" name="global_update_vendor_account_number" value="1">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label>Account Manager</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-user"></i></span>
                        </div>
                        <input type="text" class="form-control" name="contact_name" maxlength="200" value="<?php echo $vendor_contact_name; ?>" placeholder="Vendor contact name">
                        <div class="input-group-append">
                            <div class="input-group-text">
                                <input type="checkbox" name="global_update_vendor_contact_name" value="1">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <div class="custom-control custom-checkbox">
                        <input type="checkbox" class="custom-control-input" id="updateVendorsCheckbox<?php echo $vendor_template_id; ?>" name="update_base_vendors" value="1" >
                        <label class="custom-control-label" for="updateVendorsCheckbox<?php echo $vendor_template_id; ?>">Update All Base Vendors</label>
                    </div>
                </div>

            </div>

            <div class="tab-pane fade" id="pills-support<?php echo $vendor_template_id; ?>">

                <label>Support Phone</label>
                <div class="form-row">
                    <div class="col-8">
                        <div class="form-group">
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text"><i class="fa fa-fw fa-phone"></i></span>
                                </div>
                                <input type="tel" class="form-control col-2" name="phone_country_code" placeholder="+" maxlength="4" value="<?php echo $vendor_phone_country_code; ?>">
                                <input type="tel" class="form-control" name="phone" value="<?php echo $vendor_phone; ?>">
                            </div>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="input-group">
                            <input type="text" class="form-control" name="extension" placeholder="Prompts" maxlength="200" value="<?php echo $vendor_extension; ?>">
                            <div class="input-group-append">
                                <div class="input-group-text">
                                    <input type="checkbox" name="global_update_vendor_phone" value="1">
                                </div>
                            </div>
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
                        <div class="input-group-append">
                            <div class="input-group-text">
                                <input type="checkbox" name="global_update_vendor_hours" value="1">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label>Support Email</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-envelope"></i></span>
                        </div>
                        <input type="email" class="form-control" name="email" placeholder="Support Email" maxlength="200" value="<?php echo $vendor_email; ?>">
                        <div class="input-group-append">
                            <div class="input-group-text">
                                <input type="checkbox" name="global_update_vendor_email" value="1">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label>Support Website URL</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-globe"></i></span>
                        </div>
                        <input type="text" class="form-control" name="website" placeholder="Do not include http(s)://" maxlength="200" value="<?php echo $vendor_website; ?>">
                        <div class="input-group-append">
                            <div class="input-group-text">
                                <input type="checkbox" name="global_update_vendor_website" value="1">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label>SLA</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-handshake"></i></span>
                        </div>
                        <input type="text" class="form-control" name="sla" placeholder="SLA Response Time" maxlength="200" value="<?php echo $vendor_sla; ?>">
                        <div class="input-group-append">
                            <div class="input-group-text">
                                <input type="checkbox" name="global_update_vendor_sla" value="1">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label>Pin/Code</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-key"></i></span>
                        </div>
                        <input type="text" class="form-control" name="code" placeholder="Access Code or Pin" maxlength="200" value="<?php echo $vendor_code; ?>">
                        <div class="input-group-append">
                            <div class="input-group-text">
                                <input type="checkbox" name="global_update_vendor_code" value="1">
                            </div>
                        </div>
                    </div>
                </div>

            </div>

            <div class="tab-pane fade" id="pills-notes<?php echo $vendor_template_id; ?>">

                <div class="form-group">
                    <textarea class="form-control" rows="8" placeholder="Enter some notes" name="notes"><?php echo $vendor_notes; ?></textarea>
                </div>
                
                <div class="form-group">
                    <label>Update Notes Globally?</label>
                    <input type="checkbox" name="global_update_vendor_notes" value="1">
                </div>

            </div>

        </div>

    </div>
    <div class="modal-footer">
        <button type="submit" class="btn btn-primary text-bold" name="edit_vendor_template"><i class="fa fa-check mr-2"></i>Update Template</button>
        <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fa fa-times mr-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
