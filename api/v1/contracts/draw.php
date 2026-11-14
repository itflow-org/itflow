<?php
/*
 * API - Contracts - Draw
 * POST /api/v1/contracts/draw.php
 *
 * Draw the time on the client's done tickets waiting to bill from its contracts'
 * prepaid hours now, as Draw hours does - the nightly run does it too. Time over
 * what is left stays to bill.
 *
 * Parameters (POST, JSON body): api_key, contract_id, client_id (its client - needed
 * when the key's user is limited to certain clients),
 * ticket_id (optional - just this ticket)
 *
 * Answer: tickets (references), hours, left, and overage - hours that went over
 */

require_once '../validate_api_key.php';
require_once '../require_post_method.php';
require_once "../../../includes/load_global_settings.php";
require_once "../../../includes/load_company_settings.php";
require_once 'contract_find.php';

$update_count = false;

if ($contract) {
    if (!contractHoursReady()) {
        apiFormRefuse('Prepaid hours need the database update - run it from Maintenance > Update first');
    }
    $ticket_ids = [];
    if (!empty($_POST['ticket_id'])) {
        $ticket_id = intval($_POST['ticket_id']);
        if (intval(getFieldById('tickets', $ticket_id, 'ticket_client_id')) !== $client_id) {
            apiFormRefuse("ticket_id isn't one of the client's tickets");
        }
        $ticket_ids = [$ticket_id];
    }
    $result = contractHoursDraw($client_id, $ticket_ids, 'The API (' . html_entity_decode($api_key_name, ENT_QUOTES, 'UTF-8') . ')', $session_user_id);
    $update_count = 1;
    $return_arr['tickets'] = array_keys($result['drawn']);
    $return_arr['hours'] = $result['hours'];
    $return_arr['left'] = $result['left'];
    $return_arr['overage'] = array_sum($result['overage']);
    if ($result['drawn']) {
        logAudit("Contract", "Edit", "Drew " . escapeSql(contractHoursWords($result['hours'])) . " of prepaid hours for " . count($result['drawn']) . " ticket(s): " . escapeSql(implode(', ', array_keys($result['drawn']))) . " via API ($api_key_name)", $client_id);
    }
}

// Output
require_once '../update_output.php';
