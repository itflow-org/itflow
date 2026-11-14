<?php
/*
 * API - Network IPs - Update
 * POST /api/v1/network_ips/update.php
 *
 * Change a documented address, as Edit does - only what is sent, with the same
 * checks as documenting it.
 *
 * Parameters (POST, JSON body):
 *   api_key    required - Your API key
 *   ip_id      required - The address
 *   client_id  the network's client - needed when the key's user is limited to certain clients
 *   ip_address, ip_type, ip_hostname, ip_mac, ip_description   optional
 */

require_once '../validate_api_key.php';
require_once '../require_post_method.php';

$update_count = false;

unset($_POST['network_id']);
require_once 'network_ip_find.php';

if ($ip_row) {

    $ip_id = intval($ip_row['ip_id']);
    $network_id = intval($ip_row['ip_network_id']);

    $error = '';
    $fields = networkIpFieldsSet($ip_post($ip_row), $error);
    if ($fields === '') {
        $ip_refuse($error);
    }

    // Its own row is left out, so keeping the address doesn't trip the duplicate check
    $ip_address = array_key_exists('ip_address', $_POST) ? trim((string) (is_scalar($_POST['ip_address']) ? $_POST['ip_address'] : '')) : (string) $ip_row['ip_address'];
    $ip_error = checkIpForNetwork($ip_address, $network_id, $ip_id);
    if ($ip_error) {
        $ip_refuse($ip_error);
    }

    if (mysqli_query($mysqli, "UPDATE network_ips SET ip_address = '" . escapeSql($ip_address) . "', $fields WHERE ip_id = $ip_id")) {
        $update_count = 1;
        $label = escapeSql("$ip_address on network " . $ip_row['network_name']);
        logAudit("IP Address", "Edit", "Edited IP $label via API ($api_key_name)", $client_id, $ip_id);
        logAudit("API", "Success", "Edited IP $label via API ($api_key_name)", $client_id);
    }
}

// Output
require_once '../update_output.php';
