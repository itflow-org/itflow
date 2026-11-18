<?php

/*
 * ITFlow - Database update to version 3.9.1 (from 3.9.0)
 * Included by admin/database_updates.php - do not access directly
 *
 * Emails and notices to your team (Admin > Settings > Notifications, Emails
 * to your team - ticketTeamNotifyOn() in functions/ticket.php), each 1 (the
 * default - as before) or 0:
 *   settings.config_notify_assigned_app - the bell notice to an agent a
 *     ticket or recurring ticket is assigned to by someone else.
 *   settings.config_notify_assigned_email - the email to that agent.
 *   settings.config_notify_helper_replies - the copy of a client's reply to
 *     the agents helping on a ticket (the lead's own email is as before).
 *
 * Re-runnable: each column is added only where it isn't there yet.
 */

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

    $itflow_column = function ($table, $column) use ($mysqli) {
        $result = mysqli_query($mysqli, "SELECT 1 FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '$table' AND COLUMN_NAME = '$column' LIMIT 1");
        return $result && mysqli_num_rows($result) > 0;
    };

    if (!$itflow_column('settings', 'config_notify_assigned_app')) {
        mysqli_query($mysqli, "ALTER TABLE `settings` ADD `config_notify_assigned_app` tinyint(1) NOT NULL DEFAULT 1 AFTER `config_enable_alert_domain_expire`");
    }
    if (!$itflow_column('settings', 'config_notify_assigned_email')) {
        mysqli_query($mysqli, "ALTER TABLE `settings` ADD `config_notify_assigned_email` tinyint(1) NOT NULL DEFAULT 1 AFTER `config_notify_assigned_app`");
    }
    if (!$itflow_column('settings', 'config_notify_helper_replies')) {
        mysqli_query($mysqli, "ALTER TABLE `settings` ADD `config_notify_helper_replies` tinyint(1) NOT NULL DEFAULT 1 AFTER `config_notify_assigned_email`");
    }
