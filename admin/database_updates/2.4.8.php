<?php

/*
 * ITFlow - Database update to version 2.4.8 (from 2.4.7)
 * Included by admin/database_updates.php - do not access directly
 */

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

    // The login lockout queries run on every login POST and filter the logs table by
    // a 10 minute window. With only PRIMARY KEY (log_id) that is a full scan of a
    // table that grows with every action in the app, which an attacker can trigger
    // once per request. Indexing log_created_at bounds both lockout queries to the
    // recent rows, and also speeds up the nightly log purge in cron.php, which had
    // the same problem.
    mysqli_query($mysqli, "ALTER TABLE `logs` ADD INDEX `log_created_at` (`log_created_at`)");
