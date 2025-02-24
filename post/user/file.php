<?php

/*
 * ITFlow - GET/POST request handler for client files/uploads
 */

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

if (isset($_POST['upload_files'])) {
    
    enforceUserPermission('module_support', 2);    

    $client_id = intval($_POST['client_id']);
    $folder_id = intval($_POST['folder_id']);
    $description = sanitizeInput($_POST['description']);

    if (!file_exists("uploads/clients/$client_id")) {
        mkdir("uploads/clients/$client_id");
    }

    for ($i = 0; $i < count($_FILES['file']['name']); $i++) {
        // Extract file details for this iteration
        $single_file = [
            'name' => $_FILES['file']['name'][$i],
            'type' => $_FILES['file']['type'][$i],
            'tmp_name' => $_FILES['file']['tmp_name'][$i],
            'error' => $_FILES['file']['error'][$i],
            'size' => $_FILES['file']['size'][$i]
        ];

        if ($file_reference_name = checkFileUpload($single_file, array('jpg', 'jpeg', 'gif', 'png', 'webp', 'pdf', 'txt', 'md', 'doc', 'docx', 'odt', 'csv', 'xls', 'xlsx', 'ods', 'pptx', 'odp', 'zip', 'tar', 'gz', 'xml', 'msg', 'json', 'wav', 'mp3', 'ogg', 'mov', 'mp4', 'av1', 'ovpn', 'cfg', 'ps1', 'vsdx', 'drawio', 'pfx', 'pages', 'numbers', 'unf', 'key'))) {

            $file_tmp_path = $_FILES['file']['tmp_name'][$i];

            $file_name = sanitizeInput($_FILES['file']['name'][$i]);
            $extarr = explode('.', $_FILES['file']['name'][$i]);
            $file_extension = sanitizeInput(strtolower(end($extarr)));

            // Extract the file mime type and size
            $file_mime_type = sanitizeInput($single_file['type']);
            $file_size = intval($single_file['size']);

            // directory in which the uploaded file will be moved
            $upload_file_dir = "uploads/clients/$client_id/";
            $dest_path = $upload_file_dir . $file_reference_name;

            move_uploaded_file($file_tmp_path, $dest_path);

            // Extract .ext from reference file name to be used to store SHA256 hash
            $file_hash = strstr($file_reference_name, '.', true) ?: $file_reference_name;

            mysqli_query($mysqli,"INSERT INTO files SET file_reference_name = '$file_reference_name', file_name = '$file_name', file_description = '$description', file_ext = '$file_extension', file_hash = '$file_hash', file_mime_type = '$file_mime_type', file_size = $file_size, file_created_by = $session_user_id, file_folder_id = $folder_id, file_client_id = $client_id");

            $file_id = mysqli_insert_id($mysqli);

            // If the file is an image, create a thumbnail and an optimized preview image
            if (in_array($file_extension, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
                // Thumbnail dimensions
                $thumbnail_width = 200;
                $thumbnail_height = 200;

                // Optimized preview dimensions
                $preview_max_width = 1200;
                $preview_max_height = 1200;

                // Get original dimensions
                list($orig_width, $orig_height) = getimagesize($dest_path);

                // Create image resource from the original file
                switch ($file_extension) {
                    case 'jpg':
                    case 'jpeg':
                        $src_img = imagecreatefromjpeg($dest_path);
                        break;
                    case 'png':
                        $src_img = imagecreatefrompng($dest_path);
                        break;
                    case 'gif':
                        $src_img = imagecreatefromgif($dest_path);
                        break;
                    case 'webp':
                        $src_img = imagecreatefromwebp($dest_path);
                        break;
                }

                if ($src_img) {
                    // -------------------------
                    // CREATE THUMBNAIL
                    // -------------------------
                    $thumb_img = imagecreatetruecolor($thumbnail_width, $thumbnail_height);
                    imagecopyresampled($thumb_img, $src_img, 0, 0, 0, 0, 
                                       $thumbnail_width, $thumbnail_height, 
                                       $orig_width, $orig_height);

                    $thumbnail_file_name = 'thumbnail_' . $file_reference_name;
                    $thumb_path = $upload_file_dir . $thumbnail_file_name;

                    // Save thumbnail to disk
                    switch ($file_extension) {
                        case 'jpg':
                        case 'jpeg':
                            imagejpeg($thumb_img, $thumb_path, 80);
                            break;
                        case 'png':
                            imagepng($thumb_img, $thumb_path);
                            break;
                        case 'gif':
                            imagegif($thumb_img, $thumb_path);
                            break;
                        case 'webp':
                            imagewebp($thumb_img, $thumb_path);
                            break;
                    }

                    imagedestroy($thumb_img);

                    mysqli_query($mysqli,"UPDATE files SET file_has_thumbnail = 1 WHERE file_id = $file_id");

                    // -------------------------
                    // CREATE OPTIMIZED PREVIEW IMAGE
                    // -------------------------
                    $aspect_ratio = $orig_width / $orig_height;
                    if ($orig_width <= $preview_max_width && $orig_height <= $preview_max_height) {
                        $preview_new_width = $orig_width;
                        $preview_new_height = $orig_height;
                    } elseif ($aspect_ratio > 1) {
                        // Wider than tall
                        $preview_new_width = $preview_max_width;
                        $preview_new_height = (int)($preview_max_width / $aspect_ratio);
                    } else {
                        // Taller or square
                        $preview_new_height = $preview_max_height;
                        $preview_new_width = (int)($preview_max_height * $aspect_ratio);
                    }

                    $preview_img = imagecreatetruecolor($preview_new_width, $preview_new_height);
                    imagecopyresampled($preview_img, $src_img, 0, 0, 0, 0,
                                       $preview_new_width, $preview_new_height,
                                       $orig_width, $orig_height);

                    $preview_file_name = 'preview_' . $file_reference_name;
                    $preview_path = $upload_file_dir . $preview_file_name;

                    switch ($file_extension) {
                        case 'jpg':
                        case 'jpeg':
                            // Lower quality for optimization (70 is example)
                            imagejpeg($preview_img, $preview_path, 70);
                            break;
                        case 'png':
                            // Higher compression level (0-9), 7 is an example
                            imagepng($preview_img, $preview_path, 7);
                            break;
                        case 'gif':
                            imagegif($preview_img, $preview_path);
                            break;
                        case 'webp':
                            imagewebp($preview_img, $preview_path, 70);
                            break;
                    }

                    imagedestroy($preview_img);
                    imagedestroy($src_img);

                    mysqli_query($mysqli,"UPDATE files SET file_has_preview = 1 WHERE file_id = $file_id");
                }
            }

            // Logging
            logAction("File", "Upload", "$session_name uploaded file $file_name", $client_id, $file_id);

            $_SESSION['alert_message'] = "Uploaded file <strong>$file_name</strong>";
        } else {
            $_SESSION['alert_type'] = 'error';
            $_SESSION['alert_message'] = 'There was an error moving the file to upload directory. Please make sure the upload directory is writable by web server.';
        }
    }
    // Redirect at the end, after processing all files
    header("Location: " . $_SERVER["HTTP_REFERER"]);
}

if (isset($_POST['rename_file'])) {

    enforceUserPermission('module_support', 2);

    $file_id = intval($_POST['file_id']);
    $file_name = sanitizeInput($_POST['file_name']);
    $file_description = sanitizeInput($_POST['file_description']);

    // Get File Details Client ID for Logging
    $sql = mysqli_query($mysqli,"SELECT file_name, file_client_id FROM files WHERE file_id = $file_id");
    $row = mysqli_fetch_array($sql);
    $old_file_name = sanitizeInput($row['file_name']);
    $client_id = intval($row['file_client_id']);

    // file edit query
    mysqli_query($mysqli,"UPDATE files SET file_name = '$file_name' ,file_description = '$file_description' WHERE file_id = $file_id");

    // Logging
    logAction("File", "Rename", "$session_name renamed file $old_file_name to $file_name", $client_id, $file_id);

    $_SESSION['alert_message'] = "Renamed file <strong>$old_file_name</strong> to <strong>$file_name</strong>";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_POST['move_file'])) {

    enforceUserPermission('module_support', 2);

    $file_id = intval($_POST['file_id']);
    $folder_id = intval($_POST['folder_id']);

    // Get File Name and  Client ID for Logging
    $sql = mysqli_query($mysqli,"SELECT file_name, file_client_id FROM files WHERE file_id = $file_id");
    $row = mysqli_fetch_array($sql);
    $file_name = sanitizeInput($row['file_name']);
    $client_id = intval($row['file_client_id']);

    // Get Folder Name for Logging
    $sql = mysqli_query($mysqli,"SELECT folder_name FROM folders WHERE folder_id = $folder_id");
    $row = mysqli_fetch_array($sql);
    $folder_name = sanitizeInput($row['folder_name']);

    mysqli_query($mysqli,"UPDATE files SET file_folder_id = $folder_id WHERE file_id = $file_id");

    // Logging
    logAction("File", "Move", "$session_name moved file $file_name to $folder_name", $client_id, $file_id);

    $_SESSION['alert_message'] = "File <strong>$file_name</strong> moved to <strong>$folder_name</strong>";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_GET['archive_file'])) {

    enforceUserPermission('module_support', 2);

    $file_id = intval($_GET['archive_file']);

    // Get Contact Name and Client ID for logging and alert message
    $sql = mysqli_query($mysqli,"SELECT file_name, file_client_id FROM files WHERE file_id = $file_id");
    $row = mysqli_fetch_array($sql);
    $file_name = sanitizeInput($row['file_name']);
    $client_id = intval($row['file_client_id']);

    mysqli_query($mysqli,"UPDATE files SET file_archived_at = NOW() WHERE file_id = $file_id");

    //logging
    logAction("File", "Archive", "$session_name archived file $file_name", $client_id, $file_id);

    $_SESSION['alert_type'] = "error";
    $_SESSION['alert_message'] = "File <strong>$file_name</strong> archived";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_POST['delete_file'])) {

    enforceUserPermission('module_support', 3);
    validateCSRFToken($_POST['csrf_token']);

    $file_id = intval($_POST['file_id']);

    $sql_file = mysqli_query($mysqli,"SELECT * FROM files WHERE file_id = $file_id");
    $row = mysqli_fetch_array($sql_file);
    $client_id = intval($row['file_client_id']);
    $file_name = sanitizeInput($row['file_name']);
    $file_reference_name = sanitizeInput($row['file_reference_name']);
    $file_has_thumbnail = intval($row['file_has_thumbnail']);
    $file_has_preview = intval($row['file_has_preview']);
    
    unlink("uploads/clients/$client_id/$file_reference_name");

    if ($file_has_thumbnail == 1) {
        unlink("uploads/clients/$client_id/thumbnail_$file_reference_name");
    }
    if ($file_has_preview == 1) {
        unlink("uploads/clients/$client_id/preview_$file_reference_name");
    }

    mysqli_query($mysqli,"DELETE FROM files WHERE file_id = $file_id");

    mysqli_query($mysqli,"DELETE FROM quote_files WHERE file_id = $file_id");

    //Logging
    logAction("File", "Delete", "$session_name deleted file $file_name", $client_id);

    $_SESSION['alert_type'] = "error";
    $_SESSION['alert_message'] = "File <strong>$file_name</strong> deleted";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_POST['bulk_delete_files'])) {

    enforceUserPermission('module_support', 3);
    validateCSRFToken($_POST['csrf_token']);

    // Delete file loop
    if (isset($_POST['file_ids'])) {

        // Get selected file Count
        $file_count = count($_POST['file_ids']);
        
        foreach($_POST['file_ids'] as $file_id) {

            $file_id = intval($file_id);

            $sql_file = mysqli_query($mysqli,"SELECT * FROM files WHERE file_id = $file_id");
            $row = mysqli_fetch_array($sql_file);
            $client_id = intval($row['file_client_id']);
            $file_name = sanitizeInput($row['file_name']);
            $file_reference_name = sanitizeInput($row['file_reference_name']);
            $file_has_thumbnail = intval($row['file_has_thumbnail']);
            $file_has_preview = intval($row['file_has_preview']);

            unlink("uploads/clients/$client_id/$file_reference_name");

            if ($file_has_thumbnail == 1) {
                unlink("uploads/clients/$client_id/thumbnail_$file_reference_name");
            }
            if ($file_has_preview == 1) {
                unlink("uploads/clients/$client_id/preview_$file_reference_name");
            }

            mysqli_query($mysqli,"DELETE FROM files WHERE file_id = $file_id");

            // Log each invidual file deletion
            logAction("File", "Delete", "$session_name deleted file $file_name", $client_id);
        }

        // Log the bulk delete action
        logAction("File", "Bulk Delete", "$session_name deleted $file_count file(s)", $client_id);

        $_SESSION['alert_type'] = "error";
        $_SESSION['alert_message'] = "You deleted <strong>$file_count</strong> files";
    }

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_POST['bulk_move_files'])) {

    enforceUserPermission('module_support', 2);
    validateCSRFToken($_POST['csrf_token']);

    $folder_id = intval($_POST['bulk_folder_id']);

    // Get folder name for logging and Notification
    $sql = mysqli_query($mysqli,"SELECT folder_name, folder_client_id FROM folders WHERE folder_id = $folder_id");
    $row = mysqli_fetch_array($sql);
    $folder_name = sanitizeInput($row['folder_name']);
    $client_id = intval($row['folder_client_id']);

    // Check array for data
    if (isset($_POST['file_ids'])) {
        // Get Selected file Count
        $file_count = count($_POST['file_ids']);
        
        // Move Documents to Folder Loop
        foreach($_POST['file_ids'] as $file_id) {
            $file_id = intval($file_id);
            // Get file name for logging
            $sql = mysqli_query($mysqli,"SELECT file_name FROM files WHERE file_id = $file_id");
            $row = mysqli_fetch_array($sql);
            $file_name = sanitizeInput($row['file_name']);

            // file move query
            mysqli_query($mysqli,"UPDATE files SET file_folder_id = $folder_id WHERE file_id = $file_id");

            // Logging
            logAction("File", "Move", "$session_name moved file $file_name to folder $folder_name", $client_id, $file_id);
        }

        //Logging
        logAction("File", "Bulk Move", "$session_name moved $file_count file(s) to folder $folder_name", $client_id);

        $_SESSION['alert_message'] = "Moved <strong>$file_count</strong> files to the folder <strong>$folder_name</strong>";
    }

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_POST['link_asset_to_file'])) {

    enforceUserPermission('module_support', 2);

    $file_id = intval($_POST['file_id']);
    $asset_id = intval($_POST['asset_id']);

    // Get File Name and  Client ID for Logging
    $sql = mysqli_query($mysqli,"SELECT file_name, file_client_id FROM files WHERE file_id = $file_id");
    $row = mysqli_fetch_array($sql);
    $file_name = sanitizeInput($row['file_name']);
    $client_id = intval($row['file_client_id']);

    // Get Asset Name for Logging
    $sql = mysqli_query($mysqli,"SELECT asset_name FROM assets WHERE asset_id = $asset_id");
    $row = mysqli_fetch_array($sql);
    $asset_name = sanitizeInput($row['asset_name']);

    // Contact add query
    mysqli_query($mysqli,"INSERT INTO asset_files SET asset_id = $asset_id, file_id = $file_id");

    // Logging
    logAction("File", "Link", "$session_name linked asset $asset_name to file $file_name", $client_id, $file_id);

    $_SESSION['alert_message'] = "Asset <strong>$asset_name</strong> linked to File <strong>$file_name</strong>";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_GET['unlink_asset_from_file'])) {

    enforceUserPermission('module_support', 2);

    $asset_id = intval($_GET['asset_id']);
    $file_id = intval($_GET['file_id']);

    // Get File Name and  Client ID for Logging
    $sql = mysqli_query($mysqli,"SELECT file_name, file_client_id FROM files WHERE file_id = $file_id");
    $row = mysqli_fetch_array($sql);
    $file_name = sanitizeInput($row['file_name']);
    $client_id = intval($row['file_client_id']);

    // Get Asset Name for Logging
    $sql = mysqli_query($mysqli,"SELECT asset_name FROM assets WHERE asset_id = $asset_id");
    $row = mysqli_fetch_array($sql);
    $asset_name = sanitizeInput($row['asset_name']);

    mysqli_query($mysqli,"DELETE FROM asset_files WHERE asset_id = $asset_id AND file_id = $file_id");

    //Logging
    logAction("File", "Link", "$session_name unlinked asset $asset_name from file $file_name", $client_id, $file_id);

    $_SESSION['alert_message'] = "Asset <strong>$asset_name</strong> unlinked from File <strong>$file_name</strong>";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}
