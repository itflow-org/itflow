<?php

/*
 * Admin > Settings > Notifications (admin/settings_notification.php):
 * edit_notification_settings saves the domain alert, what agents are told about
 * their tickets, and the emails and texts clients get by themselves. Reminders aren't set here (they moved to Invoices and
 * billing with their schedule), nor cron (Maintenance > Cron). A switch for a
 * module that is off isn't on the page, so is kept as it is.
 */

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

if (isset($_POST['edit_notification_settings'])) {

    validateCSRFToken();

    $sms_columns = smsColumnExists('settings', 'config_sms_client_tickets') ? ['config_sms_client_tickets', 'config_sms_client_visits', 'config_sms_visit_reminder_hours', 'config_sms_client_invoices'] : [];
    // What agents are told about their tickets - from database 3.9.1 (ticketTeamNotifyOn())
    $team_columns = $config_module_enable_ticketing && version_compare(CURRENT_DATABASE_VERSION, '3.9.1', '>=') ? ['config_notify_assigned_app', 'config_notify_assigned_email', 'config_notify_helper_replies'] : [];
    // The weekly summary - from database 4.0.7 (functions/weekly_summary.php)
    $weekly_columns = weeklySummaryReady() ? ['config_weekly_summary', 'config_weekly_summary_day', 'config_weekly_summary_to'] : [];
    $before = settingsCurrent(array_merge(['config_enable_alert_domain_expire', 'config_ticket_client_general_notifications', 'config_recurring_auto_send_invoice'], $team_columns, $sms_columns, $weekly_columns));

    $after = ['config_enable_alert_domain_expire' => empty($_POST['config_enable_alert_domain_expire']) ? 0 : 1];
    if ($config_module_enable_ticketing) {
        $after['config_ticket_client_general_notifications'] = empty($_POST['config_ticket_client_general_notifications']) ? 0 : 1;
    }
    foreach ($team_columns as $column) {
        $after[$column] = empty($_POST[$column]) ? 0 : 1;
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

    if ($weekly_columns) {
        $after['config_weekly_summary'] = empty($_POST['config_weekly_summary']) ? 0 : 1;
        $day = intval($_POST['config_weekly_summary_day'] ?? $before['config_weekly_summary_day']);
        $after['config_weekly_summary_day'] = isset(WEEKLY_SUMMARY_DAYS[$day]) ? $day : intval($before['config_weekly_summary_day']);
        $after['config_weekly_summary_to'] = implode(', ', weeklySummaryAddresses($_POST['config_weekly_summary_to'] ?? ''));
    }

    settingsSave('notification', $before, $after);

    redirect();

}

if (isset($_POST['send_weekly_summary_now'])) {

    // The weekly summary for the seven days to yesterday, to the admin asking - to see what it looks like
    validateCSRFToken();

    if (!weeklySummaryReady() || !filter_var($session_email ?? '', FILTER_VALIDATE_EMAIL)) {
        flashAlert('There is nowhere to send it - your account has no email address.', 'error');
        redirect();
    }
    $sent = weeklySummarySend([['email' => $session_email, 'name' => $session_name]], date('Y-m-d'));
    flashAlert($sent ? 'The weekly summary is on its way to <strong>' . escapeHtml($session_email) . '</strong>.' : 'It could not be queued - check Admin &gt; Mail.', $sent ? 'success' : 'error');
    redirect();

}
