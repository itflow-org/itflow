<?php

/*
 * ITFlow - Database update to version 3.3.1 (from 3.3.0)
 * Included by admin/database_updates.php - do not access directly
 */

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

    // The audit log, made consistent so it can be filtered and each entry opened.
    //
    // 1. Entries that pointed at the wrong record: a client restored through the
    //    API was logged as a Contact with no record, editing or redacting a ticket
    //    reply pointed at the reply rather than its ticket, a document edited
    //    through the API at its old version, and paying back an agent at the
    //    reimbursement rather than the expense that recorded it. Moving an id
    //    twice would move it somewhere wrong, so this is done only while the
    //    log_entity_id index, added last below, is not there yet.
    $itflow_logs_done = mysqli_query($mysqli, "SELECT 1 FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'logs' AND INDEX_NAME = 'log_entity_id' LIMIT 1");
    if (!$itflow_logs_done || mysqli_num_rows($itflow_logs_done) === 0) {
        mysqli_query($mysqli, "UPDATE `logs` SET `log_type` = 'Client', `log_entity_id` = `log_client_id`
            WHERE `log_type` = 'Contact' AND `log_action` = 'Unarchive' AND `log_entity_id` = 0 AND `log_description` LIKE '% unarchived via API (%'");
        mysqli_query($mysqli, "UPDATE `logs` LEFT JOIN `ticket_replies` ON `ticket_reply_id` = `log_entity_id`
            SET `log_entity_id` = COALESCE(`ticket_reply_ticket_id`, 0)
            WHERE `log_type` = 'Ticket' AND `log_action` = 'Reply' AND (`log_description` LIKE '% edited a reply on ticket %' OR `log_description` LIKE '% redacted a reply on ticket %')");
        mysqli_query($mysqli, "UPDATE `logs` LEFT JOIN `document_versions` ON `document_version_id` = `log_entity_id`
            SET `log_entity_id` = COALESCE(`document_version_document_id`, 0)
            WHERE `log_type` = 'Document' AND `log_action` = 'Edit' AND `log_description` LIKE '% via API (%), previous version kept'");
        mysqli_query($mysqli, "UPDATE `logs` LEFT JOIN `reimbursements` ON `reimbursement_id` = `log_entity_id`
            SET `log_entity_id` = COALESCE(`reimbursement_expense_id`, 0)
            WHERE `log_type` = 'Expense' AND `log_action` = 'Reimburse'");
        mysqli_query($mysqli, "UPDATE `logs` SET `log_entity_id` = 0 WHERE `log_type` = 'Expense' AND `log_action` = 'Reimbursement Undo'");
    }

    // 2. One name for each thing done, whatever it was done to - the past-tense
    //    and API spellings become the verbs everything else uses, an agent
    //    turned away from a client says Denied rather than Access, and a saved
    //    payment method saved or deleted says so rather than Update
    mysqli_query($mysqli, "UPDATE `logs` SET `log_type` = 'Client' WHERE BINARY `log_type` = 'client'");
    foreach (['Unarchive' => 'Restore', 'Restored' => 'Restore', 'Deleted' => 'Delete', 'Merged' => 'Merge', 'Closed' => 'Close',
        'Reopened' => 'Reopen', 'Resolved' => 'Resolve', 'Sent' => 'Send'] as $itflow_old_action => $itflow_new_action) {
        mysqli_query($mysqli, "UPDATE `logs` SET `log_action` = '$itflow_new_action' WHERE `log_action` = '$itflow_old_action'");
    }
    mysqli_query($mysqli, "UPDATE `logs` SET `log_action` = 'Denied' WHERE `log_type` = 'Client' AND `log_action` = 'Access'");
    mysqli_query($mysqli, "UPDATE `logs` SET `log_action` = 'Delete' WHERE `log_type` IN ('Payment Provider', 'Stripe') AND `log_action` = 'Update' AND `log_description` LIKE '% deleted %'");
    mysqli_query($mysqli, "UPDATE `logs` SET `log_action` = 'Create' WHERE `log_type` = 'Stripe' AND `log_action` = 'Update' AND `log_description` LIKE '% saved payment method %'");
    mysqli_query($mysqli, "UPDATE `logs` SET `log_action` = 'Edit' WHERE `log_type` = 'Contract Template' AND `log_action` = 'Update'");

    // 3. Two indexes on logs - by client, for the client filter and a client's
    //    recent activity, and by record, for a record's history. logs takes a
    //    write per change, so these two and no more; the record's id leads
    //    because it is the selective column, its type trails.
    $itflow_indexes = [
        ['logs', 'log_client_id', ['log_client_id', 'log_created_at']],
        ['logs', 'log_entity_id', ['log_entity_id', 'log_type']],
    ];
    foreach ($itflow_indexes as $itflow_index) {
        list($itflow_index_table, $itflow_index_name, $itflow_index_columns) = $itflow_index;
        // No ADD INDEX IF NOT EXISTS - check first, so the step can run again
        $itflow_index_exists = mysqli_query($mysqli, "SELECT 1 FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '$itflow_index_table' AND INDEX_NAME = '$itflow_index_name' LIMIT 1");
        if ($itflow_index_exists && mysqli_num_rows($itflow_index_exists) > 0) {
            continue;
        }
        mysqli_query($mysqli, "ALTER TABLE `$itflow_index_table` ADD KEY `$itflow_index_name` (`" . implode('`, `', $itflow_index_columns) . "`)");
    }
    unset($itflow_logs_done, $itflow_indexes, $itflow_index, $itflow_index_table, $itflow_index_name, $itflow_index_columns, $itflow_index_exists, $itflow_old_action, $itflow_new_action);
