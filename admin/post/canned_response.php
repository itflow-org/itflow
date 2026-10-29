<?php

/*
 * Canned responses (Admin > Canned Responses) - making, changing, copying,
 * archiving and deleting them, with their files, sharing an agent's own
 * with everyone, and exporting and importing them as a file. What is made
 * here is shared. The work is in functions/canned_response.php and
 * functions/canned_response_transfer.php; an agent's own are made in Your
 * account (agent/user/post/canned_response.php).
 */

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

/* The response a request is for - or back to the list, saying it is gone. */
function cannedResponseForRequest($canned_response_id) {
    $row = cannedResponseRow($canned_response_id);
    if (!$row) {
        flashAlert('That canned response no longer exists.', 'error');
        redirect('canned_responses.php');
    }
    return $row;
}

if (isset($_POST['add_canned_response']) || isset($_POST['edit_canned_response'])) {

    validateCSRFToken();

    $editing = isset($_POST['edit_canned_response']);
    $canned_response_id = $editing ? intval(cannedResponseForRequest($_POST['canned_response_id'] ?? 0)['canned_response_id']) : 0;

    $fields = cannedResponseFields($_POST, $error);
    if (!$fields) {
        flashAlert(escapeHtml($error), 'error');
        redirect();
    }
    $canned_response_id = cannedResponseSave($canned_response_id, $fields);
    $name = escapeSql($fields['name']);

    // Its files - the ones ticked off go, the new ones are kept
    cannedResponseRemoveFiles($canned_response_id, (array) ($_POST['remove_file_ids'] ?? []));
    $refused = cannedResponseSaveFiles($canned_response_id);

    logAudit("Canned Response", $editing ? "Edit" : "Create", "$session_name " . ($editing ? 'edited' : 'created') . " canned response $name", 0, $canned_response_id);

    flashAlert("Canned response <strong>" . escapeHtml($fields['name']) . "</strong> " . ($editing ? 'saved' : 'created')
        . ($refused ? " - but not <strong>" . escapeHtml(implode(', ', $refused)) . "</strong>, a kind a ticket doesn't take or more than " . CANNED_RESPONSE_MAX_FILES . " files" : ''), $refused ? 'error' : 'success');

    redirect();

}

if (isset($_GET['copy_canned_response'])) {

    validateCSRFToken();

    // A copy made here is shared, an agent's own copied too
    $row = cannedResponseForRequest($_GET['copy_canned_response']);
    $new_id = cannedResponseCopy($row['canned_response_id'], 0);
    $new_name = (string) cannedResponseRow($new_id)['canned_response_name'];

    logAudit("Canned Response", "Create", "$session_name copied canned response " . escapeSql($row['canned_response_name']) . " as " . escapeSql($new_name), 0, $new_id);

    flashAlert("Canned response <strong>" . escapeHtml($row['canned_response_name']) . "</strong> copied as <strong>" . escapeHtml($new_name) . "</strong>");

    redirect();

}

if (isset($_GET['archive_canned_response']) || isset($_GET['restore_canned_response'])) {

    validateCSRFToken();

    $archive = isset($_GET['archive_canned_response']);
    $row = cannedResponseForRequest($archive ? $_GET['archive_canned_response'] : $_GET['restore_canned_response']);
    $canned_response_id = intval($row['canned_response_id']);
    $name = escapeSql($row['canned_response_name']);

    // Archived, it isn't offered on tickets; restoring it is all it takes to offer it again
    mysqli_query($mysqli, "UPDATE canned_responses SET canned_response_archived_at = " . ($archive ? 'NOW()' : 'NULL') . " WHERE canned_response_id = $canned_response_id");

    logAudit("Canned Response", $archive ? "Archive" : "Restore", "$session_name " . ($archive ? 'archived' : 'restored') . " canned response $name", 0, $canned_response_id);

    flashAlert("Canned response <strong>" . escapeHtml($row['canned_response_name']) . "</strong> " . ($archive ? 'archived' : 'restored'), $archive ? 'error' : 'success');

    redirect();

}

if (isset($_GET['delete_canned_response'])) {

    validateCSRFToken();

    $row = cannedResponseForRequest($_GET['delete_canned_response']);
    $canned_response_id = intval($row['canned_response_id']);

    // Its categories, files and record of use go with it, by their foreign keys, and its folder of files; replies it went into stay as they are
    mysqli_query($mysqli, "DELETE FROM canned_responses WHERE canned_response_id = $canned_response_id");
    cannedResponseDeleteFiles($canned_response_id);

    logAudit("Canned Response", "Delete", "$session_name deleted canned response " . escapeSql($row['canned_response_name']));

    flashAlert("Canned response <strong>" . escapeHtml($row['canned_response_name']) . "</strong> deleted", 'error');

    redirect();

}

if (isset($_GET['share_canned_response'])) {

    validateCSRFToken();

    // An agent's own, offered to everyone from now on - it becomes a shared one, made here
    $row = cannedResponseForRequest($_GET['share_canned_response']);
    $owner = intval($row['canned_response_user_id']);
    if ($owner) {
        $owner_name = (string) getFieldById('users', $owner, 'user_name');
        mysqli_query($mysqli, "UPDATE canned_responses SET canned_response_user_id = 0 WHERE canned_response_id = " . intval($row['canned_response_id']));
        logAudit("Canned Response", "Edit", "$session_name shared " . escapeSql(($owner_name !== '' ? "$owner_name's" : "a removed agent's") . " canned response " . $row['canned_response_name']) . " with everyone", 0, intval($row['canned_response_id']));
        flashAlert("Canned response <strong>" . escapeHtml($row['canned_response_name']) . "</strong> is shared with everyone now");
    }

    redirect();

}

if (isset($_POST['export_canned_responses']) || isset($_GET['export_canned_response'])) {

    // A file of responses to import into another ITFlow - every current shared one, the ones picked, or one
    validateCSRFToken();

    if (isset($_GET['export_canned_response'])) {
        $row = cannedResponseForRequest($_GET['export_canned_response']);
        $export = cannedResponsesExport([intval($row['canned_response_id'])]);
    } else {
        $export = cannedResponsesExport((array) ($_POST['canned_response_ids'] ?? []), !empty($_POST['archived']), !empty($_POST['files']));
    }
    if (!$export['responses']) {
        flashAlert('There are no canned responses to export.', 'error');
        redirect();
    }

    $count = count($export['responses']);
    logAudit("Canned Response", "Export", "$session_name exported $count canned response" . ($count === 1 ? ' ' . escapeSql($export['responses'][0]['name']) : 's'));

    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . cannedResponsesExportFileName($export) . '"');
    header('Cache-Control: no-store');
    echo json_encode($export, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;

}

if (isset($_POST['import_canned_responses'])) {

    // Responses from a file exported by ITFlow, as shared ones
    validateCSRFToken();

    $file = $_FILES['file'] ?? null;
    if ($file && in_array($file['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) {
        flashAlert('That file is bigger than this server takes in an upload - export them without their files, or a few at a time.', 'error');
        redirect('canned_responses.php');
    }
    if (!$file || $file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
        flashAlert('Pick the file to import.', 'error');
        redirect('canned_responses.php');
    }
    if ($file['size'] > 200 * 1024 * 1024) {
        flashAlert('That file is too big to be canned responses.', 'error');
        redirect('canned_responses.php');
    }
    $responses = cannedResponsesImportRead(file_get_contents($file['tmp_name']), $import_error);
    if ($responses === null) {
        flashAlert(escapeHtml($import_error), 'error');
        redirect('canned_responses.php');
    }
    $existing = in_array($_POST['existing'] ?? '', ['skip', 'copy', 'replace'], true) ? $_POST['existing'] : 'skip';
    $counts = cannedResponsesImport($responses, $existing);

    logAudit("Canned Response", "Import", "$session_name imported canned responses from " . escapeSql(mb_substr((string) $file['name'], 0, 100)) . " - {$counts['added']} added, {$counts['replaced']} replaced, {$counts['skipped']} skipped");

    flashAlert(cannedResponsesImportSummary($counts), $counts['files_refused'] ? 'warning' : ($counts['added'] || $counts['replaced'] ? 'success' : 'info'));

    redirect('canned_responses.php');

}
