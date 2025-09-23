<?php

require_once '../../../includes/modal_header.php';

$rack_id = intval($_GET['id']);

$sql = mysqli_query($mysqli, "SELECT * FROM racks WHERE rack_id = $rack_id LIMIT 1");

$row = mysqli_fetch_array($sql);
$rack_name = nullable_htmlentities($row['rack_name']);
$rack_description = nullable_htmlentities($row['rack_description']);
$rack_model = nullable_htmlentities($row['rack_model']);
$rack_depth = nullable_htmlentities($row['rack_depth']);
$rack_type = nullable_htmlentities($row['rack_type']);
$rack_units = intval($row['rack_units']);
$rack_photo = nullable_htmlentities($row['rack_photo']);
$rack_physical_location = nullable_htmlentities($row['rack_physical_location']);
$rack_notes = nullable_htmlentities($row['rack_notes']);
$rack_location_id = nullable_htmlentities($row['rack_location_id']);
$rack_created_at = nullable_htmlentities($row['rack_created_at']);
$client_id = intval($row['rack_client_id']);

// Generate the HTML form content using output buffering.
ob_start();
?>
<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fa fa-fw fa-server mr-2"></i>Editing rack: <strong><?php echo $rack_name; ?></strong></h5>
    <button type="button" class="close text-white" data-dismiss="modal">
        <span>&times;</span>
    </button>
</div>

<form action="post.php" method="post" enctype="multipart/form-data" autocomplete="off">

    <input type="hidden" name="rack_id" value="<?php echo $rack_id; ?>">
    <input type="hidden" name="client_id" value="<?php echo $client_id; ?>">

    <div class="modal-body">

        <ul class="nav nav-pills nav-justified mb-3">
            <li class="nav-item">
                <a class="nav-link active" data-toggle="pill" href="#pills-rack-details<?php echo $rack_id; ?>">Details</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-toggle="pill" href="#pills-rack-notes<?php echo $rack_id; ?>">Notes</a>
            </li>
        </ul>

        <hr>

        <div class="tab-content" <?php if (lookupUserPermission('module_support') <= 1) { echo 'inert'; } ?>>

            <div class="tab-pane fade show active" id="pills-rack-details<?php echo $rack_id; ?>">

                <div class="form-group">
                    <label>Name <strong class="text-danger">*</strong></label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-server"></i></span>
                        </div>
                        <input type="text" class="form-control" name="name" placeholder="Rack name" maxlength="200" value="<?php echo $rack_name; ?>" required autofocus>
                    </div>
                </div>

                <div class="form-group">
                    <label>Description</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-angle-right"></i></span>
                        </div>
                        <input type="text" class="form-control" name="description" placeholder="Description of the rack" value="<?php echo $rack_description; ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label>Type <strong class="text-danger">*</strong></label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-server"></i></span>
                        </div>
                        <select class="form-control select2" name="type" required>
                            <option value="">- Type -</option>
                            <?php foreach($rack_type_select_array as $rack_type_select) { ?>
                                <option <?php if ($rack_type == $rack_type_select) { echo "selected"; } ?>><?php echo $rack_type_select; ?></option>
                            <?php } ?>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label>Model</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-tag"></i></span>
                        </div>
                        <input type="text" class="form-control" name="make" placeholder="ex StarTech 12U Open Frame" maxlength="200" value="<?php echo $rack_model; ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label>Depth</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-ruler"></i></span>
                        </div>
                        <input type="text" class="form-control" name="depth" placeholder="Rack Depth eg 800 mm or 31.5 Inches" maxlength="50" value="<?php echo $rack_depth; ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label>Number of Units <strong class="text-danger">*</strong></label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-sort-numeric-up-alt"></i></span>
                        </div>
                        <input type="number" class="form-control" name="units" placeholder="Number of Units" min="1" max="70" value="<?php echo $rack_units; ?>" required>
                    </div>
                </div>

                <div class="form-group">
                    <label>Physical Location</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-map-marker-alt"></i></span>
                        </div>
                        <input type="text" class="form-control" name="physical_location" placeholder="Physical location eg. Floor 2, Closet B" maxlength="200" value="<?php echo $rack_physical_location; ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label>Location</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-map-marker-alt"></i></span>
                        </div>
                        <select class="form-control select2" name="location">
                            <option value="">- Location -</option>
                            <?php

                            $sql_location_select = mysqli_query($mysqli, "SELECT * FROM locations WHERE location_archived_at IS NULL AND location_client_id = $client_id ORDER BY location_name ASC");
                            while ($row = mysqli_fetch_array($sql_location_select)) {
                                $location_id_select = intval($row['location_id']);
                                $location_name_select = nullable_htmlentities($row['location_name']);
                                ?>
                                <option <?php if ($rack_location_id == $location_id_select) { echo "selected"; } ?> value="<?php echo $location_id_select; ?>"><?php echo $location_name_select; ?></option>
                            <?php } ?>

                        </select>
                    </div>
                </div>

            </div>

            <div class="tab-pane fade" id="pills-rack-notes<?php echo $rack_id; ?>">

                <?php if ($rack_photo) { ?>
                    <img class="img-fluid p-3" alt="rack_photo" src="<?php echo "uploads/clients/$client_id/$rack_photo"; ?>">
                <?php } ?>

                <div class="form-group">
                    <label>Upload Photo</label>
                    <input type="file" class="form-control-file" name="file" accept="image/*">
                </div>

                <div class="form-group">
                    <textarea class="form-control" rows="8" placeholder="Enter some notes" name="notes"><?php echo $rack_notes; ?></textarea>
                </div>

            </div>

        </div>
    </div>
    <div class="modal-footer">
        <button type="submit" name="edit_rack" class="btn btn-primary text-bold"><i class="fa fa-check mr-2"></i>Save</button>
        <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fa fa-times mr-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
