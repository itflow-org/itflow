<?php

// Doc Templates

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

if (isset($_POST['add_document_template'])) {

    $name = sanitizeInput($_POST['name']);
    $description = sanitizeInput($_POST['description']);
    $content = mysqli_real_escape_string($mysqli,$_POST['content']);

    mysqli_query($mysqli,"INSERT INTO document_templates SET document_template_name = '$name', document_template_description = '$description', document_template_content = '$content', document_template_created_by = $session_user_id");
    
    $document_template_id = mysqli_insert_id($mysqli);

    logAction("Document Template", "Create", "$session_name created document template $name", 0, $document_template_id);

    flash_alert("Document template <strong>$name</strong> created");

    redirect();

}

if (isset($_POST['edit_document_template'])) {

    $document_template_id = intval($_POST['document_template_id']);
    $name = sanitizeInput($_POST['name']);
    $description = sanitizeInput($_POST['description']);
    $content = mysqli_real_escape_string($mysqli,$_POST['content']);

    // Document edit query
    mysqli_query($mysqli,"UPDATE document_templates SET document_template_name = '$name', document_template_description = '$description', document_template_content = '$content', document_template_updated_by = $session_user_id WHERE document_template_id = $document_template_id");

    logAction("Document Template", "Edit", "$session_name edited document template $name", 0, $document_template_id);

    flash_alert("Document Template <strong>$name</strong> edited");

    redirect();

}

if (isset($_GET['delete_document_template'])) {

    $document_template_id = intval($_GET['delete_document_template']);

    $document_template_name = sanitizeInput(getFieldById('document_templates', $document_template_id, 'document_template_name'));

    mysqli_query($mysqli,"DELETE FROM document_templates WHERE document_template_id = $document_template_id");

    logAction("Document Template", "Delete", "$session_name deleted document template $document_template_name");

    flash_alert("Document Template <strong>$document_template_name</strong> deleted", 'error');

    redirect();

}
