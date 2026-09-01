<?php

/*
 * ITFlow - Database update to version 2.7.5 (from 2.7.4)
 * Included by admin/database_updates.php - do not access directly
 */

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

    // Index the client-scoped lookups.
    //
    // Every one of these columns was unindexed, which is why the client
    // context was slow: agent/includes/inc_all_client.php runs a count per
    // table to fill in the side nav badges, and with no index each one is a
    // full table scan before the page renders a byte. The same columns are
    // what clientScopeSql() filters on, so every client-scoped list page in
    // the app was paying for it too.
    //
    // Shape: (<entity>_client_id, <entity>_archived_at) where the query pairs
    // the two, otherwise the client column alone. The archived column is
    // deliberately the TRAILING part and never an index of its own - on a
    // healthy instance almost every row has it NULL, so it is not selective
    // enough to lead and an index on it alone would be ignored.
    //
    // Tables whose queries carry no archived filter get a single-column index:
    // recurring_tickets, services, invoices, recurring_invoices,
    // calendar_events. invoices is in that group because the money total there
    // spans archived rows as well, so the scan cannot be narrowed by it.
    //
    // payments.payment_invoice_id and transfers.transfer_revenue_id are join
    // keys rather than client scoping, but they are on the same page's
    // critical path (amount paid, and the income count's transfer exclusion).
    //
    // InnoDB builds these online, but on a mature instance this is the slowest
    // step of the upgrade - it is proportional to table size, not row count
    // returned.

    $itflow_indexes = [
        ['contacts',           'contact_client_id',            ['contact_client_id', 'contact_archived_at']],
        ['locations',          'location_client_id',           ['location_client_id', 'location_archived_at']],
        ['assets',             'asset_client_id',              ['asset_client_id', 'asset_archived_at']],
        ['tickets',            'ticket_client_id',             ['ticket_client_id', 'ticket_archived_at']],
        ['recurring_tickets',  'recurring_ticket_client_id',   ['recurring_ticket_client_id']],
        ['projects',           'project_client_id',            ['project_client_id', 'project_archived_at']],
        ['services',           'service_client_id',            ['service_client_id']],
        ['vendors',            'vendor_client_id',             ['vendor_client_id', 'vendor_archived_at']],
        ['credentials',        'credential_client_id',         ['credential_client_id', 'credential_archived_at']],
        ['networks',           'network_client_id',            ['network_client_id', 'network_archived_at']],
        ['racks',              'rack_client_id',               ['rack_client_id', 'rack_archived_at']],
        ['domains',            'domain_client_id',             ['domain_client_id', 'domain_archived_at']],
        ['certificates',       'certificate_client_id',        ['certificate_client_id', 'certificate_archived_at']],
        ['software',           'software_client_id',           ['software_client_id', 'software_archived_at']],
        ['invoices',           'invoice_client_id',            ['invoice_client_id']],
        ['quotes',             'quote_client_id',              ['quote_client_id', 'quote_archived_at']],
        ['recurring_invoices', 'recurring_invoice_client_id',  ['recurring_invoice_client_id']],
        ['revenues',           'revenue_client_id',            ['revenue_client_id', 'revenue_archived_at']],
        ['files',              'file_client_id',               ['file_client_id', 'file_archived_at']],
        ['documents',          'document_client_id',           ['document_client_id', 'document_archived_at']],
        ['calendar_events',    'event_client_id',              ['event_client_id']],
        ['trips',              'trip_client_id',               ['trip_client_id', 'trip_archived_at']],
        ['payments',           'payment_invoice_id',           ['payment_invoice_id']],
        ['transfers',          'transfer_revenue_id',          ['transfer_revenue_id']],
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
