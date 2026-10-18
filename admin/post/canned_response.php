<?php

/*
 * Canned responses (Admin > Canned Responses) - making, changing, copying,
 * archiving and deleting them. The work is in functions/canned_response.php.
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

    logAudit("Canned Response", $editing ? "Edit" : "Create", "$session_name " . ($editing ? 'edited' : 'created') . " canned response $name", 0, $canned_response_id);

    flashAlert("Canned response <strong>" . escapeHtml($fields['name']) . "</strong> " . ($editing ? 'saved' : 'created'));

    redirect();

}

if (isset($_GET['copy_canned_response'])) {

    validateCSRFToken();

    $row = cannedResponseForRequest($_GET['copy_canned_response']);
    $new_id = cannedResponseCopy($row['canned_response_id']);
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

    // Its categories and its record of use go with it, by their foreign keys; replies it went into stay as they are
    mysqli_query($mysqli, "DELETE FROM canned_responses WHERE canned_response_id = $canned_response_id");

    logAudit("Canned Response", "Delete", "$session_name deleted canned response " . escapeSql($row['canned_response_name']));

    flashAlert("Canned response <strong>" . escapeHtml($row['canned_response_name']) . "</strong> deleted", 'error');

    redirect();

}
