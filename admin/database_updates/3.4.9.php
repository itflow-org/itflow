<?php

/*
 * ITFlow - Database update to version 3.4.9 (from 3.4.8)
 * Included by admin/database_updates.php - do not access directly
 *
 * An asset's details - what only its kind of thing has: a switch's ports, a
 * UPS's battery, a VM's host (ASSET_TYPES and ASSET_SPECS in
 * functions/asset_type.php). One row a detail; nothing to fill in for the
 * assets already here, which start with none.
 *
 *   asset_specs.asset_spec_asset_id  the asset (ON DELETE CASCADE)
 *   asset_specs.asset_spec_key       which detail - ports, ram_gb, va...
 *   asset_specs.asset_spec_value     its value, as text
 */

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

    mysqli_query($mysqli, "CREATE TABLE IF NOT EXISTS `asset_specs` (
        `asset_spec_id` int(11) NOT NULL AUTO_INCREMENT,
        `asset_spec_asset_id` int(11) NOT NULL,
        `asset_spec_key` varchar(50) NOT NULL,
        `asset_spec_value` varchar(255) NOT NULL,
        PRIMARY KEY (`asset_spec_id`),
        UNIQUE KEY `asset_spec_asset_key` (`asset_spec_asset_id`, `asset_spec_key`),
        KEY `asset_spec_key` (`asset_spec_key`, `asset_spec_value`(20)),
        CONSTRAINT `asset_specs_asset` FOREIGN KEY (`asset_spec_asset_id`) REFERENCES `assets` (`asset_id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
