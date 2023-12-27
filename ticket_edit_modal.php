<div class="modal" id="editTicketModal<?php echo $ticket_id; ?>" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content bg-dark">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fa fa-fw fa-life-ring mr-2"></i>Editing ticket: <strong><?php echo "$ticket_prefix$ticket_number"; ?></strong> - <?php echo $client_name; ?></h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form action="post.php" method="post" autocomplete="off">
                <input type="hidden" name="ticket_id" value="<?php echo $ticket_id; ?>">
                <input type="hidden" name="client_id" value="<?php echo $client_id; ?>">
                <input type="hidden" name="ticket_number" value="<?php echo "$ticket_prefix$ticket_number"; ?>">
                <div class="modal-body bg-white">

                    <ul class="nav nav-pills nav-justified mb-3">
                        <li class="nav-item">
                            <a class="nav-link active" data-toggle="pill" href="#pills-details<?php echo $ticket_id; ?>"><i class="fa fa-fw fa-life-ring mr-2"></i>Details</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" data-toggle="pill" href="#pills-contacts<?php echo $ticket_id; ?>"><i class="fa fa-fw fa-users mr-2"></i>Contacts</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" data-toggle="pill" href="#pills-assets<?php echo $ticket_id; ?>"><i class="fa fa-fw fa-desktop mr-2"></i>Assets</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" data-toggle="pill" href="#pills-vendors<?php echo $ticket_id; ?>"><i class="fa fa-fw fa-building mr-2"></i>Vendors</a>
                        </li>
                    </ul>

                    <hr>

                    <div class="tab-content">

                        <div class="tab-pane fade show active" id="pills-details<?php echo $ticket_id; ?>">

                            <div class="form-group">
                                <label>Subject <strong class="text-danger">*</strong></label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fa fa-fw fa-tag"></i></span>
                                    </div>
                                    <input type="text" class="form-control" name="subject" value="<?php echo $ticket_subject; ?>" placeholder="Subject" required>
                                </div>
                            </div>

                            <div class="form-group">
                                <textarea class="form-control tinymce" rows="8" name="details"><?php echo $ticket_details; ?></textarea>
                            </div>

                            <div class="form-group">
                                <label>Priority <strong class="text-danger">*</strong></label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fa fa-fw fa-thermometer-half"></i></span>
                                    </div>
                                    <select class="form-control select2" name="priority" required>
                                        <option <?php if ($ticket_priority == 'Low') { echo "selected"; } ?> >Low</option>
                                        <option <?php if ($ticket_priority == 'Medium') { echo "selected"; } ?> >Medium</option>
                                        <option <?php if ($ticket_priority == 'High') { echo "selected"; } ?> >High</option>
                                    </select>
                                </div>
                            </div>

                            <?php if ($config_module_enable_accounting) {
                                ?>
                            <div class="form-group">
                                <label>Billable</label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fa fa-fw fa-money-bill"></i></span>
                                    </div>
                                    <select class="form-control" name="billable">
                                        <option <?php if ($ticket_billable == 1) { echo "selected"; } ?> value="1">Yes</option>
                                        <option <?php if ($ticket_billable == 0) { echo "selected"; } ?> value="0">No</option>
                                    </select>
                                </div>
                            </div>
                            <?php } ?>


                        </div>

                        <div class="tab-pane fade" id="pills-contacts<?php echo $ticket_id; ?>">

                            <div class="form-group">
                                <label>Contact</label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fa fa-fw fa-user"></i></span>
                                    </div>
                                    <select class="form-control select2" name="contact">
                                        <option value="0">No One</option>
                                        <?php
                                        $sql_client_contacts_select = mysqli_query($mysqli, "SELECT * FROM contacts WHERE contact_client_id = $client_id AND contact_archived_at IS NULL ORDER BY contact_primary DESC, contact_technical DESC, contact_name ASC");
                                        while ($row = mysqli_fetch_array($sql_client_contacts_select)) {
                                            $contact_id_select = intval($row['contact_id']);
                                            $contact_name_select = nullable_htmlentities($row['contact_name']);
                                            $contact_primary_select = intval($row['contact_primary']);
                                            if($contact_primary_select == 1) {
                                                $contact_primary_display_select = " (Primary)";
                                            } else {
                                                $contact_primary_display_select = "";
                                            }
                                            $contact_technical_select = intval($row['contact_technical']);
                                            if($contact_technical_select == 1) {
                                                $contact_technical_display_select = " (Technical)";
                                            } else {
                                                $contact_technical_display_select = "";
                                            }
                                            $contact_title_select = nullable_htmlentities($row['contact_title']);
                                            if(!empty($contact_title_select)) {
                                                $contact_title_display_select = " - $contact_title_select";
                                            } else {
                                                $contact_title_display_select = "";
                                            }
                                            
                                            ?>
                                            <option value="<?php echo $contact_id_select; ?>" <?php if ($contact_id_select  == $contact_id) { echo "selected"; } ?>><?php echo "$contact_name_select$contact_title_display_select$contact_primary_display_select$contact_technical_display_select"; ?></option>
                                        <?php } ?>
                                    </select>
                                </div>
                            </div>

                        </div>

                        <div class="tab-pane fade" id="pills-assets<?php echo $ticket_id; ?>">

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
                                            <option <?php if ($asset_id == $asset_id_select) { echo "selected"; } ?> value="<?php echo $asset_id_select; ?>"><?php echo $asset_name_select; ?></option>

                                            <?php
                                        }
                                        ?>
                                    </select>
                                </div>
                            </div>

                        </div>

                        <div class="tab-pane fade" id="pills-vendors<?php echo $ticket_id; ?>">

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
                                            $vendor_name_select = nullable_htmlentities($row['vendor_name']);
                                            ?>
                                            <option <?php if ($vendor_id == $vendor_id_select) { echo "selected"; } ?> value="<?php echo $vendor_id_select; ?>"><?php echo $vendor_name_select; ?></option>

                                            <?php
                                        }
                                        ?>
                                    </select>
                                </div>
                            </div>

                            <div class="form-group">
                                <label>Vendor Ticket Number</label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fa fa-fw fa-tag"></i></span>
                                    </div>
                                    <input type="text" class="form-control" name="vendor_ticket_number" placeholder="Vendor ticket number" value="<?php echo $ticket_vendor_ticket_number; ?>">
                                </div>
                            </div>

                        </div>

                    </div>

                </div>

                <div class="modal-footer bg-white">
                    <button type="submit" name="edit_ticket" class="btn btn-primary text-bold"><i class="fa fa-check mr-2"></i>Save</button>
                    <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fa fa-times mr-2"></i>Cancel</button>
                </div>

            </form>

        </div>
    </div>
</div>
