<?php

/*
 * Get started - the walk through what is left to set up once ITFlow is
 * installed (admin/getting_started.php, its steps in admin/getting_started/,
 * saved by admin/post/getting_started.php): cron, email, backups, billing,
 * support, the team, starter content and a first client - each a short form
 * of its own, or Skip for now.
 *
 * A step is done when the setting it is about is: cron heard from and on, a
 * way to send email, backups scheduled, an hourly rate, default SLAs, a
 * second agent, starter content, a client. So it ticks itself off however it
 * was set - here or on the settings page - and a step skipped can be done
 * later.
 *
 * On a new install (settings.config_getting_started, on in db.sql) the first
 * admin to sign in is taken to it once (config_getting_started_opened_at),
 * and the dashboard shows how far it has got until every step is done or
 * skipped, or an admin dismisses it. An install updated to 3.4.7 has it off,
 * and an admin opens it from Admin > Get started.
 */

/** The steps, in order - key => nav label, title, icon and what it is for. Billing and Support go with their modules. */
const GETTING_STARTED_STEPS = [
    'cron' => ['label' => 'Cron', 'title' => 'Background jobs', 'icon' => 'fa-clock',
        'about' => 'Everything that happens without someone clicking - email going out, mail turned into tickets, invoices made, backups taken - is run by cron, once a minute.'],
    'mail' => ['label' => 'Email', 'title' => 'Email', 'icon' => 'fa-envelope',
        'about' => 'The mailbox ITFlow sends from - ticket updates, invoices, quotes and alerts - and, if you like, a support mailbox it turns into tickets.'],
    'backups' => ['label' => 'Backups', 'title' => 'Backups', 'icon' => 'fa-save',
        'about' => 'A backup every night - the database and your files, locked with your backup key - and how many to keep.'],
    'billing' => ['label' => 'Billing', 'title' => 'Billing', 'icon' => 'fa-file-invoice-dollar', 'module' => 'accounting',
        'about' => 'What you charge an hour, when invoices are due, how invoices and quotes are numbered, your tax, and card payments.'],
    'support' => ['label' => 'Support', 'title' => 'Support', 'icon' => 'fa-life-ring', 'module' => 'ticketing',
        'about' => 'Your business hours, how fast you answer each priority, how tickets are numbered, and the client portal.'],
    'team' => ['label' => 'Team', 'title' => 'Your team', 'icon' => 'fa-users',
        'about' => 'The people who work tickets, send invoices and look after clients with you.'],
    'content' => ['label' => 'Content', 'title' => 'Starter content', 'icon' => 'fa-seedling',
        'about' => 'Ticket and project templates, canned responses, documents, vendors, licenses and products to start from - all yours to change.'],
    'client' => ['label' => 'First client', 'title' => 'Your first client', 'icon' => 'fa-user-friends',
        'about' => 'A client to raise tickets and send invoices for - or the demo data, to look around first.'],
];

/**
 * The standard response times the Support step offers - business minutes to
 * answer, by priority. No time to resolve: one can be added to an SLA in
 * Admin > SLAs.
 */
const GETTING_STARTED_SLAS = [
    'Urgent' => 60,
    'High' => 120,
    'Medium' => 240,
    'Low' => 480,
];

/** Has the 3.4.7 update run? Until it has, Get started isn't shown anywhere. */
function gettingStartedReady() {
    global $mysqli;
    static $ready = null;
    if ($ready === null) {
        if (defined('CURRENT_DATABASE_VERSION')) {
            $version = CURRENT_DATABASE_VERSION;
        } else {
            $result = mysqli_query($mysqli, "SELECT config_current_database_version FROM settings WHERE company_id = 1");
            $version = $result && ($row = mysqli_fetch_row($result)) ? (string) $row[0] : '0';
        }
        $ready = version_compare($version, '3.4.7', '>=');
    }
    return $ready;
}

/** Whether it is offered, the steps put off for now and when it was first opened - ['on', 'skipped' => [keys], 'opened_at']. */
function gettingStartedState($refresh = false) {
    global $mysqli;
    static $state = null;
    if ($state === null || $refresh) {
        $state = ['on' => false, 'skipped' => [], 'opened_at' => null];
        if (gettingStartedReady()) {
            $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT config_getting_started, config_getting_started_skipped, config_getting_started_opened_at FROM settings WHERE company_id = 1")) ?: [];
            $state = [
                'on' => intval($row['config_getting_started'] ?? 0) === 1,
                'skipped' => array_values(array_intersect(array_keys(GETTING_STARTED_STEPS), explode(',', (string) ($row['config_getting_started_skipped'] ?? '')))),
                'opened_at' => $row['config_getting_started_opened_at'] ?? null,
            ];
        }
    }
    return $state;
}

/** Where a step is - or the page itself, or the summary ('done'). */
function gettingStartedUrl($step = '') {
    return '/admin/getting_started.php' . ($step !== '' ? '?step=' . rawurlencode($step) : '');
}

/** The crontab line ITFlow needs. */
function gettingStartedCronCommand() {
    return '* * * * * php ' . dirname(__DIR__) . '/cron/cron.php >/dev/null';
}

/** The backup job's row - seeded from the registry, as the dispatcher would, when cron hasn't met it yet. */
function gettingStartedBackupJob() {
    global $mysqli;
    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT cron_job_enabled, cron_job_daily_at FROM cron_jobs WHERE cron_job_name = 'backup' LIMIT 1"));
    if ($row) {
        return ['enabled' => intval($row['cron_job_enabled']) === 1, 'daily_at' => substr((string) ($row['cron_job_daily_at'] ?? '02:00'), 0, 5) ?: '02:00', 'seeded' => true];
    }
    return ['enabled' => false, 'daily_at' => '02:00', 'seeded' => false];
}

/**
 * The steps this install has, in order, each with whether it is done - from
 * the settings themselves - skipped, and a line on where it stands.
 * key => GETTING_STARTED_STEPS' fields + done, skipped, status.
 */
function gettingStartedSteps() {
    global $mysqli, $config_module_enable_accounting, $config_module_enable_ticketing;
    $state = gettingStartedState();
    $settings = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT config_enable_cron, config_cron_last_dispatch_at, config_smtp_provider, config_mail_from_email,
        config_default_hourly_rate, config_default_net_terms, company_currency FROM settings JOIN companies ON companies.company_id = settings.company_id WHERE settings.company_id = 1")) ?: [];
    $count = function ($sql) use ($mysqli) {
        $result = mysqli_query($mysqli, $sql);
        return $result ? intval(mysqli_fetch_row($result)[0] ?? 0) : 0;
    };
    $steps = [];
    foreach (GETTING_STARTED_STEPS as $key => $step) {
        $module = $step['module'] ?? '';
        if (($module === 'accounting' && !intval($config_module_enable_accounting)) || ($module === 'ticketing' && !intval($config_module_enable_ticketing))) {
            continue;
        }
        $done = false;
        $status = '';
        switch ($key) {
            case 'cron':
                $heard = $settings['config_cron_last_dispatch_at'] ?? null;
                $running = $heard && time() - strtotime($heard) < 300;
                $on = intval($settings['config_enable_cron'] ?? 0) === 1;
                $done = $running && $on;
                $status = !$heard ? 'Not heard from yet' : ($running ? 'Ran ' . timeAgo($heard) : 'Last ran ' . timeAgo($heard)) . ($on ? '' : ' - switched off');
                break;
            case 'mail':
                $provider = (string) ($settings['config_smtp_provider'] ?? '');
                $done = $provider !== '' && trim((string) ($settings['config_mail_from_email'] ?? '')) !== '';
                $status = $done ? 'Sends as ' . $settings['config_mail_from_email'] : ($provider !== '' ? 'No address to send from' : 'Nothing is emailed yet');
                break;
            case 'backups':
                $job = gettingStartedBackupJob();
                $done = $job['enabled'];
                $status = $done ? 'Every night at ' . date('g:i A', strtotime($job['daily_at'])) : 'Not scheduled';
                break;
            case 'billing':
                $rate = floatval($settings['config_default_hourly_rate'] ?? 0);
                $done = $rate > 0;
                $status = $done ? billingMoney($rate, (string) ($settings['company_currency'] ?? '')) . ' an hour, ' . (intval($settings['config_default_net_terms'] ?? 0) ? 'due in ' . intval($settings['config_default_net_terms']) . ' days' : 'due on receipt') : 'No hourly rate yet';
                break;
            case 'support':
                $slas = $count("SELECT COUNT(*) FROM sla_assignments JOIN slas ON sla_id = sla_assignment_sla_id WHERE sla_assignment_client_id = 0 AND sla_archived_at IS NULL");
                $done = $slas > 0;
                $status = $done ? 'Response times for ' . $slas . ' ' . ($slas === 1 ? 'priority' : 'priorities') : 'No response times yet';
                break;
            case 'team':
                $agents = $count("SELECT COUNT(*) FROM users WHERE user_type = 1 AND user_status = 1 AND user_archived_at IS NULL");
                $done = $agents > 1;
                $status = $agents === 1 ? 'Just you' : "$agents people";
                break;
            case 'content':
                $templates = $count("SELECT (SELECT COUNT(*) FROM ticket_templates) + (SELECT COUNT(*) FROM canned_responses) + (SELECT COUNT(*) FROM document_templates)");
                $done = $templates > 0;
                $status = $done ? 'Templates to start from' : 'Nothing added yet';
                break;
            case 'client':
                $clients = $count("SELECT COUNT(*) FROM clients WHERE client_archived_at IS NULL");
                $done = $clients > 0;
                $status = $clients ? $clients . ' ' . ($clients === 1 ? 'client' : 'clients') : 'No clients yet';
                break;
        }
        $steps[$key] = $step + ['key' => $key, 'done' => $done, 'skipped' => !$done && in_array($key, $state['skipped'], true), 'status' => $status];
    }
    return $steps;
}

/** How far it has got - done (or skipped), of how many, the next step to do ('' when none is left) and whether it is finished. */
function gettingStartedProgress(array $steps = null) {
    $steps = $steps ?? gettingStartedSteps();
    $done = 0;
    $next = '';
    foreach ($steps as $key => $step) {
        if ($step['done'] || $step['skipped']) {
            $done++;
        } elseif ($next === '') {
            $next = $key;
        }
    }
    return ['done' => $done, 'total' => count($steps), 'next' => $next, 'finished' => $next === ''];
}

/** The step after this one, in order - 'done' (the summary) after the last. */
function gettingStartedAfter($key, array $steps = null) {
    $keys = array_keys($steps ?? gettingStartedSteps());
    $at = array_search($key, $keys, true);
    return $at === false || !isset($keys[$at + 1]) ? 'done' : $keys[$at + 1];
}

/** Put a step off for now, or take it back. */
function gettingStartedSkip($key, $skip = true) {
    global $mysqli;
    if (!isset(GETTING_STARTED_STEPS[$key]) || !gettingStartedReady()) {
        return;
    }
    $skipped = gettingStartedState(true)['skipped'];
    $skipped = $skip ? array_values(array_unique(array_merge($skipped, [$key]))) : array_values(array_diff($skipped, [$key]));
    mysqli_query($mysqli, "UPDATE settings SET config_getting_started_skipped = '" . escapeSql(implode(',', $skipped)) . "' WHERE company_id = 1");
    gettingStartedState(true);
}

/** Note that it was opened - it opens by itself on signing in only until then. */
function gettingStartedOpened() {
    global $mysqli;
    if (gettingStartedReady()) {
        mysqli_query($mysqli, "UPDATE settings SET config_getting_started_opened_at = NOW() WHERE company_id = 1 AND config_getting_started_opened_at IS NULL");
    }
}

/** Stop offering it - on the dashboard and on signing in. It stays in Admin. */
function gettingStartedDismiss() {
    global $mysqli;
    if (gettingStartedReady()) {
        mysqli_query($mysqli, "UPDATE settings SET config_getting_started = 0 WHERE company_id = 1");
        gettingStartedState(true);
    }
}

/** Should signing in take this user to Get started - an admin, on an install that offers it, before anyone has opened it? */
function gettingStartedFirstVisit($user_id) {
    global $mysqli;
    if (!gettingStartedReady()) {
        return false;
    }
    $state = gettingStartedState(true);
    if (!$state['on'] || $state['opened_at'] !== null) {
        return false;
    }
    $row = mysqli_fetch_row(mysqli_query($mysqli, "SELECT role_is_admin FROM users JOIN user_roles ON role_id = user_role_id WHERE user_id = " . intval($user_id) . " AND user_type = 1 LIMIT 1"));
    return intval($row[0] ?? 0) === 1;
}

/** Is the dashboard to show how far it has got - to an admin, while it is offered and not finished? ['steps', 'progress'] or null. */
function gettingStartedCard() {
    global $session_is_admin;
    if (empty($session_is_admin) || !gettingStartedReady() || !gettingStartedState()['on']) {
        return null;
    }
    $steps = gettingStartedSteps();
    $progress = gettingStartedProgress($steps);
    return $progress['finished'] ? null : ['steps' => $steps, 'progress' => $progress];
}

/*
 * ---------- what the steps save ----------
 */

/**
 * Schedule the nightly backup, or stop it - the backup job's row (made with
 * the registry's defaults when cron hasn't met it yet), and what it takes and
 * keeps. What changed, in words.
 */
function gettingStartedBackupSave($enabled, $daily_at, $type, $keep_count, $keep_days) {
    global $mysqli;
    require_once dirname(__DIR__) . '/includes/cron_jobs.php';
    $registry = cronJobRegistryByName()['backup'] ?? ['schedule' => 'Daily', 'daily_at' => '02:00', 'enabled' => 0];
    $before = gettingStartedBackupJob();
    $settings = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT config_backup_cron_type, config_backup_retention_count, config_backup_retention_days FROM settings WHERE company_id = 1")) ?: [];
    mysqli_query($mysqli, "INSERT IGNORE INTO cron_jobs SET cron_job_name = 'backup', cron_job_enabled = " . intval($registry['enabled'] ?? 0) . ",
        cron_job_schedule = 'Daily', cron_job_interval_minutes = 1, cron_job_daily_at = '" . escapeSql($registry['daily_at'] ?? '02:00') . ":00'");
    mysqli_query($mysqli, "UPDATE cron_jobs SET cron_job_enabled = " . ($enabled ? 1 : 0) . ", cron_job_schedule = 'Daily', cron_job_daily_at = '" . escapeSql($daily_at) . ":00' WHERE cron_job_name = 'backup'");
    mysqli_query($mysqli, "UPDATE settings SET config_backup_cron_type = '" . escapeSql($type) . "', config_backup_retention_count = " . intval($keep_count) . ",
        config_backup_retention_days = " . intval($keep_days) . " WHERE company_id = 1");

    $lines = [];
    if ($before['enabled'] !== (bool) $enabled) {
        $lines[] = $enabled ? 'nightly backups on' : 'nightly backups off';
    }
    if ($enabled && $before['daily_at'] !== $daily_at) {
        $lines[] = "at $daily_at";
    }
    if ((string) ($settings['config_backup_cron_type'] ?? '') !== $type) {
        $lines[] = (function_exists('backupTypeLabel') ? strtolower(backupTypeLabel($type)) : $type);
    }
    if (intval($settings['config_backup_retention_count'] ?? 0) !== intval($keep_count) || intval($settings['config_backup_retention_days'] ?? 0) !== intval($keep_days)) {
        $lines[] = 'keeping ' . intval($keep_count) . ($keep_days ? ' for up to ' . intval($keep_days) . ' days' : '');
    }
    return $lines;
}

/**
 * The standard response times as the default SLAs, for each priority that
 * has none - an SLA named "Standard - High" and so on, with no resolution
 * time, or one already there with the same response time and none - and open
 * tickets stamped with them. The SLAs given, priority => id.
 */
function gettingStartedApplySlas() {
    global $mysqli;
    $ids = [];
    foreach (GETTING_STARTED_SLAS as $priority => $response) {
        $assigned = mysqli_fetch_row(mysqli_query($mysqli, "SELECT 1 FROM sla_assignments JOIN slas ON sla_id = sla_assignment_sla_id
            WHERE sla_assignment_client_id = 0 AND sla_assignment_priority = '$priority' AND sla_archived_at IS NULL LIMIT 1"));
        if ($assigned) {
            continue;
        }
        $existing = intval(mysqli_fetch_row(mysqli_query($mysqli, "SELECT sla_id FROM slas WHERE sla_archived_at IS NULL AND sla_response_minutes = $response AND IFNULL(sla_resolution_minutes, 0) = 0 ORDER BY sla_id LIMIT 1"))[0] ?? 0);
        if (!$existing) {
            $name = escapeSql("Standard - $priority");
            $description = escapeSql('Respond within ' . formatSlaMinutes($response));
            mysqli_query($mysqli, "INSERT INTO slas SET sla_name = '$name', sla_description = '$description', sla_response_minutes = $response, sla_resolution_minutes = NULL");
            $existing = intval(mysqli_insert_id($mysqli));
        }
        $ids[$priority] = $existing;
        mysqli_query($mysqli, "DELETE FROM sla_assignments WHERE sla_assignment_client_id = 0 AND sla_assignment_priority = '$priority'");
        mysqli_query($mysqli, "INSERT INTO sla_assignments SET sla_assignment_client_id = 0, sla_assignment_priority = '$priority', sla_assignment_sla_id = $existing");
    }
    if (!$ids) {
        return $ids;
    }
    $sql = mysqli_query($mysqli, "SELECT ticket_id FROM tickets WHERE ticket_closed_at IS NULL AND ticket_archived_at IS NULL");
    while ($row = mysqli_fetch_row($sql)) {
        applyTicketSla(intval($row[0]));
    }
    return $ids;
}

/** Business days as words - "Mon-Fri", "Mon, Wed, Fri". $days are ISO weekdays, 1 Monday. */
function gettingStartedDaysText(array $days) {
    $names = [1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri', 6 => 'Sat', 7 => 'Sun'];
    $days = array_values(array_unique(array_map('intval', $days)));
    sort($days);
    if (!$days) {
        return 'no days';
    }
    $runs = [];
    $start = $prev = $days[0];
    foreach (array_slice($days, 1) as $day) {
        if ($day !== $prev + 1) {
            $runs[] = [$start, $prev];
            $start = $day;
        }
        $prev = $day;
    }
    $runs[] = [$start, $prev];
    return implode(', ', array_map(function ($run) use ($names) {
        return $run[0] === $run[1] ? $names[$run[0]] : ($run[1] - $run[0] === 1 ? $names[$run[0]] . ', ' . $names[$run[1]] : $names[$run[0]] . '-' . $names[$run[1]]);
    }, $runs));
}
