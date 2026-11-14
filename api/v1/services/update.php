<?php

require_once '../validate_api_key.php';
require_once '../require_post_method.php';

// Update a service - only the fields you send change (serviceFormSet(), as the Edit form)

// Parse ID
$service_id = intval($_POST['service_id'] ?? 0);

// Default
$update_count = false;

if (!empty($service_id)) {

    $service_row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT * FROM services WHERE service_id = $service_id AND service_client_id = $client_id AND service_archived_at IS NULL LIMIT 1"));

    if ($service_row) {

        require_once 'service_model.php';

        $error = '';
        $set = serviceFormSet($service_post, $error, $service_row);
        if ($set === '') {
            http_response_code(400);
            $return_arr['success'] = "False";
            $return_arr['message'] = $error;
            echo json_encode($return_arr);
            exit();
        }

        $update_sql = mysqli_query($mysqli, "UPDATE services SET $set WHERE service_id = $service_id AND service_client_id = $client_id LIMIT 1");

        if ($update_sql) {
            // A service saved as it was still counts as updated
            $update_count = 1;

            $service_name = escapeSql(trim($service_post['name']));
            logAudit("Service", "Edit", "$service_name via API ($api_key_name)", $client_id, $service_id);
            logAudit("API", "Success", "Edited service $service_name via API ($api_key_name)", $client_id);
        }
    }
}

// Output
require_once '../update_output.php';
