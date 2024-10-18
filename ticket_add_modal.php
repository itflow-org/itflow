<div class="modal" id="addTicketModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content bg-dark">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-fw fa-life-ring mr-2"></i>New Ticket</h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form action="post.php" method="post" autocomplete="off">
                <input type="hidden" name="billable" value="0">
                <div class="modal-body bg-white">

                    <?php if (isset($_GET['client_id'])) { ?>
                        <ul class="nav nav-pills nav-justified mb-3">
                            <li class="nav-item">
                                <a class="nav-link active" data-toggle="pill" href="#pills-details"><i class="fa fa-fw fa-life-ring mr-2"></i>Details</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" data-toggle="pill" href="#pills-contacts"><i class="fa fa-fw fa-users mr-2"></i>Contact</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" data-toggle="pill" href="#pills-assignment"><i class="fa fa-fw fa-desktop mr-2"></i>Assignment</a>
                            </li>
                        </ul>

                        <hr>

                    <?php } ?>

                    <div class="tab-content">

                        <div class="tab-pane fade show active" id="pills-details">

                            <div class="form-group">
                                <label>Subject <strong class="text-danger">*</strong></label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fa fa-fw fa-tag"></i></span>
                                    </div>
                                    <input type="text" class="form-control" name="subject" placeholder="Subject" required>
                                </div>
                            </div>

                            <?php if($config_ai_enable) { ?>
                            <div class="form-group">
                                <textarea class="form-control tinymceai" id="textInput" name="details"></textarea>
                            </div>

                            <div class="mb-3">
                                <button id="rewordButton" class="btn btn-primary" type="button"><i class="fas fa-fw fa-robot mr-2"></i>Reword</button>
                                <button id="undoButton" class="btn btn-secondary" type="button" style="display:none;"><i class="fas fa-fw fa-redo-alt mr-2"></i>Undo</button>
                            </div>
                            <?php } else { ?>
                            <div class="form-group">
                                <textarea class="form-control tinymce" rows="5" name="details"></textarea>
                            </div>
                            <?php } ?>

                            <?php if (empty($_GET['client_id'])) { ?>

                                <div class="form-group">
                                    <label>Client <strong class="text-danger">*</strong> / <span class="text-secondary">Use Primary Contact</label>
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text"><i class="fa fa-fw fa-user"></i></span>
                                        </div>
                                        <select class="form-control select2" name="client" required>
                                            <option value="">- Client -</option>
                                            <?php

                                            $sql = mysqli_query($mysqli, "SELECT * FROM clients WHERE client_archived_at IS NULL $access_permission_query ORDER BY client_name ASC");
                                            while ($row = mysqli_fetch_array($sql)) {
                                                $client_id = intval($row['client_id']);
                                                $client_name = nullable_htmlentities($row['client_name']); ?>
                                                <option value="<?php echo $client_id; ?>"><?php echo $client_name; ?></option>

                                            <?php } ?>
                                        </select>
                                        <div class="input-group-append">
                                            <div class="input-group-text">
                                                <input type="checkbox" name="use_primary_contact" value="1">
                                            </div>
                                        </div>
                                    </div>
                                </div>

                            <?php } ?>

                            <div class="row">
                                
                                <div class="col">
                                    <div class="form-group">
                                        <label>Priority <strong class="text-danger">*</strong></label>
                                        <div class="input-group">
                                            <div class="input-group-prepend">
                                                <span class="input-group-text"><i class="fa fa-fw fa-thermometer-half"></i></span>
                                            </div>
                                            <select class="form-control select2" name="priority" required>
                                                <option>Low</option>
                                                <option>Medium</option>
                                                <option>High</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <div class="col">
                                    <div class="form-group">
                                        <label>Category</label>
                                        <div class="input-group">
                                            <div class="input-group-prepend">
                                                <span class="input-group-text"><i class="fa fa-fw fa-layer-group"></i></span>
                                            </div>
                                            <select class="form-control select2" name="category">
                                                <option value="0">- Not Categorized -</option>
                                                <?php
                                                $sql_categories = mysqli_query($mysqli, "SELECT category_id, category_name FROM categories WHERE category_type = 'Ticket' AND category_archived_at IS NULL");
                                                while ($row = mysqli_fetch_array($sql_categories)) {
                                                    $category_id = intval($row['category_id']);
                                                    $category_name = nullable_htmlentities($row['category_name']);

                                                    ?>
                                                    <option value="<?php echo $category_id; ?>"><?php echo $category_name; ?></option>
                                                <?php } ?>

                                            </select>
                                        </div>
                                    </div>
                                </div>

                            </div>

                            <div class="form-group">
                                <label>Assign to</label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fa fa-fw fa-user-check"></i></span>
                                    </div>
                                    <select class="form-control select2" name="assigned_to">
                                        <option value="0">Not Assigned</option>
                                        <?php

                                        $sql = mysqli_query(
                                            $mysqli,
                                            "SELECT users.user_id, user_name FROM users
                                            LEFT JOIN user_settings on users.user_id = user_settings.user_id
                                            WHERE user_role > 1 AND user_status = 1 AND user_archived_at IS NULL ORDER BY user_name ASC"
                                        );
                                        while ($row = mysqli_fetch_array($sql)) {
                                            $user_id = intval($row['user_id']);
                                            $user_name = nullable_htmlentities($row['user_name']); ?>
                                            <option <?php if ($session_user_id == $user_id) { echo "selected"; } ?> value="<?php echo $user_id; ?>"><?php echo $user_name; ?></option>
                                        <?php } ?>
                                    </select>
                                </div>
                            </div>

                            <?php if ($config_module_enable_accounting) { ?>
                            <div class="form-group">
                                <div class="custom-control custom-switch">
                                    <input type="checkbox" class="custom-control-input" name="billable" <?php if ($config_ticket_default_billable == 1) { echo "checked"; } ?> value="1" id="billableSwitch">
                                    <label class="custom-control-label" for="billableSwitch">Mark Billable</label>
                                </div>
                            </div>
                            <?php } ?>

                        </div>

                        <?php if (isset($_GET['client_id'])) { ?>

                            <div class="tab-pane fade" id="pills-contacts">

                                <input type="hidden" name="client" value="<?php echo $client_id; ?>">

                                <div class="form-group">
                                    <label>Contact</label>
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text"><i class="fa fa-fw fa-user"></i></span>
                                        </div>
                                        <select class="form-control select2" name="contact">
                                            <option value="0">- No One -</option>
                                            <?php
                                            $sql = mysqli_query($mysqli, "SELECT * FROM contacts WHERE contact_client_id = $client_id AND contact_archived_at IS NULL ORDER BY contact_primary DESC, contact_technical DESC, contact_name ASC");
                                            while ($row = mysqli_fetch_array($sql)) {
                                                $contact_id = intval($row['contact_id']);
                                                $contact_name = nullable_htmlentities($row['contact_name']);
                                                $contact_primary = intval($row['contact_primary']);
                                                if($contact_primary == 1) {
                                                    $contact_primary_display = " (Primary)";
                                                } else {
                                                    $contact_primary_display = "";
                                                }
                                                $contact_technical = intval($row['contact_technical']);
                                                if($contact_technical == 1) {
                                                    $contact_technical_display = " (Technical)";
                                                } else {
                                                    $contact_technical_display = "";
                                                }
                                                $contact_title = nullable_htmlentities($row['contact_title']);
                                                if(!empty($contact_title)) {
                                                    $contact_title_display = " - $contact_title";
                                                } else {
                                                    $contact_title_display = "";
                                                }

                                                ?>
                                                <option value="<?php echo $contact_id; ?>" <?php if ($contact_primary == 1 || $contact_id == isset($_GET['contact_id'])) { echo "selected"; } ?>><?php echo "$contact_name$contact_title_display$contact_primary_display$contact_technical_display"; ?></option>

                                            <?php } ?>
                                        </select>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label>Watchers</label>
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text"><i class="fa fa-fw fa-envelope"></i></span>
                                        </div>
                                        <select class="form-control select2" name="watchers[]" data-tags="true" data-placeholder="Enter or select email address" multiple>
                                            <option value=""></option>
                                            <?php
                                            $sql = mysqli_query($mysqli, "SELECT * FROM contacts WHERE contact_client_id = $client_id AND contact_archived_at IS NULL AND contact_email IS NOT NULL ORDER BY contact_email ASC");
                                            while ($row = mysqli_fetch_array($sql)) {
                                                $contact_email = nullable_htmlentities($row['contact_email']);
                                                ?>
                                                <option><?php echo $contact_email; ?></option>

                                            <?php } ?>
                                        </select>
                                    </div>
                                </div>

                            </div>

                            <div class="tab-pane fade" id="pills-assignment">

                                <div class="form-group">
                                    <label>Asset</label>
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text"><i class="fa fa-fw fa-desktop"></i></span>
                                        </div>
                                        <select class="form-control select2" name="asset">
                                            <option value="0">- None -</option>
                                            <?php

                                            $sql_assets = mysqli_query($mysqli, "SELECT * FROM assets LEFT JOIN contacts ON contact_id = asset_contact_id WHERE asset_client_id = $client_id AND asset_archived_at IS NULL ORDER BY asset_name ASC");
                                            while ($row = mysqli_fetch_array($sql_assets)) {
                                                $asset_id_select = intval($row['asset_id']);
                                                $asset_name_select = nullable_htmlentities($row['asset_name']);
                                                $asset_contact_name_select = nullable_htmlentities($row['contact_name']);
                                            ?>
                                                <option value="<?php echo $asset_id_select; ?>"><?php echo "$asset_name_select - $asset_contact_name_select"; ?></option>

                                            <?php } ?>
                                        </select>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label>Location</label>
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text"><i class="fa fa-fw fa-map-marker-alt"></i></span>
                                        </div>
                                        <select class="form-control select2" name="location">
                                            <option value="0">- None -</option>
                                            <?php

                                            $sql_locations = mysqli_query($mysqli, "SELECT * FROM locations WHERE location_client_id = $client_id AND location_archived_at IS NULL ORDER BY location_name ASC");
                                            while ($row = mysqli_fetch_array($sql_locations)) {
                                                $location_id_select = intval($row['location_id']);
                                                $location_name_select = nullable_htmlentities($row['location_name']);
                                            ?>
                                                <option value="<?php echo $location_id_select; ?>"><?php echo $location_name_select; ?></option>

                                            <?php } ?>
                                        </select>
                                    </div>
                                </div>

                                <div class="row">
                                
                                    <div class="col">

                                        <div class="form-group">
                                            <label>Vendor</label>
                                            <div class="input-group">
                                                <div class="input-group-prepend">
                                                    <span class="input-group-text"><i class="fa fa-fw fa-building"></i></span>
                                                </div>
                                                <select class="form-control select2" name="vendor">
                                                    <option value="0">- None -</option>
                                                    <?php

                                                    $sql_vendors = mysqli_query($mysqli, "SELECT * FROM vendors WHERE vendor_client_id = $client_id AND vendor_template = 0 AND vendor_archived_at IS NULL ORDER BY vendor_name ASC");
                                                    while ($row = mysqli_fetch_array($sql_vendors)) {
                                                        $vendor_id_select = intval($row['vendor_id']);
                                                        $vendor_name_select = nullable_htmlentities($row['vendor_name']); ?>
                                                        <option value="<?php echo $vendor_id_select; ?>"><?php echo $vendor_name_select; ?></option>

                                                    <?php } ?>
                                                </select>
                                            </div>
                                        </div>

                                    </div>

                                    <div class="col">

                                        <div class="form-group">
                                            <label>Vendor Ticket Number</label>
                                            <div class="input-group">
                                                <div class="input-group-prepend">
                                                    <span class="input-group-text"><i class="fa fa-fw fa-tag"></i></span>
                                                </div>
                                                <input type="text" class="form-control" name="vendor_ticket_number" placeholder="Vendor ticket number">
                                            </div>
                                        </div>
                                    
                                    </div>

                                </div>

                                <div class="form-group">
                                    <label>Project</label>
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text"><i class="fa fa-fw fa-project-diagram"></i></span>
                                        </div>
                                        <select class="form-control select2" name="project">
                                            <option value="0">- None -</option>
                                            <?php

                                            $sql_projects = mysqli_query($mysqli, "SELECT * FROM projects WHERE project_client_id = $client_id AND project_completed_at IS NULL AND project_archived_at IS NULL ORDER BY project_name ASC");
                                            while ($row = mysqli_fetch_array($sql_projects)) {
                                                $project_id_select = intval($row['project_id']);
                                                $project_name_select = nullable_htmlentities($row['project_name']); ?>
                                                <option value="<?php echo $project_id_select; ?>"><?php echo $project_name_select; ?></option>

                                            <?php } ?>
                                        </select>
                                    </div>
                                </div>

                            </div>

                        <?php } ?>

                    </div>

                </div>
                <div class="modal-footer bg-white">
                    <button type="submit" name="add_ticket" class="btn btn-primary text-bold"><i class="fas fa-check mr-2"></i>Create</button>
                    <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fas fa-times mr-2"></i>Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>
