<?php
require_once "includes/inc_all_admin.php";
 ?>

    <div class="card card-dark">
        <div class="card-header py-3">
            <h3 class="card-title"><i class="fas fa-fw fa-life-ring mr-2"></i>Ticket Settings</h3>
        </div>
        <div class="card-body">
            <form action="post.php" method="post" autocomplete="off">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?>">

                <div class="form-group">
                    <label>Ticket Prefix</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-life-ring"></i></span>
                        </div>
                        <input type="text" class="form-control" name="config_ticket_prefix" placeholder="Ticket Prefix" value="<?php echo nullable_htmlentities($config_ticket_prefix); ?>" pattern="^[A-Za-z-]+$" title="Only letters and hyphens are allowed" required>
                    </div>
                </div>

                <div class="form-group">
                    <label>Next Number</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-barcode"></i></span>
                        </div>
                        <input type="number" min="<?php echo intval($config_ticket_next_number); ?>" class="form-control" name="config_ticket_next_number" placeholder="Next Ticket Number" value="<?php echo intval($config_ticket_next_number); ?>" required>
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
                        <input type="checkbox" class="custom-control-input" name="config_ticket_email_parse_unknown_senders" <?php if($config_ticket_email_parse_unknown_senders == 1){ echo "checked"; } ?> value="1" id="emailToTicketAnonParseSwitch" <?php if($config_ticket_email_parse == 0){ echo "disabled"; } ?>>
                        <label class="custom-control-label" for="emailToTicketAnonParseSwitch">Create tickets for emails from unknown senders/domains <small class="text-secondary">(Enable to ensure all emails automatically create tickets)</small></label>
                    </div>
                </div>

                <?php if ($config_module_enable_accounting) { ?>
                <div class="form-group">
                    <div class="custom-control custom-switch">
                        <input type="checkbox" class="custom-control-input" name="config_ticket_default_billable" <?php if ($config_ticket_default_billable == 1) { echo "checked"; } ?> value="1" id="ticketBillableSwitch">
                        <label class="custom-control-label" for="ticketBillableSwitch">Default to Billable <small class="text-secondary">(This will check the billable box on all new tickets)</small></label>
                    </div>
                </div>
                <?php } ?>

                <div class="form-group">
                    <div class="custom-control custom-switch">
                        <input type="checkbox" class="custom-control-input" name="config_ticket_timer_autostart" <?php if ($config_ticket_timer_autostart == 1) { echo "checked"; } ?> value="1" id="ticketTimerSwitch">
                        <label class="custom-control-label" for="ticketTimerSwitch">Autostart Ticket Timer <small class="text-secondary">(This option will control if the timer starts automatically or manually)</small></label>
                    </div>
                </div>

                <div class="form-group">
                    <label>Number of hours to auto close resolved tickets</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-clock"></i></span>
                        </div>
                        <input type="number" min="72" class="form-control" name="config_ticket_autoclose_hours" placeholder="Delay in hours before a resolved ticket is fully closed" value="<?php echo intval($config_ticket_autoclose_hours); ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label>Email address to notify when new tickets are raised <small class="text-secondary">(Ideally a distribution list/shared mailbox)</small></label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-bell"></i></span>
                        </div>
                        <input type="email" class="form-control" name="config_ticket_new_ticket_notification_email" placeholder="Address to notify for new tickets, leave blank for none" value="<?php echo nullable_htmlentities($config_ticket_new_ticket_notification_email); ?>">
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Tickets Default View</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-eye"></i></span>
                        </div>
                        <select class="form-control" name="config_ticket_default_view">
                            <option value=0 <?php if ($config_ticket_default_view == 0) { echo "selected"; } ?>>List</option>
                            <option value=1 <?php if ($config_ticket_default_view == 1) { echo "selected"; } ?>>Compact</option>
                            <option value=2 <?php if ($config_ticket_default_view == 2) { echo "selected"; } ?>>Kanban</option>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                <label>Kanban Settings</label>
                    <div class="custom-control custom-switch">
                        <input type="checkbox" class="custom-control-input" name="config_ticket_ordering" <?php if ($config_ticket_ordering == 1) { echo "checked"; } ?> value="1" id="ticketOrderingSwitch">
                        <label class="custom-control-label" for="ticketOrderingSwitch">Allow ticket ordering within its column<small class="text-secondary">(uncheked will result in ordering it by priority and id)</small></label>
                    </div>
                    <div class="custom-control custom-switch">
                    <input type="checkbox" class="custom-control-input" name="config_ticket_moving_columns" <?php if ($config_ticket_moving_columns == 1) { echo "checked"; } ?> value="1" id="ticketMovingColumnsSwitch">
                        <label class="custom-control-label" for="ticketMovingColumnsSwitch">Allow moving columns</label>
                    </div>
                </div>

                <hr>

                <button type="submit" name="edit_ticket_settings" class="btn btn-primary text-bold"><i class="fas fa-check mr-2"></i>Save</button>

            </form>
        </div>
    </div>

<?php
require_once "../includes/footer.php";

