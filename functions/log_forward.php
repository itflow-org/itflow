<?php

/*
 * ITFlow - sending log entries on to a syslog server or SIEM as they are
 * written (Admin > Settings > Logs). Each audit log entry - from logAudit()
 * and the few places that write to logs themselves - and each app log entry
 * at or above the level picked (logApp()) goes out as an RFC 5424 syslog
 * message, over UDP, TCP or TLS, with the entry as JSON, CEF or plain text.
 *
 * Entries are queued as they are written (logForwardQueue()) and sent when
 * the request ends (logForwardFlush()), over one connection - so an entry
 * written in a transaction that is then rolled back is never sent, as it is
 * no longer there to read. Sending never gets in the way: a server that
 * can't be reached is tried once, the failure goes in the app log under Log
 * Forwarding, and nothing more is sent for a minute, so a dead server doesn't
 * slow every page down. The entries in between are only in ITFlow's own logs.
 */

const LOG_FORWARD_PROTOCOLS = ['udp' => 'UDP', 'tcp' => 'TCP', 'tls' => 'TLS'];
const LOG_FORWARD_FORMATS = ['json' => 'JSON', 'cef' => 'CEF', 'plain' => 'Plain text'];
const LOG_FORWARD_TIMEOUT = 2;      // seconds to connect, or to write
const LOG_FORWARD_BACKOFF = 60;     // seconds nothing is sent after a failure
const LOG_FORWARD_BATCH = 50;       // entries queued before they are sent anyway, for a long cron run
const LOG_FORWARD_FACILITY = 16;    // local0
const LOG_FORWARD_CATEGORY = 'Log Forwarding';

/** Which app log entries go out - the level and the ones above it, '' for none. */
function logForwardAppLevels() {
    return [
        '' => 'None',
        'error' => 'Errors',
        'warning' => 'Warnings and errors',
        'info' => 'Info, warnings and errors',
        'debug' => 'Everything, debug too',
    ];
}

/** What each protocol is, in a line. */
function logForwardProtocolHelp() {
    return [
        'udp' => 'Fast, and nothing waits for the server - but nothing says whether it got there.',
        'tcp' => 'Each entry arrives, in order, one a line.',
        'tls' => 'TCP, encrypted - for a server outside your network.',
    ];
}

/** What each format is, in a line. */
function logForwardFormatHelp() {
    return [
        'json' => 'One JSON object an entry - Graylog, Splunk, Elastic, Wazuh and most others read it as it is.',
        'cef' => 'Common Event Format - for Microsoft Sentinel, QRadar, ArcSight and other SIEMs that take it.',
        'plain' => 'A line of text an entry, as a person would read it.',
    ];
}

/*
 * The forwarding settings, tidied: enabled, host, port, protocol, format,
 * audit, app_level and tls_verify. Read once a request, apart from the
 * settings every page loads, so a page still opens before the update that
 * adds them - all off until then. $fresh reads them again, after a save.
 */
function logForwardSettings($fresh = false) {
    global $mysqli;
    static $settings = null;
    if ($settings !== null && !$fresh) {
        return $settings;
    }
    $row = [];
    try {
        $result = mysqli_query($mysqli, "SELECT config_log_forward_enabled, config_log_forward_host, config_log_forward_port, config_log_forward_protocol,
                config_log_forward_format, config_log_forward_audit, config_log_forward_app_level, config_log_forward_tls_verify
            FROM settings WHERE company_id = 1");
        $row = $result ? (mysqli_fetch_assoc($result) ?: []) : [];
    } catch (Throwable $e) {
        $row = [];
    }
    $settings = logForwardTidy([
        'enabled' => $row['config_log_forward_enabled'] ?? 0,
        'host' => $row['config_log_forward_host'] ?? '',
        'port' => $row['config_log_forward_port'] ?? 514,
        'protocol' => $row['config_log_forward_protocol'] ?? 'udp',
        'format' => $row['config_log_forward_format'] ?? 'json',
        'audit' => $row['config_log_forward_audit'] ?? 1,
        'app_level' => $row['config_log_forward_app_level'] ?? 'warning',
        'tls_verify' => $row['config_log_forward_tls_verify'] ?? 1,
    ]);
    return $settings;
}

/** Settings from the database or the form, each made one of what it can be. */
function logForwardTidy(array $src) {
    // One value each - a list from a form is nothing
    $src = array_map(function ($value) {
        return is_scalar($value) || $value === null ? $value : '';
    }, $src);
    $host = trim((string) ($src['host'] ?? ''));
    $port = intval($src['port'] ?? 514);
    // A name or IPv4 address typed with its port - logs.example.com:1514 - is the two
    if (preg_match('/^([A-Za-z0-9._-]+):(\d{1,5})$/', $host, $match)) {
        $host = $match[1];
        $port = intval($match[2]);
    }
    // A name or an address, IPv6 in brackets or not - nothing that could break out of the address it is put in
    if (!preg_match('/^\[?[A-Za-z0-9.:_-]{1,200}\]?$/', $host)) {
        $host = '';
    }
    $protocol = (string) ($src['protocol'] ?? 'udp');
    $format = (string) ($src['format'] ?? 'json');
    $app_level = (string) ($src['app_level'] ?? '');
    return [
        'enabled' => intval($src['enabled'] ?? 0) ? 1 : 0,
        'host' => trim($host, '[]'),
        'port' => $port >= 1 && $port <= 65535 ? $port : 514,
        'protocol' => isset(LOG_FORWARD_PROTOCOLS[$protocol]) ? $protocol : 'udp',
        'format' => isset(LOG_FORWARD_FORMATS[$format]) ? $format : 'json',
        'audit' => intval($src['audit'] ?? 0) ? 1 : 0,
        'app_level' => isset(logForwardAppLevels()[$app_level]) ? $app_level : '',
        'tls_verify' => intval($src['tls_verify'] ?? 0) ? 1 : 0,
    ];
}

/** Is anything being sent? */
function logForwardOn(?array $settings = null) {
    $settings = $settings ?? logForwardSettings();
    return $settings['enabled'] && $settings['host'] !== '' && ($settings['audit'] || $settings['app_level'] !== '');
}

/** Where they go, in words - "logs.example.com:514 over UDP". */
function logForwardTarget(array $settings) {
    $host = strpos($settings['host'], ':') !== false ? '[' . $settings['host'] . ']' : $settings['host'];
    return $host . ':' . $settings['port'] . ' over ' . LOG_FORWARD_PROTOCOLS[$settings['protocol']];
}

/** Does an app log entry at this level go out, by the setting? */
function logForwardAppLevelSent($level, $threshold) {
    $order = ['error' => 1, 'warning' => 2, 'info' => 3, 'debug' => 4];
    $level = strtolower((string) $level);
    return $threshold !== '' && isset($order[$level], $order[$threshold]) && $order[$level] <= $order[$threshold];
}

/*
 * The entries waiting to go, by log - 'audit' and 'app' ids. Called with
 * ['audit', 12] it adds one; with false it hands them over and starts again.
 */
function logForwardPending($add = null) {
    static $pending = ['audit' => [], 'app' => []];
    if ($add === false) {
        $taken = $pending;
        $pending = ['audit' => [], 'app' => []];
        return $taken;
    }
    if (is_array($add)) {
        $pending[$add[0]][] = intval($add[1]);
    }
    return $pending;
}

/*
 * An entry just written, to send - 'audit' with its log_id, or 'app' with its
 * app_log_id, level and category. Nothing when forwarding is off, for that
 * log or level, for the forwarding's own failures, or for an email preview,
 * whose sample records are thrown away again (functions/email_preview.php).
 */
function logForwardQueue($log, $id, $level = '', $category = '') {
    static $registered = false;
    $id = intval($id);
    if ($id <= 0 || (function_exists('emailCapturing') && emailCapturing())) {
        return;
    }
    $settings = logForwardSettings();
    if (!logForwardOn($settings)) {
        return;
    }
    if ($log === 'audit' && !$settings['audit']) {
        return;
    }
    if ($log === 'app' && ((string) $category === LOG_FORWARD_CATEGORY || !logForwardAppLevelSent($level, $settings['app_level']))) {
        return;
    }
    if ($log !== 'audit' && $log !== 'app') {
        return;
    }
    logForwardPending([$log, $id]);
    if (!$registered) {
        $registered = true;
        register_shutdown_function('logForwardFlush');
    }
    $pending = logForwardPending();
    if (count($pending['audit']) + count($pending['app']) >= LOG_FORWARD_BATCH) {
        logForwardFlush();
    }
}

/*
 * Sends what is waiting - read back from the database, so it is what was
 * kept - over one connection, kept open for the rest of the request. Runs
 * when the request ends, and on the way for a long cron run. Never throws.
 */
function logForwardFlush() {
    global $mysqli;
    $pending = logForwardPending(false);
    if (!$pending['audit'] && !$pending['app']) {
        return;
    }
    try {
        $settings = logForwardSettings();
        if (!logForwardOn($settings) || logForwardBackingOff($settings)) {
            return;
        }
        $messages = [];
        if ($pending['audit']) {
            $sql = mysqli_query($mysqli, "SELECT log_id, log_type, log_action, log_description, log_ip, log_user_agent, log_created_at, log_client_id, log_user_id, log_entity_id,
                    user_name, user_email, user_type, client_name
                FROM logs LEFT JOIN users ON user_id = log_user_id AND log_user_id > 0 LEFT JOIN clients ON client_id = log_client_id AND log_client_id > 0
                WHERE log_id IN (" . implode(',', array_unique($pending['audit'])) . ") ORDER BY log_id");
            while ($row = mysqli_fetch_assoc($sql)) {
                $messages[] = [strtotime($row['log_created_at']), logForwardAuditMessage($row, $settings['format'])];
            }
        }
        if ($pending['app']) {
            $sql = mysqli_query($mysqli, "SELECT app_log_id, app_log_category, app_log_type, app_log_details, app_log_created_at FROM app_logs
                WHERE app_log_id IN (" . implode(',', array_unique($pending['app'])) . ") ORDER BY app_log_id");
            while ($row = mysqli_fetch_assoc($sql)) {
                $messages[] = [strtotime($row['app_log_created_at']), logForwardAppMessage($row, $settings['format'])];
            }
        }
        if (!$messages) {
            return;
        }
        // In the order they happened, the two logs together
        usort($messages, function ($a, $b) {
            return $a[0] <=> $b[0];
        });
        $error = '';
        if (!logForwardSend($settings, array_column($messages, 1), $error)) {
            logForwardFailed($settings, count($messages), $error);
        }
    } catch (Throwable $e) {
        error_log('ITFlow log forwarding: ' . $e->getMessage());
    }
}

/*
 * Sends lines over the connection this request keeps open to the server,
 * opening it the first time - true when every one was written. TCP and TLS
 * end each with a newline (RFC 6587 non-transparent framing); UDP sends one
 * datagram each. $keep false closes it after, for a one-off like the test.
 */
function logForwardSend(array $settings, array $lines, &$error = '', $keep = true) {
    static $socket = null;
    static $socket_target = '';
    $error = '';
    $target = logForwardTarget($settings) . ($settings['protocol'] === 'tls' ? ($settings['tls_verify'] ? ' verified' : ' unverified') : '');
    if ($socket && ($socket_target !== $target || !$keep)) {
        @fclose($socket);
        $socket = null;
    }
    if (!$socket) {
        $socket = logForwardConnect($settings, $error);
        $socket_target = $target;
        if (!$socket) {
            return false;
        }
    }
    foreach ($lines as $line) {
        $data = $settings['protocol'] === 'udp' ? $line : $line . "\n";
        if (!logForwardWrite($socket, $data, $error)) {
            @fclose($socket);
            $socket = null;
            return false;
        }
    }
    if (!$keep) {
        // A UDP port nothing listens on answers with "port unreachable", which only shows on the next
        // read - it fails at once, where a port something listens on just has nothing to read in time
        if ($settings['protocol'] === 'udp') {
            stream_set_timeout($socket, 0, 300000);
            $read = @fread($socket, 1);
            if ($read === false && empty(stream_get_meta_data($socket)['timed_out'])) {
                $error = 'the server said nothing is listening on that port';
                @fclose($socket);
                $socket = null;
                return false;
            }
        }
        @fclose($socket);
        $socket = null;
    }
    return true;
}

/** Opens the connection - null, with why in $error, when it can't. */
function logForwardConnect(array $settings, &$error) {
    $host = strpos($settings['host'], ':') !== false ? '[' . $settings['host'] . ']' : $settings['host'];
    $options = [];
    if ($settings['protocol'] === 'tls') {
        $verify = (bool) $settings['tls_verify'];
        $options['ssl'] = [
            'verify_peer' => $verify,
            'verify_peer_name' => $verify,
            'allow_self_signed' => !$verify,
            'peer_name' => $settings['host'],
            'SNI_enabled' => true,
            'crypto_method' => STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | (defined('STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT') ? STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT : 0),
        ];
    }
    // A TLS failure says why only in the warnings on the way, not in $errstr - so they are kept
    $warnings = [];
    set_error_handler(function ($severity, $message) use (&$warnings) {
        $warnings[] = $message;
        return true;
    });
    $errno = 0;
    $errstr = '';
    try {
        $socket = stream_socket_client($settings['protocol'] . "://$host:" . $settings['port'], $errno, $errstr, LOG_FORWARD_TIMEOUT, STREAM_CLIENT_CONNECT, stream_context_create($options));
    } finally {
        restore_error_handler();
    }
    if (!$socket) {
        $error = logForwardReason(array_merge($warnings, [$errstr]));
        return null;
    }
    stream_set_timeout($socket, LOG_FORWARD_TIMEOUT);
    return $socket;
}

/** Writes all of it - false, with why in $error, when it can't. */
function logForwardWrite($socket, $data, &$error) {
    $length = strlen($data);
    $written = 0;
    while ($written < $length) {
        error_clear_last();
        $sent = @fwrite($socket, substr($data, $written));
        if ($sent === false || $sent === 0) {
            $meta = stream_get_meta_data($socket);
            $error = !empty($meta['timed_out']) ? 'the server stopped taking them' : logForwardReason([error_get_last()['message'] ?? '', 'the connection was closed']);
            return false;
        }
        $written += $sent;
    }
    return true;
}

/*
 * Why a connection or write failed, in words - from the messages PHP gave on
 * the way (one, or several), without its own wording around them.
 */
function logForwardReason($messages) {
    $messages = array_values(array_filter(array_map(function ($message) {
        $message = trim(preg_replace('/^.*?(stream_socket_client|fwrite|fread)\(\):\s*/', '', (string) $message));
        return trim(preg_replace('/^Unable to connect to \S+ \((.*)\)$/', '$1', $message));
    }, (array) $messages), function ($message) {
        return $message !== '' && strcasecmp($message, 'Unknown error') !== 0;
    }));
    $all = implode(' ', $messages);
    if (stripos($all, 'certificate verify failed') !== false) {
        return "the server's certificate couldn't be checked - it isn't from an authority this server trusts, has run out, or is for another name";
    }
    if (stripos($all, 'did not match expected CN') !== false || stripos($all, 'peer certificate CN') !== false) {
        return "the server's certificate is for another name";
    }
    if (stripos($all, 'getaddrinfo') !== false || stripos($all, 'php_network_getaddresses') !== false) {
        return "that server's name couldn't be found";
    }
    if (stripos($all, 'Connection refused') !== false) {
        return 'the server refused the connection - nothing is listening on that port';
    }
    if (stripos($all, 'timed out') !== false) {
        return 'the server did not answer in ' . LOG_FORWARD_TIMEOUT . ' seconds';
    }
    if (stripos($all, 'crypto') !== false || stripos($all, 'SSL') !== false) {
        return "it couldn't start TLS with the server - " . ($messages[0] ?? 'it may not take TLS on that port');
    }
    return $messages[0] ?? 'it could not be reached';
}

/** The file that says when sending last failed, so the minute after is skipped. */
function logForwardBackoffFile(array $settings) {
    return rtrim(sys_get_temp_dir(), '/\\') . '/itflow-log-forward-' . substr(md5(__DIR__ . '|' . logForwardTarget($settings)), 0, 16);
}

/** Did sending to this server fail in the last minute? */
function logForwardBackingOff(array $settings) {
    $file = logForwardBackoffFile($settings);
    $at = @filemtime($file);
    return $at !== false && $at > time() - LOG_FORWARD_BACKOFF;
}

/** Sending failed - wait a minute before trying again, and say so in the app log. */
function logForwardFailed(array $settings, $count, $error) {
    @touch(logForwardBackoffFile($settings));
    $what = $count === 1 ? '1 log entry' : "$count log entries";
    logApp(LOG_FORWARD_CATEGORY, 'error', "Couldn't send $what to " . logForwardTarget($settings) . " - $error. Nothing more is sent for a minute; the entries are still here.");
}

/** Sending works again - forget the last failure. */
function logForwardRecovered(array $settings) {
    @unlink(logForwardBackoffFile($settings));
}

/*
 * ---------- the messages ----------
 */

/** How serious an audit log entry is, as a syslog severity: a failure or something turned away is a warning, a deletion a notice, the rest information. */
function logForwardAuditSeverity($action) {
    if (in_array((string) $action, ['Failed', 'MFA Failed', 'Failure', 'Blocked', 'Denied', 'Alert', 'Error'], true)) {
        return 4;
    }
    if (in_array((string) $action, ['Delete', 'Bulk Delete', 'Revoke', 'Discard'], true)) {
        return 5;
    }
    return 6;
}

/** An app log level as a syslog severity. */
function logForwardAppSeverity($level) {
    return ['error' => 3, 'warning' => 4, 'info' => 6, 'debug' => 7][strtolower((string) $level)] ?? 6;
}

/** The name ITFlow sends as - its address, else the server's name. */
function logForwardHostname() {
    global $config_base_url;
    $host = (string) parse_url('https://' . preg_replace('#^https?://#i', '', (string) ($config_base_url ?? '')), PHP_URL_HOST);
    if ($host === '') {
        $host = (string) gethostname();
    }
    $host = preg_replace('/[^\x21-\x7e]/', '', $host);
    return $host !== '' ? substr($host, 0, 255) : '-';
}

/** A time from the database as RFC 3339, in ITFlow's timezone. */
function logForwardTime($datetime) {
    try {
        return (new DateTime((string) $datetime))->format('Y-m-d\TH:i:sP');
    } catch (Throwable $e) {
        return date('Y-m-d\TH:i:sP');
    }
}

/** The RFC 5424 line around a message. */
function logForwardSyslog($severity, $msgid, $time, $message) {
    $message = str_replace(["\r\n", "\r", "\n"], ' ', $message);
    return '<' . (LOG_FORWARD_FACILITY * 8 + $severity) . '>1 ' . logForwardTime($time) . ' ' . logForwardHostname() . " itflow - $msgid - $message";
}

/** A CEF header field - | and \ escaped. */
function logForwardCefHeader($value) {
    return str_replace(['\\', '|', "\r", "\n"], ['\\\\', '\\|', ' ', ' '], (string) $value);
}

/** CEF extensions, from key => value, the empty ones left out - \ and = escaped, line breaks as \n. */
function logForwardCefExtensions(array $fields) {
    $out = [];
    foreach ($fields as $key => $value) {
        if ($value === null || $value === '') {
            continue;
        }
        $out[] = $key . '=' . str_replace(['\\', '=', "\r\n", "\n", "\r"], ['\\\\', '\\=', '\\n', '\\n', '\\n'], (string) $value);
    }
    return implode(' ', $out);
}

/** CEF's severity, 0 to 10, from a syslog one. */
function logForwardCefSeverity($severity) {
    return [0 => 10, 1 => 10, 2 => 9, 3 => 8, 4 => 6, 5 => 4, 6 => 3, 7 => 1][$severity] ?? 3;
}

/** The start of a CEF message. */
function logForwardCefStart($signature, $name, $severity) {
    require_once __DIR__ . '/../includes/app_version.php';
    return 'CEF:0|ITFlow|ITFlow|' . logForwardCefHeader(defined('APP_VERSION') ? APP_VERSION : '') . '|' . logForwardCefHeader($signature) . '|' . logForwardCefHeader($name) . '|' . logForwardCefSeverity($severity) . '|';
}

/** An audit log entry (with user_name, user_email, user_type and client_name) as a syslog line. */
function logForwardAuditMessage(array $row, $format) {
    $severity = logForwardAuditSeverity($row['log_action']);
    $user_id = intval($row['log_user_id']);
    $who = $user_id ? (string) ($row['user_name'] ?? '') : 'ITFlow';
    $who_kind = !$user_id ? 'system' : (intval($row['user_type'] ?? 1) === 2 ? 'portal' : 'agent');
    $ip = trim((string) $row['log_ip']);
    if ($format === 'cef') {
        $message = logForwardCefStart($row['log_type'] . ':' . $row['log_action'], $row['log_type'] . ' ' . $row['log_action'], $severity) . logForwardCefExtensions([
            'rt' => strtotime($row['log_created_at']) * 1000,
            'externalId' => intval($row['log_id']),
            'cat' => $row['log_type'],
            'act' => $row['log_action'],
            'msg' => trim((string) $row['log_description']),
            'suid' => $user_id ?: null,
            'suser' => $who,
            'src' => filter_var($ip, FILTER_VALIDATE_IP) ? $ip : null,
            'requestClientApplication' => $row['log_user_agent'],
            'cs1Label' => $row['client_name'] !== null ? 'Client' : null,
            'cs1' => $row['client_name'],
            'cn1Label' => intval($row['log_client_id']) ? 'Client ID' : null,
            'cn1' => intval($row['log_client_id']) ?: null,
            'cn2Label' => intval($row['log_entity_id']) ? 'Record ID' : null,
            'cn2' => intval($row['log_entity_id']) ?: null,
            'cs2Label' => 'Who',
            'cs2' => $who_kind,
            'dvchost' => logForwardHostname(),
        ]);
    } elseif ($format === 'plain') {
        $details = array_filter([
            $user_id ? "by $who" . ($who_kind === 'portal' ? ' (portal)' : '') : 'by ITFlow',
            $row['client_name'] !== null ? 'for ' . $row['client_name'] : '',
            $ip !== '' ? "from $ip" : '',
            'entry ' . intval($row['log_id']),
        ]);
        $message = $row['log_type'] . ' ' . $row['log_action'] . ': ' . trim((string) $row['log_description']) . ' (' . implode(', ', $details) . ')';
    } else {
        $message = json_encode([
            'log' => 'audit',
            'id' => intval($row['log_id']),
            'time' => logForwardTime($row['log_created_at']),
            'type' => (string) $row['log_type'],
            'action' => (string) $row['log_action'],
            'description' => trim((string) $row['log_description']),
            'severity' => ['', '', '', 'error', 'warning', 'notice', 'info'][$severity],
            'who' => $who_kind,
            'user_id' => $user_id,
            'user' => $who,
            'user_email' => $user_id ? (string) ($row['user_email'] ?? '') : '',
            'client_id' => intval($row['log_client_id']),
            'client' => (string) ($row['client_name'] ?? ''),
            'record_id' => intval($row['log_entity_id']),
            'ip' => $ip,
            'user_agent' => (string) $row['log_user_agent'],
            'host' => logForwardHostname(),
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    }
    return logForwardSyslog($severity, 'audit', $row['log_created_at'], $message);
}

/** An app log entry as a syslog line. */
function logForwardAppMessage(array $row, $format) {
    $level = strtolower((string) $row['app_log_type']);
    $severity = logForwardAppSeverity($level);
    $category = (string) $row['app_log_category'];
    if ($format === 'cef') {
        $message = logForwardCefStart('app:' . $category, $category . ' ' . $level, $severity) . logForwardCefExtensions([
            'rt' => strtotime($row['app_log_created_at']) * 1000,
            'externalId' => intval($row['app_log_id']),
            'cat' => $category,
            'msg' => trim((string) $row['app_log_details']),
            'cs1Label' => 'Level',
            'cs1' => $level,
            'dvchost' => logForwardHostname(),
        ]);
    } elseif ($format === 'plain') {
        $message = "$level $category: " . trim((string) $row['app_log_details']) . ' (app log entry ' . intval($row['app_log_id']) . ')';
    } else {
        $message = json_encode([
            'log' => 'app',
            'id' => intval($row['app_log_id']),
            'time' => logForwardTime($row['app_log_created_at']),
            'level' => $level,
            'category' => $category,
            'details' => trim((string) $row['app_log_details']),
            'host' => logForwardHostname(),
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    }
    return logForwardSyslog($severity, 'app', $row['app_log_created_at'], $message);
}

/*
 * Sends one test message with these settings, now, and says how it went:
 * ['ok', 'error']. Over UDP it can only say a closed port was turned away -
 * the message going out is no sign it arrived.
 */
function logForwardTest(array $settings, $by = '') {
    $now = date('Y-m-d H:i:s');
    $text = 'Test message from ITFlow' . ($by !== '' ? " - sent by $by" : '') . ' from Admin > Settings > Logs';
    if ($settings['format'] === 'cef') {
        $message = logForwardCefStart('test', 'Test message', 6) . logForwardCefExtensions(['rt' => time() * 1000, 'msg' => $text, 'dvchost' => logForwardHostname()]);
    } elseif ($settings['format'] === 'plain') {
        $message = $text;
    } else {
        $message = json_encode(['log' => 'test', 'time' => logForwardTime($now), 'message' => $text, 'host' => logForwardHostname()], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    }
    $error = '';
    $ok = logForwardSend($settings, [logForwardSyslog(6, 'test', $now, $message)], $error, false);
    if ($ok) {
        logForwardRecovered($settings);
    }
    return ['ok' => $ok, 'error' => $error];
}
