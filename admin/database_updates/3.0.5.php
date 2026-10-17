<?php

/*
 * ITFlow - Database update to version 3.0.5 (from 3.0.4)
 * Included by admin/database_updates.php - do not access directly
 */

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

    // Other income (functions/income.php): who entered it, what it was for in
    // more than a line, and the dates the Income list and its totals run on
    mysqli_query($mysqli, "ALTER TABLE `revenues`
        MODIFY `revenue_description` text DEFAULT NULL,
        ADD COLUMN `revenue_created_by` int(11) NOT NULL DEFAULT 0 AFTER `revenue_client_id`,
        ADD KEY `revenue_date` (`revenue_date`)");
    mysqli_query($mysqli, "ALTER TABLE `payments` ADD KEY `payment_date` (`payment_date`)");

    // Who entered each, from the audit log where it says
    mysqli_query($mysqli, "UPDATE revenues JOIN logs ON log_type = 'Revenue' AND log_action = 'Create' AND log_entity_id = revenue_id
        SET revenue_created_by = log_user_id WHERE revenue_created_by = 0 AND log_user_id > 0");
