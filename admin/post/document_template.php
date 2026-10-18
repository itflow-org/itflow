<?php

/*
 * Document templates (Admin > Document Templates) - making, changing,
 * copying, archiving and deleting them, with their images. The work is in
 * functions/document_template.php; a document is made from one by
 * add_document_from_template (agent/post/document.php).
 */

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

/* The template a request is for - or back to the list, saying it is gone. */
function documentTemplateForRequest($document_template_id) {
    $row = documentTemplateRow($document_template_id);
    if (!$row) {
        flashAlert('That document template no longer exists.', 'error');
        redirect('document_templates.php');
    }
    return $row;
}

if (isset($_POST['add_document_template']) || isset($_POST['edit_document_template'])) {

    validateCSRFToken();

    $editing = isset($_POST['edit_document_template']);
    $document_template_id = $editing ? intval(documentTemplateForRequest($_POST['document_template_id'] ?? 0)['document_template_id']) : 0;

    $fields = documentTemplateFields($_POST, $error);
    if (!$fields) {
        flashAlert(escapeHtml($error), 'error');
        redirect();
    }
    $document_template_id = documentTemplateSave($document_template_id, $fields);
    $name = escapeSql($fields['name']);

    logAudit("Document Template", $editing ? "Edit" : "Create", "$session_name " . ($editing ? 'edited' : 'created') . " document template $name", 0, $document_template_id);

    flashAlert("Document template <strong>" . escapeHtml($fields['name']) . "</strong> " . ($editing ? 'saved' : 'created'));

    // A new one opens on its page
    redirect($editing ? null : "document_template.php?document_template_id=$document_template_id");

}

if (isset($_GET['copy_document_template'])) {

    validateCSRFToken();

    $row = documentTemplateForRequest($_GET['copy_document_template']);
    $new_id = documentTemplateCopy($row['document_template_id']);
    $new_name = (string) documentTemplateRow($new_id)['document_template_name'];

    logAudit("Document Template", "Create", "$session_name copied document template " . escapeSql($row['document_template_name']) . " as " . escapeSql($new_name), 0, $new_id);

    flashAlert("Document template <strong>" . escapeHtml($row['document_template_name']) . "</strong> copied as <strong>" . escapeHtml($new_name) . "</strong>");

    redirect("document_template.php?document_template_id=$new_id");

}

if (isset($_GET['archive_document_template']) || isset($_GET['restore_document_template'])) {

    validateCSRFToken();

    $archive = isset($_GET['archive_document_template']);
    $row = documentTemplateForRequest($archive ? $_GET['archive_document_template'] : $_GET['restore_document_template']);
    $document_template_id = intval($row['document_template_id']);

    // Archived, it isn't offered for new documents; the ones made from it stay
    mysqli_query($mysqli, "UPDATE document_templates SET document_template_archived_at = " . ($archive ? 'NOW()' : 'NULL') . " WHERE document_template_id = $document_template_id");

    logAudit("Document Template", $archive ? "Archive" : "Restore", "$session_name " . ($archive ? 'archived' : 'restored') . " document template " . escapeSql($row['document_template_name']), 0, $document_template_id);

    flashAlert("Document template <strong>" . escapeHtml($row['document_template_name']) . "</strong> " . ($archive ? 'archived' : 'restored'), $archive ? 'error' : 'success');

    redirect();

}

if (isset($_GET['delete_document_template'])) {

    validateCSRFToken();

    $row = documentTemplateForRequest($_GET['delete_document_template']);

    // Its images go with it; the documents made from it stay, no longer linked to it
    documentTemplateDelete($row['document_template_id']);

    logAudit("Document Template", "Delete", "$session_name deleted document template " . escapeSql($row['document_template_name']));

    flashAlert("Document template <strong>" . escapeHtml($row['document_template_name']) . "</strong> deleted", 'error');

    // From its own page, back to the list
    redirect(!empty($_GET['from_page']) ? 'document_templates.php' : null);

}
