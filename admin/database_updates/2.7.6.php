<?php

/*
 * ITFlow - Database update to version 2.7.6 (from 2.7.5)
 * Included by admin/database_updates.php - do not access directly
 */

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

    // Second indexing pass. 2.7.5 covered client scoping - the columns every
    // list page and the client side nav filter on. This one covers the other
    // two shapes that were still scanning whole tables:
    //
    //   1. "give me the child rows of this one parent" - the queries a detail
    //      page fires. Opening a ticket cost three full table scans
    //      (ticket_history, tasks, ticket_watchers) and opening an invoice cost
    //      two (invoice_items, history). ticket_replies and sla_history were
    //      already indexed by ticket, so this finishes that set.
    //
    //   2. the worker loop. cron/mail_queue.php runs
    //      WHERE email_status = 0 AND email_queued_at <= NOW() every minute -
    //      1440 scans a day of a table that keeps sent rows for 90 days.
    //
    // Same shape rule as 2.7.5: the selective column leads and anything
    // low-cardinality trails. email_status leads because pending is a small
    // slice of a queue that is almost entirely sent rows, and
    // notification_dismissed_at trails because almost every live row is NULL.
    //
    // history gets two single-column indexes rather than one composite: the
    // table serves invoices and quotes from the same rows, and the two detail
    // pages each filter on their own column alone.
    //
    // NOT included, deliberately, and the reasoning is worth keeping:
    //   - categories.category_type and tags.tag_type look like the busiest
    //     columns in the codebase, but both tables hold a few dozen rows. A
    //     scan in the buffer pool beats an index lookup.
    //   - ticket_status / invoice_status have five or six distinct values, so
    //     the optimizer scans regardless.
    //   - the join keys on the big side of a join (tickets.ticket_status and
    //     friends) never get looked up - the lookup lands on the small table's
    //     primary key.
    //   - payments/expenses/revenues .*_account_id are real but serve one
    //     screen, so they are not worth the write amplification yet.

    $itflow_indexes = [
        ['email_queue',     'email_status',             ['email_status', 'email_queued_at']],
        ['ticket_history',  'ticket_history_ticket_id', ['ticket_history_ticket_id']],
        ['tasks',           'task_ticket_id',           ['task_ticket_id']],
        ['ticket_watchers', 'watcher_ticket_id',        ['watcher_ticket_id']],
        ['invoice_items',   'item_invoice_id',          ['item_invoice_id']],
        ['quote_items',     'item_quote_id',            ['item_quote_id']],
        ['history',         'history_invoice_id',       ['history_invoice_id']],
        ['history',         'history_quote_id',         ['history_quote_id']],
        ['notifications',   'notification_user_id',     ['notification_user_id', 'notification_dismissed_at']],
        ['records',         'record_domain_id',         ['record_domain_id']],
    ];

    foreach ($itflow_indexes as $itflow_index) {
        list($itflow_index_table, $itflow_index_name, $itflow_index_columns) = $itflow_index;

        // MySQL has no ADD INDEX IF NOT EXISTS, and re-adding one is an error
        // rather than a no-op, so check first. This also makes the migration
        // safe on an instance where someone added the index by hand.
        $itflow_index_exists = mysqli_query($mysqli, "SELECT 1 FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = '$itflow_index_table'
            AND INDEX_NAME = '$itflow_index_name'
            LIMIT 1");

        if ($itflow_index_exists && mysqli_num_rows($itflow_index_exists) > 0) {
            continue;
        }

        $itflow_index_column_list = '`' . implode('`, `', $itflow_index_columns) . '`';

        mysqli_query($mysqli, "ALTER TABLE `$itflow_index_table`
            ADD KEY `$itflow_index_name` ($itflow_index_column_list)");
    }

    unset($itflow_indexes, $itflow_index, $itflow_index_table, $itflow_index_name,
        $itflow_index_columns, $itflow_index_exists, $itflow_index_column_list);
