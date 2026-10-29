<?php

/*
 * ITFlow - Database update to version 3.0.3 (from 3.0.2)
 * Included by admin/database_updates.php - do not access directly
 */

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

    // Trips (functions/trips.php): the client's site it went to, the ticket it
    // was for, who logged it, miles as an exact number, and the end odometer
    // reading's misspelt column put right
    mysqli_query($mysqli, "ALTER TABLE `trips`
        CHANGE `trip_end_odmeter` `trip_end_odometer` int(11) DEFAULT NULL,
        MODIFY `trip_miles` decimal(10,1) NOT NULL DEFAULT 0.0,
        ADD COLUMN `trip_location_id` int(11) NOT NULL DEFAULT 0 AFTER `trip_client_id`,
        ADD COLUMN `trip_ticket_id` int(11) NOT NULL DEFAULT 0 AFTER `trip_location_id`,
        ADD COLUMN `trip_created_by` int(11) NOT NULL DEFAULT 0 AFTER `trip_ticket_id`,
        ADD KEY `trip_user_id` (`trip_user_id`, `trip_date`),
        ADD KEY `trip_ticket_id` (`trip_ticket_id`),
        ADD KEY `trip_location_id` (`trip_location_id`)");

    // Who logged each trip, from the audit log where it says - otherwise the driver
    mysqli_query($mysqli, "UPDATE trips JOIN logs ON log_type = 'Trip' AND log_action = 'Create' AND log_entity_id = trip_id
        SET trip_created_by = log_user_id WHERE trip_created_by = 0 AND log_user_id > 0");
    mysqli_query($mysqli, "UPDATE trips SET trip_created_by = trip_user_id WHERE trip_created_by = 0");

    // New Trip put a client site's address in as the destination - link those
    // trips to the site, by its address or its name
    mysqli_query($mysqli, "UPDATE trips JOIN locations ON location_client_id = trip_client_id
        SET trip_location_id = location_id
        WHERE trip_location_id = 0 AND trip_client_id > 0 AND (
            LOWER(TRIM(trip_destination)) = LOWER(TRIM(location_name))
            OR LOWER(TRIM(trip_destination)) = LOWER(TRIM(CONCAT_WS(' ',
                NULLIF(TRIM(location_address), ''),
                NULLIF(IF(TRIM(COALESCE(location_city, '')) <> '' AND TRIM(CONCAT_WS(' ', NULLIF(TRIM(location_state), ''), NULLIF(TRIM(location_zip), ''))) <> '',
                    CONCAT(TRIM(location_city), ', ', TRIM(CONCAT_WS(' ', NULLIF(TRIM(location_state), ''), NULLIF(TRIM(location_zip), '')))),
                    TRIM(CONCAT_WS(' ', NULLIF(TRIM(location_city), ''), NULLIF(TRIM(location_state), ''), NULLIF(TRIM(location_zip), '')))), '')))))");
