<?php

/*
 * ITFlow - Database update to version 4.1.2 (from 4.1.1)
 * Included by admin/database_updates.php - do not access directly
 *
 * The asset type each kind of device an RMM brings in comes in as, picked in the provider's Edit:
 *
 *   rmm_providers.rmm_provider_asset_types   JSON of the type the sync guesses (Desktop, Laptop,
 *                                            Server, Virtual Machine) => the asset type to make it.
 *                                            NULL, or a kind it doesn't name, keeps the guessed type.
 *
 * Re-runnable: it only adds the column where it isn't.
 */

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

    $itflow_column = mysqli_query($mysqli, "SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rmm_providers' AND COLUMN_NAME = 'rmm_provider_asset_types' LIMIT 1");

    if ($itflow_column && mysqli_num_rows($itflow_column) === 0) {
        mysqli_query($mysqli, "ALTER TABLE `rmm_providers` ADD `rmm_provider_asset_types` varchar(1000) DEFAULT NULL AFTER `rmm_provider_alerts_note`");
    }
