<?php

/*
 * ITFlow - accounts (functions/accounts.php does the work). New and Edit post
 * add_account and edit_account through accountSave(), which checks every
 * field; Archive, Restore and Delete keep an account with money or history
 * on it. Write access to Financial changes accounts, full access deletes.
 */

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

/** The account form's fields as accountSave() takes them. */
function accountPostInput() {
    return [
        'name' => (string) ($_POST['name'] ?? ''),
        'type' => intval($_POST['type'] ?? 0),
        'currency' => (string) ($_POST['currency_code'] ?? ''),
        'opening_balance' => (string) ($_POST['opening_balance'] ?? ''),
        'description' => (string) ($_POST['description'] ?? ''),
        'notes' => (string) ($_POST['notes'] ?? ''),
    ];
}

if (isset($_POST['add_account'])) {

    validateCSRFToken();

    enforceUserPermission('module_financial', 2);

    $account_id = accountSave(accountPostInput(), 0, $error);
    if (!$account_id) {
        flashAlert(escapeHtml($error), 'error');
        redirect();
    }
    $row = accountRow($account_id);

    logAudit("Account", "Create", "$session_name created account " . escapeSql($row['account_name']), 0, $account_id);

    flashAlert("Account <strong>" . escapeHtml($row['account_name']) . "</strong> created");

    redirect();

}

if (isset($_POST['edit_account'])) {

    validateCSRFToken();

    enforceUserPermission('module_financial', 2);

    $account_id = intval($_POST['account_id'] ?? 0);
    $before = accountRow($account_id);
    if (!accountSave(accountPostInput(), $account_id, $error)) {
        flashAlert(escapeHtml($error), 'error');
        redirect();
    }
    $row = accountRow($account_id);
    $changed = $before && abs(floatval($before['opening_balance']) - floatval($row['opening_balance'])) >= 0.005
        ? ', opening balance ' . billingMoney($before['opening_balance'], $row['account_currency_code']) . ' to ' . billingMoney($row['opening_balance'], $row['account_currency_code']) : '';

    logAudit("Account", "Edit", "$session_name edited account " . escapeSql($row['account_name'] . $changed), 0, $account_id);

    flashAlert("Account <strong>" . escapeHtml($row['account_name']) . "</strong> saved");

    redirect();

}

if (isset($_GET['archive_account'])) {

    validateCSRFToken();

    enforceUserPermission('module_financial', 2);

    $row = accountArchive(intval($_GET['archive_account']), $error);
    if (!$row) {
        flashAlert(escapeHtml($error), 'error');
        redirect();
    }

    logAudit("Account", "Archive", "$session_name archived account " . escapeSql($row['account_name']), 0, intval($row['account_id']));

    flashAlert("Account <strong>" . escapeHtml($row['account_name']) . "</strong> archived", 'error');

    redirect();

}

if (isset($_GET['restore_account'])) {

    validateCSRFToken();

    enforceUserPermission('module_financial', 2);

    $row = accountRestore(intval($_GET['restore_account']), $error);
    if (!$row) {
        flashAlert(escapeHtml($error), 'error');
        redirect();
    }

    logAudit("Account", "Restore", "$session_name restored account " . escapeSql($row['account_name']), 0, intval($row['account_id']));

    flashAlert("Account <strong>" . escapeHtml($row['account_name']) . "</strong> restored");

    redirect();

}

if (isset($_GET['delete_account'])) {

    validateCSRFToken();

    enforceUserPermission('module_financial', 3);

    $row = accountDelete(intval($_GET['delete_account']), $error);
    if (!$row) {
        flashAlert(escapeHtml($error), 'error');
        redirect();
    }

    logAudit("Account", "Delete", "$session_name deleted account " . escapeSql($row['account_name']));

    flashAlert("Account <strong>" . escapeHtml($row['account_name']) . "</strong> deleted", 'error');

    redirect('accounts.php');

}
