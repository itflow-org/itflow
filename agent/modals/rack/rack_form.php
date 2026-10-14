<?php
/*
 * Rack form fields - shared by rack_add.php and rack_edit.php, which set the
 * $rack_* values (empty for a new rack) and $client_id before including this.
 * Should not be accessed directly.
 */

isset($mysqli) || die("Direct file access is not allowed");

// Rack types come from Admin > Categories. A rack saved with a type that has
// since been renamed or archived keeps it as an option, or the required select
// would force the type to change on every edit.
$rack_type_options = [];
$sql_rack_types_select = mysqli_query($mysqli, "SELECT category_name, category_color, category_icon FROM categories
    WHERE category_type = 'rack_type' AND category_archived_at IS NULL
    ORDER BY category_order ASC, category_name ASC");
while ($row = mysqli_fetch_assoc($sql_rack_types_select)) {
    $rack_type_options[escapeHtml($row['category_name'])] = selectOptionColor($row['category_color'] ?? '', $row['category_icon'] ?? '');
}
if ($rack_type !== '' && !isset($rack_type_options[$rack_type])) {
    $rack_type_options = [$rack_type => ''] + $rack_type_options;
}

?>

<ul class="nav nav-pills nav-justified mb-3">
    <li class="nav-item">
        <a class="nav-link active" data-bs-toggle="pill" href="#rackFormDetails">Details</a>
    </li>
    <li class="nav-item">
        <a class="nav-link" data-bs-toggle="pill" href="#rackFormPhotos">Photos &amp; Notes</a>
    </li>
</ul>

<div class="tab-content">

    <div class="tab-pane fade show active" id="rackFormDetails">

        <div class="row g-3">

            <div class="col-md-7">
                <label class="form-label">Name <strong class="text-danger">*</strong></label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-server"></i></span>
                    <input type="text" class="form-control" name="name" placeholder="MDF-RACK-01" maxlength="200" value="<?= $rack_name ?>" required autofocus>
                </div>
            </div>

            <div class="col-md-5">
                <label class="form-label">Type <strong class="text-danger">*</strong></label>
                <select class="form-select select2" name="type" required>
                    <option value="">- Type -</option>
                    <?php foreach ($rack_type_options as $rack_type_option => $rack_type_option_data) { ?>
                        <option <?php if ($rack_type === $rack_type_option) { echo "selected"; } ?><?= $rack_type_option_data ?>><?= $rack_type_option ?></option>
                    <?php } ?>
                </select>
            </div>

            <div class="col-md-4">
                <label class="form-label">Height <strong class="text-danger">*</strong></label>
                <div class="input-group">
                    <input type="number" class="form-control" name="units" placeholder="42" min="<?= max(1, $rack_highest_used_unit) ?>" max="70" value="<?= $rack_units ?: '' ?>" required>
                    <span class="input-group-text">U</span>
                </div>
                <?php if ($rack_highest_used_unit > 0) { ?>
                    <div class="form-text">In use up to U<?= $rack_highest_used_unit ?></div>
                <?php } ?>
            </div>

            <div class="col-md-8">
                <label class="form-label">Unit numbering</label>
                <select class="form-select" name="units_descending">
                    <option value="0" <?php if (!$rack_units_descending) { echo "selected"; } ?>>U1 at the bottom (standard)</option>
                    <option value="1" <?php if ($rack_units_descending) { echo "selected"; } ?>>U1 at the top</option>
                </select>
            </div>

            <div class="col-md-7">
                <label class="form-label">Model</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-tag"></i></span>
                    <input type="text" class="form-control" name="model" placeholder="APC NetShelter SX 42U" maxlength="200" value="<?= $rack_model ?>">
                </div>
            </div>

            <div class="col-md-5">
                <label class="form-label">Depth</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-ruler"></i></span>
                    <input type="text" class="form-control" name="depth" placeholder="1070 mm" maxlength="50" value="<?= $rack_depth ?>">
                </div>
            </div>

            <div class="col-md-6">
                <label class="form-label">Location</label>
                <select class="form-select select2" name="location">
                    <option value="">- Location -</option>
                    <?php
                    $sql = mysqli_query($mysqli, "SELECT location_id, location_name FROM locations WHERE location_archived_at IS NULL AND location_client_id = $client_id ORDER BY location_name ASC");
                    while ($row = mysqli_fetch_assoc($sql)) {
                        $location_id_select = intval($row['location_id']);
                        $location_name_select = escapeHtml($row['location_name']);
                        ?>
                        <option value="<?= $location_id_select ?>" <?php if ($rack_location_id === $location_id_select) { echo "selected"; } ?>><?= $location_name_select ?></option>
                    <?php } ?>
                </select>
            </div>

            <div class="col-md-6">
                <label class="form-label">Where in the building</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-door-closed"></i></span>
                    <input type="text" class="form-control" name="physical_location" placeholder="2nd floor, comms closet B" maxlength="200" value="<?= $rack_physical_location ?>">
                </div>
            </div>

            <div class="col-md-6">
                <label class="form-label">Power budget</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-bolt"></i></span>
                    <input type="number" class="form-control" name="power_capacity" placeholder="1440" min="0" max="1000000" value="<?= $rack_power_capacity ?: '' ?>">
                    <span class="input-group-text">W</span>
                </div>
                <div class="form-text">What the circuit or UPS feeding this rack can supply. Leave empty to skip power tracking.</div>
            </div>

            <div class="col-md-6">
                <label class="form-label">Description</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-align-left"></i></span>
                    <input type="text" class="form-control" name="description" placeholder="Main comms rack" maxlength="500" value="<?= $rack_description ?>">
                </div>
            </div>

        </div>

    </div>

    <div class="tab-pane fade" id="rackFormPhotos">

        <div class="row g-3 mb-3">
            <?php foreach (['front' => ['file', 'remove_photo', $rack_photo], 'rear' => ['file_rear', 'remove_photo_rear', $rack_photo_rear]] as $photo_side => [$photo_field, $photo_remove_field, $photo_current]) { ?>
                <div class="col-md-6">
                    <label class="form-label"><?= ucfirst($photo_side) ?> photo</label>
                    <?php if ($photo_current) { ?>
                        <div class="itflow-rack-form-photo mb-2">
                            <img src="<?= "../uploads/clients/$client_id/$photo_current" ?>" alt="<?= ucfirst($photo_side) ?> of the rack">
                        </div>
                    <?php } ?>
                    <input type="file" class="form-control" name="<?= $photo_field ?>" accept="image/jpeg,image/png,image/gif,image/webp">
                    <?php if ($photo_current) { ?>
                        <div class="form-check mt-2">
                            <input class="form-check-input" type="checkbox" name="<?= $photo_remove_field ?>" value="1" id="rackForm_<?= $photo_remove_field ?>">
                            <label class="form-check-label" for="rackForm_<?= $photo_remove_field ?>">Remove this photo</label>
                        </div>
                    <?php } ?>
                </div>
            <?php } ?>
        </div>

        <label class="form-label">Notes</label>
        <textarea class="form-control" rows="6" placeholder="Keys, access, who to call at the site, power circuit numbers..." name="notes"><?= $rack_notes ?></textarea>

    </div>

</div>
