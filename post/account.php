<?php

/*
 * ITFlow - GET/POST request handler for account(s) (accounting related)
 */

if (isset($_POST['add_account'])) {
    // Check if the user is an accountant
    validateAccountantRole();

    // Sanitize the input
    $name = sanitizeInput($_POST['name']);
    $opening_balance = floatval($_POST['opening_balance']);
    $currency_code = sanitizeInput($_POST['currency_code']);
    $notes = sanitizeInput($_POST['notes']);
    $type = intval($_POST['type']);

    // Create the account
    createAccount($name, $opening_balance, $currency_code, $notes, $type);

    // Redirect to the accounts page with a success message
    referWithAlert("Account created", "success", "accounts.php");
}

if (isset($_POST['edit_account'])) {
    // Check if the user is an accountant
    validateAccountantRole();

    // Sanitize the input
    $account_id = intval($_POST['account_id']);
    $name = sanitizeInput($_POST['name']);
    $type = intval($_POST['type']);
    $notes = sanitizeInput($_POST['notes']);

    // Edit the account
    editAccount($account_id, $name, $type, $notes);

    // Redirect to the referring page with a success message
    referWithAlert("Account edited", "success");
}

if (isset($_GET['archive_account'])) {
    // Check if the user is an accountant
    validateAccountantRole();

    // Sanitize the input
    $account_id = intval($_GET['archive_account']);

    // Archive the account
    archiveAccount($account_id);

    // Redirect to the accounts page with a success message
    referWithAlert("Account archived", "success", "accounts.php");
}

if (isset($_GET['delete_account'])) {
    // Check if the user is an accountant
    validateAccountantRole();

    // Sanitize the input
    $account_id = intval($_GET['delete_account']);

    // Delete the account
    deleteAccount($account_id);

    // Redirect to the accounts page with a success message
    referWithAlert("Account deleted", "success", "accounts.php");
}

