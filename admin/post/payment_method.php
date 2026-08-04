<?php

/*
 * ITFlow - GET/POST request handler for payment methods ('payment_methods')
 */

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

if (isset($_POST['add_payment_method'])) {

    validateCSRFToken();

    $name = escapeSql($_POST['name']);
    $description = escapeSql($_POST['description']);

    mysqli_query($mysqli, "INSERT INTO payment_methods SET payment_method_name = '$name', payment_method_description = '$description'");

    logAudit("Payment Method", "Create", "$session_name created Payment Method $name");

    flashAlert("Payment Method <strong>$name</strong> created");

    redirect();

}

if (isset($_POST['edit_payment_method'])) {

    validateCSRFToken();

    $payment_method_id = intval($_POST['payment_method_id']);
    $name = escapeSql($_POST['name']);
    $description = escapeSql($_POST['description']);

    mysqli_query($mysqli, "UPDATE payment_methods SET payment_method_name = '$name', payment_method_description = '$description' WHERE payment_method_id = $payment_method_id");

    logAudit("Payment Method", "Edit", "$session_name edited Payment Method $name");

    flashAlert("Payment Method <strong>$name</strong> edited");

    redirect();

}

if (isset($_GET['delete_payment_method'])) {

    validateCSRFToken();

    $payment_method_id = intval($_GET['delete_payment_method']);

    $payment_method_name = escapeSql(getFieldById('payment_methods', $payment_method_id, 'payment_method_name'));

    mysqli_query($mysqli,"DELETE FROM payment_methods WHERE payment_method_id = $payment_method_id");

    logAudit("Payment Method", "Delete", "$session_name deleted Payment Method $payment_method_name");

    flashAlert("Payment Method <strong>$payment_method_name</strong> deleted", 'error');

    redirect();

}
