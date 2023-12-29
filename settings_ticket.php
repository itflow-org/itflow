<?php
require_once "inc_all_settings.php";
 ?>

    <div class="card card-dark">
        <div class="card-header py-3">
            <h3 class="card-title"><i class="fas fa-fw fa-life-ring mr-2"></i>Ticket Settings</h3>
        </div>
        <div class="card-body">
            <form action="post.php" method="post" autocomplete="off">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?>">
                <input type="hidden" name="config_ticket_email_parse" value="0">
                <input type="hidden" name="config_ticket_autoclose" value="0">

                <div class="form-group">
                    <label>Ticket Prefix</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-life-ring"></i></span>
                        </div>
                        <input type="text" class="form-control" name="config_ticket_prefix" placeholder="Ticket Prefix" value="<?php echo nullable_htmlentities($config_ticket_prefix); ?>">
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
                    <div class="custom-control custom-switch">
                        <input type="checkbox" class="custom-control-input" name="config_ticket_email_parse" <?php if($config_ticket_email_parse == 1){ echo "checked"; } ?> value="1" id="emailToTicketParseSwitch">
                        <label class="custom-control-label" for="emailToTicketParseSwitch">Email-to-ticket parsing <small class="text-secondary">(cron_ticket_email_parser.php must also be added to cron and run every few mins)</small></label>
                    </div>
                </div>

                <div class="form-group">
                    <div class="custom-control custom-switch">
                        <input type="checkbox" class="custom-control-input" name="config_ticket_autoclose" <?php if($config_ticket_autoclose == 1){ echo "checked"; } ?> value="1" id="ticketAutoCloseSwitch">
                        <label class="custom-control-label" for="ticketAutoCloseSwitch">Enable Auto close Tickets <small class="text-secondary">(If no response is received after 48 hrs, a friendly chaser email is sent. The ticket is then automatically closed after the time specified below (defaults to 72 hours). </small></label>
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

                <div class="form-group">
                    <label>Email address to notify when new tickets are raised <small class="text-secondary">(Ideally a distribution list/shared mailbox)</small></label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-bell"></i></span>
                        </div>
                        <input type="email" class="form-control" name="config_ticket_new_ticket_notification_email" placeholder="Address to notify for new tickets, leave bank for none" value="<?php echo nullable_htmlentities($config_ticket_new_ticket_notification_email); ?>">
                    </div>
                </div>

                <hr>

                <button type="submit" name="edit_ticket_settings" class="btn btn-primary text-bold"><i class="fas fa-check mr-2"></i>Save</button>

            </form>
        </div>
    </div>

<?php
require_once "footer.php";

