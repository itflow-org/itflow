<?php
/*
 * API - Racks - Delete
 * POST /api/v1/racks/delete.php
 *
 * Delete an archived rack, as Delete does - what is mounted in it and its photos
 * go with it; the assets stay. Archived first, as for the other records the API deletes.
 *
 * Parameters (POST, JSON body): api_key, rack_id, client_id (its client - needed when
 * the key's user is limited to certain clients)
 */

require_once '../validate_api_key.php';
require_once '../require_post_method.php';

$delete_count = false;

require_once 'rack_find.php';

if ($rack && !empty($rack['rack_archived_at'])) {

    // rack_units go with it (ON DELETE CASCADE)
    mysqli_query($mysqli, "DELETE FROM racks WHERE rack_id = $rack_id");
    $delete_count = mysqli_affected_rows($mysqli);

    if ($delete_count > 0) {
        rackDeletePhoto($client_id, $rack['rack_photo']);
        rackDeletePhoto($client_id, $rack['rack_photo_rear']);

        $name = escapeSql($rack['rack_name']);
        logAudit("Rack", "Delete", "Deleted rack $name via API ($api_key_name)", $client_id);
        logAudit("API", "Success", "Deleted rack $name via API ($api_key_name)", $client_id);
    }
}

// Output
require_once '../delete_output.php';
