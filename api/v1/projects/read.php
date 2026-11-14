<?php
/*
 * API - Projects - Read
 * GET /api/v1/projects/read.php
 *
 * Projects - open, completed and archived - each with its reference (PRJ-12).
 * A project with no client (0) is internal work. One project, by project_id,
 * also has its tickets (of the clients the key's user can see) and its numbers
 * (projectStats()): tickets, open, done, overdue, tasks, tasks_done, and the
 * seconds logged on its tickets - all, billable, and billable but not yet invoiced.
 *
 *   project_id     optional - Just this project, with its tickets and numbers
 *   client_id      optional - Just this client's; 0 for internal projects
 *   limit, offset  optional - Paging
 */

require_once '../validate_api_key.php';
require_once '../require_get_method.php';

$columns = "projects.*, CONCAT(project_prefix, project_number) AS project_reference";

if (isset($_GET['project_id'])) {
    $id = intval($_GET['project_id']);
    $sql = mysqli_query($mysqli, "SELECT $columns FROM projects WHERE project_id = $id " . apiClientScopeSql('project_client_id'));
    if ($sql && ($row = mysqli_fetch_assoc($sql))) {
        $row['tickets'] = [];
        $tickets = mysqli_query($mysqli, "SELECT ticket_id, ticket_prefix, ticket_number, ticket_subject, ticket_status, ticket_status_name, ticket_priority,
                ticket_assigned_to, ticket_due_at, ticket_resolved_at, ticket_closed_at, ticket_client_id
            FROM tickets LEFT JOIN ticket_statuses ON ticket_status_id = ticket_status
            WHERE ticket_project_id = $id AND ticket_archived_at IS NULL " . clientScopeSql('ticket_client_id') . "
            ORDER BY ticket_id");
        while ($ticket = mysqli_fetch_assoc($tickets)) {
            $row['tickets'][] = $ticket;
        }
        $row['stats'] = projectStats([$id])[$id];
        echo json_encode(['success' => 'True', 'count' => 1, 'data' => [$row]]);
        exit();
    }
} else {
    $sql = mysqli_query($mysqli, "SELECT $columns FROM projects WHERE 1=1 " . apiClientScopeSql('project_client_id') . " ORDER BY project_id LIMIT $limit OFFSET $offset");
}

// Output
require_once "../read_output.php";
