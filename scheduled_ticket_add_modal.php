<div class="modal" id="addScheduledTicketModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content bg-dark">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fa fa-fw fa-sync"></i> New Scheduled Ticket</h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form action="post.php" method="post" autocomplete="off">
                <div class="modal-body bg-white">
                    <?php if(isset($_GET['client_id'])){ ?>
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

                                    $sql = mysqli_query($mysqli,"SELECT * FROM contacts WHERE contact_client_id = $client_id ORDER BY contact_name ASC");
                                    while($row = mysqli_fetch_array($sql)){
                                        $contact_id = $row['contact_id'];
                                        $contact_name = $row['contact_name'];
                                        ?>
                                        <option value="<?php echo $contact_id; ?>" <?php if($primary_contact == $contact_id){ echo "selected"; } ?>><?php echo "$contact_name"; ?></option>

                                        <?php
                                    }
                                    ?>
                                </select>
                            </div>
                        </div>
                    <?php }else{ ?>
                        <div class="form-group">
                            <label>Client <strong class="text-danger">*</strong></label>
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text"><i class="fa fa-fw fa-user"></i></span>
                                </div>
                                <select class="form-control select2" name="client" required>
                                    <option value="">- Client -</option>
                                    <?php

                                    $sql = mysqli_query($mysqli,"SELECT * FROM clients WHERE company_id = $session_company_id ORDER BY client_name ASC");
                                    while($row = mysqli_fetch_array($sql)){
                                        $client_id = $row['client_id'];
                                        $client_name = $row['client_name'];
                                        ?>
                                        <option value="<?php echo $client_id; ?>"><?php echo "$client_name"; ?></option>

                                        <?php
                                    }
                                    ?>
                                </select>
                            </div>
                        </div>
                    <?php } ?>

                    <div class="form-group">
                        <label>Frequency <strong class="text-danger">*</strong></label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fa fa-fw fa-calendar-plus"></i></span>
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
                            <input class="form-control" type="date" min="<?php echo date("Y-m-d"); ?>" name="start_date" required>
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

                    <div class="form-group">
                        <label>Asset</label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fa fa-fw fa-desktop"></i></span>
                            </div>
                            <select class="form-control select2" name="asset">
                                <option value="0">- None -</option>
                                <?php

                                $sql_assets = mysqli_query($mysqli,"SELECT * FROM assets WHERE asset_client_id = $client_id ORDER BY asset_name ASC");
                                while($row = mysqli_fetch_array($sql_assets)){
                                    $asset_id_select = $row['asset_id'];
                                    $asset_name_select = $row['asset_name'];
                                    ?>
                                    <option value="<?php echo $asset_id_select; ?>"><?php echo $asset_name_select; ?></option>

                                    <?php
                                }
                                ?>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <textarea class="form-control summernote" rows="8" name="details"></textarea>
                    </div>

                </div>
                <div class="modal-footer bg-white">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_scheduled_ticket" class="btn btn-primary">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>