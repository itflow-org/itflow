<?php

/*
 * ITFlow - GET/POST request handler for client physical locations/sites
 */

if(isset($_POST['add_location'])){

    validateAdminRole();

    require_once('post/client_locations_model.php');

    if(!file_exists("uploads/clients/$client_id")) {
        mkdir("uploads/clients/$client_id");
    }

    mysqli_query($mysqli,"INSERT INTO locations SET location_name = '$name', location_country = '$country', location_address = '$address', location_city = '$city', location_state = '$state', location_zip = '$zip', location_phone = '$phone', location_hours = '$hours', location_notes = '$notes', location_contact_id = $contact, location_client_id = $client_id");

    $location_id = mysqli_insert_id($mysqli);

    // Update Primay location in clients if primary location is checked
    if($location_primary == 1){
        // Old way of adding contact_primary Set for Removal
        mysqli_query($mysqli,"UPDATE clients SET primary_location = $location_id WHERE client_id = $client_id");

        // New Way of setting primary location
        mysqli_query($mysqli,"UPDATE locations SET location_primary = 0 WHERE location_client_id = $client_id");
        mysqli_query($mysqli,"UPDATE locations SET location_primary = 1 WHERE location_id = $location_id");
    }

    //Check to see if a file is attached
    if($_FILES['file']['tmp_name'] != ''){
        if ($new_file_name = checkFileUpload($_FILES['file'], array('jpg', 'jpeg', 'gif', 'png'))) {

            $file_tmp_path = $_FILES['file']['tmp_name'];

            // directory in which the uploaded file will be moved
            $upload_file_dir = "uploads/clients/$client_id/";
            $dest_path = $upload_file_dir . $new_file_name;

            move_uploaded_file($file_tmp_path, $dest_path);

            mysqli_query($mysqli,"UPDATE locations SET location_photo = '$new_file_name' WHERE location_id = $location_id");

            $_SESSION['alert_message'] = 'File successfully uploaded.';
        }else{

            $_SESSION['alert_message'] = 'There was an error moving the file to upload directory. Please make sure the upload directory is writable by web server.';
        }
    }

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Location', log_action = 'Create', log_description = '$session_name created location $name', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_client_id = $client_id, log_user_id = $session_user_id, log_entity_id = $location_id");

    $_SESSION['alert_message'] .= "Location <strong>$name</strong> created.";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_POST['edit_location'])){

    validateAdminRole();

    require_once('post/client_locations_model.php');

    $location_id = intval($_POST['location_id']);

    // Get old location photo
    $sql = mysqli_query($mysqli,"SELECT location_photo FROM locations WHERE location_id = $location_id");
    $row = mysqli_fetch_array($sql);
    $existing_file_name = sanitizeInput($row['location_photo']);


    if(!file_exists("uploads/clients/$client_id")) {
        mkdir("uploads/clients/$client_id");
    }

    mysqli_query($mysqli,"UPDATE locations SET location_name = '$name', location_country = '$country', location_address = '$address', location_city = '$city', location_state = '$state', location_zip = '$zip', location_phone = '$phone', location_hours = '$hours', location_notes = '$notes', location_contact_id = $contact WHERE location_id = $location_id");

    // Update Primay location in clients if primary location is checked
    if($location_primary == 1){
        // Old way of adding contact_primary Set for Removal
        mysqli_query($mysqli,"UPDATE clients SET primary_location = $location_id WHERE client_id = $client_id");

        // New Way of setting primary location
        mysqli_query($mysqli,"UPDATE locations SET location_primary = 0 WHERE location_client_id = $client_id");
        mysqli_query($mysqli,"UPDATE locations SET location_primary = 1 WHERE location_id = $location_id");
    }

    //Check to see if a file is attached
    if($_FILES['file']['tmp_name'] != ''){

        if ($new_file_name = checkFileUpload($_FILES['file'], array('jpg', 'jpeg', 'gif', 'png'))) {

            $file_tmp_path = $_FILES['file']['tmp_name'];

            // directory in which the uploaded file will be moved
            $upload_file_dir = "uploads/clients/$client_id/";
            $dest_path = $upload_file_dir . $new_file_name;

            move_uploaded_file($file_tmp_path, $dest_path);

            //Delete old file
            unlink("uploads/clients/$client_id/$existing_file_name");

            mysqli_query($mysqli,"UPDATE locations SET location_photo = '$new_file_name' WHERE location_id = $location_id");

            $_SESSION['alert_message'] = 'File successfully uploaded.';
        }else{

            $_SESSION['alert_message'] = 'There was an error moving the file to upload directory. Please make sure the upload directory is writable by web server.';
        }
    }

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Location', log_action = 'Modify', log_description = '$session_name modified location $name', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_client_id = $client_id, log_user_id = $session_user_id, log_entity_id = $location_id");

    $_SESSION['alert_message'] .= "Location <strong>$name</strong> updated";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_GET['archive_location'])){

    validateTechRole();

    $location_id = intval($_GET['archive_location']);

    // Get Location Name and Client ID for logging and alert message
    $sql = mysqli_query($mysqli,"SELECT location_name, location_client_id FROM locations WHERE location_id = $location_id");
    $row = mysqli_fetch_array($sql);
    $location_name = sanitizeInput($row['location_name']);
    $client_id = intval($row['location_client_id']);

    mysqli_query($mysqli,"UPDATE locations SET location_archived_at = NOW() WHERE location_id = $location_id");

    //logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Location', log_action = 'Archive', log_description = '$session_name archived location $location_name', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_client_id = $client_id, log_user_id = $session_user_id, log_entity_id = $location_id");

    $_SESSION['alert_type'] = "error";
    $_SESSION['alert_message'] = "Location <strong>$location_name</strong> archived";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_GET['undo_archive_location'])){

    $location_id = intval($_GET['undo_archive_location']);

    // Get Location Name and Client ID for logging and alert message
    $sql = mysqli_query($mysqli,"SELECT location_name, location_client_id FROM locations WHERE location_id = $location_id");
    $row = mysqli_fetch_array($sql);
    $location_name = sanitizeInput($row['location_name']);
    $client_id = intval($row['location_client_id']);

    mysqli_query($mysqli,"UPDATE locations SET location_archived_at = NULL WHERE location_id = $location_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Location', log_action = 'Undo Archive', log_description = '$session_name restored location $location_name', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_client_id = $client_id, log_user_id = $session_user_id, log_entity_id = $location_id");

    $_SESSION['alert_message'] = "Location <strong>$location_name</strong> restored";

    header("Location: " . $_SERVER["HTTP_REFERER"]);
}

if(isset($_GET['delete_location'])){

    validateAdminRole();

    $location_id = intval($_GET['delete_location']);

    // Get Location Name and Client ID for logging and alert message
    $sql = mysqli_query($mysqli,"SELECT location_name, location_client_id FROM locations WHERE location_id = $location_id");
    $row = mysqli_fetch_array($sql);
    $location_name = sanitizeInput($row['location_name']);
    $client_id = intval($row['location_client_id']);

    mysqli_query($mysqli,"DELETE FROM locations WHERE location_id = $location_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Location', log_action = 'Delete', log_description = '$session_name deleted location $location_name', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_client_id = $client_id, log_user_id = $session_user_id, log_entity_id = $location_id");

    $_SESSION['alert_type'] = "error";
    $_SESSION['alert_message'] = "Location <strong>$location_name</strong> deleted";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_POST['export_client_locations_csv'])){
    $client_id = intval($_POST['client_id']);

    //get records from database
    $sql = mysqli_query($mysqli,"SELECT * FROM clients WHERE client_id = $client_id");
    $row = mysqli_fetch_array($sql);

    $client_name = sanitizeInput($row['client_name']);

    //Locations
    $sql = mysqli_query($mysqli,"SELECT * FROM locations WHERE location_client_id = $client_id AND location_archived_at IS NULL ORDER BY location_name ASC");

    $num_rows = mysqli_num_rows($sql);

    if($num_rows > 0) {
        $delimiter = ",";
        $filename = strtoAZaz09($client_name) . "-Locations-" . date('Y-m-d') . ".csv";

        //create a file pointer
        $f = fopen('php://memory', 'w');

        //set column headers
        $fields = array('Name', 'Address', 'City', 'State', 'Postal Code', 'Phone', 'Hours');
        fputcsv($f, $fields, $delimiter);

        //output each row of the data, format line as csv and write to file pointer
        while($row = $sql->fetch_assoc()){
            $lineData = array($row['location_name'], $row['location_address'], $row['location_city'], $row['location_state'], $row['location_zip'], $row['location_phone'], $row['location_hours']);
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

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Location', log_action = 'Export', log_description = '$session_name exported $num_rows location(s) to a CSV file', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_client_id = $client_id, log_user_id = $session_user_id");

    exit;

}

if(isset($_POST["import_client_locations_csv"])){

    validateTechRole();

    $client_id = intval($_POST['client_id']);
    $file_name = $_FILES["file"]["tmp_name"];
    $error = false;

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
    if(!$error & count($f_columns) != 7) {
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
                $address = sanitizeInput($column[1]);
            }
            if(isset($column[2])){
                $city = sanitizeInput($column[2]);
            }
            if(isset($column[3])){
                $state = sanitizeInput($column[3]);
            }
            if(isset($column[4])){
                $zip = sanitizeInput($column[4]);
            }
            if(isset($column[5])){
                $phone = preg_replace("/[^0-9]/", '',$column[5]);
            }
            if(isset($column[6])){
                $hours = sanitizeInput($column[6]);
            }

            // Check if duplicate was detected
            if($duplicate_detect == 0){
                //Add
                mysqli_query($mysqli,"INSERT INTO locations SET location_name = '$name', location_address = '$address', location_city = '$city', location_state = '$state', location_zip = '$zip', location_phone = '$phone', location_hours = '$hours', location_client_id = $client_id");
                $row_count = $row_count + 1;
            }else{
                $duplicate_count = $duplicate_count + 1;
            }
        }
        fclose($file);

        //Logging
        mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Location', log_action = 'Import', log_description = '$session_name imported $row_count location(s) via CSV file', log_ip = '$session_ip', log_user_agent = '$session_user_agent' log_client_id = $client_id, log_user_id = $session_user_id");

        $_SESSION['alert_message'] = "$row_count Location(s) imported, $duplicate_count duplicate(s) detected and not imported";
        header("Location: " . $_SERVER["HTTP_REFERER"]);
    }
    //Check for any errors, if there are notify user and redirect
    if($error) {
        $_SESSION['alert_type'] = "warning";
        header("Location: " . $_SERVER["HTTP_REFERER"]);
    }
}

if(isset($_GET['download_client_locations_csv_template'])){
    $client_id = intval($_GET['download_client_locations_csv_template']);

    //get records from database
    $sql = mysqli_query($mysqli,"SELECT client_name FROM clients WHERE client_id = $client_id");
    $row = mysqli_fetch_array($sql);

    $client_name = $row['client_name'];

    $delimiter = ",";
    $filename = strtoAZaz09($client_name) . "-Locations-Template.csv";

    //create a file pointer
    $f = fopen('php://memory', 'w');

    //set column headers
    $fields = array('Name', 'Address', 'City', 'State', 'Postal Code', 'Phone', 'Hours');
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
