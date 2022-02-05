<div class="modal" id="editScheduledTicketModal<?php echo $scheduled_ticket_id ?>" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content bg-dark">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fa fa-fw fa-sync"></i> Edit Scheduled Ticket - <?php echo "$scheduled_ticket_subject for $scheduled_ticket_client_name "?></h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form action="post.php" method="post" autocomplete="off">
                <div class="modal-body bg-white">
                    <input type="hidden" name="ticket_id" value="<?php echo $scheduled_ticket_id; ?>">
                    <input type="hidden" name="client_id" value="<?php echo $scheduled_ticket_client_id; ?>">

                    <div class="form-group">
                        <label>Frequency <strong class="text-danger">*</strong></label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fa fa-fw fa-calendar-plus"></i></span>
                            </div>
                            <select class="form-control select2" name="frequency" required>
                                <option <?php if($scheduled_ticket_frequency == "Weekly") {echo "selected";} ?>>Weekly</option>
                                <option <?php if($scheduled_ticket_frequency == "Monthly") {echo "selected";} ?>>Monthly</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Next run date <strong class="text-danger">*</strong></label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fa fa-fw fa-calendar-day"></i></span>
                            </div>
                            <input class="form-control" type="date" name="next_date" value="<?php echo $scheduled_ticket_next_run ?>">
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Priority <strong class="text-danger">*</strong></label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fa fa-fw fa-thermometer-half"></i></span>
                            </div>
                            <select class="form-control select2" name="priority" required>
                                <option <?php if($scheduled_ticket_priority == 'Low'){ echo "selected"; } ?> >Low</option>
                                <option <?php if($scheduled_ticket_priority == 'Medium'){ echo "selected"; } ?> >Medium</option>
                                <option <?php if($scheduled_ticket_priority == 'High'){ echo "selected"; } ?> >High</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Subject <strong class="text-danger">*</strong></label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fa fa-fw fa-tag"></i></span>
                            </div>
                            <input type="text" class="form-control" name="subject" placeholder="Subject" required value="<?php echo $scheduled_ticket_subject?>">
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Asset</label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fa fa-fw fa-desktop"></i></span>
                            </div>
                            <select class="form-control select2" name="asset">
                                <option value="0">- None -</option>
                                <?php

                                $sql_assets = mysqli_query($mysqli,"SELECT * FROM assets WHERE asset_client_id = $scheduled_ticket_client_id ORDER BY asset_name ASC");
                                while($row = mysqli_fetch_array($sql_assets)){
                                    $asset_id_select = $row['asset_id'];
                                    $asset_name_select = $row['asset_name'];
                                    ?>
                                    <option value="<?php echo $asset_id_select?>" <?php if($asset_id_select == $scheduled_ticket_asset_id){echo "selected";} ?>><?php echo $asset_name_select; ?></option>

                                    <?php
                                }
                                ?>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <textarea class="form-control summernote" rows="8" name="details"><?php echo $scheduled_ticket_details ?></textarea>
                    </div>

                </div>
                <div class="modal-footer bg-white">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" name="edit_scheduled_ticket" class="btn btn-primary">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>