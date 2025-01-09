<?php

// Doc Templates

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

// Import shared code from user-side docs as we reuse functions
require_once 'post/user/document.php';

if (isset($_POST['add_document_template'])) {

    $client_id = intval($_POST['client_id']);
    $name = sanitizeInput($_POST['name']);
    $description = sanitizeInput($_POST['description']);
    $content = mysqli_real_escape_string($mysqli,$_POST['content']);
    $content_raw = sanitizeInput($_POST['name'] . " " . str_replace("<", " <", $_POST['content']));
    // Content Raw is used for FULL INDEX searching. Adding a space before HTML tags to allow spaces between newlines, bulletpoints, etc. for searching.

    // Document create query
    mysqli_query($mysqli,"INSERT INTO documents SET document_name = '$name', document_description = '$description', document_content = '$content', document_content_raw = '$content_raw', document_template = 1, document_folder_id = 0, document_created_by = $session_user_id, document_client_id = 0");
    
    $document_id = mysqli_insert_id($mysqli);

    // Logging
    logAction("Document Template", "Create", "$session_name created document template $name", $client_id, $document_id);

    $_SESSION['alert_message'] = "Document template <strong>$name</strong> created";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}
