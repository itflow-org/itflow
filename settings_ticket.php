<?php
require_once("inc_all_settings.php"); ?>

    <div class="card card-dark">
        <div class="card-header py-3">
            <h3 class="card-title"><i class="fas fa-fw fa-life-ring mr-2"></i>Ticket Settings</h3>
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
                        <input type="number" min="0" class="form-control" name="config_ticket_next_number" placeholder="Next Ticket Number" value="<?php echo intval($config_ticket_next_number); ?>" required>
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

                <div class="form-group">
                    <div class="custom-control custom-switch">
                        <input type="checkbox" class="custom-control-input" name="config_ticket_email_parse" <?php if($config_ticket_email_parse == 1){ echo "checked"; } ?> value="1" id="emailToTicketParseSwitch">
                        <label class="custom-control-label" for="emailToTicketParseSwitch">Email-to-ticket parsing <small>(cron_ticket_email_parser.php must also be added to cron and run every few mins)</small></label>
                    </div>
                </div>

                <div class="form-group">
                    <div class="custom-control custom-switch">
                        <input type="checkbox" class="custom-control-input" name="config_ticket_client_general_notifications" <?php if($config_ticket_client_general_notifications == 1){ echo "checked"; } ?> value="1" id="ticketNotificationSwitch">
                        <label class="custom-control-label" for="ticketNotificationSwitch">Send clients general notification emails <small>(Should clients receive automatic emails when tickets are raised/closed?)</small></label>
                    </div>
                </div>

                <div class="form-group">
                    <div class="custom-control custom-switch">
                        <input type="checkbox" class="custom-control-input" name="config_ticket_autoclose" <?php if($config_ticket_autoclose == 1){ echo "checked"; } ?> value="1" id="ticketAutoCloseSwitch">
                        <label class="custom-control-label" for="ticketAutoCloseSwitch">Enable Autoclose Tickets <small class="text-secondary">(If no response is received after 48 hrs, a chaser email is sent mentioning "This is an automatic friendly reminder that your ticket regarding "Test ticket" will be closed, unless you respond", including the last public technician response for reference
If no response is received after a further 22 hrs (70 total since ticket was put in auto close), the ticket is silently closed. (Note: I chose 70 hrs to help prevent situations where the chaser email is sent twice - feel free to adjust as needed))</small></label>
                    </div>
                </div>

                <div class="form-group">
                    <label>Number of hours to auto close ticket</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-clock"></i></span>
                        </div>
                        <input type="number" min="72" class="form-control" name="config_ticket_autoclose_hours" placeholder="Enter the number of hours to auto close ticket" value="<?php echo intval($config_ticket_autoclose_hours); ?>">
                    </div>
                </div>

                <hr>

                <button type="submit" name="edit_ticket_settings" class="btn btn-primary text-bold"><i class="fas fa-check mr-2"></i>Save</button>

            </form>
        </div>
    </div>

<?php
require_once("footer.php");
