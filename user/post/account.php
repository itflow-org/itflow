<?php

/*
 * ITFlow - GET/POST request handler for account(s) (accounting related)
 */

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

if (isset($_POST['add_account'])) {
    enforceUserPermission('module_financial', 2);
    validateCSRFToken($_POST['csrf_token']);

    $name = sanitizeInput($_POST['name']);
    $opening_balance = floatval($_POST['opening_balance']);
    $currency_code = sanitizeInput($_POST['currency_code']);
    $notes = sanitizeInput($_POST['notes']);

    mysqli_query($mysqli,"INSERT INTO accounts SET account_name = '$name', opening_balance = $opening_balance, account_currency_code = '$currency_code', account_notes = '$notes'");

    // Logging
    logAction("Account", "Create", "$session_name created account $name");

    $_SESSION['alert_message'] = "Account <strong>$name</strong> created ";

    redirect();

}

if (isset($_POST['edit_account'])) {
    enforceUserPermission('module_financial', 2);
    validateCSRFToken($_POST['csrf_token']);

    $account_id = intval($_POST['account_id']);
    $name = sanitizeInput($_POST['name']);
    $notes = sanitizeInput($_POST['notes']);

    mysqli_query($mysqli,"UPDATE accounts SET account_name = '$name', account_notes = '$notes' WHERE account_id = $account_id");

    // Logging
    logAction("Account", "Edit", "$session_name edited account $name");

    $_SESSION['alert_message'] = "Account <strong>$name</strong> edited";

    redirect();

}

if (isset($_GET['archive_account'])) {
    enforceUserPermission('module_financial', 2);

    validateCSRFToken($_GET['csrf_token']);
    $account_id = intval($_GET['archive_account']);

    // Get Account Name for logging
    $sql = mysqli_query($mysqli,"SELECT account_name FROM accounts WHERE account_id = $account_id");
    $row = mysqli_fetch_array($sql);
    $account_name = sanitizeInput($row['account_name']);

    mysqli_query($mysqli,"UPDATE accounts SET account_archived_at = NOW() WHERE account_id = $account_id");

    // Logging
    logAction("Account", "Archive", "$session_name archived account $account_name");

    $_SESSION['alert_message'] = "Account <strong>$account_name</strong> archived";

    redirect();

}

// Not used anywhere?
if (isset($_GET['delete_account'])) {
    enforceUserPermission('module_financial', 3);

    $account_id = intval($_GET['delete_account']);

    // Get Account Name for logging
    $sql = mysqli_query($mysqli,"SELECT account_name FROM accounts WHERE account_id = $account_id");
    $row = mysqli_fetch_array($sql);
    $account_name = sanitizeInput($row['account_name']);

    mysqli_query($mysqli,"DELETE FROM accounts WHERE account_id = $account_id");

    //Logging
    logAction("Account", "Delete", "$session_name deleted account $account_name");

    $_SESSION['alert_message'] = "Account <strong>$account_name</strong> deleted";

    redirect();

}
