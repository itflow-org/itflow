<?php
/*
 * API - Network IPs - Read
 * GET /api/v1/network_ips/read.php
 *
 * The addresses documented on a network, lowest first - fixed ones, DHCP
 * reservations (with the MAC they are for), ones held back and floating ones.
 *
 *   network_id     This network's addresses - this or ip_id is needed
 *   ip_id          Just this address
 *   client_id      optional - Only if the network is this client's
 *   limit, offset  optional - Paging
 */

require_once '../validate_api_key.php';
require_once '../require_get_method.php';

$network_id = intval($_GET['network_id'] ?? 0);
$ip_id = intval($_GET['ip_id'] ?? 0);

if (!$network_id && !$ip_id) {
    http_response_code(400);
    $return_arr['success'] = "False";
    $return_arr['message'] = "network_ips/read.php needs network_id or ip_id.";
    echo json_encode($return_arr);
    exit();
}

$sql = mysqli_query($mysqli, "SELECT network_ips.*, network_name, network, network_client_id
    FROM network_ips JOIN networks ON network_id = ip_network_id
    WHERE " . ($ip_id ? "ip_id = $ip_id" : "ip_network_id = $network_id") . " " . apiClientScopeSql('network_client_id') . "
    ORDER BY INET_ATON(ip_address) IS NULL, INET_ATON(ip_address), ip_address LIMIT $limit OFFSET $offset");

// Output
require_once "../read_output.php";
