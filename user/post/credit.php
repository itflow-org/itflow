<?php

/*
 * ITFlow - GET/POST request handler for credits
 */

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

if (isset($_POST['add_credit'])) {

    validateCSRFToken($_POST['csrf_token']);
    enforceUserPermission('module_sales', 2);

    $client_id = intval($_POST['client']);
    $amount = floatval($_POST['amount']);
    $expire = sanitizeInput($_POST['expire']);
    $reference = sanitizeInput($_POST['reference']);

    mysqli_query($mysqli,"INSERT INTO credits SET credit_amount = $amount, credit_reference = '$reference', credit_created_by = $session_user_id, credit_client_id = $client_id");
    
    $credit_id = mysqli_insert_id($mysqli);

    // Logging
    logAction("Credit", "Create", "$session_name added " . numfmt_format_currency($currency_format, $amount, $session_company_currency) . "", $client_id, $credit_id);

    $_SESSION['alert_message'] = "" . numfmt_format_currency($currency_format, $amount, $session_company_currency) . " Credit Added ";

    redirect();
}
