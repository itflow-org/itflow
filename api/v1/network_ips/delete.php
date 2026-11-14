<?php
/*
 * API - Network IPs - Delete
 * POST /api/v1/network_ips/delete.php
 *
 * Take an address off its network's address plan, as Delete does on it.
 *
 * Parameters (POST, JSON body): api_key, ip_id, client_id (the network's client -
 * needed when the key's user is limited to certain clients)
 */

require_once '../validate_api_key.php';
require_once '../require_post_method.php';

$delete_count = false;

unset($_POST['network_id']);
require_once 'network_ip_find.php';

if ($ip_row) {

    mysqli_query($mysqli, "DELETE FROM network_ips WHERE ip_id = " . intval($ip_row['ip_id']));
    $delete_count = mysqli_affected_rows($mysqli);

    if ($delete_count > 0) {
        $label = escapeSql($ip_row['ip_address'] . ' from network ' . $ip_row['network_name']);
        logAudit("IP Address", "Delete", "Deleted IP $label via API ($api_key_name)", $client_id);
        logAudit("API", "Success", "Deleted IP $label via API ($api_key_name)", $client_id);
    }
}

// Output
require_once '../delete_output.php';
