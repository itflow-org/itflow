<?php

require_once '../validate_api_key.php';

require_once '../require_post_method.php';

// Parse ID
$network_id = intval($_POST['network_id']);

// Default
$update_count = false;

if (!empty($network_id)) {

    $network_row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT * FROM networks WHERE network_id = '$network_id' AND network_client_id = $client_id AND network_archived_at IS NULL LIMIT 1"));

    // Variable assignment from POST - assigning the current database value if a value is not provided
    require_once 'network_model.php';

    if ($network_row) {

        $update_sql = mysqli_query($mysqli, "UPDATE networks SET network_name = '$name', network_description = '$description', network_vlan = $vlan, network = '$network', network_gateway = '$gateway', network_primary_dns = '$primary_dns', network_secondary_dns = '$secondary_dns', network_dhcp_range = '$dhcp_range', network_notes = '$notes', network_location_id = $location_id WHERE network_id = $network_id AND network_client_id = $client_id AND network_archived_at IS NULL LIMIT 1");

        // Check update & get affected rows
        if ($update_sql) {
            $update_count = mysqli_affected_rows($mysqli);

            // Logging
            logAudit("Network", "Edit", "$name via API ($api_key_name)", $client_id, $network_id);
            logAudit("API", "Success", "Edited network $name via API ($api_key_name)", $client_id);
        }
    }
}

// Output
require_once '../update_output.php';