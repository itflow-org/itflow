<?php
/*
 * API - Racks - Create
 * POST /api/v1/racks/create.php
 *
 * Document a client's rack, as New Rack does. Photos are added in ITFlow.
 *
 * Parameters (POST, JSON body):
 *   api_key                required - Your API key
 *   client_id              required - The client
 *   rack_name              required
 *   rack_type              required - A rack type (Admin > Categories)
 *   rack_units             required - How tall, 1 to 70 units
 *   rack_units_descending  optional - 1: U1 is at the top
 *   rack_model, rack_depth, rack_power_capacity (watts), rack_location_id (the client's),
 *   rack_physical_location, rack_description, rack_notes   optional
 */

require_once '../validate_api_key.php';
require_once '../require_post_method.php';

$insert_id = false;

$rack_lookup_id = 0;
require_once 'rack_find.php';

if (!$client_id || !mysqli_num_rows(mysqli_query($mysqli, "SELECT 1 FROM clients WHERE client_id = $client_id AND client_archived_at IS NULL"))) {
    $rack_refuse('client_id is required - the client the rack is for.');
}

require_once 'rack_model.php';

foreach (['rack_name' => 'A rack needs a name - rack_name.', 'rack_type' => 'rack_type is one of: ' . implode(', ', array_keys(categoryPicker('rack_type'))) . '.', 'rack_units' => 'rack_units is how tall it is - 1 to 70 units.'] as $field => $message) {
    if (!isset($rack_set[$field])) {
        $rack_refuse($message);
    }
}
$rack_set['rack_client_id'] = $client_id;

mysqli_query($mysqli, "INSERT INTO racks SET " . implode(', ', array_map(function ($column, $value) {
    return "$column = $value";
}, array_keys($rack_set), $rack_set)));
$rack_id = intval(mysqli_insert_id($mysqli));

if ($rack_id) {
    $insert_id = $rack_id;
    $name = escapeSql((string) getFieldById('racks', $rack_id, 'rack_name'));
    logAudit("Rack", "Create", "Created rack $name via API ($api_key_name)", $client_id, $rack_id);
    logAudit("API", "Success", "Created rack $name via API ($api_key_name)", $client_id);
}

// Output
require_once '../create_output.php';
