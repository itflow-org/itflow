<?php

/*
 * ITFlow - GET/POST request handler for client documents
 */

if (isset($_POST['add_document'])) {

    enforceUserPermission('module_support', 2);

    require_once 'document_model.php';

    // Document add query
    $add_document = mysqli_query($mysqli,"INSERT INTO documents SET document_name = '$name', document_description = '$description', document_content = '$content', document_content_raw = '$content_raw', document_template = 0, document_folder_id = $folder, document_created_by = $session_user_id, document_client_id = $client_id");
    $document_id = mysqli_insert_id($mysqli);

    // Update field document_parent to be the same id as document ID as this is the only version of the document.
    mysqli_query($mysqli,"UPDATE documents SET document_parent = $document_id WHERE document_id = $document_id");

    // Logging
    logAction("Document", "Create", "$session_name created document $name", $client_id, $document_id);

    $_SESSION['alert_message'] = "Document <strong>$name</strong> created";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_POST['add_document_from_template'])) {

    // ROLE Check
    enforceUserPermission('module_support', 2);

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

    // Update field document_parent to be the same id as document ID as this is the only version of the document.
    mysqli_query($mysqli,"UPDATE documents SET document_parent = $document_id WHERE document_id = $document_id");

    // Logging
    logAction("Document", "Create", "$session_name created document $name from template $document_template_name", $client_id, $document_id);

    $_SESSION['alert_message'] = "Document <strong>$document_name</strong> created from template";

    header("Location: client_document_details.php?client_id=$client_id&document_id=$document_id");

}

if (isset($_POST['edit_document'])) {

    enforceUserPermission('module_support', 2);

    require_once 'document_model.php';
    $document_id = intval($_POST['document_id']);
    $document_created_by = intval($_POST['created_by']);
    $document_parent = intval($_POST['document_parent']);

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
    logAction("Document", "Edit", "$session_name edited document $name, previous version kept", $client_id, $new_document_id);

    $_SESSION['alert_message'] = "Document <strong>$name</strong> edited, previous version kept";

    header("Location: client_document_details.php?client_id=$client_id&document_id=$new_document_id");
}

if (isset($_POST['move_document'])) {

    enforceUserPermission('module_support', 2);

    $document_id = intval($_POST['document_id']);
    $folder_id = intval($_POST['folder']);

    // Get Document Name Client ID for logging
    $sql_document = mysqli_query($mysqli,"SELECT document_name, document_client_id FROM documents WHERE document_id = $document_id");
    $row = mysqli_fetch_array($sql_document);
    $document_name = sanitizeInput($row['document_name']);
    $client_id = intval($row['document_client_id']);

    // Get Folder Name for logging
    $sql_folder = mysqli_query($mysqli,"SELECT folder_name FROM folders WHERE folder_id = $folder_id");
    $row = mysqli_fetch_array($sql_folder);
    $folder_name = sanitizeInput($row['folder_name']);
    
    // Document edit query
    mysqli_query($mysqli,"UPDATE documents SET document_folder_id = $folder_id WHERE document_id = $document_id");

    //Logging
    logAction("Document", "Move", "$session_name moved document $document_name to folder $folder_name", $client_id, $document_id);

    $_SESSION['alert_message'] = "Document <strong>$document_name</strong> moved to folder <strong>$folder_name</strong>";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_POST['rename_document'])) {

    enforceUserPermission('module_support', 2);

    $document_id = intval($_POST['document_id']);
    $client_id = intval($_POST['client_id']);
    $name = sanitizeInput($_POST['name']);

    // Get Document Name before renaming for logging
    $sql_document = mysqli_query($mysqli,"SELECT document_name FROM documents WHERE document_id = $document_id");
    $row = mysqli_fetch_array($sql_document);
    $old_document_name = sanitizeInput($row['document_name']);

    // Document edit query
    mysqli_query($mysqli,"UPDATE documents SET document_name = '$name' WHERE document_id = $document_id");

    //Logging
    logAction("Document", "Edit", "$session_name renamed document $old_document_name to $name", $client_id, $document_id);


    $_SESSION['alert_message'] = "You renamed Document from <strong>$old_document_name</strong> to <strong>$name</strong>";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_POST['bulk_move_document'])) {

    enforceUserPermission('module_support', 2);

    $folder_id = intval($_POST['bulk_folder_id']);

    // Get folder name for logging and Notification
    $sql = mysqli_query($mysqli,"SELECT folder_name, folder_client_id FROM folders WHERE folder_id = $folder_id");
    $row = mysqli_fetch_array($sql);
    $folder_name = sanitizeInput($row['folder_name']);
    $client_id = intval($row['folder_client_id']);

    // Move Documents to Folder Loop
    if ($_POST['document_ids']) {

        // Get Selected Count
        $count = count($_POST['document_ids']);

        foreach($_POST['document_ids'] as $document_id) {
            $document_id = intval($document_id);
            // Get document name for logging
            $sql = mysqli_query($mysqli,"SELECT document_name FROM documents WHERE document_id = $document_id");
            $row = mysqli_fetch_array($sql);
            $document_name = sanitizeInput($row['document_name']);

            // Document move query
            mysqli_query($mysqli,"UPDATE documents SET document_folder_id = $folder_id WHERE document_id = $document_id");

            //Logging
            logAction("Document", "Move", "$session_name moved document $document_name to folder $folder_name", $client_id, $document_id);
        }

        logAction("Document", "Bulk Move", "$session_name moved $count document(s) to folder $folder_name", $client_id);
    }

    $_SESSION['alert_message'] = "You moved <strong>$count</strong> document(s) to the folder <strong>$folder_name</strong>";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_POST['link_file_to_document'])) {

    enforceUserPermission('module_support', 2);

    $document_id = intval($_POST['document_id']);
    $file_id = intval($_POST['file_id']);

    // Get Document Name and Client ID for logging
    $sql_document = mysqli_query($mysqli,"SELECT document_name, document_client_id FROM documents WHERE document_id = $document_id");
    $row = mysqli_fetch_array($sql_document);
    $document_name = sanitizeInput($row['document_name']);
    $client_id = intval($row['document_client_id']);

    // Get File Name for logging
    $sql_file = mysqli_query($mysqli,"SELECT file_name FROM files WHERE file_id = $file_id");
    $row = mysqli_fetch_array($sql_file);
    $file_name = sanitizeInput($row['file_name']);

    // Document add query
    mysqli_query($mysqli,"INSERT INTO document_files SET file_id = $file_id, document_id = $document_id");

    // Logging
    logAction("Document", "Link", "$session_name linked file $file_name to document $document_name", $client_id, $document_id);

    $_SESSION['alert_message'] = "File <strong>$file_name</strong> linked with Document <strong>$document_name</strong>";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_GET['unlink_file_from_document'])) {

    enforceUserPermission('module_support', 2);

    $file_id = intval($_GET['file_id']);
    $document_id = intval($_GET['document_id']);

    // Get Document Name and Client ID for logging
    $sql_document = mysqli_query($mysqli,"SELECT document_name, document_client_id FROM documents WHERE document_id = $document_id");
    $row = mysqli_fetch_array($sql_document);
    $document_name = sanitizeInput($row['document_name']);
    $client_id = intval($row['document_client_id']);

    // Get File Name for logging
    $sql_file = mysqli_query($mysqli,"SELECT file_name FROM files WHERE file_id = $file_id");
    $row = mysqli_fetch_array($sql_file);
    $file_name = sanitizeInput($row['file_name']);

    mysqli_query($mysqli,"DELETE FROM document_files WHERE file_id = $file_id AND document_id = $document_id");

    //Logging
    logAction("Document", "Unlink", "$session_name unlinked file $file_name from document $document_name", $client_id, $document_id);

    $_SESSION['alert_type'] = "error";
    $_SESSION['alert_message'] = "File <strong>$file_name</strong> unlinked from Document <strong>$document_name</strong>";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_POST['link_vendor_to_document'])) {

    enforceUserPermission('module_support', 2);

    $document_id = intval($_POST['document_id']);
    $vendor_id = intval($_POST['vendor_id']);

    // Get Document Name and Client ID for logging
    $sql_document = mysqli_query($mysqli,"SELECT document_name, document_client_id FROM documents WHERE document_id = $document_id");
    $row = mysqli_fetch_array($sql_document);
    $document_name = sanitizeInput($row['document_name']);
    $client_id = intval($row['document_client_id']);

    // Get Vendor Name for logging
    $sql_vendor = mysqli_query($mysqli,"SELECT vendor_name FROM vendors WHERE vendor_id = $vendor_id");
    $row = mysqli_fetch_array($sql_vendor);
    $vendor_name = sanitizeInput($row['vendor_name']);

    // Document add query
    mysqli_query($mysqli,"INSERT INTO vendor_documents SET vendor_id = $vendor_id, document_id = $document_id");

    // Logging
    logAction("Document", "Link", "$session_name linked vendor $vendor_name to document $document_name", $client_id, $document_id);

    $_SESSION['alert_message'] = "Vendor <strong>$vendor_name</strong> linked with Document <strong>$document_name</strong>";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_GET['unlink_vendor_from_document'])) {

    enforceUserPermission('module_support', 2);

    $vendor_id = intval($_GET['vendor_id']);
    $document_id = intval($_GET['document_id']);

    // Get Document Name and Client ID for logging
    $sql_document = mysqli_query($mysqli,"SELECT document_name, document_client_id FROM documents WHERE document_id = $document_id");
    $row = mysqli_fetch_array($sql_document);
    $document_name = sanitizeInput($row['document_name']);
    $client_id = intval($row['document_client_id']);

    // Get Vendor Name for logging
    $sql_vendor = mysqli_query($mysqli,"SELECT vendor_name FROM vendors WHERE vendor_id = $vendor_id");
    $row = mysqli_fetch_array($sql_vendor);
    $vendor_name = sanitizeInput($row['vendor_name']);

    mysqli_query($mysqli,"DELETE FROM vendor_documents WHERE vendor_id = $vendor_id AND document_id = $document_id");

    //Logging
    logAction("Document", "Unlink", "$session_name unlinked vendor $vendor_name from document $document_name", $client_id, $document_id);

    $_SESSION['alert_type'] = "error";
    $_SESSION['alert_message'] = "Vendor <strong>$vendor_name</strong> unlinked from Document <strong>$document_name</strong>";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_POST['link_contact_to_document'])) {

    enforceUserPermission('module_support', 2);

    $client_id = intval($_POST['client_id']);
    $document_id = intval($_POST['document_id']);
    $contact_id = intval($_POST['contact_id']);

    // Get Document Name and Client ID for logging
    $sql_document = mysqli_query($mysqli,"SELECT document_name, document_client_id FROM documents WHERE document_id = $document_id");
    $row = mysqli_fetch_array($sql_document);
    $document_name = sanitizeInput($row['document_name']);
    $client_id = intval($row['document_client_id']);

    // Get Contact Name for logging
    $sql_contact = mysqli_query($mysqli,"SELECT contact_name FROM contacts WHERE contact_id = $contact_id");
    $row = mysqli_fetch_array($sql_contact);
    $contact_name = sanitizeInput($row['contact_name']);

    // Contact add query
    mysqli_query($mysqli,"INSERT INTO contact_documents SET contact_id = $contact_id, document_id = $document_id");

    // Logging
    logAction("Document", "Link", "$session_name linked contact $contact_name to document $document_name", $client_id, $document_id);

    $_SESSION['alert_message'] = "Contact <strong>$contact_name</strong> linked with Document <strong>$document_name</strong>";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_GET['unlink_contact_from_document'])) {

    enforceUserPermission('module_support', 2);

    $contact_id = intval($_GET['contact_id']);
    $document_id = intval($_GET['document_id']);

    // Get Document Name and Client ID for logging
    $sql_document = mysqli_query($mysqli,"SELECT document_name, document_client_id FROM documents WHERE document_id = $document_id");
    $row = mysqli_fetch_array($sql_document);
    $document_name = sanitizeInput($row['document_name']);
    $client_id = intval($row['document_client_id']);

    // Get Contact Name for logging
    $sql_contact = mysqli_query($mysqli,"SELECT contact_name FROM contacts WHERE contact_id = $contact_id");
    $row = mysqli_fetch_array($sql_contact);
    $contact_name = sanitizeInput($row['contact_name']);

    mysqli_query($mysqli,"DELETE FROM contact_documents WHERE contact_id = $contact_id AND document_id = $document_id");

    //Logging
    logAction("Document", "Unlink", "$session_name unlinked contact $contact_name from document $document_name", $client_id, $document_id);

    $_SESSION['alert_type'] = "error";
    $_SESSION['alert_message'] = "Contact <strong>$contact_name</strong> unlinked from Document <strong>$document_name</strong>";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_POST['link_asset_to_document'])) {

    enforceUserPermission('module_support', 2);

    $document_id = intval($_POST['document_id']);
    $asset_id = intval($_POST['asset_id']);

    // Get Document Name and Client ID for logging
    $sql_document = mysqli_query($mysqli,"SELECT document_name, document_client_id FROM documents WHERE document_id = $document_id");
    $row = mysqli_fetch_array($sql_document);
    $document_name = sanitizeInput($row['document_name']);
    $client_id = intval($row['document_client_id']);

    // Get Asset Name for logging
    $sql_asset = mysqli_query($mysqli,"SELECT asset_name FROM assets WHERE asset_id = $asset_id");
    $row = mysqli_fetch_array($sql_asset);
    $asset_name = sanitizeInput($row['asset_name']);

    // Contact add query
    mysqli_query($mysqli,"INSERT INTO asset_documents SET asset_id = $asset_id, document_id = $document_id");

    // Logging
    logAction("Document", "Link", "$session_name linked asset $asset_name to document $document_name", $client_id, $document_id);

    $_SESSION['alert_message'] = "Asset <strong>$asset_name</strong> linked with Document <strong>$document_name</strong>";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_GET['unlink_asset_from_document'])) {

    enforceUserPermission('module_support', 2);

    $asset_id = intval($_GET['asset_id']);
    $document_id = intval($_GET['document_id']);

    // Get Document Name and Client ID for logging
    $sql_document = mysqli_query($mysqli,"SELECT document_name, document_client_id FROM documents WHERE document_id = $document_id");
    $row = mysqli_fetch_array($sql_document);
    $document_name = sanitizeInput($row['document_name']);
    $client_id = intval($row['document_client_id']);

    // Get Asset Name for logging
    $sql_asset = mysqli_query($mysqli,"SELECT asset_name FROM assets WHERE asset_id = $asset_id");
    $row = mysqli_fetch_array($sql_asset);
    $asset_name = sanitizeInput($row['asset_name']);

    mysqli_query($mysqli,"DELETE FROM asset_documents WHERE asset_id = $asset_id AND document_id = $document_id");

    // Logging
    logAction("Document", "Unlink", "$session_name unlinked asset $asset_name from document $document_name", $client_id, $document_id);

    $_SESSION['alert_type'] = "error";
    $_SESSION['alert_message'] = "Asset <strong>$asset_name</strong> unlinked from Document <strong>$document_name</strong>";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_POST['link_software_to_document'])) {

    enforceUserPermission('module_support', 2);

    $document_id = intval($_POST['document_id']);
    $software_id = intval($_POST['software_id']);

    // Get Document Name and Client ID for logging
    $sql_document = mysqli_query($mysqli,"SELECT document_name, document_client_id FROM documents WHERE document_id = $document_id");
    $row = mysqli_fetch_array($sql_document);
    $document_name = sanitizeInput($row['document_name']);
    $client_id = intval($row['document_client_id']);

    // Get Software Name for logging
    $sql_software = mysqli_query($mysqli,"SELECT software_name FROM software WHERE software_id = $software_id");
    $row = mysqli_fetch_array($sql_software);
    $software_name = sanitizeInput($row['software_name']);

    // Contact add query
    mysqli_query($mysqli,"INSERT INTO software_documents SET software_id = $software_id, document_id = $document_id");

    // Logging
    logAction("Document", "Link", "$session_name linked software $software_name to document $document_name", $client_id, $document_id);

    $_SESSION['alert_message'] = "Software <strong>$software_name</strong> linked with Document <strong>$document_name</strong>";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_GET['unlink_software_from_document'])) {

    enforceUserPermission('module_support', 2);

    $software_id = intval($_GET['software_id']);
    $document_id = intval($_GET['document_id']);

    // Get Document Name and Client ID for logging
    $sql_document = mysqli_query($mysqli,"SELECT document_name, document_client_id FROM documents WHERE document_id = $document_id");
    $row = mysqli_fetch_array($sql_document);
    $document_name = sanitizeInput($row['document_name']);
    $client_id = intval($row['document_client_id']);

    // Get Software Name for logging
    $sql_software = mysqli_query($mysqli,"SELECT software_name FROM software WHERE software_id = $software_id");
    $row = mysqli_fetch_array($sql_software);
    $software_name = sanitizeInput($row['software_name']);

    mysqli_query($mysqli,"DELETE FROM software_documents WHERE software_id = $software_id AND document_id = $document_id");

    // Logging
    logAction("Document", "Unlink", "$session_name unlinked software $software_name from document $document_name", $client_id, $document_id);

    $_SESSION['alert_type'] = "error";
    $_SESSION['alert_message'] = "Software <strong>$software_name</strong> unlinked from Document <strong>$document_name</strong>";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_POST['edit_document_template'])) {

    enforceUserPermission('module_support', 2);

    $document_id = intval($_POST['document_id']);
    $name = sanitizeInput($_POST['name']);
    $description = sanitizeInput($_POST['description']);
    $content = mysqli_real_escape_string($mysqli,$_POST['content']);
    $content_raw = sanitizeInput($_POST['name'] . " " . str_replace("<", " <", $_POST['content']));
    // Content Raw is used for FULL INDEX searching. Adding a space before HTML tags to allow spaces between newlines, bulletpoints, etc. for searching.

    // Document edit query
    mysqli_query($mysqli,"UPDATE documents SET document_name = '$name', document_description = '$description', document_content = '$content', document_content_raw = '$content_raw', document_updated_by = $session_user_id WHERE document_id = $document_id");

    // Logging
    logAction("Document Template", "Edit", "$session_name edited document template $name", 0, $document_id);

    $_SESSION['alert_message'] = "Document Template <strong>$name</strong> edited";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_POST['toggle_document_visibility'])) {

    enforceUserPermission('module_support', 2);

    $document_id = intval($_POST['document_id']);
    $document_visible = intval($_POST['document_visible']);

    if ($document_visible == 0) {
        $visable_wording = "Invisable";
    } else {
        $visable_wording = "Visable";
    }

    // Get Document Name and Client ID for logging
    $sql_document = mysqli_query($mysqli,"SELECT document_name, document_client_id FROM documents WHERE document_id = $document_id");
    $row = mysqli_fetch_array($sql_document);
    $document_name = sanitizeInput($row['document_name']);
    $client_id = intval($row['document_client_id']);

    mysqli_query($mysqli,"UPDATE documents SET document_client_visible = $document_visible WHERE document_id = $document_id");

    //Logging
    logAction("Document", "Edit", "$session_name changed document $document_name visibilty to $visable_wording in the client portal", $client_id, $document_id);

    $_SESSION['alert_message'] = "Document <strong>$document_name</strong> changed to <strong>$visable_wording</strong> in the client portal";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_GET['archive_document'])) {

    enforceUserPermission('module_support', 2);

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

    // Logging
    logAction("Document", "Archive", "$session_name archived document $document_name", $client_id, $document_id);

    $_SESSION['alert_type'] = "error";
    $_SESSION['alert_message'] = "Document <strong>$document_name</strong> archived";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_GET['delete_document_version'])) {

    enforceUserPermission('module_support', 3);

    $document_id = intval($_GET['delete_document_version']);

    // Get Document Parent ID
    $sql = mysqli_query($mysqli,"SELECT document_name, document_parent, document_client_id FROM documents WHERE document_id = $document_id");
    $row = mysqli_fetch_array($sql);
    $client_id = intval($row['document_client_id']);
    $document_parent = intval($row['document_parent']);
    $document_name = sanitizeInput($row['document_name']);

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
    logAction("Document Version", "Delete", "$session_name deleted document version $document_name", $client_id);

    $_SESSION['alert_type'] = "error";
    $_SESSION['alert_message'] = "Document $document_name version deleted";

    header("Location: client_document_details.php?client_id=$client_id&document_id=$document_parent");

}

if (isset($_GET['delete_document'])) {

    enforceUserPermission('module_support', 3);

    $document_id = intval($_GET['delete_document']);

    // Get Document Name and Client ID for logging
    $sql = mysqli_query($mysqli,"SELECT document_name, document_client_id FROM documents WHERE document_id = $document_id");
    $row = mysqli_fetch_array($sql);
    $client_id = intval($row['document_client_id']);
    $document_name = sanitizeInput($row['document_name']);

    mysqli_query($mysqli,"DELETE FROM documents WHERE document_id = $document_id");

    // Delete all versions associated with the master document
    mysqli_query($mysqli,"DELETE FROM documents WHERE document_parent = $document_id");

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
    logAction("Document", "Delete", "$session_name deleted document $document_name and all versions", $client_id);

    $_SESSION['alert_message'] = "Document $document_name and all versions";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_POST['bulk_delete_documents'])) {

    enforceUserPermission('module_support', 3);
    validateCSRFToken($_POST['csrf_token']);

    
    if ($_POST['document_ids']) {     

        // Get selected document count
        $count = count($_POST['document_ids']);
        
        // Delete document loop
        foreach($_POST['document_ids'] as $document_id) {
            $document_id = intval($document_id);
            // Get document name for logging
            $sql = mysqli_query($mysqli,"SELECT document_name, document_client_id FROM documents WHERE document_id = $document_id");
            $row = mysqli_fetch_array($sql);
            $document_name = sanitizeInput($row['document_name']);
            $client_id = intval($row['document_client_id']);

            mysqli_query($mysqli,"DELETE FROM documents WHERE document_id = $document_id");

            // Delete all versions associated with the master document
            mysqli_query($mysqli,"DELETE FROM documents WHERE document_parent = $document_id");

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
            logAction("Document", "Delete", "$session_name deleted document $document_name and all versions", $client_id);  

        }

        //Logging
        mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Document', log_action = 'Bulk Delete', log_description = '$session_name deleted $document_count documents', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id");
        logAction("Document", "Bulk Delete", "$session_name deleted $count document(s) and all versions", $client_id);

        $_SESSION['alert_type'] = "error";
        $_SESSION['alert_message'] = "Deleted <strong>$count</strong> Documents and associated document versions";
    }

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}


