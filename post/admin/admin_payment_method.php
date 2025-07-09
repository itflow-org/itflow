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

    // Logging
    logAction("Payment Method", "Create", "$session_name created Payment Method $name");

    $_SESSION['alert_message'] = "Payment Method <strong>$name</strong> created";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_POST['edit_payment_method'])) {

    validateCSRFToken($_POST['csrf_token']);

    $payment_method_id = intval($_POST['payment_method_id']);
    $name = sanitizeInput($_POST['name']);
    $description = sanitizeInput($_POST['description']);

    mysqli_query($mysqli,"UPDATE payment_methods SET payment_method_name = '$name', payment_method_description = '$description' WHERE payment_method_id = $payment_method_id");

    // Logging
    logAction("Payment Method", "Edit", "$session_name edited Payment Method $name");

    $_SESSION['alert_message'] = "Payment Method <strong>$name</strong> edited";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_GET['delete_payment_method'])) {
    
    $payment_method_id = intval($_GET['delete_payment_method']);

    $sql = mysqli_query($mysqli,"SELECT payment_method_name FROM payment_methods WHERE payment_method_id = $payment_method_id");
    $row = mysqli_fetch_array($sql);
    $payment_method_name = sanitizeInput($row['payment_method_name']);

    mysqli_query($mysqli,"DELETE FROM payment_methods WHERE payment_method_id = $payment_method_id");

    // Logging
    logAction("Payment Method", "Delete", "$session_name deleted Payment Method $payment_method_name");

    $_SESSION['alert_type'] = "error";
    $_SESSION['alert_message'] = "Payment Method <strong>$payment_method_name</strong> deleted";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}
