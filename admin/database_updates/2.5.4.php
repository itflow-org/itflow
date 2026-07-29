<?php

/*
 * ITFlow - Database update to version 2.5.4 (from 2.5.3)
 * Included by admin/database_updates.php - do not access directly
 */

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

    // Recurring tickets can now carry a ticket template. The template's subject
    // and details are copied into the recurring ticket when it is picked, so the
    // link exists for one reason only: to stamp the template's task list onto
    // every ticket the schedule raises. 0 means no template, matching the other
    // optional relations on this table.
    mysqli_query($mysqli, "ALTER TABLE `recurring_tickets`
        ADD COLUMN `recurring_ticket_ticket_template_id` int(11) NOT NULL DEFAULT 0 AFTER `recurring_ticket_asset_id`");
