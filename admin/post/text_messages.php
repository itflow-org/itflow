<?php

/*
 * Maintenance > Text Messages - retry a text that failed (or was cancelled), cancel one
 * still waiting, delete one - one at a time or several. A retried text goes at the Text
 * Messages job's next run.
 */

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

/* Do one thing to texts - how many it was done to */
function textMessagesAct(array $ids, string $action): int
{
    global $mysqli;
    $done = 0;
    foreach (array_unique(array_filter(array_map('intval', $ids))) as $id) {
        $row = smsMessageRow($id);
        if (!$row || !smsLogCan($row, $action)) {
            continue;
        }
        if ($action === 'retry') {
            mysqli_query($mysqli, "UPDATE sms_messages SET sms_message_status = 'queued', sms_message_attempts = 0, sms_message_attempted_at = NULL, sms_message_error = NULL WHERE sms_message_id = $id");
        } elseif ($action === 'cancel') {
            mysqli_query($mysqli, "UPDATE sms_messages SET sms_message_status = 'cancelled', sms_message_error = 'Cancelled' WHERE sms_message_id = $id AND sms_message_status = 'queued'");
        } else {
            mysqli_query($mysqli, "DELETE FROM sms_messages WHERE sms_message_id = $id AND sms_message_status <> 'sending'");
        }
        $done += mysqli_affected_rows($mysqli) > 0 ? 1 : 0;
    }
    return $done;
}

foreach (['retry' => 'retry_text', 'cancel' => 'cancel_text', 'delete' => 'delete_text'] as $action => $key) {
    if (isset($_GET[$key])) {
        validateCSRFToken();
        $done = textMessagesAct([$_GET[$key]], $action);
        if (!$done) {
            flashAlert('That text can\'t be ' . ['retry' => 'retried', 'cancel' => 'cancelled', 'delete' => 'deleted'][$action] . ' - it may have gone already, or be going now.', 'error');
            redirect();
        }
        logAudit("Text Message", ['retry' => 'Edit', 'cancel' => 'Edit', 'delete' => 'Delete'][$action], "$session_name " . ['retry' => 'retried', 'cancel' => 'cancelled', 'delete' => 'deleted'][$action] . " text " . intval($_GET[$key]));
        flashAlert(['retry' => 'Text queued again - it goes at the next run of the Text Messages job', 'cancel' => 'Text cancelled - it won\'t be sent', 'delete' => 'Text deleted'][$action], $action === 'delete' ? 'error' : 'success');
        redirect();
    }
}

foreach (['retry' => 'bulk_retry_texts', 'cancel' => 'bulk_cancel_texts', 'delete' => 'bulk_delete_texts'] as $action => $key) {
    if (isset($_POST[$key])) {
        validateCSRFToken();
        $ids = (array) ($_POST['sms_message_ids'] ?? []);
        $done = textMessagesAct($ids, $action);
        $verb = ['retry' => 'retried', 'cancel' => 'cancelled', 'delete' => 'deleted'][$action];
        if ($done) {
            logAudit("Text Message", $action === 'delete' ? 'Delete' : 'Edit', "$session_name $verb $done text" . ($done === 1 ? '' : 's'));
        }
        $skipped = count(array_unique(array_filter(array_map('intval', $ids)))) - $done;
        flashAlert("<strong>$done</strong> text" . ($done === 1 ? '' : 's') . " $verb" . ($skipped ? " - $skipped couldn't be" : ''), $done ? ($action === 'delete' ? 'error' : 'success') : 'warning');
        redirect();
    }
}
