<?php

/*
 * ITFlow - GET/POST request handler for AI Providers ('ai_providers')
 */

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

if (isset($_POST['add_payment_provider'])) {

    validateCSRFToken($_POST['csrf_token']);

    $provider = escapeSql($_POST['provider']);
    $public_key = escapeSql($_POST['public_key']);
    $private_key = escapeSql($_POST['private_key']);
    $threshold = floatval($_POST['threshold']);
    $account = intval($_POST['account']);
    $expense_vendor = intval($_POST['expense_vendor']) ?? 0;
    $expense_category = intval($_POST['expense_category']) ?? 0;
    $percentage_fee = floatval($_POST['percentage_fee']) / 100 ?? 0;
    $flat_fee = floatval($_POST['flat_fee']) ?? 0;

    // Check to ensure provider isn't added twice
    $sql = mysqli_query($mysqli, "SELECT 1 FROM payment_providers WHERE payment_provider_name = '$provider' LIMIT 1");
    if (mysqli_num_rows($sql) > 0) {
        flashAlert("Payment Provider <strong>$provider</strong> already exists", 'error');
        redirect();
    }

    mysqli_query($mysqli,"INSERT INTO payment_providers SET payment_provider_name = '$provider', payment_provider_public_key = '$public_key', payment_provider_private_key = '$private_key', payment_provider_threshold = $threshold, payment_provider_account = $account, payment_provider_expense_vendor = $expense_vendor, payment_provider_expense_category = $expense_category, payment_provider_expense_percentage_fee = $percentage_fee, payment_provider_expense_flat_fee = $flat_fee");

    $provider_id = mysqli_insert_id($mysqli);

    logAudit("Payment Provider", "Create", "$session_name created AI Provider $provider");

    flashAlert("Payment provider <strong>$provider</strong> created");

    redirect();

}

if (isset($_POST['edit_payment_provider'])) {

    validateCSRFToken($_POST['csrf_token']);

    $provider_id = intval($_POST['provider_id']);
    $description = escapeSql($_POST['description']);
    $public_key = escapeSql($_POST['public_key']);
    $private_key = escapeSql($_POST['private_key']);
    $threshold = floatval($_POST['threshold']);
    $account = intval($_POST['account']);
    $expense_vendor = intval($_POST['expense_vendor']) ?? 0;
    $expense_category = intval($_POST['expense_category']) ?? 0;
    $percentage_fee = floatval($_POST['percentage_fee']) / 100;
    $flat_fee = floatval($_POST['flat_fee']);

    mysqli_query($mysqli,"UPDATE payment_providers SET payment_provider_public_key = '$public_key', payment_provider_private_key = '$private_key', payment_provider_threshold = $threshold, payment_provider_account = $account, payment_provider_expense_vendor = $expense_vendor, payment_provider_expense_category = $expense_category, payment_provider_expense_percentage_fee = $percentage_fee, payment_provider_expense_flat_fee = $flat_fee WHERE payment_provider_id = $provider_id");

    logAudit("Payment Provider", "Edit", "$session_name edited Payment Provider $provider");

    flashAlert("Payment Provider <strong>$provider</strong> edited");

    redirect();

}

if (isset($_GET['delete_payment_provider'])) {

    validateCSRFToken($_GET['csrf_token']);

    $provider_id = intval($_GET['delete_payment_provider']);

    // When deleted it cascades deletes
    // all Recurring paymentes related to payment provider
    // Delete all Saved Cards related
    // Delete Client Payment Provider Releation

    $provider_name = escapeSql(getFieldById('payment_providers', $provider_id, 'provider_name'));

    // Delete provider
    mysqli_query($mysqli,"DELETE FROM payment_providers WHERE payment_provider_id = $provider_id");

    logAudit("Payment Provider", "Delete", "$session_name deleted Payment Provider $provider_name");

    flashAlert("Payment Provider <strong>$provider_name</strong> deleted", 'error');

    redirect();

}
