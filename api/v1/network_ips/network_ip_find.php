<?php

// The network a request names (network_id) or the documented address (ip_id) - of a
// client the key's user can reach, and client_id's when it is sent - as $network
// and $ip_row (null when not found). $client_id becomes the network's client.

if (!function_exists('apiDeny')) {
    http_response_code(404);
    exit();
}

$ip_row = !empty($_POST['ip_id']) ? networkIpRow($_POST['ip_id']) : null;
$network = $ip_row ? networkRow($ip_row['ip_network_id']) : (!empty($_POST['network_id']) ? networkRow($_POST['network_id']) : null);
if ($network && (!apiUserCanAccessClient($network['network_client_id']) || ($client_id && intval($network['network_client_id']) !== $client_id))) {
    $network = null;
    $ip_row = null;
}
if ($network) {
    $client_id = intval($network['network_client_id']);
}

// What the form posts for an address's other fields, from the API's names - with
// what the address has now for those not sent, on an update
$ip_post = function ($row = null) {
    $post = [];
    foreach (['ip_type' => 'type', 'ip_hostname' => 'hostname', 'ip_mac' => 'mac', 'ip_description' => 'description'] as $field => $key) {
        $post[$key] = array_key_exists($field, $_POST) ? (is_scalar($_POST[$field]) ? (string) $_POST[$field] : '') : (string) ($row[$field] ?? '');
    }
    // A reservation documented without its MAC (an import) can still be changed, until a MAC is sent
    $post['allow_no_mac'] = $row && $row['ip_type'] === 'Reservation' && (string) $row['ip_mac'] === '' && !array_key_exists('ip_mac', $_POST);
    return $post;
};

$ip_refuse = function ($message) use (&$return_arr) {
    http_response_code(400);
    $return_arr['success'] = "False";
    $message = rtrim(html_entity_decode(strip_tags($message), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    $return_arr['message'] = preg_match('/[.!?]$/', $message) ? $message : "$message.";
    echo json_encode($return_arr);
    exit();
};

if (array_key_exists('ip_type', $_POST) && !isset(NETWORK_IP_TYPES[$_POST['ip_type']])) {
    $ip_refuse('ip_type is one of: ' . implode(', ', array_keys(NETWORK_IP_TYPES)) . '.');
}
