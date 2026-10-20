<?php

/*
 * Admin > Settings > Notifications - the alerts ITFlow raises for your team,
 * and the emails it sends clients by itself: about their tickets, and the
 * invoices recurring invoices make. Payment reminders and statements are set
 * with their schedule in Invoices and billing - shown here, not set. Saved by
 * edit_notification_settings (admin/post/settings_notification.php).
 */

require_once "includes/inc_all_admin.php";

echo settingsPageStart('notification');

?>

<form action="post.php" method="post" autocomplete="off" data-settings-form>
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

    <div class="card itflow-settings-card">
        <div class="card-body">

            <?= settingsSectionStart('alerts', 'Alerts for your team', 'In the bell at the top of the page, for everyone who can see the client - raised by the nightly job.') ?>
                <?= settingsSwitch('config_enable_alert_domain_expire', $config_enable_alert_domain_expire, ['help' => '45, 7 and 1 days before a domain expires - saying when one is set to auto-renew, to check it goes through.']) ?>
            <?= settingsSectionEnd() ?>

            <?= settingsSectionStart('clients', 'Emails to clients', 'What ITFlow emails your clients by itself. What an agent sends - a reply, an invoice, a quote - goes whatever is set here.') ?>
                <?php if (empty($config_smtp_provider)) { ?>
                    <div class="alert alert-warning py-2 small" data-notification-no-mail><i class="fas fa-exclamation-triangle me-2"></i>Mail sending is off, so none of these go. <a href="settings_mail.php?tab=sending">Set up sending</a></div>
                <?php } ?>
                <?php if ($config_module_enable_ticketing) { ?>
                    <?= settingsSwitch('config_ticket_client_general_notifications', $config_ticket_client_general_notifications, ['help' => 'The contact and watchers on a ticket hear when it is raised, scheduled, resolved and closed, and colleagues when one is raised at their company. Off, they still get the replies an agent sends.']) ?>
                <?php } ?>
                <?php if ($config_module_enable_accounting) { ?>
                    <?= settingsSwitch('config_recurring_auto_send_invoice', $config_recurring_auto_send_invoice, ['help' => 'Each recurring invoice set to send itself goes to the client\'s primary and billing contacts. Off, they are made as drafts to check and send.']) ?>
                    <div class="itflow-settings-field" data-setting="config_send_invoice_reminders" data-notification-reminders>
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge <?= $config_send_invoice_reminders ? 'text-bg-success' : 'text-bg-secondary' ?>"><?= $config_send_invoice_reminders ? 'On' : 'Off' ?></span>
                            <span>Payment reminders and statements</span>
                        </div>
                        <div class="form-text">On their own schedule, in <a href="settings_invoice.php#reminders">Invoices and billing</a>.</div>
                    </div>
                <?php } ?>
            <?= settingsSectionEnd() ?>

            <?= settingsSaveBar('edit_notification_settings') ?>
        </div>
    </div>
</form>

<?php
require_once "../includes/footer.php";
