<?php

require_once '../validate_api_key.php';
require_once '../require_post_method.php';

// Parse ID
$document_id = intval($_POST['document_id'] ?? 0);

// Default
$delete_count = false;

if (!empty($document_id) && !empty($client_id)) {

    // Archived first, as in the app
    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT document_name FROM documents WHERE document_id = $document_id AND document_client_id = $client_id AND document_archived_at IS NOT NULL LIMIT 1"));

    if ($row) {

        // The document, its versions, its links and its files (functions/files.php)
        if (documentDeleteForGood($document_id) !== null) {
            $delete_count = 1;

            $document_name = escapeSql($row['document_name']);
            logAudit("API", "Success", "Deleted document $document_name via API ($api_key_name)", $client_id);
        }
    }
}

// Output
require_once '../delete_output.php';
