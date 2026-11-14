<?php
/*
 * API - Projects - Update
 * POST /api/v1/projects/update.php
 *
 * Change a project, as Edit Project does - only what is sent. Not while it is
 * archived. A new manager is told, unless it is the key's own agent. Its
 * tickets go on and off it through tickets/update.php (ticket_project_id).
 *
 * Parameters (POST, JSON body):
 *   api_key     required - Your API key
 *   project_id  required - The project
 *   client_id   its client - needed when the key's user is limited to certain clients
 *   project_name, project_description, project_start, project_due, project_budget_hours,
 *   project_manager, project_contact_id   optional - as for projects/create.php
 */

require_once '../validate_api_key.php';
require_once '../require_post_method.php';

$project_id = intval($_POST['project_id'] ?? 0);

$update_count = false;

$project_row = $project_id ? projectRow($project_id) : null;
if ($project_row && (!apiUserCanAccessClient($project_row['project_client_id']) || ($client_id && intval($project_row['project_client_id']) !== $client_id))) {
    $project_row = null;
}

if ($project_row) {

    $client_id = intval($project_row['project_client_id']);
    $reference = projectReference($project_row);

    if (!empty($project_row['project_archived_at'])) {
        http_response_code(400);
        $return_arr['success'] = "False";
        $return_arr['message'] = "Project $reference is archived - unarchive it to change it.";
        echo json_encode($return_arr);
        exit();
    }

    require_once 'project_model.php';

    if (!$project_set) {
        http_response_code(400);
        $return_arr['success'] = "False";
        $return_arr['message'] = 'Send at least one of project_name, project_description, project_start, project_due, project_budget_hours, project_manager or project_contact_id.';
        echo json_encode($return_arr);
        exit();
    }

    $update_sql = mysqli_query($mysqli, "UPDATE projects SET " . implode(', ', array_map(function ($column, $value) {
        return "$column = $value";
    }, array_keys($project_set), $project_set)) . " WHERE project_id = $project_id");

    if ($update_sql) {
        $update_count = 1;

        if (isset($project_set['project_manager']) && intval($project_set['project_manager']) !== intval($project_row['project_manager'])) {
            projectNotifyManager($project_row, $project_set['project_manager']);
        }

        $label = escapeSql($reference . ' - ' . (string) getFieldById('projects', $project_id, 'project_name'));
        logAudit("Project", "Edit", "Edited project $label via API ($api_key_name)", $client_id, $project_id);
        logAudit("API", "Success", "Edited project $label via API ($api_key_name)", $client_id);
    }
}

// Output
require_once '../update_output.php';
