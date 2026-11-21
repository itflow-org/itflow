<?php

/*
 * ITFlow - the mail queue (email_queue), as Maintenance > Mail Queue shows it.
 *
 * Everything ITFlow sends is queued (emailQueue(), functions/email.php) and
 * sent by the Mail Queue cron job (cron/mail_queue.php). An email's
 * email_status is where it is:
 *   0  waiting - or scheduled, while its email_queued_at is still ahead
 *   1  being sent this minute
 *   2  failed - tried again every MAIL_QUEUE_RETRY_MINUTES until it has had
 *      MAIL_QUEUE_TRIES; 99 tries means it isn't tried again (a bad address)
 *   3  sent - it keeps what it said, its calendar file and attachments, except
 *      one that carried a link or code that opens something (email_sensitive:
 *      a shared login, an invite, a password link, a signing or approval link),
 *      cleared as it goes - and a failed or cancelled one like it a day after
 *      it was queued (nightly, mailQueueClearSensitive())
 *   4  cancelled by an admin - never picked up again
 * email_error says why it last failed, or who cancelled it - cleared when it
 * is sent or retried. Sent, failed and cancelled email is deleted after 90
 * days (nightly).
 */

const MAIL_QUEUE_TRIES = 4;
const MAIL_QUEUE_RETRY_MINUTES = 30;
const MAIL_QUEUE_KEEP_DAYS = 90;
const MAIL_QUEUE_CANCELLED = 4;
// Pictures that were in an email as data, kept once each for the queue to attach inline (emailInlineImages()) - the web can't reach uploads/tmp
const MAIL_QUEUE_IMAGE_DIR = 'uploads/tmp/mail_images';

/* The tabs of the list - key => [label, icon, SQL for what is in it]. */
function mailQueueTabs() {
    $tries = MAIL_QUEUE_TRIES;
    return [
        '' => ['All', 'fa-inbox', '1 = 1'],
        'waiting' => ['Waiting', 'fa-hourglass-half', 'email_status = 0'],
        'sending' => ['Sending', 'fa-paper-plane', 'email_status = 1'],
        'retrying' => ['Retrying', 'fa-redo-alt', "email_status = 2 AND email_attempts < $tries"],
        'failed' => ['Failed', 'fa-exclamation-circle', "email_status = 2 AND email_attempts >= $tries"],
        'sent' => ['Sent', 'fa-check', 'email_status = 3'],
        'cancelled' => ['Cancelled', 'fa-ban', 'email_status = ' . MAIL_QUEUE_CANCELLED],
    ];
}

/*
 * Where an email stands, in words - key (as the tabs), label, the badge's
 * classes, a line under it, the exact time for its title, and why it failed
 * (error). $row has email_status, email_attempts, its times and
 * email_error.
 */
function mailQueueState(array $row) {
    $state = mailQueueStateOf($row);
    // Why it failed, or who cancelled it - not once it has gone, or while it waits again
    $state['error'] = in_array($state['key'], ['retrying', 'failed', 'cancelled'], true) ? mailQueueErrorText($row['email_error'] ?? '') : '';
    return $state;
}

function mailQueueStateOf(array $row) {
    $status = intval($row['email_status']);
    $tries = intval($row['email_attempts']);
    $when = function ($datetime) {
        return $datetime ? date('M j, Y g:i A', strtotime($datetime)) : '';
    };
    $queued = (string) $row['email_queued_at'];
    $failed = (string) ($row['email_failed_at'] ?? '');

    if ($status === 0 && $queued !== '' && strtotime($queued) > time()) {
        return ['key' => 'waiting', 'label' => 'Scheduled', 'badge' => 'text-bg-info', 'icon' => 'fa-calendar-alt',
            'detail' => 'Goes ' . date('M j, g:i A', strtotime($queued)), 'title' => 'Not before ' . $when($queued)];
    }
    if ($status === 0) {
        return ['key' => 'waiting', 'label' => 'Waiting', 'badge' => 'text-bg-primary', 'icon' => 'fa-hourglass-half',
            'detail' => 'Queued ' . timeAgo($queued), 'title' => 'Queued ' . $when($queued)];
    }
    if ($status === 1) {
        return ['key' => 'sending', 'label' => 'Sending', 'badge' => 'text-bg-warning', 'icon' => 'fa-paper-plane',
            'detail' => 'Going now', 'title' => ''];
    }
    if ($status === 2 && $tries < MAIL_QUEUE_TRIES) {
        $next = $failed !== '' ? strtotime($failed) + MAIL_QUEUE_RETRY_MINUTES * 60 : time();
        return ['key' => 'retrying', 'label' => 'Retrying', 'badge' => 'text-bg-warning', 'icon' => 'fa-redo-alt',
            'detail' => 'Tried ' . max(1, $tries) . ' of ' . MAIL_QUEUE_TRIES . ' · next ' . ($next > time() ? 'about ' . date('g:i A', $next) : 'any minute'),
            'title' => $failed !== '' ? 'Last failed ' . $when($failed) : ''];
    }
    if ($status === 2) {
        return ['key' => 'failed', 'label' => 'Failed', 'badge' => 'text-bg-danger', 'icon' => 'fa-exclamation-circle',
            'detail' => ($tries > MAIL_QUEUE_TRIES ? 'Not tried again' : 'Gave up after ' . MAIL_QUEUE_TRIES . ' tries') . ($failed !== '' ? ' · ' . timeAgo($failed) : ''),
            'title' => $failed !== '' ? 'Failed ' . $when($failed) : ''];
    }
    if ($status === 3) {
        $sent = (string) ($row['email_sent_at'] ?? '');
        return ['key' => 'sent', 'label' => 'Sent', 'badge' => 'text-bg-success', 'icon' => 'fa-check',
            'detail' => $sent !== '' ? 'Sent ' . timeAgo($sent) : '', 'title' => $sent !== '' ? 'Sent ' . $when($sent) : ''];
    }
    return ['key' => 'cancelled', 'label' => 'Cancelled', 'badge' => 'text-bg-secondary', 'icon' => 'fa-ban',
        'detail' => $failed !== '' ? 'Cancelled ' . timeAgo($failed) : '', 'title' => $failed !== '' ? 'Cancelled ' . $when($failed) : ''];
}

/*
 * What can be done with an email: retry (anything that didn't go - sent mail
 * has been cleared, and one going now is left alone), cancel (one still to
 * be sent or tried again) and delete (anything not going this minute).
 */
function mailQueueCan(array $row, $action) {
    $status = intval($row['email_status']);
    $tries = intval($row['email_attempts']);
    switch ($action) {
        case 'retry':
            // Not one whose link was cleared - there is nothing left to send
            return ($status === 2 || $status === MAIL_QUEUE_CANCELLED) && empty($row['email_cleared']);
        case 'cancel':
            return $status === 0 || ($status === 2 && $tries < MAIL_QUEUE_TRIES);
        case 'delete':
            return $status !== 1;
    }
    return false;
}

/*
 * Retry, cancel or delete emails - each only where it can be done
 * (mailQueueCan()), checked again in the UPDATE so one the worker took
 * meanwhile is left alone. The ids it was done to.
 */
function mailQueueAct($action, array $email_ids) {
    global $mysqli;
    $ids = array_values(array_unique(array_filter(array_map('intval', $email_ids))));
    if (!$ids) {
        return [];
    }
    $list = implode(',', $ids);
    $tries = MAIL_QUEUE_TRIES;
    $cancelled = MAIL_QUEUE_CANCELLED;
    $where = [
        'retry' => "email_status IN (2, $cancelled) AND email_content <> ''",
        'cancel' => "(email_status = 0 OR (email_status = 2 AND email_attempts < $tries))",
        'delete' => 'email_status <> 1',
    ][$action] ?? null;
    if ($where === null) {
        return [];
    }
    $done = [];
    $sql = mysqli_query($mysqli, "SELECT email_id FROM email_queue WHERE email_id IN ($list) AND $where");
    while ($row = mysqli_fetch_row($sql)) {
        $done[] = intval($row[0]);
    }
    if (!$done) {
        return [];
    }
    $done_list = implode(',', $done);
    if ($action === 'retry') {
        // From the start: a full set of tries, at the next run
        mysqli_query($mysqli, "UPDATE email_queue SET email_status = 0, email_attempts = 0, email_failed_at = NULL, email_queued_at = LEAST(email_queued_at, NOW())"
            . mailQueueErrorSql(null) . " WHERE email_id IN ($done_list) AND $where");
    } elseif ($action === 'cancel') {
        global $session_name;
        mysqli_query($mysqli, "UPDATE email_queue SET email_status = $cancelled, email_failed_at = NOW()"
            . mailQueueErrorSql('Cancelled by ' . stripslashes((string) ($session_name ?? 'an admin'))) . " WHERE email_id IN ($done_list) AND $where");
    } else {
        mysqli_query($mysqli, "DELETE FROM email_queue WHERE email_id IN ($done_list) AND $where");
    }
    return $done;
}

/*
 * Whether the queue is moving - the first thing in the way, as level
 * (danger/warning), title, text and where to put it right; or, when nothing
 * is, level '' and a line on how it runs. $waiting is how many are due and
 * have waited more than ten minutes.
 */
function mailQueueHealth() {
    global $mysqli, $config_smtp_provider;
    $settings = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT config_enable_cron, config_cron_last_dispatch_at FROM settings WHERE company_id = 1")) ?: [];
    $job = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT cron_job_enabled, cron_job_last_run_at, cron_job_last_error, cron_job_last_error_at FROM cron_jobs WHERE cron_job_name = 'mail_queue'")) ?: null;
    $stuck = mysqli_fetch_row(mysqli_query($mysqli, "SELECT COUNT(*), MIN(email_queued_at) FROM email_queue
        WHERE email_status = 0 AND email_queued_at <= NOW() - INTERVAL 10 MINUTE"));
    $waiting = intval($stuck[0] ?? 0);
    $oldest = $stuck[1] ?? null;
    $heard = $settings['config_cron_last_dispatch_at'] ?? null;
    $ago = function ($datetime) {
        if (!$datetime) {
            return 'never';
        }
        return abs(time() - strtotime($datetime)) < 60 ? 'less than a minute ago' : timeAgo($datetime);
    };
    $held = $waiting ? " $waiting " . ($waiting === 1 ? 'email has' : 'emails have') . ' been waiting since ' . date('M j, g:i A', strtotime($oldest)) . '.' : '';

    if (empty($config_smtp_provider)) {
        return ['level' => 'danger', 'key' => 'smtp', 'title' => 'Mail sending is off', 'text' => 'Nothing in the queue goes until it is set up.' . $held,
            'url' => 'settings_mail.php?tab=sending', 'action' => 'Set up sending', 'waiting' => $waiting];
    }
    if (empty($settings['config_enable_cron'])) {
        return ['level' => 'danger', 'key' => 'cron_off', 'title' => 'Cron is switched off', 'text' => 'No job runs, so no email is sent.' . $held,
            'url' => 'cron.php', 'action' => 'Maintenance > Cron', 'waiting' => $waiting];
    }
    if (!$heard || time() - strtotime($heard) >= 300) {
        return ['level' => 'danger', 'key' => 'cron_stopped', 'title' => 'Cron isn\'t running', 'text' => 'ITFlow last heard from it ' . $ago($heard) . ', so no email is being sent.' . $held,
            'url' => 'cron.php', 'action' => 'See how to set it up', 'waiting' => $waiting];
    }
    if ($job && !intval($job['cron_job_enabled'])) {
        return ['level' => 'warning', 'key' => 'job_off', 'title' => 'The Mail Queue job is switched off', 'text' => 'Cron is running, but this job isn\'t.' . $held,
            'url' => 'cron.php', 'action' => 'Maintenance > Cron', 'waiting' => $waiting];
    }
    if ($waiting) {
        $error = $job && $job['cron_job_last_error'] && $job['cron_job_last_error_at'] && strtotime($job['cron_job_last_error_at']) > time() - 3600
            ? ' Its last error: ' . mb_strimwidth((string) $job['cron_job_last_error'], 0, 200, '...') : '';
        return ['level' => 'warning', 'key' => 'slow', 'title' => 'Email is waiting longer than it should', 'text' => trim($held) . ' The Mail Queue job last ran ' . $ago($job['cron_job_last_run_at'] ?? null) . '.' . $error,
            'url' => 'cron.php', 'action' => 'Maintenance > Cron', 'waiting' => $waiting];
    }
    return ['level' => '', 'key' => 'ok', 'title' => '', 'text' => 'Sent by the Mail Queue job each minute - it last ran ' . $ago($job['cron_job_last_run_at'] ?? null) . '. A failed email is tried '
        . (MAIL_QUEUE_TRIES - 1) . ' more times, ' . MAIL_QUEUE_RETRY_MINUTES . ' minutes apart. Email is kept ' . MAIL_QUEUE_KEEP_DAYS . ' days - with what it said, apart from one with a link or code that opens something, cleared once it has gone.', 'url' => '', 'action' => '', 'waiting' => 0];
}

/* Whether email_queue has email_error yet - not until the 3.3.7 update has run. */
function mailQueueHasError() {
    global $mysqli;
    static $has = null;
    if ($has === null) {
        $result = mysqli_query($mysqli, "SHOW COLUMNS FROM email_queue LIKE 'email_error'");
        $has = $result && mysqli_num_rows($result) > 0;
    }
    return $has;
}

/*
 * Why an email failed, as kept with it - one line, 500 characters at most,
 * as the server said it (an address in <> stays). '' for none.
 */
function mailQueueErrorText($error) {
    $error = trim(preg_replace('/\s+/u', ' ', (string) $error));
    return mb_strlen($error) > 500 ? mb_substr($error, 0, 497) . '...' : $error;
}

/*
 * The SET clause for why an email failed (or NULL to clear it), to add to an
 * UPDATE of email_queue - ", email_error = '...'", or '' before the update
 * adding the column has run, so the rest of the UPDATE still happens.
 */
function mailQueueErrorSql($error) {
    if (!mailQueueHasError()) {
        return '';
    }
    $error = $error === null ? '' : mailQueueErrorText($error);
    return ', email_error = ' . ($error === '' ? 'NULL' : "'" . escapeSql($error) . "'");
}

/* The email_error column for a SELECT - itself, or NULL before the update. */
function mailQueueErrorColumn() {
    return mailQueueHasError() ? 'email_error' : 'NULL AS email_error';
}

/*
 * What an email's manifest (email_attachments) holds - ['files' => names of the files attached,
 * 'images' => [cid => app-relative path] of the pictures that go in its message (emailInlineImages())].
 */
function mailQueueAttachments($json) {
    $out = ['files' => [], 'images' => []];
    foreach ((array) json_decode((string) $json, true) as $attachment) {
        if (!is_array($attachment) || trim((string) ($attachment['name'] ?? '')) === '') {
            continue;
        }
        if (!empty($attachment['cid'])) {
            $out['images'][(string) $attachment['cid']] = (string) ($attachment['path'] ?? '');
        } else {
            $out['files'][] = basename((string) $attachment['name']);
        }
    }
    return $out;
}

/*
 * An email's message with its pictures back in it as data, to show in Admin > Mail Queue - the
 * message points at them by cid:, as the mail program that gets it reads them. One gone stays a cid:.
 */
function mailQueueShowImages($html, $json) {
    $dir = realpath(dirname(__DIR__) . '/' . MAIL_QUEUE_IMAGE_DIR);
    foreach (mailQueueAttachments($json)['images'] as $cid => $path) {
        $file = $dir ? realpath(dirname(__DIR__) . '/' . $path) : false;
        $info = $file && strpos($file, $dir . DIRECTORY_SEPARATOR) === 0 ? @getimagesize($file) : false;
        if ($info) {
            $html = str_replace('cid:' . $cid, 'data:' . $info['mime'] . ';base64,' . base64_encode((string) file_get_contents($file)), $html);
        }
    }
    return $html;
}

/*
 * Delete the pictures no email in the queue points at any more - left a day first, so one saved
 * for an email being queued this minute stays (nightly). How many went.
 */
function mailQueueImageCleanup() {
    global $mysqli;
    $dir = dirname(__DIR__) . '/' . MAIL_QUEUE_IMAGE_DIR;
    if (!is_dir($dir)) {
        return 0;
    }
    $kept = [];
    $sql = mysqli_query($mysqli, "SELECT email_attachments FROM email_queue WHERE email_attachments LIKE '%mail\\_images%'");
    while ($row = mysqli_fetch_row($sql)) {
        foreach (mailQueueAttachments($row[0])['images'] as $path) {
            $kept[basename($path)] = true;
        }
    }
    $removed = 0;
    foreach (glob("$dir/*") ?: [] as $file) {
        if (is_file($file) && !isset($kept[basename($file)]) && filemtime($file) < time() - 86400 && @unlink($file)) {
            $removed++;
        }
    }
    return $removed;
}

/* Is there an email_sensitive column yet? Database 3.7.1 adds it. */
function mailQueueHasSensitive() {
    global $mysqli;
    static $has = null;
    if ($has === null) {
        $result = mysqli_query($mysqli, "SHOW COLUMNS FROM email_queue LIKE 'email_sensitive'");
        $has = $result && mysqli_num_rows($result) > 0;
    }
    return $has;
}

/*
 * What an email that has gone keeps, for the UPDATE that marks it sent (cron/mail_queue.php):
 * everything, unless it carried a link or code that opens something - then what it said, its
 * calendar file and attachments are cleared. Before the update that says which those are,
 * every one is cleared, as all were.
 */
function mailQueueSentSql() {
    if (!mailQueueHasSensitive()) {
        return ", email_content = '', email_cal_str = '', email_attachments = ''";
    }
    return ", email_content = IF(email_sensitive = 1, '', email_content), email_cal_str = IF(email_sensitive = 1, '', email_cal_str),"
        . " email_attachments = IF(email_sensitive = 1, '', email_attachments)";
}

/*
 * Clear what a failed or cancelled email with a link or code said, a day after it was queued
 * - its tries are long over, and a new one is a new link anyway (nightly). How many.
 */
function mailQueueClearSensitive() {
    global $mysqli;
    if (!mailQueueHasSensitive()) {
        return 0;
    }
    mysqli_query($mysqli, "UPDATE email_queue SET email_content = '', email_cal_str = '', email_attachments = ''
        WHERE email_sensitive = 1 AND email_status IN (2, " . MAIL_QUEUE_CANCELLED . ") AND email_content <> '' AND email_queued_at < NOW() - INTERVAL 1 DAY");
    return mysqli_affected_rows($mysqli);
}

/*
 * The mail queue at a glance, for the pages that point to it - how many are
 * waiting (due now), retrying, and given up on in the last day and the last
 * week, and when one was last sent. Read once a request.
 */
function mailQueueSummary() {
    global $mysqli;
    static $summary = null;
    if ($summary !== null) {
        return $summary;
    }
    $tries = MAIL_QUEUE_TRIES;
    $row = mysqli_fetch_row(mysqli_query($mysqli, "SELECT
            COALESCE(SUM(email_status = 0 AND email_queued_at <= NOW()), 0),
            COALESCE(SUM(email_status = 2 AND email_attempts < $tries), 0),
            COALESCE(SUM(email_status = 2 AND email_attempts >= $tries AND email_failed_at >= NOW() - INTERVAL 1 DAY), 0),
            COALESCE(SUM(email_status = 2 AND email_attempts >= $tries AND email_failed_at >= NOW() - INTERVAL 7 DAY), 0),
            MAX(CASE WHEN email_status = 3 THEN email_sent_at END)
        FROM email_queue WHERE email_queued_at >= NOW() - INTERVAL " . MAIL_QUEUE_KEEP_DAYS . " DAY"));
    $summary = ['waiting' => intval($row[0] ?? 0), 'retrying' => intval($row[1] ?? 0), 'failed_day' => intval($row[2] ?? 0), 'failed_week' => intval($row[3] ?? 0), 'last_sent' => $row[4] ?? null];
    return $summary;
}
