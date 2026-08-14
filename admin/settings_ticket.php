<?php
require_once "includes/inc_all_admin.php";
 ?>

    <div class="card card-dark">
        <div class="card-header py-3">
            <h3 class="card-title"><i class="fas fa-fw fa-life-ring me-2"></i>Ticket Settings</h3>
        </div>
        <div class="card-body">
            <form action="post.php" method="post" autocomplete="off">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

                <div class="mb-3">
                    <label>Ticket Prefix</label>
                    <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-life-ring"></i></span>
                        <input type="text" class="form-control" name="config_ticket_prefix" placeholder="Ticket Prefix" maxlength="200" value="<?= escapeHtml($config_ticket_prefix) ?>" pattern="^[A-Za-z-]+$" title="Only letters and hyphens are allowed" required>
                    </div>
                </div>

                <div class="mb-3">
                    <label>Next Number</label>
                    <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-barcode"></i></span>
                        <input type="number" min="<?= intval($config_ticket_next_number) ?>" class="form-control" name="config_ticket_next_number" placeholder="Next Ticket Number" value="<?= intval($config_ticket_next_number) ?>" required>
                    </div>
                </div>

                <div class="mb-3">
                    <div class="form-check form-switch">
                        <input type="checkbox" class="form-check-input" name="config_ticket_email_parse" <?php if($config_ticket_email_parse == 1){ echo "checked"; } ?> value="1" id="emailToTicketParseSwitch">
                        <label class="form-check-label" for="emailToTicketParseSwitch">Email-to-ticket parsing <small class="text-secondary">(the Ticket Email Parser cron job must also be enabled - see <a href="cron.php">Maintenance &gt; Cron</a>)</small></label>
                    </div>
                </div>

                <div class="mb-3">
                    <div class="form-check form-switch">
                        <input type="checkbox" class="form-check-input" name="config_ticket_email_parse_unknown_senders" <?php if($config_ticket_email_parse_unknown_senders == 1){ echo "checked"; } ?> value="1" id="emailToTicketAnonParseSwitch" <?php if($config_ticket_email_parse == 0){ echo "disabled"; } ?>>
                        <label class="form-check-label" for="emailToTicketAnonParseSwitch">Create tickets for emails from unknown senders/domains <small class="text-secondary">(Enable to ensure all emails automatically create tickets)</small></label>
                    </div>
                </div>

                <?php if ($config_module_enable_accounting) { ?>
                <div class="mb-3">
                    <div class="form-check form-switch">
                        <input type="checkbox" class="form-check-input" name="config_ticket_default_billable" <?php if ($config_ticket_default_billable == 1) { echo "checked"; } ?> value="1" id="ticketBillableSwitch">
                        <label class="form-check-label" for="ticketBillableSwitch">Default to Billable <small class="text-secondary">(This will check the billable box on all new tickets)</small></label>
                    </div>
                </div>
                <?php } ?>

                <div class="mb-3">
                    <div class="form-check form-switch">
                        <input type="checkbox" class="form-check-input" name="config_ticket_timer_autostart" <?php if ($config_ticket_timer_autostart == 1) { echo "checked"; } ?> value="1" id="ticketTimerSwitch">
                        <label class="form-check-label" for="ticketTimerSwitch">Autostart Ticket Timer <small class="text-secondary">(This option will control if the timer starts automatically or manually)</small></label>
                    </div>
                </div>

                <div class="mb-3">
                    <label>Number of hours to auto close resolved tickets</label>
                    <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-clock"></i></span>
                        <input type="number" min="24" class="form-control" name="config_ticket_autoclose_hours" placeholder="Delay in hours before a resolved ticket is fully closed" value="<?= intval($config_ticket_autoclose_hours) ?>">
                    </div>
                </div>

                <div class="mb-3">
                    <label>Email address to notify when new tickets are raised <small class="text-secondary">(Ideally a distribution list/shared mailbox)</small></label>
                    <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-bell"></i></span>
                        <input type="email" class="form-control" name="config_ticket_new_ticket_notification_email" placeholder="Address to notify for new tickets, leave blank for none" maxlength="200" value="<?= escapeHtml($config_ticket_new_ticket_notification_email) ?>">
                    </div>
                </div>
                
                <div class="mb-3">
                    <label>Tickets Default View</label>
                    <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-eye"></i></span>
                        <select class="form-control" name="config_ticket_default_view">
                            <option value=0 <?php if ($config_ticket_default_view == 0) { echo "selected"; } ?>>List</option>
                            <option value=1 <?php if ($config_ticket_default_view == 1) { echo "selected"; } ?>>Compact</option>
                            <option value=2 <?php if ($config_ticket_default_view == 2) { echo "selected"; } ?>>Kanban</option>
                        </select>
                    </div>
                </div>
                <div class="mb-3">
                <label>Kanban Settings</label>
                    <div class="form-check form-switch">
                        <input type="checkbox" class="form-check-input" name="config_ticket_ordering" <?php if ($config_ticket_ordering == 1) { echo "checked"; } ?> value="1" id="ticketOrderingSwitch">
                        <label class="form-check-label" for="ticketOrderingSwitch">Allow ticket ordering within its column<small class="text-secondary"> (unchecked = order by priority and id)</small></label>
                    </div>
                    <div class="form-check form-switch">
                    <input type="checkbox" class="form-check-input" name="config_ticket_moving_columns" <?php if ($config_ticket_moving_columns == 1) { echo "checked"; } ?> value="1" id="ticketMovingColumnsSwitch">
                        <label class="form-check-label" for="ticketMovingColumnsSwitch">Allow moving columns</label>
                    </div>
                </div>

                <hr>

                <button type="submit" name="edit_ticket_settings" class="btn btn-primary text-bold"><i class="fas fa-check me-2"></i>Save</button>

            </form>
        </div>
    </div>

<?php
require_once "../includes/footer.php";

