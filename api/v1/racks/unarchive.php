<?php
/*
 * API - Racks - Unarchive
 * POST /api/v1/racks/unarchive.php
 *
 * Bring an archived rack back, as Restore does.
 *
 * Parameters (POST, JSON body): api_key, rack_id, client_id (its client - needed when
 * the key's user is limited to certain clients)
 */

require_once '../validate_api_key.php';
require_once '../require_post_method.php';

$update_count = false;

require_once 'rack_find.php';

if ($rack && !empty($rack['rack_archived_at'])) {

    mysqli_query($mysqli, "UPDATE racks SET rack_archived_at = NULL WHERE rack_id = $rack_id");

    $update_count = 1;
    $name = escapeSql($rack['rack_name']);
    logAudit("Rack", "Restore", "Restored rack $name via API ($api_key_name)", $client_id, $rack_id);
    logAudit("API", "Success", "Unarchived rack $name via API ($api_key_name)", $client_id);
}

// Output
require_once '../update_output.php';
