<?php

/*
 * ITFlow - GET/POST request handler for client documents
 *
 * Linking documents to files, assets, contacts, licenses and vendors lives in
 * post/link.php.
 */

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

/** The document row a handler works on, with its client checked - or a flash and back. */
function documentForHandler($document_id) {
    global $mysqli, $client_id;
    $document_id = intval($document_id);
    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT document_id, document_name, document_description, document_content,
            document_client_id, document_folder_id, document_client_visible, document_archived_at,
            document_created_at, document_created_by, document_updated_at, document_updated_by
        FROM documents WHERE document_id = $document_id"));
    if (!$row) {
        flashAlert('That document no longer exists.', 'error');
        redirect();
    }
    $client_id = intval($row['document_client_id']);
    enforceClientAccess();
    return $row;
}

/** Keep a document's current content as a version, before it is replaced. Returns the version id. */
function documentKeepVersion($row) {
    global $mysqli;
    $by = intval($row['document_updated_by']) ?: intval($row['document_created_by']);
    $at = escapeSql($row['document_updated_at'] ?: $row['document_created_at']);
    mysqli_query($mysqli, "INSERT INTO document_versions SET
        document_version_name = '" . escapeSql($row['document_name']) . "',
        document_version_description = '" . escapeSql($row['document_description']) . "',
        document_version_content = '" . mysqli_real_escape_string($mysqli, (string) $row['document_content']) . "',
        document_version_created_by = $by,
        document_version_created_at = '$at',
        document_version_document_id = " . intval($row['document_id']));
    return mysqli_insert_id($mysqli);
}

if (isset($_POST['add_document'])) {

    validateCSRFToken();

    enforceUserPermission('module_support', 2);

    require_once 'document_model.php';
    $client_id = intval($_POST['client_id']);

    enforceClientAccess();

    // Only the client's own folder, contact, asset and license
    $folder = folderOwnedId($folder, $client_id);
    $contact_id = assetClientOwnedId('contact', $_POST['contact'] ?? 0, $client_id);
    $asset = linkRecord('asset', $_POST['asset'] ?? 0);
    $asset_id = $asset && intval($asset['client_id']) === $client_id ? intval($asset['id']) : 0;
    $software = linkRecord('software', $_POST['software'] ?? 0);
    $software_id = $software && intval($software['client_id']) === $client_id ? intval($software['id']) : 0;
    $client_visible = intval($_POST['client_visible'] ?? 0) ? 1 : 0;

    if ($name === '') {
        flashAlert('A document needs a name.', 'error');
        redirect();
    }

    mysqli_query($mysqli, "INSERT INTO documents SET document_name = '$name', document_description = '$description', document_content = '', document_content_raw = '$content_raw',
        document_client_visible = $client_visible, document_folder_id = $folder, document_created_by = $session_user_id, document_client_id = $client_id");

    $document_id = mysqli_insert_id($mysqli);

    // Pasted images become files under uploads/documents/<id>/, so it needs its id first
    $processed_content = mysqli_real_escape_string($mysqli, saveBase64Images(
        $_POST['content'] ?? '',
        $_SERVER['DOCUMENT_ROOT'] . "/uploads/documents/",
        "uploads/documents/",
        $document_id
    ));
    mysqli_query($mysqli, "UPDATE documents SET document_content = '$processed_content' WHERE document_id = $document_id");

    if ($contact_id) {
        mysqli_query($mysqli, "INSERT IGNORE INTO contact_documents SET contact_id = $contact_id, document_id = $document_id");
    }
    if ($asset_id) {
        mysqli_query($mysqli, "INSERT IGNORE INTO asset_documents SET asset_id = $asset_id, document_id = $document_id");
    }
    if ($software_id) {
        mysqli_query($mysqli, "INSERT IGNORE INTO software_documents SET software_id = $software_id, document_id = $document_id");
    }

    logAudit("Document", "Create", "$session_name created document $name", $client_id, $document_id);

    flashAlert("Document <strong>" . trim($_POST['name']) . "</strong> created");

    // From an asset, a contact or a license, back to it; otherwise to the new document
    redirect(($contact_id || $asset_id || $software_id) ? null : "document.php?client_id=$client_id&document_id=$document_id");

}

if (isset($_POST['add_document_from_template'])) {

    validateCSRFToken();

    enforceUserPermission('module_support', 2);

    $client_id = intval($_POST['client_id']);
    $document_name = trim($_POST['name'] ?? '');
    $document_description = escapeSql($_POST['description'] ?? '');
    $document_template_id = intval($_POST['document_template_id'] ?? 0);

    enforceClientAccess();

    $folder = folderOwnedId($_POST['folder'] ?? 0, $client_id);

    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT document_template_content, document_template_name FROM document_templates
        WHERE document_template_id = $document_template_id AND document_template_archived_at IS NULL"));
    if (!$row) {
        flashAlert('That template no longer exists.', 'error');
        redirect();
    }

    // No name given - the template's
    if ($document_name === '') {
        $document_name = $row['document_template_name'];
    }
    $document_name_sql = escapeSql($document_name);

    mysqli_query($mysqli, "INSERT INTO documents SET
        document_name = '$document_name_sql',
        document_description = '$document_description',
        document_content = '',
        document_content_raw = '',
        document_folder_id = $folder,
        document_created_by = $session_user_id,
        document_client_id = $client_id");

    $document_id = mysqli_insert_id($mysqli);

    // The template's images come along, and its HTML is pointed at the copies
    copyDirectory($_SERVER['DOCUMENT_ROOT'] . "/uploads/document_templates/$document_template_id", $_SERVER['DOCUMENT_ROOT'] . "/uploads/documents/$document_id");
    $processed_html = str_replace("/uploads/document_templates/$document_template_id/", "/uploads/documents/$document_id/", (string) $row['document_template_content']);

    $content = mysqli_real_escape_string($mysqli, $processed_html);
    $content_raw = escapeSql(documentContentRaw($document_name, $processed_html));

    mysqli_query($mysqli, "UPDATE documents SET document_content = '$content', document_content_raw = '$content_raw' WHERE document_id = $document_id");

    logAudit("Document", "Create", "$session_name created document $document_name_sql from template " . escapeSql($row['document_template_name']), $client_id, $document_id);

    flashAlert("Document <strong>$document_name</strong> created from template");

    redirect("document.php?client_id=$client_id&document_id=$document_id");
}

if (isset($_POST['edit_document'])) {

    validateCSRFToken();

    enforceUserPermission('module_support', 2);

    require_once 'document_model.php';

    $row = documentForHandler($_POST['document_id']);
    $document_id = intval($row['document_id']);

    if ($name === '') {
        flashAlert('A document needs a name.', 'error');
        redirect();
    }

    // What it said before, kept as a version
    $document_version_id = documentKeepVersion($row);

    $processed_html = saveBase64Images(
        $_POST['content'] ?? '',
        $_SERVER['DOCUMENT_ROOT'] . "/uploads/documents/",
        "uploads/documents/",
        $document_id
    );
    $content = mysqli_real_escape_string($mysqli, $processed_html);
    $content_raw = escapeSql(documentContentRaw($_POST['name'], $processed_html));

    // The portal switch is only on the form when the portal is on - left alone otherwise
    $visible_sql = isset($_POST['client_visible']) ? ', document_client_visible = ' . (intval($_POST['client_visible']) ? 1 : 0) : '';

    mysqli_query($mysqli, "UPDATE documents SET
        document_name = '$name',
        document_description = '$description',
        document_content = '$content',
        document_content_raw = '$content_raw',
        document_updated_by = $session_user_id
        $visible_sql
        WHERE document_id = $document_id");

    logAudit("Document", "Edit", "$session_name edited document $name, previous version kept", $client_id, $document_id);

    flashAlert("Document <strong>" . trim($_POST['name']) . "</strong> saved - the previous version is kept");

    redirect("document.php?client_id=$client_id&document_id=$document_id");

}

if (isset($_POST['move_document'])) {

    validateCSRFToken();

    enforceUserPermission('module_support', 2);

    $row = documentForHandler($_POST['document_id']);
    $document_id = intval($row['document_id']);

    // Only into one of this client's folders
    $folder_id = folderOwnedId($_POST['folder'] ?? 0, $client_id);
    $folder_name = folderDisplayName($folder_id);

    mysqli_query($mysqli, "UPDATE documents SET document_folder_id = $folder_id, document_updated_at = document_updated_at WHERE document_id = $document_id");

    logAudit("Document", "Move", "$session_name moved document " . escapeSql($row['document_name']) . " to folder " . escapeSql($folder_name), $client_id, $document_id);

    flashAlert("Document <strong>{$row['document_name']}</strong> moved to <strong>$folder_name</strong>");

    redirect();

}

if (isset($_POST['rename_document'])) {

    validateCSRFToken();

    enforceUserPermission('module_support', 2);

    $row = documentForHandler($_POST['document_id']);
    $document_id = intval($row['document_id']);
    $name = escapeSql(trim($_POST['name'] ?? ''));
    $description_sql = isset($_POST['description']) ? ", document_description = '" . escapeSql($_POST['description']) . "'" : '';

    if ($name === '') {
        flashAlert('A document needs a name.', 'error');
        redirect();
    }

    mysqli_query($mysqli, "UPDATE documents SET document_name = '$name' $description_sql, document_updated_at = document_updated_at WHERE document_id = $document_id");

    logAudit("Document", "Edit", "$session_name renamed document " . escapeSql($row['document_name']) . " to $name", $client_id, $document_id);

    flashAlert("Document <strong>" . trim($_POST['name']) . "</strong> saved");

    redirect();

}

if (isset($_POST['toggle_document_visibility'])) {

    validateCSRFToken();

    enforceUserPermission('module_support', 2);

    $row = documentForHandler($_POST['document_id']);
    $document_id = intval($row['document_id']);
    $document_visible = intval($_POST['document_visible'] ?? 0) ? 1 : 0;
    $wording = $document_visible ? 'visible' : 'hidden';

    mysqli_query($mysqli, "UPDATE documents SET document_client_visible = $document_visible, document_updated_at = document_updated_at WHERE document_id = $document_id");

    logAudit("Document", "Edit", "$session_name made document " . escapeSql($row['document_name']) . " $wording in the client portal", $client_id, $document_id);

    flashAlert("Document <strong>{$row['document_name']}</strong> is now <strong>$wording</strong> in the client portal");

    redirect();

}

if (isset($_GET['export_document'])) {

    validateCSRFToken();

    // Reading, not changing - anyone who can open the document can take a PDF of it
    enforceUserPermission('module_client');

    $row = documentForHandler($_GET['export_document']);
    $document_id = intval($row['document_id']);

    logAudit("Document", "Export", "$session_name exported document " . escapeSql($row['document_name']), $client_id, $document_id);

    require_once('../libs/TCPDF/tcpdf.php');

    $pdf = new TCPDF();
    $pdf->SetCreator(PDF_CREATOR);
    $pdf->SetAuthor($session_name);
    $pdf->SetTitle($row['document_name']);
    $pdf->AddPage();
    $pdf->SetFont('helvetica', '', 12);
    $pdf->writeHTML('<h1>' . escapeHtml($row['document_name']) . '</h1>' . $row['document_content'], true, false, true, false, '');

    // The PDF is the response - nothing may follow it
    $pdf->Output(preg_replace('/[^A-Za-z0-9 ._-]/', '', $row['document_name']) . '.pdf', 'I');
    exit;

}

if (isset($_GET['archive_document'])) {

    validateCSRFToken();

    enforceUserPermission('module_support', 2);

    $row = documentForHandler($_GET['archive_document']);
    $document_id = intval($row['document_id']);

    mysqli_query($mysqli, "UPDATE documents SET document_archived_at = NOW(), document_updated_at = document_updated_at WHERE document_id = $document_id");

    // An archived document drops its links, as it always has
    linkRemoveAll('document', $document_id);

    logAudit("Document", "Archive", "$session_name archived document " . escapeSql($row['document_name']), $client_id, $document_id);

    flashAlert("Document <strong>{$row['document_name']}</strong> archived", 'error');

    redirect();

}

if (isset($_GET['restore_document'])) {

    validateCSRFToken();

    enforceUserPermission('module_support', 2);

    $row = documentForHandler($_GET['restore_document']);
    $document_id = intval($row['document_id']);

    mysqli_query($mysqli, "UPDATE documents SET document_archived_at = NULL, document_updated_at = document_updated_at WHERE document_id = $document_id");

    logAudit("Document", "Restore", "$session_name restored document " . escapeSql($row['document_name']), $client_id, $document_id);

    flashAlert("Document <strong>{$row['document_name']}</strong> restored");

    redirect();

}

/*
 * Put an older version back. What the document says now is kept as a version
 * first, so a restore can itself be undone.
 */
if (isset($_POST['restore_document_version'])) {

    validateCSRFToken();

    enforceUserPermission('module_support', 2);

    $document_version_id = intval($_POST['document_version_id']);
    $version = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT document_version_document_id, document_version_name, document_version_description,
            document_version_content, document_version_created_at
        FROM document_versions WHERE document_version_id = $document_version_id"));
    if (!$version) {
        flashAlert('That version no longer exists.', 'error');
        redirect();
    }

    $row = documentForHandler($version['document_version_document_id']);
    $document_id = intval($row['document_id']);

    documentKeepVersion($row);

    $name = escapeSql($version['document_version_name']);
    $content = mysqli_real_escape_string($mysqli, (string) $version['document_version_content']);
    $content_raw = escapeSql(documentContentRaw($version['document_version_name'], $version['document_version_content']));
    mysqli_query($mysqli, "UPDATE documents SET
        document_name = '$name',
        document_description = '" . escapeSql($version['document_version_description']) . "',
        document_content = '$content',
        document_content_raw = '$content_raw',
        document_updated_by = $session_user_id
        WHERE document_id = $document_id");

    $version_date = date('M j, Y g:ia', strtotime($version['document_version_created_at']));
    logAudit("Document", "Edit", "$session_name restored document $name to its version of " . escapeSql($version_date) . ", the current one kept", $client_id, $document_id);

    flashAlert("Document <strong>{$version['document_version_name']}</strong> restored to the version of $version_date - what it said before is kept as a version");

    redirect("document.php?client_id=$client_id&document_id=$document_id");

}

if (isset($_GET['delete_document_version'])) {

    validateCSRFToken();

    enforceUserPermission('module_support', 3);

    $document_version_id = intval($_GET['delete_document_version']);

    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT document_version_name, document_client_id FROM documents, document_versions
        WHERE document_version_document_id = document_id AND document_version_id = $document_version_id"));
    if (!$row) {
        flashAlert('That version no longer exists.', 'error');
        redirect();
    }
    $client_id = intval($row['document_client_id']);

    enforceClientAccess();

    mysqli_query($mysqli, "DELETE FROM document_versions WHERE document_version_id = $document_version_id");

    logAudit("Document Version", "Delete", "$session_name deleted document version " . escapeSql($row['document_version_name']), $client_id);

    flashAlert("A version of <strong>{$row['document_version_name']}</strong> deleted", 'error');

    redirect();

}

if (isset($_GET['delete_document'])) {

    validateCSRFToken();

    enforceUserPermission('module_support', 3);

    $document_name = documentDeleteForGood(intval($_GET['delete_document']));

    if ($document_name === null) {
        flashAlert('That document no longer exists.', 'error');
        redirect();
    }

    flashAlert("Document <strong>$document_name</strong> and all its versions deleted", 'error');

    // From the document's own page there is nothing to go back to
    if (($_GET['from'] ?? '') === 'document_details') {
        redirect("files.php?client_id=$client_id");
    }
    redirect();

}
