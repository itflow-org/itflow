<?php

/*
 * Texts (agent/texts.php) - open a ticket from an unmatched text, dismiss one, mark one spam
 * (which blocks its number) or not, say a code isn't one, name a number, and delete spam
 * (functions/sms.php).
 */

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

/* The text a request names - inbound, the client's one this agent may reach, in one of the statuses given - or back with an error */
function textsForRequest($message_id, array $statuses): array
{
    $row = smsMessageRow($message_id);
    if (!$row || $row['sms_message_direction'] !== 'in' || !in_array($row['sms_message_status'], $statuses, true) || !hasClientAccess($row['sms_message_client_id'])) {
        flashAlert('That text has moved on - someone may have dealt with it already.', 'error');
        redirect('texts.php');
    }
    return $row;
}

if (isset($_POST['sms_open_ticket'])) {

    validateCSRFToken();
    enforceUserPermission('module_support', 2);

    $row = textsForRequest($_POST['id'] ?? 0, ['unmatched']);
    $number = (string) $row['sms_message_number'];

    $client_id = intval($_POST['client_id'] ?? 0);
    if ($client_id && (!hasClientAccess($client_id) || !mysqli_num_rows(mysqli_query($mysqli, "SELECT 1 FROM clients WHERE client_id = $client_id AND client_archived_at IS NULL")))) {
        flashAlert('That client isn\'t one you can open tickets for.', 'error');
        redirect();
    }
    $contact_id = intval($_POST['contact_id'] ?? 0);
    $contact = $contact_id && $client_id ? mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT contact_id, contact_name, contact_mobile FROM contacts WHERE contact_id = $contact_id AND contact_client_id = $client_id AND contact_archived_at IS NULL")) : null;
    $contact_id = $contact ? intval($contact['contact_id']) : 0;
    $subject = trim((string) ($_POST['subject'] ?? ''));
    if ($subject === '') {
        flashAlert('Give the ticket a subject.', 'error');
        redirect();
    }

    // Every unmatched text from the number lately goes on it, in the order they came
    $texts = mysqli_fetch_all(mysqli_query($mysqli, "SELECT sms_message_id, sms_message_body, sms_message_media, sms_message_created_at FROM sms_messages
        WHERE sms_message_direction = 'in' AND sms_message_status = 'unmatched' AND sms_message_number = '" . escapeSql($number) . "'
        AND sms_message_created_at > NOW() - INTERVAL " . SMS_CONVERSATION_DAYS . " DAY ORDER BY sms_message_id ASC LIMIT 20"), MYSQLI_ASSOC);
    $ids = array_map('intval', array_column($texts, 'sms_message_id'));
    if (!in_array(intval($row['sms_message_id']), $ids, true)) {
        $ids[] = intval($row['sms_message_id']);
        $texts[] = $row;
    }
    $words = [];
    $media = [];
    foreach ($texts as $text) {
        if (trim((string) $text['sms_message_body']) !== '') {
            $words[] = count($texts) > 1 ? date('M j, g:i A', strtotime($text['sms_message_created_at'])) . ":\n" . $text['sms_message_body'] : (string) $text['sms_message_body'];
        }
        $media = array_merge($media, $text['sms_message_media'] ? (json_decode($text['sms_message_media'], true) ?: []) : []);
    }

    $ticket_id = smsTicketOpen(intval($row['sms_message_id']), $number, implode("\n\n", $words), array_slice($media, 0, SMS_MEDIA_MAX_FILES), $contact_id, $client_id, $subject, intval($session_user_id), $ids);
    mysqli_query($mysqli, "UPDATE sms_messages SET sms_message_status = 'ticket', sms_message_handled_at = NOW(), sms_message_handled_by = " . intval($session_user_id) . " WHERE sms_message_id IN (" . implode(',', $ids) . ")");

    if (!empty($_POST['assign_me'])) {
        mysqli_query($mysqli, "UPDATE tickets SET ticket_assigned_to = " . intval($session_user_id) . ", ticket_status = 2 WHERE ticket_id = $ticket_id");
    }

    // Their next text finds them - when they have no mobile yet
    $saved = '';
    if ($contact && !empty($_POST['save_number']) && trim((string) $contact['contact_mobile']) === '' && smsNumberIsPhone($number)) {
        [$cc, $national] = smsNumberParts($number);
        mysqli_query($mysqli, "UPDATE contacts SET contact_mobile = '" . escapeSql($national) . "', contact_mobile_country_code = '" . escapeSql($cc) . "' WHERE contact_id = $contact_id");
        $saved = ' - ' . smsNumberText($number) . ' saved as ' . $contact['contact_name'] . '\'s mobile';
    }

    $reference = ticketReference(ticketRow($ticket_id));
    logAudit("Text Message", "Edit", "$session_name opened ticket " . escapeSql($reference) . " from " . count($ids) . " text" . (count($ids) === 1 ? '' : 's') . " from " . escapeSql(smsNumberText($number) . $saved), $client_id, $ticket_id);
    flashAlert("Ticket <strong>" . escapeHtml($reference) . "</strong> opened from the text" . (count($ids) === 1 ? '' : 's') . escapeHtml($saved) . " - Text reply on it texts them back");
    redirect('/agent/' . ticketUrl($ticket_id, $client_id));

}

// Ask a contact by text whether texts about their work are all right - a YES turns them on
if (isset($_GET['sms_ask_opt_in'])) {

    validateCSRFToken();
    enforceUserPermission('module_client', 2);

    $contact_id = intval($_GET['sms_ask_opt_in']);
    $contact = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT contact_name, contact_client_id FROM contacts WHERE contact_id = $contact_id"));
    if (!$contact || !hasClientAccess($contact['contact_client_id'])) {
        flashAlert('That contact no longer exists.', 'error');
        redirect();
    }
    $asked = smsOptInAsk($contact_id, intval($session_user_id));
    if (!$asked['ok']) {
        flashAlert('Not asked - ' . escapeHtml($asked['error']), 'error');
        redirect();
    }
    logAudit("Contact", "Edit", "$session_name asked " . escapeSql($contact['contact_name']) . " by text whether texts are all right", intval($contact['contact_client_id']), $contact_id);
    flashAlert('<strong>' . escapeHtml($contact['contact_name']) . '</strong> asked by text - a YES turns texts on for them');
    redirect();

}

if (isset($_GET['sms_dismiss'])) {

    validateCSRFToken();
    enforceUserPermission('module_support', 2);

    $row = textsForRequest($_GET['sms_dismiss'], ['unmatched']);
    mysqli_query($mysqli, "UPDATE sms_messages SET sms_message_status = 'dismissed', sms_message_handled_at = NOW(), sms_message_handled_by = " . intval($session_user_id) . " WHERE sms_message_id = " . intval($row['sms_message_id']));
    logAudit("Text Message", "Edit", "$session_name dismissed a text from " . escapeSql(smsNumberText($row['sms_message_number'])), intval($row['sms_message_client_id']));
    flashAlert('Text dismissed - it stays in the text log for ' . SMS_SPAM_KEEP_DAYS . ' days');
    redirect();

}

if (isset($_GET['sms_mark_spam'])) {

    validateCSRFToken();
    enforceUserPermission('module_support', 2);

    $row = textsForRequest($_GET['sms_mark_spam'], ['unmatched']);
    $number = (string) $row['sms_message_number'];
    smsNumberSet($number, ['blocked_at' => 'NOW()', 'blocked_by' => intval($session_user_id)]);
    mysqli_query($mysqli, "UPDATE sms_messages SET sms_message_status = 'spam', sms_message_handled_at = NOW(), sms_message_handled_by = " . intval($session_user_id) . "
        WHERE sms_message_direction = 'in' AND sms_message_number = '" . escapeSql($number) . "' AND sms_message_status IN ('unmatched', 'dismissed')");
    logAudit("Text Message", "Edit", "$session_name marked texts from " . escapeSql(smsNumberText($number)) . " as spam and blocked the number");
    flashAlert('<strong>' . escapeHtml(smsNumberText($number)) . '</strong> blocked - what it sends goes straight to Spam');
    redirect();

}

if (isset($_GET['sms_not_spam'])) {

    validateCSRFToken();
    enforceUserPermission('module_support', 2);

    $row = textsForRequest($_GET['sms_not_spam'], ['spam']);
    $number = (string) $row['sms_message_number'];
    smsNumberSet($number, ['blocked_at' => null, 'blocked_by' => 0]);
    mysqli_query($mysqli, "UPDATE sms_messages SET sms_message_status = 'unmatched', sms_message_handled_at = NULL, sms_message_handled_by = 0
        WHERE sms_message_direction = 'in' AND sms_message_number = '" . escapeSql($number) . "' AND sms_message_status = 'spam'");
    logAudit("Text Message", "Edit", "$session_name said texts from " . escapeSql(smsNumberText($number) . " aren't spam") . " and unblocked the number");
    flashAlert('<strong>' . escapeHtml(smsNumberText($number)) . '</strong> isn\'t spam - its texts are in Unmatched');
    redirect('texts.php?show=unmatched');

}

if (isset($_GET['sms_delete'])) {

    validateCSRFToken();
    enforceUserPermission('module_support', 2);

    $row = textsForRequest($_GET['sms_delete'], ['spam']);
    mysqli_query($mysqli, "DELETE FROM sms_messages WHERE sms_message_id = " . intval($row['sms_message_id']));
    flashAlert('Text deleted', 'error');
    redirect();

}

if (isset($_GET['sms_not_code'])) {

    validateCSRFToken();
    enforceUserPermission('module_credential', 2);

    $row = textsForRequest($_GET['sms_not_code'], ['code']);
    if ($row['sms_message_body'] === null) {
        flashAlert('Its words were wiped a day after it came.', 'error');
        redirect();
    }
    mysqli_query($mysqli, "UPDATE sms_messages SET sms_message_status = 'unmatched', sms_message_code = NULL WHERE sms_message_id = " . intval($row['sms_message_id']));
    logAudit("Text Message", "Edit", "$session_name said a text from " . escapeSql(smsNumberText($row['sms_message_number']) . " isn't a code") . ", and moved it to Unmatched");
    flashAlert('Moved to Unmatched');
    redirect();

}

if (isset($_POST['sms_label_number'])) {

    validateCSRFToken();

    $row = smsMessageRow($_POST['id'] ?? 0);
    $code = $row && $row['sms_message_status'] === 'code';
    if (!$row || $row['sms_message_direction'] !== 'in') {
        flashAlert('That text is gone.', 'error');
        redirect('texts.php');
    }
    enforceUserPermission($code ? 'module_credential' : 'module_support', $code ? 1 : 2);

    $number = (string) $row['sms_message_number'];
    $label = mb_substr(trim((string) ($_POST['label'] ?? '')), 0, 100);
    smsNumberSet($number, ['label' => $label !== '' ? $label : null]);
    logAudit("Text Message", "Edit", "$session_name " . ($label !== '' ? "named " . escapeSql(smsNumberText($number)) . " " . escapeSql($label) : "took the name off " . escapeSql(smsNumberText($number))));
    flashAlert($label !== '' ? '<strong>' . escapeHtml(smsNumberText($number)) . '</strong> is ' . escapeHtml($label) . ' now' : 'Name taken off');
    redirect();

}
