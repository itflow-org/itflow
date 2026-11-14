<?php

require_once '../validate_api_key.php';
require_once '../require_post_method.php';

// Update a license - only the fields you send change (softwareFormSet(), as the Edit form)

// Parse ID
$software_id = intval($_POST['software_id'] ?? 0);

// Default
$update_count = false;

if (!empty($software_id)) {

    $software_row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT * FROM software WHERE software_id = $software_id AND software_client_id = $client_id AND software_archived_at IS NULL LIMIT 1"));

    if ($software_row) {

        require_once 'software_model.php';

        $set = softwareFormSet($software_post, $client_id);
        if ($set === null) {
            http_response_code(400);
            $return_arr['success'] = "False";
            $return_arr['message'] = 'A license needs a name.';
            echo json_encode($return_arr);
            exit();
        }

        $update_sql = mysqli_query($mysqli, "UPDATE software SET $set WHERE software_id = $software_id AND software_client_id = $client_id LIMIT 1");

        if ($update_sql) {
            // A license saved as it was still counts as updated
            $update_count = 1;

            $name = escapeSql(trim($software_post['name']));
            logAudit("Software", "Edit", "$name via API ($api_key_name)", $client_id, $software_id);
            logAudit("API", "Success", "Edited license $name via API ($api_key_name)", $client_id);
        }
    }
}

// Output
require_once '../update_output.php';
