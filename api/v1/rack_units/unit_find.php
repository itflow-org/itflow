<?php

// The rack item a request names (unit_id) and its rack - of a client the key's user
// can reach - as $unit and $rack, or null.

if (!function_exists('apiDeny')) {
    http_response_code(404);
    exit();
}

$unit_id = intval($_POST['unit_id'] ?? 0);
$unit = $unit_id ? mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT rack_units.*, asset_name FROM rack_units LEFT JOIN assets ON asset_id = unit_asset_id WHERE unit_id = $unit_id")) : null;
// The rack comes from the item itself, never from the request
$rack_lookup_id = intval($unit['unit_rack_id'] ?? 0);
require_once '../racks/rack_find.php';
if (!$rack) {
    $unit = null;
}
