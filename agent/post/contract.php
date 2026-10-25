<?php

/*
 * ITFlow - contracts: add, change, copy (through add), make a draft active,
 * renew, cancel and delete. Needs Sales - to change, or to delete. The work
 * is in functions/contract.php.
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
        if ($changes) {
            logAudit("Contract", "Edit", "$session_name changed contract " . escapeSql($row['contract_name']) . ": " . escapeSql(implode('; ', $changes)), $client_id, $contract_id);
        }
        flashAlert("Contract <strong>" . escapeHtml($fields['name']) . "</strong> saved");
    } else {
        $contract_id = contractCreate($fields);
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
    logAudit("Contract", "Edit", "$session_name made contract " . escapeSql($row['contract_name']) . " active from " . escapeSql($start) . ($end !== '' ? " to " . escapeSql($end) : ''), $client_id, intval($row['contract_id']));
    flashAlert("Contract <strong>" . escapeHtml($row['contract_name']) . "</strong> is active");

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
    logAudit("Contract", "Edit", "$session_name renewed contract " . escapeSql($row['contract_name']) . " to " . escapeSql($end), $client_id, intval($row['contract_id']));
    flashAlert("Contract <strong>" . escapeHtml($row['contract_name']) . "</strong> renewed to " . date('M j, Y', strtotime($end)));

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
    logAudit("Contract", "Edit", "$session_name cancelled contract " . escapeSql($row['contract_name']) . ", last day " . escapeSql($last_day) . ($reason !== '' ? " - " . escapeSql($reason) : ''), $client_id, intval($row['contract_id']));
    flashAlert("Contract <strong>" . escapeHtml($row['contract_name']) . "</strong> cancelled" . ($last_day >= date('Y-m-d') ? " - in force until " . date('M j, Y', strtotime($last_day)) : ''), 'error');

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
