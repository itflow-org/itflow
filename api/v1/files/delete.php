<?php
/*
 * API - Files - Delete
 * POST /api/v1/files/delete.php
 *
 * Delete an archived file, as Delete does (fileDeleteForGood()) - from the server
 * too, with its links. Archived first, as for the other records the API deletes.
 *
 * Parameters (POST, JSON body): api_key, file_id, client_id (its client - needed when
 * the key's user is limited to certain clients)
 */

require_once '../validate_api_key.php';
require_once '../require_post_method.php';
require_once 'file_find.php';

$delete_count = false;

if ($file && !empty($file['file_archived_at'])) {
    if (fileDeleteForGood($file_id) !== null) {
        $delete_count = 1;
        logAudit("API", "Success", "Deleted file $file_label via API ($api_key_name)", $client_id);
    }
}

// Output
require_once '../delete_output.php';
