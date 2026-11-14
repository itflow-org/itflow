<?php

// The contract a request names (contract_id) - of a client the key's user can reach,
// and client_id's when it is sent - as $contract, or null. $client_id becomes its client.

if (!function_exists('apiDeny')) {
    http_response_code(404);
    exit();
}

require_once '../form_input.php';

$contract_id = intval($contract_lookup_id ?? ($_POST['contract_id'] ?? 0));
$contract = $contract_id ? contractRow($contract_id) : null;
if ($contract && (!apiUserCanAccessClient($contract['contract_client_id']) || ($client_id && intval($contract['contract_client_id']) !== $client_id))) {
    $contract = null;
}
if ($contract) {
    $client_id = intval($contract['contract_client_id']);
    $contract_label = escapeSql($contract['contract_name']);
}
