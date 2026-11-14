<?php
/*
 * API - License Keys - Create
 * POST /api/v1/software_keys/create.php
 *
 * Add keys to a license, as Add keys does - one per line, or a list. Blank ones
 * and keys it has already are skipped.
 *
 * Parameters (POST, JSON body):
 *   api_key      required - Your API key
 *   software_id  required - The license
 *   client_id    its client - needed when the key's user is limited to certain clients
 *   keys         required - The keys, one per line or as a list
 *
 * Answer: insert_id (the last key added), and added - how many.
 */

require_once '../validate_api_key.php';
require_once '../require_post_method.php';

$insert_id = false;

$software_lookup_id = intval($_POST['software_id'] ?? 0);
require_once '../software/software_find.php';

if ($software) {

    $software_id = intval($software['software_id']);
    $text = is_array($_POST['keys'] ?? null) ? implode("\n", array_filter($_POST['keys'], 'is_scalar')) : (string) (is_scalar($_POST['keys'] ?? '') ? ($_POST['keys'] ?? '') : '');

    $added = softwareAddKeys($software_id, $text);
    if (!$added) {
        http_response_code(400);
        $return_arr['success'] = "False";
        $return_arr['message'] = 'No new keys to add - keys was blank, or ' . $software['software_name'] . ' has them already.';
        echo json_encode($return_arr);
        exit();
    }

    $insert_id = intval(mysqli_fetch_row(mysqli_query($mysqli, "SELECT MAX(software_key_id) FROM software_keys WHERE software_key_software_id = $software_id"))[0]);
    $return_arr['added'] = $added;

    $label = "$added key" . ($added === 1 ? '' : 's') . ' to license ' . escapeSql($software['software_name']);
    logAudit("Software", "Keys", "Added $label via API ($api_key_name)", $client_id, $software_id);
    logAudit("API", "Success", "Added $label via API ($api_key_name)", $client_id);
}

// Output
require_once '../create_output.php';
