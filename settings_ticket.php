<?php include("inc_all_settings.php"); ?>

    <div class="card card-dark">
        <div class="card-header py-3">
            <h3 class="card-title"><i class="fa fa-fw fa-life-ring"></i> Ticket Settings</h3>
        </div>
        <div class="card-body">
            <form action="post.php" method="post" autocomplete="off">

                <div class="form-group">
                    <label>Ticket Prefix</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-life-ring"></i></span>
                        </div>
                        <input type="text" class="form-control" name="config_ticket_prefix" placeholder="Ticket Prefix" value="<?php echo htmlentities($config_ticket_prefix); ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label>Next Number</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-barcode"></i></span>
                        </div>
                        <input type="number" min="0" class="form-control" name="config_ticket_next_number" placeholder="Next Ticket Number" value="<?php echo htmlentities($config_ticket_next_number); ?>" required>
                    </div>
                </div>

                <div class="form-group">
                    <label>From Email</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-envelope"></i></span>
                        </div>
                        <input type="email" class="form-control" name="config_ticket_from_email" placeholder="From Email" value="<?php echo htmlentities($config_ticket_from_email); ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label>From Name</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-tag"></i></span>
                        </div>
                        <input type="text" class="form-control" name="config_ticket_from_name" placeholder="Name" value="<?php echo htmlentities($config_ticket_from_name); ?>">
                    </div>
                </div>


                <div class="custom-control custom-switch mb-2">
                    <input type="checkbox" class="custom-control-input" name="config_ticket_email_parse" <?php if($config_ticket_email_parse == 1){ echo "checked"; } ?> value="1" id="customSwitch1">
                    <label class="custom-control-label" for="customSwitch1">Email-to-ticket parsing (Beta) <small>(cron_ticket_email_parser.php must also be added to cron and run every few mins)</small></label>
                </div>

                <div class="custom-control custom-switch mb-2">
                    <input type="checkbox" class="custom-control-input" name="config_ticket_client_general_notifications" <?php if($config_ticket_client_general_notifications == 1){ echo "checked"; } ?> value="1" id="customSwitch2">
                    <label class="custom-control-label" for="customSwitch2">Send clients general notification emails <small>(Should clients receive automatic emails when tickets are raised/closed?)</small></label>
                </div>

                <hr>

                <button type="submit" name="edit_ticket_settings" class="btn btn-primary text-bold"><i class="fa fa-check"></i> Save</button>

            </form>
        </div>
    </div>

<?php include("footer.php");
