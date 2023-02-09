<?php

require_once('../validate_api_key.php');
require_once('../require_post_method.php');

// Parse ID
$contact_id = intval($_POST['contact_id']);

// Default
$delete_count = false;

if (!empty($contact_id)) {
    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT * FROM contacts WHERE contact_id = $contact_id AND contact_client_id = $client_id AND company_id = '$company_id' LIMIT 1"));
    $contact_name = $row['contact_name'];

    $delete_sql = mysqli_query($mysqli, "DELETE FROM contacts WHERE contact_id = $contact_id AND contact_client_id = $client_id AND company_id = '$company_id' LIMIT 1");

    // Check delete & get affected rows
    if ($delete_sql && !empty($contact_name)) {
        $delete_count = mysqli_affected_rows($mysqli);

        //Logging
        mysqli_query($mysqli, "INSERT INTO logs SET log_type = 'Contact', log_action = 'Deleted', log_description = '$contact_name via API ($api_key_name)', log_ip = '$ip', log_user_agent = '$user_agent', log_client_id = $client_id, company_id = $company_id");
    }
}

// Output
require_once('../delete_output.php');
