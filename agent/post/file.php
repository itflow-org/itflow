<?php

/*
 * ITFlow - GET/POST request handler for client files/uploads
 */

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

if (isset($_POST['upload_files'])) {

    validateCSRFToken($_POST['csrf_token']);

    enforceUserPermission('module_support', 2);

    // Sanitize and initialize inputs
    $client_id   = intval($_POST['client_id']);
    $folder_id   = intval($_POST['folder_id']);
    $description = escapeSql($_POST['description']);
    $contact_id = intval($_POST['contact_id'] ?? 0);
    $asset_id = intval($_POST['asset_id'] ?? 0);
    $client_dir  = "../uploads/clients/$client_id";

    enforceClientAccess();

    // Create client directory if it doesn't exist
    if (!is_dir($client_dir)) {
        mkdir($client_dir, 0755, true);
    }

    // Allowed file extensions list
    $allowedExtensions = [
        'jpg', 'jpeg', 'gif', 'png', 'webp', 'pdf', 'txt', 'md', 'doc', 'docx',
        'odt', 'csv', 'xls', 'xlsx', 'ods', 'pptx', 'odp', 'zip', 'tar', 'gz',
        'msg', 'json', 'wav', 'mp3', 'ogg', 'mov', 'mp4', 'av1', 'ovpn',
        'cfg', 'ps1', 'vsdx', 'drawio', 'pfx', 'pages', 'numbers', 'unf', 'unifi',
        'key', 'bat', 'stk', 'swb'
    ];

    // Loop through each uploaded file
    foreach ($_FILES['file']['name'] as $index => $originalName) {

        // Build a file array for this iteration
        $single_file = [
            'name'     => $_FILES['file']['name'][$index],
            'type'     => $_FILES['file']['type'][$index],
            'tmp_name' => $_FILES['file']['tmp_name'][$index],
            'error'    => $_FILES['file']['error'][$index],
            'size'     => $_FILES['file']['size'][$index]
        ];

        // Validate and get a safe file reference name
        if ($file_reference_name = checkFileUpload($single_file, $allowedExtensions)) {

            $file_tmp_path   = $single_file['tmp_name'];
            $file_name       = escapeSql($originalName);
            $extParts        = explode('.', $file_name);
            $file_extension  = strtolower(end($extParts));
            $file_mime_type  = escapeSql($single_file['type']);
            $file_size       = intval($single_file['size']);

            // Define destination path and move the uploaded file
            $upload_file_dir = $client_dir . "/";
            $dest_path       = $upload_file_dir . $file_reference_name;

            if (!move_uploaded_file($file_tmp_path, $dest_path)) {
                flash_alert('Error moving file to upload directory. Please ensure the directory is writable.', 'error');
                continue; // Skip processing this file
            }

            // Use the file reference (without extension) as the file hash
            $file_hash = strstr($file_reference_name, '.', true) ?: $file_reference_name;

            // Insert file metadata into the database
            $query = "INSERT INTO files SET
                        file_reference_name = '$file_reference_name',
                        file_name = '$file_name',
                        file_description = '$description',
                        file_ext = '$file_extension',
                        file_mime_type = '$file_mime_type',
                        file_size = $file_size,
                        file_created_by = $session_user_id,
                        file_folder_id = $folder_id,
                        file_client_id = $client_id";
            mysqli_query($mysqli, $query);
            $file_id = mysqli_insert_id($mysqli);

            if ($contact_id) {
                mysqli_query($mysqli,"INSERT INTO contact_files SET contact_id = $contact_id, file_id = $file_id");
            }

            if ($asset_id) {
                mysqli_query($mysqli,"INSERT INTO asset_files SET asset_id = $asset_id, file_id = $file_id");
            }

            logAction("File", "Upload", "$session_name uploaded file $file_name", $client_id, $file_id);

            flash_alert("Uploaded file <strong>$file_name</strong>");
        }
    }

    redirect();

}


if (isset($_POST['rename_file'])) {

    validateCSRFToken($_POST['csrf_token']);

    enforceUserPermission('module_support', 2);

    $file_id = intval($_POST['file_id']);
    $file_name = escapeSql($_POST['file_name']);
    $file_description = escapeSql($_POST['file_description']);

    // Get File Details Client ID for Logging
    $sql = mysqli_query($mysqli,"SELECT file_name, file_client_id FROM files WHERE file_id = $file_id");
    $row = mysqli_fetch_assoc($sql);
    $old_file_name = escapeSql($row['file_name']);
    $client_id = intval($row['file_client_id']);

    enforceClientAccess();

    // file edit query
    mysqli_query($mysqli,"UPDATE files SET file_name = '$file_name' ,file_description = '$file_description' WHERE file_id = $file_id");

    logAction("File", "Rename", "$session_name renamed file $old_file_name to $file_name", $client_id, $file_id);

    flash_alert("Renamed file <strong>$old_file_name</strong> to <strong>$file_name</strong>");

    redirect();

}

if (isset($_POST['move_file'])) {

    validateCSRFToken($_POST['csrf_token']);

    enforceUserPermission('module_support', 2);

    $file_id = intval($_POST['file_id']);
    $folder_id = intval($_POST['folder_id']);

    // Get File Name and  Client ID for Logging
    $sql = mysqli_query($mysqli,"SELECT file_name, file_client_id FROM files WHERE file_id = $file_id");
    $row = mysqli_fetch_assoc($sql);
    $file_name = escapeSql($row['file_name']);
    $client_id = intval($row['file_client_id']);

    enforceClientAccess();

    // Get Folder Name for Logging
    $folder_name = escapeSql(getFieldById('folders', $folder_id, 'folder_name'));

    mysqli_query($mysqli,"UPDATE files SET file_folder_id = $folder_id WHERE file_id = $file_id");

    logAction("File", "Move", "$session_name moved file $file_name to $folder_name", $client_id, $file_id);

    flash_alert("File <strong>$file_name</strong> moved to <strong>$folder_name</strong>");

    redirect();

}

if (isset($_GET['archive_file'])) {

    validateCSRFToken($_GET['csrf_token']);

    enforceUserPermission('module_support', 2);

    $file_id = intval($_GET['archive_file']);

    // Get Contact Name and Client ID for logging and alert message
    $sql = mysqli_query($mysqli,"SELECT file_name, file_client_id FROM files WHERE file_id = $file_id");
    $row = mysqli_fetch_assoc($sql);
    $file_name = escapeSql($row['file_name']);
    $client_id = intval($row['file_client_id']);

    enforceClientAccess();

    mysqli_query($mysqli,"UPDATE files SET file_archived_at = NOW() WHERE file_id = $file_id");

    logAction("File", "Archive", "$session_name archived file $file_name", $client_id, $file_id);

    flash_alert("File <strong>$file_name</strong> archived", 'error');

    redirect();

}

if (isset($_GET['restore_file'])) {

    validateCSRFToken($_GET['csrf_token']);

    enforceUserPermission('module_support', 2);

    $file_id = intval($_GET['restore_file']);

    // Get Document Name and Client ID for logging and alert message
    $sql = mysqli_query($mysqli,"SELECT file_name, file_client_id FROM files WHERE file_id = $file_id");
    $row = mysqli_fetch_assoc($sql);
    $file_name = escapeSql($row['file_name']);
    $client_id = intval($row['file_client_id']);

    enforceClientAccess();

    mysqli_query($mysqli,"UPDATE files SET file_archived_at = NULL WHERE file_id = $file_id");

    logAction("File", "Restore", "$session_name restored file $file_name", $client_id, $file_id);

    flash_alert("File <strong>$file_name</strong> Restored");

    redirect();

}

if (isset($_POST['delete_file'])) {

    validateCSRFToken($_POST['csrf_token']);

    enforceUserPermission('module_support', 3);

    $file_id = intval($_POST['file_id']);

    $sql_file = mysqli_query($mysqli,"SELECT * FROM files WHERE file_id = $file_id");
    $row = mysqli_fetch_assoc($sql_file);
    $client_id = intval($row['file_client_id']);
    $file_name = escapeSql($row['file_name']);
    $file_reference_name = escapeSql($row['file_reference_name']);
    $file_has_thumbnail = intval($row['file_has_thumbnail']);
    $file_has_preview = intval($row['file_has_preview']);

    enforceClientAccess();

    unlink("../uploads/clients/$client_id/$file_reference_name");

    if ($file_has_thumbnail == 1) {
        unlink("../uploads/clients/$client_id/thumbnail_$file_reference_name");
    }
    if ($file_has_preview == 1) {
        unlink("../uploads/clients/$client_id/preview_$file_reference_name");
    }

    mysqli_query($mysqli,"DELETE FROM files WHERE file_id = $file_id");

    logAction("File", "Delete", "$session_name deleted file $file_name", $client_id);

    flash_alert("File <strong>$file_name</strong> deleted", 'alert');

    redirect();

}

if (isset($_POST['bulk_archive_files'])) {

    validateCSRFToken($_POST['csrf_token']);

    enforceUserPermission('module_support', 2);

    // Archive file loop
    if (isset($_POST['file_ids'])) {

        // Get selected file Count
        $file_count = count($_POST['file_ids']);

        foreach($_POST['file_ids'] as $file_id) {

            $file_id = intval($file_id);

            $sql_file = mysqli_query($mysqli,"SELECT * FROM files WHERE file_id = $file_id");
            $row = mysqli_fetch_assoc($sql_file);
            $client_id = intval($row['file_client_id']);
            $file_name = escapeSql($row['file_name']);

            enforceClientAccess();

            mysqli_query($mysqli,"UPDATE files SET file_archived_at = NOW() WHERE file_id = $file_id");

            logAction("File", "Archive", "$session_name archived file $file_name", $client_id, $file_id);
        }

    }

    // Archive documents loop
    if (isset($_POST['document_ids'])) {

        // Get selected document count
        $document_count = count($_POST['document_ids']);

        // Delete document loop
        foreach($_POST['document_ids'] as $document_id) {
            $document_id = intval($document_id);
            // Get document name for logging
            $sql = mysqli_query($mysqli,"SELECT document_name, document_client_id FROM documents WHERE document_id = $document_id");
            $row = mysqli_fetch_assoc($sql);
            $document_name = escapeSql($row['document_name']);
            $client_id = intval($row['document_client_id']);

            enforceClientAccess();

            mysqli_query($mysqli,"UPDATE documents SET document_archived_at = NOW(), document_updated_at = document_updated_at WHERE document_id = $document_id");

            logAction("Document", "Archive", "$session_name archived document $document_name", $client_id, $document_id);

        }

    }

    logAction("File", "Bulk Archive", "$session_name archived $document_count document(s) and $file_count file(s)", $client_id);

    flash_alert("Archived <strong>$document_count</strong> Documents and <strong>$file_count</strong> files", 'error');

    redirect();

}

if (isset($_POST['bulk_delete_files'])) {

    validateCSRFToken($_POST['csrf_token']);

    enforceUserPermission('module_support', 3);

    // Delete file loop
    if (isset($_POST['file_ids'])) {

        // Get selected file Count
        $file_count = count($_POST['file_ids']);

        foreach($_POST['file_ids'] as $file_id) {

            $file_id = intval($file_id);

            $sql_file = mysqli_query($mysqli,"SELECT * FROM files WHERE file_id = $file_id");
            $row = mysqli_fetch_assoc($sql_file);
            $client_id = intval($row['file_client_id']);
            $file_name = escapeSql($row['file_name']);
            $file_reference_name = escapeSql($row['file_reference_name']);
            $file_has_thumbnail = intval($row['file_has_thumbnail']);
            $file_has_preview = intval($row['file_has_preview']);

            enforceClientAccess();

            unlink("../uploads/clients/$client_id/$file_reference_name");

            if ($file_has_thumbnail == 1) {
                unlink("../uploads/clients/$client_id/thumbnail_$file_reference_name");
            }
            if ($file_has_preview == 1) {
                unlink("../uploads/clients/$client_id/preview_$file_reference_name");
            }

            mysqli_query($mysqli,"DELETE FROM files WHERE file_id = $file_id");

            logAction("File", "Delete", "$session_name deleted file $file_name", $client_id);
        }

    }

    // Delete documents loop
    if (isset($_POST['document_ids'])) {

        // Get selected document count
        $document_count = count($_POST['document_ids']);

        // Delete document loop
        foreach($_POST['document_ids'] as $document_id) {
            $document_id = intval($document_id);
            // Get Document Name and Client ID for logging
            $sql = mysqli_query($mysqli,"SELECT document_name, document_client_id FROM documents WHERE document_id = $document_id");
            $row = mysqli_fetch_assoc($sql);
            $client_id = intval($row['document_client_id']);
            $document_name = escapeSql($row['document_name']);

            enforceClientAccess();

            mysqli_query($mysqli,"DELETE FROM documents WHERE document_id = $document_id");

            // Delete all versions associated with the master document
            mysqli_query($mysqli,"DELETE FROM document_versions WHERE document_version_document_id = $document_id");

            // Delete uploads/document/$document_id if exists
            removeDirectory($_SERVER['DOCUMENT_ROOT'] . "/uploads/documents/" . $document_id);

            logAction("Document", "Delete", "$session_name deleted document $document_name and all versions", $client_id);

        }

    }

    logAction("File", "Bulk Delete", "$session_name deleted $document_count document(s) and $file_count file(s)", $client_id);

    flash_alert("Deleted <strong>$document_count</strong> Documents and <strong>$file_count</strong> files", 'error');

    redirect();

}

if (isset($_POST['bulk_restore_files'])) {

    validateCSRFToken($_POST['csrf_token']);

    enforceUserPermission('module_support', 2);

    // Restore file loop
    if (isset($_POST['file_ids'])) {

        // Get selected file Count
        $file_count = count($_POST['file_ids']);

        foreach($_POST['file_ids'] as $file_id) {

            $file_id = intval($file_id);

            $sql_file = mysqli_query($mysqli,"SELECT * FROM files WHERE file_id = $file_id");
            $row = mysqli_fetch_assoc($sql_file);
            $client_id = intval($row['file_client_id']);
            $file_name = escapeSql($row['file_name']);

            enforceClientAccess();

            mysqli_query($mysqli,"UPDATE files SET file_archived_at = NULL WHERE file_id = $file_id");

            logAction("File", "Restore", "$session_name restored file $file_name", $client_id, $file_id);
        }

    }

    // Restore documents loop
    if (isset($_POST['document_ids'])) {

        // Get selected document count
        $document_count = count($_POST['document_ids']);

        // Restore document loop
        foreach($_POST['document_ids'] as $document_id) {
            $document_id = intval($document_id);
            // Get document name for logging
            $sql = mysqli_query($mysqli,"SELECT document_name, document_client_id FROM documents WHERE document_id = $document_id");
            $row = mysqli_fetch_assoc($sql);
            $document_name = escapeSql($row['document_name']);
            $client_id = intval($row['document_client_id']);

            enforceClientAccess();

            mysqli_query($mysqli,"UPDATE documents SET document_archived_at = NULL, document_updated_at = document_updated_at WHERE document_id = $document_id");

            logAction("Document", "Restore", "$session_name restored document $document_name", $client_id, $document_id);

        }

    }

    logAction("File", "Bulk Restore", "$session_name restored $document_count document(s) and $file_count file(s)", $client_id);

    flash_alert("Restored <strong>$document_count</strong> Documents and <strong>$file_count</strong> files");

    redirect();

}

// Unified bulk move for Files + Documents
if (isset($_POST['bulk_move_files'])) {

    validateCSRFToken($_POST['csrf_token']);

    enforceUserPermission('module_support', 2);

    $folder_id = intval($_POST['bulk_folder_id']);

    // Default values (for root or missing folder)
    $folder_name    = "/";
    $log_client_id  = 0;

    // If moving into a real folder, get folder name + client for logging
    if ($folder_id > 0) {
        $sql = mysqli_query($mysqli,"SELECT folder_name, folder_client_id FROM folders WHERE folder_id = $folder_id");
        if ($row = mysqli_fetch_assoc($sql)) {
            $folder_name   = escapeSql($row['folder_name']);
            $log_client_id = intval($row['folder_client_id']);
        }
    }

    $file_count     = 0;
    $document_count = 0;

    // -------------------------
    // Move FILES (if any)
    // -------------------------
    if (!empty($_POST['file_ids']) && is_array($_POST['file_ids'])) {

        $file_ids = array_map('intval', $_POST['file_ids']);
        $file_count = count($file_ids);

        foreach ($file_ids as $file_id) {

            // Get file name for logging
            $file_name = escapeSql(getFieldById('files', $file_id, 'file_name'));
            $client_id = intval(getFieldById('files', $file_id, 'file_client_id'));

            enforceClientAccess();

            // Move file
            mysqli_query($mysqli,"UPDATE files SET file_folder_id = $folder_id WHERE file_id = $file_id");

            // Per-file log
            logAction(
                "File",
                "Move",
                "$session_name moved file $file_name to folder $folder_name",
                $log_client_id,
                $file_id
            );
        }

        // Bulk summary log for files
        logAction(
            "File",
            "Bulk Move",
            "$session_name moved $file_count file(s) to folder $folder_name",
            $log_client_id
        );
    }

    // -------------------------
    // Move DOCUMENTS (if any)
    // -------------------------
    if (!empty($_POST['document_ids']) && is_array($_POST['document_ids'])) {

        $document_ids = array_map('intval', $_POST['document_ids']);
        $document_count = count($document_ids);

        foreach ($document_ids as $document_id) {

            // Get document name for logging
            $document_name = escapeSql(getFieldById('documents', $document_id, 'document_name'));
            $client_id = intval(getFieldById('documents', $document_id, 'document_client_id'));

            enforceClientAccess();

            // Move document
            mysqli_query($mysqli,"UPDATE documents SET document_folder_id = $folder_id, document_updated_at = document_updated_at WHERE document_id = $document_id");

            // Per-document log
            logAction(
                "Document",
                "Move",
                "$session_name moved document $document_name to folder $folder_name",
                $log_client_id,
                $document_id
            );
        }

        // Bulk summary log for documents
        logAction(
            "Document",
            "Bulk Move",
            "$session_name moved $document_count document(s) to folder $folder_name",
            $log_client_id
        );
    }

    // -------------------------
    // Flash message
    // -------------------------
    if ($file_count && $document_count) {
        flash_alert("Moved <strong>$file_count</strong> file(s) and <strong>$document_count</strong> document(s) to the folder <strong>$folder_name</strong>");
    } elseif ($file_count) {
        flash_alert("Moved <strong>$file_count</strong> file(s) to the folder <strong>$folder_name</strong>");
    } elseif ($document_count) {
        flash_alert("Moved <strong>$document_count</strong> document(s) to the folder <strong>$folder_name</strong>");
    } else {
        flash_alert("No items were moved.");
    }

    redirect();
}


if (isset($_POST['link_asset_to_file'])) {

    validateCSRFToken($_POST['csrf_token']);

    enforceUserPermission('module_support', 2);

    $file_id = intval($_POST['file_id']);
    $asset_id = intval($_POST['asset_id']);

    // Get File Name and  Client ID for Logging
    $sql = mysqli_query($mysqli,"SELECT file_name, file_client_id FROM files WHERE file_id = $file_id");
    $row = mysqli_fetch_assoc($sql);
    $file_name = escapeSql($row['file_name']);
    $client_id = intval($row['file_client_id']);

    enforceClientAccess();

    // Get Asset Name for Logging
    $asset_name = escapeSql(getFieldById('assets', $asset_id, 'asset_name'));

    // Contact add query
    mysqli_query($mysqli,"INSERT INTO asset_files SET asset_id = $asset_id, file_id = $file_id");

    logAction("File", "Link", "$session_name linked asset $asset_name to file $file_name", $client_id, $file_id);

    flash_alert("Asset <strong>$asset_name</strong> linked to File <strong>$file_name</strong>");

    redirect();

}

if (isset($_GET['unlink_asset_from_file'])) {

    validateCSRFToken($_GET['csrf_token']);

    enforceUserPermission('module_support', 2);

    $asset_id = intval($_GET['asset_id']);
    $file_id = intval($_GET['file_id']);

    // Get File Name and  Client ID for Logging
    $sql = mysqli_query($mysqli,"SELECT file_name, file_client_id FROM files WHERE file_id = $file_id");
    $row = mysqli_fetch_assoc($sql);
    $file_name = escapeSql($row['file_name']);
    $client_id = intval($row['file_client_id']);

    enforceClientAccess();

    // Get Asset Name for Logging
    $asset_name = escapeSql(getFieldById('assets', $asset_id, 'asset_name'));

    mysqli_query($mysqli,"DELETE FROM asset_files WHERE asset_id = $asset_id AND file_id = $file_id");

    logAction("File", "Link", "$session_name unlinked asset $asset_name from file $file_name", $client_id, $file_id);

    flash_alert("Asset <strong>$asset_name</strong> unlinked from File <strong>$file_name</strong>");

    redirect();

}
