<?php

/*
 * ITFlow - GET/POST request handler for account(s) (accounting related)
 */

if (isset($_POST['add_account_type'])) {

    $name = sanitizeInput($_POST['name']);
    $type = intval($_POST['type']);
    $description = sanitizeInput($_POST['description']);

    switch ($type) {
        case 10:
            $type_name = "Assets";
            $result = mysqli_query($mysqli,"SELECT account_type_id FROM account_types");
            $account_type_id = 10;
            while ($row = mysqli_fetch_array($result)) {
                if ($row['account_type_id'] == $account_type_id) {
                    $account_type_id++;
                }
            }
            mysqli_query($mysqli,"INSERT INTO account_types SET account_type_id = $account_type_id, account_type_name = '$name', account_type_description = '$description'");
            break;
        case 20:
            $type_name = "Liabilities";
            $result = mysqli_query($mysqli,"SELECT account_type_id FROM account_types");
            $account_type_id = 20;
            while ($row = mysqli_fetch_array($result)) {
                if ($row['account_type_id'] == $account_type_id) {
                    $account_type_id++;
                }
            }
            mysqli_query($mysqli,"INSERT INTO account_types SET account_type_id = $account_type_id, account_type_name = '$name', account_type_description = '$description'");
            break;
        case 30:
            $type_name = "Equity";
            $result = mysqli_query($mysqli,"SELECT account_type_id FROM account_types");
            $account_type_id = 30;
            while ($row = mysqli_fetch_array($result)) {
                if ($row['account_type_id'] == $account_type_id) {
                    $account_type_id++;
                }
            }
            mysqli_query($mysqli,"INSERT INTO account_types SET account_type_id = $account_type_id, account_type_name = '$name', account_type_description = '$description'");
            break;
    }


    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Account Type', log_action = 'Create', log_description = '$name', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id");

    $_SESSION['alert_message'] = "Account added";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_POST['edit_account_type'])) {

    $account_type_id = intval($_POST['account_type_id']);
    $name = sanitizeInput($_POST['name']);
    $description = sanitizeInput($_POST['description']);

    mysqli_query($mysqli,"UPDATE account_types SET account_type_name = '$name', account_type_description = '$description' WHERE account_type_id = $account_type_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Account Type', log_action = 'Edit', log_description = '$name', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id");

    $_SESSION['alert_message'] = "Account edited";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_GET['archive_account_type'])) {
    $account_type_id = intval($_GET['archive_account_type']);

    mysqli_query($mysqli,"UPDATE account_types SET account_type_archived_at = NOW() WHERE account_type_id = $account_type_id");

    //logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Account Type', log_action = 'Archive', log_description = '$account_id', log_ip = '$session_ip', log_user_agent = '$session_user_agent'");

    $_SESSION['alert_message'] = "Account Archived";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_GET['unarchive_account_type'])) {
    $account_type_id = intval($_GET['unarchive_account_type']);

    mysqli_query($mysqli,"UPDATE account_types SET account_type_archived_at = NULL WHERE account_type_id = $account_type_id");

    //logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Account Type', log_action = 'Unarchive', log_description = '$account_id', log_ip = '$session_ip', log_user_agent = '$session_user_agent'");

    $_SESSION['alert_message'] = "Account Unarchived";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}