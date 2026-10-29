<?php

/*
 * ITFlow - transfers between our accounts (functions/accounts.php does the
 * work). New and Edit post add_transfer and edit_transfer through
 * transferSave(), which checks there are two different accounts, an amount
 * and a date. Write access to Financial moves money, full access deletes a
 * transfer.
 */

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

/** The transfer form's fields as transferSave() takes them. */
function transferPostInput() {
    return [
        'date' => (string) ($_POST['date'] ?? ''),
        'amount' => (string) ($_POST['amount'] ?? ''),
        'from' => intval($_POST['account_from'] ?? 0),
        'to' => intval($_POST['account_to'] ?? 0),
        'method' => (string) ($_POST['transfer_method'] ?? ''),
        'notes' => (string) ($_POST['notes'] ?? ''),
        'deposit' => array_map('strval', (array) ($_POST['deposit'] ?? [])),
    ];
}

if (isset($_POST['add_transfer'])) {

    validateCSRFToken();

    enforceUserPermission('module_financial', 2);

    $transfer_id = transferSave(transferPostInput(), 0, $error);
    if (!$transfer_id) {
        flashAlert(escapeHtml($error), 'error');
        redirect();
    }
    $row = transferRow($transfer_id);

    logAudit("Account Transfer", "Create", "$session_name transferred " . escapeSql(transferLabel($row)), 0, $transfer_id);

    flashAlert("Moved <strong>" . escapeHtml(transferLabel($row)) . "</strong>");

    redirect();

}

if (isset($_POST['edit_transfer'])) {

    validateCSRFToken();

    enforceUserPermission('module_financial', 2);

    $transfer_id = intval($_POST['transfer_id'] ?? 0);
    if (!transferSave(transferPostInput(), $transfer_id, $error)) {
        flashAlert(escapeHtml($error), 'error');
        redirect();
    }
    $row = transferRow($transfer_id);

    logAudit("Account Transfer", "Edit", "$session_name edited the transfer " . escapeSql(transferLabel($row)), 0, $transfer_id);

    flashAlert("Transfer <strong>" . escapeHtml(transferLabel($row)) . "</strong> saved");

    redirect();

}

if (isset($_GET['delete_transfer'])) {

    validateCSRFToken();

    enforceUserPermission('module_financial', 3);

    $row = transferDelete(intval($_GET['delete_transfer']), $error);
    if (!$row) {
        flashAlert(escapeHtml($error), 'error');
        redirect();
    }

    logAudit("Account Transfer", "Delete", "$session_name deleted the transfer " . escapeSql(transferLabel($row)));

    flashAlert("Transfer <strong>" . escapeHtml(transferLabel($row)) . "</strong> deleted", 'error');

    redirect();

}
