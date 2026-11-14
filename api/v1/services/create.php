<?php

require_once '../validate_api_key.php';
require_once '../require_post_method.php';

/*
 * Create a service for a client - the same rules as the New Service form
 * (serviceFormSet()): a name is needed, importance is High, Medium or Low,
 * and a review schedule with no date sets its first review itself.
 */

$insert_id = false;

if (!empty($client_id) && mysqli_num_rows(mysqli_query($mysqli, "SELECT 1 FROM clients WHERE client_id = $client_id AND client_archived_at IS NULL"))) {

    require_once 'service_model.php';

    $error = '';
    $set = serviceFormSet($service_post, $error);
    if ($set === '') {
        http_response_code(400);
        $return_arr['success'] = "False";
        $return_arr['message'] = $error;
        echo json_encode($return_arr);
        exit();
    }

    $insert_sql = mysqli_query($mysqli, "INSERT INTO services SET $set, service_client_id = $client_id");

    if ($insert_sql) {
        $insert_id = mysqli_insert_id($mysqli);

        $service_name = escapeSql(trim($service_post['name']));
        logAudit("Service", "Create", "$service_name via API ($api_key_name)", $client_id, $insert_id);
        logAudit("API", "Success", "Created service $service_name via API ($api_key_name)", $client_id);
    }
}

// Output
require_once '../create_output.php';
