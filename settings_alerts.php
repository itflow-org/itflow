<?php
require_once("inc_all_settings.php"); ?>

    <div class="card card-dark">
        <div class="card-header py-3">
            <h3 class="card-title"><i class="fas fa-fw fa-bell mr-2"></i>Alerts</h3>
        </div>
        <div class="card-body">
            <form action="post.php" method="post" autocomplete="off">

                <div class="form-group">
                    <div class="custom-control custom-switch">
                        <input type="checkbox" class="custom-control-input" name="config_enable_cron" <?php if ($config_enable_cron == 1) { echo "checked"; } ?> value="1" id="enableCronSwitch">
                        <label class="custom-control-label" for="enableCronSwitch">Enable Cron <small>(cron.php must also be added to cron and run daily at 1:00AM for alerts to work)</small></label>
                    </div>
                </div>

                <div class="form-group">
                    <label>Cron Key</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-key"></i></span>
                        </div>
                        <input type="text" class="form-control" name="config_cron_key" placeholder="Generate a CRON Key" value="<?php echo htmlentities($config_cron_key); ?>" readonly>
                        <div class="input-group-append">
                            <a href="post.php?generate_cron_key" class="btn btn-secondary"><i class="fas fa-fw fa-sync mr-2"></i>Generate</a>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <div class="custom-control custom-switch">
                        <input type="checkbox" class="custom-control-input" name="config_enable_alert_domain_expire" <?php if ($config_enable_alert_domain_expire == 1) { echo "checked"; } ?> value="1" id="alertDomainExpireSwitch">
                        <label class="custom-control-label" for="alertDomainExpireSwitch">Enable Domain Expiration Alerts</label>
                    </div>
                </div>

                <div class="form-group">
                    <div class="custom-control custom-switch">
                        <input type="checkbox" class="custom-control-input" name="config_send_invoice_reminders" <?php if ($config_send_invoice_reminders == 1) { echo "checked"; } ?> value="1" id="sendInvoiceRemindersSwitch">
                        <label class="custom-control-label" for="sendInvoiceRemindersSwitch">Send Invoice Reminders</label>
                    </div>
                </div>

                <?php if ($config_send_invoice_reminders == 1) { ?>

                    <div class="form-group">
                        <label>Overdue Reminders</label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fa fa-fw fa-bell"></i></span>
                            </div>
                            <input type="text" class="form-control" name="config_invoice_overdue_reminders" placeholder="Send After Due Days" value="<?php echo htmlentities($config_invoice_overdue_reminders); ?>">
                        </div>
                    </div>

                <?php } ?>

                <hr>

                <button type="submit" name="edit_alert_settings" class="btn btn-primary text-bold"><i class="fa fa-check mr-2"></i>Save</button>

            </form>
        </div>
    </div>

<?php
require_once("footer.php");
