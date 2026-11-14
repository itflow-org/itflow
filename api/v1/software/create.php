<?php

require_once '../validate_api_key.php';
require_once '../require_post_method.php';

/*
 * Create a license for a client - the same rules as the New License form
 * (softwareFormSet()): a name is needed, dates are Y-m-d, seats a whole number
 * (none sent is no limit), and the vendor must be one of the client's.
 * software_keys adds license keys, one per line.
 */

$insert_id = false;

if (!empty($client_id) && mysqli_num_rows(mysqli_query($mysqli, "SELECT 1 FROM clients WHERE client_id = $client_id AND client_archived_at IS NULL"))) {

    require_once 'software_model.php';

    $set = softwareFormSet($software_post, $client_id);
    if ($set === null) {
        http_response_code(400);
        $return_arr['success'] = "False";
        $return_arr['message'] = 'A license needs a name.';
        echo json_encode($return_arr);
        exit();
    }

    $insert_sql = mysqli_query($mysqli, "INSERT INTO software SET $set, software_client_id = $client_id");

    if ($insert_sql) {
        $insert_id = mysqli_insert_id($mysqli);

        // Its keys, one per line (functions/software.php)
        $keys_added = softwareAddKeys($insert_id, is_scalar($_POST['software_keys'] ?? '') ? (string) ($_POST['software_keys'] ?? '') : '');

        $name = escapeSql(trim($software_post['name']));
        logAudit("Software", "Create", "$name" . ($keys_added ? " with $keys_added key" . ($keys_added === 1 ? '' : 's') : '') . " via API ($api_key_name)", $client_id, $insert_id);
        logAudit("API", "Success", "Created license $name via API ($api_key_name)", $client_id);
    }
}

// Output
require_once '../create_output.php';
