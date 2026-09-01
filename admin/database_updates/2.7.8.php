<?php

/*
 * ITFlow - Database update to version 2.7.8 (from 2.7.7)
 * Included by admin/database_updates.php - do not access directly
 */

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

    // The logs table only ever had KEY log_created_at, which is the column the
    // one page that reads it does NOT filter on usefully. Two shapes now do:
    //
    //   1. The client portal. profile.php shows a contact their recent sign-ins
    //      and recent actions, and activity.php lists the lot - all filtered on
    //      log_user_id + log_client_id. That is two full scans on every profile
    //      view of a table that grows forever and is never pruned.
    //
    //   2. admin/audit_logs.php, which filters a date range. It had an index
    //      available the whole time and could not use it, because wrapping the
    //      column in DATE() makes the comparison non-sargable. That query is
    //      rewritten as a half-open range in the same commit as this migration,
    //      so KEY log_created_at finally does its job.
    //
    // log_user_id leads the composite: a single user is a small slice of the
    // table, whereas one client can account for most of it on a single-client
    // install. Same selectivity rule as the 2.7.5 and 2.7.6 passes.

    $itflow_index_exists = mysqli_query($mysqli, "SELECT 1 FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'logs'
        AND INDEX_NAME = 'log_user_id'
        LIMIT 1");

    if (!$itflow_index_exists || mysqli_num_rows($itflow_index_exists) === 0) {
        mysqli_query($mysqli, "ALTER TABLE `logs` ADD KEY `log_user_id` (`log_user_id`, `log_client_id`)");
    }

    unset($itflow_index_exists);
