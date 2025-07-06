<?php

require_once '../validate_api_key.php';

require_once '../require_post_method.php';


// Parse ID
$contact_id = intval($_POST['contact_id']);

// Default
$delete_count = false;

if (!empty($contact_id)) {
    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT * FROM contacts WHERE contact_id = $contact_id AND contact_client_id = $client_id LIMIT 1"));
    $contact_name = $row['contact_name'];

    $delete_sql = mysqli_query($mysqli, "DELETE FROM contacts WHERE contact_id = $contact_id AND contact_client_id = $client_id LIMIT 1");

    // Check delete & get affected rows
    if ($delete_sql && !empty($contact_name)) {
        $delete_count = mysqli_affected_rows($mysqli);

        // Logging
        logAction("Contact", "Delete", "$contact_name via API ($api_key_name)", $client_id);
    }
}

// Output
require_once '../delete_output.php';

