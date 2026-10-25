<?php

/*
 * ITFlow - contracts: add, change, copy (through add), make a draft active,
 * renew, cancel and delete; send one for signature, send the request again,
 * withdraw it, countersign, and the PDF; prepaid hours - add a block (and
 * invoice it), delete one nothing was drawn from, draw tickets' time now,
 * put a ticket's hours back; the recurring invoice that bills it - make
 * one, link one, unlink it. Needs Sales - to change, or to delete. The work
 * is in functions/contract.php and, for signing, prepaid hours and billing,
 * functions/contract_signature.php, functions/contract_hours.php and
 * functions/contract_billing.php.
 */

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

/* The contract posted, after the checks every action makes - or a flash and back. */
function contractPostRow($id) {
    global $client_id;
    $row = contractRow($id);
    if (!$row) {
        flashAlert("That contract is gone - it may have been deleted.", 'error');
        redirect();
    }
    $client_id = intval($row['contract_client_id']);
    enforceClientAccess();
    return $row;
}

/* A round of signing posted, after the same checks - or a flash and back. */
function contractSignaturePostRound($id) {
    global $client_id;
    $round = contractSignatureRow($id);
    if (!$round) {
        flashAlert("That signing request is gone - it may have been deleted with its contract.", 'error');
        redirect();
    }
    $client_id = intval($round['contract_client_id']);
    enforceClientAccess();
    return $round;
}

if (isset($_POST['add_contract']) || isset($_POST['edit_contract'])) {

    validateCSRFToken();
    enforceUserPermission('module_sales', 2);

    $row = isset($_POST['edit_contract']) ? contractPostRow($_POST['contract_id'] ?? 0) : null;
    $input = $_POST;
    if ($row) {
        // A contract stays with its client, and in its status - those change through their own actions
        $input['client_id'] = $row['contract_client_id'];
        $input['status'] = $row['contract_status'] === 'Draft' ? 'Draft' : 'Active';
    }
    [$fields, $error] = contractFromInput($input, 'contract');
    if ($error !== '') {
        flashAlert(escapeHtml($error) . ' The contract was not saved.', 'error');
        redirect();
    }
    $client_id = $fields['client_id'];
    enforceClientAccess();

    if ($row) {
        $contract_id = intval($row['contract_id']);
        $changes = contractUpdate($row, $fields);
        contractHoursSync($contract_id);
        if ($changes) {
            logAudit("Contract", "Edit", "$session_name changed contract " . escapeSql($row['contract_name']) . ": " . escapeSql(implode('; ', $changes)), $client_id, $contract_id);
        }
        // Out for signature, and what it says changed - no one should sign what it no longer says
        $withdrawn = contractSignaturesReady() ? contractSignatureCheckChanged(contractRow($contract_id)) : null;
        if ($withdrawn) {
            logAudit("Contract", "Withdraw", "The signing request for contract " . escapeSql($fields['name']) . " sent to " . escapeSql($withdrawn['contract_signature_sent_to_name']) . " was withdrawn - the contract changed", $client_id, $contract_id);
        }
        flashAlert("Contract <strong>" . escapeHtml($fields['name']) . "</strong> saved" . ($withdrawn ? " - it was out for signature, so that request was withdrawn. Send it again for the new version." : ''), $withdrawn ? 'warning' : 'success');
    } else {
        $contract_id = contractCreate($fields);
        contractHoursSync($contract_id);
        logAudit("Contract", "Create", "$session_name added " . strtolower($fields['status']) . " contract " . escapeSql($fields['name']) . " (" . escapeSql(contractTypeLabel($fields['type'])) . ")"
            . ($fields['start_date'] !== '' ? " from " . escapeSql($fields['start_date']) : '') . ($fields['end_date'] !== '' ? " to " . escapeSql($fields['end_date']) : ''), $client_id, $contract_id);
        flashAlert("Contract <strong>" . escapeHtml($fields['name']) . "</strong> added" . ($fields['status'] === 'Draft' ? ' as a draft' : ''));
        redirect("contract.php?client_id=$client_id&contract_id=$contract_id");
    }

    redirect();

}

if (isset($_POST['activate_contract'])) {

    validateCSRFToken();
    enforceUserPermission('module_sales', 2);

    $row = contractPostRow($_POST['contract_id'] ?? 0);
    $start = trim((string) ($_POST['start_date'] ?? ''));
    if ($row['contract_status'] !== 'Draft' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $start) || !strtotime($start)) {
        flashAlert("Pick the day it is in force from.", 'error');
        redirect();
    }
    if ($row['contract_end_date'] && $row['contract_end_date'] < $start) {
        flashAlert("It ends before that day - change its end date first.", 'error');
        redirect();
    }

    $end = contractActivate($row, $start);
    contractHoursSync(intval($row['contract_id']));
    $recurring_state = contractRecurringFollow(intval($row['contract_id']), stripslashes($session_name));
    logAudit("Contract", "Edit", "$session_name made contract " . escapeSql($row['contract_name']) . " active from " . escapeSql($start) . ($end !== '' ? " to " . escapeSql($end) : ''), $client_id, intval($row['contract_id']));
    flashAlert("Contract <strong>" . escapeHtml($row['contract_name']) . "</strong> is active" . ($recurring_state === 'started' ? ' - its recurring invoice is running' : ''));

    redirect();

}

if (isset($_POST['renew_contract'])) {

    validateCSRFToken();
    enforceUserPermission('module_sales', 2);

    $row = contractPostRow($_POST['contract_id'] ?? 0);
    $end = trim((string) ($_POST['end_date'] ?? ''));
    if ($row['contract_status'] === 'Draft' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $end) || !strtotime($end) || $end < date('Y-m-d') || ($row['contract_end_date'] && $end <= $row['contract_end_date'])) {
        flashAlert("Pick a new last day - after its current one, and not in the past.", 'error');
        redirect();
    }

    contractRenew($row, $end);
    contractHoursSync(intval($row['contract_id']));
    $recurring_state = contractRecurringFollow(intval($row['contract_id']), stripslashes($session_name));
    logAudit("Contract", "Edit", "$session_name renewed contract " . escapeSql($row['contract_name']) . " to " . escapeSql($end), $client_id, intval($row['contract_id']));
    flashAlert("Contract <strong>" . escapeHtml($row['contract_name']) . "</strong> renewed to " . date('M j, Y', strtotime($end)) . ($recurring_state === 'started' ? ' - its recurring invoice is running again' : ''));

    redirect();

}

if (isset($_POST['cancel_contract'])) {

    validateCSRFToken();
    enforceUserPermission('module_sales', 2);

    $row = contractPostRow($_POST['contract_id'] ?? 0);
    $last_day = trim((string) ($_POST['last_day'] ?? ''));
    $reason = mb_substr(trim(strip_tags((string) ($_POST['reason'] ?? ''))), 0, 255);
    if ($row['contract_status'] !== 'Active' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $last_day) || !strtotime($last_day)
        || ($row['contract_start_date'] && $last_day < $row['contract_start_date'])) {
        flashAlert("Pick its last day - not before it started.", 'error');
        redirect();
    }

    contractCancel($row, $last_day, $reason);
    // Its recurring invoice stops once the last day has passed - now, when it already has
    $recurring_state = contractRecurringFollow(intval($row['contract_id']), stripslashes($session_name));
    logAudit("Contract", "Edit", "$session_name cancelled contract " . escapeSql($row['contract_name']) . ", last day " . escapeSql($last_day) . ($reason !== '' ? " - " . escapeSql($reason) : ''), $client_id, intval($row['contract_id']));
    flashAlert("Contract <strong>" . escapeHtml($row['contract_name']) . "</strong> cancelled" . ($last_day >= date('Y-m-d') ? " - in force until " . date('M j, Y', strtotime($last_day)) : '')
        . ($recurring_state === 'stopped' ? ' - its recurring invoice is paused' : ''), 'error');

    redirect();

}

if (isset($_GET['delete_contract'])) {

    validateCSRFToken();
    enforceUserPermission('module_sales', 3);

    $row = contractPostRow($_GET['delete_contract']);
    mysqli_query($mysqli, "DELETE FROM contracts WHERE contract_id = " . intval($row['contract_id']));
    logAudit("Contract", "Delete", "$session_name deleted contract " . escapeSql($row['contract_name']), $client_id, intval($row['contract_id']));
    flashAlert("Contract <strong>" . escapeHtml($row['contract_name']) . "</strong> deleted", 'error');

    redirect("contracts.php?client_id=$client_id");

}

if (isset($_POST['send_contract_signature'])) {

    validateCSRFToken();
    enforceUserPermission('module_sales', 2);

    $row = contractPostRow($_POST['contract_id'] ?? 0);
    $contract_id = intval($row['contract_id']);
    if (!contractSignaturesReady()) {
        flashAlert("Signing needs the database update - run it from Maintenance > Update first.", 'error');
        redirect();
    }
    if (in_array($row['contract_status'], ['Ended', 'Cancelled'], true)) {
        flashAlert("An ended or cancelled contract isn't sent for signature - renew it first.", 'error');
        redirect();
    }

    // One of the client's contacts, or a name and an address typed in
    $contact_id = intval($_POST['contact_id'] ?? 0);
    if ($contact_id > 0) {
        $contact = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT contact_name, contact_email FROM contacts WHERE contact_id = $contact_id AND contact_client_id = $client_id AND contact_archived_at IS NULL"));
        $to_name = (string) ($contact['contact_name'] ?? '');
        $to_email = trim((string) ($contact['contact_email'] ?? ''));
        if (!$contact || !filter_var($to_email, FILTER_VALIDATE_EMAIL)) {
            flashAlert("That contact has no email address - pick another, or type a name and an address.", 'error');
            redirect();
        }
    } else {
        $contact_id = 0;
        $to_name = mb_substr(trim(preg_replace('/\s+/u', ' ', strip_tags((string) ($_POST['to_name'] ?? '')))), 0, 200);
        $to_email = trim((string) ($_POST['to_email'] ?? ''));
        if ($to_name === '' || !filter_var($to_email, FILTER_VALIDATE_EMAIL) || strlen($to_email) > 255) {
            flashAlert("Pick a contact, or give a name and an email address.", 'error');
            redirect();
        }
    }
    $note = mb_substr(trim((string) ($_POST['note'] ?? '')), 0, 2000);

    [$round_id, $queued] = contractSignatureSend($row, $to_name, $to_email, $contact_id, $note, $session_user_id);
    logAudit("Contract", "Send", "$session_name sent contract " . escapeSql($row['contract_name']) . " for signature to " . escapeSql("$to_name <$to_email>"), $client_id, $contract_id);
    if ($queued) {
        flashAlert("Contract <strong>" . escapeHtml($row['contract_name']) . "</strong> sent to <strong>" . escapeHtml($to_name) . "</strong> to sign");
    } else {
        flashAlert("Contract <strong>" . escapeHtml($row['contract_name']) . "</strong> is ready to sign, but nothing was emailed - mail isn't set up. Copy its signing link here and send it yourself.", 'warning');
    }

    redirect();

}

if (isset($_GET['resend_contract_signature'])) {

    validateCSRFToken();
    enforceUserPermission('module_sales', 2);

    $round = contractSignaturePostRound($_GET['resend_contract_signature']);
    $state = contractSignatureState($round);
    if (!$state || !in_array($state['key'], ['sent', 'viewed'], true)) {
        flashAlert("That request isn't waiting for the client any more.", 'error');
        redirect();
    }
    $queued = contractSignatureRequestEmail($round);
    logAudit("Contract", "Send", "$session_name sent the signing link for contract " . escapeSql($round['contract_name']) . " again to " . escapeSql($round['contract_signature_sent_to_name'] . ' <' . $round['contract_signature_sent_to_email'] . '>'), $client_id, intval($round['contract_id']));
    flashAlert($queued ? "Signing link sent again to <strong>" . escapeHtml($round['contract_signature_sent_to_name']) . "</strong>" : "Nothing was emailed - mail isn't set up. Copy the signing link and send it yourself.", $queued ? 'success' : 'warning');

    redirect();

}

if (isset($_GET['withdraw_contract_signature'])) {

    validateCSRFToken();
    enforceUserPermission('module_sales', 2);

    $round = contractSignaturePostRound($_GET['withdraw_contract_signature']);
    if (!contractSignaturePending($round) || !contractSignatureWithdraw($round, 'Withdrawn by ' . stripslashes($session_name))) {
        flashAlert("That request isn't waiting on anyone any more.", 'error');
        redirect();
    }
    logAudit("Contract", "Withdraw", "$session_name withdrew the signing request for contract " . escapeSql($round['contract_name']) . " sent to " . escapeSql($round['contract_signature_sent_to_name']), $client_id, intval($round['contract_id']));
    flashAlert("Signing request withdrawn - its link no longer signs anything", 'error');

    redirect();

}

if (isset($_POST['countersign_contract'])) {

    validateCSRFToken();
    enforceUserPermission('module_sales', 2);

    $round = contractSignaturePostRound($_POST['contract_signature_id'] ?? 0);
    $state = contractSignatureState($round);
    if (!$state || $state['key'] !== 'countersign') {
        flashAlert("That contract isn't waiting for a countersignature.", 'error');
        redirect();
    }
    [$f, $error] = contractSignatureFromInput($_POST);
    if ($error !== '') {
        flashAlert(escapeHtml($error) . ' It was not countersigned.', 'error');
        redirect();
    }
    $was_draft = $round['contract_status'] === 'Draft';
    if (!contractSignatureAgentSign($round, $f, $session_user_id)) {
        flashAlert("That contract isn't waiting for a countersignature.", 'error');
        redirect();
    }
    if ($was_draft) {
        contractHoursSync(intval($round['contract_id']));
        contractRecurringFollow(intval($round['contract_id']), stripslashes($session_name));
    }
    logAudit("Contract", "Sign", "$session_name countersigned contract " . escapeSql($round['contract_name']) . " as " . escapeSql($f['name']) . ($was_draft ? ", which is now active" : ''), $client_id, intval($round['contract_id']));
    flashAlert("Contract <strong>" . escapeHtml($round['contract_name']) . "</strong> is signed by both" . ($was_draft ? " and active" : '') . " - a copy went to " . escapeHtml($round['contract_signature_sent_to_name']));

    redirect();

}

if (isset($_GET['export_contract_pdf'])) {

    enforceUserPermission('module_sales');

    $row = contractPostRow($_GET['export_contract_pdf']);
    $round = null;
    if (!empty($_GET['round'])) {
        $round = contractSignatureRow($_GET['round']);
        if (!$round || intval($round['contract_id']) !== intval($row['contract_id'])) {
            flashAlert("That copy of the contract is gone.", 'error');
            redirect();
        }
    }
    billingPdfSend(contractPdf($row, $round), true);

}

/* ---------------------------------------------------------------- prepaid hours */

if (isset($_POST['add_contract_hours'])) {

    validateCSRFToken();
    enforceUserPermission('module_sales', 2);

    $row = contractPostRow($_POST['contract_id'] ?? 0);
    if (!contractHoursReady()) {
        flashAlert("Prepaid hours need the database update - run it from Maintenance > Update first.", 'error');
        redirect();
    }
    if (in_array($row['contract_status'], ['Ended', 'Cancelled'], true)) {
        flashAlert("Hours are added to a draft or active contract - renew it first.", 'error');
        redirect();
    }
    [$f, $error] = contractHoursFromInput($_POST);
    if ($error === '' && $f['invoice'] && intval($config_module_enable_accounting) !== 1) {
        $error = 'Invoicing needs accounting turned on, in Admin > Settings > Modules.';
    }
    if ($error !== '') {
        flashAlert(escapeHtml($error) . ' No hours were added.', 'error');
        redirect();
    }
    [$block_id, $invoice_id, $number] = contractHoursAdd($row, $f, $session_user_id, stripslashes($session_name));
    $hours = contractHoursWords($f['hours']);
    logAudit("Contract", "Edit", "$session_name added $hours to contract " . escapeSql($row['contract_name'])
        . ($f['price'] !== null ? " for " . escapeSql(billingMoney($f['price'], $session_company_currency)) : '')
        . ($f['expires'] !== '' ? ", good until " . escapeSql($f['expires']) : '') . ($invoice_id ? ", on invoice " . escapeSql($number) : ''), $client_id, intval($row['contract_id']));
    flashAlert("<strong>" . escapeHtml($hours) . "</strong> added to <strong>" . escapeHtml($row['contract_name']) . "</strong>"
        . ($invoice_id ? " - draft invoice <strong>" . escapeHtml($number) . "</strong> made for them" : ''));

    redirect();

}

if (isset($_GET['delete_contract_block'])) {

    validateCSRFToken();
    enforceUserPermission('module_sales', 2);

    $block = contractBlockRow($_GET['delete_contract_block']);
    if (!$block) {
        flashAlert("Those hours are gone - they may have been deleted.", 'error');
        redirect();
    }
    $row = contractPostRow($block['contract_id']);
    if (intval($block['uses']) || !contractHoursBlockDelete($block['contract_block_id'])) {
        flashAlert("Time was drawn from those hours, so they stay - put the tickets back first.", 'error');
        redirect();
    }
    $hours = contractHoursWords($block['contract_block_hours']);
    logAudit("Contract", "Edit", "$session_name deleted $hours from contract " . escapeSql($row['contract_name']) . " (" . escapeSql($block['contract_block_note'] ?: 'bought ' . $block['contract_block_bought']) . ")", $client_id, intval($row['contract_id']));
    flashAlert("<strong>" . escapeHtml($hours) . "</strong> deleted from <strong>" . escapeHtml($row['contract_name']) . "</strong>" . (intval($block['contract_block_invoice_id']) ? " - the invoice for them stays" : ''), 'error');

    redirect();

}

if (isset($_POST['draw_contract_hours'])) {

    validateCSRFToken();
    enforceUserPermission('module_sales', 2);

    // From a contract, Unbilled Time, or one ticket
    $ticket_ids = [];
    if (!empty($_POST['ticket_id'])) {
        $client_id = intval(getFieldById('tickets', intval($_POST['ticket_id']), 'ticket_client_id'));
        $ticket_ids = [intval($_POST['ticket_id'])];
    } elseif (!empty($_POST['contract_id'])) {
        $client_id = intval(contractPostRow($_POST['contract_id'])['contract_client_id']);
    } else {
        $client_id = intval($_POST['client_id'] ?? 0);
    }
    if (!$client_id || !contractHoursReady()) {
        flashAlert("Pick a client with prepaid hours.", 'error');
        redirect();
    }
    enforceClientAccess();

    $result = contractHoursDraw($client_id, $ticket_ids, stripslashes($session_name), $session_user_id);
    $count = count($result['drawn']);
    if (!$count) {
        flashAlert($result['contracts'] ? 'Nothing to draw - no done tickets waiting to bill fall within prepaid hours with time left.' : 'Nothing to draw - the client has no active contract with prepaid hours.', 'warning');
        redirect();
    }
    logAudit("Contract", "Edit", "$session_name drew " . escapeSql(contractHoursWords($result['hours'])) . " of prepaid hours for $count ticket" . ($count === 1 ? '' : 's') . ": " . escapeSql(implode(', ', array_keys($result['drawn']))), $client_id);
    $over = array_sum($result['overage']);
    flashAlert("<strong>" . escapeHtml(contractHoursWords($result['hours'])) . "</strong> drawn from prepaid hours for $count ticket" . ($count === 1 ? '' : 's')
        . " - " . escapeHtml(contractHoursText($result['left'])) . " left" . ($over > 0 ? ". <strong>" . escapeHtml(contractHoursText($over)) . "</strong> went over and is left to bill" : ''), $over > 0 ? 'warning' : 'success');

    redirect();

}

if (isset($_GET['put_back_contract_hours'])) {

    validateCSRFToken();
    enforceUserPermission('module_sales', 2);

    $ticket_id = intval($_GET['put_back_contract_hours']);
    $ticket = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT ticket_id, ticket_prefix, ticket_number, ticket_client_id, ticket_invoice_id FROM tickets WHERE ticket_id = $ticket_id"));
    if (!$ticket || !contractHoursReady()) {
        flashAlert("That ticket is gone.", 'error');
        redirect();
    }
    $client_id = intval($ticket['ticket_client_id']);
    enforceClientAccess();
    $hours = contractHoursPutBack($ticket_id, stripslashes($session_name), $session_user_id);
    if ($hours === null) {
        flashAlert(intval($ticket['ticket_invoice_id']) ? "That ticket is on an invoice now, so its hours stay drawn." : "That ticket drew no prepaid hours.", 'error');
        redirect();
    }
    $reference = $ticket['ticket_prefix'] . $ticket['ticket_number'];
    logAudit("Contract", "Edit", "$session_name put " . escapeSql(contractHoursWords($hours)) . " back on the prepaid hours from ticket " . escapeSql($reference), $client_id, 0);
    flashAlert("<strong>" . escapeHtml(contractHoursWords($hours)) . "</strong> put back from <strong>" . escapeHtml($reference) . "</strong> - it is in Unbilled Time to bill as usual");

    redirect();

}

/* ---------------------------------------------------------------- the recurring invoice */

/* The contract posted, when a recurring invoice can be made or linked for it - or a flash and back. */
function contractRecurringPostRow($id) {
    global $config_module_enable_accounting;
    $row = contractPostRow($id);
    if (!contractBillingReady()) {
        flashAlert("Linking a recurring invoice needs the database update - run it from Maintenance > Update first.", 'error');
        redirect();
    }
    if (intval($config_module_enable_accounting) !== 1) {
        flashAlert("Recurring invoices need accounting turned on, in Admin > Settings > Modules.", 'error');
        redirect();
    }
    if (!contractRecurringShouldRun($row) && $row['contract_status'] !== 'Draft') {
        flashAlert("An ended contract isn't billed - renew it first.", 'error');
        redirect();
    }
    if (contractRecurringRow($row)) {
        flashAlert("It already has a recurring invoice - unlink that one first.", 'error');
        redirect();
    }
    return $row;
}

if (isset($_POST['make_contract_recurring'])) {

    validateCSRFToken();
    enforceUserPermission('module_sales', 2);

    $row = contractRecurringPostRow($_POST['contract_id'] ?? 0);
    [$f, $error] = contractRecurringFromInput($_POST);
    if ($error !== '') {
        flashAlert(escapeHtml($error) . ' No recurring invoice was made.', 'error');
        redirect();
    }
    [$recurring_id, $number, $state] = contractRecurringCreate($row, $f, stripslashes($session_name));
    logAudit("Contract", "Edit", "$session_name made recurring invoice " . escapeSql($number) . " for contract " . escapeSql($row['contract_name']), $client_id, intval($row['contract_id']));
    flashAlert("Recurring invoice <strong>" . escapeHtml($number) . "</strong> made for <strong>" . escapeHtml($row['contract_name']) . "</strong>"
        . ($state === 'started' ? ' - the first invoice is on ' . date('M j, Y', strtotime($f['start'])) : " - paused until the contract is active"));

    redirect();

}

if (isset($_POST['link_contract_recurring'])) {

    validateCSRFToken();
    enforceUserPermission('module_sales', 2);

    $row = contractRecurringPostRow($_POST['contract_id'] ?? 0);
    $recurring_id = intval($_POST['recurring_invoice_id'] ?? 0);
    $choices = contractRecurringChoices($client_id, intval($row['contract_id']));
    if (!isset($choices[$recurring_id])) {
        flashAlert("Pick one of the client's recurring invoices that isn't another contract's.", 'error');
        redirect();
    }
    $number = billingNumber('recurring', $choices[$recurring_id]);
    $state = contractRecurringLink($row, $recurring_id, stripslashes($session_name));
    logAudit("Contract", "Edit", "$session_name linked recurring invoice " . escapeSql($number) . " to contract " . escapeSql($row['contract_name']), $client_id, intval($row['contract_id']));
    flashAlert("Recurring invoice <strong>" . escapeHtml($number) . "</strong> linked to <strong>" . escapeHtml($row['contract_name']) . "</strong>"
        . ($state === 'started' ? ' - it is running now' : ($state === 'stopped' ? ' - paused until the contract is active' : '')));

    redirect();

}

if (isset($_GET['unlink_contract_recurring'])) {

    validateCSRFToken();
    enforceUserPermission('module_sales', 2);

    $row = contractPostRow($_GET['unlink_contract_recurring']);
    $recurring = contractRecurringRow($row);
    if (!$recurring) {
        flashAlert("It has no recurring invoice to unlink.", 'error');
        redirect();
    }
    $number = billingNumber('recurring', $recurring);
    contractRecurringUnlink($row, stripslashes($session_name));
    logAudit("Contract", "Edit", "$session_name unlinked recurring invoice " . escapeSql($number) . " from contract " . escapeSql($row['contract_name']), $client_id, intval($row['contract_id']));
    flashAlert("Recurring invoice <strong>" . escapeHtml($number) . "</strong> unlinked - it carries on as it is, " . (intval($recurring['recurring_invoice_status']) ? 'running' : 'paused'));

    redirect();

}
