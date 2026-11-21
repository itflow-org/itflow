<?php

// An interface's fields from POST, as interfaceFormSet() takes them - with what the
// interface has now ($interface_row) for those not sent, on an update. $asset is its
// asset (asset_id, asset_name, asset_client_id). Sets $interface_set (the SET clause)
// and $interface_warnings, or turns the request down with 400 and why.

if (!function_exists('apiDeny')) {
    http_response_code(404);
    exit();
}

$interface_row = $interface_row ?? null;

$interface_refuse = function ($message) use (&$return_arr) {
    http_response_code(400);
    $message = rtrim(html_entity_decode(strip_tags($message), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    $return_arr['success'] = "False";
    $return_arr['message'] = preg_match('/[.!?]$/', $message) ? $message : "$message.";
    echo json_encode($return_arr);
    exit();
};

$interface_post = [];
foreach (['interface_name' => 'name', 'interface_description' => 'description', 'interface_type' => 'type', 'interface_mac' => 'mac', 'interface_ip' => 'ip',
    'interface_nat_ip' => 'nat_ip', 'interface_ipv6' => 'ipv6', 'interface_vlans' => 'vlans', 'interface_notes' => 'notes', 'interface_network_id' => 'network_id'] as $field => $key) {
    $interface_post[$key] = array_key_exists($field, $_POST) ? (is_scalar($_POST[$field]) ? (string) $_POST[$field] : '') : (string) ($interface_row[$field] ?? '');
}
// The address: an IP, DHCP, or empty for none
$interface_post['ip_mode'] = strtoupper(trim($interface_post['ip'])) === 'DHCP' ? 'dhcp' : (trim($interface_post['ip']) === '' ? 'none' : 'static');

// A type from Admin > Categories, as the picker offers - the one it has stays allowed
if (array_key_exists('interface_type', $_POST)) {
    $type = categoryPickedName('network_interface', $interface_post['type'], (string) ($interface_row['interface_type'] ?? ''));
    if ($type === null) {
        $interface_refuse('interface_type is one of: ' . implode(', ', array_keys(categoryPicker('network_interface'))) . ' - or empty for none.');
    }
    $interface_post['type'] = $type;
}

// The network has to be the asset's client's
if (array_key_exists('interface_network_id', $_POST) && intval($_POST['interface_network_id'])
    && !assetClientOwnedId('network', $_POST['interface_network_id'], $asset['asset_client_id'])) {
    $interface_refuse("interface_network_id isn't one of the client's networks.");
}

$error = '';
$interface_warnings = [];
$interface_set = interfaceFormSet($interface_post, $asset, intval($interface_row['interface_id'] ?? 0), $error, $interface_warnings);
if ($interface_set === '') {
    $interface_refuse($error);
}
$interface_warnings = array_map(function ($warning) {
    return html_entity_decode(strip_tags($warning), ENT_QUOTES | ENT_HTML5, 'UTF-8');
}, $interface_warnings);

// What it is plugged into - one of the client's other assets' interfaces
$interface_connect = null;
if (array_key_exists('connected_to', $_POST)) {
    $interface_connect = interfaceConnectTarget($_POST['connected_to'], $asset['asset_client_id'], $asset['asset_id']);
    if (intval($_POST['connected_to']) && !$interface_connect) {
        $interface_refuse("connected_to is an interface on another of the client's assets - not archived.");
    }
}
