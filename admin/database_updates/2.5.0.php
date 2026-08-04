<?php

/*
 * ITFlow - Database update to version 2.5.0 (from 2.4.8)
 * Included by admin/database_updates.php - do not access directly
 *
 * (2.4.9 was briefly used by the reverted refunds change - skipping that
 * number so instances that applied it before the revert still pick this up)
 */

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

    // Ticket SLAs. slas holds the response/resolution targets and
    // sla_assignments maps a client + priority to an SLA (client 0 rows are
    // the global default, an assignment pointing at SLA 0 is an explicit "no
    // SLA" override). SLAs are optional - with no assignments defined, nothing
    // in the app changes behaviour.
    mysqli_query($mysqli, "CREATE TABLE `sla_assignments` (
        `sla_assignment_id` int(11) NOT NULL AUTO_INCREMENT,
        `sla_assignment_client_id` int(11) NOT NULL DEFAULT 0,
        `sla_assignment_priority` varchar(200) NOT NULL,
        `sla_assignment_sla_id` int(11) NOT NULL DEFAULT 0,
        PRIMARY KEY (`sla_assignment_id`),
        UNIQUE KEY `sla_assignment_client_priority` (`sla_assignment_client_id`,`sla_assignment_priority`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

    mysqli_query($mysqli, "CREATE TABLE `slas` (
        `sla_id` int(11) NOT NULL AUTO_INCREMENT,
        `sla_name` varchar(200) NOT NULL,
        `sla_description` varchar(500) DEFAULT NULL,
        `sla_response_minutes` int(11) NOT NULL,
        `sla_resolution_minutes` int(11) DEFAULT NULL,
        `sla_created_at` datetime NOT NULL DEFAULT current_timestamp(),
        `sla_archived_at` datetime DEFAULT NULL,
        PRIMARY KEY (`sla_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

    // SLA targets are computed once at write time and stored on the ticket, so
    // the ticket list and cron/ticket_sla.php only ever compare datetimes. The
    // due date indexes bound the cron's every-minute scans the same way the
    // 2.4.8 logs index bounded the login lockout queries.
    mysqli_query($mysqli, "ALTER TABLE `tickets`
        ADD COLUMN `ticket_sla_id` int(11) NOT NULL DEFAULT 0 AFTER `ticket_status`,
        ADD COLUMN `ticket_response_due_at` datetime DEFAULT NULL AFTER `ticket_first_response_at`,
        ADD COLUMN `ticket_resolution_due_at` datetime DEFAULT NULL AFTER `ticket_response_due_at`,
        ADD COLUMN `ticket_response_sla_met` tinyint(1) DEFAULT NULL AFTER `ticket_resolution_due_at`,
        ADD COLUMN `ticket_resolution_sla_met` tinyint(1) DEFAULT NULL AFTER `ticket_response_sla_met`,
        ADD COLUMN `ticket_response_sla_alert_stage` tinyint(1) NOT NULL DEFAULT 0 AFTER `ticket_resolution_sla_met`,
        ADD COLUMN `ticket_resolution_sla_alert_stage` tinyint(1) NOT NULL DEFAULT 0 AFTER `ticket_response_sla_alert_stage`,
        ADD INDEX `ticket_response_due_at` (`ticket_response_due_at`),
        ADD INDEX `ticket_resolution_due_at` (`ticket_resolution_due_at`)");

    // Business hours + SLA notification settings
    mysqli_query($mysqli, "ALTER TABLE `settings`
        ADD COLUMN `config_business_days` varchar(20) NOT NULL DEFAULT '1,2,3,4,5' AFTER `config_timezone`,
        ADD COLUMN `config_business_hours_start` time NOT NULL DEFAULT '09:00:00' AFTER `config_business_days`,
        ADD COLUMN `config_business_hours_end` time NOT NULL DEFAULT '17:00:00' AFTER `config_business_hours_start`,
        ADD COLUMN `config_sla_warning_percent` tinyint(3) NOT NULL DEFAULT 75 AFTER `config_business_hours_end`,
        ADD COLUMN `config_sla_notification_email` varchar(200) DEFAULT NULL AFTER `config_sla_warning_percent`");
