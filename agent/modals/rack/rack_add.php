<?php

require_once '../../../includes/modal_header.php';

$client_id = intval($_GET['client_id'] ?? 0);

ob_start();

?>
<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fa fa-fw fa-server me-2"></i>New Rack</h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>

<form action="post.php" method="post" enctype="multipart/form-data" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="client_id" value="<?= $client_id ?>">

    <div class="modal-body">

        <ul class="nav nav-pills nav-justified mb-3">
            <li class="nav-item">
                <a class="nav-link active" data-bs-toggle="pill" href="#pills-details">Details</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-bs-toggle="pill" href="#pills-notes">Notes</a>
            </li>
        </ul>

        <hr>

        <div class="tab-content">

            <div class="tab-pane fade show active" id="pills-details">

                <div class="mb-3">
                    <label>Type <strong class="text-danger">*</strong></label>
                    <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-server"></i></span>
                        <select class="form-control select2" name="type" required>
                            <option value="">- Type -</option>
                            <?php
                            $sql_rack_types_select = mysqli_query($mysqli, "
                                SELECT category_name FROM categories
                                WHERE category_type = 'rack_type'
                                AND category_archived_at IS NULL
                                ORDER BY category_order ASC, category_name ASC
                            ");
                            while ($row = mysqli_fetch_assoc($sql_rack_types_select)) {
                                $rack_type_select = escapeHtml($row['category_name']);
                                ?>
                                <option><?= $rack_type_select ?></option>
                            <?php } ?>
                        </select>
                    </div>
                </div>

                <div class="mb-3">
                    <label>Name <strong class="text-danger">*</strong></label>
                    <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-server"></i></span>
                        <input type="text" class="form-control" name="name" placeholder="Rack name" maxlength="200" required autofocus>
                    </div>
                </div>

                <div class="mb-3">
                    <label>Number of Units <strong class="text-danger">*</strong></label>
                    <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-sort-numeric-up-alt"></i></span>
                        <input type="number" class="form-control" name="units" placeholder="Number of Units" min="1" max="70" required>
                    </div>
                </div>

                <div class="mb-3">
                    <label>Model</label>
                    <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-tag"></i></span>
                        <input type="text" class="form-control" name="make" placeholder="ex StarTech 12U Open Frame" maxlength="200">
                    </div>
                </div>

                <div class="mb-3">
                    <label>Depth</label>
                    <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-ruler"></i></span>
                        <input type="text" class="form-control" name="depth" placeholder="Rack Depth eg 800 mm or 31.5 Inches" maxlength="50">
                    </div>
                </div>

                <div class="mb-3">
                    <label>Location</label>
                    <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-map-marker-alt"></i></span>
                        <select class="form-control select2" name="location">
                            <option value="">- Location -</option>
                            <?php

                            $sql = mysqli_query($mysqli, "SELECT location_id, location_name FROM locations WHERE location_archived_at IS NULL AND location_client_id = $client_id ORDER BY location_name ASC");
                            while ($row = mysqli_fetch_assoc($sql)) {
                                $location_id = intval($row['location_id']);
                                $location_name = escapeHtml($row['location_name']);
                                ?>
                                <option value="<?= $location_id ?>"><?= $location_name ?></option>
                            <?php } ?>

                        </select>
                    </div>
                </div>

                <div class="mb-3">
                    <label>Physical Location</label>
                    <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-map-marker-alt"></i></span>
                        <input type="text" class="form-control" name="physical_location" placeholder="Physical location eg. Floor 2, Closet B" maxlength="200">
                    </div>
                </div>

                <div class="mb-3">
                    <label>Description</label>
                    <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-align-left"></i></span>
                        <input type="text" class="form-control" name="description" placeholder="Description of the rack">
                    </div>
                </div>

            </div>

            <div class="tab-pane fade" id="pills-notes">

                <div class="mb-3">
                    <label>Upload Photo</label>
                    <input type="file" class="form-control" name="file" accept="image/*">
                </div>

                <div class="mb-3">
                    <textarea class="form-control" rows="8" placeholder="Enter some notes" name="notes"></textarea>
                </div>

            </div>

        </div>
    </div>
    <div class="modal-footer">
        <button type="submit" name="add_rack" class="btn btn-primary text-bold"><i class="fa fa-check me-2"></i>Create</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fa fa-times me-2"></i>Cancel</button>
    </div>
</form>

<?php

require_once '../../../includes/modal_footer.php';
