<?php

/*
 * ITFlow - GET/POST request handler for trips (accounting related)
 */

if (isset($_POST['add_trip'])) {

    require_once 'post/user/trip_model.php';


    mysqli_query($mysqli,"INSERT INTO trips SET trip_date = '$date', trip_source = '$source', trip_destination = '$destination', trip_miles = $miles, round_trip = $roundtrip, trip_purpose = '$purpose', trip_user_id = $user_id, trip_client_id = $client_id");

    // Logging
    logAction("Trip", "Create", "$session_name logged trip from $source to $destination", $client_id , $trip_id);

    $_SESSION['alert_message'] = "Trip from <strong>$source</strong> to <strong>$destination</strong> logged";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_POST['edit_trip'])) {

    require_once 'post/user/trip_model.php';

    $trip_id = intval($_POST['trip_id']);

    mysqli_query($mysqli,"UPDATE trips SET trip_date = '$date', trip_source = '$source', trip_destination = '$destination', trip_miles = $miles, trip_purpose = '$purpose', round_trip = $roundtrip, trip_user_id = $user_id, trip_client_id = $client_id WHERE trip_id = $trip_id");

    // Logging
    logAction("Trip", "Edit", "$session_name edited trip", $client_id , $trip_id);

    $_SESSION['alert_message'] = "Trip edited";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_GET['delete_trip'])) {
    
    $trip_id = intval($_GET['delete_trip']);

    // Get Trip Info and Client ID for logging
    $row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT * FROM trips WHERE trip_id = $trip_id"));
    $client_id = intval($row['trip_client_id']);
    $trip_source = sanitizeInput($row['trip_source']);
    $trip_destination = sanitizeInput($row['trip_destination']);

    mysqli_query($mysqli,"DELETE FROM trips WHERE trip_id = $trip_id");

    // Logging
    logAction("Trip", "Delete", "$session_name deleted trip ($trip_source - $trip_destination)", $client_id);

    $_SESSION['alert_type'] = "error";
    $_SESSION['alert_message'] = "Trip ($trip_source - $trip_destination) deleted";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_POST['export_trips_csv'])) {
    
    $date_from = sanitizeInput($_POST['date_from']);
    $date_to = sanitizeInput($_POST['date_to']);
    if (!empty($date_from) && !empty($date_to)){
        $date_query = "DATE(trip_date) BETWEEN '$date_from' AND '$date_to'";
        $file_name_date = "$date_from-to-$date_to";
    } else {
        $date_query = "trip_date IS NOT NULL";
        $file_name_date = date('Y-m-d');
    }

    //get records from database
    $sql = mysqli_query($mysqli,"SELECT * FROM trips 
        LEFT JOIN clients ON trip_client_id = client_id
        WHERE $date_query
        ORDER BY trip_date DESC"
    );

    $count = mysqli_num_rows($sql);

    if ($count > 0) {
        $delimiter = ",";
        $filename = "$session_company_name-Trips-$file_name_date.csv";

        //create a file pointer
        $f = fopen('php://memory', 'w');

        //set column headers
        $fields = array('Date', 'Purpose', 'Source', 'Destination', 'Miles');
        fputcsv($f, $fields, $delimiter);

        //output each row of the data, format line as csv and write to file pointer
        while($row = mysqli_fetch_assoc($sql)){
            $lineData = array($row['trip_date'], $row['trip_purpose'], $row['trip_source'], $row['trip_destination'], $row['trip_miles']);
            fputcsv($f, $lineData, $delimiter);
        }

        //move back to beginning of file
        fseek($f, 0);

        //set headers to download file rather than displayed
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '";');

        //output all remaining data on a file pointer
        fpassthru($f);
    
        // Logging
        logAction("Trip", "Export", "$session_name exported $count trip(s) to a CSV file");
    }
    exit;

}

if (isset($_POST['export_client_trips_csv'])) {
    $client_id = intval($_POST['client_id']);

    //get records from database
    $sql = mysqli_query($mysqli,"SELECT client_name FROM clients WHERE client_id = $client_id");
    $row = mysqli_fetch_array($sql);

    $client_name = sanitizeInput($row['client_name']);

    $sql = mysqli_query($mysqli,"SELECT * FROM trips WHERE trip_client_id = $client_id ORDER BY trip_date ASC");
    
    $count = mysqli_num_rows($sql);

    if ($count > 0) {
        $delimiter = ",";
        $filename = $row['client_name'] . "-Trips-" . date('Y-m-d') . ".csv";

        //create a file pointer
        $f = fopen('php://memory', 'w');

        //set column headers
        $fields = array('Date', 'Purpose', 'Source', 'Destination', 'Miles');
        fputcsv($f, $fields, $delimiter);

        //output each row of the data, format line as csv and write to file pointer
        while($row = $sql->fetch_assoc()){
            $lineData = array($row['trip_date'], $row['trip_purpose'], $row['trip_source'], $row['trip_destination'], $row['trip_miles']);
            fputcsv($f, $lineData, $delimiter);
        }

        //move back to beginning of file
        fseek($f, 0);

        //set headers to download file rather than displayed
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '";');

        //output all remaining data on a file pointer
        fpassthru($f);

        // Logging
        logAction("Trip", "Export", "$session_name exported $count trip(s) to a CSV file for client $client_name", $client_id);
    }
    exit;

}
