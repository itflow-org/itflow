<?php

require_once '../validate_api_key.php';

require_once '../require_post_method.php';

// Parse ID
$document_id = intval($_POST['document_id']);

// Default
$update_count = false;

if (!empty($document_id)) {

    $document_row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT * FROM documents WHERE document_id = '$document_id' AND document_client_id = $client_id LIMIT 1"));

    // Variable assignment from POST - assigning the current database value if a value is not provided
    require_once 'document_model.php';

    // Documents are a little weird as we update them by *inserting* a new document row
    $update_insert_sql = mysqli_query($mysqli,"INSERT INTO documents SET document_name = '$name', document_description = '$description', document_content = '$content', document_content_raw = '$content_raw', document_folder_id = $folder, document_created_by = 0, document_client_id = $client_id");

    // Check insert & get insert ID
    if ($update_insert_sql) {
        $insert_id = $new_document_id = mysqli_insert_id($mysqli);

        // Logging
        logAction("Document", "Edit", "$name via API ($api_key_name) previous version kept", $client_id, $insert_id);
        logAction("API", "Success", "Edited document $name via API ($api_key_name)", $client_id);

        // Override update count to 1 for API to report a success (as we inserted a document, not "updated" an existing row)
        $update_count = 1;
    }

}

// Output
require_once '../update_output.php';
