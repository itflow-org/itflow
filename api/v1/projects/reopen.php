<?php
/*
 * API - Projects - Reopen
 * POST /api/v1/projects/reopen.php
 *
 * Bring a completed project back to active, as Reopen does on its page. Not an
 * archived one - unarchive it first.
 *
 * Parameters (POST, JSON body): api_key, project_id, client_id (its client - needed
 * when the key's user is limited to certain clients)
 */

require_once '../validate_api_key.php';
require_once '../require_post_method.php';

$project_id = intval($_POST['project_id'] ?? 0);

$row = $project_id ? projectRow($project_id) : null;
if ($row && (!apiUserCanAccessClient($row['project_client_id']) || ($client_id && intval($row['project_client_id']) !== $client_id))) {
    $row = null;
}

$update_count = false;

if ($row) {

    $client_id = intval($row['project_client_id']);
    $reference = projectReference($row);

    if (empty($row['project_completed_at']) || !empty($row['project_archived_at'])) {
        http_response_code(400);
        $return_arr['success'] = "False";
        $return_arr['message'] = "Project $reference isn't completed" . (!empty($row['project_archived_at']) ? ' - it is archived, unarchive it first' : '') . '.';
        echo json_encode($return_arr);
        exit();
    }

    projectReopen($row);

    $update_count = 1;
    logAudit("API", "Success", "Reopened project " . escapeSql($reference . ' - ' . $row['project_name']) . " via API ($api_key_name)", $client_id);
}

// Output
require_once '../update_output.php';
