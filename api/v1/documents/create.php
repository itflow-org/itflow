<?php

require_once '../validate_api_key.php';

require_once '../require_post_method.php';

// Parse info
require_once 'document_model.php';

// Default
$insert_id = false;

if (!empty($name) && !(empty($content))) {

    // Create document
    $insert_sql = mysqli_query($mysqli,"INSERT INTO documents SET document_name = '$name', document_description = '$description', document_content = '$content', document_content_raw = '$content_raw', document_template = 0, document_folder_id = $folder, document_created_by = 0, document_client_id = $client_id");

    // Check insert & get insert ID
    if ($insert_sql) {
        $insert_id = mysqli_insert_id($mysqli);

        // Update field document_parent to be the same id as document ID as this is the only version of the document.
        mysqli_query($mysqli,"UPDATE documents SET document_parent = $insert_id WHERE document_id = $insert_id");

        //Logging
        mysqli_query($mysqli, "INSERT INTO logs SET log_type = 'Document', log_action = 'Create', log_description = '$name via API ($api_key_name)', log_ip = '$ip', log_user_agent = '$user_agent', log_client_id = $client_id");
        mysqli_query($mysqli, "INSERT INTO logs SET log_type = 'API', log_action = 'Success', log_description = 'Created document $name via API ($api_key_name)', log_ip = '$ip', log_user_agent = '$user_agent', log_client_id = $client_id");
    }

}


// Output
require_once '../create_output.php';
