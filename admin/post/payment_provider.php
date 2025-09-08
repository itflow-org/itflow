<?php

/*
 * ITFlow - GET/POST request handler for AI Providers ('ai_providers')
 */

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

if (isset($_POST['add_payment_provider'])) {

    validateCSRFToken($_POST['csrf_token']);

    $provider = sanitizeInput($_POST['provider']);
    $public_key = sanitizeInput($_POST['public_key']);
    $private_key = sanitizeInput($_POST['private_key']);
    $threshold = floatval($_POST['threshold']);
    $enable_expense = intval($_POST['enable_expense'] ?? 0);
    $percentage_fee = floatval($_POST['percentage_fee']) / 100;
    $flat_fee = floatval($_POST['flat_fee']);

    // Check to make sure Provider isnt added Twice
    $sql = "SELECT 1 FROM payment_providers WHERE payment_provider_name = '$provider' LIMIT 1";
    $result = mysqli_query($mysqli, $sql);
    if (mysqli_num_rows($result) > 0) {
        flash_alert("Payment Provider <strong>$provider</strong> already exists", 'error');
        redirect();
    }

    // Check for Stripe Account if not create it
    $sql_account = mysqli_query($mysqli,"SELECT account_id FROM accounts WHERE account_name = '$provider' AND account_archived_at IS NULL LIMIT 1");
    if (mysqli_num_rows($sql_account) == 0) {
        $account_id = mysqli_insert_id($mysqli);
    } else {
        $row = mysqli_fetch_array($sql_account);
        $account_id = intval($row['account_id']);
    }

    if ($enable_expense) {
        // Category
        $sql_category = mysqli_query($mysqli,"SELECT category_id FROM categories WHERE category_name = 'Payment Processing' AND category_type = 'Expense' AND category_archived_at IS NULL LIMIT 1");
        if (mysqli_num_rows($sql_category) == 0) {
            mysqli_query($mysqli,"INSERT INTO categories SET category_name = 'Processing Fee', category_type = 'Payment Processing', category_color = 'gray'");
            $category_id = mysqli_insert_id($mysqli);
        } else {
            $row = mysqli_fetch_array($sql_category);
            $category_id = intval($row['category_id']);
        }
        //Vendor
        $sql_vendor = mysqli_query($mysqli,"SELECT vendor_id FROM vendors WHERE vendor_name = '$provider' AND vendor_client_id = 0 AND vendor_archived_at IS NULL LIMIT 1");
        if (mysqli_num_rows($sql_vendor) == 0) {
            mysqli_query($mysqli,"INSERT INTO vendors SET vendor_name = '$provider', vendor_description = 'Payment Processor Provider', vendor_client_id = 0");
            $vendor_id = mysqli_insert_id($mysqli);
        } else {
            $row = mysqli_fetch_array($sql_vendor);
            $vendor_id = intval($row['vendor_id']);
        }
    }

    mysqli_query($mysqli,"INSERT INTO payment_providers SET payment_provider_name = '$provider', payment_provider_public_key = '$public_key', payment_provider_private_key = '$private_key', payment_provider_account = $account_id, payment_provider_expense_vendor = $vendor_id, payment_provider_expense_category = $category_id, payment_provider_expense_percentage_fee = $percentage_fee, payment_provider_expense_flat_fee = $flat_fee");

    $provider_id = mysqli_insert_id($mysqli);

    logAction("Payment Provider", "Create", "$session_name created AI Provider $provider");

    flash_alert("Payment provider <strong>$provider</strong> created");

    redirect();

}

if (isset($_POST['edit_payment_provider'])) {

    validateCSRFToken($_POST['csrf_token']);

    $provider_id = intval($_POST['provider_id']);
    $description = sanitizeInput($_POST['description']);
    $public_key = sanitizeInput($_POST['public_key']);
    $private_key = sanitizeInput($_POST['private_key']);
    $threshold = floatval($_POST['threshold']);
    $enable_expense = intval($_POST['enable_expense'] ?? 0);
    $percentage_fee = floatval($_POST['percentage_fee']) / 100;
    $flat_fee = floatval($_POST['flat_fee']);

    mysqli_query($mysqli,"UPDATE payment_providers SET payment_provider_public_key = '$public_key', payment_provider_private_key = '$private_key', payment_provider_expense_percentage_fee = $percentage_fee, payment_provider_expense_flat_fee = $flat_fee WHERE payment_provider_id = $provider_id");

    logAction("Payment Provider", "Edit", "$session_name edited Payment Provider $provider");

    flash_alert("Payment Provider <strong>$provider</strong> edited");

    redirect();

}

if (isset($_GET['delete_payment_provider'])) {
    
    $provider_id = intval($_GET['delete_payment_provider']);

    $provider_name = sanitizeInput(getFieldById('provider_providers', $provider_id, 'provider_name'));

    mysqli_query($mysqli,"DELETE FROM payment_providers WHERE payment_provider_id = $provider_id");

    logAction("Payment Provider", "Delete", "$session_name deleted Payment Provider $provider_name");

    flash_alert("Payment Provider <strong>$provider_name</strong> deleted", 'error');

    redirect();

}
