<?php

/*
 * ITFlow - Database update to version 2.8.0 (from 2.7.9)
 * Included by admin/database_updates.php - do not access directly
 */

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

    // Racks: unit numbering direction, a power budget and a photo of the back
    mysqli_query($mysqli, "ALTER TABLE `racks`
        ADD COLUMN `rack_units_descending` tinyint(1) NOT NULL DEFAULT 0 AFTER `rack_units`,
        ADD COLUMN `rack_power_capacity` int(11) DEFAULT NULL AFTER `rack_units_descending`,
        ADD COLUMN `rack_photo_rear` varchar(200) DEFAULT NULL AFTER `rack_photo`
    ");

    // Rack units: which face an item is mounted on and whether it takes the full depth
    // (existing items default to front + full depth, which is exactly how the old
    // single-face overlap check treated them), what kind of item an unlinked one is,
    // its power draw and a short note. The asset key backs the "racked in" lookup on
    // the asset page and the clean-up when an asset is deleted or transferred.
    mysqli_query($mysqli, "ALTER TABLE `rack_units`
        ADD COLUMN `unit_face` varchar(10) NOT NULL DEFAULT 'front' AFTER `unit_device`,
        ADD COLUMN `unit_full_depth` tinyint(1) NOT NULL DEFAULT 1 AFTER `unit_face`,
        ADD COLUMN `unit_type` varchar(50) DEFAULT NULL AFTER `unit_full_depth`,
        ADD COLUMN `unit_power_watts` int(11) DEFAULT NULL AFTER `unit_type`,
        ADD COLUMN `unit_notes` varchar(255) DEFAULT NULL AFTER `unit_power_watts`,
        ADD KEY `unit_asset_id` (`unit_asset_id`)
    ");

    // Unlinked items were stored with an asset id of 0 rather than NULL - normalise,
    // so "linked to an asset" is one test everywhere
    mysqli_query($mysqli, "UPDATE `rack_units` SET `unit_asset_id` = NULL WHERE `unit_asset_id` = 0");

    // Items whose asset was deleted were left behind pointing at nothing. Keep the
    // slot (someone racked something there) but drop the dead link.
    mysqli_query($mysqli, "UPDATE `rack_units` LEFT JOIN `assets` ON `asset_id` = `unit_asset_id`
        SET `unit_asset_id` = NULL, `unit_device` = COALESCE(NULLIF(`unit_device`, ''), 'Removed asset')
        WHERE `unit_asset_id` IS NOT NULL AND `asset_id` IS NULL
    ");

    // Give the unlinked items already in racks a type where their name makes it
    // obvious, so a patch panel draws as one instead of as a generic device
    $rack_unit_type_guesses = [
        'Patch Panel' => '%patch%panel%',
        'Cable Management' => '%cable%manage%',
        'Blanking Panel' => '%blank%',
        'Shelf' => '%shelf%',
    ];
    foreach ($rack_unit_type_guesses as $rack_unit_type => $rack_unit_pattern) {
        mysqli_query($mysqli, "UPDATE `rack_units` SET `unit_type` = '$rack_unit_type'
            WHERE `unit_type` IS NULL AND `unit_asset_id` IS NULL AND `unit_device` LIKE '$rack_unit_pattern'
        ");
    }
    mysqli_query($mysqli, "UPDATE `rack_units` SET `unit_type` = 'Device' WHERE `unit_type` IS NULL AND `unit_asset_id` IS NULL");
