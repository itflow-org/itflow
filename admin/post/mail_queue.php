<?php

/*
 * Maintenance > Mail Queue (admin/mail_queue.php): retry an email from the
 * start, cancel it or delete it - one (retry_mail, cancel_mail, delete_mail)
 * or the ones ticked (bulk_retry_emails, bulk_cancel_emails,
 * bulk_delete_emails). Each is only done where it can be (mailQueueAct(),
 * functions/mail_queue.php) - a sent email can't be retried, as what it said
 * was cleared on sending, and one going this minute is left alone - and the
 * message says how many it was done to, and why any weren't.
 */

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

$mail_queue_actions = [
    'retry' => ['retry_mail', 'bulk_retry_emails', 'retried', 'Retry', ['it goes at the next run', 'they go at the next run'], 'A sent one can\'t be - what it said was cleared on sending - nor one waiting or going now.'],
    'cancel' => ['cancel_mail', 'bulk_cancel_emails', 'cancelled', 'Cancel', ['it won\'t be sent', 'they won\'t be sent'], 'Only one still to be sent or tried again can be.'],
    'delete' => ['delete_mail', 'bulk_delete_emails', 'deleted', 'Delete', ['', ''], 'One going this minute can\'t be.'],
];
// The Resend link before this page was rebuilt
if (isset($_GET['send_failed_mail'])) {
    $_GET['retry_mail'] = $_GET['send_failed_mail'];
}

foreach ($mail_queue_actions as $mail_queue_action => [$one_key, $bulk_key, $done_word, $log_action, $done_note, $why_not]) {
    $mail_queue_ids = null;
    if (isset($_GET[$one_key])) {
        $mail_queue_ids = [$_GET[$one_key]];
    } elseif (isset($_POST[$bulk_key])) {
        $mail_queue_ids = is_array($_POST['email_ids'] ?? null) ? $_POST['email_ids'] : [];
    }
    if ($mail_queue_ids === null) {
        continue;
    }

    validateCSRFToken();

    $asked = count(array_unique(array_filter(array_map('intval', $mail_queue_ids))));
    if (!$asked) {
        flashAlert('None were ticked.', 'warning');
        redirect();
    }

    $done = mailQueueAct($mail_queue_action, $mail_queue_ids);
    $count = count($done);

    if ($count) {
        $ids = implode(', ', array_map(function ($id) { return "#$id"; }, array_slice($done, 0, 20))) . ($count > 20 ? ' and ' . ($count - 20) . ' more' : '');
        logAudit("Email", $log_action, "$session_name $done_word " . ($count === 1 ? 'email' : "$count emails") . " in the mail queue: $ids", 0, $count === 1 ? $done[0] : 0);
    }

    $email_word = function ($n) {
        return $n === 1 ? 'email' : 'emails';
    };
    if ($count === $asked) {
        $note = $done_note[$count === 1 ? 0 : 1];
        flashAlert(ucfirst($done_word) . " <strong>$count</strong> " . $email_word($count) . ($note !== '' ? " - $note" : ''), $mail_queue_action === 'delete' ? 'error' : 'success');
    } elseif ($count) {
        flashAlert(ucfirst($done_word) . " <strong>$count</strong> of $asked " . $email_word($asked) . ". $why_not", 'warning');
    } else {
        flashAlert(($asked === 1 ? 'That email' : 'None of those emails') . " couldn't be $done_word. $why_not", 'error');
    }

    redirect();
}

unset($mail_queue_actions, $mail_queue_action, $mail_queue_ids, $one_key, $bulk_key, $done_word, $log_action, $done_note, $why_not);
