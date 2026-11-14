<?php
/*
 * API - Recurring Tickets - Read
 * GET /api/v1/recurring_tickets/read.php
 *
 * Recurring tickets - paused ones too, with recurring_ticket_paused_at set. One,
 * by recurring_ticket_id, also has its tasks, its other assets (asset_ids) and its
 * template's fill-ins with their answers (fill_ins).
 *
 *   recurring_ticket_id  optional - Just this one
 *   client_id            optional - Just this client's
 *   limit, offset        optional - Paging
 */

require_once '../validate_api_key.php';
require_once '../require_get_method.php';

if (isset($_GET['recurring_ticket_id'])) {
    $id = intval($_GET['recurring_ticket_id']);
    $sql = mysqli_query($mysqli, "SELECT * FROM recurring_tickets WHERE recurring_ticket_id = $id " . apiClientScopeSql('recurring_ticket_client_id'));
    if ($sql && ($row = mysqli_fetch_assoc($sql))) {
        $row['tasks'] = [];
        $tasks = mysqli_query($mysqli, "SELECT recurring_ticket_task_name AS name, recurring_ticket_task_completion_estimate AS estimate FROM recurring_ticket_tasks
            WHERE recurring_ticket_task_recurring_ticket_id = $id ORDER BY recurring_ticket_task_order ASC, recurring_ticket_task_id ASC");
        while ($task = mysqli_fetch_assoc($tasks)) {
            $row['tasks'][] = ['name' => $task['name'], 'estimate' => intval($task['estimate'])];
        }
        $row['asset_ids'] = [];
        $assets = mysqli_query($mysqli, "SELECT asset_id FROM recurring_ticket_assets WHERE recurring_ticket_id = $id ORDER BY asset_id");
        while ($asset = mysqli_fetch_row($assets)) {
            $row['asset_ids'][] = intval($asset[0]);
        }
        $row['fill_ins'] = array_map(function ($answer) {
            return ['name' => $answer['name'], 'type' => $answer['type'], 'value' => $answer['value']];
        }, ticketFillInsStored('recurring_ticket', $id));
        echo json_encode(['success' => 'True', 'count' => 1, 'data' => [$row]]);
        exit();
    }
} else {
    $sql = mysqli_query($mysqli, "SELECT * FROM recurring_tickets WHERE 1=1 " . apiClientScopeSql('recurring_ticket_client_id') . " ORDER BY recurring_ticket_id LIMIT $limit OFFSET $offset");
}

// Output
require_once "../read_output.php";
