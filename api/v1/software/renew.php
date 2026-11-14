<?php
/*
 * API - Licenses - Renew
 * POST /api/v1/software/renew.php
 *
 * Renew a license, as Renew does - by a term from its renewal date (or from
 * today, when that has passed), or to a date of your choosing - with the
 * purchase reference for it.
 *
 * Parameters (POST, JSON body):
 *   api_key             required - Your API key
 *   software_id         required - The license
 *   client_id           its client - needed when the key's user is limited to certain clients
 *   renew_term          1m, 3m, 6m, 1y, 2y or 3y - this or renew_date
 *   renew_date          The new renewal date, Y-m-d
 *   purchase_reference  optional - The order or invoice it was renewed on
 *
 * Answer: software_expire - its renewal date now.
 */

require_once '../validate_api_key.php';
require_once '../require_post_method.php';

$update_count = false;

$software_lookup_id = intval($_POST['software_id'] ?? 0);
require_once 'software_find.php';

if ($software) {

    $software_id = intval($software['software_id']);
    $term = (string) ($_POST['renew_term'] ?? '');
    $until = !empty($_POST['renew_date']) ? softwareFormDate($_POST['renew_date']) : ($term !== '' ? softwareRenewalExtended($software['software_expire'], $term) : null);

    if (!$until) {
        http_response_code(400);
        $return_arr['success'] = "False";
        $return_arr['message'] = 'Send renew_term - one of ' . implode(', ', array_keys(SOFTWARE_RENEWAL_TERMS)) . ' - or renew_date, the new renewal date as 2027-06-30.';
        echo json_encode($return_arr);
        exit();
    }

    $reference = trim((string) (is_scalar($_POST['purchase_reference'] ?? '') ? ($_POST['purchase_reference'] ?? '') : ''));
    $reference_sql = $reference !== '' ? ", software_purchase_reference = '" . escapeSql(mb_substr($reference, 0, 200)) . "'" : '';

    if (mysqli_query($mysqli, "UPDATE software SET software_expire = '$until' $reference_sql WHERE software_id = $software_id")) {
        $update_count = 1;
        $return_arr['software_expire'] = $until;

        $was = $software['software_expire'] ?: 'no date';
        $label = escapeSql($software['software_name']) . " until $until (was " . escapeSql($was) . ")" . ($reference !== '' ? ', reference ' . escapeSql($reference) : '');
        logAudit("Software", "Renew", "Renewed license $label via API ($api_key_name)", $client_id, $software_id);
        logAudit("API", "Success", "Renewed license " . escapeSql($software['software_name']) . " via API ($api_key_name)", $client_id);
    }
}

// Output
require_once '../update_output.php';
