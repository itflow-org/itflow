<?php

/*
 * ITFlow - Managed mode: one ITFlow managing others
 *
 * Any ITFlow can be a MANAGER (Admin > Managed Instances) and any can be MANAGED (Admin >
 * Managed By) - or both, for a reseller managed by its provider.
 *
 * The instance does all the talking. Connecting it uses a one-time code from the manager;
 * from then on its Manager Check-in cron job calls the manager every minute with a health
 * report, and gets back what the manager sets for it (seat limit, white-label key, updates,
 * active, read-only or suspended) and anything it has asked (update now, check for updates).
 * The manager never has to reach an instance, so instances can sit behind NAT and firewalls.
 *
 * Support sign-in is the one thing that goes the other way, through the browser: the manager
 * signs a two-minute link with its own key (managerSign()) and the instance checks it with the
 * public half it was given when it connected - so an instance's database can't be used to sign
 * anyone into any other instance. The person signs in as the instance's support user, which
 * takes no seat, has no password and can't open the credential vault.
 *
 * On a managed instance, what the manager sets is locked: seat limit, white label and updates.
 * Everything else stays the instance's own. $config_manager_required = true in config.php
 * stops its admins disconnecting it - for a provider hosting it.
 */

/* What each mode means - read-only lets people look, suspended shuts the doors */
const MANAGED_MODES = ['active' => 'Active', 'read_only' => 'Read-only', 'suspended' => 'Suspended'];

/* A connection code works for a week */
const MANAGED_ENROLL_DAYS = 7;

/* No check-in for this long and the manager calls an instance offline */
const MANAGED_OFFLINE_MINUTES = 10;

/* A support sign-in link works this long; the session it opens, this long */
const MANAGED_SUPPORT_LINK_SECONDS = 120;
const MANAGED_SUPPORT_SESSION_HOURS = 2;

/* The cron jobs that keep running while an instance is read-only or suspended */
const MANAGED_KEEP_JOBS = [
    'read_only' => ['manager_checkin', 'update_check', 'app_update', 'backup', 'mail_queue'],
    'suspended' => ['manager_checkin', 'update_check', 'app_update', 'backup'],
];

/* ------------------------------------------------------------------- Both sides */

function managedBase64UrlEncode(string $data): string
{
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function managedBase64UrlDecode(string $data): string
{
    return (string) base64_decode(strtr($data, '-_', '+/') . str_repeat('=', (4 - strlen($data) % 4) % 4), true);
}

/* A random UUID (v4) - an instance's name to its manager */
function managedUuid(): string
{
    $bytes = random_bytes(16);
    $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
    $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
}

/*
 * This install's address - config.php's $config_base_url, over https unless config.php allows
 * plain http and this request came that way. Kept by the instance when it connects, because
 * cron has no request.
 */
function managedRequestBaseUrl(): string
{
    global $config_base_url, $config_https_only;

    $https = !isset($config_https_only) || $config_https_only
        || (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https'
        || strtolower((string) ($_SERVER['REQUEST_SCHEME'] ?? '')) === 'https';

    return ($https ? 'https' : 'http') . '://' . rtrim((string) $config_base_url, '/');
}

/* POST JSON and read JSON back - ['ok', 'code', 'json', 'err'] */
function managedHttpJson(string $url, array $body, array $headers = []): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($body),
        CURLOPT_HTTPHEADER => array_merge(['Content-Type: application/json', 'Accept: application/json'], $headers),
        CURLOPT_TIMEOUT => 20,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_USERAGENT => 'ITFlow managed mode',
        // An http address moved to https still gets the POST, not a GET
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 3,
        CURLOPT_POSTREDIR => CURL_REDIR_POST_ALL,
        CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
        CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
    ]);
    $raw = curl_exec($ch);
    $err = curl_error($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $json = is_string($raw) ? json_decode($raw, true) : null;

    return ['ok' => $raw !== false && $code >= 200 && $code < 300 && is_array($json), 'code' => $code, 'json' => is_array($json) ? $json : [], 'err' => $err];
}

/* ------------------------------------------------- Managed instance (this ITFlow) */

/*
 * What this ITFlow knows about its manager (settings.config_managed) - [] when it isn't
 * managed, or its database hasn't caught up with managed mode yet. Read once a request.
 *
 *   manager_url, manager_name, public_key  who manages it, and the key it signs with
 *   uuid, secret                            how it identifies itself when it checks in
 *   self_url                                its own address, as it was when it connected
 *   state                                   what the manager last set (managedState())
 *   support_user_id                         the user support signs in as
 *   last_checkin_at, last_error             how the last check-in went
 *   acks                                    answers to commands, sent with the next check-in
 *   metrics                                 disk and database size, measured every half hour
 *   white_label_applied                     the key the manager put in, if it did
 *   nonces                                  support links already used, until they expire
 */
function managedConfig(bool $refresh = false): array
{
    global $mysqli;
    static $config = null;

    if ($config === null || $refresh) {
        $config = [];
        try {
            $row = mysqli_fetch_row(mysqli_query($mysqli, "SELECT config_managed FROM settings WHERE company_id = 1"));
            $decoded = json_decode((string) ($row[0] ?? ''), true);
            $config = is_array($decoded) ? $decoded : [];
        } catch (Throwable $e) {
            // The column arrives with a migration - before it, nothing is managed
            $config = [];
        }
    }

    return $config;
}

function managedConfigSave(array $config): void
{
    global $mysqli;

    $json = $config ? mysqli_real_escape_string($mysqli, (string) json_encode($config)) : '';
    mysqli_query($mysqli, "UPDATE settings SET config_managed = " . ($json === '' ? 'NULL' : "'$json'") . " WHERE company_id = 1");
    managedConfig(true);
}

function managedIsManaged(): bool
{
    $config = managedConfig();
    return !empty($config['manager_url']) && !empty($config['uuid']) && !empty($config['secret']);
}

/* What the manager set, with nothing missing */
function managedState(): array
{
    $state = managedIsManaged() ? (array) (managedConfig()['state'] ?? []) : [];

    return [
        'mode' => isset(MANAGED_MODES[$state['mode'] ?? '']) ? $state['mode'] : 'active',
        'message' => (string) ($state['message'] ?? ''),
        'seat_limit' => max(0, intval($state['seat_limit'] ?? 0)),
        'white_label_key' => isset($state['white_label_key']) && is_string($state['white_label_key']) ? $state['white_label_key'] : null,
        'update_policy' => ($state['update_policy'] ?? '') === 'auto' ? 'auto' : 'manual',
        'update_delay_days' => max(0, intval($state['update_delay_days'] ?? 7)),
        'manager_name' => (string) ($state['manager_name'] ?? (managedConfig()['manager_name'] ?? '')),
        'contact' => (string) ($state['contact'] ?? ''),
    ];
}

/* active, read_only or suspended - active unless a manager says otherwise */
function managedMode(): string
{
    return managedState()['mode'];
}

function managedManagerName(): string
{
    $name = managedState()['manager_name'];
    return $name !== '' ? $name : 'its manager';
}

/* Whether config.php keeps this install managed - its admins can't disconnect it */
function managedRequired(): bool
{
    global $config_manager_required;
    return !empty($config_manager_required);
}

/* The seat limit the manager set - 0 for none, when config.php's applies (getAgentUserLimit()) */
function managedSeatLimit(): int
{
    return managedState()['seat_limit'];
}

/* What the manager has locked here */
function managedLocks(): array
{
    $managed = managedIsManaged();
    $state = managedState();

    return [
        'seat_limit' => $managed && $state['seat_limit'] > 0,
        'white_label' => $managed && $state['white_label_key'] !== null,
        'updates' => $managed,
    ];
}

function managedSupportUserId(): int
{
    return managedIsManaged() ? intval(managedConfig()['support_user_id'] ?? 0) : 0;
}

/* A manager's connection code - ITFLOW-MANAGE:<base64url {"u": manager address, "t": token}> - read, or null */
function managedParseCode(string $code): ?array
{
    $code = preg_replace('/\s+/', '', $code);
    if (!preg_match('/^ITFLOW-MANAGE:([A-Za-z0-9_-]+)$/', $code, $match)) {
        return null;
    }
    $data = json_decode(managedBase64UrlDecode($match[1]), true);
    if (!is_array($data) || !is_string($data['u'] ?? null) || !is_string($data['t'] ?? null)
        || !preg_match('#^https?://[^\s/]+(/[^\s]*)?$#', $data['u']) || !preg_match('/^[a-f0-9]{64}$/', $data['t'])) {
        return null;
    }
    return ['manager_url' => rtrim($data['u'], '/'), 'token' => $data['t']];
}

/*
 * Connect this ITFlow to the manager a code is from. The code works once: the manager hands
 * back this instance's id and secret, its name and the public key it signs support sign-ins
 * with. Then a first check-in, so the page shows the answer straight away.
 */
function managedEnroll(string $code, string $self_url, &$error = ''): bool
{
    global $mysqli;

    $parsed = managedParseCode($code);
    if (!$parsed) {
        $error = "That isn't a connection code - copy it again from the manager's Managed Instances page. It starts ITFLOW-MANAGE:";
        return false;
    }

    require_once __DIR__ . '/../includes/app_version.php';

    $company = (string) (mysqli_fetch_row(mysqli_query($mysqli, "SELECT company_name FROM companies WHERE company_id = 1"))[0] ?? '');
    $response = managedHttpJson($parsed['manager_url'] . '/api/managed/enroll.php', [
        'token' => $parsed['token'],
        'url' => $self_url,
        'company' => $company,
        'version' => APP_VERSION,
    ]);

    if (!$response['ok']) {
        $error = $response['json']['error'] ?? ($response['code'] ? "The manager answered " . $response['code'] . "." : "The manager couldn't be reached - " . ($response['err'] ?: 'no answer') . '.');
        return false;
    }

    $json = $response['json'];
    if (!preg_match('/^[a-f0-9-]{36}$/', (string) ($json['uuid'] ?? '')) || !preg_match('/^[a-f0-9]{64}$/', (string) ($json['secret'] ?? ''))
        || !str_contains((string) ($json['manager']['public_key'] ?? ''), 'BEGIN PUBLIC KEY')) {
        $error = "The manager's answer didn't make sense - is it running the same version of ITFlow?";
        return false;
    }

    managedConfigSave([
        'manager_url' => $parsed['manager_url'],
        'manager_name' => (string) ($json['manager']['name'] ?? ''),
        'public_key' => (string) $json['manager']['public_key'],
        'uuid' => $json['uuid'],
        'secret' => $json['secret'],
        'self_url' => rtrim($self_url, '/'),
        'connected_at' => date('Y-m-d H:i:s'),
        'state' => is_array($json['state'] ?? null) ? $json['state'] : [],
    ]);
    managedApplyState();

    managedCheckin();

    return true;
}

/*
 * Leave the manager. Its support user is archived, which ends any support session, and a
 * white-label key the manager put in comes out. The manager is told, best effort.
 */
function managedDisconnect(string $why = 'disconnected'): void
{
    global $mysqli;

    $config = managedConfig();
    if (!$config) {
        return;
    }

    if ($why === 'disconnected' && !empty($config['manager_url'])) {
        managedHttpJson($config['manager_url'] . '/api/managed/checkin.php', ['disconnect' => true, 'auth' => ($config['uuid'] ?? '') . ':' . ($config['secret'] ?? '')],
            ['Authorization: Bearer ' . ($config['uuid'] ?? '') . ':' . ($config['secret'] ?? '')]);
    }

    $support_user_id = intval($config['support_user_id'] ?? 0);
    if ($support_user_id) {
        mysqli_query($mysqli, "UPDATE users SET user_archived_at = NOW(), user_status = 0 WHERE user_id = $support_user_id AND user_archived_at IS NULL");
    }

    if (!empty($config['white_label_applied'])) {
        $applied = mysqli_real_escape_string($mysqli, (string) $config['white_label_applied']);
        mysqli_query($mysqli, "UPDATE settings SET config_whitelabel_key = '', config_whitelabel_enabled = 0 WHERE company_id = 1 AND config_whitelabel_key = '$applied'");
    }

    managedConfigSave([]);
}

/*
 * Put what the manager set into effect where it lives elsewhere. The seat limit and mode are
 * read from managedState() where they're used; the white-label key goes where Admin >
 * Settings > Modules keeps it, as it is the manager's.
 */
function managedApplyState(): void
{
    global $mysqli;

    $config = managedConfig();
    $key = managedState()['white_label_key'];

    if ($key !== null && $key !== ($config['white_label_applied'] ?? null)) {
        $valid = $key !== '' && validateWhitelabelKey($key);
        $key_sql = mysqli_real_escape_string($mysqli, $key);
        mysqli_query($mysqli, "UPDATE settings SET config_whitelabel_key = '$key_sql', config_whitelabel_enabled = " . ($valid ? 1 : 0) . " WHERE company_id = 1");
        $config['white_label_applied'] = $key;
        managedConfigSave($config);
    } elseif ($key === null && !empty($config['white_label_applied'])) {
        // The manager stopped setting one - its key comes out, and the field is the instance's again
        $applied = mysqli_real_escape_string($mysqli, (string) $config['white_label_applied']);
        mysqli_query($mysqli, "UPDATE settings SET config_whitelabel_key = '', config_whitelabel_enabled = 0 WHERE company_id = 1 AND config_whitelabel_key = '$applied'");
        unset($config['white_label_applied']);
        managedConfigSave($config);
    }
}

/*
 * A time from this install's clock, with its offset (2026-11-05T14:55:00-05:00) - the manager
 * may keep another time zone, and reads it right either way. null stays null.
 */
function managedIsoTime($datetime): ?string
{
    $time = $datetime ? strtotime((string) $datetime) : false;
    return $time ? date('c', $time) : null;
}

/* A directory's size in MB - du when it can be run, else walked */
function managedDirMb(string $dir): float
{
    if (!is_dir($dir)) {
        return 0.0;
    }
    if (function_exists('shellCommandsAvailable') && shellCommandsAvailable()) {
        $out = trim((string) shell_exec('du -sk ' . escapeshellarg($dir) . ' 2>/dev/null'));
        if (preg_match('/^(\d+)/', $out, $match)) {
            return round(intval($match[1]) / 1024, 1);
        }
    }
    $bytes = 0;
    try {
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)) as $file) {
            $bytes += $file->getSize();
        }
    } catch (Throwable $e) {
        // Unreadable in part - what was read is still something
    }
    return round($bytes / 1048576, 1);
}

/*
 * How this ITFlow is doing, for its manager - versions, updates, cron, backups, errors,
 * agents against the seat limit, and disk and database size (measured every half hour,
 * as walking the uploads folder isn't free). Times carry their offset (managedIsoTime()).
 */
function managedReport(): array
{
    global $mysqli;

    require_once __DIR__ . '/../includes/app_version.php';
    require_once __DIR__ . '/../includes/database_version.php';

    $config = managedConfig();
    $settings = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT config_current_database_version, config_enable_cron, config_cron_last_dispatch_at,
        config_update_latest_commit, config_update_pending_commits, config_update_checked_at, config_update_details FROM settings WHERE company_id = 1")) ?: [];

    $current_commit = gitCurrentCommit();
    $latest_commit = (string) ($settings['config_update_latest_commit'] ?? '');
    $details = json_decode((string) ($settings['config_update_details'] ?? ''), true) ?: [];
    $pending = json_decode((string) ($settings['config_update_pending_commits'] ?? ''), true) ?: [];
    $security = false;
    foreach ((array) ($details['changelog'] ?? []) as $section) {
        if (is_array($section) && preg_match('/^###\s+Security/mi', (string) ($section[1] ?? ''))) {
            $security = true;
        }
    }
    $release = (string) ($details['version'] ?? '');

    $app_update = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT cron_job_last_status, cron_job_last_finished_at, cron_job_last_error, cron_job_last_error_at
        FROM cron_jobs WHERE cron_job_name = 'app_update' LIMIT 1")) ?: [];
    $backup = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT backup_status, backup_created_at, backup_completed_at FROM backups
        WHERE backup_status IN ('Complete', 'Failed') ORDER BY backup_created_at DESC LIMIT 1")) ?: [];
    $backup_ok = mysqli_fetch_row(mysqli_query($mysqli, "SELECT MAX(backup_completed_at) FROM backups WHERE backup_status = 'Complete'"))[0] ?? null;
    $errors = intval(mysqli_fetch_row(mysqli_query($mysqli, "SELECT COUNT(*) FROM app_logs WHERE app_log_type = 'error' AND app_log_created_at > NOW() - INTERVAL 1 DAY"))[0] ?? 0);
    $clients = intval(mysqli_fetch_row(mysqli_query($mysqli, "SELECT COUNT(*) FROM clients WHERE client_archived_at IS NULL AND client_lead = 0"))[0] ?? 0);
    $company = (string) (mysqli_fetch_row(mysqli_query($mysqli, "SELECT company_name FROM companies WHERE company_id = 1"))[0] ?? '');

    // Disk and database size, every half hour
    $metrics = (array) ($config['metrics'] ?? []);
    if (empty($metrics['measured_at']) || strtotime($metrics['measured_at']) < strtotime('-30 minutes')) {
        $db_mb = mysqli_fetch_row(mysqli_query($mysqli, "SELECT ROUND(SUM(data_length + index_length) / 1048576, 1) FROM information_schema.TABLES WHERE table_schema = DATABASE()"))[0] ?? 0;
        $metrics = ['uploads_mb' => managedDirMb(dirname(__DIR__) . '/uploads'), 'db_mb' => floatval($db_mb), 'measured_at' => date('Y-m-d H:i:s')];
        $config['metrics'] = $metrics;
        managedConfigSave($config);
    }

    return [
        'url' => (string) ($config['self_url'] ?? ''),
        'company' => $company,
        'reported_at' => date('c'),
        'timezone' => date_default_timezone_get(),
        'app' => [
            'version' => APP_VERSION,
            'commit' => substr($current_commit, 0, 7),
            'branch' => getRepoBranch(),
            'db_version' => (string) ($settings['config_current_database_version'] ?? ''),
            'db_latest' => LATEST_DATABASE_VERSION,
            'php' => PHP_VERSION,
        ],
        'update' => [
            'checked_at' => managedIsoTime($settings['config_update_checked_at'] ?? null),
            'available' => $latest_commit !== '' && $current_commit !== '' && $latest_commit !== $current_commit,
            'release' => $release !== '' && version_compare($release, APP_VERSION, '>') ? $release : '',
            'latest_commit' => substr($latest_commit, 0, 7),
            'commits' => count($pending),
            'security' => $security,
            'last' => [
                'status' => (string) ($app_update['cron_job_last_status'] ?? ''),
                'finished_at' => managedIsoTime($app_update['cron_job_last_finished_at'] ?? null),
                'error' => ($app_update['cron_job_last_status'] ?? '') === 'Failed' ? (string) ($app_update['cron_job_last_error'] ?? '') : '',
            ],
        ],
        'cron' => [
            'enabled' => !empty($settings['config_enable_cron']),
            'last_dispatch_at' => managedIsoTime($settings['config_cron_last_dispatch_at'] ?? null),
        ],
        'backup' => [
            'last_status' => (string) ($backup['backup_status'] ?? ''),
            'last_at' => managedIsoTime($backup['backup_completed_at'] ?? ($backup['backup_created_at'] ?? null)),
            'last_ok_at' => managedIsoTime($backup_ok),
        ],
        'errors_24h' => $errors,
        'agents' => [
            'active' => getActiveAgentUserCount(),
            'invites' => function_exists('userInvitesHoldingSeats') ? userInvitesHoldingSeats() : 0,
            'limit' => getAgentUserLimit(),
        ],
        'clients' => $clients,
        'disk' => ['measured_at' => managedIsoTime($metrics['measured_at'] ?? null)] + $metrics,
        'mode' => managedMode(),
    ];
}

/*
 * Check in with the manager - the Manager Check-in job's work, and done once on connecting.
 * Sends the report and the answers to last time's commands; takes back what the manager sets
 * and anything it asks. A manager that has removed this instance answers 410, and the
 * instance lets go. ['ok', 'error', 'commands' => how many it ran].
 */
function managedCheckin(): array
{
    $config = managedConfig();
    if (!managedIsManaged()) {
        return ['ok' => false, 'error' => 'Not managed', 'commands' => 0];
    }

    $report = managedReport();
    $config = managedConfig(true);
    $report['acks'] = array_values((array) ($config['acks'] ?? []));
    // In the body as well as the header - some web servers drop Authorization before PHP sees it
    $report['auth'] = $config['uuid'] . ':' . $config['secret'];

    $response = managedHttpJson($config['manager_url'] . '/api/managed/checkin.php', $report,
        ['Authorization: Bearer ' . $config['uuid'] . ':' . $config['secret']]);

    if ($response['code'] === 410 && !empty($response['json']['released'])) {
        managedDisconnect('released');
        appNotify("Management", "This ITFlow is no longer managed - " . escapeSql(managedManagerNameFrom($config)) . " removed it", "/admin/managed_by.php");
        logAudit("Management", "Release", "The manager removed this instance, so it is no longer managed");
        return ['ok' => true, 'error' => '', 'commands' => 0];
    }

    if (!$response['ok']) {
        $config['last_error'] = $response['json']['error'] ?? ($response['code'] ? "The manager answered " . $response['code'] : "The manager couldn't be reached - " . ($response['err'] ?: 'no answer'));
        $config['last_error_at'] = date('Y-m-d H:i:s');
        managedConfigSave($config);
        return ['ok' => false, 'error' => $config['last_error'], 'commands' => 0];
    }

    $json = $response['json'];
    $was_mode = managedMode();
    $config['state'] = is_array($json['state'] ?? null) ? $json['state'] : ($config['state'] ?? []);
    if (!empty($config['state']['manager_name'])) {
        $config['manager_name'] = $config['state']['manager_name'];
    }
    $config['last_checkin_at'] = date('Y-m-d H:i:s');
    unset($config['last_error'], $config['last_error_at']);
    $config['acks'] = [];
    managedConfigSave($config);
    managedApplyState();

    $now_mode = managedMode();
    if ($now_mode !== $was_mode) {
        logAudit("Management", "Mode", escapeSql(managedManagerName() . " set this ITFlow " . strtolower(MANAGED_MODES[$now_mode])));
    }

    // Commands - each answered on the next check-in
    $ran = 0;
    $acks = [];
    foreach ((array) ($json['commands'] ?? []) as $command) {
        if (!is_array($command) || !intval($command['id'] ?? 0)) {
            continue;
        }
        $acks[] = ['id' => intval($command['id'])] + managedRunCommand((string) ($command['command'] ?? ''));
        $ran++;
    }
    if ($acks) {
        $config = managedConfig(true);
        $config['acks'] = $acks;
        managedConfigSave($config);
    }

    return ['ok' => true, 'error' => '', 'commands' => $ran];
}

function managedManagerNameFrom(array $config): string
{
    $name = (string) ($config['state']['manager_name'] ?? ($config['manager_name'] ?? ''));
    return $name !== '' ? $name : 'its manager';
}

/*
 * Do what the manager asked - ['ok', 'message']. The update goes through the same queue as
 * Maintenance > Update's button, so cron/app_update.php runs it in its own process and the
 * outcome shows in the next report.
 */
function managedRunCommand(string $command): array
{
    global $mysqli;

    switch ($command) {
        case 'update_now':
            if (!settingsColumnExists($mysqli, 'config_update_queued_at')) {
                return ['ok' => false, 'message' => 'This install has to be updated from a shell once - its database is too far behind to queue updates'];
            }
            mysqli_query($mysqli, "UPDATE settings SET config_update_queued_at = '" . date('Y-m-d H:i:s') . "' WHERE company_id = 1");
            mysqli_query($mysqli, "UPDATE cron_jobs SET cron_job_run_now = 1 WHERE cron_job_name = 'app_update'");
            logAudit("App", "Update", escapeSql(managedManagerName() . " queued an update to be applied by cron"));
            return ['ok' => true, 'message' => 'Update started'];
        case 'check_updates':
            mysqli_query($mysqli, "UPDATE cron_jobs SET cron_job_run_now = 1 WHERE cron_job_name = 'update_check'");
            return ['ok' => true, 'message' => 'Checked for updates'];
    }

    return ['ok' => false, 'message' => "Not something this version of ITFlow can do ($command)"];
}

/*
 * A support sign-in link's payload, checked - signed by the manager, for this instance, in
 * date and not used before - or null, with $error.
 */
function managedVerifySupportToken(string $token, &$error = ''): ?array
{
    $config = managedConfig();
    if (!managedIsManaged() || empty($config['public_key'])) {
        $error = 'This ITFlow is not managed, so it takes no support sign-ins.';
        return null;
    }

    $parts = explode('.', $token);
    if (count($parts) !== 2) {
        $error = "That isn't a support sign-in link.";
        return null;
    }
    [$body, $signature] = $parts;
    $public_key = openssl_pkey_get_public((string) $config['public_key']);
    if (!$public_key || openssl_verify($body, managedBase64UrlDecode($signature), $public_key, OPENSSL_ALGO_SHA256) !== 1) {
        $error = "That link wasn't signed by this ITFlow's manager.";
        return null;
    }

    $payload = json_decode(managedBase64UrlDecode($body), true);
    if (!is_array($payload) || ($payload['i'] ?? '') !== $config['uuid']) {
        $error = 'That link is for another ITFlow.';
        return null;
    }
    $expires = intval($payload['x'] ?? 0);
    // A minute's grace either way, for two servers' clocks
    if ($expires < time() - 60 || $expires > time() + MANAGED_SUPPORT_LINK_SECONDS + 60) {
        $error = 'That link has run out - sign in from the manager again.';
        return null;
    }
    $nonce = (string) ($payload['j'] ?? '');
    $nonces = array_filter((array) ($config['nonces'] ?? []), function ($until) {
        return $until > time();
    });
    if (!preg_match('/^[a-f0-9]{32}$/', $nonce) || isset($nonces[$nonce])) {
        $error = 'That link has been used - sign in from the manager again.';
        return null;
    }

    $nonces[$nonce] = $expires + 120;
    $config['nonces'] = $nonces;
    managedConfigSave($config);

    return [
        'name' => mb_substr(trim((string) ($payload['n'] ?? '')), 0, 120),
        'email' => mb_substr(trim((string) ($payload['e'] ?? '')), 0, 200),
    ];
}

/*
 * The user support signs in as - made on the first sign-in, and kept an active admin while
 * the instance is managed (signing in again undoes an archive). No password anyone knows, no
 * MFA (the manager's own sign-in stands for it), no seat. Its id, or 0.
 */
function managedSupportUser(): int
{
    global $mysqli;

    $config = managedConfig();
    $manager = managedManagerNameFrom($config);
    $user_id = intval($config['support_user_id'] ?? 0);
    $role_id = intval(mysqli_fetch_row(mysqli_query($mysqli, "SELECT role_id FROM user_roles WHERE role_is_admin = 1 AND role_archived_at IS NULL ORDER BY role_id ASC LIMIT 1"))[0] ?? 0);
    if (!$role_id) {
        return 0;
    }

    $name = mysqli_real_escape_string($mysqli, mb_substr("Support - $manager", 0, 200));
    $host = preg_replace('/[^a-z0-9.-]/', '', strtolower((string) parse_url((string) $config['manager_url'], PHP_URL_HOST))) ?: 'manager';
    $email = mysqli_real_escape_string($mysqli, "managed-support@$host.invalid");

    $exists = $user_id ? mysqli_fetch_row(mysqli_query($mysqli, "SELECT user_id FROM users WHERE user_id = $user_id AND user_type = 1")) : null;
    if (!$exists) {
        // Connected again after a disconnect - the support user from before, rather than a second one
        $exists = mysqli_fetch_row(mysqli_query($mysqli, "SELECT user_id FROM users WHERE user_email = '$email' AND user_type = 1 ORDER BY user_id DESC LIMIT 1"));
        $user_id = intval($exists[0] ?? 0);
        if ($user_id) {
            $config['support_user_id'] = $user_id;
            managedConfigSave($config);
        }
    }
    if ($exists) {
        mysqli_query($mysqli, "UPDATE users SET user_name = '$name', user_status = 1, user_archived_at = NULL, user_role_id = $role_id WHERE user_id = $user_id");
    } else {
        $password = mysqli_real_escape_string($mysqli, password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT));
        mysqli_query($mysqli, "INSERT INTO users SET user_name = '$name', user_email = '$email', user_password = '$password', user_auth_method = 'local', user_type = 1, user_status = 1, user_role_id = $role_id");
        $user_id = intval(mysqli_insert_id($mysqli));
        $config['support_user_id'] = $user_id;
        managedConfigSave($config);
    }
    mysqli_query($mysqli, "INSERT INTO user_settings SET user_id = $user_id, user_config_force_mfa = 0 ON DUPLICATE KEY UPDATE user_config_force_mfa = 0");

    return $user_id;
}

/*
 * The guard on every request of a managed instance - called where each kind of request starts:
 *
 *   agent    includes/check_login.php, once the agent's session is loaded
 *   portal   client/includes/check_login.php
 *   public   includes/load_global_settings.php - which nearly every page loads, so guest links,
 *            signing up, joining and password resets are covered without each page asking
 *   api      api/v1/validate_api_key.php (and public, for an /api/ page loading the settings)
 *   login    login.php - signing in isn't a change, so only suspended applies
 *
 * Read-only: anything that would change something is turned away - the pages still open, and
 * exports and downloads still work. Suspended: nothing opens but the notice. Signing out
 * always works, and so does a support session from the manager, which has to get in either
 * way - it lasts two hours, and the support user can sign in no other way.
 */
function managedRequestGuard(string $surface): void
{
    if (!managedIsManaged()) {
        return;
    }

    $support_id = managedSupportUserId();
    $agent_id = !empty($_SESSION['logged']) ? intval($_SESSION['user_id'] ?? 0) : 0;
    if (!empty($_SESSION['managed_support'])) {
        if (!$support_id || ($agent_id && $agent_id !== $support_id)
            || intval($_SESSION['managed_support']['started'] ?? 0) < time() - MANAGED_SUPPORT_SESSION_HOURS * 3600) {
            session_unset();
            session_destroy();
            redirect('/login.php');
        }
        // A support session can't open the credential vault - that takes a user's own password.
        // So nothing that seals the vault's key for someone, or needs a password of its own, is
        // done in one: a password set for an agent, or the key sealed under it, would be sealed
        // empty, and they'd lose the vault
        $sealing = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && (trim((string) ($_POST['password'] ?? '')) !== ''
            || trim((string) ($_POST['new_password'] ?? '')) !== '' || isset($_POST['ir_reset_user_password'])
            || isset($_POST['add_api_key']) || isset($_POST['getting_started_team']));
        if ($sealing) {
            flashAlert("A support session can't open this ITFlow's saved passwords, so it can't set or use passwords, or make API keys - invite people instead, or ask one of its admins.", 'error');
            redirect();
        }
        return;
    }
    if ($support_id && $agent_id === $support_id) {
        session_unset();
        session_destroy();
        redirect('/login.php');
    }

    $mode = managedMode();
    if ($mode === 'active') {
        return;
    }

    $leaving = isset($_GET['logout']) || isset($_POST['logout']) || isset($_GET['exit_view_as']);
    if ($leaving) {
        return;
    }

    $state = managedState();
    $message = $state['message'] !== '' ? $state['message'] : ($mode === 'suspended'
        ? 'Access has been suspended by ' . managedManagerName() . '.'
        : 'Nothing can be changed for now - ' . managedManagerName() . ' has made this read-only.');
    $signout = !empty($_SESSION['logged']) ? '/agent/post.php?logout' : (!empty($_SESSION['client_logged_in']) ? '/client/post.php?logout' : '');

    if ($mode === 'suspended') {
        if ($surface === 'api') {
            http_response_code(503);
            header('Content-Type: application/json');
            exit(json_encode(['success' => 'False', 'message' => $message]));
        }
        managedBlockedPage(503, 'Suspended', $message, $state['contact'], $signout, '');
    }

    // Read-only. Signing in isn't a change
    if ($surface === 'login') {
        return;
    }
    $script = basename((string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    $writing = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' || $script === 'post.php' || $script === 'guest_post.php';
    // Let through: the MFA set-up a user can't get past, taking things out (what read-only is
    // for), and asking the manager straight away whether it's still read-only
    $keys = array_keys($_GET + $_POST);
    $allowed = isset($_POST['enable_mfa']) || isset($_POST['verify_code']) || isset($_POST['portal_mfa_enable'])
        || preg_grep('/^(export|download)/', $keys) || isset($_POST['managed_checkin_now']);
    if (!$writing || $allowed) {
        return;
    }

    $ajax = strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest'
        || str_contains((string) ($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json') || $surface === 'api';
    if ($ajax) {
        http_response_code(403);
        header('Content-Type: application/json');
        exit(json_encode(['success' => false, 'error' => $message, 'message' => $message]));
    }

    // Someone signed in hears it on the page they came from; anyone else, here
    if (session_status() === PHP_SESSION_ACTIVE && $signout !== '') {
        flashAlert(escapeHtml($message), 'error');
        redirect();
    }
    $referer = (string) ($_SERVER['HTTP_REFERER'] ?? '');
    $referer_host = (string) parse_url($referer, PHP_URL_HOST) . (parse_url($referer, PHP_URL_PORT) ? ':' . parse_url($referer, PHP_URL_PORT) : '');
    $back = $referer !== '' && $referer_host === ($_SERVER['HTTP_HOST'] ?? '') ? $referer : '/';
    managedBlockedPage(403, 'Read-only', $message, $state['contact'], '', $back);
}

/* The notice in place of a page (includes/managed_blocked.php) - and the end of the request */
function managedBlockedPage(int $status, string $title, string $message, string $contact, string $signout, string $back): void
{
    global $mysqli;

    http_response_code($status);
    $managed_blocked_title = $title;
    $managed_blocked_message = $message;
    $managed_blocked_contact = $contact;
    $managed_blocked_signout = $signout;
    $managed_blocked_back = $back;
    require __DIR__ . '/../includes/managed_blocked.php';
    exit;
}

/*
 * The notice along the top of each page - a support session from the manager, saying whose and
 * until when, or read-only. '' when there's nothing to say.
 */
function managedBannerHtml(): string
{
    if (!managedIsManaged()) {
        return '';
    }

    $html = '';
    if (!empty($_SESSION['managed_support'])) {
        $support = (array) $_SESSION['managed_support'];
        $who = trim((string) ($support['name'] ?? ''));
        $ends = intval($support['started'] ?? time()) + MANAGED_SUPPORT_SESSION_HOURS * 3600;
        $html .= '<div class="alert alert-info d-flex gap-2 align-items-start" data-managed-banner="support"><i class="fas fa-fw fa-headset mt-1"></i><div><strong>Support session</strong> from '
            . escapeHtml(managedManagerName()) . ($who !== '' ? ' - ' . escapeHtml($who) : '') . '. It ends at ' . date('g:i A', $ends) . '; everything done in it is in the audit log.</div></div>';
    }
    if (managedMode() === 'read_only') {
        $message = managedState()['message'];
        $html .= '<div class="alert alert-warning d-flex gap-2 align-items-start" data-managed-banner="read_only"><i class="fas fa-fw fa-lock mt-1"></i><div><strong>Read-only.</strong> '
            . escapeHtml($message !== '' ? $message : 'Nothing can be changed for now - ' . managedManagerName() . ' has made this read-only.') . '</div></div>';
    }

    return $html;
}

/* --------------------------------------------------------------- Manager (this ITFlow) */

/*
 * This ITFlow's signing key pair, made the first time it's needed (settings.config_manager_keys).
 * Instances are given the public half when they connect.
 */
function managerKeys(): array
{
    global $mysqli;

    $row = mysqli_fetch_row(mysqli_query($mysqli, "SELECT config_manager_keys FROM settings WHERE company_id = 1"));
    $keys = json_decode((string) ($row[0] ?? ''), true);
    if (is_array($keys) && !empty($keys['private']) && !empty($keys['public'])) {
        return $keys;
    }

    $pair = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);
    if (!$pair || !openssl_pkey_export($pair, $private)) {
        throw new RuntimeException("This server's PHP couldn't make a signing key (OpenSSL)");
    }
    $keys = ['private' => $private, 'public' => openssl_pkey_get_details($pair)['key']];
    mysqli_query($mysqli, "UPDATE settings SET config_manager_keys = '" . mysqli_real_escape_string($mysqli, json_encode($keys)) . "' WHERE company_id = 1");

    return $keys;
}

/* A payload signed with this ITFlow's key - body.signature, both base64url */
function managerSign(array $payload): string
{
    $body = managedBase64UrlEncode((string) json_encode($payload));
    openssl_sign($body, $signature, openssl_pkey_get_private(managerKeys()['private']), OPENSSL_ALGO_SHA256);
    return $body . '.' . managedBase64UrlEncode($signature);
}

/* This ITFlow's name and address, as instances see them */
function managerIdentity(): array
{
    global $mysqli;

    $company = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT company_name, company_email, company_phone, company_phone_country_code FROM companies WHERE company_id = 1")) ?: [];
    $contact = array_filter([(string) ($company['company_email'] ?? ''),
        !empty($company['company_phone']) ? formatPhoneNumber($company['company_phone'], $company['company_phone_country_code'] ?? '') : '']);

    return ['name' => (string) ($company['company_name'] ?? 'ITFlow'), 'contact' => implode(' · ', $contact)];
}

/* One instance this ITFlow manages, with its client's name - null when there is none */
function managedInstanceRow(int $instance_id): ?array
{
    global $mysqli;

    return mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT managed_instances.*, client_name FROM managed_instances
        LEFT JOIN clients ON client_id = managed_instance_client_id
        WHERE managed_instance_id = $instance_id LIMIT 1")) ?: null;
}

/* The report an instance last sent, read */
function managedInstanceReport(array $row): array
{
    $report = json_decode((string) ($row['managed_instance_report'] ?? ''), true);
    return is_array($report) ? $report : [];
}

/*
 * How an instance is doing, from its last report - state (pending: never connected,
 * offline, online), what needs a look (issues, worst first, each [tone, text]), and what
 * the list shows of it.
 */
function managedInstanceHealth(array $row): array
{
    $report = managedInstanceReport($row);
    $last = $row['managed_instance_last_checkin_at'] ?? null;

    if (empty($row['managed_instance_secret_hash'])) {
        $state = 'pending';
    } elseif (!$last || strtotime($last) < strtotime('-' . MANAGED_OFFLINE_MINUTES . ' minutes')) {
        $state = 'offline';
    } else {
        $state = 'online';
    }

    $issues = [];
    if ($state === 'offline') {
        $issues[] = ['danger', $last ? 'Not checked in for ' . str_replace(' ago', '', timeAgo($last)) : 'Connected, but never checked in'];
    }
    if ($report) {
        $cron = (array) ($report['cron'] ?? []);
        if (empty($cron['enabled'])) {
            $issues[] = ['danger', 'Cron is switched off'];
        } elseif (empty($cron['last_dispatch_at']) || strtotime($cron['last_dispatch_at']) < strtotime((string) ($report['reported_at'] ?? 'now')) - 600) {
            $issues[] = ['danger', 'Cron isn\'t running'];
        }
        $app = (array) ($report['app'] ?? []);
        if (!empty($app['db_latest']) && !empty($app['db_version']) && version_compare($app['db_latest'], $app['db_version'], '>')) {
            $issues[] = ['danger', 'Database update not applied'];
        }
        $update = (array) ($report['update'] ?? []);
        if (!empty($update['last']['error'])) {
            $issues[] = ['danger', 'The last update failed'];
        }
        if (!empty($update['available'])) {
            $issues[] = [!empty($update['security']) ? 'danger' : 'info', (!empty($update['security']) ? 'Security update' : 'Update') . ' available' . (!empty($update['release']) ? ' - ' . $update['release'] : '')];
        }
        $backup = (array) ($report['backup'] ?? []);
        if (($backup['last_status'] ?? '') === 'Failed') {
            $issues[] = ['warning', 'The last backup failed'];
        } elseif (empty($backup['last_ok_at'])) {
            $issues[] = ['warning', 'No backups made'];
        } elseif (strtotime($backup['last_ok_at']) < strtotime('-3 days')) {
            $issues[] = ['warning', 'No backup for ' . str_replace(' ago', '', timeAgo($backup['last_ok_at']))];
        }
        if (intval($report['errors_24h'] ?? 0) > 0) {
            $issues[] = ['warning', intval($report['errors_24h']) . ' error' . (intval($report['errors_24h']) === 1 ? '' : 's') . ' logged today'];
        }
        $agents = (array) ($report['agents'] ?? []);
        if (intval($agents['limit'] ?? 0) > 0 && intval($agents['active'] ?? 0) + intval($agents['invites'] ?? 0) >= intval($agents['limit'])) {
            $issues[] = ['info', 'Every seat is taken'];
        }
    }

    $order = ['danger' => 0, 'warning' => 1, 'info' => 2];
    usort($issues, function ($a, $b) use ($order) {
        return $order[$a[0]] <=> $order[$b[0]];
    });

    return ['state' => $state, 'issues' => $issues, 'report' => $report];
}

/*
 * A new connection code for an instance - the old one stops working. The code carries this
 * ITFlow's address and a token that works once, for a week; the manager keeps only its hash.
 */
function managedInstanceNewCode(int $instance_id): string
{
    global $mysqli;

    $token = bin2hex(random_bytes(32));
    $expires = date('Y-m-d H:i:s', strtotime('+' . MANAGED_ENROLL_DAYS . ' days'));
    mysqli_query($mysqli, "UPDATE managed_instances SET managed_instance_enroll_hash = '" . hash('sha256', $token) . "', managed_instance_enroll_expires_at = '$expires'
        WHERE managed_instance_id = $instance_id");

    return 'ITFLOW-MANAGE:' . managedBase64UrlEncode((string) json_encode(['u' => managedRequestBaseUrl(), 't' => $token]));
}

/* Ask an instance to do something, at its next check-in */
function managedQueueCommand(int $instance_id, string $command, string $by, string $source = 'manual'): int
{
    global $mysqli;

    $command = mysqli_real_escape_string($mysqli, $command);
    $by = mysqli_real_escape_string($mysqli, mb_substr($by, 0, 200));
    $source = mysqli_real_escape_string($mysqli, $source);

    // The same thing asked twice before the first was picked up is one request
    $queued = mysqli_fetch_row(mysqli_query($mysqli, "SELECT managed_command_id FROM managed_instance_commands
        WHERE managed_command_instance_id = $instance_id AND managed_command = '$command' AND managed_command_status = 'queued' LIMIT 1"));
    if ($queued) {
        return intval($queued[0]);
    }

    mysqli_query($mysqli, "INSERT INTO managed_instance_commands SET managed_command_instance_id = $instance_id, managed_command = '$command',
        managed_command_source = '$source', managed_command_requested_by = '$by'");

    return intval(mysqli_insert_id($mysqli));
}

/*
 * Add Instance and Edit Instance's fields, read and checked - [fields, error]. The white-label
 * key is checked here, where it's typed, rather than found wrong on the instance later.
 */
function managedInstanceFromInput(array $input): array
{
    global $mysqli;

    $fields = [
        'name' => mb_substr(trim((string) ($input['name'] ?? '')), 0, 200),
        'client_id' => max(0, intval($input['client_id'] ?? 0)),
        'notes' => trim((string) ($input['notes'] ?? '')),
        'seat_limit' => min(100000, max(0, intval($input['seat_limit'] ?? 0))),
        'update_policy' => ($input['update_policy'] ?? '') === 'auto' ? 'auto' : 'manual',
        'update_delay_days' => min(90, max(0, intval($input['update_delay_days'] ?? 7))),
        'white_label_key' => preg_replace('/\s+/', '', (string) ($input['white_label_key'] ?? '')),
        'mode' => isset(MANAGED_MODES[$input['mode'] ?? '']) ? $input['mode'] : 'active',
        'mode_message' => mb_substr(trim((string) ($input['mode_message'] ?? '')), 0, 500),
    ];

    if ($fields['name'] === '') {
        return [$fields, 'Give it a name.'];
    }
    if ($fields['client_id'] && !mysqli_fetch_row(mysqli_query($mysqli, "SELECT 1 FROM clients WHERE client_id = " . $fields['client_id']))) {
        return [$fields, 'That client is gone - pick another.'];
    }
    if ($fields['white_label_key'] !== '' && !validateWhitelabelKey($fields['white_label_key'])) {
        return [$fields, "That isn't a white-label key, or it has run out."];
    }

    return [$fields, ''];
}

/* The SET of a managed_instances write, from managedInstanceFromInput()'s fields */
function managedInstanceSetSql(array $fields): string
{
    global $mysqli;

    $text = function ($value) use ($mysqli) {
        return $value === '' ? 'NULL' : "'" . mysqli_real_escape_string($mysqli, $value) . "'";
    };

    return "managed_instance_name = '" . mysqli_real_escape_string($mysqli, $fields['name']) . "',
        managed_instance_client_id = " . intval($fields['client_id']) . ",
        managed_instance_notes = " . $text($fields['notes']) . ",
        managed_instance_seat_limit = " . intval($fields['seat_limit']) . ",
        managed_instance_update_policy = '" . $fields['update_policy'] . "',
        managed_instance_update_delay_days = " . intval($fields['update_delay_days']) . ",
        managed_instance_white_label_key = " . $text($fields['white_label_key']) . ",
        managed_instance_mode = '" . $fields['mode'] . "',
        managed_instance_mode_message = " . $text($fields['mode_message']);
}

/* Words for a command */
function managedCommandLabel(string $command): string
{
    return ['update_now' => 'Update ITFlow', 'check_updates' => 'Check for updates'][$command] ?? $command;
}

/* What an instance is told at each check-in - all it needs to set itself */
function managerStateFor(array $row): array
{
    $identity = managerIdentity();

    return [
        'mode' => isset(MANAGED_MODES[$row['managed_instance_mode']]) ? $row['managed_instance_mode'] : 'active',
        'message' => (string) ($row['managed_instance_mode_message'] ?? ''),
        'seat_limit' => intval($row['managed_instance_seat_limit']),
        'white_label_key' => trim((string) ($row['managed_instance_white_label_key'] ?? '')) !== '' ? trim((string) $row['managed_instance_white_label_key']) : null,
        'update_policy' => $row['managed_instance_update_policy'] === 'auto' ? 'auto' : 'manual',
        'update_delay_days' => intval($row['managed_instance_update_delay_days']),
        'manager_name' => $identity['name'],
        'contact' => $identity['contact'],
    ];
}

/*
 * An instance connecting with a code (api/managed/enroll.php) - [http status, answer].
 * The code works once; the instance gets its id, a secret to check in with (kept here only
 * as its hash), this ITFlow's name and the public key support sign-ins are signed with.
 */
function managerEnroll(array $body, string $ip): array
{
    global $mysqli;

    $token = (string) ($body['token'] ?? '');
    if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
        return [400, ['error' => "That isn't a connection code."]];
    }
    $hash = hash('sha256', $token);
    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT * FROM managed_instances
        WHERE managed_instance_enroll_hash = '$hash' AND managed_instance_archived_at IS NULL LIMIT 1"));
    if (!$row || empty($row['managed_instance_enroll_expires_at']) || strtotime($row['managed_instance_enroll_expires_at']) < time()) {
        return [404, ['error' => "That code isn't valid any more - it works once, for " . MANAGED_ENROLL_DAYS . " days. Make a new one on the manager."]];
    }

    $instance_id = intval($row['managed_instance_id']);
    $secret = bin2hex(random_bytes(32));
    $url = mysqli_real_escape_string($mysqli, mb_substr(preg_match('#^https?://#', (string) ($body['url'] ?? '')) ? (string) $body['url'] : '', 0, 255));
    $version = mysqli_real_escape_string($mysqli, mb_substr((string) ($body['version'] ?? ''), 0, 20));
    $ip = mysqli_real_escape_string($mysqli, $ip);

    mysqli_query($mysqli, "UPDATE managed_instances SET managed_instance_secret_hash = '" . hash('sha256', $secret) . "', managed_instance_enrolled_at = NOW(),
        managed_instance_enroll_hash = NULL, managed_instance_enroll_expires_at = NULL, managed_instance_url = '$url', managed_instance_version = '$version',
        managed_instance_last_ip = '$ip', managed_instance_offline_notified_at = NULL
        WHERE managed_instance_id = $instance_id");

    $name = (string) $row['managed_instance_name'];
    logAudit("Managed Instance", "Connect", escapeSql("$name connected from " . ($url !== '' ? $url : $ip)), intval($row['managed_instance_client_id']), $instance_id);
    appNotify("Managed Instance", escapeSql("$name connected" . ($url !== '' ? " - $url" : '')), "/admin/managed_instance.php?id=$instance_id", intval($row['managed_instance_client_id']), $instance_id);

    $identity = managerIdentity();

    return [200, [
        'uuid' => $row['managed_instance_uuid'],
        'secret' => $secret,
        'manager' => ['name' => $identity['name'], 'public_key' => managerKeys()['public']],
        'state' => managerStateFor($row),
    ]];
}

/*
 * An instance checking in (api/managed/checkin.php) - [http status, answer]. Stores its
 * report, closes the commands it has answered, decides whether an automatic update is due,
 * and hands back what it should be set to and anything it has been asked.
 */
function managerCheckin(string $authorization, array $body, string $ip): array
{
    global $mysqli;

    if (trim($authorization) === '' && is_string($body['auth'] ?? null)) {
        $authorization = 'Bearer ' . $body['auth'];
    }
    unset($body['auth']);
    if (!preg_match('/^Bearer ([a-f0-9-]{36}):([a-f0-9]{64})$/', trim($authorization), $match)) {
        return [401, ['error' => 'Not signed']];
    }
    $uuid = mysqli_real_escape_string($mysqli, $match[1]);
    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT * FROM managed_instances WHERE managed_instance_uuid = '$uuid' LIMIT 1"));
    if (!$row || empty($row['managed_instance_secret_hash']) || !hash_equals((string) $row['managed_instance_secret_hash'], hash('sha256', $match[2]))) {
        return [401, ['error' => "This manager doesn't know this instance - connect it again with a new code"]];
    }

    $instance_id = intval($row['managed_instance_id']);
    $client_id = intval($row['managed_instance_client_id']);
    $name = (string) $row['managed_instance_name'];

    if (!empty($row['managed_instance_archived_at'])) {
        return [410, ['released' => true, 'error' => 'Removed from the manager']];
    }

    // The instance leaving of its own accord
    if (!empty($body['disconnect'])) {
        mysqli_query($mysqli, "UPDATE managed_instances SET managed_instance_secret_hash = NULL WHERE managed_instance_id = $instance_id");
        logAudit("Managed Instance", "Disconnect", escapeSql("$name disconnected itself from management"), $client_id, $instance_id);
        appNotify("Managed Instance", escapeSql("$name disconnected itself from management"), "/admin/managed_instance.php?id=$instance_id", $client_id, $instance_id);
        return [200, ['disconnected' => true]];
    }

    // The report, kept as sent - checked for shape where it's read
    $acks = (array) ($body['acks'] ?? []);
    unset($body['acks']);
    $report_json = (string) json_encode($body);
    if (strlen($report_json) > 262144) {
        return [413, ['error' => 'Report too large']];
    }
    $app = (array) ($body['app'] ?? []);
    $update = (array) ($body['update'] ?? []);
    $set = [
        "managed_instance_report = '" . mysqli_real_escape_string($mysqli, $report_json) . "'",
        "managed_instance_last_checkin_at = NOW()",
        "managed_instance_last_ip = '" . mysqli_real_escape_string($mysqli, $ip) . "'",
        "managed_instance_version = '" . mysqli_real_escape_string($mysqli, mb_substr((string) ($app['version'] ?? ''), 0, 20)) . "'",
        "managed_instance_db_version = '" . mysqli_real_escape_string($mysqli, mb_substr((string) ($app['db_version'] ?? ''), 0, 20)) . "'",
        "managed_instance_offline_notified_at = NULL",
    ];
    if (preg_match('#^https?://#', (string) ($body['url'] ?? ''))) {
        $set[] = "managed_instance_url = '" . mysqli_real_escape_string($mysqli, mb_substr((string) $body['url'], 0, 255)) . "'";
    }

    // Back from being offline
    if (!empty($row['managed_instance_offline_notified_at'])) {
        appNotify("Managed Instance", escapeSql("$name is checking in again"), "/admin/managed_instance.php?id=$instance_id", $client_id, $instance_id);
    }

    // What it has been asked - first seen and when, for an automatic update's wait
    $update_key = !empty($update['available']) ? (string) (($update['release'] ?? '') !== '' ? $update['release'] : ($update['latest_commit'] ?? '')) : '';
    if ($update_key !== (string) ($row['managed_instance_update_version'] ?? '')) {
        $set[] = $update_key === '' ? "managed_instance_update_version = NULL, managed_instance_update_seen_at = NULL"
            : "managed_instance_update_version = '" . mysqli_real_escape_string($mysqli, mb_substr($update_key, 0, 40)) . "', managed_instance_update_seen_at = NOW()";
        $row['managed_instance_update_seen_at'] = date('Y-m-d H:i:s');
    }
    mysqli_query($mysqli, "UPDATE managed_instances SET " . implode(', ', $set) . " WHERE managed_instance_id = $instance_id");

    // The commands it answered
    foreach ($acks as $ack) {
        $command_id = intval($ack['id'] ?? 0);
        if (!$command_id) {
            continue;
        }
        $command = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT managed_command FROM managed_instance_commands
            WHERE managed_command_id = $command_id AND managed_command_instance_id = $instance_id AND managed_command_status = 'sent'"));
        if (!$command) {
            continue;
        }
        $result = mysqli_real_escape_string($mysqli, mb_substr((string) ($ack['message'] ?? ''), 0, 1000));
        // An update queued is still to run - it is done once the report says it has
        $status = empty($ack['ok']) ? 'failed' : ($command['managed_command'] === 'update_now' ? 'running' : 'done');
        mysqli_query($mysqli, "UPDATE managed_instance_commands SET managed_command_status = '$status', managed_command_result = '$result'"
            . ($status !== 'running' ? ", managed_command_done_at = NOW()" : '') . " WHERE managed_command_id = $command_id");
    }

    // An update that was running and has since finished - as the instance's report says
    $last = (array) ($update['last'] ?? []);
    if (!empty($last['finished_at']) && in_array($last['status'] ?? '', ['Completed', 'Failed'], true)) {
        $running = mysqli_query($mysqli, "SELECT managed_command_id, managed_command_sent_at FROM managed_instance_commands
            WHERE managed_command_instance_id = $instance_id AND managed_command = 'update_now' AND managed_command_status = 'running'");
        while ($command = mysqli_fetch_assoc($running)) {
            // Its clock and ours may differ - a finish within a few minutes of the send counts
            if (strtotime($last['finished_at']) >= strtotime($command['managed_command_sent_at']) - 300) {
                $failed = $last['status'] === 'Failed';
                $result = mysqli_real_escape_string($mysqli, mb_substr($failed ? 'Failed - ' . (string) ($last['error'] ?? '') : 'Updated to ' . (string) ($app['version'] ?? '') . ' (' . (string) ($app['commit'] ?? '') . ')', 0, 1000));
                mysqli_query($mysqli, "UPDATE managed_instance_commands SET managed_command_status = '" . ($failed ? 'failed' : 'done') . "', managed_command_result = '$result', managed_command_done_at = NOW()
                    WHERE managed_command_id = " . intval($command['managed_command_id']));
                if ($failed) {
                    appNotify("Managed Instance", escapeSql("The update on $name failed"), "/admin/managed_instance.php?id=$instance_id", $client_id, $instance_id);
                }
            }
        }
    }

    // An automatic update - a security release straight away, anything else once it's waited.
    // Not while one is on its way, nor for six hours after one failed, so a broken update isn't
    // tried every minute
    if ($row['managed_instance_update_policy'] === 'auto' && $update_key !== '') {
        $waited = !empty($row['managed_instance_update_seen_at']) && strtotime($row['managed_instance_update_seen_at']) <= strtotime('-' . intval($row['managed_instance_update_delay_days']) . ' days');
        $busy = mysqli_fetch_row(mysqli_query($mysqli, "SELECT 1 FROM managed_instance_commands WHERE managed_command_instance_id = $instance_id AND managed_command = 'update_now'
            AND (managed_command_status IN ('queued', 'sent', 'running') OR (managed_command_status = 'failed' AND managed_command_created_at > NOW() - INTERVAL 6 HOUR)) LIMIT 1"));
        if (!$busy && (!empty($update['security']) || $waited)) {
            managedQueueCommand($instance_id, 'update_now', 'Automatic updates', 'auto');
            logAudit("Managed Instance", "Update", escapeSql("Automatic update queued for $name" . (!empty($update['security']) ? ' - a security release' : '')), $client_id, $instance_id);
        }
    }

    // Anything asked of it, handed over now
    $commands = [];
    $queued = mysqli_query($mysqli, "SELECT managed_command_id, managed_command FROM managed_instance_commands
        WHERE managed_command_instance_id = $instance_id AND managed_command_status = 'queued' ORDER BY managed_command_id ASC");
    while ($command = mysqli_fetch_assoc($queued)) {
        $commands[] = ['id' => intval($command['managed_command_id']), 'command' => $command['managed_command']];
        mysqli_query($mysqli, "UPDATE managed_instance_commands SET managed_command_status = 'sent', managed_command_sent_at = NOW() WHERE managed_command_id = " . intval($command['managed_command_id']));
    }

    return [200, ['state' => managerStateFor($row), 'commands' => $commands]];
}

/*
 * A link that signs the person asking into an instance as its support user - signed with this
 * ITFlow's key, for that instance only, good for two minutes and once.
 */
function managedSupportLink(array $row, string $name, string $email): string
{
    $token = managerSign([
        'i' => $row['managed_instance_uuid'],
        'n' => $name,
        'e' => $email,
        'x' => time() + MANAGED_SUPPORT_LINK_SECONDS,
        'j' => bin2hex(random_bytes(16)),
    ]);

    return rtrim((string) $row['managed_instance_url'], '/') . '/login_support.php?token=' . rawurlencode($token);
}

/* How many instances have stopped checking in - for the side nav. 0 before the tables exist */
function managedOfflineCount(): int
{
    global $mysqli;

    try {
        return intval(mysqli_fetch_row(mysqli_query($mysqli, "SELECT COUNT(*) FROM managed_instances WHERE managed_instance_archived_at IS NULL
            AND managed_instance_secret_hash IS NOT NULL AND (managed_instance_last_checkin_at IS NULL OR managed_instance_last_checkin_at < NOW() - INTERVAL " . MANAGED_OFFLINE_MINUTES . " MINUTE)"))[0] ?? 0);
    } catch (Throwable $e) {
        return 0;
    }
}

/* What the list and the page show of a mode */
function managedModePill(string $mode): string
{
    $tone = ['active' => 'success', 'read_only' => 'warning', 'suspended' => 'danger'][$mode] ?? 'secondary';
    $icon = ['active' => 'fa-check-circle', 'read_only' => 'fa-lock', 'suspended' => 'fa-ban'][$mode] ?? 'fa-question';
    return '<span class="badge rounded-pill bg-' . $tone . '-subtle text-' . $tone . '-emphasis border border-' . $tone . '-subtle fw-normal"><i class="fas fa-fw ' . $icon . ' me-1"></i>' . escapeHtml(MANAGED_MODES[$mode] ?? $mode) . '</span>';
}
