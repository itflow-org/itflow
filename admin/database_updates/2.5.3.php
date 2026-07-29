<?php

/*
 * ITFlow - Database update to version 2.5.3 (from 2.5.2)
 * Included by admin/database_updates.php - do not access directly
 */

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

    // Asset note types
    // The asset_notes table itself already ships in db.sql (and picked up its
    // foreign key back in 2.0.2) - it was simply never wired to a UI, so the
    // only thing missing for multiple notes per asset is the type list
    mysqli_query($mysqli, "INSERT INTO categories SET category_name = 'Maintenance', category_description = 'Routine or scheduled maintenance performed on the asset', category_icon = 'fa-tools', category_type = 'asset_note_type', category_order = 1"); // 1
    mysqli_query($mysqli, "INSERT INTO categories SET category_name = 'Repair', category_description = 'Repair work or hardware replacement', category_icon = 'fa-wrench', category_type = 'asset_note_type', category_order = 2"); // 2
    mysqli_query($mysqli, "INSERT INTO categories SET category_name = 'Configuration', category_description = 'Configuration or settings change made to the asset', category_icon = 'fa-sliders-h', category_type = 'asset_note_type', category_order = 3"); // 3
    mysqli_query($mysqli, "INSERT INTO categories SET category_name = 'Upgrade', category_description = 'Hardware or software upgrade', category_icon = 'fa-arrow-circle-up', category_type = 'asset_note_type', category_order = 4"); // 4
    mysqli_query($mysqli, "INSERT INTO categories SET category_name = 'Inspection', category_description = 'Physical inspection or audit of the asset', category_icon = 'fa-clipboard-check', category_type = 'asset_note_type', category_order = 5"); // 5
    mysqli_query($mysqli, "INSERT INTO categories SET category_name = 'Note', category_description = 'General note or internal comment', category_icon = 'fa-sticky-note', category_type = 'asset_note_type', category_order = 6"); // 6
