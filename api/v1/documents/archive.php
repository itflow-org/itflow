<?php

require_once '../validate_api_key.php';
require_once '../require_post_method.php';

// Parse ID
$document_id = intval($_POST['document_id'] ?? 0);

// Default
$update_count = false;

if (!empty($document_id)) {

    // Fetch document info
    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "
        SELECT document_name
        FROM documents
        WHERE document_id = $document_id AND document_client_id = $client_id AND document_archived_at IS NULL
        LIMIT 1
    "));

    if ($row) {

        $document_name = escapeSql($row['document_name']);

        // Archive document
        $update_sql = mysqli_query($mysqli, "
            UPDATE documents SET document_archived_at = NOW()
            WHERE document_id = $document_id AND document_client_id = $client_id
        ");

        if ($update_sql) {
            $update_count = mysqli_affected_rows($mysqli);

            // Logging
            logAudit("Document", "Archive", "$document_name archived via API ($api_key_name)", $client_id, $document_id);
            logAudit("API", "Success", "Archived document $document_name via API ($api_key_name)", $client_id);
        }
    }
}

// Output
require_once '../update_output.php';
