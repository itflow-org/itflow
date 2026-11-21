<?php

/*
 * ITFlow - Database update to version 3.9.9 (from 3.9.8)
 * Included by admin/database_updates.php - do not access directly
 *
 * rmm_providers.rmm_provider_actions goes. Develop builds before 26.12 added it (database 3.6.9)
 * for RMM device actions, which were taken out before release along with that migration. An
 * install updating from 26.11 never had it; one that ran a develop build still does, and the
 * health page lists it as an extra column. Nothing reads or writes it.
 *
 * Re-runnable: it only drops the column where it is.
 */

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

    $itflow_extra_column = mysqli_query($mysqli, "SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rmm_providers' AND COLUMN_NAME = 'rmm_provider_actions' LIMIT 1");

    if ($itflow_extra_column && mysqli_num_rows($itflow_extra_column) > 0) {
        mysqli_query($mysqli, "ALTER TABLE `rmm_providers` DROP `rmm_provider_actions`");
    }
