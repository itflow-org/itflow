<?php

/*
 * ITFlow - Database update to version 4.1.3 (from 4.1.2)
 * Included by admin/database_updates.php - do not access directly
 *
 * More than one device on a rack row - two Tiny PCs in a 1U bracket, eight PLCs on
 * a shelf, a media converter chassis. A holder is a rack item that devices go in
 * side by side; each device is a rack item of its own, in one of the holder's slots.
 *
 *   rack_units.unit_slots       how many fit side by side, on a holder - 0 for any other item.
 *                               Devices past that wrap onto another row, for stacks.
 *   rack_units.unit_parent_id   the holder a device sits in, NULL for one in the rack itself.
 *                               Removing the holder takes its devices out with it.
 *   rack_units.unit_position    the device's slot in its holder - 1 is the first, counting across
 *                               and then a row on.
 *
 * A device in a holder keeps the holder's units, face and depth in its own columns, so
 * whatever reads where something is racked reads it the same way.
 *
 * Re-runnable: it only adds what isn't there.
 */

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

    $itflow_has = function ($what, $name) use ($mysqli) {
        $table = $what === 'column' ? 'COLUMNS' : ($what === 'key' ? 'STATISTICS' : 'TABLE_CONSTRAINTS');
        $field = $what === 'column' ? 'COLUMN_NAME' : ($what === 'key' ? 'INDEX_NAME' : 'CONSTRAINT_NAME');
        $schema = $what === 'constraint' ? 'CONSTRAINT_SCHEMA' : 'TABLE_SCHEMA';
        $result = mysqli_query($mysqli, "SELECT 1 FROM information_schema.$table
            WHERE $schema = DATABASE() AND TABLE_NAME = 'rack_units' AND $field = '$name' LIMIT 1");
        return $result && mysqli_num_rows($result) > 0;
    };

    if (!$itflow_has('column', 'unit_slots')) {
        mysqli_query($mysqli, "ALTER TABLE `rack_units` ADD `unit_slots` int(11) NOT NULL DEFAULT 0 AFTER `unit_notes`");
    }
    if (!$itflow_has('column', 'unit_position')) {
        mysqli_query($mysqli, "ALTER TABLE `rack_units` ADD `unit_position` int(11) NOT NULL DEFAULT 0 AFTER `unit_slots`");
    }
    if (!$itflow_has('column', 'unit_parent_id')) {
        mysqli_query($mysqli, "ALTER TABLE `rack_units` ADD `unit_parent_id` int(11) DEFAULT NULL AFTER `unit_rack_id`");
    }
    if (!$itflow_has('key', 'unit_parent_id')) {
        mysqli_query($mysqli, "ALTER TABLE `rack_units` ADD KEY `unit_parent_id` (`unit_parent_id`)");
    }
    if (!$itflow_has('constraint', 'rack_units_ibfk_2')) {
        mysqli_query($mysqli, "ALTER TABLE `rack_units` ADD CONSTRAINT `rack_units_ibfk_2` FOREIGN KEY (`unit_parent_id`) REFERENCES `rack_units` (`unit_id`) ON DELETE CASCADE");
    }

    unset($itflow_has);
