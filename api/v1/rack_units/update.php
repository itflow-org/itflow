<?php
/*
 * API - Rack Units - Update
 * POST /api/v1/rack_units/update.php
 *
 * Move or change an item in its rack, as Edit does on it - only what is sent,
 * with the same checks as mounting it.
 *
 * Parameters (POST, JSON body):
 *   api_key    required - Your API key
 *   unit_id    required - The item
 *   client_id  the rack's client - needed when the key's user is limited to certain clients
 *   the fields of rack_units/create.php but rack_id   optional
 */

require_once '../validate_api_key.php';
require_once '../require_post_method.php';

$update_count = false;

require_once 'unit_find.php';

if ($unit) {

    require_once 'unit_model.php';

    if (mysqli_query($mysqli, "UPDATE rack_units SET $unit_set WHERE unit_id = $unit_id")) {
        $update_count = 1;
        $what = escapeSql("$unit_label in rack " . $rack['rack_name']);
        logAudit("Rack", "Edit", "Updated $what via API ($api_key_name)", $client_id, $rack_id);
        logAudit("API", "Success", "Updated $what via API ($api_key_name)", $client_id);
    }
}

// Output
require_once '../update_output.php';
