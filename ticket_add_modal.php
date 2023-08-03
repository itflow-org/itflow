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
                <div class="modal-body bg-white">

                    <?php if (isset($_GET['client_id'])) { ?>
                        <ul class="nav nav-pills nav-justified mb-3">
                            <li class="nav-item">
                                <a class="nav-link active" data-toggle="pill" href="#pills-details"><i class="fa fa-fw fa-life-ring mr-2"></i>Details</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" data-toggle="pill" href="#pills-contacts"><i class="fa fa-fw fa-users mr-2"></i>Contacts</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" data-toggle="pill" href="#pills-assets"><i class="fa fa-fw fa-desktop mr-2"></i>Assets</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" data-toggle="pill" href="#pills-vendors"><i class="fa fa-fw fa-building mr-2"></i>Vendors</a>
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

                            <div class="form-group">
                                <textarea class="form-control tinymce" rows="5" name="details"></textarea>
                            </div>

                            <?php if (empty($_GET['client_id'])) { ?>

                                <div class="form-group">
                                    <label>Client <strong class="text-danger">*</strong></label>
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text"><i class="fa fa-fw fa-user"></i></span>
                                        </div>
                                        <select class="form-control select2" name="client" required>
                                            <option value="">- Client -</option>
                                            <?php

                                            $sql = mysqli_query($mysqli, "SELECT * FROM clients ORDER BY client_name ASC");
                                            while ($row = mysqli_fetch_array($sql)) {
                                                $client_id = intval($row['client_id']);
                                                $client_name = nullable_htmlentities($row['client_name']); ?>
                                                <option value="<?php echo $client_id; ?>"><?php echo $client_name; ?></option>

                                            <?php } ?>
                                        </select>
                                    </div>
                                </div>

                            <?php } ?>

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
                                            WHERE user_role > 1 AND user_archived_at IS NULL ORDER BY user_name ASC"
                                        );
                                        while ($row = mysqli_fetch_array($sql)) {
                                            $user_id = intval($row['user_id']);
                                            $user_name = nullable_htmlentities($row['user_name']); ?>
                                            <option <?php if ($session_user_id == $user_id) { echo "selected"; } ?> value="<?php echo $user_id; ?>"><?php echo $user_name; ?></option>
                                        <?php } ?>
                                    </select>
                                </div>
                            </div>

                        </div>

                        <?php if (isset($_GET['client_id'])) { ?>

                            <div class="tab-pane fade" id="pills-contacts">

                                <input type="hidden" name="client" value="<?php echo $client_id; ?>">
                                
                                <div class="form-group">
                                    <label>Contact <strong class="text-danger">*</strong></label>
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text"><i class="fa fa-fw fa-user"></i></span>
                                        </div>
                                        <select class="form-control select2" name="contact" required>
                                            <option value="">- Contact -</option>
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
                                                <option value="<?php echo $contact_id; ?>" <?php if ($contact_primary == 1) { echo "selected"; } ?>><?php echo "$contact_name$contact_title_display$contact_primary_display$contact_technical_display"; ?></option>

                                            <?php } ?>
                                        </select>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label>Watchers</label>
                                    <div class="watchers"></div>
                                    <button type="button" class="btn btn-primary" onclick="addWatcher(this)"><i class="fas fa-fw fa-plus"></i> Add Watcher</button>
                                </div>

                            </div>

                            <div class="tab-pane fade" id="pills-assets">

                                <div class="form-group">
                                    <label>Asset</label>
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text"><i class="fa fa-fw fa-desktop"></i></span>
                                        </div>
                                        <select class="form-control select2" name="asset">
                                            <option value="0">- None -</option>
                                            <?php

                                            $sql_assets = mysqli_query($mysqli, "SELECT * FROM assets WHERE asset_client_id = $client_id AND asset_archived_at IS NULL ORDER BY asset_name ASC");
                                            while ($row = mysqli_fetch_array($sql_assets)) {
                                                $asset_id_select = intval($row['asset_id']);
                                                $asset_name_select = nullable_htmlentities($row['asset_name']); ?>
                                                <option value="<?php echo $asset_id_select; ?>"><?php echo $asset_name_select; ?></option>

                                            <?php } ?>
                                        </select>
                                    </div>
                                </div>

                            </div>

                            <div class="tab-pane fade" id="pills-vendors">

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
