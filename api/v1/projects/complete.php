<?php
/*
 * API - Projects - Complete
 * POST /api/v1/projects/complete.php
 *
 * Mark a project complete, as Complete does on its page - only once none of
 * its tickets is open.
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

    $why_not = projectComplete($row);
    if ($why_not !== '') {
        http_response_code(400);
        $return_arr['success'] = "False";
        $return_arr['message'] = "Project $reference can't be completed - $why_not.";
        echo json_encode($return_arr);
        exit();
    }

    $update_count = 1;
    logAudit("API", "Success", "Completed project " . escapeSql($reference . ' - ' . $row['project_name']) . " via API ($api_key_name)", $client_id);
}

// Output
require_once '../update_output.php';
