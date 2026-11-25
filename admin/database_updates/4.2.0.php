<?php

/*
 * ITFlow - Database update to version 4.2.0 (from 4.1.9)
 * Included by admin/database_updates.php - do not access directly
 *
 * How much a managed instance is used, on its history (functions/managed.php) - each day as
 * the instance counts it, by its own date; NULL for a day it didn't report it:
 *   managed_instance_stats._tickets_opened, _tickets_email, _tickets_resolved, _replies
 *     (agents'), _invoices, _payments, _agent_signins (agents who signed in), _portal_signins,
 *     _emails_sent, _emails_failed, _api_calls and _api_keys (JSON, calls under each key's
 *     name), and _agents_30d (agents who signed in over the 30 days to that day)
 *
 * Re-runnable: each column is made only where it isn't there yet.
 */

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

    $itflow_usage_columns = [
        'managed_stat_tickets_opened' => "int(11) DEFAULT NULL AFTER `managed_stat_outages`",
        'managed_stat_tickets_email' => "int(11) DEFAULT NULL AFTER `managed_stat_tickets_opened`",
        'managed_stat_tickets_resolved' => "int(11) DEFAULT NULL AFTER `managed_stat_tickets_email`",
        'managed_stat_replies' => "int(11) DEFAULT NULL AFTER `managed_stat_tickets_resolved`",
        'managed_stat_invoices' => "int(11) DEFAULT NULL AFTER `managed_stat_replies`",
        'managed_stat_payments' => "int(11) DEFAULT NULL AFTER `managed_stat_invoices`",
        'managed_stat_agent_signins' => "int(11) DEFAULT NULL AFTER `managed_stat_payments`",
        'managed_stat_portal_signins' => "int(11) DEFAULT NULL AFTER `managed_stat_agent_signins`",
        'managed_stat_emails_sent' => "int(11) DEFAULT NULL AFTER `managed_stat_portal_signins`",
        'managed_stat_emails_failed' => "int(11) DEFAULT NULL AFTER `managed_stat_emails_sent`",
        'managed_stat_api_calls' => "int(11) DEFAULT NULL AFTER `managed_stat_emails_failed`",
        'managed_stat_api_keys' => "text DEFAULT NULL AFTER `managed_stat_api_calls`",
        'managed_stat_agents_30d' => "int(11) DEFAULT NULL AFTER `managed_stat_api_keys`",
    ];
    foreach ($itflow_usage_columns as $itflow_usage_column => $itflow_usage_definition) {
        $itflow_column_exists = mysqli_query($mysqli, "SELECT 1 FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'managed_instance_stats' AND COLUMN_NAME = '$itflow_usage_column' LIMIT 1");
        if (!$itflow_column_exists || mysqli_num_rows($itflow_column_exists) === 0) {
            mysqli_query($mysqli, "ALTER TABLE `managed_instance_stats` ADD `$itflow_usage_column` $itflow_usage_definition");
        }
    }
