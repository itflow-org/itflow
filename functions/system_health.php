<?php

/*
 * How this ITFlow and the server under it are doing - for Admin > Debug
 * (admin/debug.php). Checks in sections - ITFlow itself, background jobs,
 * mail, backups, PHP, web and security, files and disk, the server, and
 * errors logged - each a label, a level (ok, info, warning, danger), what
 * was found, and what to do when it isn't right, with where to do it. Then
 * the database (its size, its biggest tables and how its structure compares
 * with db.sql) and the uploads folder, folder by folder. healthReport() is
 * all of it as plain text to paste into a forum post or a support email -
 * no host names, paths, addresses or keys.
 *
 * Every read is careful: the page has to open on a database the code has
 * run ahead of (a missing column) and on a host with parts of PHP turned
 * off, as that is when it is wanted most.
 */

/** One check. $fix is HTML - escape anything in it. 'private' leaves its value out of healthReport(). */
function healthItem($label, $level, $value, $fix = '', $url = '', $action = '', $private = false) {
    return ['label' => (string) $label, 'level' => $level, 'value' => (string) $value, 'fix' => (string) $fix, 'url' => (string) $url, 'action' => (string) $action, 'private' => (bool) $private];
}

/** A query that answers null rather than stopping the page - a column the database doesn't have yet, say. */
function healthQuery($sql) {
    global $mysqli;
    try {
        $result = mysqli_query($mysqli, $sql);
        return $result ?: null;
    } catch (Throwable $e) {
        return null;
    }
}

/** The first row of a query, or [] (healthQuery()). */
function healthRow($sql) {
    $result = healthQuery($sql);
    return $result ? (mysqli_fetch_assoc($result) ?: []) : [];
}

/** "3 minutes ago", "less than a minute ago", or "never". */
function healthAgo($datetime) {
    if (empty($datetime)) {
        return 'never';
    }
    return abs(time() - strtotime($datetime)) < 60 ? 'less than a minute ago' : timeAgo($datetime);
}

/** "Just now", "4 min ago", "20 hr ago", "3 days ago" or "Never" - short enough for a figure along the top. */
function healthShortAgo($datetime) {
    if (empty($datetime)) {
        return 'Never';
    }
    $seconds = max(0, time() - strtotime($datetime));
    if ($seconds < 60) {
        return 'Just now';
    }
    if ($seconds < 3600) {
        return floor($seconds / 60) . ' min ago';
    }
    if ($seconds < 86400) {
        return floor($seconds / 3600) . ' hr ago';
    }
    $days = floor($seconds / 86400);
    return $days . ($days == 1 ? ' day' : ' days') . ' ago';
}

/** The levels, worst first - icon, colour and word. */
function healthLevels() {
    return [
        'danger' => ['icon' => 'fa-times-circle', 'tone' => 'danger', 'word' => 'Problem'],
        'warning' => ['icon' => 'fa-exclamation-triangle', 'tone' => 'warning', 'word' => 'Warning'],
        'info' => ['icon' => 'fa-info-circle', 'tone' => 'secondary', 'word' => 'Note'],
        'ok' => ['icon' => 'fa-check-circle', 'tone' => 'success', 'word' => 'OK'],
    ];
}

/*
 * The setup wizard's requirement list (setup/setup_checks.php), when it is
 * here - the PHP extensions ITFlow can't run without, name => what for. The
 * same list setup checks, so the two never disagree.
 */
function healthRequiredExtensions() {
    $file = dirname(__DIR__) . '/setup/setup_checks.php';
    if (!function_exists('setupRequiredExtensions') && is_file($file)) {
        defined('FROM_SETUP') || define('FROM_SETUP', true); // the file's guard against being opened on its own
        require_once $file;
    }
    return function_exists('setupRequiredExtensions') ? setupRequiredExtensions() : ['mysqli' => 'the database', 'intl' => 'money and dates', 'curl' => 'mail', 'mbstring' => 'text', 'gd' => 'images', 'xml' => 'mail and documents', 'openssl' => 'encryption', 'fileinfo' => 'uploads'];
}

/** The host ITFlow's links use ($config_base_url), without a port or path - lower case. */
function healthBaseHost() {
    global $config_base_url;
    $host = preg_replace('#^[a-z]+://#i', '', trim((string) $config_base_url));
    $host = preg_replace('#[/?\#].*$#', '', $host);
    return strtolower(preg_replace('/:\d+$/', '', $host));
}

/*
 * The certificate a host serves - ['valid' => bool, 'until' => timestamp,
 * 'issuer' => name], or null when it can't be reached from here (behind a
 * proxy, a firewall). Four seconds at most. Asked of the configured host,
 * never one a request named.
 */
function healthCertificate($host) {
    if ($host === '' || !function_exists('openssl_x509_parse')) {
        return null;
    }
    $context = stream_context_create(['ssl' => ['capture_peer_cert' => true, 'verify_peer' => false, 'verify_peer_name' => false, 'SNI_enabled' => true, 'peer_name' => $host]]);
    $socket = @stream_socket_client("ssl://$host:443", $errno, $errstr, 4, STREAM_CLIENT_CONNECT, $context);
    if (!$socket) {
        return null;
    }
    $params = stream_context_get_params($socket);
    fclose($socket);
    $cert = $params['options']['ssl']['peer_certificate'] ?? null;
    $info = $cert ? openssl_x509_parse($cert) : false;
    if (!$info) {
        return null;
    }
    $from = intval($info['validFrom_time_t']);
    $until = intval($info['validTo_time_t']);
    return ['valid' => time() >= $from && time() <= $until, 'until' => $until, 'issuer' => (string) ($info['issuer']['O'] ?? $info['issuer']['CN'] ?? '')];
}

/** Whether this request came over HTTPS - directly, or through a proxy that says so. */
function healthRequestIsHttps() {
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || intval($_SERVER['SERVER_PORT'] ?? 0) === 443
        || strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
}

/*
 * Every section of checks, in the order the page shows them - each
 * ['key', 'title', 'icon', 'items' => [healthItem(), ...]].
 */
function healthChecks() {
    return [
        ['key' => 'itflow', 'title' => 'ITFlow', 'icon' => 'fa-cube', 'items' => healthItflowChecks()],
        ['key' => 'jobs', 'title' => 'Background jobs', 'icon' => 'fa-clock', 'items' => healthJobChecks()],
        ['key' => 'mail', 'title' => 'Mail', 'icon' => 'fa-envelope', 'items' => healthMailChecks()],
        ['key' => 'backups', 'title' => 'Backups', 'icon' => 'fa-cloud-upload-alt', 'items' => healthBackupChecks()],
        ['key' => 'php', 'title' => 'PHP', 'icon' => 'fa-code', 'items' => healthPhpChecks()],
        ['key' => 'web', 'title' => 'Web and security', 'icon' => 'fa-shield-alt', 'items' => healthWebChecks()],
        ['key' => 'files', 'title' => 'Files and disk', 'icon' => 'fa-hdd', 'items' => healthFileChecks()],
        ['key' => 'server', 'title' => 'Server', 'icon' => 'fa-server', 'items' => healthServerChecks()],
        ['key' => 'errors', 'title' => 'Errors logged', 'icon' => 'fa-bug', 'items' => healthErrorChecks()],
    ];
}

/** ITFlow itself - its version, where its code came from, the database's version against the code's, and updates. */
function healthItflowChecks() {
    $items = [];
    $branch = gitCurrentBranch();
    $commit = gitCurrentCommit();
    $items[] = healthItem('Version', 'ok', APP_VERSION . ($branch !== '' ? " - $branch" . ($commit !== '' ? ' @ ' . substr($commit, 0, 7) : '') : ' - not a git checkout'));

    $installed = (string) CURRENT_DATABASE_VERSION;
    $expected = (string) LATEST_DATABASE_VERSION;
    if (version_compare($expected, $installed, '>')) {
        $items[] = healthItem('Database version', 'danger', "$installed - this code expects $expected",
            'The files were updated but the database wasn\'t. Parts of ITFlow will fail until it is - apply the database update.', 'update.php', 'Maintenance > Update');
    } elseif (version_compare($installed, $expected, '>')) {
        $items[] = healthItem('Database version', 'warning', "$installed - newer than this code ($expected)",
            'The database has been updated past these files - the code was rolled back, or the update didn\'t finish. Update the files to match.', 'update.php', 'Maintenance > Update');
    } else {
        $items[] = healthItem('Database version', 'ok', "$installed - up to date");
    }

    // Updates - Maintenance > Update fetches them with git, run through exec and shell_exec
    if ($commit === '') {
        $items[] = healthItem('Updates', 'info', 'Not a git checkout - updated by hand', '', 'https://docs.itflow.org/updating', 'How to update');
    } elseif (!shellCommandsAvailable()) {
        $items[] = healthItem('Updates', 'warning', 'PHP can\'t run git here', 'exec or shell_exec is in <code>disable_functions</code> in php.ini, so ITFlow can\'t check for or apply updates. Take them out of it, or update by hand.', 'https://docs.itflow.org/updating', 'How to update');
    } elseif (commandPath('git') === '') {
        $items[] = healthItem('Updates', 'warning', 'git isn\'t installed', 'ITFlow checks for and applies updates with git - <code>apt install git</code> on Debian and Ubuntu.');
    } else {
        $update = healthRow("SELECT config_update_latest_commit, config_update_pending_commits, config_update_checked_at FROM settings WHERE company_id = 1");
        $latest = (string) ($update['config_update_latest_commit'] ?? '');
        $pending = json_decode((string) ($update['config_update_pending_commits'] ?? ''), true);
        if (!$update || empty($update['config_update_checked_at'])) {
            $items[] = healthItem('Updates', 'info', 'Not checked yet', 'The Update Check job looks each night.', 'update.php', 'Maintenance > Update');
        } elseif ($latest !== '' && $latest !== $commit) {
            $count = is_array($pending) ? count($pending) : 0;
            $items[] = healthItem('Updates', 'info', ($count ? "$count " . ($count === 1 ? 'change' : 'changes') . ' waiting' : 'An update is waiting') . ' - checked ' . healthAgo($update['config_update_checked_at']), '', 'update.php', 'See what\'s new');
        } else {
            $items[] = healthItem('Updates', 'ok', 'Up to date - checked ' . healthAgo($update['config_update_checked_at']));
        }
    }
    return $items;
}

/** Cron - the heartbeat, the master switch, jobs that failed lately and jobs switched off. */
function healthJobChecks() {
    $items = [];
    $settings = healthRow("SELECT config_enable_cron, config_cron_last_dispatch_at FROM settings WHERE company_id = 1");
    if (!$settings) {
        return [healthItem('Cron', 'warning', 'Can\'t tell yet', 'The database needs updating before cron can be checked.', 'update.php', 'Maintenance > Update')];
    }
    $heard = $settings['config_cron_last_dispatch_at'] ?? null;
    $command = '* * * * * php ' . dirname(__DIR__) . '/cron/cron.php >/dev/null';
    if (!$heard || time() - strtotime($heard) >= 300) {
        $items[] = healthItem('Cron', 'danger', 'Last ran ' . healthAgo($heard),
            'Nothing runs without it - no mail goes out, no email becomes a ticket, nothing is invoiced or backed up. Add this line to the crontab of the user that owns the ITFlow files: <code>' . escapeHtml($command) . '</code>', 'cron.php', 'Maintenance > Cron');
    } else {
        $items[] = healthItem('Cron', 'ok', 'Last ran ' . healthAgo($heard));
    }
    if (empty($settings['config_enable_cron'])) {
        $items[] = healthItem('Cron switch', 'danger', 'Off', 'Every job stops itself while it is off. Turn it on, unless this is a copy that mustn\'t email clients or charge cards.', 'cron.php', 'Maintenance > Cron');
    }

    $labels = [];
    if (function_exists('cronJobRegistry')) {
        foreach (cronJobRegistry() as $job) {
            $labels[$job['name']] = $job['label'];
        }
    }
    $failing = [];
    $off = [];
    $result = healthQuery("SELECT cron_job_name, cron_job_enabled, cron_job_last_error, cron_job_last_error_at FROM cron_jobs ORDER BY cron_job_name");
    while ($result && $row = mysqli_fetch_assoc($result)) {
        $label = $labels[$row['cron_job_name']] ?? $row['cron_job_name'];
        if (!intval($row['cron_job_enabled'])) {
            $off[] = $label;
        } elseif (!empty($row['cron_job_last_error']) && !empty($row['cron_job_last_error_at']) && strtotime($row['cron_job_last_error_at']) > time() - 86400) {
            $failing[] = $label . ': ' . mb_strimwidth(trim((string) $row['cron_job_last_error']), 0, 160, '...');
        }
    }
    if ($failing) {
        $items[] = healthItem('Jobs', 'warning', count($failing) . ' failed in the last day', escapeHtml(implode(' · ', $failing)), 'cron.php', 'Maintenance > Cron');
    } elseif ($result) {
        $items[] = healthItem('Jobs', 'ok', 'None failed in the last day');
    }
    if ($off) {
        $items[] = healthItem('Switched off', 'info', implode(', ', $off), '', 'cron.php', 'Maintenance > Cron');
    }
    return $items;
}

/** Mail - sending set up and moving (mailQueueHealth()), mail given up on, and email becoming tickets. */
function healthMailChecks() {
    global $config_ticket_email_parse, $config_module_enable_ticketing;
    $items = [];
    try {
        $queue = mailQueueHealth();
        if (in_array($queue['key'], ['cron_off', 'cron_stopped'], true)) {
            // Said once, under Background jobs - here, only what it means for mail
            $waiting = intval($queue['waiting']);
            $items[] = healthItem('Sending', $waiting ? 'warning' : 'info', 'Waiting on cron' . ($waiting ? " - $waiting " . ($waiting === 1 ? 'email' : 'emails') . ' held up' : ''),
                'Email goes out when cron runs - see Background jobs.', 'mail_queue.php', 'Mail Queue');
        } elseif ($queue['level'] !== '') {
            $items[] = healthItem('Sending', $queue['level'], $queue['title'], escapeHtml($queue['text']), $queue['url'], $queue['action']);
        } else {
            $summary = mailQueueSummary();
            $items[] = healthItem('Sending', 'ok', $summary['last_sent'] ? 'Last sent ' . healthAgo($summary['last_sent']) : 'Nothing sent yet');
        }
        $summary = mailQueueSummary();
        if ($summary['failed_week']) {
            $items[] = healthItem('Failed', 'warning', $summary['failed_week'] . ' in the last week', 'Tried ' . MAIL_QUEUE_TRIES . ' times and given up on - the Mail Queue says why, and can try again.', 'mail_queue.php?show=failed', 'Mail Queue');
        } else {
            $items[] = healthItem('Failed', 'ok', 'None in the last week');
        }
        if ($summary['retrying']) {
            $items[] = healthItem('Retrying', 'info', $summary['retrying'] . ' to be tried again', '', 'mail_queue.php?show=retrying', 'Mail Queue');
        }
    } catch (Throwable $e) {
        $items[] = healthItem('Sending', 'warning', 'Can\'t tell yet', 'The database needs updating first.', 'update.php', 'Maintenance > Update');
    }
    if (!$config_module_enable_ticketing) {
        return $items;
    }
    if (intval($config_ticket_email_parse) === 1) {
        $job = healthRow("SELECT cron_job_enabled, cron_job_last_run_at, cron_job_last_error, cron_job_last_error_at FROM cron_jobs WHERE cron_job_name = 'ticket_email_parser'");
        if ($job && !intval($job['cron_job_enabled'])) {
            $items[] = healthItem('Email to tickets', 'warning', 'Its job is switched off', 'Email to the ticket mailbox isn\'t being read.', 'cron.php', 'Maintenance > Cron');
        } elseif ($job && !empty($job['cron_job_last_error']) && !empty($job['cron_job_last_error_at']) && strtotime($job['cron_job_last_error_at']) > time() - 3600) {
            $items[] = healthItem('Email to tickets', 'warning', 'Failed ' . healthAgo($job['cron_job_last_error_at']), escapeHtml(mb_strimwidth((string) $job['cron_job_last_error'], 0, 200, '...')), 'settings_mail.php?tab=receiving', 'Admin > Mail');
        } elseif ($job) {
            $items[] = healthItem('Email to tickets', 'ok', 'Mailbox last read ' . healthAgo($job['cron_job_last_run_at']));
        }
    } else {
        $items[] = healthItem('Email to tickets', 'info', 'Off', '', 'settings_mail.php?tab=receiving', 'Admin > Mail');
    }
    return $items;
}

/** Backups - the last one that worked, any that failed lately, and somewhere to put them. */
function healthBackupChecks() {
    global $config_backup_path;
    $items = [];
    $last = healthRow("SELECT backup_type, backup_size, backup_completed_at FROM backups WHERE backup_status = 'Complete' ORDER BY backup_completed_at DESC LIMIT 1");
    $result = healthQuery("SELECT 1 FROM backups LIMIT 1");
    if (!$result) {
        return [healthItem('Backups', 'warning', 'Can\'t tell yet', 'The database needs updating first.', 'update.php', 'Maintenance > Update')];
    }
    if (!$last) {
        $items[] = healthItem('Last backup', 'warning', 'None yet', 'Take one now, and let the Backup job keep taking them.', 'backup.php', 'Maintenance > Backup');
    } else {
        $age = time() - strtotime($last['backup_completed_at']);
        $value = healthAgo($last['backup_completed_at']) . ' - ' . (function_exists('backupTypeLabel') ? backupTypeLabel((string) $last['backup_type']) : $last['backup_type']) . ', ' . formatBytes(intval($last['backup_size']), 1);
        $items[] = $age > 7 * 86400
            ? healthItem('Last backup', 'warning', $value, 'It has been more than a week. Check the Backup job is on, and take one now.', 'backup.php', 'Maintenance > Backup')
            : healthItem('Last backup', 'ok', $value);
    }
    // One that failed this week - unless one has worked since
    $failed = healthRow("SELECT backup_error, backup_created_at FROM backups WHERE backup_status = 'Failed' AND backup_created_at > NOW() - INTERVAL 7 DAY ORDER BY backup_created_at DESC LIMIT 1");
    if ($failed && (!$last || strtotime($failed['backup_created_at']) > strtotime($last['backup_completed_at']))) {
        $items[] = healthItem('Failed', 'warning', healthAgo($failed['backup_created_at']), escapeHtml(mb_strimwidth((string) $failed['backup_error'], 0, 200, '...')), 'backup.php', 'Maintenance > Backup');
    }
    $dir = !empty($config_backup_path) ? rtrim($config_backup_path, '/\\') : dirname(__DIR__) . '/uploads/backups';
    $writable = is_dir($dir) ? is_writable($dir) : is_writable(dirname($dir));
    $items[] = $writable
        ? healthItem('Backup folder', 'ok', 'Writable', '', '', '', true)
        : healthItem('Backup folder', 'danger', 'Can\'t be written to', 'Backups are written to <code>' . escapeHtml($dir) . '</code> - make it writable by the web server and the user cron runs as.', 'backup.php', 'Maintenance > Backup', true);
    return $items;
}

/** PHP - its version, the extensions ITFlow needs, and the limits a backup or a big upload runs into. */
function healthPhpChecks() {
    $items = [];
    $extensions = healthRequiredExtensions(); // loads setup's requirements, the minimum version with them
    $minimum = defined('SETUP_PHP_MINIMUM') ? SETUP_PHP_MINIMUM : '8.2.0';
    $items[] = version_compare(PHP_VERSION, $minimum, '>=')
        ? healthItem('Version', 'ok', PHP_VERSION)
        : healthItem('Version', 'danger', PHP_VERSION, "ITFlow needs PHP $minimum or later.");

    $missing = [];
    foreach ($extensions as $extension => $what) {
        if (!extension_loaded($extension)) {
            $missing[$extension] = $what;
        }
    }
    if ($missing) {
        foreach ($missing as $extension => $what) {
            $items[] = healthItem("$extension extension", 'danger', 'Not installed', 'ITFlow uses it for ' . escapeHtml($what) . ' - <code>apt install php-' . escapeHtml($extension) . '</code> on Debian and Ubuntu, then restart the web server.');
        }
    } else {
        $items[] = healthItem('Extensions', 'ok', 'All ' . count($extensions) . ' ITFlow needs');
    }
    $items[] = class_exists('ZipArchive')
        ? healthItem('zip extension', 'ok', 'Installed')
        : healthItem('zip extension', 'warning', 'Not installed', 'Backups and client exports are zip files - <code>apt install php-zip</code>.');

    $bytes = function ($value) {
        return function_exists('backupParseIniBytes') ? backupParseIniBytes($value) : intval($value);
    };
    $upload = ini_get('upload_max_filesize');
    $post = ini_get('post_max_size');
    $room = 500 * 1024 * 1024;
    $items[] = $bytes($upload) >= $room && $bytes($post) >= $room
        ? healthItem('Upload size', 'ok', "upload_max_filesize $upload, post_max_size $post")
        : healthItem('Upload size', 'warning', "upload_max_filesize $upload, post_max_size $post", 'Restoring a backup and uploading large files need room - set both to <code>500M</code> in php.ini.');
    $memory = (string) ini_get('memory_limit');
    $items[] = $memory === '-1' || $bytes($memory) >= 512 * 1024 * 1024
        ? healthItem('Memory limit', 'ok', $memory === '-1' ? 'No limit' : $memory)
        : healthItem('Memory limit', 'warning', $memory, 'Big exports, reports and backups can run out - set <code>memory_limit = 512M</code> in php.ini.');
    $time = intval(ini_get('max_execution_time'));
    $items[] = $time === 0 || $time >= 300
        ? healthItem('Time limit', 'ok', $time === 0 ? 'No limit' : "$time seconds")
        : healthItem('Time limit', 'warning', "$time seconds", 'A restore or a big export in the browser can stop part way - set <code>max_execution_time = 300</code> in php.ini.');

    return $items;
}

/** How the site is reached - HTTPS, its certificate, the address in its links and sign-in over HTTPS only. */
function healthWebChecks() {
    global $config_https_only;
    $items = [];
    $https = healthRequestIsHttps();
    $items[] = $https
        ? healthItem('HTTPS', 'ok', 'Yes')
        : healthItem('HTTPS', 'warning', 'No', 'Sign-ins, passwords and client data cross the network - put a certificate on the site (Let\'s Encrypt is free) and use https://.');

    $host = healthBaseHost();
    if ($https) {
        $cert = healthCertificate($host);
        if ($cert === null) {
            $items[] = healthItem('Certificate', 'info', 'Can\'t be checked from here', 'This server couldn\'t reach its own address on port 443 - normal behind a proxy or a firewall.');
        } elseif (!$cert['valid']) {
            $items[] = healthItem('Certificate', 'danger', 'Expired or not yet valid', 'Renew the site\'s certificate.');
        } else {
            $days = intval(floor(($cert['until'] - time()) / 86400));
            $value = 'Valid until ' . date('M j, Y', $cert['until']) . ($cert['issuer'] !== '' ? ' - ' . $cert['issuer'] : '');
            $items[] = $days < 14
                ? healthItem('Certificate', 'warning', $value, "It runs out in $days " . ($days === 1 ? 'day' : 'days') . ' - check it renews.')
                : healthItem('Certificate', 'ok', $value);
        }
    }

    $request_host = strtolower(preg_replace('/:\d+$/', '', trim((string) ($_SERVER['HTTP_HOST'] ?? ''))));
    if ($host === '') {
        $items[] = healthItem('Address in links', 'danger', 'Not set', 'Emails link to ITFlow with <code>$config_base_url</code> in config.php - set it to the address you reach ITFlow on.', '', '', true);
    } elseif ($request_host !== '' && $request_host !== $host) {
        $items[] = healthItem('Address in links', 'warning', "Emails link to $host, but you are on $request_host",
            'Links in ITFlow\'s emails and the client portal go to <code>$config_base_url</code> in config.php. If that isn\'t the address clients use, change it there.', '', '', true);
    } else {
        $items[] = healthItem('Address in links', 'ok', $host, '', '', '', true);
    }
    $fqdn = (bool) preg_match('/^(?=.{1,253}$)([a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,}$/i', $host);
    if ($host !== '' && !$fqdn) {
        $items[] = healthItem('Domain name', 'warning', 'An IP address or a local name', 'Clients follow links in ITFlow\'s emails - give it a domain name, like itflow.example.com.', '', '', true);
    }
    if (isset($config_https_only) && !$config_https_only) {
        $items[] = healthItem('HTTPS only', 'warning', 'Off - $config_https_only is FALSE', 'Sign-in cookies can travel over plain HTTP. Fine for a test install - on a real one, set it to TRUE in config.php once the site has a certificate.');
    }
    return $items;
}

/** The folders ITFlow writes to, and room on the disk. */
function healthFileChecks() {
    $items = [];
    $root = dirname(__DIR__);
    $uploads = "$root/uploads";
    $items[] = is_dir($uploads) && is_writable($uploads)
        ? healthItem('Uploads folder', 'ok', 'Writable', '', '', '', true)
        : healthItem('Uploads folder', 'danger', is_dir($uploads) ? 'Can\'t be written to' : 'Missing', 'Logos, avatars, attachments and files go in <code>' . escapeHtml($uploads) . '</code> - make it writable by the web server.', '', '', true);
    $tmp = "$uploads/tmp";
    if (is_dir($tmp) && !is_writable($tmp)) {
        $items[] = healthItem('Uploads tmp folder', 'danger', 'Can\'t be written to', 'Email to tickets and imports work in <code>' . escapeHtml($tmp) . '</code> - make it writable by the web server and the user cron runs as.', '', '', true);
    }
    $items[] = is_writable($root)
        ? healthItem('ITFlow folder', 'ok', 'Writable - one-click updates can replace the files', '', '', '', true)
        : healthItem('ITFlow folder', 'info', 'Read only to the web server', 'One-click updates need to write here - update by hand, or make the files belong to the web server\'s user.', '', '', true);

    $free = @disk_free_space($uploads);
    $total = @disk_total_space($uploads);
    if ($free !== false && $total) {
        $value = formatBytes($free, 1) . ' free of ' . formatBytes($total, 1);
        if ($free < 1024 * 1024 * 1024) {
            $items[] = healthItem('Disk', 'danger', $value, 'Uploads, backups and the database will start failing - free some space.');
        } elseif ($free < 5 * 1024 * 1024 * 1024) {
            $items[] = healthItem('Disk', 'warning', $value, 'Running low - backups alone can take a lot.');
        } else {
            $items[] = healthItem('Disk', 'ok', $value);
        }
    }
    return $items;
}

/** What it runs on, and whether the clocks agree - PHP's, the database's and ITFlow's time zone. */
function healthServerChecks() {
    global $mysqli, $config_timezone;
    $items = [];
    $items[] = healthItem('Operating system', 'info', php_uname('s') . ' ' . php_uname('r'));
    $items[] = healthItem('Web server', 'info', ($_SERVER['SERVER_SOFTWARE'] ?? 'Unknown') . ' - PHP ' . PHP_SAPI);
    $items[] = healthItem('Database server', 'info', (string) mysqli_get_server_info($mysqli));
    $zone = date_default_timezone_get();
    $items[] = healthItem('Time zone', 'info', $zone . (!empty($config_timezone) && $config_timezone !== $zone ? " (ITFlow is set to $config_timezone)" : ''));
    $db = healthRow("SELECT NOW() AS now");
    if (!empty($db['now'])) {
        $drift = strtotime($db['now']) - time();
        $items[] = abs($drift) > 120
            ? healthItem('Clocks', 'warning', 'The database is ' . round(abs($drift) / 60) . ' minutes ' . ($drift > 0 ? 'ahead of' : 'behind') . ' PHP',
                'Times on tickets, SLAs and logs will be off. Check both servers keep time (NTP) and use the same time zone.')
            : healthItem('Clocks', 'ok', 'PHP and the database agree');
    }
    return $items;
}

/** Errors in the app logs in the last day - how many, and the latest few. */
function healthErrorChecks() {
    $count = healthRow("SELECT COUNT(*) AS n FROM app_logs WHERE app_log_type = 'error' AND app_log_created_at > NOW() - INTERVAL 1 DAY");
    if (!$count) {
        return [];
    }
    $n = intval($count['n']);
    if (!$n) {
        return [healthItem('Last 24 hours', 'ok', 'No errors', '', 'app_logs.php', 'App Logs')];
    }
    $latest = [];
    $result = healthQuery("SELECT app_log_category, app_log_details, app_log_created_at FROM app_logs WHERE app_log_type = 'error' AND app_log_created_at > NOW() - INTERVAL 1 DAY ORDER BY app_log_id DESC LIMIT 3");
    while ($result && $row = mysqli_fetch_assoc($result)) {
        $latest[] = escapeHtml(healthAgo($row['app_log_created_at']) . ' - ' . $row['app_log_category'] . ': ' . mb_strimwidth((string) $row['app_log_details'], 0, 140, '...'));
    }
    return [healthItem('Last 24 hours', 'warning', "$n " . ($n === 1 ? 'error' : 'errors'), implode('<br>', $latest), 'app_logs.php?view=error', 'App Logs')];
}

/** How many checks are at each level - and those that aren't ok or info, worst first, each with its section's title. */
function healthSummary(array $sections) {
    $counts = ['danger' => 0, 'warning' => 0, 'info' => 0, 'ok' => 0];
    $attention = [];
    foreach ($sections as $section) {
        foreach ($section['items'] as $item) {
            $counts[$item['level']]++;
            if ($item['level'] === 'danger' || $item['level'] === 'warning') {
                $attention[] = $item + ['section' => $section['title']];
            }
        }
    }
    usort($attention, function ($a, $b) {
        return ($a['level'] === 'danger' ? 0 : 1) <=> ($b['level'] === 'danger' ? 0 : 1);
    });
    return ['counts' => $counts, 'attention' => $attention];
}

/*
 * The database - its tables from information_schema in one go: ['tables' =>
 * [name => rows, bytes], 'size', 'rows', 'exact']. Rows are InnoDB's
 * estimate, unless $exact - then each table is counted, which takes a while
 * on a big install.
 */
function healthDatabaseTables($exact = false) {
    global $database;
    $tables = [];
    $size = $rows = 0;
    $schema = escapeSql($database);
    $result = healthQuery("SELECT TABLE_NAME, TABLE_ROWS, DATA_LENGTH + INDEX_LENGTH AS bytes FROM information_schema.TABLES WHERE TABLE_SCHEMA = '$schema' AND TABLE_TYPE = 'BASE TABLE' ORDER BY bytes DESC, TABLE_NAME");
    while ($result && $row = mysqli_fetch_assoc($result)) {
        $count = intval($row['TABLE_ROWS']);
        if ($exact) {
            $counted = healthRow("SELECT COUNT(*) AS n FROM `" . str_replace('`', '``', $row['TABLE_NAME']) . "`");
            $count = $counted ? intval($counted['n']) : $count;
        }
        $tables[$row['TABLE_NAME']] = ['rows' => $count, 'bytes' => intval($row['bytes'])];
        $size += intval($row['bytes']);
        $rows += $count;
    }
    return ['tables' => $tables, 'size' => $size, 'rows' => $rows, 'exact' => (bool) $exact];
}

/** A column type as compared - lower case, without integer display widths, which MySQL 8 no longer shows ("int(11)" is "int"). */
function healthColumnType($type) {
    $type = strtolower(trim(preg_replace('/\s+/', ' ', (string) $type)));
    return preg_replace('/^(tinyint|smallint|mediumint|int|bigint)\(\d+\)/', '$1', $type);
}

/*
 * How the database's tables and columns compare with db.sql - what a fresh
 * install makes. Each difference ['what' => missing table, missing column,
 * extra table, extra column or type, 'table', 'column', 'expected', 'found'].
 * null when db.sql isn't there.
 */
function healthStructureDifferences() {
    global $database;
    $file = dirname(__DIR__) . '/db.sql';
    if (!is_file($file)) {
        return null;
    }
    $expected = [];
    if (preg_match_all('/CREATE TABLE\s+`([^`]+)`\s*\((.*?)\n\)[^;]*;/s', (string) file_get_contents($file), $creates, PREG_SET_ORDER)) {
        foreach ($creates as $create) {
            $columns = [];
            foreach (explode("\n", $create[2]) as $line) {
                if (preg_match('/^\s*`([^`]+)`\s+([a-z]+(?:\([^)]*\))?(?:\s+unsigned)?)/i', $line, $column)) {
                    $columns[$column[1]] = $column[2];
                }
            }
            $expected[$create[1]] = $columns;
        }
    }
    $found = [];
    $schema = escapeSql($database);
    $result = healthQuery("SELECT TABLE_NAME, COLUMN_NAME, COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = '$schema' ORDER BY TABLE_NAME, ORDINAL_POSITION");
    while ($result && $row = mysqli_fetch_assoc($result)) {
        $found[$row['TABLE_NAME']][$row['COLUMN_NAME']] = $row['COLUMN_TYPE'];
    }
    $differences = [];
    foreach ($expected as $table => $columns) {
        if (!isset($found[$table])) {
            $differences[] = ['what' => 'Missing table', 'table' => $table, 'column' => '', 'expected' => '', 'found' => ''];
            continue;
        }
        foreach ($columns as $column => $type) {
            if (!isset($found[$table][$column])) {
                $differences[] = ['what' => 'Missing column', 'table' => $table, 'column' => $column, 'expected' => $type, 'found' => ''];
            } elseif (healthColumnType($type) !== healthColumnType($found[$table][$column])) {
                $differences[] = ['what' => 'Different type', 'table' => $table, 'column' => $column, 'expected' => $type, 'found' => $found[$table][$column]];
            }
        }
        foreach ($found[$table] as $column => $type) {
            if (!isset($columns[$column])) {
                $differences[] = ['what' => 'Extra column', 'table' => $table, 'column' => $column, 'expected' => '', 'found' => $type];
            }
        }
    }
    foreach (array_keys($found) as $table) {
        if (!isset($expected[$table])) {
            $differences[] = ['what' => 'Extra table', 'table' => $table, 'column' => '', 'expected' => '', 'found' => ''];
        }
    }
    return $differences;
}

/*
 * The uploads folder, folder by folder - [name => ['files', 'bytes']], the
 * biggest first, with 'partial' => true when it took longer than $seconds
 * and stopped counting (so the totals are at least that).
 */
function healthUploads($seconds = 4) {
    $root = dirname(__DIR__) . '/uploads';
    $folders = [];
    $partial = false;
    if (!is_dir($root)) {
        return ['folders' => [], 'files' => 0, 'bytes' => 0, 'partial' => false];
    }
    $stop = microtime(true) + $seconds;
    try {
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::LEAVES_ONLY, RecursiveIteratorIterator::CATCH_GET_CHILD);
        foreach ($iterator as $file) {
            if (!$file->isFile()) {
                continue;
            }
            $relative = substr($file->getPathname(), strlen($root) + 1);
            $top = strpos($relative, DIRECTORY_SEPARATOR) !== false ? strtok($relative, DIRECTORY_SEPARATOR) : '(top level)';
            $folders[$top]['files'] = ($folders[$top]['files'] ?? 0) + 1;
            $folders[$top]['bytes'] = ($folders[$top]['bytes'] ?? 0) + $file->getSize();
            if (microtime(true) > $stop) {
                $partial = true;
                break;
            }
        }
    } catch (Throwable $e) {
        $partial = true;
    }
    uasort($folders, function ($a, $b) {
        return $b['bytes'] <=> $a['bytes'];
    });
    return ['folders' => $folders, 'files' => array_sum(array_column($folders, 'files')), 'bytes' => array_sum(array_column($folders, 'bytes')), 'partial' => $partial];
}

/*
 * Everything as plain text, to paste into a forum post or a support email -
 * versions, what needs attention, and the size of things. No host names,
 * paths, addresses or keys: a check marked private gives its label alone.
 */
function healthReport(array $sections, array $database, $differences, array $uploads) {
    $summary = healthSummary($sections);
    $lines = ['ITFlow ' . APP_VERSION . ' - debug report, ' . date('Y-m-d H:i T'), ''];
    foreach ($sections as $section) {
        $lines[] = '[' . $section['title'] . ']';
        foreach ($section['items'] as $item) {
            $mark = ['danger' => 'X', 'warning' => '!', 'info' => '-', 'ok' => 'ok'][$item['level']];
            $lines[] = str_pad($mark, 3) . $item['label'] . ($item['private'] ? '' : ': ' . $item['value']);
        }
        $lines[] = '';
    }
    $lines[] = '[Database]';
    $lines[] = count($database['tables']) . ' tables, ' . (empty($database['exact']) ? 'about ' : '') . number_format($database['rows']) . ' rows, ' . formatBytes($database['size'], 1);
    $lines[] = $differences === null ? 'db.sql not found' : ($differences ? count($differences) . ' differences from db.sql:' : 'Matches db.sql');
    foreach (array_slice((array) $differences, 0, 30) as $difference) {
        $lines[] = '  ' . $difference['what'] . ': ' . $difference['table'] . ($difference['column'] !== '' ? '.' . $difference['column'] : '')
            . ($difference['expected'] !== '' || $difference['found'] !== '' ? ' (' . trim($difference['expected'] . ($difference['found'] !== '' ? ' -> ' . $difference['found'] : '')) . ')' : '');
    }
    $lines[] = '';
    $lines[] = '[Uploads]';
    $lines[] = number_format($uploads['files']) . ' files, ' . formatBytes($uploads['bytes'], 1) . ($uploads['partial'] ? ' (at least - stopped counting)' : '');
    $lines[] = '';
    $lines[] = $summary['counts']['danger'] . ' problems, ' . $summary['counts']['warning'] . ' warnings';
    return implode("\n", $lines);
}
