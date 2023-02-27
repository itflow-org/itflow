<?php
/*
 * API - create_output.php
 * Included on calls to create.php endpoints
 * Checks the status of the insert SQL query ($insert_sql)
 * Returns success data / fail messages
 */

// Check if the insert query was successful
if (isset($insert_id) && is_numeric($insert_id)) {
    // Insert successful
    $return_arr['success'] = "True";
    $return_arr['count'] = '1';
    $return_arr['data'][] = [
        'insert_id' => $insert_id
    ];
}

// Query returned false: something went wrong, or it was declined due to required variables missing
else {
    $return_arr['success'] = "False";
    $return_arr['message'] = "Auth success but insert query failed, ensure ALL required variables are provided (and aren't duplicates where applicable) and database schema is up-to-date. Turn on error logging and look for 'undefined index'.";

    // Log any database/schema related errors to the PHP Error log
    if (mysqli_error($mysqli)) {
        error_log("API Database Error: " . mysqli_error($mysqli));
    }
}

echo json_encode($return_arr);
exit();
