<?php

/*
 * ITFlow - Database update to version 3.6.4 (from 3.6.3)
 * Included by admin/database_updates.php - do not access directly
 */

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

    // Third indexing pass - the dashboard, and the counts in the menu on every page.
    //
    // 2.7.5 and 2.7.6 indexed "this client's records" and "this record's children". What
    // was still reading whole tables is "the ones that are still open": on an install with
    // years behind it nearly every ticket is closed and nearly every invoice paid, and the
    // dashboard and the menu ask for the few that aren't - a dozen times over tickets on
    // the dashboard, twice in the menu on every page. Measured on 280,000 tickets, 380,000
    // replies and 70,000 invoices: the dashboard from 5.1s to 0.7s, and about half a
    // second off every other page.
    //
    //   tickets.ticket_resolved_at, tickets.ticket_closed_at
    //     IS NULL is the open tickets - a sliver of the table, where *_archived_at IS NULL
    //     is nearly all of it (why 2.7.5 only ever put that one last). The same indexes
    //     serve "resolved since" for the dashboard's opened and resolved chart.
    //   tickets.ticket_created_at
    //     the other half of that chart, and the years the period picker offers.
    //   tickets (ticket_billable, ticket_invoice_id, ticket_status, ticket_client_id, ticket_archived_at)
    //     Unbilled Time's count, in the menu on every page for anyone who can see billing.
    //     It can match a large share of the table, so it carries every column the count
    //     reads - the count is answered from the index and never touches a ticket's row.
    //   ticket_replies (ticket_reply_by, ticket_reply_created_at)
    //     one agent's time this week and last - two passes over every reply ever written.
    //   invoices.invoice_status
    //     2.7.6 left this out as too few distinct values, which is right for Paid. Sent,
    //     Viewed and Partial together are a sliver, the optimizer does take the index for
    //     them (and still scans for Paid), and a visit to the dashboard asks eight times.
    //   expenses.expense_date
    //     payment_date and revenue_date have theirs; every expense figure for a period
    //     read every expense.
    //
    // Still not included: payments/expenses/revenues .*_account_id. Account balances add up
    // every row there is, so an index on the account would not spare them one.
    //
    // Re-runnable, as the two before it: each index is added only where it isn't.

    $itflow_indexes = [
        ['tickets',        'ticket_resolved_at', ['ticket_resolved_at']],
        ['tickets',        'ticket_closed_at',   ['ticket_closed_at']],
        ['tickets',        'ticket_created_at',  ['ticket_created_at']],
        ['tickets',        'ticket_billable',    ['ticket_billable', 'ticket_invoice_id', 'ticket_status', 'ticket_client_id', 'ticket_archived_at']],
        ['ticket_replies', 'ticket_reply_by',    ['ticket_reply_by', 'ticket_reply_created_at']],
        ['invoices',       'invoice_status',     ['invoice_status']],
        ['expenses',       'expense_date',       ['expense_date']],
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
