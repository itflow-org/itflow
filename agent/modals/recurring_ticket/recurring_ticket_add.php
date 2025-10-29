<div class="modal" id="addRecurringTicketModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-dark">
                <h5 class="modal-title"><i class="fa fa-fw fa-calendar-check mr-2"></i>New Recurring Ticket</h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form action="post.php" method="post" autocomplete="off">
                <?php if (isset($client_id)) { ?>
                       <input type="hidden" name="client" value="<?php echo $client_id; ?>>">
                <?php } ?>
                <input type="hidden" name="billable" value="0">

                <div class="modal-body">

                    <ul class="nav nav-pills nav-justified mb-3">
                        <li class="nav-item">
                            <a class="nav-link active" data-toggle="pill" href="#pills-add-details"><i class="fa fa-fw fa-life-ring mr-2"></i>Details</a>
                        </li>
                        <?php if (!isset($_GET['contact_id'])) { ?>
                        <li class="nav-item">
                            <a class="nav-link" data-toggle="pill" href="#pills-add-contacts"><i class="fa fa-fw fa-users mr-2"></i>Contact</a>
                        </li>
                        <?php } ?>
                        <li class="nav-item">
                            <a class="nav-link" data-toggle="pill" href="#pills-add-schedule"><i class="fa fa-fw fa-building mr-2"></i>Schedule</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" data-toggle="pill" href="#pills-add-assets"><i class="fa fa-fw fa-desktop mr-2"></i>Assets</a>
                        </li>
                    </ul>

                    <div class="tab-content">

                        <div class="tab-pane fade show active" id="pills-add-details">

                            <div class="form-group">
                                <label>Subject <strong class="text-danger">*</strong></label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fa fa-fw fa-tag"></i></span>
                                    </div>
                                    <input type="text" class="form-control" name="subject" placeholder="Subject" maxlength="500" required>
                                </div>
                            </div>

                            <div class="form-group">
                                <textarea class="form-control tinymceTicket" name="details"></textarea>
                            </div>

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
                                                $sql_categories = mysqli_query($mysqli, "SELECT category_id, category_name FROM categories WHERE category_type = 'Ticket' AND category_archived_at IS NULL ORDER BY category_name ASC");
                                                while ($row = mysqli_fetch_array($sql_categories)) {
                                                    $category_id = intval($row['category_id']);
                                                    $category_name = nullable_htmlentities($row['category_name']);

                                                    ?>
                                                    <option value="<?php echo $category_id; ?>"><?php echo $category_name; ?></option>
                                                <?php } ?>

                                            </select>
                                            <div class="input-group-append">
                                                <button class="btn btn-secondary ajax-modal" type="button"
                                                    data-modal-url="../admin/modals/category/category_add.php?category=Ticket">
                                                    <i class="fas fa-fw fa-plus"></i>
                                                </button>
                                            </div>
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
                                        <option value="0">- Not Assigned -</option>
                                        <?php

                                        $sql = mysqli_query(
                                            $mysqli,
                                            "SELECT user_id, user_name FROM users
                                            WHERE user_type = 1 AND user_status = 1 AND user_archived_at IS NULL ORDER BY user_name ASC"
                                        );
                                        while ($row = mysqli_fetch_array($sql)) {
                                            $user_id = intval($row['user_id']);
                                            $user_name = nullable_htmlentities($row['user_name']); ?>
                                            <option value="<?php echo $user_id; ?>"><?php echo $user_name; ?></option>
                                        <?php } ?>
                                    </select>
                                </div>
                            </div>

                            <?php if ($config_module_enable_accounting) { ?>
                            <div class="form-group">
                                <div class="custom-control custom-switch">
                                    <input type="checkbox" class="custom-control-input" name="billable" <?php if ($config_ticket_default_billable == 1) { echo "checked"; } ?> value="1" id="billable">
                                    <label class="custom-control-label" for="billable">Mark Billable</label>
                                </div>
                            </div>
                            <?php } ?>

                        </div>

                        <?php if (!isset($_GET['contact_id'])) { ?>
                        <div class="tab-pane fade" id="pills-add-contacts">

                            <div class="form-group">
                                <label>Client <strong class="text-danger">*</strong></label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fa fa-fw fa-user"></i></span>
                                    </div>
                                    <select class="form-control select2" name="client" id="changeClientSelect" required <?php if (isset($client_id)) { echo "disabled"; } ?>>
                                        <option value="">- Client -</option>
                                        <?php

                                        $sql = mysqli_query($mysqli, "SELECT * FROM clients WHERE client_archived_at IS NULL $access_permission_query ORDER BY client_name ASC");
                                        while ($row = mysqli_fetch_array($sql)) {
                                            $selectable_client_id = intval($row['client_id']);
                                            $client_name = nullable_htmlentities($row['client_name']); ?>

                                            <option value="<?php echo $selectable_client_id; ?>" <?php if (isset($client_id) && $client_id == $selectable_client_id) {echo "selected"; } ?>><?php echo $client_name; ?></option>

                                        <?php } ?>
                                    </select>
                                </div>
                            </div>


                            <div class="form-group">
                                <label>Contact </label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fa fa-fw fa-user"></i></span>
                                    </div>
                                    <select class="form-control select2" name="contact" id="contactSelect">
                                    </select>
                                </div>
                            </div>

                            <div id="contacts-section">

                            </div>
                        </div>
                        <?php } else { ?>
                        <input type="hidden" name="client" value="<?php echo $client_id; ?>">
                        <input type="hidden" name="contact" value="<?php echo intval($_GET['contact_id']); ?>">
                        <?php } ?>

                        <div class="tab-pane fade" id="pills-add-schedule">

                            <div class="form-group">
                                <label>Frequency <strong class="text-danger">*</strong></label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fa fa-fw fa-recycle"></i></span>
                                    </div>
                                    <select class="form-control select2" name="frequency" required>
                                        <optgroup label="Days">
                                            <option>Three Days</option>
                                            <option>Weekly</option>
                                            <option>Biweekly</option>
                                        </optgroup>
                                        <optgroup label="Months">
                                            <option>Monthly</option>
                                            <option>Quarterly</option>
                                            <option>Biannually</option>
                                            <option>Annually</option>
                                        </optgroup>
                                    </select>
                                </div>
                            </div>

                            <div class="form-group">
                                <label>Starting date <strong class="text-danger">*</strong></label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fa fa-fw fa-calendar-day"></i></span>
                                    </div>
                                    <input class="form-control" type="date" name="start_date" min="<?php echo date("Y-m-d"); ?>" max="2999-12-31" required>
                                </div>
                            </div>

                        </div>

                        <div class="tab-pane fade" id="pills-add-assets">

                            <?php if (isset($client_id)) { ?>

                                <div class="form-group">
                                    <label>Asset</label>
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text"><i class="fa fa-fw fa-desktop"></i></span>
                                        </div>
                                        <select class="form-control select2" name="asset">
                                            <option value="0">- None -</option>

                                            <?php
                                            // Query assets ordered by type, then name
                                            $sql_assets = mysqli_query($mysqli, "
                                                SELECT asset_id, asset_name, asset_type, asset_make, asset_model, contact_name 
                                                FROM assets 
                                                LEFT JOIN contacts ON contact_id = asset_contact_id 
                                                WHERE asset_client_id = $client_id 
                                                  AND asset_archived_at IS NULL 
                                                ORDER BY asset_type ASC, asset_name ASC
                                            ");

                                            $current_type = null; // Track which optgroup we're in

                                            while ($row = mysqli_fetch_array($sql_assets)) {
                                                $asset_id_select = intval($row['asset_id']);
                                                $asset_name_select = nullable_htmlentities($row['asset_name']);
                                                $asset_type_select = nullable_htmlentities($row['asset_type']);
                                                $asset_make_select = nullable_htmlentities($row['asset_make']);
                                                $asset_model_select = nullable_htmlentities($row['asset_model']);
                                                $contact_name_select = nullable_htmlentities($row['contact_name']);
                                                
                                                // Start new optgroup if type changes
                                                if ($asset_type_select !== $current_type) {
                                                    if ($current_type !== null) echo "</optgroup>";
                                                    echo "<optgroup label=\"" . ($asset_type_select ?: 'Uncategorized') . "\">";
                                                    $current_type = $asset_type_select;
                                                }

                                                // Build full display
                                                $full_name = $asset_name_select . ($asset_make_select ? " - $asset_make_select" . ($asset_model_select ? " $asset_model_select" : '') : '') 
                                                             . ($contact_name_select ? " - ($contact_name_select)" : '');
                                                ?>

                                                <option value="<?= $asset_id_select ?>"><?= $full_name ?></option>

                                            <?php } 

                                            if ($current_type_select !== null) echo "</optgroup>"; 
                                            ?>
                                        </select>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label>Additional Assets</label>
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text"><i class="fa fa-fw fa-desktop"></i></span>
                                        </div>
                                        <select class="form-control select2" name="additional_assets[]" data-tags="true" data-placeholder="- Select Additional Assets -" multiple>
                                            <option value=""></option>

                                            <?php
                                            // Query assets ordered by type then name
                                            $sql_assets = mysqli_query($mysqli, "
                                                SELECT asset_id, asset_name, asset_type, asset_make, asset_model, contact_name 
                                                FROM assets 
                                                LEFT JOIN contacts ON contact_id = asset_contact_id 
                                                WHERE asset_client_id = $client_id 
                                                  AND asset_archived_at IS NULL 
                                                ORDER BY asset_type ASC, asset_name ASC
                                            ");

                                            $current_type = null;

                                            while ($row = mysqli_fetch_array($sql_assets)) {
                                                $asset_id_select = intval($row['asset_id']);
                                                $asset_name_select = nullable_htmlentities($row['asset_name']);
                                                $asset_type_select = nullable_htmlentities($row['asset_type']);
                                                $asset_make_select = nullable_htmlentities($row['asset_make']);
                                                $asset_model_select = nullable_htmlentities($row['asset_model']);
                                                $contact_name_select = nullable_htmlentities($row['contact_name']);

                                                // Start new optgroup if type changes
                                                if ($asset_type_select !== $current_type) {
                                                    if ($current_type !== null) echo "</optgroup>";
                                                    echo "<optgroup label=\"" . ($asset_type_select ?: 'Uncategorized') . "\">";
                                                    $current_type = $asset_type_select;
                                                }

                                                // Build full display
                                                $full_name = $asset_name_select . ($asset_make_select ? " - $asset_make_select" . ($asset_model_select ? " $asset_model_select" : '') : '') 
                                                             . ($contact_name_select ? " - ($contact_name_select)" : '');
                                                ?>

                                                <option value="<?= $asset_id_select ?>"><?= $full_name ?></option>

                                            <?php } 

                                            if ($current_type !== null) echo "</optgroup>"; 
                                            ?>
                                        </select>
                                    </div>
                                </div>


                            <?php } ?>

                        </div>

                    </div>

                </div>
                <div class="modal-footer">
                    <button type="submit" name="add_recurring_ticket" class="btn btn-primary text-bold"><i class="fas fa-check mr-2"></i>Create</button>
                    <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fas fa-times mr-2"></i>Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Recurring Ticket Client/Contact JS -->
<link rel="stylesheet" href="../plugins/jquery-ui/jquery-ui.min.css">
<script src="../plugins/jquery-ui/jquery-ui.min.js"></script>
<script src="js/tickets_add_modal.js"></script>
