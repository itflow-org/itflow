<?php

require_once '../../../includes/modal_header_new.php';

$location_id = intval($_GET['id']);

$sql = mysqli_query($mysqli, "SELECT * FROM locations WHERE location_id = $location_id LIMIT 1");

$row = mysqli_fetch_array($sql);
$location_name = nullable_htmlentities($row['location_name']);
$location_description = nullable_htmlentities($row['location_description']);
$location_country = nullable_htmlentities($row['location_country']);
$location_address = nullable_htmlentities($row['location_address']);
$location_city = nullable_htmlentities($row['location_city']);
$location_state = nullable_htmlentities($row['location_state']);
$location_zip = nullable_htmlentities($row['location_zip']);
$location_phone_country_code = nullable_htmlentities($row['location_phone_country_code']);
$location_phone = nullable_htmlentities(formatPhoneNumber($row['location_phone'], $location_phone_country_code));
//$location_extension = intval($row['location_extension']);
$location_fax_country_code = nullable_htmlentities($row['location_fax_country_code']);
$location_fax = nullable_htmlentities(formatPhoneNumber($row['location_fax'], $location_fax_country_code));
$location_hours = nullable_htmlentities($row['location_hours']);
$location_photo = nullable_htmlentities($row['location_photo']);
$location_notes = nullable_htmlentities($row['location_notes']);
$location_created_at = nullable_htmlentities($row['location_created_at']);
$location_archived_at = nullable_htmlentities($row['location_archived_at']);
$location_contact_id = intval($row['location_contact_id']);
$client_id = intval($row['location_client_id']);
$location_primary = intval($row['location_primary']);

// Tags
$location_tag_id_array = array();
$sql_location_tags = mysqli_query($mysqli, "SELECT * FROM location_tags WHERE location_id = $location_id");
while ($row = mysqli_fetch_array($sql_location_tags)) {
    $location_tag_id = intval($row['tag_id']);
    $location_tag_id_array[] = $location_tag_id;
}

// Generate the HTML form content using output buffering.
ob_start();
?>

<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fa fa-fw fa-map-marker-alt mr-2"></i>Editing location: <strong><?php echo $location_name; ?></strong></h5>
    <button type="button" class="close text-white" data-dismiss="modal">
        <span>&times;</span>
    </button>
</div>
<form action="post.php" method="post" enctype="multipart/form-data" autocomplete="off">
    <input type="hidden" name="location_id" value="<?php echo $location_id; ?>">
    <input type="hidden" name="client_id" value="<?php echo $client_id; ?>">
    
    <div class="modal-body">

        <ul class="nav nav-pills nav-justified mb-3">
            <li class="nav-item">
                <a class="nav-link active" data-toggle="pill" href="#pills-details<?php echo $location_id; ?>">Details</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-toggle="pill" href="#pills-address<?php echo $location_id; ?>">Address</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-toggle="pill" href="#pills-contact<?php echo $location_id; ?>">Contact</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-toggle="pill" href="#pills-notes<?php echo $location_id; ?>">Notes</a>
            </li>
        </ul>

        <hr>

        <div class="tab-content" <?php if (lookupUserPermission('module_client') <= 1) { echo 'inert'; } ?>>

            <div class="tab-pane fade show active" id="pills-details<?php echo $location_id; ?>">

                <div class="form-group">
                    <label>Location Name <strong class="text-danger">*</strong> / <span class="text-secondary">Primary</span></label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-map-marker"></i></span>
                        </div>
                        <input type="text" class="form-control" name="name" placeholder="Name of location" maxlength="200" value="<?php echo $location_name; ?>" required>
                        <div class="input-group-append">
                            <div class="input-group-text">
                                <input type="checkbox" name="location_primary" value="1" <?php if ($location_primary == 1) { echo "checked"; } ?>>
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
                        <input type="text" class="form-control" name="description" placeholder="Short Description" value="<?php echo $location_description; ?>">
                    </div>
                </div>

                <div class="mb-3" style="text-align: center;">
                    <?php if (!empty($location_photo)) { ?>
                        <img class="img-fluid" src="<?php echo "../uploads/clients/$client_id/$location_photo"; ?>">
                    <?php } ?>
                </div>

                <div class="form-group">
                    <label>Photo</label>
                    <input type="file" class="form-control-file" name="file" accept="image/*">
                </div>

            </div>

            <div class="tab-pane fade" id="pills-address<?php echo $location_id; ?>">

                <div class="form-group">
                    <label>Address</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-map-marker-alt"></i></span>
                        </div>
                        <input type="text" class="form-control" name="address" placeholder="Street Address" maxlength="200" value="<?php echo $location_address; ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label>City</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-city"></i></span>
                        </div>
                        <input type="text" class="form-control" name="city" placeholder="City" maxlength="200" value="<?php echo $location_city; ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label>State / Province</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-flag"></i></span>
                        </div>
                        <input type="text" class="form-control" name="state" placeholder="State or Province" maxlength="200" value="<?php echo $location_state; ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label>Postal Code</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fab fa-fw fa-usps"></i></span>
                        </div>
                        <input type="text" class="form-control" name="zip" placeholder="Zip or Postal Code" maxlength="200" value="<?php echo $location_zip; ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label>Country</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-globe-americas"></i></span>
                        </div>
                        <select class="form-control select2" name="country">
                            <option value="">- Country -</option>
                            <?php foreach($countries_array as $country_name) { ?>
                                <option <?php if ($location_country == $country_name) { echo "selected"; } ?>><?php echo $country_name; ?></option>
                            <?php } ?>
                        </select>
                    </div>
                </div>

            </div>

            <div class="tab-pane fade" id="pills-contact<?php echo $location_id; ?>">

                <div class="form-group">
                    <label>Contact</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-user"></i></span>
                        </div>
                        <select class="form-control select2" name="contact">
                            <option value="">- Contact -</option>
                            <?php

                            $sql_contacts = mysqli_query($mysqli, "SELECT * FROM contacts WHERE (contact_archived_at > '$location_created_at' OR contact_archived_at IS NULL) AND contact_client_id = $client_id ORDER BY contact_archived_at ASC, contact_name ASC");
                            while ($row = mysqli_fetch_array($sql_contacts)) {
                                $contact_id_select = intval($row['contact_id']);
                                $contact_name_select = nullable_htmlentities($row['contact_name']);
                                $contact_archived_at = nullable_htmlentities($row['contact_archived_at']);
                                if (empty($contact_archived_at)) {
                                    $contact_archived_display = "";
                                } else {
                                    $contact_archived_display = "Archived - ";
                                }

                                ?>
                                <option <?php if ($location_contact_id == $contact_id_select) { echo "selected"; } ?> value="<?php echo $contact_id_select; ?>"><?php echo "$contact_archived_display$contact_name_select"; ?></option>
                            <?php } ?>

                        </select>
                    </div>
                </div>

                <label>Phone / <span class="text-secondary">Extension</span></label>
                <div class="form-row">
                    <div class="col-9">
                        <div class="form-group">
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text"><i class="fa fa-fw fa-phone"></i></span>
                                </div>
                                <input type="tel" class="form-control col-2" name="phone_country_code" value="<?php echo $location_phone_country_code; ?>" placeholder="+" maxlength="4">
                                <input type="tel" class="form-control" name="phone" value="<?php echo $location_phone; ?>" placeholder="Phone Number" maxlength="200">
                            </div>
                        </div>
                    </div>
                    <div class="col-3">
                        <div class="form-group">
                            <input type="text" class="form-control" name="extension" value="<?php echo $location_extension; ?>" placeholder="ext." maxlength="200">
                        </div>
                    </div>
                </div>

                <label>Fax</label>
                <div class="form-row">
                    <div class="col-9">
                        <div class="form-group">
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text"><i class="fa fa-fw fa-fax"></i></span>
                                </div>
                                <input type="tel" class="form-control col-2" name="fax_country_code" value="<?php echo $location_fax_country_code; ?>" placeholder="+" maxlength="4">
                                <input type="tel" class="form-control" name="fax" value="<?php echo $location_fax; ?>" placeholder="Phone Number" maxlength="200">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label>Hours</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-clock"></i></span>
                        </div>
                        <input type="text" class="form-control" name="hours" placeholder="Hours of operation" maxlength="200" value="<?php echo $location_hours; ?>">
                    </div>
                </div>

            </div>

            <div class="tab-pane fade" id="pills-notes<?php echo $location_id; ?>">

                <div class="form-group">
                    <textarea class="form-control" rows="8" name="notes" placeholder="Notes, eg Parking Info, Building Access etc"><?php echo $location_notes; ?></textarea>
                </div>

                <div class="form-group">
                    <label>Tags</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-tags"></i></span>
                        </div>
                        <select class="form-control select2" name="tags[]" data-placeholder="Add some tags" multiple>
                            <?php

                            $sql_tags_select = mysqli_query($mysqli, "SELECT * FROM tags WHERE tag_type = 2 ORDER BY tag_name ASC");
                            while ($row = mysqli_fetch_array($sql_tags_select)) {
                                $tag_id_select = intval($row['tag_id']);
                                $tag_name_select = nullable_htmlentities($row['tag_name']);
                                ?>
                                <option value="<?php echo $tag_id_select; ?>" <?php if (in_array($tag_id_select, $location_tag_id_array)) { echo "selected"; } ?>><?php echo $tag_name_select; ?></option>
                            <?php } ?>

                        </select>
                        <div class="input-group-append">
                            <button class="btn btn-secondary" type="button"
                                data-toggle="ajax-modal"
                                data-modal-size="sm"
                                data-ajax-url="ajax/ajax_tag_add.php"
                                data-ajax-id="2">
                                <i class="fas fa-plus"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <p class="text-muted text-right">Location ID: <?= $location_id ?></p>

            </div>

        </div>

    </div>
    <div class="modal-footer">
        <button type="submit" name="edit_location" class="btn btn-primary text-bold"><i class="fas fa-check mr-2"></i>Save</button>
        <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fas fa-times mr-2"></i>Cancel</button>
    </div>
</form>

<?php

require_once '../../../includes/modal_footer_new.php';
