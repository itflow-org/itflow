<?php
/*
 * API - Racks - Archive
 * POST /api/v1/racks/archive.php
 *
 * Archive a rack, as Archive does - it and what is in it stay as they are.
 *
 * Parameters (POST, JSON body): api_key, rack_id, client_id (its client - needed when
 * the key's user is limited to certain clients)
 */

require_once '../validate_api_key.php';
require_once '../require_post_method.php';

$update_count = false;

require_once 'rack_find.php';

if ($rack && empty($rack['rack_archived_at'])) {

    mysqli_query($mysqli, "UPDATE racks SET rack_archived_at = NOW() WHERE rack_id = $rack_id");

    $update_count = 1;
    $name = escapeSql($rack['rack_name']);
    logAudit("Rack", "Archive", "Archived rack $name via API ($api_key_name)", $client_id, $rack_id);
    logAudit("API", "Success", "Archived rack $name via API ($api_key_name)", $client_id);
}

// Output
require_once '../update_output.php';
