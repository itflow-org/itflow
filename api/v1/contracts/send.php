<?php
/*
 * API - Contracts - Send
 * POST /api/v1/contracts/send.php
 *
 * Send a contract to be signed, as Send for signature does - to one of the client's
 * contacts, or a name and email address - with a link to read and sign it. With
 * contract_signature_id, the link of a request still waiting goes again instead.
 *
 * Parameters (POST, JSON body): api_key, contract_id, client_id (its client - needed
 * when the key's user is limited to certain clients),
 *   contact_id, or to_name and to_email - who signs it
 *   note                   optional - A note in the email
 *   contract_signature_id  optional - Send this waiting request's link again
 *
 * Answer: contract_signature_id, and emailed - 0 when mail isn't set up (the link is then in the contract)
 */

require_once '../validate_api_key.php';
require_once '../require_post_method.php';
require_once "../../../includes/load_global_settings.php";
require_once "../../../includes/load_company_settings.php";
require_once 'contract_find.php';

$update_count = false;

if ($contract) {
    if (!contractSignaturesReady()) {
        apiFormRefuse('Signing needs the database update - run it from Maintenance > Update first');
    }

    // The link of one still waiting, again
    if (!empty($_POST['contract_signature_id'])) {
        $round = contractSignatureRow($_POST['contract_signature_id']);
        $state = $round && intval($round['contract_id']) === $contract_id ? contractSignatureState($round) : null;
        if (!$state || !in_array($state['key'], ['sent', 'viewed'], true)) {
            apiFormRefuse("That request isn't waiting for the client any more");
        }
        $queued = contractSignatureRequestEmail($round);
        $update_count = 1;
        $return_arr['contract_signature_id'] = intval($round['contract_signature_id']);
        $return_arr['emailed'] = $queued ? 1 : 0;
        logAudit("Contract", "Send", "Sent the signing link for contract $contract_label again to " . escapeSql($round['contract_signature_sent_to_name'] . ' <' . $round['contract_signature_sent_to_email'] . '>') . " via API ($api_key_name)", $client_id, $contract_id);
        require_once '../update_output.php';
    }

    if (in_array($contract['contract_status'], ['Ended', 'Cancelled'], true)) {
        apiFormRefuse("An ended or cancelled contract isn't sent for signature - renew it first");
    }
    if (!empty(getFieldById('clients', $client_id, 'client_archived_at'))) {
        apiFormRefuse('The client is archived - restore them before sending them a contract to sign');
    }
    $contact_id = intval($_POST['contact_id'] ?? 0);
    if ($contact_id > 0) {
        $contact = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT contact_name, contact_email FROM contacts WHERE contact_id = $contact_id AND contact_client_id = $client_id AND contact_archived_at IS NULL"));
        $to_name = (string) ($contact['contact_name'] ?? '');
        $to_email = trim((string) ($contact['contact_email'] ?? ''));
        if (!$contact || !filter_var($to_email, FILTER_VALIDATE_EMAIL)) {
            apiFormRefuse("contact_id is one of the client's contacts with an email address - or send to_name and to_email");
        }
    } else {
        $contact_id = 0;
        $to_name = mb_substr(trim(preg_replace('/\s+/u', ' ', strip_tags((string) ($_POST['to_name'] ?? '')))), 0, 200);
        $to_email = trim((string) ($_POST['to_email'] ?? ''));
        if ($to_name === '' || !filter_var($to_email, FILTER_VALIDATE_EMAIL) || strlen($to_email) > 255) {
            apiFormRefuse('Send contact_id, or to_name and to_email - who signs it');
        }
    }
    $note = mb_substr(trim((string) ($_POST['note'] ?? '')), 0, 2000);

    [$round_id, $queued] = contractSignatureSend($contract, $to_name, $to_email, $contact_id, $note, $session_user_id);
    $update_count = 1;
    $return_arr['contract_signature_id'] = intval($round_id);
    $return_arr['emailed'] = $queued ? 1 : 0;
    logAudit("Contract", "Send", "Sent contract $contract_label for signature to " . escapeSql("$to_name <$to_email>") . " via API ($api_key_name)", $client_id, $contract_id);
    logAudit("API", "Success", "Sent contract $contract_label for signature via API ($api_key_name)", $client_id);
}

// Output
require_once '../update_output.php';
