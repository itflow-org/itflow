<?php

/*
 * ITFlow - GET/POST request handler for client physical locations/sites
 */

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

if(isset($_POST['add_location'])){

    enforceUserPermission('module_client', 2);

    require_once 'location_model.php';


    if(!file_exists("../uploads/clients/$client_id")) {
        mkdir("../uploads/clients/$client_id");
    }

    mysqli_query($mysqli,"INSERT INTO locations SET location_name = '$name', location_description = '$description', location_country = '$country', location_address = '$address', location_city = '$city', location_state = '$state', location_zip = '$zip', location_phone_country_code = '$phone_country_code', location_phone = '$phone', location_phone_extension = '$extension', location_fax_country_code = '$fax_country_code', location_fax = '$fax', location_hours = '$hours', location_notes = '$notes', location_contact_id = $contact, location_client_id = $client_id");

    $location_id = mysqli_insert_id($mysqli);

    // Add Tags
    if (isset($_POST['tags'])) {
        foreach($_POST['tags'] as $tag) {
            $tag = intval($tag);
            mysqli_query($mysqli, "INSERT INTO location_tags SET location_id = $location_id, tag_id = $tag");
        }
    }

    // Update Primary location in clients if primary location is checked
    if ($location_primary == 1) {
        mysqli_query($mysqli,"UPDATE locations SET location_primary = 0 WHERE location_client_id = $client_id");
        mysqli_query($mysqli,"UPDATE locations SET location_primary = 1 WHERE location_id = $location_id");
    }

    if (isset($_FILES['file']['tmp_name'])) {
        if ($new_file_name = checkFileUpload($_FILES['file'], array('jpg', 'jpeg', 'gif', 'png', 'webp'))) {

            $file_tmp_path = $_FILES['file']['tmp_name'];

            // directory in which the uploaded file will be moved
            $upload_file_dir = "../uploads/clients/$client_id/";
            $dest_path = $upload_file_dir . $new_file_name;

            move_uploaded_file($file_tmp_path, $dest_path);

            mysqli_query($mysqli,"UPDATE locations SET location_photo = '$new_file_name' WHERE location_id = $location_id");

        }
    }

    // Logging
    logAction("Location", "Create", "$session_name created location $name", $client_id, $location_id);

    $_SESSION['alert_message'] = "Location <strong>$name</strong> created.";

    redirect();

}

if(isset($_POST['edit_location'])){

    enforceUserPermission('module_client', 2);

    require_once 'location_model.php';


    $location_id = intval($_POST['location_id']);

    // Get old location photo
    $sql = mysqli_query($mysqli,"SELECT location_photo FROM locations WHERE location_id = $location_id");
    $row = mysqli_fetch_array($sql);
    $existing_file_name = sanitizeInput($row['location_photo']);


    if(!file_exists("../uploads/clients/$client_id")) {
        mkdir("../uploads/clients/$client_id");
    }

    mysqli_query($mysqli,"UPDATE locations SET location_name = '$name', location_description = '$description', location_country = '$country', location_address = '$address', location_city = '$city', location_state = '$state', location_zip = '$zip',  location_phone_country_code = '$phone_country_code', location_phone = '$phone', location_phone_extension = '$extension',  location_fax_country_code = '$fax_country_code', location_fax = '$fax', location_hours = '$hours', location_notes = '$notes', location_contact_id = $contact WHERE location_id = $location_id");

    // Update Primay location in clients if primary location is checked
    if ($location_primary == 1) {
        mysqli_query($mysqli,"UPDATE locations SET location_primary = 0 WHERE location_client_id = $client_id");
        mysqli_query($mysqli,"UPDATE locations SET location_primary = 1 WHERE location_id = $location_id");
    }

    // Tags
    // Delete existing tags
    mysqli_query($mysqli, "DELETE FROM location_tags WHERE location_id = $location_id");

    // Add new tags
    if (isset($_POST['tags'])) {
        foreach($_POST['tags'] as $tag) {
            $tag = intval($tag);
            mysqli_query($mysqli, "INSERT INTO location_tags SET location_id = $location_id, tag_id = $tag");
        }
    }

    if ($new_file_name = checkFileUpload($_FILES['file'], array('jpg', 'jpeg', 'gif', 'png', 'webp'))) {

        $file_tmp_path = $_FILES['file']['tmp_name'];

        // directory in which the uploaded file will be moved
        $upload_file_dir = "../uploads/clients/$client_id/";
        $dest_path = $upload_file_dir . $new_file_name;

        move_uploaded_file($file_tmp_path, $dest_path);

        //Delete old file
        unlink("../uploads/clients/$client_id/$existing_file_name");

        mysqli_query($mysqli,"UPDATE locations SET location_photo = '$new_file_name' WHERE location_id = $location_id");

    }

    // Logging
    logAction("Location", "Edit", "$session_name edited location $name", $client_id, $location_id);

    $_SESSION['alert_message'] = "Location <strong>$name</strong> updated";

    redirect();

}

if(isset($_GET['archive_location'])){

    enforceUserPermission('module_client', 2);

    $location_id = intval($_GET['archive_location']);

    // Get Location Name and Client ID for logging and alert message
    $sql = mysqli_query($mysqli,"SELECT location_name, location_client_id FROM locations WHERE location_id = $location_id");
    $row = mysqli_fetch_array($sql);
    $location_name = sanitizeInput($row['location_name']);
    $client_id = intval($row['location_client_id']);

    mysqli_query($mysqli,"UPDATE locations SET location_archived_at = NOW() WHERE location_id = $location_id");

    // Logging
    logAction("Location", "Archive", "$session_name archived location $location_name", $client_id, $location_id);

    $_SESSION['alert_type'] = "error";
    $_SESSION['alert_message'] = "Location <strong>$location_name</strong> archived";

    redirect();

}

if(isset($_GET['unarchive_location'])){

    enforceUserPermission('module_client', 2);

    $location_id = intval($_GET['unarchive_location']);

    // Get Location Name and Client ID for logging and alert message
    $sql = mysqli_query($mysqli,"SELECT location_name, location_client_id FROM locations WHERE location_id = $location_id");
    $row = mysqli_fetch_array($sql);
    $location_name = sanitizeInput($row['location_name']);
    $client_id = intval($row['location_client_id']);

    mysqli_query($mysqli,"UPDATE locations SET location_archived_at = NULL WHERE location_id = $location_id");

    // Logging
    logAction("Location", "Unarchive", "$session_name unarchived location $location_name", $client_id, $location_id);

    $_SESSION['alert_message'] = "Location <strong>$location_name</strong> restored";

    redirect();
}

if(isset($_GET['delete_location'])){

    enforceUserPermission('module_client', 3);

    $location_id = intval($_GET['delete_location']);

    // Get Location Name and Client ID for logging and alert message
    $sql = mysqli_query($mysqli,"SELECT location_name, location_client_id FROM locations WHERE location_id = $location_id");
    $row = mysqli_fetch_array($sql);
    $location_name = sanitizeInput($row['location_name']);
    $client_id = intval($row['location_client_id']);

    mysqli_query($mysqli,"DELETE FROM locations WHERE location_id = $location_id");

    // Logging
    logAction("Location", "Delete", "$session_name deleted location $location_name", $client_id);


    $_SESSION['alert_type'] = "error";
    $_SESSION['alert_message'] = "Location <strong>$location_name</strong> deleted";

    redirect();

}

if (isset($_POST['bulk_assign_location_tags'])) {

    enforceUserPermission('module_client', 2);

    // Assign Tags to Selected
    if (isset($_POST['location_ids'])) {

        // Get Selected Count
        $count = count($_POST['location_ids']);

        foreach($_POST['location_ids'] as $location_id) {
            $location_id = intval($location_id);

            // Get Contact Details for Logging
            $sql = mysqli_query($mysqli,"SELECT location_name, location_client_id FROM locations WHERE location_id = $location_id");
            $row = mysqli_fetch_array($sql);
            $location_name = sanitizeInput($row['location_name']);
            $client_id = intval($row['location_client_id']);

            if($_POST['bulk_remove_tags']) {
                // Delete tags if chosed to do so
                mysqli_query($mysqli, "DELETE FROM location_tags WHERE location_id = $location_id");
            }

            // Add new tags
            if (isset($_POST['bulk_tags'])) {
                foreach($_POST['bulk_tags'] as $tag) {
                    $tag = intval($tag);

                    $sql = mysqli_query($mysqli,"SELECT * FROM location_tags WHERE location_id = $location_id AND tag_id = $tag");
                    if (mysqli_num_rows($sql) == 0) {
                        mysqli_query($mysqli, "INSERT INTO location_tags SET location_id = $location_id, tag_id = $tag");
                    }
                }
            }

            // Logging
            logAction("Location", "Edit", "$session_name assigned tags to location $location_name", $client_id, $location_id);

        } // End Assign Location Loop

        // Logging
        logAction("Location", "Bulk Edit", "$session_name assigned tags to $count location(s)", $client_id);

        $_SESSION['alert_message'] = "Assigned tags for <strong>$count</strong> locations";
    }

    redirect();

}

if (isset($_POST['bulk_archive_locations'])) {
    enforceUserPermission('module_client', 2);
    validateCSRFToken($_POST['csrf_token']);

    if (isset($_POST['location_ids'])) {

        $count = 0; // Default 0

        // Cycle through array and archive each contact
        foreach ($_POST['location_ids'] as $location_id) {

            $location_id = intval($location_id);

            // Get Name and Client ID for logging and alert message
            $sql = mysqli_query($mysqli,"SELECT location_name, location_client_id, location_primary FROM locations WHERE location_id = $location_id");
            $row = mysqli_fetch_array($sql);
            $location_name = sanitizeInput($row['location_name']);
            $location_primary = intval($row['location_primary']);
            $client_id = intval($row['location_client_id']);

            if($location_primary == 0) {
                mysqli_query($mysqli,"UPDATE locations SET location_archived_at = NOW() WHERE location_id = $location_id");

                // Individual Contact logging
                logAction("Location", "Archive", "$session_name archived location $location_name", $client_id, $location_id);
                
                $count++;
            }

        }

        // Bulk Logging
        logAction("Location", "Bulk Archive", "$session_name archived $count location(s)");

        $_SESSION['alert_type'] = "error";
        $_SESSION['alert_message'] = "Archived <strong>$count</strong> location(s)";

    }

    redirect();
}

if (isset($_POST['bulk_unarchive_locations'])) {
    enforceUserPermission('module_client', 2);
    validateCSRFToken($_POST['csrf_token']);

    if (isset($_POST['location_ids'])) {

        // Get Selected Count
        $count = count($_POST['location_ids']);

        // Cycle through array and unarchive
        foreach ($_POST['location_ids'] as $location_id) {

            $location_id = intval($location_id);

            // Get Name and Client ID for logging and alert message
            $sql = mysqli_query($mysqli,"SELECT location_name, location_client_id FROM locations WHERE location_id = $location_id");
            $row = mysqli_fetch_array($sql);
            $location_name = sanitizeInput($row['location_name']);
            $client_id = intval($row['location_client_id']);

            mysqli_query($mysqli,"UPDATE locations SET location_archived_at = NULL WHERE location_id = $location_id");

            // Individual logging
            logAction("Location", "Unarchive", "$session_name unarchived location $location_name", $client_id, $location_id);

        }

        // Bulk Logging
        logAction("Location", "Bulk Unarchive", "$session_name unarchived $count location(s)", $client_id);

        $_SESSION['alert_message'] = "Unarchived <strong>$count</strong> location(s)";

    }

    redirect();
}

if (isset($_POST['bulk_delete_locations'])) {
    enforceUserPermission('module_client', 3);
    validateCSRFToken($_POST['csrf_token']);

    if (isset($_POST['location_ids'])) {

        // Get Selected Count
        $count = count($_POST['location_ids']);

        // Cycle through array and delete each record
        foreach ($_POST['location_ids'] as $location_id) {

            $location_id = intval($location_id);

            // Get Name and Client ID for logging and alert message
            $sql = mysqli_query($mysqli,"SELECT location_name, location_client_id FROM locations WHERE location_id = $location_id");
            $row = mysqli_fetch_array($sql);
            $location_name = sanitizeInput($row['location_name']);
            $client_id = intval($row['location_client_id']);

            mysqli_query($mysqli, "DELETE FROM locations WHERE location_id = $location_id AND location_client_id = $client_id");
            
            // Logging
            logAction("Location", "Delete", "$session_name deleted location $location_name", $client_id);

        }

        // Logging
        logAction("Location", "Bulk Delete", "$session_name deleted $count location(s)", $client_id);

        $_SESSION['alert_type'] = "error";
        $_SESSION['alert_message'] = "Deleted <strong>$count</strong> location(s)";

    }

    redirect();
}

if(isset($_POST['export_locations_csv'])){
    if (isset($_POST['client_id'])) {
        $client_id = intval($_POST['client_id']);
        $client_query = "AND location_client_id = $client_id";
    } else {
        $client_query = '';
        $client_id = 0;
    }

    //Locations
    $sql = mysqli_query($mysqli,"SELECT * FROM locations WHERE location_archived_at IS NULL $client_query ORDER BY location_name ASC");

    $num_rows = mysqli_num_rows($sql);

    if($num_rows > 0) {
        $delimiter = ",";
        $filename = "Locations-" . date('Y-m-d') . ".csv";

        //create a file pointer
        $f = fopen('php://memory', 'w');

        //set column headers
        $fields = array('Name', 'Description', 'Address', 'City', 'State', 'Postal Code', 'Phone', 'Hours');
        fputcsv($f, $fields, $delimiter);

        //output each row of the data, format line as csv and write to file pointer
        while($row = $sql->fetch_assoc()){
            $lineData = array($row['location_name'], $row['location_description'], $row['location_address'], $row['location_city'], $row['location_state'], $row['location_zip'], $row['location_phone'], $row['location_hours']);
            fputcsv($f, $lineData, $delimiter);
        }

        //move back to beginning of file
        fseek($f, 0);

        //set headers to download file rather than displayed
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '";');

        //output all remaining data on a file pointer
        fpassthru($f);
    }

    // Logging
    logAction("Location", "Export", "$session_name exported $num_rows location(s) to a CSV file", $client_id);

    exit;

}

if (isset($_POST["import_locations_csv"])) {

    enforceUserPermission('module_client', 2);

    $client_id = intval($_POST['client_id']);
    $error = false;

    if (!empty($_FILES["file"]["tmp_name"])) {
        $file_name = $_FILES["file"]["tmp_name"];
    } else {
        $_SESSION['alert_message'] = "Please select a file to upload.";
        $_SESSION['alert_type'] = "error";
        redirect();
        exit();
    }

    //Check file is CSV
    $file_extension = strtolower(end(explode('.',$_FILES['file']['name'])));
    $allowed_file_extensions = array('csv');
    if(in_array($file_extension,$allowed_file_extensions) === false){
        $error = true;
        $_SESSION['alert_message'] = "Bad file extension";
    }

    //Check file isn't empty
    elseif($_FILES["file"]["size"] < 1){
        $error = true;
        $_SESSION['alert_message'] = "Bad file size (empty?)";
    }

    //(Else)Check column count
    $f = fopen($file_name, "r");
    $f_columns = fgetcsv($f, 1000, ",");
    if(!$error & count($f_columns) != 8) {
        $error = true;
        $_SESSION['alert_message'] = "Bad column count.";
    }

    //Else, parse the file
    if(!$error){
        $file = fopen($file_name, "r");
        fgetcsv($file, 1000, ","); // Skip first line
        $row_count = 0;
        $duplicate_count = 0;
        while(($column = fgetcsv($file, 1000, ",")) !== false){
            $duplicate_detect = 0;
            if(isset($column[0])){
                $name = sanitizeInput($column[0]);
                if(mysqli_num_rows(mysqli_query($mysqli,"SELECT * FROM locations WHERE location_name = '$name' AND location_client_id = $client_id")) > 0){
                    $duplicate_detect = 1;
                }
            }
            if(isset($column[1])){
                $description = sanitizeInput($column[1]);
            }
            if(isset($column[2])){
                $address = sanitizeInput($column[2]);
            }
            if(isset($column[3])){
                $city = sanitizeInput($column[3]);
            }
            if(isset($column[4])){
                $state = sanitizeInput($column[4]);
            }
            if(isset($column[5])){
                $zip = sanitizeInput($column[5]);
            }
            if(isset($column[6])){
                $phone = preg_replace("/[^0-9]/", '',$column[6]);
            }
            if(isset($column[7])){
                $hours = sanitizeInput($column[7]);
            }

            // Check if duplicate was detected
            if($duplicate_detect == 0){
                //Add
                mysqli_query($mysqli,"INSERT INTO locations SET location_name = '$name', location_description = '$description', location_address = '$address', location_city = '$city', location_state = '$state', location_zip = '$zip', location_phone = '$phone', location_hours = '$hours', location_client_id = $client_id");
                $row_count = $row_count + 1;
            }else{
                $duplicate_count = $duplicate_count + 1;
            }
        }
        fclose($file);

        // Logging
        logAction("Location", "Import", "$session_name imported $row_count location(s). $duplicate_count duplicate(s) found and not imported", $client_id);

        $_SESSION['alert_message'] = "$row_count Location(s) imported, $duplicate_count duplicate(s) detected and not imported";
        redirect();
    }
    //Check for any errors, if there are notify user and redirect
    if($error) {
        $_SESSION['alert_type'] = "warning";
        redirect();
    }
}

if(isset($_GET['download_locations_csv_template'])){

    $delimiter = ",";
    $filename = "Locations-Template.csv";

    //create a file pointer
    $f = fopen('php://memory', 'w');

    //set column headers
    $fields = array('Name', 'Description', 'Address', 'City', 'State', 'Postal Code', 'Phone', 'Hours');
    fputcsv($f, $fields, $delimiter);

    //move back to beginning of file
    fseek($f, 0);

    //set headers to download file rather than displayed
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '";');

    //output all remaining data on a file pointer
    fpassthru($f);
    exit;

}
