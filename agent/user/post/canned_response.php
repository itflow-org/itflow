<?php

/*
 * Your own canned responses (Your account > Canned Responses) - making,
 * changing, copying, archiving and deleting them, with their files, and
 * copying a shared one to make it yours. Saving a reply on a ticket as one
 * (Save your reply as a canned response, in the picker) is the same as making one, answered
 * in JSON so the reply being written stays. Only the agent's own: the shared
 * ones are Admin > Canned Responses' (admin/post/canned_response.php), where
 * an admin shares one of an agent's with everyone. The work is in
 * functions/canned_response.php.
 */

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

/* One of the agent's own a request is for - or back to their list, saying it isn't there. */
function cannedResponseYoursForRequest($canned_response_id) {
    global $session_user_id;
    $row = cannedResponseRow($canned_response_id);
    if (!$row || intval($row['canned_response_user_id']) !== intval($session_user_id)) {
        flashAlert("That canned response isn't one of yours now - it may have been deleted or shared with everyone.", 'error');
        redirect('user_canned_responses.php');
    }
    return $row;
}

if (isset($_POST['add_your_canned_response']) || isset($_POST['edit_your_canned_response'])) {

    validateCSRFToken();

    // Saved from a reply on a ticket - answered in JSON, the page stays as it is
    $json = !empty($_POST['respond_json']);
    $answer = function (array $data) {
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    };

    if (intval(lookupUserPermission('module_support')) < 2) {
        if ($json) {
            $answer(['ok' => false, 'error' => permissionRefusedText('module_support', 2, intval(lookupUserPermission('module_support')))]);
        }
        enforceUserPermission('module_support', 2);
    }

    $editing = isset($_POST['edit_your_canned_response']);
    $canned_response_id = $editing ? intval(cannedResponseYoursForRequest($_POST['canned_response_id'] ?? 0)['canned_response_id']) : 0;

    $fields = cannedResponseFields($_POST, $error);
    if (!$fields) {
        if ($json) {
            $answer(['ok' => false, 'error' => $error]);
        }
        flashAlert(escapeHtml($error), 'error');
        redirect();
    }

    // An admin can share one they make with everyone straight away
    $shared = !$editing && $session_is_admin && !empty($_POST['share']);
    $canned_response_id = cannedResponseSave($canned_response_id, $fields, $shared ? 0 : $session_user_id);
    $name = escapeSql($fields['name']);

    // Its files - the ones ticked off go, the new ones are kept
    cannedResponseRemoveFiles($canned_response_id, (array) ($_POST['remove_file_ids'] ?? []));
    $refused = cannedResponseSaveFiles($canned_response_id);

    // The ticket it was saved from, if this agent may see it
    $from_ticket = $json ? ticketRow(intval($_POST['ticket_id'] ?? 0)) : null;
    if ($from_ticket && intval($from_ticket['ticket_client_id']) && !hasClientAccess($from_ticket['ticket_client_id'])) {
        $from_ticket = null;
    }
    logAudit("Canned Response", $editing ? "Edit" : "Create", "$session_name " . ($editing ? 'edited' : 'created') . ($shared ? ' shared' : ' their own') . " canned response $name"
        . ($from_ticket ? ' from a reply on ' . escapeSql(ticketReference($from_ticket)) : ''), 0, $canned_response_id);

    $refused_text = $refused ? " - but not " . implode(', ', $refused) . ", a kind a ticket doesn't take or more than " . CANNED_RESPONSE_MAX_FILES . " files" : '';

    if ($json) {
        // Where the picker on that ticket offers it now, if it does: Yours, or for a shared one its category's group or Every ticket
        $group = '';
        $ticket_category_id = intval($from_ticket['ticket_category'] ?? 0);
        if (!$fields['category_ids'] || in_array($ticket_category_id, $fields['category_ids'], true)) {
            $group = $shared ? ($fields['category_ids'] ? (string) getFieldById('categories', $ticket_category_id, 'category_name') : 'Every ticket') : 'Yours';
        }
        $row = cannedResponseRow($canned_response_id);
        $answer([
            'ok' => true,
            'id' => $canned_response_id,
            'name' => $fields['name'],
            'preview' => cannedResponsePreview($fields['body'], 160),
            'sets_up' => cannedResponseSetsUpHtml($row),
            'group' => $group,
            'shared' => $shared,
            'message' => $fields['name'] . ' saved as ' . ($shared ? 'a canned response shared with everyone' : 'one of your canned responses') . $refused_text
                . ($group === '' ? ". It isn't offered on this ticket's category." : '.'),
            'refused' => $refused,
        ]);
    }

    flashAlert("Canned response <strong>" . escapeHtml($fields['name']) . "</strong> " . ($editing ? 'saved' : 'created') . escapeHtml($refused_text), $refused ? 'error' : 'success');

    redirect();

}

if (isset($_GET['copy_your_canned_response']) || isset($_GET['copy_canned_response_to_yours'])) {

    validateCSRFToken();
    enforceUserPermission('module_support', 2);

    // One of yours, or a shared one on offer now, as a new one of yours
    if (isset($_GET['copy_your_canned_response'])) {
        $row = cannedResponseYoursForRequest($_GET['copy_your_canned_response']);
    } else {
        $row = cannedResponseRow($_GET['copy_canned_response_to_yours']);
        if (!$row || intval($row['canned_response_user_id']) !== 0 || $row['canned_response_archived_at']) {
            flashAlert("That canned response isn't shared now - it may have been archived or deleted.", 'error');
            redirect('user_canned_responses.php?show=shared');
        }
    }
    $new_id = cannedResponseCopy($row['canned_response_id'], $session_user_id);
    $new_name = (string) cannedResponseRow($new_id)['canned_response_name'];

    logAudit("Canned Response", "Create", "$session_name copied canned response " . escapeSql($row['canned_response_name']) . " as their own " . escapeSql($new_name), 0, $new_id);

    flashAlert("Canned response <strong>" . escapeHtml($row['canned_response_name']) . "</strong> copied as <strong>" . escapeHtml($new_name) . "</strong>, one of yours");

    redirect('user_canned_responses.php');

}

if (isset($_GET['archive_your_canned_response']) || isset($_GET['restore_your_canned_response'])) {

    validateCSRFToken();
    enforceUserPermission('module_support', 2);

    $archive = isset($_GET['archive_your_canned_response']);
    $row = cannedResponseYoursForRequest($archive ? $_GET['archive_your_canned_response'] : $_GET['restore_your_canned_response']);
    $canned_response_id = intval($row['canned_response_id']);

    // Archived, the picker doesn't offer it; restoring it is all it takes to offer it again
    mysqli_query($mysqli, "UPDATE canned_responses SET canned_response_archived_at = " . ($archive ? 'NOW()' : 'NULL') . " WHERE canned_response_id = $canned_response_id");

    logAudit("Canned Response", $archive ? "Archive" : "Restore", "$session_name " . ($archive ? 'archived' : 'restored') . " their own canned response " . escapeSql($row['canned_response_name']), 0, $canned_response_id);

    flashAlert("Canned response <strong>" . escapeHtml($row['canned_response_name']) . "</strong> " . ($archive ? 'archived' : 'restored'), $archive ? 'error' : 'success');

    redirect();

}

if (isset($_GET['delete_your_canned_response'])) {

    validateCSRFToken();
    enforceUserPermission('module_support', 2);

    $row = cannedResponseYoursForRequest($_GET['delete_your_canned_response']);
    $canned_response_id = intval($row['canned_response_id']);

    // Its categories, files and record of use go with it, by their foreign keys, and its folder of files; replies it went into stay as they are
    mysqli_query($mysqli, "DELETE FROM canned_responses WHERE canned_response_id = $canned_response_id");
    cannedResponseDeleteFiles($canned_response_id);

    logAudit("Canned Response", "Delete", "$session_name deleted their own canned response " . escapeSql($row['canned_response_name']));

    flashAlert("Canned response <strong>" . escapeHtml($row['canned_response_name']) . "</strong> deleted", 'error');

    redirect();

}
