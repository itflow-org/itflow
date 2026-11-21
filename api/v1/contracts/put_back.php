<?php
/*
 * API - Contracts - Put back
 * POST /api/v1/contracts/put_back.php
 *
 * Put a ticket's prepaid hours back, as Put back does - its time is to bill as
 * usual again. Not once the ticket is on an invoice.
 *
 * Parameters (POST, JSON body): api_key, contract_id, client_id (its client - needed
 * when the key's user is limited to certain clients), ticket_id
 *
 * Answer: hours - how many went back
 */

require_once '../validate_api_key.php';
require_once '../require_post_method.php';
require_once "../../../includes/load_global_settings.php";
require_once "../../../includes/load_company_settings.php";
require_once 'contract_find.php';

$update_count = false;

if ($contract) {
    $ticket_id = intval($_POST['ticket_id'] ?? 0);
    $ticket = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT ticket_prefix, ticket_number, ticket_invoice_id FROM tickets WHERE ticket_id = $ticket_id AND ticket_client_id = $client_id"));
    if (!$ticket || !contractHoursReady()) {
        apiFormRefuse("ticket_id isn't one of the client's tickets");
    }
    $hours = contractHoursPutBack($ticket_id, 'The API (' . html_entity_decode($api_key_name, ENT_QUOTES, 'UTF-8') . ')', $session_user_id);
    if ($hours === null) {
        apiFormRefuse(intval($ticket['ticket_invoice_id']) ? 'That ticket is on an invoice now, so its hours stay drawn' : 'That ticket drew no prepaid hours');
    }
    $update_count = 1;
    $return_arr['hours'] = $hours;
    logAudit("Contract", "Edit", "Put " . escapeSql(contractHoursWords($hours)) . " back on the prepaid hours from ticket " . escapeSql($ticket['ticket_prefix'] . $ticket['ticket_number']) . " via API ($api_key_name)", $client_id, 0);
}

// Output
require_once '../update_output.php';
