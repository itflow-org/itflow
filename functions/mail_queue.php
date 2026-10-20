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
 *   3  sent - its body, calendar file and attachments are cleared then, as
 *      they can carry share keys and temporary passwords
 *   4  cancelled by an admin - never picked up again
 * Sent, failed and cancelled email is deleted after 90 days (nightly).
 */

const MAIL_QUEUE_TRIES = 4;
const MAIL_QUEUE_RETRY_MINUTES = 30;
const MAIL_QUEUE_KEEP_DAYS = 90;
const MAIL_QUEUE_CANCELLED = 4;

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
 * classes, a line under it and the exact time for its title. $row has
 * email_status, email_attempts and its times.
 */
function mailQueueState(array $row) {
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
            return $status === 2 || $status === MAIL_QUEUE_CANCELLED;
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
        'retry' => "email_status IN (2, $cancelled)",
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
        mysqli_query($mysqli, "UPDATE email_queue SET email_status = 0, email_attempts = 0, email_failed_at = NULL, email_queued_at = LEAST(email_queued_at, NOW())
            WHERE email_id IN ($done_list) AND $where");
    } elseif ($action === 'cancel') {
        mysqli_query($mysqli, "UPDATE email_queue SET email_status = $cancelled, email_failed_at = NOW() WHERE email_id IN ($done_list) AND $where");
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
        . (MAIL_QUEUE_TRIES - 1) . ' more times, ' . MAIL_QUEUE_RETRY_MINUTES . ' minutes apart. Email is kept ' . MAIL_QUEUE_KEEP_DAYS . ' days; a sent one without what it said.', 'url' => '', 'action' => '', 'waiting' => 0];
}
