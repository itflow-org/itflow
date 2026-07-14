<?php

require_once '../validate_api_key.php';

require_once '../require_post_method.php';

// Parse info
require_once 'document_model.php';

// Default
$insert_id = false;

if (!empty($name) && !(empty($content))) {

    // Create document
    $insert_sql = mysqli_query($mysqli,"INSERT INTO documents SET document_name = '$name', document_description = '$description', document_content = '', document_content_raw = '$content_raw', document_folder_id = $folder, document_created_by = 0, document_client_id = $client_id");

    // Check insert & get insert ID
    if ($insert_sql) {
        $insert_id = mysqli_insert_id($mysqli);

        $processed_content = mysqli_escape_string(
            $mysqli,
            saveBase64Images(
                $content,
                $_SERVER['DOCUMENT_ROOT'] . "/uploads/documents/",
                "uploads/documents/",
                $insert_id
            )
        );

        // Document update content
        mysqli_query($mysqli,"UPDATE documents SET document_content = '$processed_content' WHERE document_id = $insert_id");

        // Logging
        logAudit("Document", "Create", "$name via API ($api_key_name)", $client_id, $insert_id);
        logAudit("API", "Success", "Created document $name via API ($api_key_name)", $client_id);
    }

}

// Output
require_once '../create_output.php';
