<?php

/*
 * ITFlow - GET/POST request handler for tax
 */

if (isset($_POST['add_tax'])) {

    validateCSRFToken($_POST['csrf_token']);
    $name = sanitizeInput($_POST['name']);
    $percent = floatval($_POST['percent']);

    mysqli_query($mysqli,"INSERT INTO taxes SET tax_name = '$name', tax_percent = $percent");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Tax', log_action = 'Create', log_description = '$name - $percent', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id");

    $_SESSION['alert_message'] = "Tax added";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_POST['edit_tax'])) {

    validateCSRFToken($_POST['csrf_token']);
    $tax_id = intval($_POST['tax_id']);
    $name = sanitizeInput($_POST['name']);
    $percent = floatval($_POST['percent']);

    mysqli_query($mysqli,"UPDATE taxes SET tax_name = '$name', tax_percent = $percent WHERE tax_id = $tax_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Tax', log_action = 'Modify', log_description = '$name - $percent', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id");

    $_SESSION['alert_message'] = "Tax modified";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_GET['archive_tax'])) {
    validateCSRFToken($_GET['csrf_token']);
    $tax_id = intval($_GET['archive_tax']);

    mysqli_query($mysqli,"UPDATE taxes SET tax_archived_at = NOW() WHERE tax_id = $tax_id");

    //logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Tax', log_action = 'Archive', log_description = '$tax_id', log_ip = '$session_ip', log_user_agent = '$session_user_agent'");

    $_SESSION['alert_message'] = "Tax Archived";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_GET['delete_tax'])) {
    $tax_id = intval($_GET['delete_tax']);

    mysqli_query($mysqli,"DELETE FROM taxes WHERE tax_id = $tax_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Tax', log_action = 'Delete', log_description = '$tax_id', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id");

    $_SESSION['alert_message'] = "Tax deleted";
    $_SESSION['alert_type'] = "error";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}
