<?php

require_once '../includes/ajax_header.php';

$login_id = intval($_GET['id']);

$sql = mysqli_query($mysqli, "SELECT * FROM logins WHERE login_id = $login_id LIMIT 1");

$row = mysqli_fetch_array($sql);
$client_id = intval($row['login_client_id']);
$login_id = intval($row['login_id']);
$login_name = nullable_htmlentities($row['login_name']);
$login_description = nullable_htmlentities($row['login_description']);
$login_uri = nullable_htmlentities($row['login_uri']);
$login_uri_2 = nullable_htmlentities($row['login_uri_2']);
$login_username = nullable_htmlentities(decryptLoginEntry($row['login_username']));
$login_password = nullable_htmlentities(decryptLoginEntry($row['login_password']));
$login_otp_secret = nullable_htmlentities($row['login_otp_secret']);
$login_note = nullable_htmlentities($row['login_note']);
$login_created_at = nullable_htmlentities($row['login_created_at']);
$login_archived_at = nullable_htmlentities($row['login_archived_at']);
$login_important = intval($row['login_important']);
$login_contact_id = intval($row['login_contact_id']);
$login_vendor_id = intval($row['login_vendor_id']);
$login_asset_id = intval($row['login_asset_id']);
$login_software_id = intval($row['login_software_id']);

// Tags
$login_tag_id_array = array();
$sql_login_tags = mysqli_query($mysqli, "SELECT tag_id FROM login_tags WHERE login_id = $login_id");
while ($row = mysqli_fetch_array($sql_login_tags)) {
    $login_tag_id = intval($row['tag_id']);
    $login_tag_id_array[] = $login_tag_id;
}

// Generate the HTML form content using output buffering.
ob_start();
?>

<div class="modal-header">
    <h5 class="modal-title"><i class='fas fa-fw fa-key mr-2'></i>Editing credential: <strong><?php echo $login_name; ?></strong></h5>
    <button type="button" class="close text-white" data-dismiss="modal">
        <span>&times;</span>
    </button>
</div>

<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="login_id" value="<?php echo $login_id; ?>">
    <input type="hidden" name="client_id" value="<?php echo $client_id; ?>">
    <div class="modal-body bg-white">

        <ul class="nav nav-pills nav-justified mb-3">
            <li class="nav-item">
                <a class="nav-link active" data-toggle="pill" href="#pills-login-details<?php echo $login_id; ?>">Details</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-toggle="pill" href="#pills-login-relation<?php echo $login_id; ?>">Relation</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-toggle="pill" href="#pills-login-notes<?php echo $login_id; ?>">Notes</a>
            </li>
        </ul>

        <hr>

        <div class="tab-content" <?php if (lookupUserPermission('module_credential') <= 1) { echo 'inert'; } ?>>

            <div class="tab-pane fade show active" id="pills-login-details<?php echo $login_id; ?>">

                <div class="form-group">
                    <label>Name <strong class="text-danger">*</strong> / <span class="text-secondary">Important?</span></label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-key"></i></span>
                        </div>
                        <input type="text" class="form-control" name="name" placeholder="Name of Login" maxlength="200" value="<?php echo $login_name; ?>" required>
                        <div class="input-group-append">
                            <div class="input-group-text">
                                <input type="checkbox" name="important" value="1" <?php if ($login_important == 1) { echo "checked"; } ?>>
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
                        <input type="text" class="form-control" name="description" placeholder="Description" value="<?php echo $login_description; ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label>Username / ID</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-user"></i></span>
                        </div>
                        <input type="text" class="form-control" name="username" placeholder="Username or ID" maxlength="350" value="<?php echo $login_username; ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label>Password / Key <strong class="text-danger">*</strong></label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-lock"></i></span>
                        </div>
                        <input type="password" class="form-control" data-toggle="password" name="password" placeholder="Password or Key" maxlength="350" value="<?php echo $login_password; ?>" required autocomplete="new-password">
                        <div class="input-group-append">
                            <span class="input-group-text"><i class="fa fa-fw fa-eye"></i></span>
                        </div>
                        <div class="input-group-append">
                            <button class="btn btn-default clipboardjs" type="button" data-clipboard-text="<?php echo $login_password; ?>"><i class="fa fa-fw fa-copy"></i></button>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label>OTP</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-key"></i></span>
                        </div>
                        <input type="password" class="form-control" data-toggle="password" name="otp_secret" maxlength="200" value="<?php echo $login_otp_secret; ?>" placeholder="Insert secret key">
                        <div class="input-group-append">
                            <span class="input-group-text"><i class="fa fa-fw fa-eye"></i></span>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label>URI</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-link"></i></span>
                        </div>
                        <input type="text" class="form-control" name="uri" placeholder="ex. http://192.168.1.1" maxlength="500" value="<?php echo $login_uri; ?>">
                        <div class="input-group-append">

                            <a href="<?php echo $login_uri; ?>" class="input-group-text"><i class="fa fa-fw fa-link"></i></a>
                        </div>
                        <div class="input-group-append">
                            <button class="input-group-text clipboardjs" type="button" data-clipboard-text="<?php echo $login_uri; ?>"><i class="fa fa-fw fa-copy"></i></button>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label>URI 2</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-link"></i></span>
                        </div>
                        <input type="text" class="form-control" name="uri_2" placeholder="ex. https://server.company.com:5001" maxlength="500" value="<?php echo $login_uri_2; ?>">
                        <div class="input-group-append">
                            <a href="<?php echo $login_uri_2; ?>" class="input-group-text"><i class="fa fa-fw fa-link"></i></a>
                        </div>
                        <div class="input-group-append">
                            <button class="input-group-text clipboardjs" type="button" data-clipboard-text="<?php echo $login_uri_2; ?>"><i class="fa fa-fw fa-copy"></i></button>
                        </div>
                    </div>
                </div>

            </div>

            <div class="tab-pane fade" id="pills-login-relation<?php echo $login_id; ?>">

                <div class="form-group">
                    <label>Contact</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-user"></i></span>
                        </div>
                        <select class="form-control select2" name="contact">
                            <option value="">- Contact -</option>
                            <?php

                            $sql_contacts = mysqli_query($mysqli, "SELECT contact_id, contact_name FROM contacts WHERE contact_client_id = $client_id ORDER BY contact_name ASC");
                            while ($row = mysqli_fetch_array($sql_contacts)) {
                                $contact_id_select = intval($row['contact_id']);
                                $contact_name_select = nullable_htmlentities($row['contact_name']);
                                ?>
                                <option <?php if ($login_contact_id == $contact_id_select) { echo "selected"; } ?> value="<?php echo $contact_id_select; ?>"><?php echo $contact_name_select; ?></option>
                            <?php } ?>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label>Vendor</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-building"></i></span>
                        </div>
                        <select class="form-control select2" name="vendor">
                            <option value="0">- None -</option>
                            <?php

                            $sql_vendors = mysqli_query($mysqli, "SELECT vendor_id, vendor_name FROM vendors WHERE vendor_client_id = $client_id ORDER BY vendor_name ASC");
                            while ($row = mysqli_fetch_array($sql_vendors)) {
                                $vendor_id_select = intval($row['vendor_id']);
                                $vendor_name_select = nullable_htmlentities($row['vendor_name']);
                                ?>
                                <option <?php if ($login_vendor_id == $vendor_id_select) { echo "selected"; } ?> value="<?php echo $vendor_id_select; ?>"><?php echo $vendor_name_select; ?></option>
                            <?php } ?>

                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label>Asset</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-tag"></i></span>
                        </div>
                        <select class="form-control select2" name="asset">
                            <option value="0">- None -</option>
                            <?php

                            $sql_assets = mysqli_query($mysqli, "SELECT asset_id, asset_name, location_name  FROM assets LEFT JOIN locations on asset_location_id = location_id WHERE asset_client_id = $client_id AND asset_archived_at IS NULL ORDER BY asset_name ASC");
                            while ($row = mysqli_fetch_array($sql_assets)) {
                                $asset_id_select = intval($row['asset_id']);
                                $asset_name_select = nullable_htmlentities($row['asset_name']);
                                $asset_location_select = nullable_htmlentities($row['location_name']);

                                $asset_select_display_string = $asset_name_select;
                                if (!empty($asset_location_select)) {
                                    $asset_select_display_string = "$asset_name_select ($asset_location_select)";
                                }

                                ?>
                                <option <?php if ($login_asset_id == $asset_id_select) { echo "selected"; } ?> value="<?php echo $asset_id_select; ?>"><?php echo $asset_select_display_string; ?></option>

                            <?php } ?>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label>Software</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-box"></i></span>
                        </div>
                        <select class="form-control select2" name="software">
                            <option value="0">- None -</option>
                            <?php

                            $sql_software = mysqli_query($mysqli, "SELECT software_id, software_name FROM software WHERE software_client_id = $client_id ORDER BY software_name ASC");
                            while ($row = mysqli_fetch_array($sql_software)) {
                                $software_id_select = intval($row['software_id']);
                                $software_name_select = nullable_htmlentities($row['software_name']);
                                ?>
                                <option <?php if ($login_software_id == $software_id_select) { echo "selected"; } ?> value="<?php echo $software_id_select; ?>"><?php echo $software_name_select; ?></option>

                            <?php } ?>
                        </select>
                    </div>
                </div>

            </div>

            <div class="tab-pane fade" id="pills-login-notes<?php echo $login_id; ?>">

                <div class="form-group">
                    <textarea class="form-control" rows="12" placeholder="Enter some notes" name="note"><?php echo $login_note; ?></textarea>
                </div>

                <div class="form-group">
                    <label>Tags</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-tags"></i></span>
                        </div>
                        <select class="form-control select2" name="tags[]" data-placeholder="Add some tags" multiple>
                            <?php

                            $sql_tags_select = mysqli_query($mysqli, "SELECT tag_id, tag_name FROM tags WHERE tag_type = 4 ORDER BY tag_name ASC");
                            while ($row = mysqli_fetch_array($sql_tags_select)) {
                                $tag_id_select = intval($row['tag_id']);
                                $tag_name_select = nullable_htmlentities($row['tag_name']);
                                ?>
                                <option value="<?php echo $tag_id_select; ?>" <?php if (in_array($tag_id_select, $login_tag_id_array)) { echo "selected"; } ?>><?php echo $tag_name_select; ?></option>
                            <?php } ?>

                        </select>
                    </div>
                </div>

            </div>

        </div>
    </div>
    <div class="modal-footer bg-white">
        <button type="submit" name="edit_login" class="btn btn-primary text-bold"><i class="fa fa-check mr-2"></i>Save</button>
        <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fa fa-times mr-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once "../includes/ajax_footer.php";
