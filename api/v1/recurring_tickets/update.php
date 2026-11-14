<?php
/*
 * API - Recurring Tickets - Update
 * POST /api/v1/recurring_tickets/update.php
 *
 * Change a recurring ticket, as Edit does - only what is sent. Moving next_date
 * makes it the day the schedule keeps to from then; it has to be today or later.
 * Sending tasks or asset_ids replaces its tasks or other assets. Its template's
 * fill-ins keep their answers but for those sent in fill_ins. A new agent is told.
 *
 * Parameters (POST, JSON body):
 *   api_key              required - Your API key
 *   recurring_ticket_id  required - The recurring ticket
 *   client_id            its client - needed when the key's user is limited to certain clients
 *   next_date            optional - Its next run, Y-m-d
 *   subject, details, priority, frequency, billable, assigned_to, contact_id, asset_id,
 *   asset_ids, category_id, ticket_template_id, fill_ins, tasks
 *                        optional - as for recurring_tickets/create.php
 */

require_once '../validate_api_key.php';
require_once '../require_post_method.php';
require_once "../../../includes/load_global_settings.php";

$update_count = false;

require_once 'rt_find.php';

if ($rt_row) {

    require_once 'recurring_ticket_model.php';

    // The next run - a date in the past only when it was already (a missed run stays missed until moved)
    if (array_key_exists('next_date', $_POST)) {
        $next_run = recurringTicketDate($_POST['next_date']);
        $current_next_run = substr((string) $rt_row['recurring_ticket_next_run'], 0, 10);
        if (!$next_run || ($next_run < date('Y-m-d') && $next_run !== $current_next_run)) {
            $rt_refuse('next_date is its next run - a date, today or later, such as ' . date('Y-m-d') . '.');
        }
        if ($next_run !== $current_next_run) {
            // A run moved by hand is the new anchor - monthly ones keep to its day of the month from here
            $rt_set['recurring_ticket_next_run'] = "'$next_run'";
            $rt_set['recurring_ticket_start_date'] = "'$next_run'";
        }
    }

    if (!$rt_set && $rt_assets === null && $rt_tasks === null && $rt_fill_ins === null && !array_key_exists('next_date', $_POST)) {
        $rt_refuse('Send at least one of subject, details, priority, frequency, next_date, billable, assigned_to, contact_id, asset_id, asset_ids, category_id, ticket_template_id, fill_ins or tasks.');
    }

    $update_sql = $rt_set ? mysqli_query($mysqli, "UPDATE recurring_tickets SET " . implode(', ', array_map(function ($column, $value) {
        return "$column = $value";
    }, array_keys($rt_set), $rt_set)) . " WHERE recurring_ticket_id = $recurring_ticket_id") : true;

    if ($update_sql) {
        $update_count = 1;

        $row = recurringTicketRow($recurring_ticket_id);
        $rt_subject = (string) $row['recurring_ticket_subject'];
        $rt_notify_agent = intval($row['recurring_ticket_assigned_to']) !== intval($rt_row['recurring_ticket_assigned_to']) ? intval($row['recurring_ticket_assigned_to']) : 0;
        require 'recurring_ticket_save.php';

        triggerCustomAction('recurring_ticket_update', $recurring_ticket_id);

        $label = escapeSql($rt_subject);
        logAudit("Recurring Ticket", "Edit", "Edited recurring ticket $label via API ($api_key_name)", $client_id, $recurring_ticket_id);
        logAudit("API", "Success", "Edited recurring ticket $label via API ($api_key_name)", $client_id);
    }
}

// Output
require_once '../update_output.php';
