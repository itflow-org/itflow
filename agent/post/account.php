<?php

/*
 * ITFlow - GET/POST request handler for account(s) (accounting related)
 */

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

if (isset($_POST['add_account'])) {

    validateCSRFToken($_POST['csrf_token']);

    enforceUserPermission('module_financial', 2);

    $name = escapeSql($_POST['name']);
    $opening_balance = floatval($_POST['opening_balance']);
    $currency_code = escapeSql($_POST['currency_code']);
    $notes = escapeSql($_POST['notes']);

    mysqli_query($mysqli,"INSERT INTO accounts SET account_name = '$name', opening_balance = $opening_balance, account_currency_code = '$currency_code', account_notes = '$notes'");

    logAction("Account", "Create", "$session_name created account $name");

    flash_alert("Account <strong>$name</strong> created");

    redirect();

}

if (isset($_POST['edit_account'])) {

    validateCSRFToken($_POST['csrf_token']);

    enforceUserPermission('module_financial', 2);

    $account_id = intval($_POST['account_id']);
    $name = escapeSql($_POST['name']);
    $notes = escapeSql($_POST['notes']);

    mysqli_query($mysqli,"UPDATE accounts SET account_name = '$name', account_notes = '$notes' WHERE account_id = $account_id");

    logAction("Account", "Edit", "$session_name edited account $name");

    flash_alert("Account <strong>$name</strong> edited");

    redirect();

}

if (isset($_GET['archive_account'])) {

    validateCSRFToken($_GET['csrf_token']);

    enforceUserPermission('module_financial', 2);

    $account_id = intval($_GET['archive_account']);

    $account_name = escapeSql(getFieldById('accounts', $account_id, 'account_name'));

    mysqli_query($mysqli,"UPDATE accounts SET account_archived_at = NOW() WHERE account_id = $account_id");

    logAction("Account", "Archive", "$session_name archived account $account_name");

    flash_alert("Account <strong>$account_name</strong> archived", 'error');

    redirect();

}

// Not used anywhere?
if (isset($_GET['delete_account'])) {

    validateCSRFToken($_GET['csrf_token']);

    enforceUserPermission('module_financial', 3);

    $account_id = intval($_GET['delete_account']);

    $account_name = escapeSql(getFieldById('accounts', $account_id, 'account_name'));

    mysqli_query($mysqli,"DELETE FROM accounts WHERE account_id = $account_id");

    logAction("Account", "Delete", "$session_name deleted account $account_name");

    flash_alert("Account <strong>$account_name</strong> deleted", 'error');

    redirect();

}
