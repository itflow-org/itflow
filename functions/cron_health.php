<?php

/*
 * ITFlow - Cron health
 *
 * Is cron running, and is every job that's on keeping up? Maintenance > Cron
 * already records each job's last run, how it went and its error (cron_jobs);
 * this reads them and says so:
 *   - an email when a job fails, gets stuck or falls behind, again once a day
 *     while it lasts, and when it's fixed (the Cron Watch job, cron/cron_watch.php),
 *   - a health address for an uptime monitor (/health.php) - the only thing
 *     that can tell anyone cron itself has stopped, since cron sends the email.
 *
 * Settings (database 3.7.9): config_cron_alert_email (1 = email),
 * config_cron_alert_to (addresses; empty = every active admin),
 * config_cron_health_key (the health address's key) and config_cron_alert_state
 * (JSON: 'sent' - each problem's key => when it was last emailed; 'waiting' -
 * each job that's on but has never run => since when). Set on Maintenance >
 * Cron's Alerts card (admin/post/cron.php).
 */

// A job still running after this many minutes counts as stuck
const CRON_HEALTH_STUCK_MINUTES = 120;

// The dispatcher writes its heartbeat every minute - older than this and cron isn't running
const CRON_HEALTH_HEARTBEAT_SECONDS = 300;

/** Whether the 3.7.9 update has run - the alert settings are there. */
function cronHealthReady() {
    global $mysqli;
    static $ready = null;
    if ($ready === null) {
        if (defined('CURRENT_DATABASE_VERSION')) {
            $version = CURRENT_DATABASE_VERSION;
        } else {
            $result = mysqli_query($mysqli, "SELECT config_current_database_version FROM settings WHERE company_id = 1");
            $version = $result && ($row = mysqli_fetch_row($result)) ? (string) $row[0] : '0';
        }
        $ready = version_compare($version, '3.7.9', '>=');
    }
    return $ready;
}

/** The cron settings this reads, fresh from the database (a job runs in a long process). */
function cronHealthSettings() {
    global $mysqli;
    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT config_enable_cron, config_cron_last_dispatch_at, config_cron_alert_email,
        config_cron_alert_to, config_cron_health_key, config_cron_alert_state FROM settings WHERE company_id = 1"));
    $state = json_decode((string) ($row['config_cron_alert_state'] ?? ''), true);
    $state = is_array($state) ? $state : [];
    $row['state'] = ['sent' => (array) ($state['sent'] ?? []), 'waiting' => (array) ($state['waiting'] ?? [])];
    return $row;
}

function cronHealthSaveState(array $state) {
    global $mysqli;
    $json = escapeSql(json_encode(['sent' => (object) $state['sent'], 'waiting' => (object) $state['waiting']]));
    mysqli_query($mysqli, "UPDATE settings SET config_cron_alert_state = '$json' WHERE company_id = 1");
}

/*
 * The switched-on jobs cron is expected to run, with their rows: name => row + label.
 * A job a manager has paused (a read-only or suspended managed instance) isn't expected to.
 */
function cronHealthJobs() {
    global $mysqli;
    require_once __DIR__ . '/../includes/cron_jobs.php';
    $mode = managedMode();
    $kept = $mode === 'active' ? null : (MANAGED_KEEP_JOBS[$mode] ?? []);
    $rows = [];
    $result = mysqli_query($mysqli, "SELECT cron_job_name, cron_job_schedule, cron_job_interval_minutes, cron_job_daily_at, cron_job_last_run_at,
        cron_job_last_status, cron_job_last_error, cron_job_last_error_at FROM cron_jobs WHERE cron_job_enabled = 1");
    while ($row = mysqli_fetch_assoc($result)) {
        $rows[$row['cron_job_name']] = $row;
    }
    $jobs = [];
    foreach (cronJobRegistry() as $entry) {
        if (!isset($rows[$entry['name']]) || ($kept !== null && !in_array($entry['name'], $kept, true))) {
            continue;
        }
        $jobs[$entry['name']] = $rows[$entry['name']] + ['label' => $entry['label']];
    }
    return $jobs;
}

/*
 * Whether a job has gone too long without a run. An interval job may miss one
 * run - late is past two intervals, and never less than ten minutes over. A
 * daily job is late two hours after its time went by without a run.
 */
function cronHealthOverdue(array $job) {
    $last_run = $job['cron_job_last_run_at'] ? strtotime($job['cron_job_last_run_at']) : 0;
    if ($job['cron_job_schedule'] === 'Daily') {
        $occurrence = strtotime(date('Y-m-d') . ' ' . substr((string) $job['cron_job_daily_at'], 0, 5) . ':00');
        if ($occurrence > time()) {
            $occurrence -= 86400;
        }
        return $last_run < $occurrence && time() - $occurrence > 2 * 3600;
    }
    $interval = max(1, intval($job['cron_job_interval_minutes']));
    return time() - $last_run > ($interval + max(10, $interval)) * 60;
}

/** The longest a job may wait for its first run, in seconds - as long as it may go between runs. */
function cronHealthFirstRunGap(array $job) {
    if ($job['cron_job_schedule'] === 'Daily') {
        return 26 * 3600;
    }
    $interval = max(1, intval($job['cron_job_interval_minutes']));
    return ($interval + max(10, $interval)) * 60;
}

/*
 * What's wrong, as a list of problems - empty when all is well. Each:
 *   key     the same for as long as the problem lasts (what the emails go by)
 *   job     the job's name, or '' for cron itself
 *   label   the job's label, or 'Cron'
 *   kind    not_running, off, failed, stuck or overdue
 *   text    a sentence for people
 *   detail  the job's error, or ''
 * $fresh reads again - the answer is kept for the rest of the request.
 */
function cronHealthProblems($fresh = false) {
    static $problems = null;
    if ($problems !== null && !$fresh) {
        return $problems;
    }
    $problems = [];
    if (!cronHealthReady()) {
        return $problems;
    }

    $settings = cronHealthSettings();
    $heartbeat = $settings['config_cron_last_dispatch_at'] ?? null;
    $running = $heartbeat && time() - strtotime($heartbeat) < CRON_HEALTH_HEARTBEAT_SECONDS;
    if (!$running) {
        $problems[] = ['key' => 'not_running', 'job' => '', 'label' => 'Cron', 'kind' => 'not_running', 'detail' => '',
            'text' => ($heartbeat ? 'Cron isn\'t running - it last ran ' . timeAgo($heartbeat) . '.' : 'Cron has never run on this server.')
                . ' No email goes out, no email becomes a ticket and nothing is invoiced until it does.'];
    }
    if (intval($settings['config_enable_cron'] ?? 0) !== 1) {
        // Every job stops itself, so none of them is late either
        $problems[] = ['key' => 'off', 'job' => '', 'label' => 'Cron', 'kind' => 'off', 'detail' => '',
            'text' => 'Cron is switched off on Maintenance > Cron - every job stops itself until it\'s turned back on.'];
        return $problems;
    }

    foreach (cronHealthJobs() as $name => $job) {
        $label = $job['label'];
        $status = (string) $job['cron_job_last_status'];

        if (!$job['cron_job_last_run_at']) {
            // Never run: late once it has waited longer than it may go between runs, counted from when the
            // Cron Watch job first saw it waiting (cron_jobs keeps no "switched on since")
            $waiting = intval($settings['state']['waiting'][$name] ?? 0);
            if ($running && $waiting && time() - $waiting > cronHealthFirstRunGap($job)) {
                $problems[] = ['key' => "never:$name", 'job' => $name, 'label' => $label, 'kind' => 'overdue', 'detail' => '',
                    'text' => "$label hasn't run yet - it has waited " . preg_replace('/ ago$/', '', timeAgo(date('Y-m-d H:i:s', $waiting))) . ' for its first run.'];
            }
            continue;
        }

        if ($status === 'Failed') {
            $problems[] = ['key' => "failed:$name", 'job' => $name, 'label' => $label, 'kind' => 'failed',
                'text' => "$label failed " . timeAgo($job['cron_job_last_error_at'] ?: $job['cron_job_last_run_at']) . '.',
                'detail' => mb_substr(trim((string) $job['cron_job_last_error']), 0, 500)];
        } elseif ($status === 'Running' && time() - strtotime($job['cron_job_last_run_at']) > CRON_HEALTH_STUCK_MINUTES * 60) {
            $problems[] = ['key' => "stuck:$name", 'job' => $name, 'label' => $label, 'kind' => 'stuck', 'detail' => '',
                'text' => "$label started " . timeAgo($job['cron_job_last_run_at']) . ' and is still running - it may have stopped part way.'];
        } elseif ($running && cronHealthOverdue($job)) {
            // Late only means something while cron is running - otherwise "isn't running" says it all
            $problems[] = ['key' => "overdue:$name", 'job' => $name, 'label' => $label, 'kind' => 'overdue', 'detail' => '',
                'text' => "$label is behind - it last ran " . timeAgo($job['cron_job_last_run_at']) . '.'];
        }
    }
    return $problems;
}

/** Who the alerts go to: name keyed by address - the addresses set, or every active admin with one. */
function cronHealthRecipients($to = null) {
    global $mysqli;
    $to = $to ?? (string) (cronHealthReady() ? (cronHealthSettings()['config_cron_alert_to'] ?? '') : '');
    $set = cronHealthAddresses($to)[0];
    if ($set) {
        return array_fill_keys($set, '');
    }
    $recipients = [];
    $result = mysqli_query($mysqli, "SELECT user_name, user_email FROM users JOIN user_roles ON role_id = user_role_id
        WHERE role_is_admin = 1 AND user_type = 1" . serviceAccountsOutSql() . " AND user_status = 1 AND user_archived_at IS NULL ORDER BY user_name");
    while ($row = mysqli_fetch_assoc($result)) {
        if (filter_var($row['user_email'], FILTER_VALIDATE_EMAIL)) {
            $recipients[$row['user_email']] = $row['user_name'];
        }
    }
    return $recipients;
}

/** Addresses typed with commas, semicolons or spaces between: [good, not addresses]. */
function cronHealthAddresses($text) {
    $good = $bad = [];
    foreach (preg_split('/[\s,;]+/', trim((string) $text)) as $address) {
        if ($address === '') {
            continue;
        }
        if (filter_var($address, FILTER_VALIDATE_EMAIL)) {
            $good[strtolower($address)] = $address;
        } else {
            $bad[] = $address;
        }
    }
    return [array_values($good), $bad];
}

/** Queue an alert to everyone it goes to - or to $to's addresses (a test of ones typed). Returns how many it was queued for. */
function cronHealthMail($subject, array $email, $to = null) {
    global $config_base_url;
    $to = $to ?? (string) (cronHealthSettings()['config_cron_alert_to'] ?? '');
    $footnote = cronHealthAddresses($to)[0] ? 'This address is set to get cron alerts on Maintenance > Cron.' : 'You get this as an admin of ITFlow - Maintenance > Cron says who cron alerts go to.';
    $recipients = [];
    foreach (cronHealthRecipients($to) as $address => $name) {
        $recipients[] = ['email' => $address, 'name' => $name, 'footnote' => $footnote];
    }
    if (!$recipients) {
        return 0;
    }
    $email += ['kind' => 'Cron', 'button' => ['label' => 'Open Maintenance > Cron', 'url' => 'https://' . $config_base_url . '/admin/cron.php']];
    return count(emailQueue($recipients, $subject, $email));
}

/*
 * The Cron Watch job: email about problems that are new, still there a day
 * later, or gone. Cron is running when this runs, so "isn't running" never
 * comes up here - that's what the health address is for. Also notes when it
 * first saw each job waiting for its first run. Returns lines for the log.
 */
function cronHealthWatch() {
    $settings = cronHealthSettings();
    $state = $settings['state'];

    // Jobs that are on and have never run: since when (kept), the rest let go
    $waiting = [];
    foreach (cronHealthJobs() as $name => $job) {
        if (!$job['cron_job_last_run_at']) {
            $waiting[$name] = intval($state['waiting'][$name] ?? 0) ?: time();
        }
    }
    $state['waiting'] = $waiting;
    cronHealthSaveState($state);

    $problems = array_values(array_filter(cronHealthProblems(true), function ($problem) {
        return $problem['kind'] !== 'not_running' && $problem['job'] !== 'cron_watch';
    }));

    $now = time();
    $new = $reminders = $sent = [];
    foreach ($problems as $problem) {
        $last = intval($state['sent'][$problem['key']] ?? 0);
        if (!$last) {
            $new[] = $problem;
            $sent[$problem['key']] = $now;
        } elseif ($now - $last >= 86400) {
            $reminders[] = $problem;
            $sent[$problem['key']] = $now;
        } else {
            $sent[$problem['key']] = $last;
        }
    }
    $fixed = array_diff_key($state['sent'], $sent);
    $state['sent'] = $sent;
    cronHealthSaveState($state);

    if (!$new && !$reminders && !$fixed) {
        return [$problems ? count($problems) . ' problem(s), already emailed' : 'All well'];
    }
    if (intval($settings['config_cron_alert_email'] ?? 1) !== 1) {
        return ['Email alerts are off - ' . count($problems) . ' problem(s)'];
    }
    if (!emailReady()) {
        return ['No mail server is set up - ' . count($problems) . ' problem(s)'];
    }

    $count = cronHealthAlert($new, $reminders, count($fixed), count($problems));
    return ['Emailed ' . $count . ' - new ' . count($new) . ', reminders ' . count($reminders) . ', fixed ' . count($fixed)];
}

/*
 * The alert email: the problems that are new and those still there a day
 * later, how many were fixed, and how many there are now - an all clear when
 * none. Returns how many it was queued for.
 */
function cronHealthAlert(array $new, array $reminders, $fixed, $total) {
    $list = function (array $items) {
        $html = '<ul>';
        foreach ($items as $problem) {
            $html .= '<li>' . escapeHtml($problem['text']) . ($problem['detail'] !== '' ? '<br><small>' . escapeHtml($problem['detail']) . '</small>' : '') . '</li>';
        }
        return $html . '</ul>';
    };
    if (!$total) {
        return cronHealthMail('ITFlow cron: all clear', [
            'tone' => 'success', 'title' => 'Cron: all clear',
            'intro' => ['Every cron job is keeping up again - nothing needs looking at.'],
        ]);
    }
    $content = '';
    if ($new) {
        $content .= '<p><strong>New:</strong></p>' . $list($new);
    }
    if ($reminders) {
        $content .= '<p><strong>Still not fixed:</strong></p>' . $list($reminders);
    }
    if ($fixed) {
        $content .= '<p><strong>Fixed since the last email:</strong> ' . $fixed . ' problem' . ($fixed === 1 ? '' : 's') . '.</p>';
    }
    return cronHealthMail("ITFlow cron needs attention ($total)", [
        'tone' => 'danger', 'title' => 'Cron needs attention',
        'intro' => [$total === 1 ? 'A cron job on ITFlow needs looking at.' : "$total cron jobs on ITFlow need looking at."],
        'content' => $content,
    ]);
}

/** The key in the health address - made the first time it's asked for, or new with $renew. */
function cronHealthKey($renew = false) {
    global $mysqli;
    $key = (string) (cronHealthSettings()['config_cron_health_key'] ?? '');
    if ($key === '' || $renew) {
        $key = bin2hex(random_bytes(20));
        mysqli_query($mysqli, "UPDATE settings SET config_cron_health_key = '$key' WHERE company_id = 1");
    }
    return $key;
}

function cronHealthUrl() {
    global $config_base_url;
    return 'https://' . $config_base_url . '/health.php?key=' . cronHealthKey();
}
