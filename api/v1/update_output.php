<?php
/*
 * API - update_output.php
 * Included on calls to update.php endpoints
 * Checks the status of the update SQL query ($update_sql)
 * Returns success data / fail messages
 */

// Check if the insert query was successful
if (isset($update_count) && is_numeric($update_count) && $update_count > 0) {
    // Insert successful
    $return_arr['success'] = "True";
    $return_arr['count'] = $update_count;
}

// Query returned false: something went wrong, or it was declined due to required variables missing
else {
    $return_arr['success'] = "False";
    $return_arr['message'] = "Auth success but update query failed/returned no results. Ensure ALL required variables are provided and database schema is up-to-date. Most likely cause: non-existent module ID (i.e. bad contact ID/ticket ID/etc).";

    // Log any database/schema related errors to the PHP Error log
    if (mysqli_error($mysqli)) {
        error_log("API Database Error: " . mysqli_error($mysqli));
    }
}

echo json_encode($return_arr);
exit();
