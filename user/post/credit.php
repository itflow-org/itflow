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
    $type = sanitizeInput($_POST['type']);
    $expire = sanitizeInput($_POST['expire']);
    $note = sanitizeInput($_POST['note']);

    mysqli_query($mysqli,"INSERT INTO credits SET credit_amount = $amount, credit_type = '$type', credit_note = '$note', credit_created_by = $session_user_id, credit_client_id = $client_id");
    
    $credit_id = mysqli_insert_id($mysqli);

    logAction("Credit", "Create", "$session_name added " . numfmt_format_currency($currency_format, $amount, $session_company_currency) . "", $client_id, $credit_id);

    flash_alert(numfmt_format_currency($currency_format, $amount, $session_company_currency) . " Credit Added");

    redirect();

}
