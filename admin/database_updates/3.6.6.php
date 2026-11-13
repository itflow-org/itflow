<?php

/*
 * ITFlow - Database update to version 3.6.6 (from 3.6.5)
 * Included by admin/database_updates.php - do not access directly
 */

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

    // Fourth indexing pass - the audit log, and tickets by the invoice they were billed on.
    //
    // The audit log is the table that grows fastest, and three Admin pages read the whole of
    // it on every load: Users (each user's last sign-in), Sign-ins (every sign-in, by person
    // and by address) and Audit Logs (the views counted by what is in them, the type and
    // action lists). On a test install with 1,000,000 entries they took 1.1, 1.8 and 2.5
    // seconds. Two indexes, with the pages' queries changed to use them:
    //
    //   logs (log_user_id, log_type, log_action) - one user's entries of a type and action:
    //     Users' last sign-in and last portal sign-in, the New IP check on a sign-in,
    //     and the Audit Logs counts for one person. MAX(log_id) comes off the end of it.
    //
    //   logs (log_type, log_action, log_user_id, log_ip, log_created_at) - by type and action:
    //     the Audit Logs views are counted by type and action from it without reading an
    //     entry, and its type and action lists step through it. Every sign-in query on the
    //     Sign-ins page finds what it needs in it alone (who, the address, the action, when),
    //     and so does the check after a failed sign-in for too many in a few minutes.
    //
    // Together they are about as big as the two biggest the log already has. KEY log_user_id
    // (log_user_id, log_client_id) is kept: it is what one person at one client is found by.
    //
    //   tickets (ticket_invoice_id) - the tickets billed on an invoice, which the invoice's
    //     page lists. KEY ticket_billable has the column second, behind ticket_billable, so
    //     every invoice opened read every ticket - 0.75 seconds with 280,000 of them. It also
    //     serves putting an invoice's tickets back to unbilled when the invoice is deleted.
    //
    // Each is added only where it isn't there already, as 3.6.4's are. One that can't be added
    // is left out and said so in App Logs: these make pages quicker, and nothing waits on them.
    // Adding them takes a few seconds on most installs - about 2 seconds a million audit log
    // entries on the test install.

    $itflow_indexes = [
        ['logs', 'log_user_type', ['log_user_id', 'log_type', 'log_action']],
        ['logs', 'log_type', ['log_type', 'log_action', 'log_user_id', 'log_ip', 'log_created_at']],
        ['tickets', 'ticket_invoice_id', ['ticket_invoice_id']],
    ];

    foreach ($itflow_indexes as $itflow_index) {
        list($itflow_index_table, $itflow_index_name, $itflow_index_columns) = $itflow_index;

        $itflow_index_exists = mysqli_query($mysqli, "SELECT 1 FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = '$itflow_index_table'
            AND INDEX_NAME = '$itflow_index_name'
            LIMIT 1");

        if ($itflow_index_exists && mysqli_num_rows($itflow_index_exists) > 0) {
            continue;
        }

        $itflow_index_column_list = '`' . implode('`, `', $itflow_index_columns) . '`';

        try {
            mysqli_query($mysqli, "ALTER TABLE `$itflow_index_table`
                ADD KEY `$itflow_index_name` ($itflow_index_column_list)");
        } catch (Throwable $itflow_index_error) {
            $itflow_index_message = "Database update 3.6.6 could not add the index $itflow_index_name to $itflow_index_table (the rest of the update went on): " . $itflow_index_error->getMessage();
            error_log("ITFlow: $itflow_index_message");
            if (function_exists('logApp')) {
                try {
                    logApp('Database Update', 'warning', $itflow_index_message);
                } catch (Throwable $itflow_index_error) {
                    // The PHP error log has it
                }
            }
        }
    }

    unset($itflow_indexes, $itflow_index, $itflow_index_table, $itflow_index_name,
        $itflow_index_columns, $itflow_index_exists, $itflow_index_column_list, $itflow_index_error, $itflow_index_message);
