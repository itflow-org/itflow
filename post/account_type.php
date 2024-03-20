<?php

/*
 * ITFlow - GET/POST request handler for account(s) (accounting related)
 */

if (isset($_POST['add_account_type'])) {
    // Check if the user is an accountant
    validateAccountantRole();

    // Sanitize the input
    $name = sanitizeInput($_POST['name']);
    $type = intval($_POST['type']);
    $description = sanitizeInput($_POST['description']);

    // Create the account type
    createAccountType($name, $type, $description);

    // Redirect to the referring page with a success message
    referWithAlert("Account added", "success");
}

if (isset($_POST['edit_account_type'])) {
    // Check if the user is an accountant
    validateAccountantRole();

    // Sanitize the input
    $account_type_id = intval($_POST['account_type_id']);
    $name = sanitizeInput($_POST['name']);
    $type = intval($_POST['type']);
    $description = sanitizeInput($_POST['description']);

    // Edit the account type
    editAccountType($account_type_id, $name, $type, $description);

    // Redirect to the referring page with a success message
    referWithAlert("Account edited", "success");
}

if (isset($_GET['archive_account_type'])) {
    // Check if the user is an accountant
    validateAccountantRole();

    // Sanitize the input
    $account_type_id = intval($_GET['archive_account_type']);

    // Archive the account type
    archiveAccountType($account_type_id);

    // Redirect to the referring page with a success message
    referWithAlert("Account Archived", "success");
}

if (isset($_GET['unarchive_account_type'])) {
    // Check if the user is an accountant
    validateAccountantRole();

    // Sanitize the input
    $account_type_id = intval($_GET['unarchive_account_type']);

    // Unarchive the account type
    unarchiveAccountType($account_type_id);

    // Redirect to the referring page with a success message
    referWithAlert("Account Unarchived", "success");
}