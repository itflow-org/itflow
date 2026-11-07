<?php

/*
 * Admin > Text Messaging - Add and Edit (asked first by check_sms_provider, which answers in
 * JSON, so what is wrong shows beside each field), turning a provider on and off, a new
 * address for texts to come back to, Delete, Send a test text, and putting back a number
 * that replied STOP or was blocked as spam (functions/sms.php). What changed is in the audit
 * log - the secret only as changed.
 */

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

if (isset($_POST['check_sms_provider']) || isset($_POST['add_sms_provider']) || isset($_POST['edit_sms_provider'])) {

    validateCSRFToken();

    $checking = isset($_POST['check_sms_provider']);
    $provider_id = intval($_POST['provider_id'] ?? 0);
    $before = $provider_id ? smsProviderRow($provider_id) : null;

    if ($provider_id && !$before) {
        if ($checking) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => false, 'errors' => ['provider' => 'That texting provider no longer exists - close this and look again.']]);
            exit;
        }
        flashAlert('That texting provider no longer exists.', 'error');
        redirect('text_messaging.php');
    }

    [$after, $errors] = smsProviderCheck($_POST, $before);

    if ($checking) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => !$errors, 'errors' => $errors]);
        exit;
    }

    // Only reached without the window's script - say the first thing wrong
    if ($errors) {
        flashAlert('Not saved - ' . escapeHtml(reset($errors)), 'error');
        redirect();
    }

    $name = $after['sms_provider_name'];
    $number = smsNumberText($after['sms_provider_number']);

    if (!$before) {
        $provider_id = smsProviderWrite($after);
        logAudit("Text Message", "Create", "$session_name added texting provider " . escapeSql("$name, texting from $number"), 0, $provider_id);
        flashAlert('<strong>' . escapeHtml($name) . '</strong> added, texting from <strong>' . escapeHtml($number) . '</strong> - paste the address texts come back to into its portal, then send a test text');
        redirect('text_messaging.php');
    }

    $lines = smsProviderChangeLines($before, $after);
    if (!$lines) {
        flashAlert('Nothing changed on <strong>' . escapeHtml($name) . '</strong>', 'info');
        redirect();
    }
    smsProviderWrite($after, $provider_id);
    logAudit("Text Message", "Edit", "$session_name changed texting provider " . escapeSql("$name: " . implode('; ', $lines)), 0, $provider_id);
    flashAlert('<strong>' . escapeHtml($name) . '</strong> saved - ' . escapeHtml(count($lines) === 1 ? $lines[0] : count($lines) . ' changes'));
    redirect();

}

// Turn a provider off - nothing is texted, and texts to the number aren't taken in - or on again
if (isset($_GET['turn_sms_provider_off']) || isset($_GET['turn_sms_provider_on'])) {

    validateCSRFToken();

    $on = isset($_GET['turn_sms_provider_on']);
    $row = smsProviderRow($on ? $_GET['turn_sms_provider_on'] : $_GET['turn_sms_provider_off']);
    if (!$row) {
        flashAlert('That texting provider no longer exists.', 'error');
        redirect('text_messaging.php');
    }
    $name = $row['sms_provider_name'];
    if (intval($row['sms_provider_active']) === ($on ? 1 : 0)) {
        flashAlert('<strong>' . escapeHtml($name) . '</strong> is ' . ($on ? 'on' : 'off') . ' already', 'info');
        redirect();
    }
    mysqli_query($mysqli, "UPDATE sms_providers SET sms_provider_active = " . ($on ? 1 : 0) . " WHERE sms_provider_id = " . intval($row['sms_provider_id']));
    logAudit("Text Message", "Edit", "$session_name turned texting provider " . escapeSql($name) . ($on ? ' on' : ' off'), 0, intval($row['sms_provider_id']));
    flashAlert($on
        ? '<strong>' . escapeHtml($name) . '</strong> turned on - texts go out and come in again'
        : '<strong>' . escapeHtml($name) . '</strong> turned off - nothing is texted, and texts to the number aren\'t taken in until it is on again');
    redirect();

}

// A new address for texts to come back to - the old one stops working at once
if (isset($_GET['new_sms_inbound_key'])) {

    validateCSRFToken();

    $row = smsProviderRow($_GET['new_sms_inbound_key']);
    if (!$row) {
        flashAlert('That texting provider no longer exists.', 'error');
        redirect('text_messaging.php');
    }
    mysqli_query($mysqli, "UPDATE sms_providers SET sms_provider_inbound_key = '" . escapeSql(randomString(40)) . "' WHERE sms_provider_id = " . intval($row['sms_provider_id']));
    logAudit("Text Message", "Edit", "$session_name made a new inbound address for texting provider " . escapeSql($row['sms_provider_name']), 0, intval($row['sms_provider_id']));
    flashAlert('New address made - paste it into <strong>' . escapeHtml($row['sms_provider_name']) . '</strong>\'s portal now, or texts to the number won\'t reach ITFlow', 'warning');
    redirect();

}

// Delete a provider - the texts sent and received stay in the log and on their tickets
if (isset($_GET['delete_sms_provider'])) {

    validateCSRFToken();

    $row = smsProviderRow($_GET['delete_sms_provider']);
    if (!$row) {
        flashAlert('That texting provider no longer exists.', 'error');
        redirect('text_messaging.php');
    }
    $provider_id = intval($row['sms_provider_id']);
    mysqli_query($mysqli, "DELETE FROM sms_providers WHERE sms_provider_id = $provider_id");
    // Texts waiting to go have nothing to go through now
    mysqli_query($mysqli, "UPDATE sms_messages SET sms_message_status = 'cancelled', sms_message_error = 'Its texting provider was deleted' WHERE sms_message_status = 'queued'");
    logAudit("Text Message", "Delete", "$session_name deleted texting provider " . escapeSql($row['sms_provider_name'] . ', which texted from ' . smsNumberText($row['sms_provider_number'])), 0, $provider_id);
    flashAlert('<strong>' . escapeHtml($row['sms_provider_name']) . '</strong> deleted - nothing more is texted, and texts to the number no longer reach ITFlow', 'error');
    redirect('text_messaging.php');

}

// Send a test text - to a number of your own, for real
if (isset($_POST['send_sms_test'])) {

    validateCSRFToken();

    $row = smsProviderRow($_POST['provider_id'] ?? 0);
    if (!$row || !intval($row['sms_provider_active'])) {
        flashAlert('Turn the texting provider on first.', 'error');
        redirect();
    }
    $number = smsNumber((string) ($_POST['number'] ?? ''));
    $text = trim((string) ($_POST['text'] ?? ''));
    if ($number === '') {
        flashAlert('That isn\'t a number a text can go to - with its dial code, like +1 412 555 0100.', 'error');
        redirect();
    }
    if ($text === '') {
        $text = 'A test text from ITFlow.';
    }
    $queued = smsQueue($number, $text, ['purpose' => 'test', 'user_id' => intval($session_user_id)]);
    $sent = $queued['ids'] ? smsSendNow($queued['ids']) : ['sent' => 0, 'error' => $queued['error']];
    $to = escapeHtml(smsNumberText($number));
    if ($sent['sent'] && $sent['error'] === '') {
        logAudit("Text Message", "Send", "$session_name sent a test text to " . escapeSql(smsNumberText($number)), 0, intval($row['sms_provider_id']));
        flashAlert("Test text sent to <strong>$to</strong> - text it back to see the reply come into Texts");
    } else {
        $error = $sent['error'] !== '' ? $sent['error'] : $queued['error'];
        logAudit("Text Message", "Send", "$session_name sent a test text to " . escapeSql(smsNumberText($number) . " - it didn't go: $error"), 0, intval($row['sms_provider_id']));
        flashAlert("The test text to <strong>$to</strong> didn't go - " . escapeHtml($error), 'error');
    }
    redirect();

}

// Put a number back - opted back in, when they asked by some other way than texting START, or unblocked
if (isset($_GET['sms_number_opt_in']) || isset($_GET['sms_number_unblock'])) {

    validateCSRFToken();

    $opt_in = isset($_GET['sms_number_opt_in']);
    $number = preg_replace('/\D/', '', (string) ($opt_in ? $_GET['sms_number_opt_in'] : $_GET['sms_number_unblock']));
    if (!smsNumberRow($number)) {
        flashAlert('That number isn\'t opted out or blocked.', 'error');
        redirect();
    }
    smsNumberSet($number, $opt_in ? ['opted_out_at' => null] : ['blocked_at' => null, 'blocked_by' => 0]);
    $number_text = smsNumberText($number);
    logAudit("Text Message", "Edit", "$session_name " . ($opt_in ? 'opted ' . escapeSql($number_text) . ' back in to texts' : 'unblocked ' . escapeSql($number_text)));
    flashAlert('<strong>' . escapeHtml($number_text) . '</strong> ' . ($opt_in ? 'can be texted again' : 'is unblocked - its texts come in as any other'));
    redirect();

}
