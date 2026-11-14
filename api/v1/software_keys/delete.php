<?php
/*
 * API - License Keys - Delete
 * POST /api/v1/software_keys/delete.php
 *
 * Delete a key from its license, as Delete does on it. The seats it was handed
 * with stay.
 *
 * Parameters (POST, JSON body): api_key, software_key_id, client_id (the license's
 * client - needed when the key's user is limited to certain clients)
 */

require_once '../validate_api_key.php';
require_once '../require_post_method.php';

$delete_count = false;

require_once 'key_find.php';

if ($key_row) {

    mysqli_query($mysqli, "DELETE FROM software_keys WHERE software_key_id = $software_key_id");
    $delete_count = mysqli_affected_rows($mysqli);

    if ($delete_count > 0) {
        $label = escapeSql($software['software_name']);
        logAudit("Software", "Keys", "Deleted a key from license $label via API ($api_key_name)", $client_id, intval($software['software_id']));
        logAudit("API", "Success", "Deleted a key from license $label via API ($api_key_name)", $client_id);
    }
}

// Output
require_once '../delete_output.php';
