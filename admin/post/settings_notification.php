<?php

/*
 * Admin > Settings > Notifications (admin/settings_notification.php):
 * edit_notification_settings saves the domain alert, and the emails and texts
 * clients get by themselves. Reminders aren't set here (they moved to Invoices and
 * billing with their schedule), nor cron (Maintenance > Cron). A switch for a
 * module that is off isn't on the page, so is kept as it is.
 */

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

if (isset($_POST['edit_notification_settings'])) {

    validateCSRFToken();

    $sms_columns = smsColumnExists('settings', 'config_sms_client_tickets') ? ['config_sms_client_tickets', 'config_sms_client_visits', 'config_sms_visit_reminder_hours', 'config_sms_client_invoices'] : [];
    $before = settingsCurrent(array_merge(['config_enable_alert_domain_expire', 'config_ticket_client_general_notifications', 'config_recurring_auto_send_invoice'], $sms_columns));

    $after = ['config_enable_alert_domain_expire' => empty($_POST['config_enable_alert_domain_expire']) ? 0 : 1];
    if ($config_module_enable_ticketing) {
        $after['config_ticket_client_general_notifications'] = empty($_POST['config_ticket_client_general_notifications']) ? 0 : 1;
    }
    if ($config_module_enable_accounting) {
        $after['config_recurring_auto_send_invoice'] = empty($_POST['config_recurring_auto_send_invoice']) ? 0 : 1;
    }
    // Texts to clients - once the database has them; a choice not on the list keeps what it is
    if ($sms_columns) {
        if ($config_module_enable_ticketing) {
            $tickets = intval($_POST['config_sms_client_tickets'] ?? $before['config_sms_client_tickets']);
            $after['config_sms_client_tickets'] = in_array($tickets, [0, 1, 2], true) ? $tickets : intval($before['config_sms_client_tickets']);
            $after['config_sms_client_visits'] = empty($_POST['config_sms_client_visits']) ? 0 : 1;
            $hours = intval($_POST['config_sms_visit_reminder_hours'] ?? $before['config_sms_visit_reminder_hours']);
            $after['config_sms_visit_reminder_hours'] = isset(smsReminderChoices()[$hours]) ? $hours : intval($before['config_sms_visit_reminder_hours']);
        }
        if ($config_module_enable_accounting) {
            $after['config_sms_client_invoices'] = empty($_POST['config_sms_client_invoices']) ? 0 : 1;
        }
    }

    settingsSave('notification', $before, $after);

    redirect();

}
