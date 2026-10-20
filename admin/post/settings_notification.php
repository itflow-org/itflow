<?php

/*
 * Admin > Settings > Notifications (admin/settings_notification.php):
 * edit_notification_settings saves the domain alert and the emails clients
 * get by themselves. Reminders aren't set here (they moved to Invoices and
 * billing with their schedule), nor cron (Maintenance > Cron). A switch for a
 * module that is off isn't on the page, so is kept as it is.
 */

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

if (isset($_POST['edit_notification_settings'])) {

    validateCSRFToken();

    $before = settingsCurrent(['config_enable_alert_domain_expire', 'config_ticket_client_general_notifications', 'config_recurring_auto_send_invoice']);

    $after = ['config_enable_alert_domain_expire' => empty($_POST['config_enable_alert_domain_expire']) ? 0 : 1];
    if ($config_module_enable_ticketing) {
        $after['config_ticket_client_general_notifications'] = empty($_POST['config_ticket_client_general_notifications']) ? 0 : 1;
    }
    if ($config_module_enable_accounting) {
        $after['config_recurring_auto_send_invoice'] = empty($_POST['config_recurring_auto_send_invoice']) ? 0 : 1;
    }

    settingsSave('notification', $before, $after);

    redirect();

}
