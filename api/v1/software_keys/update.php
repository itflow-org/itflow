<?php
/*
 * API - License Keys - Update
 * POST /api/v1/software_keys/update.php
 *
 * Change a key, or who it is handed to, as Edit does on it - only what is sent.
 * Each device or user it is handed to gets a seat on the license, if it had none.
 *
 * Parameters (POST, JSON body):
 *   api_key          required - Your API key
 *   software_key_id  required - The key
 *   client_id        the license's client - needed when the key's user is limited to certain clients
 *   software_key     optional - The key itself
 *   asset_ids        optional - The client's assets it is handed to, in place of those it had
 *   contact_ids      optional - The client's contacts it is handed to, in place of those it had
 *
 * Answer: warning, when the license is over its seats.
 */

require_once '../validate_api_key.php';
require_once '../require_post_method.php';

$update_count = false;

require_once 'key_find.php';

if ($key_row) {

    $software_id = intval($software['software_id']);

    $refuse = '';
    if (array_key_exists('software_key', $_POST)) {
        $key = mb_substr(trim((string) (is_scalar($_POST['software_key']) ? $_POST['software_key'] : '')), 0, 400);
        if ($key === '') {
            $refuse = 'A key cannot be blank - delete it instead.';
        }
    }
    $holders = [];
    foreach (['asset' => 'asset_ids', 'contact' => 'contact_ids'] as $kind => $field) {
        if ($refuse === '' && array_key_exists($field, $_POST)) {
            $ids = array_values(array_unique(array_filter(array_map('intval', (array) $_POST[$field]))));
            $holders[$kind] = softwareClientIds($kind, $ids, $client_id);
            if (count($holders[$kind]) !== count($ids)) {
                $refuse = "$field are the client's own {$kind}s, not archived - these aren't: " . implode(', ', array_diff($ids, $holders[$kind])) . '.';
            }
        }
    }
    if ($refuse === '' && !array_key_exists('software_key', $_POST) && !$holders) {
        $refuse = 'Send at least one of software_key, asset_ids or contact_ids.';
    }
    if ($refuse !== '') {
        http_response_code(400);
        $return_arr['success'] = "False";
        $return_arr['message'] = $refuse;
        echo json_encode($return_arr);
        exit();
    }

    if (isset($key)) {
        mysqli_query($mysqli, "UPDATE software_keys SET software_key = '" . escapeSql($key) . "' WHERE software_key_id = $software_key_id");
    }

    // Who it is handed to - each gets a seat if they had none
    if (isset($holders['asset'])) {
        mysqli_query($mysqli, "DELETE FROM software_key_asset_assignments WHERE software_key_id = $software_key_id");
        foreach ($holders['asset'] as $asset_id) {
            mysqli_query($mysqli, "INSERT IGNORE INTO software_key_asset_assignments SET software_key_id = $software_key_id, asset_id = $asset_id");
            mysqli_query($mysqli, "INSERT IGNORE INTO software_assets SET software_id = $software_id, asset_id = $asset_id");
        }
    }
    if (isset($holders['contact'])) {
        mysqli_query($mysqli, "DELETE FROM software_key_contact_assignments WHERE software_key_id = $software_key_id");
        foreach ($holders['contact'] as $contact_id) {
            mysqli_query($mysqli, "INSERT IGNORE INTO software_key_contact_assignments SET software_key_id = $software_key_id, contact_id = $contact_id");
            mysqli_query($mysqli, "INSERT IGNORE INTO software_contacts SET software_id = $software_id, contact_id = $contact_id");
        }
    }

    $update_count = 1;
    $return_arr['warning'] = $software_seat_warning($software_id);

    $label = escapeSql($software['software_name']);
    logAudit("Software", "Keys", "Updated a key on license $label via API ($api_key_name)", $client_id, $software_id);
    logAudit("API", "Success", "Updated a key on license $label via API ($api_key_name)", $client_id);
}

// Output
require_once '../update_output.php';
