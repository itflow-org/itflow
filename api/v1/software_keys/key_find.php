<?php

// The key a request names (software_key_id) and its license ($software) - of a client
// the key's user can reach - as $key_row, or null.

if (!function_exists('apiDeny')) {
    http_response_code(404);
    exit();
}

$software_key_id = intval($_POST['software_key_id'] ?? 0);
$key_row = $software_key_id ? mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT software_key_id, software_key, software_key_software_id FROM software_keys WHERE software_key_id = $software_key_id")) : null;
$software_lookup_id = intval($key_row['software_key_software_id'] ?? 0);
require_once '../software/software_find.php';
if (!$software) {
    $key_row = null;
}
