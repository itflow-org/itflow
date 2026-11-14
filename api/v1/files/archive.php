<?php
/*
 * API - Files - Archive
 * POST /api/v1/files/archive.php
 *
 * Archive a file, as Archive does.
 *
 * Parameters (POST, JSON body): api_key, file_id, client_id (its client - needed when
 * the key's user is limited to certain clients)
 */

require_once '../validate_api_key.php';
require_once '../require_post_method.php';
require_once 'file_find.php';

$update_count = false;

if ($file && empty($file['file_archived_at'])) {
    mysqli_query($mysqli, "UPDATE files SET file_archived_at = NOW() WHERE file_id = $file_id");
    $update_count = 1;
    logAudit("File", "Archive", "Archived file $file_label via API ($api_key_name)", $client_id, $file_id);
    logAudit("API", "Success", "Archived file $file_label via API ($api_key_name)", $client_id);
}

// Output
require_once '../update_output.php';
