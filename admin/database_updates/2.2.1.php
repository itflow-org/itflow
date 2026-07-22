<?php

/*
 * ITFlow - Database update to version 2.2.1 (from 2.2.0)
 * Included by admin/database_updates.php - do not access directly
 */

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

    mysqli_query($mysqli, "ALTER TABLE `tickets` ADD `ticket_quote_id` INT(11) NOT NULL DEFAULT 0 AFTER `ticket_asset_id`");
