<?php

/*
 * ITFlow - Custom script to fill in DB Values Size and MIME Type for uploaded files
 */

require_once "config.php";

require_once "functions.php";

require_once "check_login.php";


function scanDirectory($dir, $mysqli) {
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($iterator as $file) {
        if ($file->isFile()) {
            $file_path = $file->getPathname();
            $file_name = $file->getFilename();
            // Process the file
            processFile($file_path, $file_name, $mysqli);
        }
    }
}

function processFile($file_path, $file_name, $mysqli) {
    // Get the file size
    $file_size = filesize($file_path);
    // Get the MIME type
    $file_mime_type = mime_content_type($file_path);

    // Prepare a statement to check if the file exists in the database
    $stmt_select = mysqli_prepare($mysqli, "SELECT file_id FROM files WHERE file_reference_name = ?");
    mysqli_stmt_bind_param($stmt_select, 's', $file_name);
    mysqli_stmt_execute($stmt_select);
    mysqli_stmt_store_result($stmt_select);

    if (mysqli_stmt_num_rows($stmt_select) > 0) {
        // File exists in the database, proceed to update
        $stmt_update = mysqli_prepare($mysqli, "UPDATE files SET file_mime_type = ?, file_size = ? WHERE file_reference_name = ?");
        mysqli_stmt_bind_param($stmt_update, 'sis', $file_mime_type, $file_size, $file_name);

        if (mysqli_stmt_execute($stmt_update)) {
            echo "Updated: $file_name\n";
        } else {
            echo "Error updating $file_name: " . mysqli_stmt_error($stmt_update) . "\n";
        }
        mysqli_stmt_close($stmt_update);
    } else {
        echo "No database entry found for: $file_name\n";
    }
    mysqli_stmt_close($stmt_select);
}

// Define the uploads directory (modify the path if necessary)
$uploads_dir = __DIR__ . '/uploads';

// Start scanning from the uploads directory
scanDirectory($uploads_dir, $mysqli);

// Close the database connection
mysqli_close($mysqli);

$_SESSION['alert_message'] = "Files Fixed";

header("Location: " . $_SERVER["HTTP_REFERER"]);
