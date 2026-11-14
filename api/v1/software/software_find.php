<?php

// The license a request names (software_id) - of a client the key's user can reach,
// and client_id's when it is sent - as $software (softwareRow()), or null. $client_id
// becomes its client.

if (!function_exists('apiDeny')) {
    http_response_code(404);
    exit();
}

$software = !empty($software_lookup_id) ? softwareRow($software_lookup_id) : null;
if ($software && (!apiUserCanAccessClient($software['software_client_id']) || ($client_id && intval($software['software_client_id']) !== $client_id))) {
    $software = null;
}
if ($software) {
    $client_id = intval($software['software_client_id']);
}

// A seat warning, as plain text
$software_seat_warning = function ($software_id) {
    $warning = softwareSeatWarning($software_id);
    return $warning === '' ? '' : ucfirst(ltrim(html_entity_decode(strip_tags($warning), ENT_QUOTES | ENT_HTML5, 'UTF-8'), ' -')) . '.';
};
