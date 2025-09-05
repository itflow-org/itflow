<?php

/*
 * ITFlow - GET/POST request handler for AI Providers ('ai_providers')
 */

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

if (isset($_POST['add_payment_method'])) {

    validateCSRFToken($_POST['csrf_token']);

    $name = sanitizeInput($_POST['name']);
    $description = sanitizeInput($_POST['description']);

    mysqli_query($mysqli,"INSERT INTO payment_methods SET payment_method_name = '$name', payment_method_description = '$description'");

    logAction("Payment Method", "Create", "$session_name created Payment Method $name");

    flash_alert("Payment Method <strong>$name</strong> created");

    redirect();

}

if (isset($_POST['edit_payment_method'])) {

    validateCSRFToken($_POST['csrf_token']);

    $payment_method_id = intval($_POST['payment_method_id']);
    $name = sanitizeInput($_POST['name']);
    $description = sanitizeInput($_POST['description']);

    mysqli_query($mysqli,"UPDATE payment_methods SET payment_method_name = '$name', payment_method_description = '$description' WHERE payment_method_id = $payment_method_id");

    logAction("Payment Method", "Edit", "$session_name edited Payment Method $name");

    flash_alert("Payment Method <strong>$name</strong> edited");

    redirect();

}

if (isset($_GET['delete_payment_method'])) {
    
    $payment_method_id = intval($_GET['delete_payment_method']);

    $payment_method_name = sanitizeInput(getFieldById('payment_methods', $payment_method_is, 'payment_method_name'));

    mysqli_query($mysqli,"DELETE FROM payment_methods WHERE payment_method_id = $payment_method_id");

    logAction("Payment Method", "Delete", "$session_name deleted Payment Method $payment_method_name");

    flash_alert("Payment Method <strong>$payment_method_name</strong> deleted", 'error');

    redirect();

}
