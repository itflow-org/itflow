<?php

require_once '../validate_api_key.php';
require_once '../require_post_method.php';

// Parse Info
$client_id = intval($_POST['client_id']);

// Default
$update_count = false;

if (!empty($client_id)) {

    // Fetch current client data
    $client_row = mysqli_fetch_assoc(mysqli_query($mysqli, "
        SELECT * FROM clients
        WHERE client_id = $client_id
        LIMIT 1
    "));

    // Assign variables from POST or fallback to DB
    require_once 'client_model.php';

    // Update client
    $update_sql = mysqli_query($mysqli, "
        UPDATE clients SET
            client_name = '$name',
            client_type = '$type',
            client_website = '$website',
            client_referral = '$referral',
            client_rate = $rate,
            client_currency_code = '$currency_code',
            client_net_terms = $net_terms,
            client_abbreviation = '$abbreviation',
            client_tax_id_number = '$tax_id_number',
            client_lead = $lead,
            client_notes = '$notes'
        WHERE client_id = $client_id
        LIMIT 1
    ");

    // Check update & get affected rows
    if ($update_sql) {
        $update_count = mysqli_affected_rows($mysqli);

        // Logging
        logAction("Client", "Edit", "$name via API ($api_key_name)", $client_id);
        logAction("API", "Success", "Edited client $name via API ($api_key_name)", $client_id);
    }
}

// Output
require_once '../update_output.php';
