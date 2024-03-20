<?php

// Accounts Related Functions

function createAccountType(
    $name,
    $type,
    $description
) {

    global $mysqli, $session_ip, $session_user_agent, $session_user_id;

    mysqli_query($mysqli,"INSERT INTO account_types SET account_type_parent = $type, account_type_name = '$name', account_type_description = '$description'");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Account Type', log_action = 'Create', log_description = '$name', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id");
}
function editAccountType(
    $account_type_id,
    $name,
    $type,
    $description
) {

    global $mysqli, $session_ip, $session_user_agent, $session_user_id;

    mysqli_query($mysqli,"UPDATE account_types SET account_type_parent = $type, account_type_name = '$name', account_type_description = '$description' WHERE account_type_id = $account_type_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Account Type', log_action = 'Edit', log_description = '$name', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id");
}
function readAccountType(
    $account_type_id
) {
    global $mysqli;

    $result = mysqli_query($mysqli,"SELECT * FROM account_types WHERE account_type_id = $account_type_id");
    return mysqli_fetch_assoc($result);
}
function archiveAccountType(
    $account_type_id
) {

    global $mysqli, $session_ip, $session_user_agent, $session_user_id;

    mysqli_query($mysqli,"UPDATE account_types SET account_type_archived_at = NOW() WHERE account_type_id = $account_type_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Account Type', log_action = 'Archive', log_description = '$account_type_id', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id");
}
function unarchiveAccountType(
    $account_type_id
) {

    global $mysqli, $session_ip, $session_user_agent, $session_user_id;

    mysqli_query($mysqli,"UPDATE account_types SET account_type_archived_at = NULL WHERE account_type_id = $account_type_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Account Type', log_action = 'Unarchive', log_description = '$account_type_id', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id");
}
function createAccount(
    $name,
    $opening_balance,
    $currency_code,
    $notes,
    $type
) {
    global $mysqli, $session_ip, $session_user_agent, $session_user_id;

    mysqli_query($mysqli,"INSERT INTO accounts SET account_name = '$name', opening_balance = $opening_balance, account_currency_code = '$currency_code', account_type ='$type', account_notes = '$notes'");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Account', log_action = 'Create', log_description = '$name', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id");

}
function editAccount(
    $account_id,
    $name,
    $type,
    $notes
) {
    global $mysqli, $session_ip, $session_user_agent, $session_user_id;

    mysqli_query($mysqli,"UPDATE accounts SET account_name = '$name',account_type = '$type', account_notes = '$notes' WHERE account_id = $account_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Account', log_action = 'Modify', log_description = '$name', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id");
}
function readAccount(
    $account_id
) {
    global $mysqli;

    $result = mysqli_query($mysqli,"SELECT * FROM accounts WHERE account_id = $account_id");
    return mysqli_fetch_assoc($result);
}
function archiveAccount(
    $account_id
) {
    global $mysqli, $session_ip, $session_user_agent, $session_user_id;

    mysqli_query($mysqli,"UPDATE accounts SET account_archived_at = NOW() WHERE account_id = $account_id");

    //logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Account', log_action = 'Archive', log_description = '$account_id', log_ip = '$session_ip', log_user_agent = '$session_user_agent'");
}
function unarchiveAccount(
    $account_id
) {
    global $mysqli, $session_ip, $session_user_agent, $session_user_id;

    mysqli_query($mysqli,"UPDATE accounts SET account_archived_at = NULL WHERE account_id = $account_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Account', log_action = 'Unarchive', log_description = '$account_id', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id");
}
function deleteAccount(
    $account_id
) {
    global $mysqli, $session_ip, $session_user_agent, $session_user_id;

    mysqli_query($mysqli,"DELETE FROM accounts WHERE account_id = $account_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Account', log_action = 'Delete', log_description = '$account_id', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id");
}