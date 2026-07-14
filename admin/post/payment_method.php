<?php

/*
 * ITFlow - GET/POST request handler for AI Providers ('ai_providers')
 */

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

if (isset($_POST['add_payment_method'])) {

    validateCSRFToken($_POST['csrf_token']);

    $name = cleanInput($_POST['name']);
    $description = cleanInput($_POST['description']);

    $query = mysqli_prepare(
        $mysqli, "INSERT INTO payment_methods
        SET payment_method_name = ?, payment_method_description = ?"
    );

    mysqli_stmt_bind_param($query, "ss", $name, $description);

    mysqli_stmt_execute($query);

    logAudit("Payment Method", "Create", "$session_name created Payment Method $name");

    flash_alert("Payment Method <strong>$name</strong> created");

    redirect();

}

if (isset($_POST['edit_payment_method'])) {

    validateCSRFToken($_POST['csrf_token']);

    $payment_method_id = intval($_POST['payment_method_id']);
    $name = cleanInput($_POST['name']);
    $description = cleanInput($_POST['description']);

    $query = mysqli_prepare(
        $mysqli,
        "UPDATE payment_methods
         SET payment_method_name = ?, payment_method_description = ?
         WHERE payment_method_id = ?"
    );

    mysqli_stmt_bind_param($query, "ssi", $name, $description, $payment_method_id);

    mysqli_stmt_execute($query);

    logAudit("Payment Method", "Edit", "$session_name edited Payment Method $name");

    flash_alert("Payment Method <strong>$name</strong> edited");

    redirect();

}

if (isset($_GET['delete_payment_method'])) {

    validateCSRFToken($_GET['csrf_token']);

    $payment_method_id = intval($_GET['delete_payment_method']);

    $payment_method_name = escapeSql(getFieldById('payment_methods', $payment_method_is, 'payment_method_name'));

    mysqli_query($mysqli,"DELETE FROM payment_methods WHERE payment_method_id = $payment_method_id");

    logAudit("Payment Method", "Delete", "$session_name deleted Payment Method $payment_method_name");

    flash_alert("Payment Method <strong>$payment_method_name</strong> deleted", 'error');

    redirect();

}
