<?php

require_once '../validate_api_key.php';
require_once '../require_post_method.php';

// Parse ID
$document_id = intval($_POST['document_id'] ?? 0);

// Default
$update_count = false;

if (!empty($document_id)) {

    // 1) Load the current document (scoped to this client)
    $sql_original_document = mysqli_query(
        $mysqli,
        "SELECT * FROM documents
         WHERE document_client_id = $client_id
           AND document_id = $document_id
         LIMIT 1"
    );

    if ($sql_original_document && mysqli_num_rows($sql_original_document) === 1) {

        $row = mysqli_fetch_assoc($sql_original_document);

        // Pull original fields for versioning
        $original_document_name        = escapeSql($row['document_name']);
        $original_document_description = escapeSql($row['document_description']);
        $original_document_content     = mysqli_real_escape_string($mysqli, $row['document_content']);
        $original_document_created_by  = intval($row['document_created_by']);
        $original_document_updated_by  = intval($row['document_updated_by']);
        $original_document_created_at  = escapeSql($row['document_created_at']);
        $original_document_updated_at  = escapeSql($row['document_updated_at']);

        // Determine who/when created the version (same logic as app)
        if (!empty($original_document_updated_at)) {
            $document_version_created_at = $original_document_updated_at;
        } else {
            $document_version_created_at = $original_document_created_at;
        }

        if (!empty($original_document_updated_by)) {
            $document_version_created_by = $original_document_updated_by;
        } else {
            $document_version_created_by = $original_document_created_by;
        }

        // 2) Save the current version into document_versions
        mysqli_query(
            $mysqli,
            "INSERT INTO document_versions SET
                document_version_name        = '$original_document_name',
                document_version_description = '$original_document_description',
                document_version_content     = '$original_document_content',
                document_version_created_by  = $document_version_created_by,
                document_version_created_at  = '$document_version_created_at',
                document_version_document_id = $document_id"
        );

        $document_version_id = mysqli_insert_id($mysqli);

        // 3) Variable assignment from POST
        // This should set: $name, $description, $content (raw html), $folder, etc.

        // Fetch current doc data (fresh)
        $document_row = mysqli_fetch_assoc(mysqli_query($mysqli, "
        SELECT * FROM documents
        WHERE document_client_id = $client_id
           AND document_id = $document_id
        LIMIT 1
        "));

        // Assign variables from POST or fallback to DB
        require_once 'document_model.php';

        // Process NEW HTML content: save base64 images to /uploads/documents/<document_id>/
        // In-app uses $_POST['content'] as raw; in API you likely map to $content in document_model.php
        $raw_post_content = $content;

        $processed_html = saveBase64Images(
            $raw_post_content,
            $_SERVER['DOCUMENT_ROOT'] . "/uploads/documents/",
            "uploads/documents/",
            $document_id
        );

        // Escape for DB
        $content_db = mysqli_real_escape_string($mysqli, $processed_html);

        // Rebuild content_raw for full-text search (same technique as app)
        $content_raw = escapeSql($name . " " . str_replace("<", " <", $processed_html));
        $content_raw = mysqli_real_escape_string($mysqli, $content_raw);

        // Escape name/description too (document_model.php may already sanitize; do DB escaping here regardless)
        $name_db        = mysqli_real_escape_string($mysqli, $name);
        $description_db = mysqli_real_escape_string($mysqli, $description);
        $folder_id      = intval($folder);

        // 4) Update the document (IMPORTANT: proper WHERE + scope to client)
        mysqli_query(
            $mysqli,
            "UPDATE documents SET
                document_name        = '$name_db',
                document_description = '$description_db',
                document_content     = '$content_db',
                document_content_raw = '$content_raw',
                document_folder_id   = $folder_id,
                document_updated_by  = 0
             WHERE document_id = $document_id
               AND document_client_id = $client_id
             LIMIT 1"
        );

        // For API: treat success as "updated row" OR "query ran but values unchanged"
        if (mysqli_errno($mysqli) === 0) {
            $update_count = 1;
        }

        // Logging
        logAction("Document", "Edit", "$name_db via API ($api_key_name), previous version kept", $client_id, $document_version_id);
        logAction("API", "Success", "Edited document $name_db via API ($api_key_name)", $client_id);

    } else {
        // Not found (or not this client's doc)
        $update_count = false;
        logAction("API", "Error", "Document update failed (not found or unauthorized) via API ($api_key_name)", $client_id);
    }
}

// Output
require_once '../update_output.php';
