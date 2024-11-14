<?php

/*
 * ITFlow - GET/POST request handler for DB / master key backup
 */

if (isset($_GET['download_database'])) {

    validateCSRFToken($_GET['csrf_token']);

    // Get All Table Names From the Database
    $tables = array();
    $sql = "SHOW TABLES";
    $result = mysqli_query($mysqli, $sql);

    while ($row = mysqli_fetch_row($result)) {
        $tables[] = $row[0];
    }

    $sqlScript = "";
    foreach ($tables as $table) {

        // Prepare SQLscript for creating table structure
        $query = "SHOW CREATE TABLE $table";
        $result = mysqli_query($mysqli, $query);
        $row = mysqli_fetch_row($result);

        $sqlScript .= "\n\n" . $row[1] . ";\n\n";


        $query = "SELECT * FROM $table";
        $result = mysqli_query($mysqli, $query);

        $columnCount = mysqli_num_fields($result);

        // Prepare SQLscript for dumping data for each table
        for ($i = 0; $i < $columnCount; $i ++) {
            while ($row = mysqli_fetch_row($result)) {
                $sqlScript .= "INSERT INTO $table VALUES(";
                for ($j = 0; $j < $columnCount; $j ++) {

                    if (isset($row[$j])) {
                        $sqlScript .= '"' . $row[$j] . '"';
                    } else {
                        $sqlScript .= '""';
                    }
                    if ($j < ($columnCount - 1)) {
                        $sqlScript .= ',';
                    }
                }
                $sqlScript .= ");\n";
            }
        }

        $sqlScript .= "\n";
    }

    if (!empty($sqlScript)) {

        $company_name = $session_company_name;
        // Save the SQL script to a backup file
        $backup_file_name = date('Y-m-d') . '_ITFlow_backup.sql';
        $fileHandler = fopen($backup_file_name, 'w+');
        $number_of_lines = fwrite($fileHandler, $sqlScript);
        fclose($fileHandler);

        // Download the SQL backup file to the browser
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename=' . basename($backup_file_name));
        header('Content-Transfer-Encoding: binary');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($backup_file_name));
        ob_clean();
        flush();
        readfile($backup_file_name);
        exec('rm ' . $backup_file_name);
    }

    // Logging
    logAction("Database", "Download", "$session_name downloaded the database");

    $_SESSION['alert_message'] = "Database downloaded";
}

if (isset($_POST['backup_master_key'])) {

    validateCSRFToken($_POST['csrf_token']);

    $password = $_POST['password'];

    $sql = mysqli_query($mysqli, "SELECT * FROM users WHERE user_id = $session_user_id");
    $row = mysqli_fetch_array($sql);

    if (password_verify($password, $row['user_password'])) {
        $site_encryption_master_key = decryptUserSpecificKey($row['user_specific_encryption_ciphertext'], $password);
        
        // Logging
        logAction("Master Key", "Download", "$session_name retrieved the master encryption key");

        // App Notify
        appNotify("Master Key", "$session_name retrieved the master encryption key");

        echo "==============================";
        echo "<br>Master encryption key:<br>";
        echo "<b>$site_encryption_master_key</b>";
        echo "<br>==============================";
    
    } else {
        // Log the failure
        logAction("Master Key", "Download", "$session_name attempted to retrieve the master encryption key but failed");

        $_SESSION['alert_type'] = "error";
        $_SESSION['alert_message'] = "Incorrect password.";
        
        header("Location: " . $_SERVER["HTTP_REFERER"]);
    }
}
