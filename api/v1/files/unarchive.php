<?php
/*
 * API - Files - Unarchive
 * POST /api/v1/files/unarchive.php
 *
 * Bring an archived file back, as Restore does.
 *
 * Parameters (POST, JSON body): api_key, file_id, client_id (its client - needed when
 * the key's user is limited to certain clients)
 */

require_once '../validate_api_key.php';
require_once '../require_post_method.php';
require_once 'file_find.php';

$update_count = false;

if ($file && !empty($file['file_archived_at'])) {
    mysqli_query($mysqli, "UPDATE files SET file_archived_at = NULL WHERE file_id = $file_id");
    $update_count = 1;
    logAudit("File", "Restore", "Restored file $file_label via API ($api_key_name)", $client_id, $file_id);
    logAudit("API", "Success", "Unarchived file $file_label via API ($api_key_name)", $client_id);
}

// Output
require_once '../update_output.php';
