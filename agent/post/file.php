<?php

/*
 * ITFlow - GET/POST request handler for client files/uploads
 *
 * Linking files to assets, contacts and documents lives in post/link.php.
 */

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

/** "2 documents and 3 files" - for the bulk messages. */
function filesCountPhrase($document_count, $file_count) {
    return implode(' and ', array_filter([
        $document_count ? "<strong>$document_count</strong> document" . ($document_count === 1 ? '' : 's') : '',
        $file_count ? "<strong>$file_count</strong> file" . ($file_count === 1 ? '' : 's') : '',
    ])) ?: 'nothing';
}

if (isset($_POST['upload_files'])) {

    validateCSRFToken();

    enforceUserPermission('module_support', 2);

    $client_id = intval($_POST['client_id']);
    $description = escapeSql($_POST['description'] ?? '');
    $client_dir = "../uploads/clients/$client_id";

    enforceClientAccess();

    // Only the client's own folder, contact, asset, license, location and vendor
    $folder_id = folderOwnedId($_POST['folder_id'] ?? 0, $client_id);
    $contact_id = assetClientOwnedId('contact', $_POST['contact_id'] ?? 0, $client_id);
    $asset = linkRecord('asset', $_POST['asset_id'] ?? 0);
    $asset_id = $asset && intval($asset['client_id']) === $client_id ? intval($asset['id']) : 0;
    $software = linkRecord('software', $_POST['software_id'] ?? 0);
    $software_id = $software && intval($software['client_id']) === $client_id ? intval($software['id']) : 0;
    $location_id = assetClientOwnedId('location', $_POST['location_id'] ?? 0, $client_id);
    $vendor_id = assetClientOwnedId('vendor', $_POST['vendor_id'] ?? 0, $client_id);

    if (!is_dir($client_dir)) {
        mkdir($client_dir, 0755, true);
    }

    $allowedExtensions = [
        'jpg', 'jpeg', 'gif', 'png', 'webp', 'pdf', 'txt', 'md', 'doc', 'docx',
        'odt', 'csv', 'xls', 'xlsx', 'ods', 'pptx', 'odp', 'zip', 'tar', 'gz',
        'msg', 'json', 'wav', 'mp3', 'ogg', 'mov', 'mp4', 'av1', 'ovpn',
        'cfg', 'ps1', 'vsdx', 'drawio', 'pfx', 'pages', 'numbers', 'unf', 'unifi',
        'key', 'bat', 'stk', 'swb'
    ];

    $uploaded = [];
    $skipped = [];
    $names = $_FILES['file']['name'] ?? [];

    foreach ((array) $names as $index => $original_name) {

        $single_file = [
            'name' => $_FILES['file']['name'][$index],
            'type' => $_FILES['file']['type'][$index],
            'tmp_name' => $_FILES['file']['tmp_name'][$index],
            'error' => $_FILES['file']['error'][$index],
            'size' => $_FILES['file']['size'][$index]
        ];

        $file_reference_name = checkFileUpload($single_file, $allowedExtensions);
        if (!$file_reference_name) {
            $skipped[] = $original_name;
            continue;
        }

        $dest_path = "$client_dir/$file_reference_name";
        if (!move_uploaded_file($single_file['tmp_name'], $dest_path)) {
            $skipped[] = $original_name;
            continue;
        }

        $file_name = escapeSql(basename($original_name));
        $file_extension = escapeSql(strtolower(pathinfo($original_name, PATHINFO_EXTENSION)));
        // What the file is, read from the file - not what the browser said it was
        $detected_mime = function_exists('finfo_open') ? (string) finfo_file(finfo_open(FILEINFO_MIME_TYPE), $dest_path) : '';
        $file_mime_type = escapeSql($detected_mime !== '' && $detected_mime !== 'application/octet-stream' ? $detected_mime : $single_file['type']);
        $file_size = intval($single_file['size']);

        mysqli_query($mysqli, "INSERT INTO files SET
            file_reference_name = '$file_reference_name',
            file_name = '$file_name',
            file_description = '$description',
            file_ext = '$file_extension',
            file_mime_type = '$file_mime_type',
            file_size = $file_size,
            file_created_by = $session_user_id,
            file_folder_id = $folder_id,
            file_client_id = $client_id");
        $file_id = mysqli_insert_id($mysqli);

        if ($contact_id) {
            mysqli_query($mysqli, "INSERT IGNORE INTO contact_files SET contact_id = $contact_id, file_id = $file_id");
        }
        if ($asset_id) {
            mysqli_query($mysqli, "INSERT IGNORE INTO asset_files SET asset_id = $asset_id, file_id = $file_id");
        }
        if ($software_id) {
            mysqli_query($mysqli, "INSERT IGNORE INTO software_files SET software_id = $software_id, file_id = $file_id");
        }
        if ($location_id) {
            mysqli_query($mysqli, "INSERT IGNORE INTO location_files SET location_id = $location_id, file_id = $file_id");
        }
        if ($vendor_id) {
            mysqli_query($mysqli, "INSERT IGNORE INTO vendor_files SET vendor_id = $vendor_id, file_id = $file_id");
        }

        logAudit("File", "Upload", "$session_name uploaded file $file_name", $client_id, $file_id);

        $uploaded[] = basename($original_name);
    }

    if ($uploaded && !$skipped) {
        flashAlert(count($uploaded) === 1 ? "Uploaded <strong>{$uploaded[0]}</strong>" : "Uploaded <strong>" . count($uploaded) . "</strong> files");
    } elseif ($uploaded) {
        flashAlert("Uploaded <strong>" . count($uploaded) . "</strong>, skipped <strong>" . count($skipped) . "</strong> (not an allowed type, empty or too big): " . implode(', ', $skipped), 'warning');
    } else {
        flashAlert($skipped ? "Nothing uploaded - not an allowed type, empty or too big: " . implode(', ', $skipped) : 'Choose a file to upload.', 'error');
    }

    redirect();

}

if (isset($_POST['rename_file'])) {

    validateCSRFToken();

    enforceUserPermission('module_support', 2);

    $file_id = intval($_POST['file_id']);
    $file_name = escapeSql(trim($_POST['file_name'] ?? ''));
    $file_description = escapeSql($_POST['file_description'] ?? '');

    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT file_name, file_client_id FROM files WHERE file_id = $file_id"));
    if (!$row) {
        flashAlert('That file no longer exists.', 'error');
        redirect();
    }
    $old_file_name = escapeSql($row['file_name']);
    $client_id = intval($row['file_client_id']);

    enforceClientAccess();

    if ($file_name === '') {
        flashAlert('A file needs a name.', 'error');
        redirect();
    }

    mysqli_query($mysqli, "UPDATE files SET file_name = '$file_name', file_description = '$file_description' WHERE file_id = $file_id");

    logAudit("File", "Rename", "$session_name renamed file $old_file_name to $file_name", $client_id, $file_id);

    flashAlert("File <strong>" . trim($_POST['file_name']) . "</strong> saved");

    redirect();

}

if (isset($_POST['move_file'])) {

    validateCSRFToken();

    enforceUserPermission('module_support', 2);

    $file_id = intval($_POST['file_id']);

    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT file_name, file_client_id FROM files WHERE file_id = $file_id"));
    if (!$row) {
        flashAlert('That file no longer exists.', 'error');
        redirect();
    }
    $file_name = escapeSql($row['file_name']);
    $client_id = intval($row['file_client_id']);

    enforceClientAccess();

    // Only into one of this client's folders
    $folder_id = folderOwnedId($_POST['folder_id'] ?? 0, $client_id);
    $folder_name = folderDisplayName($folder_id);

    mysqli_query($mysqli, "UPDATE files SET file_folder_id = $folder_id WHERE file_id = $file_id");

    logAudit("File", "Move", "$session_name moved file $file_name to " . escapeSql($folder_name), $client_id, $file_id);

    flashAlert("File <strong>{$row['file_name']}</strong> moved to <strong>$folder_name</strong>");

    redirect();

}

if (isset($_GET['archive_file'])) {

    validateCSRFToken();

    enforceUserPermission('module_support', 2);

    $file_id = intval($_GET['archive_file']);

    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT file_name, file_client_id FROM files WHERE file_id = $file_id"));
    if (!$row) {
        flashAlert('That file no longer exists.', 'error');
        redirect();
    }
    $client_id = intval($row['file_client_id']);

    enforceClientAccess();

    mysqli_query($mysqli, "UPDATE files SET file_archived_at = NOW() WHERE file_id = $file_id");

    logAudit("File", "Archive", "$session_name archived file " . escapeSql($row['file_name']), $client_id, $file_id);

    flashAlert("File <strong>{$row['file_name']}</strong> archived", 'error');

    redirect();

}

if (isset($_GET['restore_file'])) {

    validateCSRFToken();

    enforceUserPermission('module_support', 2);

    $file_id = intval($_GET['restore_file']);

    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT file_name, file_client_id FROM files WHERE file_id = $file_id"));
    if (!$row) {
        flashAlert('That file no longer exists.', 'error');
        redirect();
    }
    $client_id = intval($row['file_client_id']);

    enforceClientAccess();

    mysqli_query($mysqli, "UPDATE files SET file_archived_at = NULL WHERE file_id = $file_id");

    logAudit("File", "Restore", "$session_name restored file " . escapeSql($row['file_name']), $client_id, $file_id);

    flashAlert("File <strong>{$row['file_name']}</strong> restored");

    redirect();

}

if (isset($_POST['delete_file'])) {

    validateCSRFToken();

    enforceUserPermission('module_support', 3);

    $file_name = fileDeleteForGood(intval($_POST['file_id']));

    if ($file_name === null) {
        flashAlert('That file no longer exists.', 'error');
    } else {
        flashAlert("File <strong>$file_name</strong> deleted", 'error');
    }

    redirect();

}

/*
 * The bulk actions take files and documents together - the files list ticks
 * both - and each item is checked against the agent's client access on its
 * own, since nothing ties the ticked ids to one client.
 */

if (isset($_POST['bulk_archive_files'])) {

    validateCSRFToken();

    enforceUserPermission('module_support', 2);

    $file_count = 0;
    $document_count = 0;

    foreach (array_map('intval', (array) ($_POST['file_ids'] ?? [])) as $file_id) {
        $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT file_client_id, file_name FROM files WHERE file_id = $file_id AND file_archived_at IS NULL"));
        if (!$row) {
            continue;
        }
        $client_id = intval($row['file_client_id']);
        enforceClientAccess();
        mysqli_query($mysqli, "UPDATE files SET file_archived_at = NOW() WHERE file_id = $file_id");
        logAudit("File", "Archive", "$session_name archived file " . escapeSql($row['file_name']), $client_id, $file_id);
        $file_count++;
    }

    foreach (array_map('intval', (array) ($_POST['document_ids'] ?? [])) as $document_id) {
        $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT document_name, document_client_id FROM documents WHERE document_id = $document_id AND document_archived_at IS NULL"));
        if (!$row) {
            continue;
        }
        $client_id = intval($row['document_client_id']);
        enforceClientAccess();
        mysqli_query($mysqli, "UPDATE documents SET document_archived_at = NOW(), document_updated_at = document_updated_at WHERE document_id = $document_id");
        logAudit("Document", "Archive", "$session_name archived document " . escapeSql($row['document_name']), $client_id, $document_id);
        $document_count++;
    }

    flashAlert("Archived " . filesCountPhrase($document_count, $file_count), 'error');

    redirect();

}

if (isset($_POST['bulk_restore_files'])) {

    validateCSRFToken();

    enforceUserPermission('module_support', 2);

    $file_count = 0;
    $document_count = 0;

    foreach (array_map('intval', (array) ($_POST['file_ids'] ?? [])) as $file_id) {
        $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT file_client_id, file_name FROM files WHERE file_id = $file_id AND file_archived_at IS NOT NULL"));
        if (!$row) {
            continue;
        }
        $client_id = intval($row['file_client_id']);
        enforceClientAccess();
        mysqli_query($mysqli, "UPDATE files SET file_archived_at = NULL WHERE file_id = $file_id");
        logAudit("File", "Restore", "$session_name restored file " . escapeSql($row['file_name']), $client_id, $file_id);
        $file_count++;
    }

    foreach (array_map('intval', (array) ($_POST['document_ids'] ?? [])) as $document_id) {
        $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT document_name, document_client_id FROM documents WHERE document_id = $document_id AND document_archived_at IS NOT NULL"));
        if (!$row) {
            continue;
        }
        $client_id = intval($row['document_client_id']);
        enforceClientAccess();
        mysqli_query($mysqli, "UPDATE documents SET document_archived_at = NULL, document_updated_at = document_updated_at WHERE document_id = $document_id");
        logAudit("Document", "Restore", "$session_name restored document " . escapeSql($row['document_name']), $client_id, $document_id);
        $document_count++;
    }

    flashAlert("Restored " . filesCountPhrase($document_count, $file_count));

    redirect();

}

if (isset($_POST['bulk_delete_files'])) {

    validateCSRFToken();

    enforceUserPermission('module_support', 3);

    $file_count = 0;
    $document_count = 0;

    foreach (array_map('intval', (array) ($_POST['file_ids'] ?? [])) as $file_id) {
        if (fileDeleteForGood($file_id) !== null) {
            $file_count++;
        }
    }

    foreach (array_map('intval', (array) ($_POST['document_ids'] ?? [])) as $document_id) {
        if (documentDeleteForGood($document_id) !== null) {
            $document_count++;
        }
    }

    flashAlert("Deleted " . filesCountPhrase($document_count, $file_count), 'error');

    redirect();

}

if (isset($_POST['bulk_move_files'])) {

    validateCSRFToken();

    enforceUserPermission('module_support', 2);

    $target_folder = intval($_POST['bulk_folder_id'] ?? 0);
    $folder_name = folderDisplayName($target_folder);

    $file_count = 0;
    $document_count = 0;

    // Each item only moves into a folder of its own client - one of another
    // client's folders would take it out of sight of both
    foreach (array_map('intval', (array) ($_POST['file_ids'] ?? [])) as $file_id) {
        $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT file_client_id, file_name FROM files WHERE file_id = $file_id"));
        if (!$row) {
            continue;
        }
        $client_id = intval($row['file_client_id']);
        enforceClientAccess();
        if ($target_folder && folderOwnedId($target_folder, $client_id) !== $target_folder) {
            continue;
        }
        mysqli_query($mysqli, "UPDATE files SET file_folder_id = $target_folder WHERE file_id = $file_id");
        logAudit("File", "Move", "$session_name moved file " . escapeSql($row['file_name']) . " to " . escapeSql($folder_name), $client_id, $file_id);
        $file_count++;
    }

    foreach (array_map('intval', (array) ($_POST['document_ids'] ?? [])) as $document_id) {
        $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT document_client_id, document_name FROM documents WHERE document_id = $document_id"));
        if (!$row) {
            continue;
        }
        $client_id = intval($row['document_client_id']);
        enforceClientAccess();
        if ($target_folder && folderOwnedId($target_folder, $client_id) !== $target_folder) {
            continue;
        }
        mysqli_query($mysqli, "UPDATE documents SET document_folder_id = $target_folder, document_updated_at = document_updated_at WHERE document_id = $document_id");
        logAudit("Document", "Move", "$session_name moved document " . escapeSql($row['document_name']) . " to " . escapeSql($folder_name), $client_id, $document_id);
        $document_count++;
    }

    flashAlert("Moved " . filesCountPhrase($document_count, $file_count) . " to <strong>$folder_name</strong>");

    redirect();

}
