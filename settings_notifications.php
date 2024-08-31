<?php

require_once "inc_all_admin.php";

?>

    <div class="card card-dark">
        <div class="card-header py-3">
            <h3 class="card-title"><i class="fas fa-fw fa-bell mr-2"></i>Notifications</h3>
        </div>
        <div class="card-body">
            <form action="post.php" method="post" autocomplete="off">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?>">
                <input type="hidden" name="config_ticket_client_general_notifications" value="0">
                <input type="hidden" name="config_enable_cron" value="0">
                <input type="hidden" name="config_enable_alert_domain_expire" value="0">
                <input type="hidden" name="config_send_invoice_reminders" value="0">
                <input type="hidden" name="config_recurring_auto_send_invoice" value="0">

                <div class="form-group">
                    <div class="custom-control custom-switch">
                        <input type="checkbox" class="custom-control-input" name="config_enable_cron" <?php if ($config_enable_cron == 1) { echo "checked"; } ?> value="1" id="enableCronSwitch">
                        <label class="custom-control-label" for="enableCronSwitch">Enable Cron (recommended) <small>(several cron scripts must also be added to cron with correct schedules, <a href="https://docs.itflow.org/cron">docs</a>)</small></label>
                    </div>
                </div>

                <div class="form-group">
                    <label>Cron Key</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-key"></i></span>
                        </div>
                        <input type="text" class="form-control" name="config_cron_key" placeholder="Generate a CRON Key" value="<?php echo nullable_htmlentities($config_cron_key); ?>" readonly>
                        <div class="input-group-append">
                            <a href="post.php?generate_cron_key" class="btn btn-secondary confirm-link"><i class="fas fa-fw fa-sync mr-2"></i>Generate</a>
                        </div>
                    </div>
                </div>

                <table class="table table-bordered">
                    <thead class="thead-dark">
                        <tr>
                            <th>Notification</th>
                            <th>App Notify</th>
                            <th>Tech Email Notify</th>
                            <th>Client Email Notify</th>
                            <th>Create Ticket</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <th>
                                <div><i class="fas fa-fw fa-globe mr-2"></i>Domain Expiration Notice</div>
                                <small class="text-muted">
                                    (This setting triggers a notification when a domain is approaching its expiration date, specifically at 1, 7, 14, 30, 90, and 120 days prior to expiry.)
                                </small>
                            </th>
                            <td>
                                <div class="custom-control custom-checkbox text-center">
                                  <input type="checkbox" class="custom-control-input" name="config_enable_alert_domain_expire" id="customCheck1" <?php if ($config_enable_alert_domain_expire == 1) { echo "checked"; } ?> value="1">
                                  <label class="custom-control-label" for="customCheck1"></label>      
                                </div>
                            </td>
                            <td></td>
                            <td></td>
                            <td></td>
                        </tr>
                        <tr>
                            <th>
                                <div><i class="fas fa-fw fa-file-invoice mr-2"></i>Invoice Reminders</div>
                                <small class="text-muted">
                                    (This will automatically dispatch a reminder email for the invoice to the primary contact's email every 30 days following the invoice's due date.)
                                </small>
                            </th>
                            <td>
                                
                            </td>
                            <td></td>
                            <td>
                                <div class="custom-control custom-checkbox text-center">
                                    <input type="checkbox" class="custom-control-input" name="config_send_invoice_reminders" <?php if ($config_send_invoice_reminders == 1) { echo "checked"; } ?> value="1" id="sendInvoiceRemindersSwitch">
                                    <label class="custom-control-label" for="sendInvoiceRemindersSwitch"></label>
                                </div>
                            </td>
                            <td></td>
                        </tr>
                        <tr>
                            <th>
                                <div><i class="fas fa-fw fa-redo-alt mr-2"></i>Send Recurring Invoice</div>
                                <small class="text-muted">
                                    (This will notify all primary and billing contacts of a client that a new invoice was generated from recurring invoices)
                                </small>
                            </th>
                            <td>
                                
                            </td>
                            <td></td>
                            <td>
                                <div class="custom-control custom-checkbox text-center">
                                    <input type="checkbox" class="custom-control-input" name="config_recurring_auto_send_invoice" <?php if ($config_recurring_auto_send_invoice == 1) { echo "checked"; } ?> value="1" id="sendRecurringSwitch">
                                    <label class="custom-control-label" for="sendRecurringSwitch"></label>
                                </div>
                            </td>
                            <td></td>
                        </tr>
                        <tr>
                            <th>
                                <div><i class="fas fa-fw fa-bell mr-2"></i>Send clients general notification emails</div>
                                <small class="text-secondary">(Should clients receive automatic emails when tickets are raised/closed?)</small>
                            </th>
                            <td></td>
                            <td></td>
                            <td>
                                <div class="custom-control custom-checkbox text-center">
                                    <input type="checkbox" class="custom-control-input" name="config_ticket_client_general_notifications" <?php if($config_ticket_client_general_notifications == 1){ echo "checked"; } ?> value="1" id="ticketNotificationSwitch">
                                    <label class="custom-control-label" for="ticketNotificationSwitch"></label>
                                </div>
                            </td>
                            <td></td>
                        </tr>
                    </tbody>
                </table>

                <hr>

                <button type="submit" name="edit_notification_settings" class="btn btn-primary text-bold"><i class="fa fa-check mr-2"></i>Save</button>

            </form>
        </div>
    </div>

<?php
require_once "footer.php";
