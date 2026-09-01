<?php

/*
 * API - read_output.php
 * Included on calls to read.php endpoints
 * Returns success & data messages
 */

if ($sql && mysqli_num_rows($sql) > 0) {
    $return_arr['success'] = "True";
    $return_arr['count'] = mysqli_num_rows($sql);

    $row = array();
    while ($row = mysqli_fetch_assoc($sql)) {
        $return_arr['data'][] = $row;
    }

    echo json_encode($return_arr);
    exit();
}
else {
    $return_arr['success'] = "False";
    $return_arr['message'] = "No resource (for this client and company) with the specified parameter(s).";

    logApp("API", "Error", "Read query failed on API call to " . escapeSql(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH)) . " containing " . count($_GET) . " GET variables" . " via API key " . escapeSql($api_key_name) . " from IP " . escapeSql(getIP()) . " with agent " . escapeSql(getUserAgent()));

    // Log any database/schema related errors to the PHP Error log
    if (mysqli_error($mysqli)) {
        error_log("API Database Error: " . mysqli_error($mysqli));
    }

    echo json_encode($return_arr);
    exit();
}
