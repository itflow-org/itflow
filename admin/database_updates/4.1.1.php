<?php

/*
 * ITFlow - Database update to version 4.1.1 (from 4.1.0)
 * Included by admin/database_updates.php - do not access directly
 *
 * Your own asset types, from Admin > Asset Types - a Badge Reader, a Vehicle,
 * a Hypervisor - each with its icon, colour, group (its tab on the assets
 * list, the client overview and the portal), the standard fields it asks for,
 * the details it has and whether it goes in a rack. A row for one of ITFlow's
 * own types (asset_type_builtin = 1) only says whether it is switched on;
 * everything else about it is ASSET_TYPES in functions/asset_type.php.
 *
 *   asset_types.asset_type_name        what assets.asset_type holds for it
 *   asset_types.asset_type_fields      the standard fields it asks for, comma separated (ASSET_FIELDS)
 *   asset_types.asset_type_specs       ITFlow's details it has, comma separated (ASSET_SPECS)
 *   asset_types.asset_type_base        the built-in type it was started from - the form's examples
 *
 * Its own details are asset_type_details, in order - a value is kept in
 * asset_specs under the key custom_<asset_type_detail_id>.
 *
 *   asset_type_details.asset_type_detail_input    text, number, date or select
 *   asset_type_details.asset_type_detail_options  a select's choices, one a line
 *   asset_type_details.asset_type_detail_flag     a date flagged as it nears, like a warranty
 *   asset_type_details.asset_type_detail_unit_only  belongs to the one asset - not copied with it
 */

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

    mysqli_query($mysqli, "CREATE TABLE IF NOT EXISTS `asset_types` (
        `asset_type_id` int(11) NOT NULL AUTO_INCREMENT,
        `asset_type_name` varchar(200) NOT NULL,
        `asset_type_builtin` tinyint(1) NOT NULL DEFAULT 0,
        `asset_type_active` tinyint(1) NOT NULL DEFAULT 1,
        `asset_type_icon` varchar(50) NOT NULL DEFAULT 'fa-tag',
        `asset_type_tone` varchar(20) NOT NULL DEFAULT 'other',
        `asset_type_group` varchar(20) NOT NULL DEFAULT 'other',
        `asset_type_base` varchar(200) DEFAULT NULL,
        `asset_type_os` varchar(10) NOT NULL DEFAULT 'os',
        `asset_type_fields` varchar(255) NOT NULL DEFAULT '',
        `asset_type_specs` varchar(1000) NOT NULL DEFAULT '',
        `asset_type_rack` tinyint(1) NOT NULL DEFAULT 0,
        `asset_type_description` varchar(255) DEFAULT NULL,
        `asset_type_created_at` datetime NOT NULL DEFAULT current_timestamp(),
        `asset_type_updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
        PRIMARY KEY (`asset_type_id`),
        UNIQUE KEY `asset_type_name` (`asset_type_name`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

    mysqli_query($mysqli, "CREATE TABLE IF NOT EXISTS `asset_type_details` (
        `asset_type_detail_id` int(11) NOT NULL AUTO_INCREMENT,
        `asset_type_detail_asset_type_id` int(11) NOT NULL,
        `asset_type_detail_label` varchar(100) NOT NULL,
        `asset_type_detail_input` varchar(10) NOT NULL DEFAULT 'text',
        `asset_type_detail_unit` varchar(20) DEFAULT NULL,
        `asset_type_detail_options` varchar(2000) DEFAULT NULL,
        `asset_type_detail_flag` tinyint(1) NOT NULL DEFAULT 0,
        `asset_type_detail_unit_only` tinyint(1) NOT NULL DEFAULT 0,
        `asset_type_detail_order` int(11) NOT NULL DEFAULT 0,
        PRIMARY KEY (`asset_type_detail_id`),
        KEY `asset_type_detail_asset_type_id` (`asset_type_detail_asset_type_id`),
        CONSTRAINT `asset_type_details_type` FOREIGN KEY (`asset_type_detail_asset_type_id`) REFERENCES `asset_types` (`asset_type_id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
