<?php

require_once '../../../includes/modal_header.php';

enforceUserPermission('module_client', 2);

$location_id = intval($_GET['id']);

$sql = mysqli_query($mysqli, "SELECT location_address, location_archived_at, location_city, location_client_id,
    location_contact_id, location_country, location_created_at, location_description,
    location_fax, location_fax_country_code, location_hours, location_name, location_notes,
    location_phone, location_phone_country_code, location_phone_extension, location_photo, location_primary,
    location_state, location_zip FROM locations WHERE location_id = $location_id LIMIT 1");

$row = mysqli_fetch_assoc($sql);
$location_name = escapeHtml($row['location_name']);
$location_description = escapeHtml($row['location_description']);
$location_country = escapeHtml($row['location_country']);
$location_address = escapeHtml($row['location_address']);
$location_city = escapeHtml($row['location_city']);
$location_state = escapeHtml($row['location_state']);
$location_zip = escapeHtml($row['location_zip']);
$location_phone_country_code = escapeHtml($row['location_phone_country_code']);
$location_phone = escapeHtml(formatPhoneNumber($row['location_phone'], $location_phone_country_code));
$location_extension = escapeHtml($row['location_phone_extension']);
$location_fax_country_code = escapeHtml($row['location_fax_country_code']);
$location_fax = escapeHtml(formatPhoneNumber($row['location_fax'], $location_fax_country_code));
$location_hours = escapeHtml($row['location_hours']);
$location_photo = escapeHtml($row['location_photo']);
$location_notes = escapeHtml($row['location_notes']);
$location_created_at = escapeHtml($row['location_created_at']);
$location_archived_at = escapeHtml($row['location_archived_at']);
$location_contact_id = intval($row['location_contact_id']);
$client_id = intval($row['location_client_id']);
$location_primary = intval($row['location_primary']);

enforceClientAccess();

// Tags
$location_tag_id_array = array();
$sql_location_tags = mysqli_query($mysqli, "SELECT tag_id FROM location_tags WHERE location_id = $location_id");
while ($row = mysqli_fetch_assoc($sql_location_tags)) {
    $location_tag_id = intval($row['tag_id']);
    $location_tag_id_array[] = $location_tag_id;
}

// Generate the HTML form content using output buffering.
ob_start();
?>

<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fa fa-fw fa-map-marker-alt me-2"></i>Editing location: <strong><?= $location_name ?></strong></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" enctype="multipart/form-data" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="location_id" value="<?= $location_id ?>">

    <div class="modal-body">

        <ul class="nav nav-pills nav-justified mb-3">
            <li class="nav-item">
                <a class="nav-link active" data-bs-toggle="pill" href="#pills-details<?= $location_id ?>">Details</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-bs-toggle="pill" href="#pills-address<?= $location_id ?>">Address</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-bs-toggle="pill" href="#pills-contact<?= $location_id ?>">Contact</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-bs-toggle="pill" href="#pills-notes<?= $location_id ?>">Notes</a>
            </li>
        </ul>

        <hr>

        <div class="tab-content" <?php if (lookupUserPermission('module_client') <= 1) { echo 'inert'; } ?>>

            <div class="tab-pane fade show active" id="pills-details<?= $location_id ?>">

                <div class="mb-3">
                    <label>Location Name <strong class="text-danger">*</strong> / <span class="text-secondary">Primary</span></label>
                    <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-map-marker"></i></span>
                        <input type="text" class="form-control" name="name" placeholder="Name of location" maxlength="200" value="<?= $location_name ?>" required>
                            <div class="input-group-text">
                                <input type="checkbox" name="location_primary" value="1" <?php if ($location_primary == 1) { echo "checked"; } ?>>
                            </div>
                    </div>
                </div>

                <div class="mb-3">
                    <label>Description</label>
                    <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-align-left"></i></span>
                        <input type="text" class="form-control" name="description" placeholder="Short Description" value="<?= $location_description ?>">
                    </div>
                </div>

                <div class="mb-3" style="text-align: center;">
                    <?php if (!empty($location_photo)) { ?>
                        <img class="img-fluid" src="<?= "../uploads/clients/$client_id/$location_photo" ?>">
                    <?php } ?>
                </div>

                <div class="mb-3">
                    <label>Photo</label>
                    <input type="file" class="form-control" name="file" accept="image/*">
                </div>

            </div>

            <div class="tab-pane fade" id="pills-address<?= $location_id ?>">

                <div class="mb-3">
                    <label>Address</label>
                    <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-map-marker-alt"></i></span>
                        <input type="text" class="form-control" name="address" placeholder="Street Address" maxlength="200" value="<?= $location_address ?>">
                    </div>
                </div>

                <div class="mb-3">
                    <label>City</label>
                    <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-city"></i></span>
                        <input type="text" class="form-control" name="city" placeholder="City" maxlength="200" value="<?= $location_city ?>">
                    </div>
                </div>

                <div class="mb-3">
                    <label>State / Province</label>
                    <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-flag"></i></span>
                        <input type="text" class="form-control" name="state" placeholder="State or Province" maxlength="200" value="<?= $location_state ?>">
                    </div>
                </div>

                <div class="mb-3">
                    <label>Postal Code</label>
                    <div class="input-group">
                            <span class="input-group-text"><i class="fab fa-fw fa-usps"></i></span>
                        <input type="text" class="form-control" name="zip" placeholder="Zip or Postal Code" maxlength="200" value="<?= $location_zip ?>">
                    </div>
                </div>

                <div class="mb-3">
                    <label>Country</label>
                    <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-globe-americas"></i></span>
                        <select class="form-control select2" name="country">
                            <option value="">- Country -</option>
                            <?php foreach($countries_array as $country_name) { ?>
                                <option <?php if ($location_country == $country_name) { echo "selected"; } ?>><?= $country_name ?></option>
                            <?php } ?>
                        </select>
                    </div>
                </div>

            </div>

            <div class="tab-pane fade" id="pills-contact<?= $location_id ?>">

                <div class="mb-3">
                    <label>Contact</label>
                    <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-user"></i></span>
                        <select class="form-control select2" name="contact">
                            <option value="">- Contact -</option>
                            <?php

                            $sql_contacts = mysqli_query($mysqli, "SELECT contact_archived_at, contact_id, contact_name FROM contacts WHERE (contact_archived_at > '$location_created_at' OR contact_archived_at IS NULL) AND contact_client_id = $client_id ORDER BY contact_archived_at ASC, contact_name ASC");
                            while ($row = mysqli_fetch_assoc($sql_contacts)) {
                                $contact_id_select = intval($row['contact_id']);
                                $contact_name_select = escapeHtml($row['contact_name']);
                                $contact_archived_at = escapeHtml($row['contact_archived_at']);
                                if (empty($contact_archived_at)) {
                                    $contact_archived_display = "";
                                } else {
                                    $contact_archived_display = "Archived - ";
                                }

                                ?>
                                <option <?php if ($location_contact_id == $contact_id_select) { echo "selected"; } ?> value="<?= $contact_id_select ?>"><?= "$contact_archived_display$contact_name_select" ?></option>
                            <?php } ?>

                        </select>
                    </div>
                </div>

                <label>Phone / <span class="text-secondary">Extension</span></label>
                <div class="row g-2">
                    <div class="col-9">
                        <div class="mb-3">
                            <div class="input-group">
                                    <span class="input-group-text"><i class="fa fa-fw fa-phone"></i></span>
                                <input type="tel" class="form-control col-2" name="phone_country_code" value="<?= $location_phone_country_code ?>" placeholder="+" maxlength="4">
                                <input type="tel" class="form-control" name="phone" value="<?= $location_phone ?>" placeholder="Phone Number" maxlength="200">
                            </div>
                        </div>
                    </div>
                    <div class="col-3">
                        <div class="mb-3">
                            <input type="text" class="form-control" name="extension" value="<?= $location_extension ?>" placeholder="ext." maxlength="200">
                        </div>
                    </div>
                </div>

                <label>Fax</label>
                <div class="row g-2">
                    <div class="col-9">
                        <div class="mb-3">
                            <div class="input-group">
                                    <span class="input-group-text"><i class="fa fa-fw fa-fax"></i></span>
                                <input type="tel" class="form-control col-2" name="fax_country_code" value="<?= $location_fax_country_code ?>" placeholder="+" maxlength="4">
                                <input type="tel" class="form-control" name="fax" value="<?= $location_fax ?>" placeholder="Phone Number" maxlength="200">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mb-3">
                    <label>Hours</label>
                    <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-clock"></i></span>
                        <input type="text" class="form-control" name="hours" placeholder="Hours of operation" maxlength="200" value="<?= $location_hours ?>">
                    </div>
                </div>

            </div>

            <div class="tab-pane fade" id="pills-notes<?= $location_id ?>">

                <div class="mb-3">
                    <textarea class="form-control" rows="8" name="notes" placeholder="Notes, eg Parking Info, Building Access etc"><?= $location_notes ?></textarea>
                </div>

                <div class="mb-3">
                    <label>Tags</label>
                    <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-tags"></i></span>
                        <select class="form-control select2" name="tags[]" data-placeholder="Add some tags" multiple>
                            <?php

                            $sql_tags_select = mysqli_query($mysqli, "SELECT tag_id, tag_name FROM tags WHERE tag_type = 2 ORDER BY tag_name ASC");
                            while ($row = mysqli_fetch_assoc($sql_tags_select)) {
                                $tag_id_select = intval($row['tag_id']);
                                $tag_name_select = escapeHtml($row['tag_name']);
                                ?>
                                <option value="<?= $tag_id_select ?>" <?php if (in_array($tag_id_select, $location_tag_id_array)) { echo "selected"; } ?>><?= $tag_name_select ?></option>
                            <?php } ?>

                        </select>
                            <button class="btn btn-secondary ajax-modal" type="button"
                                data-modal-url="../admin/modals/tag/tag_add.php?type=2">
                                <i class="fas fa-plus"></i>
                            </button>
                    </div>
                </div>

                <p class="text-muted text-end">Location ID: <?= $location_id ?></p>

            </div>

        </div>

    </div>
    <div class="modal-footer">
        <button type="submit" name="edit_location" class="btn btn-primary text-bold"><i class="fas fa-check me-2"></i>Save</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fas fa-times me-2"></i>Cancel</button>
    </div>
</form>

<?php

require_once '../../../includes/modal_footer.php';
