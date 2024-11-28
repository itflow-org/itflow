<?php

/*
 * ITFlow - Main GET/POST request handler
 */

require_once "config.php";

require_once "functions.php";

require_once "check_login.php";


// Determine which files we should load

// Parse URL & get the path
$path = parse_url($_SERVER['HTTP_REFERER'], PHP_URL_PATH);

// Get the base name (the page name)
$module = explode(".", basename($path))[0];

// Strip off any _details bits
$module = str_ireplace('_details', '', $module);

// Dynamically load admin-related module POST logic
if (str_contains($module, 'admin') && isset($session_is_admin) && $session_is_admin) {
    // As (almost) every admin setting is only changed from 1 page, we can dynamically load the relevant logic inside this single admin check IF statement
    //  To add a new admin POST request handler, add a file named after the admin page
    //    e.g. changes made on the page http://itflow/admin_ticket_statues.php will load the page post/admin/admin_ticket_statues.php to handle the changes

    if ($module !== 'admin_update') {
        require_once "post/admin/$module.php";
    }
    // IF statement is temporary




} elseif (str_contains($module, 'xcustom')) {
    // Dynamically load any custom POST logic

    require_once "post/xcustom/$module.php";

} else {

    // Load all module POST logic
    //  Loads everything in post/user/
    //  Eventually, it would be nice to only specifically load what we need like we do for admins

    foreach (glob("post/user/*.php") as $user_module) {
        if (!preg_match('/_model\.php$/', basename($user_module))) {
            require_once $user_module;
        }
    }

}
//// added by Qais 


    // ADD NEW JOB
    if (isset($_POST['add_job'])) {
        $scope = mysqli_real_escape_string($mysqli, $_POST['scope']);
        $client_id = intval($_POST['client_id']);
        $type = mysqli_real_escape_string($mysqli, $_POST['type']);
        $status = mysqli_real_escape_string($mysqli, $_POST['status']);
        $dropbox_link = mysqli_real_escape_string($mysqli, $_POST['dropbox_link']);

        // Insert new job into the database
        $sql = "INSERT INTO jobs (client_id, scope, type, status, dropbox_link, created_at) 
                VALUES ('$client_id', '$scope', '$type', '$status', '$dropbox_link', NOW())";

        if (mysqli_query($mysqli, $sql)) {
            $_SESSION['alert_message'] = "Job added successfully!";
            header("Location: jobs.php"); // Redirect back to the jobs page
            exit;
        } else {
            $_SESSION['alert_message'] = "Error: Unable to add job. " . mysqli_error($mysqli);
            header("Location: jobs.php");
            exit;
        }
    }

    // EDIT JOB
    if (isset($_POST['edit_job'])) {
        $job_id = intval($_POST['job_id']);
        $scope = mysqli_real_escape_string($mysqli, $_POST['scope']);
        $type = mysqli_real_escape_string($mysqli, $_POST['type']);
        $status = mysqli_real_escape_string($mysqli, $_POST['status']);
        $dropbox_link = mysqli_real_escape_string($mysqli, $_POST['dropbox_link']);
    
        // Debugging: Log the received data
        error_log("Received Data: " . print_r($_POST, true));
    
        // Generate SQL query
        $sql = "UPDATE jobs 
                SET scope = '$scope', 
                    type = '$type', 
                    status = '$status', 
                    dropbox_link = '$dropbox_link', 
                    updated_at = NOW() 
                WHERE job_id = $job_id";
    
        // Debugging: Log the query
        error_log("Generated SQL: $sql");
    
        if (mysqli_query($mysqli, $sql)) {
            // Check affected rows
            if (mysqli_affected_rows($mysqli) > 0) {
                $_SESSION['alert_message'] = "Job updated successfully!";
            } else {
                $_SESSION['alert_message'] = "No changes made or job not found.";
            }
        } else {
            $_SESSION['alert_message'] = "Error: " . mysqli_error($mysqli);
        }
        header("Location: jobs.php");
        exit;
    }
    

    // DELETE JOB
    if (isset($_GET['delete_job'])) {
        $job_id = intval($_GET['delete_job']); // Ensure it's an integer

        // Update the column name to match your table structure
        $sql = "DELETE FROM jobs WHERE job_id = $job_id";

        if (mysqli_query($mysqli, $sql)) {
            $_SESSION['alert_message'] = "Job deleted successfully!";
            header("Location: jobs.php"); // Redirect back to jobs page
            exit;
        } else {
            $_SESSION['alert_message'] = "Error: Unable to delete job. " . mysqli_error($mysqli);
            header("Location: jobs.php");
            exit;
        }
    }


    ////////////// end added by Qais 



// Logout is the same for user and admin
require_once "post/logout.php";

// TODO: Move admin_update into the admin section to be auto-loaded
//  We can't do this until everyone has the new database fields added in 1.4.9 on Sept 14th 2024
require_once "post/admin_update.php"; // Load updater

// TODO: Find a home for these

require_once "post/ai.php";

require_once "post/misc.php";

