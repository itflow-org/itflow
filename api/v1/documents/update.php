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
    $update_insert_sql = mysqli_query($mysqli,"INSERT INTO documents SET document_name = '$name', document_description = '$description', document_content = '$content', document_content_raw = '$content_raw', document_template = 0, document_folder_id = $folder, document_created_by = 0, document_client_id = $client_id");

    // Check insert & get insert ID
    if ($update_insert_sql) {
        $insert_id = $new_document_id = mysqli_insert_id($mysqli);

        // Update the parent ID of the new document to match its new document ID
        mysqli_query($mysqli,"UPDATE documents SET document_parent = $new_document_id WHERE document_id = $new_document_id");

        // Link all existing links with old document with new document
        mysqli_query($mysqli,"UPDATE documents SET document_parent = $new_document_id, document_archived_at = NOW() WHERE document_parent = $document_id");

        // Update Links to the new parent document:-
        // Document files
        mysqli_query($mysqli,"UPDATE document_files SET document_id = $new_document_id WHERE document_id = $document_id");

        // Contact documents
        mysqli_query($mysqli,"UPDATE contact_documents SET document_id = $new_document_id WHERE document_id = $document_id");

        // Asset documents
        mysqli_query($mysqli,"UPDATE asset_documents SET document_id = $new_document_id WHERE document_id = $document_id");

        // Software documents
        mysqli_query($mysqli,"UPDATE software_documents SET document_id = $new_document_id WHERE document_id = $document_id");

        // Vendor documents
        mysqli_query($mysqli,"UPDATE vendor_documents SET document_id = $new_document_id WHERE document_id = $document_id");

        //Logging
        mysqli_query($mysqli, "INSERT INTO logs SET log_type = 'Document', log_action = 'Modify', log_description = '$name via API ($api_key_name) previous version was kept', log_ip = '$ip', log_user_agent = '$user_agent', log_client_id = $client_id");
        mysqli_query($mysqli, "INSERT INTO logs SET log_type = 'API', log_action = 'Success', log_description = 'Edited document $name via API ($api_key_name)', log_ip = '$ip', log_user_agent = '$user_agent', log_client_id = $client_id");

        // Override update count to 1 for API to report a success (as we inserted a document, not "updated" an existing row)
        $update_count = 1;
    }

}


// Output
require_once '../update_output.php';
