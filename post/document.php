<?php

/*
 * ITFlow - GET/POST request handler for client documents
 */

if (isset($_POST['add_document'])) {

    validateTechRole();

    $client_id = intval($_POST['client_id']);
    $name = sanitizeInput($_POST['name']);
    $description = sanitizeInput($_POST['description']);
    $content = mysqli_real_escape_string($mysqli,$_POST['content']);
    $content_raw = sanitizeInput($_POST['name'] . " " . str_replace("<", " <", $_POST['content']));
    // Content Raw is used for FULL INDEX searching. Adding a space before HTML tags to allow spaces between newlines, bulletpoints, etc. for searching.

    $folder = intval($_POST['folder']);

    // Document add query
    $add_document = mysqli_query($mysqli,"INSERT INTO documents SET document_name = '$name', document_description = '$description', document_content = '$content', document_content_raw = '$content_raw', document_template = 0, document_folder_id = $folder, document_created_by = $session_user_id, document_client_id = $client_id");
    $document_id = mysqli_insert_id($mysqli);

    // Update field document_parent to be the same id as document ID as this is the only version of the document.
    mysqli_query($mysqli,"UPDATE documents SET document_parent = $document_id WHERE document_id = $document_id");

    // Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Document', log_action = 'Create', log_description = 'Created $name', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_client_id = $client_id, log_user_id = $session_user_id");

    $_SESSION['alert_message'] = "Document <strong>$name</strong> created";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_POST['add_document_template'])) {

    validateTechRole();

    $client_id = intval($_POST['client_id']);
    $name = sanitizeInput($_POST['name']);
    $description = sanitizeInput($_POST['description']);
    $content = mysqli_real_escape_string($mysqli,$_POST['content']);
    $content_raw = sanitizeInput($_POST['name'] . " " . str_replace("<", " <", $_POST['content']));
    // Content Raw is used for FULL INDEX searching. Adding a space before HTML tags to allow spaces between newlines, bulletpoints, etc. for searching.

    // Document add query
    $add_document = mysqli_query($mysqli,"INSERT INTO documents SET document_name = '$name', document_description = '$description', document_content = '$content', document_content_raw = '$content_raw', document_template = 1, document_folder_id = 0, document_created_by = $session_user_id, document_client_id = 0");
    $document_id = mysqli_insert_id($mysqli);

    // Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Document Template', log_action = 'Create', log_description = '$session_name created document template $name', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_client_id = $client_id, log_user_id = $session_user_id, log_entity_id = $document_id");

    $_SESSION['alert_message'] = "Document template <strong>$name</strong> created";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_POST['add_document_from_template'])) {

    // ROLE Check
    validateTechRole();

    // GET POST Data
    $client_id = intval($_POST['client_id']);
    $document_name = sanitizeInput($_POST['name']);
    $document_description = sanitizeInput($_POST['description']);
    $document_template_id = intval($_POST['document_template_id']);
    $folder = intval($_POST['folder']);

    //GET Document Info
    $sql_document = mysqli_query($mysqli,"SELECT * FROM documents WHERE document_id = $document_template_id");

    $row = mysqli_fetch_array($sql_document);

    $document_template_name = sanitizeInput($row['document_name']);
    $content = mysqli_real_escape_string($mysqli,$row['document_content']);
    $content_raw = sanitizeInput($_POST['name'] . " " . str_replace("<", " <", $row['document_content']));

    // Document add query
    mysqli_query($mysqli,"INSERT INTO documents SET document_name = '$document_name', document_description = '$document_description', document_content = '$content', document_content_raw = '$content_raw', document_template = 0, document_folder_id = $folder, document_created_by = $session_user_id, document_client_id = $client_id");

    $document_id = mysqli_insert_id($mysqli);

    // Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Document', log_action = 'Create', log_description = 'Document $document_name created from template $document_template_name', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_client_id = $client_id, log_user_id = $session_user_id, log_entity_id = $document_id");

    $_SESSION['alert_message'] = "Document <strong>$document_name</strong> created from template";

    header("Location: client_document_details.php?client_id=$client_id&document_id=$document_id");

}

if (isset($_POST['edit_document'])) {

    validateTechRole();

    $document_id = intval($_POST['document_id']);
    $document_created_by = intval($_POST['created_by']);
    $document_parent = intval($_POST['document_parent']);
    $client_id = intval($_POST['client_id']);
    $name = sanitizeInput($_POST['name']);
    $description = sanitizeInput($_POST['description']);
    $content = mysqli_real_escape_string($mysqli,$_POST['content']);
    $content_raw = sanitizeInput($_POST['name'] . " " . str_replace("<", " <", $_POST['content']));
    // Content Raw is used for FULL INDEX searching. Adding a space before HTML tags to allow spaces between newlines, bulletpoints, etc. for searching.
    $folder = intval($_POST['folder']);

    // Document add query
    mysqli_query($mysqli,"INSERT INTO documents SET document_name = '$name', document_description = '$description', document_content = '$content', document_content_raw = '$content_raw', document_template = 0, document_folder_id = $folder, document_created_by = $document_created_by, document_updated_by = $session_user_id, document_client_id = $client_id");

    $new_document_id = mysqli_insert_id($mysqli);

    // Update the parent ID of the new document to match its new document ID
    mysqli_query($mysqli,"UPDATE documents SET document_parent = $new_document_id WHERE document_id = $new_document_id");

    // Link all exisiting links with old document with new document
    mysqli_query($mysqli,"UPDATE documents SET document_parent = $new_document_id, document_archived_at = NOW() WHERE document_parent = $document_id");

    // Update Links to the new parent document
    // document files
    mysqli_query($mysqli,"UPDATE document_files SET document_id = $new_document_id WHERE document_id = $document_id");

    // contact documents
    mysqli_query($mysqli,"UPDATE contact_documents SET document_id = $new_document_id WHERE document_id = $document_id");

    // asset documents
    mysqli_query($mysqli,"UPDATE asset_documents SET document_id = $new_document_id WHERE document_id = $document_id");

    // software documents
    mysqli_query($mysqli,"UPDATE software_documents SET document_id = $new_document_id WHERE document_id = $document_id");

    // vendor documents
    mysqli_query($mysqli,"UPDATE vendor_documents SET document_id = $new_document_id WHERE document_id = $document_id");

    // Service document
    mysqli_query($mysqli,"UPDATE service_documents SET document_id = $new_document_id WHERE document_id = $document_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Document', log_action = 'Edit', log_description = '$session_name Edited document $name previous version was kept', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_client_id = $client_id, log_user_id = $session_user_id, log_entity_id = $new_document_id");


    $_SESSION['alert_message'] = "Document <strong>$name</strong> updated, previous version kept";

    header("Location: client_document_details.php?client_id=$client_id&document_id=$new_document_id");
}

if (isset($_POST['move_document'])) {

    validateTechRole();

    $document_id = intval($_POST['document_id']);
    $client_id = intval($_POST['client_id']);
    $folder = intval($_POST['folder']);

    // Document edit query
    mysqli_query($mysqli,"UPDATE documents SET document_folder_id = $folder WHERE document_id = $document_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Document', log_action = 'Modify', log_description = '$session_name moved document', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_client_id = $client_id, log_user_id = $session_user_id, log_entity_id = $document_id");


    $_SESSION['alert_message'] = "Document moved";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_POST['rename_document'])) {

    validateTechRole();

    $document_id = intval($_POST['document_id']);
    $client_id = intval($_POST['client_id']);
    $name = sanitizeInput($_POST['name']);

    // Document edit query
    mysqli_query($mysqli,"UPDATE documents SET document_name = '$name' WHERE document_id = $document_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Document', log_action = 'Rename', log_description = '$session_name renamed document to $name', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_client_id = $client_id, log_user_id = $session_user_id, log_entity_id = $document_id");


    $_SESSION['alert_message'] = "You renamed Document to <strong>$name</strong>";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_POST['link_file_to_document'])) {

    validateTechRole();

    $client_id = intval($_POST['client_id']);
    $document_id = intval($_POST['document_id']);
    $file_id = intval($_POST['file_id']);

    // Document add query
    mysqli_query($mysqli,"INSERT INTO document_files SET file_id = $file_id, document_id = $document_id");

    // Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Document', log_action = 'Link', log_description = 'Created Document File link', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_client_id = $client_id, log_user_id = $session_user_id");

    $_SESSION['alert_message'] = "File linked with Document";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_GET['unlink_file_from_document'])) {

    validateTechRole();
    $file_id = intval($_GET['file_id']);
    $document_id = intval($_GET['document_id']);

    mysqli_query($mysqli,"DELETE FROM document_files WHERE file_id = $file_id AND document_id = $document_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Document', log_action = 'unLink', log_description = 'Document File link removed', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id");

    $_SESSION['alert_message'] = "File has been unlinked";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_POST['link_vendor_to_document'])) {

    validateTechRole();

    $client_id = intval($_POST['client_id']);
    $document_id = intval($_POST['document_id']);
    $vendor_id = intval($_POST['vendor_id']);

    // Document add query
    mysqli_query($mysqli,"INSERT INTO vendor_documents SET vendor_id = $vendor_id, document_id = $document_id");

    // Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Document', log_action = 'Link', log_description = 'Created Document Vendor link', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_client_id = $client_id, log_user_id = $session_user_id");

    $_SESSION['alert_message'] = "Vendor linked with Document";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_GET['unlink_vendor_from_document'])) {

    validateTechRole();
    $vendor_id = intval($_GET['vendor_id']);
    $document_id = intval($_GET['document_id']);

    mysqli_query($mysqli,"DELETE FROM vendor_documents WHERE vendor_id = $vendor_id AND document_id = $document_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Document', log_action = 'unLink', log_description = 'Document Vendor link removed', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id");

    $_SESSION['alert_message'] = "Vendor has been unlinked";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_POST['link_contact_to_document'])) {

    validateTechRole();

    $client_id = intval($_POST['client_id']);
    $document_id = intval($_POST['document_id']);
    $contact_id = intval($_POST['contact_id']);

    // Contact add query
    mysqli_query($mysqli,"INSERT INTO contact_documents SET contact_id = $contact_id, document_id = $document_id");

    // Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Document', log_action = 'Link', log_description = 'Created Document Contact link', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_client_id = $client_id, log_user_id = $session_user_id");

    $_SESSION['alert_message'] = "Contact linked with Document";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_GET['unlink_contact_from_document'])) {

    validateTechRole();
    $contact_id = intval($_GET['contact_id']);
    $document_id = intval($_GET['document_id']);

    mysqli_query($mysqli,"DELETE FROM contact_documents WHERE contact_id = $contact_id AND document_id = $document_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Document', log_action = 'unLink', log_description = 'Document Contact link removed', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id");

    $_SESSION['alert_message'] = "Contact has been unlinked";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_POST['link_asset_to_document'])) {

    validateTechRole();

    $client_id = intval($_POST['client_id']);
    $document_id = intval($_POST['document_id']);
    $asset_id = intval($_POST['asset_id']);

    // Contact add query
    mysqli_query($mysqli,"INSERT INTO asset_documents SET asset_id = $asset_id, document_id = $document_id");

    // Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Document', log_action = 'Link', log_description = 'Created Document Asset link', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_client_id = $client_id, log_user_id = $session_user_id");

    $_SESSION['alert_message'] = "Asset linked with Document";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_GET['unlink_asset_from_document'])) {

    validateTechRole();
    $asset_id = intval($_GET['asset_id']);
    $document_id = intval($_GET['document_id']);

    mysqli_query($mysqli,"DELETE FROM asset_documents WHERE asset_id = $asset_id AND document_id = $document_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Document', log_action = 'unLink', log_description = 'Document Asset link removed', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id");

    $_SESSION['alert_message'] = "Asset has been unlinked";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_POST['link_software_to_document'])) {

    validateTechRole();

    $client_id = intval($_POST['client_id']);
    $document_id = intval($_POST['document_id']);
    $software_id = intval($_POST['software_id']);

    // Contact add query
    mysqli_query($mysqli,"INSERT INTO software_documents SET software_id = $software_id, document_id = $document_id");

    // Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Document', log_action = 'Link', log_description = 'Created Document Software link', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_client_id = $client_id, log_user_id = $session_user_id");

    $_SESSION['alert_message'] = "Contact linked with Document";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_GET['unlink_software_from_document'])) {

    validateTechRole();
    $software_id = intval($_GET['software_id']);
    $document_id = intval($_GET['document_id']);

    mysqli_query($mysqli,"DELETE FROM software_documents WHERE software_id = $software_id AND document_id = $document_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Document', log_action = 'unLink', log_description = 'Document Software link removed', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id");

    $_SESSION['alert_message'] = "Software has been unlinked";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_POST['edit_document_template'])) {

    validateTechRole();

    $document_id = intval($_POST['document_id']);
    $name = sanitizeInput($_POST['name']);
    $description = sanitizeInput($_POST['description']);
    $content = mysqli_real_escape_string($mysqli,$_POST['content']);
    $content_raw = sanitizeInput($_POST['name'] . " " . str_replace("<", " <", $_POST['content']));
    // Content Raw is used for FULL INDEX searching. Adding a space before HTML tags to allow spaces between newlines, bulletpoints, etc. for searching.

    // Document edit query
    mysqli_query($mysqli,"UPDATE documents SET document_name = '$name', document_description = '$description', document_content = '$content', document_content_raw = '$content_raw', document_updated_by = $session_user_id WHERE document_id = $document_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Document Template', log_action = 'Modify', log_description = '$session_name modified document template $name', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id, log_entity_id = $document_id");


    $_SESSION['alert_message'] = "Document Template <strong>$name</strong> updated";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_GET['archive_document'])) {

    validateTechRole();

    $document_id = intval($_GET['archive_document']);

    // Get Contact Name and Client ID for logging and alert message
    $sql = mysqli_query($mysqli,"SELECT document_name, document_client_id FROM documents WHERE document_id = $document_id");
    $row = mysqli_fetch_array($sql);
    $document_name = sanitizeInput($row['document_name']);
    $client_id = intval($row['document_client_id']);

    mysqli_query($mysqli,"UPDATE documents SET document_archived_at = NOW() WHERE document_id = $document_id");

    // Remove Associations
    // File Association
    mysqli_query($mysqli,"DELETE FROM document_files WHERE document_id = $document_id");

    // Contact Associations
    mysqli_query($mysqli,"DELETE FROM contact_documents WHERE document_id = $document_id");

    // Asset Associations
    mysqli_query($mysqli,"DELETE FROM asset_documents WHERE document_id = $document_id");

    // Software Associations
    mysqli_query($mysqli,"DELETE FROM software_documents WHERE document_id = $document_id");

    // Vendor Associations
    mysqli_query($mysqli,"DELETE FROM vendor_documents WHERE document_id = $document_id");

    // Service Associations
    mysqli_query($mysqli,"DELETE FROM service_documents WHERE document_id = $document_id");

    //logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Document', log_action = 'Archive', log_description = '$session_name archived document $document_name', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_client_id = $client_id, log_user_id = $session_user_id, log_entity_id = $document_id");

    $_SESSION['alert_type'] = "error";
    $_SESSION['alert_message'] = "Document <strong>$document_name</strong> archived";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_GET['delete_document'])) {

    validateAdminRole();

    $document_id = intval($_GET['delete_document']);

    mysqli_query($mysqli,"DELETE FROM documents WHERE document_id = $document_id");

    // Remove Associations
    // File Association
    mysqli_query($mysqli,"DELETE FROM document_files WHERE document_id = $document_id");

    // Contact Associations
    mysqli_query($mysqli,"DELETE FROM contact_documents WHERE document_id = $document_id");

    // Asset Associations
    mysqli_query($mysqli,"DELETE FROM asset_documents WHERE document_id = $document_id");

    // Software Associations
    mysqli_query($mysqli,"DELETE FROM software_documents WHERE document_id = $document_id");

    // Vendor Associations
    mysqli_query($mysqli,"DELETE FROM vendor_documents WHERE document_id = $document_id");

    // Service Associations
    mysqli_query($mysqli,"DELETE FROM service_documents WHERE document_id = $document_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Document', log_action = 'Delete', log_description = '$document_id', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id");

    $_SESSION['alert_message'] = "Document deleted";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}
