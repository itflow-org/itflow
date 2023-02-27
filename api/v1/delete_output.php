<?php

/*
 * API - delete_output.php
 * Included on calls to delete.php endpoints
 * Returns success/failure messages
 */

// Check if delete query was successful
if (isset($delete_count) && is_numeric($delete_count) && $delete_count > 0) {
    // Delete was successful
    $return_arr['success'] = "True";
    $return_arr['count'] = $delete_count;
}

// Delete query returned false: something went wrong, or it was declined due to required variables missing
else {
    $return_arr['success'] = "False";
    $return_arr['message'] = "Auth success but delete query failed. Ensure ALL required variables are provided and database schema is up-to-date. Most likely cause: asset/client/company ID mismatch.";

    // Log any database/schema related errors to the PHP Error log
    if (mysqli_error($mysqli)) {
        error_log("API Database Error: " . mysqli_error($mysqli));
    }
}

echo json_encode($return_arr);
exit();
