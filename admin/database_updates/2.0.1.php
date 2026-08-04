<?php

/*
 * ITFlow - Database update to version 2.0.1 (from 2.0.0)
 * Included by admin/database_updates.php - do not access directly
 */

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

    //Dropping patch panel as a patch panel can be documented as an asset with interfaces.
    mysqli_query($mysqli, "DROP TABLE `patch_panel_ports`");
    mysqli_query($mysqli, "DROP TABLE `patch_panels`");

    mysqli_query($mysqli, "RENAME TABLE `events` TO `calendar_events`");
    mysqli_query($mysqli, "RENAME TABLE `event_attendees` TO `calendar_event_attendees`");
