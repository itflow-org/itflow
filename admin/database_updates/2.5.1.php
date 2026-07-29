<?php

/*
 * ITFlow - Database update to version 2.5.1 (from 2.5.0)
 * Included by admin/database_updates.php - do not access directly
 */

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

    // SLA pausing. sla_history records the intervals during which a ticket's
    // resolution clock was actually running - one open row (ended_at NULL) per
    // running ticket. Statuses flagged below stop the clock, and the ticket's
    // resolution due date is recomputed from the remaining budget on resume.
    mysqli_query($mysqli, "CREATE TABLE `sla_history` (
        `sla_history_id` int(11) NOT NULL AUTO_INCREMENT,
        `sla_history_started_at` datetime NOT NULL,
        `sla_history_ended_at` datetime DEFAULT NULL,
        `sla_history_minutes` int(11) DEFAULT NULL,
        `sla_history_ticket_id` int(11) NOT NULL,
        PRIMARY KEY (`sla_history_id`),
        KEY `sla_history_ticket_id` (`sla_history_ticket_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

    // Which statuses pause the resolution clock. Nothing pauses by default, so
    // SLA behaviour is unchanged until an admin opts a status in.
    mysqli_query($mysqli, "ALTER TABLE `ticket_statuses`
        ADD COLUMN `ticket_status_pauses_sla` tinyint(1) NOT NULL DEFAULT 0 AFTER `ticket_status_active`");

    // Backfill an open interval for every ticket already running a resolution
    // clock, anchored at creation. Without this their consumed time would read
    // as zero and the first pause/resume would hand back the full budget.
    mysqli_query($mysqli, "INSERT INTO sla_history (sla_history_started_at, sla_history_ticket_id)
        SELECT ticket_created_at, ticket_id
        FROM tickets
        LEFT JOIN slas ON ticket_sla_id = sla_id
        WHERE ticket_sla_id > 0
        AND sla_resolution_minutes > 0
        AND ticket_resolution_due_at IS NOT NULL
        AND ticket_resolved_at IS NULL
        AND ticket_closed_at IS NULL
        AND ticket_archived_at IS NULL");
