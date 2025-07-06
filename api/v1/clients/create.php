<?php

require_once '../validate_api_key.php';

require_once '../require_post_method.php';

// Parse Info
require_once 'client_model.php';


// Default
$insert_id = false;

// To add a client, we just need a name and an "ANY CLIENT" API key
if (!empty($name) && $client_id == 0) {

    // Insert client
    $insert_sql = mysqli_query($mysqli, "INSERT INTO clients SET client_name = '$name', client_type = '$type', client_website = '$website', client_referral = '$referral', client_rate = $rate, client_currency_code = '$currency_code', client_net_terms = $net_terms, client_tax_id_number = '$tax_id_number', client_lead = $lead, client_notes = '$notes', client_accessed_at = NOW()");

    // Check insert & get insert ID
    if ($insert_sql) {
        $insert_id = mysqli_insert_id($mysqli);
        
        // Logging
        logAction("Client", "Create", "$name via API ($api_key_name)", $insert_id);
        logAction("API", "Success", "Created client $name via API ($api_key_name)", $insert_id);
    }

}

// Output
require_once '../create_output.php';
