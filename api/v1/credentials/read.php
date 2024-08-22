<?php

require_once '../validate_api_key.php';

require_once '../require_get_method.php';

// Default
$sql = false;

// Specific credential/login via ID (single)
if (isset($_GET['login_id']) && isset($_GET['api_key_decrypt_password'])) {

    $id = intval($_GET['login_id']);
    $api_key_decrypt_password = $_GET['api_key_decrypt_password']; // No sanitization

    $sql = mysqli_query($mysqli, "SELECT * FROM logins WHERE login_id = '$id' AND login_client_id LIKE '$client_id' LIMIT 1");


} elseif (isset($_GET['api_key_decrypt_password'])) {
    // All credentials ("logins")
    $sql = mysqli_query($mysqli, "SELECT * FROM logins WHERE login_client_id LIKE '$client_id' ORDER BY login_id LIMIT $limit OFFSET $offset");

}

// Output - Not using the standard API read_output.php
// Usually we just output what is in the database, but credentials need to be decrypted first.

if ($sql && mysqli_num_rows($sql) > 0) {

    $return_arr['success'] = "True";
    $return_arr['count'] = mysqli_num_rows($sql);

    $row = array();
    while ($row = mysqli_fetch_array($sql)) {
        $row['login_username'] = apiDecryptLoginEntry($row['login_username'], $api_key_decrypt_hash, $api_key_decrypt_password);
        $row['login_password'] = apiDecryptLoginEntry($row['login_password'], $api_key_decrypt_hash, $api_key_decrypt_password);
        $return_arr['data'][] = $row;
    }

    echo json_encode($return_arr);
    exit();
}
else {
    $return_arr['success'] = "False";
    $return_arr['message'] = "No resource (for this client and company) with the specified parameter(s).";

    // Log any database/schema related errors to the PHP Error log
    if (mysqli_error($mysqli)) {
        error_log("API Database Error: " . mysqli_error($mysqli));
    }

    echo json_encode($return_arr);
    exit();
}