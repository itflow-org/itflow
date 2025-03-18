<?php

require_once '../includes/ajax_header.php';

$credential_id = intval($_GET['id']);

$sql = mysqli_query($mysqli, "SELECT * FROM credentials WHERE credential_id = $credential_id LIMIT 1");

$row = mysqli_fetch_array($sql);
$client_id = intval($row['credential_client_id']);
$credential_name = nullable_htmlentities($row['credential_name']);
$credential_description = nullable_htmlentities($row['credential_description']);
$credential_uri = nullable_htmlentities($row['credential_uri']);
$credential_uri_2 = nullable_htmlentities($row['credential_uri_2']);
$credential_username = nullable_htmlentities(decryptCredentialEntry($row['credential_username']));
$credential_password = nullable_htmlentities(decryptCredentialEntry($row['credential_password']));
$credential_otp_secret = nullable_htmlentities($row['credential_otp_secret']);
$credential_note = nullable_htmlentities($row['credential_note']);
$credential_created_at = nullable_htmlentities($row['credential_created_at']);
$credential_archived_at = nullable_htmlentities($row['credential_archived_at']);
$credential_important = intval($row['credential_important']);
$credential_contact_id = intval($row['credential_contact_id']);
$credential_asset_id = intval($row['credential_asset_id']);

// Tags
$credential_tag_id_array = array();
$sql_credential_tags = mysqli_query($mysqli, "SELECT tag_id FROM credential_tags WHERE credential_id = $credential_id");
while ($row = mysqli_fetch_array($sql_credential_tags)) {
    $credential_tag_id = intval($row['tag_id']);
    $credential_tag_id_array[] = $credential_tag_id;
}

// Generate the HTML form content using output buffering.
ob_start();
?>

<div class="modal-header">
    <h5 class="modal-title"><i class='fas fa-fw fa-key mr-2'></i>Editing credential: <strong><?php echo $credential_name; ?></strong></h5>
    <button type="button" class="close text-white" data-dismiss="modal">
        <span>&times;</span>
    </button>
</div>

<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="credential_id" value="<?php echo $credential_id; ?>">
    <input type="hidden" name="client_id" value="<?php echo $client_id; ?>">
    <div class="modal-body bg-white">

        <ul class="nav nav-pills nav-justified mb-3">
            <li class="nav-item">
                <a class="nav-link active" data-toggle="pill" href="#pills-credential-details<?php echo $credential_id; ?>">Details</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-toggle="pill" href="#pills-credential-relation<?php echo $credential_id; ?>">Relation</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-toggle="pill" href="#pills-credential-notes<?php echo $credential_id; ?>">Notes</a>
            </li>
        </ul>

        <hr>

        <div class="tab-content" <?php if (lookupUserPermission('module_credential') <= 1) { echo 'inert'; } ?>>

            <div class="tab-pane fade show active" id="pills-credential-details<?php echo $credential_id; ?>">

                <div class="form-group">
                    <label>Name <strong class="text-danger">*</strong> / <span class="text-secondary">Important?</span></label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-key"></i></span>
                        </div>
                        <input type="text" class="form-control" name="name" placeholder="Name of Credential" maxlength="200" value="<?php echo $credential_name; ?>" required>
                        <div class="input-group-append">
                            <div class="input-group-text">
                                <input type="checkbox" name="important" value="1" <?php if ($credential_important == 1) { echo "checked"; } ?>>
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
                        <input type="text" class="form-control" name="description" placeholder="Description" value="<?php echo $credential_description; ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label>Username / ID</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-user"></i></span>
                        </div>
                        <input type="text" class="form-control" name="username" placeholder="Username or ID" maxlength="350" value="<?php echo $credential_username; ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label>Password / Key <strong class="text-danger">*</strong></label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-lock"></i></span>
                        </div>
                        <input type="password" class="form-control" data-toggle="password" name="password" placeholder="Password or Key" maxlength="350" value="<?php echo $credential_password; ?>" required autocomplete="new-password">
                        <div class="input-group-append">
                            <span class="input-group-text"><i class="fa fa-fw fa-eye"></i></span>
                        </div>
                        <div class="input-group-append">
                            <button class="btn btn-default clipboardjs" type="button" data-clipboard-text="<?php echo $credential_password; ?>"><i class="fa fa-fw fa-copy"></i></button>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label>OTP</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-key"></i></span>
                        </div>
                        <input type="password" class="form-control" data-toggle="password" name="otp_secret" maxlength="200" value="<?php echo $credential_otp_secret; ?>" placeholder="Insert secret key">
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
                        <input type="text" class="form-control" name="uri" placeholder="ex. http://192.168.1.1" maxlength="500" value="<?php echo $credential_uri; ?>">
                        <div class="input-group-append">

                            <a href="<?php echo $credential_uri; ?>" class="input-group-text"><i class="fa fa-fw fa-link"></i></a>
                        </div>
                        <div class="input-group-append">
                            <button class="input-group-text clipboardjs" type="button" data-clipboard-text="<?php echo $credential_uri; ?>"><i class="fa fa-fw fa-copy"></i></button>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label>URI 2</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-link"></i></span>
                        </div>
                        <input type="text" class="form-control" name="uri_2" placeholder="ex. https://server.company.com:5001" maxlength="500" value="<?php echo $credential_uri_2; ?>">
                        <div class="input-group-append">
                            <a href="<?php echo $credential_uri_2; ?>" class="input-group-text"><i class="fa fa-fw fa-link"></i></a>
                        </div>
                        <div class="input-group-append">
                            <button class="input-group-text clipboardjs" type="button" data-clipboard-text="<?php echo $credential_uri_2; ?>"><i class="fa fa-fw fa-copy"></i></button>
                        </div>
                    </div>
                </div>

            </div>

            <div class="tab-pane fade" id="pills-credential-relation<?php echo $credential_id; ?>">

                <div class="form-group">
                    <label>Contact</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-user"></i></span>
                        </div>
                        <select class="form-control select2" name="contact">
                            <option value="">- Select Contact -</option>
                            <?php

                            $sql_contacts = mysqli_query($mysqli, "SELECT contact_id, contact_name FROM contacts WHERE contact_client_id = $client_id ORDER BY contact_name ASC");
                            while ($row = mysqli_fetch_array($sql_contacts)) {
                                $contact_id_select = intval($row['contact_id']);
                                $contact_name_select = nullable_htmlentities($row['contact_name']);
                                ?>
                                <option <?php if ($credential_contact_id == $contact_id_select) { echo "selected"; } ?> value="<?php echo $contact_id_select; ?>"><?php echo $contact_name_select; ?></option>
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
                            <option value="0">- Select Asset -</option>
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
                                <option <?php if ($credential_asset_id == $asset_id_select) { echo "selected"; } ?> value="<?php echo $asset_id_select; ?>"><?php echo $asset_select_display_string; ?></option>

                            <?php } ?>
                        </select>
                    </div>
                </div>

            </div>

            <div class="tab-pane fade" id="pills-credential-notes<?php echo $credential_id; ?>">

                <div class="form-group">
                    <textarea class="form-control" rows="12" placeholder="Enter some notes" name="note"><?php echo $credential_note; ?></textarea>
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
                                <option value="<?php echo $tag_id_select; ?>" <?php if (in_array($tag_id_select, $credential_tag_id_array)) { echo "selected"; } ?>><?php echo $tag_name_select; ?></option>
                            <?php } ?>

                        </select>
                        <div class="input-group-append">
                            <button class="btn btn-secondary" type="button"
                                data-toggle="ajax-modal"
                                data-modal-size="sm"
                                data-ajax-url="ajax/ajax_tag_add.php"
                                data-ajax-id="4">
                                <i class="fas fa-plus"></i>
                            </button>
                        </div>
                    </div>
                </div>

            </div>

        </div>
    </div>
    <div class="modal-footer bg-white">
        <button type="submit" name="edit_credential" class="btn btn-primary text-bold"><i class="fa fa-check mr-2"></i>Save</button>
        <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fa fa-times mr-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once "../includes/ajax_footer.php";
