<?php

/*
 * ITFlow - GET/POST request handler for DB / master key backup
 */

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

if (isset($_GET['download_database'])) {
    validateCSRFToken($_GET['csrf_token']);

    global $mysqli, $database;

    $backupFileName = date('Y-m-d_H-i-s') . '_backup.sql';

    header('Content-Type: application/sql');
    header('Content-Disposition: attachment; filename="' . $backupFileName . '"');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');

    if (ob_get_level()) ob_end_clean();
    flush();

    // Start of dump file — charset declaration
    echo "-- UTF-8 + Foreign Key Safe Dump\n";
    echo "SET NAMES 'utf8mb4';\n";
    echo "SET foreign_key_checks = 0;\n\n";

    // Get all tables
    $tables = [];
    $res = $mysqli->query("SHOW TABLES");
    while ($row = $res->fetch_row()) {
        $tables[] = $row[0];
    }

    foreach ($tables as $table) {
        // Table structure
        $createRes = $mysqli->query("SHOW CREATE TABLE `$table`");
        $createRow = $createRes->fetch_assoc();
        $createSQL = array_values($createRow)[1];

        echo "\n-- ----------------------------\n";
        echo "-- Table structure for `$table`\n";
        echo "-- ----------------------------\n";
        echo "DROP TABLE IF EXISTS `$table`;\n";
        echo $createSQL . ";\n\n";

        // Table data
        $dataRes = $mysqli->query("SELECT * FROM `$table`");
        if ($dataRes->num_rows > 0) {
            echo "-- Dumping data for table `$table`\n";
            while ($row = $dataRes->fetch_assoc()) {
                $columns = array_map(fn($col) => '`' . $mysqli->real_escape_string($col) . '`', array_keys($row));
                $values = array_map(function ($val) use ($mysqli) {
                    if (is_null($val)) return "NULL";
                    return "'" . $mysqli->real_escape_string($val) . "'";
                }, array_values($row));

                echo "INSERT INTO `$table` (" . implode(", ", $columns) . ") VALUES (" . implode(", ", $values) . ");\n";
            }
            echo "\n";
        }
    }

    //FINAL STEP: Re-enable foreign key checks
    echo "\nSET foreign_key_checks = 1;\n";

    logAction("Database", "Download", "$session_name downloaded the database.");
    $_SESSION['alert_message'] = "Database downloaded";
    exit;
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
