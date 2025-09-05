<?php

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

if (isset($_POST['edit_identity_provider'])) {

    validateCSRFToken($_POST['csrf_token']);

    $azure_client_id = sanitizeInput($_POST['azure_client_id']);
    $azure_client_secret = sanitizeInput($_POST['azure_client_secret']);

    mysqli_query($mysqli,"UPDATE settings SET config_azure_client_id = '$azure_client_id', config_azure_client_secret = '$azure_client_secret' WHERE company_id = 1");

    logAction("Settings", "Edit", "$session_name edited identity provider settings");

    flash_alert("Identity Provider Settings updated");

    redirect();

}
