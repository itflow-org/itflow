<?php

/*
 * ITFlow - Database update to version 2.5.5 (from 2.5.4)
 * Included by admin/database_updates.php - do not access directly
 */

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

    // Backfill guest URL keys. Every path that raises a ticket generates one
    // except the recurring block in cron.php, which omitted the column - so
    // every ticket the nightly schedule has ever created carries no key. The
    // guest view matches on ticket_url_key, so those tickets cannot be opened
    // from the "View ticket" link in reply and task-approval emails. It fails
    // closed rather than open (NULL never matches), so this is a broken link
    // rather than an exposure, but the links stay broken until a key exists.
    $sql_tickets_without_url_key = mysqli_query($mysqli, "SELECT ticket_id FROM tickets WHERE ticket_url_key IS NULL OR ticket_url_key = ''");

    while ($row = mysqli_fetch_assoc($sql_tickets_without_url_key)) {
        $ticket_id = intval($row['ticket_id']);
        $url_key = randomString(32);

        mysqli_query($mysqli, "UPDATE tickets SET ticket_url_key = '$url_key' WHERE ticket_id = $ticket_id");
    }
