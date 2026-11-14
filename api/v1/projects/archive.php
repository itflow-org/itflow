<?php
/*
 * API - Projects - Archive
 * POST /api/v1/projects/archive.php
 *
 * Archive a project, as Archive does on its page. Its tickets stay as they are.
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

if ($row && empty($row['project_archived_at'])) {

    $client_id = intval($row['project_client_id']);

    projectArchive($row);

    $update_count = 1;
    logAudit("API", "Success", "Archived project " . escapeSql(projectReference($row) . ' - ' . $row['project_name']) . " via API ($api_key_name)", $client_id);
}

// Output
require_once '../update_output.php';
