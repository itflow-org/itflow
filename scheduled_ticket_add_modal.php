<div class="modal" id="addScheduledTicketModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content bg-dark">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fa fa-fw fa-calendar-check mr-2"></i>New Scheduled Ticket</h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form action="post.php" method="post" autocomplete="off">
                <div class="modal-body bg-white">
                    <?php if (isset($_GET['client_id'])) { ?>
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
                    <?php  } else { ?>
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
                                        $selectable_client_id = intval($row['client_id']);
                                        $client_name = nullable_htmlentities($row['client_name']);
                                        ?>
                                        <option value="<?php echo $selectable_client_id; ?>"><?php echo $client_name; ?></option>

                                    <?php } ?>
                                </select>
                            </div>
                        </div>
                    <?php } ?>

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
                        <label>Subject <strong class="text-danger">*</strong></label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fa fa-fw fa-tag"></i></span>
                            </div>
                            <input type="text" class="form-control" name="subject" placeholder="Subject" required>
                        </div>
                    </div>

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
                                    $sql_assets = mysqli_query($mysqli, "SELECT SQL_CALC_FOUND_ROWS * FROM assets WHERE asset_client_id = $client_id ORDER BY asset_name ASC");

                                    while ($row = mysqli_fetch_array($sql_assets)) {
                                        $asset_id_select = intval($row['asset_id']);
                                        $asset_name_select = nullable_htmlentities($row['asset_name']);
                                        ?>
                                        <option value="<?php echo $asset_id_select; ?>"><?php echo $asset_name_select; ?></option>

                                    <?php } ?>
                                </select>
                            </div>
                        </div>

                    <?php } ?>

                    <div class="form-group">
                        <textarea class="form-control tinymce" name="details"></textarea>
                    </div>

                </div>
                <div class="modal-footer bg-white">
                    <button type="submit" name="add_scheduled_ticket" class="btn btn-primary text-bold"><i class="fas fa-check mr-2"></i>Create</button>
                    <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fas fa-times mr-2"></i>Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>
