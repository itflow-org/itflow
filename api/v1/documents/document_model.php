<?php
// Variable assignment from POST (or: blank/from DB is updating)

if (isset($_POST['document_name'])) {
    $name = sanitizeInput($_POST['document_name']);
} elseif (isset($document_row) && isset($document_row['document_name'])) {
    $name = $document_row['document_name'];
} else {
    $name = '';
}

if (isset($_POST['document_description'])) {
    $description = sanitizeInput($_POST['document_description']);
} elseif (isset($document_row) && isset($document_row['document_description'])) {
    $description = $document_row['document_description'];
} else {
    $description = '';
}

if (isset($_POST['document_content'])) {
    $content = mysqli_real_escape_string($mysqli, $_POST['document_content']);
} elseif (isset($document_row) && isset($document_row['document_content'])) {
    $content = $document_row['document_content'];
} else {
    $content = '';
}

// Raw content (used for FULL INDEX searching)
if (isset($_POST['document_content'])) {
    $content_raw = sanitizeInput($_POST['document_name'] . $_POST['document_description'] . " " . str_replace("<", " <", $_POST['document_content']));
} elseif (isset($document_row) && isset($document_row['document_content_raw'])) {
    $content_raw = $document_row['document_content_raw'];
} else {
    $content_raw = '';
}

if (isset($_POST['document_folder_id'])) {
    $folder = intval($_POST['document_folder_id']);
} elseif (isset($document_row) && isset($document_row['document_folder_id'])) {
    $folder = intval($document_row['document_folder_id']);
} else {
    $folder = 0;
}
