<div class="modal" id="addRecurringTicketModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content bg-dark">
            <div class="modal-header">
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

                <div class="modal-body bg-white">

                    <ul class="nav nav-pills nav-justified mb-3">
                        <li class="nav-item">
                            <a class="nav-link active" data-toggle="pill" href="#pills-add-details"><i class="fa fa-fw fa-life-ring mr-2"></i>Details</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" data-toggle="pill" href="#pills-add-contacts"><i class="fa fa-fw fa-users mr-2"></i>Contacts</a>
                        </li>
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
                                    <input type="text" class="form-control" name="subject" placeholder="Subject" required>
                                </div>
                            </div>

                            <div class="form-group">
                                <textarea class="form-control tinymceTicket<?php if($config_ai_enable) { echo "AI"; } ?>" id="textInput" name="details"></textarea>
                            </div>

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
                                        <option value="0">- Not Assigned -</option>
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

                        <div class="tab-pane fade" id="pills-add-schedule">

                            <div class="form-group">
                                <label>Frequency <strong class="text-danger">*</strong></label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fa fa-fw fa-recycle"></i></span>
                                    </div>
                                    <select class="form-control select2" name="frequency" required>
                                        <option>Weekly</option>
                                        <option>Monthly</option>
                                        <option>Quarterly</option>
                                        <option>Biannually</option>
                                        <option>Annually</option>
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
                                            $sql_assets = mysqli_query($mysqli, "SELECT * FROM assets WHERE asset_client_id = $client_id AND asset_archived_at IS NULL ORDER BY asset_name ASC");

                                            while ($row = mysqli_fetch_array($sql_assets)) {
                                                $asset_id_select = intval($row['asset_id']);
                                                $asset_name_select = nullable_htmlentities($row['asset_name']);
                                                ?>
                                                <option value="<?php echo $asset_id_select; ?>"
                                                    <?php if (isset($_GET['asset_id']) && $asset_id_select == $_GET['asset_id']) { echo "selected"; } 
                                                    ?>

                                                    ><?php echo $asset_name_select; ?>
                                                </option>

                                            <?php } ?>
                                        </select>
                                    </div>
                                </div>

                            <?php } ?>

                        </div>

                    </div>

                </div>
                <div class="modal-footer bg-white">
                    <button type="submit" name="add_recurring_ticket" class="btn btn-primary text-bold"><i class="fas fa-check mr-2"></i>Create</button>
                    <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fas fa-times mr-2"></i>Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Recurring Ticket Client/Contact JS -->
<link rel="stylesheet" href="plugins/jquery-ui/jquery-ui.min.css">
<script src="plugins/jquery-ui/jquery-ui.min.js"></script>
<script src="js/recurring_tickets_add_modal.js"></script>
