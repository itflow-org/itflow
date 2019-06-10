<?php

    //DB Settings

    $dbhost = "localhost";
    $dbusername = "root";
    $dbpassword = "password";
    $database = "test";

    $mysqli = mysqli_connect($dbhost, $dbusername, $dbpassword, $database);

// Name of the file
$filename = 'db.sql';
// Temporary variable, used to store current query
$templine = '';
// Read in entire file
$lines = file($filename);
// Loop through each line
foreach ($lines as $line){
    // Skip it if it's a comment
    if(substr($line, 0, 2) == '--' || $line == '')
        continue;

    // Add this line to the current segment
    $templine .= $line;
    // If it has a semicolon at the end, it's the end of the query
    if(substr(trim($line), -1, 1) == ';'){
        // Perform the query
        mysqli_query($mysqli,$templine);
        // Reset temp variable to empty
        $templine = '';
    }
}

?>