<?php

/*
 * ITFlow - Database update to version 3.6.9 (from 3.6.8)
 * Included by admin/database_updates.php - do not access directly
 *
 * RMM device actions (functions/rmm.php, Device actions):
 *   rmm_providers.rmm_provider_actions - whether technicians can reboot a provider's devices,
 *     install their patches, turn their maintenance mode on and off and run its scripts on
 *     them from ITFlow; off for every provider until an admin turns it on
 *
 * Re-runnable: the column is made only where it isn't there yet.
 */

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

    $itflow_column_exists = mysqli_query($mysqli, "SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rmm_providers' AND COLUMN_NAME = 'rmm_provider_actions' LIMIT 1");
    if (!$itflow_column_exists || mysqli_num_rows($itflow_column_exists) === 0) {
        mysqli_query($mysqli, "ALTER TABLE `rmm_providers` ADD `rmm_provider_actions` tinyint(1) NOT NULL DEFAULT 0 AFTER `rmm_provider_alerts_note`");
    }
