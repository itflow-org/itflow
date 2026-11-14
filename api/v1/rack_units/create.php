<?php
/*
 * API - Rack Units - Create
 * POST /api/v1/rack_units/create.php
 *
 * Mount an item in a rack, as Add to rack does - one of the client's assets (in
 * one rack at a time), or a named item such as a patch panel or a shelf - where
 * nothing else is on that face.
 *
 * Parameters (POST, JSON body):
 *   api_key            required - Your API key
 *   rack_id            required - The rack
 *   client_id          its client - needed when the key's user is limited to certain clients
 *   asset_id           The client's asset - or unit_device for an item that isn't one
 *   unit_device        A name for the item
 *   unit_type          optional - Without an asset: Device (the default), Patch Panel, Cable Management, Shelf, PDU, UPS, KVM / Console or Blanking Panel
 *   unit_start_number  required - The lowest unit it takes
 *   unit_height        optional - How many units, 1 if left out
 *   unit_face          optional - front (the default) or rear
 *   unit_full_depth    optional - 1: it takes the front and the rear
 *   unit_power_watts, unit_notes   optional
 */

require_once '../validate_api_key.php';
require_once '../require_post_method.php';

$insert_id = false;

require_once '../racks/rack_find.php';

if ($rack) {

    require_once 'unit_model.php';

    mysqli_query($mysqli, "INSERT INTO rack_units SET $unit_set, unit_rack_id = $rack_id");
    $insert_id = intval(mysqli_insert_id($mysqli)) ?: false;

    if ($insert_id) {
        $what = escapeSql("$unit_label to rack " . $rack['rack_name']);
        logAudit("Rack", "Edit", "Added $what via API ($api_key_name)", $client_id, $rack_id);
        logAudit("API", "Success", "Added $what via API ($api_key_name)", $client_id);
    }
}

// Output
require_once '../create_output.php';
