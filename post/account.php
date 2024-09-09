<?php

/*
 * ITFlow - GET/POST request handler for account(s) (accounting related)
 */

if (isset($_POST['add_account'])) {
    validateCSRFToken($_POST['csrf_token']);

    $name = sanitizeInput($_POST['name']);
    $opening_balance = floatval($_POST['opening_balance']);
    $currency_code = sanitizeInput($_POST['currency_code']);
    $notes = sanitizeInput($_POST['notes']);
    $type = intval($_POST['type']);

    mysqli_query($mysqli,"INSERT INTO accounts SET account_name = '$name', opening_balance = $opening_balance, account_currency_code = '$currency_code', account_type ='$type', account_notes = '$notes'");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Account', log_action = 'Create', log_description = '$name', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id");

    $_SESSION['alert_message'] = "Account added";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_POST['edit_account'])) {
    validateCSRFToken($_POST['csrf_token']);

    $account_id = intval($_POST['account_id']);
    $name = sanitizeInput($_POST['name']);
    $type = intval($_POST['type']);
    $notes = sanitizeInput($_POST['notes']);

    mysqli_query($mysqli,"UPDATE accounts SET account_name = '$name',account_type = '$type', account_notes = '$notes' WHERE account_id = $account_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Account', log_action = 'Modify', log_description = '$name', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id");

    $_SESSION['alert_message'] = "Account modified";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_GET['archive_account'])) {
    validateCSRFToken($_GET['csrf_token']);
    $account_id = intval($_GET['archive_account']);

    mysqli_query($mysqli,"UPDATE accounts SET account_archived_at = NOW() WHERE account_id = $account_id");

    //logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Account', log_action = 'Archive', log_description = '$account_id', log_ip = '$session_ip', log_user_agent = '$session_user_agent'");

    $_SESSION['alert_message'] = "Account Archived";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

// Not used anywhere?
if (isset($_GET['delete_account'])) {
    $account_id = intval($_GET['delete_account']);

    mysqli_query($mysqli,"DELETE FROM accounts WHERE account_id = $account_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Account', log_action = 'Delete', log_description = '$account_id', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id");

    $_SESSION['alert_message'] = "Account deleted";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

