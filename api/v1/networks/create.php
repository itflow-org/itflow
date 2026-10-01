<?php

require_once '../validate_api_key.php';

require_once '../require_post_method.php';

// Parse Info
require_once 'network_model.php';

// Default
$insert_id = false;

if (!empty($name) && !empty($client_id) && !empty($network)) {

    // Insert network
    $insert_sql = mysqli_query($mysqli, "INSERT INTO networks SET network_name = '$name', network_description = '$description', network_vlan = $vlan, network = '$network', network_gateway = '$gateway', network_primary_dns = '$primary_dns', network_secondary_dns = '$secondary_dns', network_dhcp_range = '$dhcp_range', network_notes = '$notes', network_location_id = $location_id, network_client_id = $client_id");

    // Check insert & get insert ID
    if ($insert_sql) {
        $insert_id = mysqli_insert_id($mysqli);

        // Logging
        logAudit("Network", "Create", "$name via API ($api_key_name)", $client_id, $insert_id);
        logAudit("API", "Success", "Created network $name via API ($api_key_name)", $client_id);
    }

}

// Output
require_once '../create_output.php';