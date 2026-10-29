<?php

/*
 * ITFlow - POST handler for Admin > Settings > Logs: how long each log is
 * kept, and sending entries on to a syslog server or SIEM. Save and send a
 * test saves first, then sends one test message with what was saved
 * (logForwardTest(), functions/log_forward.php). What changed is in the audit
 * log.
 */

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

if (isset($_POST['edit_log_settings']) || isset($_POST['test_log_forward'])) {

    validateCSRFToken();

    // Days, or 0 for keep forever
    $days = function ($name, $forever) {
        if (!empty($_POST[$forever])) {
            return 0;
        }
        return min(36500, max(1, intval($_POST[$name] ?? 90)));
    };
    $audit_days = $days('config_log_retention', 'audit_forever');
    $app_days = $days('config_app_log_retention', 'app_forever');

    $was_retention = appLogRetention();
    $was = logForwardSettings();
    $posted_host = is_string($_POST['config_log_forward_host'] ?? null) ? trim($_POST['config_log_forward_host']) : '';
    $forward = logForwardTidy([
        'enabled' => $_POST['config_log_forward_enabled'] ?? 0,
        'host' => $posted_host,
        'port' => $_POST['config_log_forward_port'] ?? 514,
        'protocol' => $_POST['config_log_forward_protocol'] ?? 'udp',
        'format' => $_POST['config_log_forward_format'] ?? 'json',
        'audit' => $_POST['config_log_forward_audit'] ?? 0,
        'app_level' => $_POST['config_log_forward_app_level'] ?? '',
        // Only asked over TLS - kept as it was otherwise
        'tls_verify' => ($_POST['config_log_forward_protocol'] ?? '') === 'tls' ? ($_POST['config_log_forward_tls_verify'] ?? 0) : $was['tls_verify'],
    ]);

    // Nowhere to send to - saved, but off
    $warning = '';
    if ($posted_host !== '' && $forward['host'] === '') {
        $warning = "That isn't a server name or address, so sending stays off.";
        $forward['enabled'] = 0;
    } elseif ($forward['enabled'] && $forward['host'] === '') {
        $warning = 'Sending stays off until there is a server to send to.';
        $forward['enabled'] = 0;
    }

    $host_sql = $forward['host'] !== '' ? "'" . escapeSql($forward['host']) . "'" : 'NULL';
    mysqli_query($mysqli, "UPDATE settings SET config_log_retention = $audit_days, config_app_log_retention = $app_days,
        config_log_forward_enabled = {$forward['enabled']}, config_log_forward_host = $host_sql, config_log_forward_port = {$forward['port']},
        config_log_forward_protocol = '{$forward['protocol']}', config_log_forward_format = '{$forward['format']}', config_log_forward_audit = {$forward['audit']},
        config_log_forward_app_level = '{$forward['app_level']}', config_log_forward_tls_verify = {$forward['tls_verify']}
        WHERE company_id = 1");

    // What changed, in words
    $changes = [];
    if ($audit_days !== $was_retention['audit']) {
        $changes[] = 'the audit log kept ' . strtolower(appLogRetentionText($audit_days)) . ' (was ' . strtolower(appLogRetentionText($was_retention['audit'])) . ')';
    }
    if ($app_days !== $was_retention['app']) {
        $changes[] = 'app logs kept ' . strtolower(appLogRetentionText($app_days)) . ' (was ' . strtolower(appLogRetentionText($was_retention['app'])) . ')';
    }
    $target = function ($settings) {
        return logForwardTarget($settings) . ($settings['protocol'] === 'tls' && !$settings['tls_verify'] ? ' (certificate not checked)' : '');
    };
    $what = function ($settings) {
        return implode(' and ', array_filter([$settings['audit'] ? 'the audit log' : '', $settings['app_level'] !== '' ? 'app ' . strtolower(logForwardAppLevels()[$settings['app_level']]) : ''])) ?: 'nothing';
    };
    $on = logForwardOn($forward);
    $was_on = logForwardOn($was);
    if ($on && !$was_on) {
        $changes[] = 'sending logs to ' . $target($forward) . ' as ' . LOG_FORWARD_FORMATS[$forward['format']] . ' - ' . $what($forward);
    } elseif (!$on && $was_on) {
        $changes[] = 'stopped sending logs (was to ' . $target($was) . ')';
    } elseif ($on) {
        if ($target($forward) !== $target($was)) {
            $changes[] = 'sending logs to ' . $target($forward) . ' (was ' . $target($was) . ')';
        }
        if ($forward['format'] !== $was['format']) {
            $changes[] = 'sent as ' . LOG_FORWARD_FORMATS[$forward['format']] . ' (was ' . LOG_FORWARD_FORMATS[$was['format']] . ')';
        }
        if ($what($forward) !== $what($was)) {
            $changes[] = 'sending ' . $what($forward) . ' (was ' . $what($was) . ')';
        }
    }

    // Read again, so what is logged from here on goes where it now should
    logForwardSettings(true);

    if ($changes) {
        logAudit("Settings", "Edit", "$session_name edited log settings - " . escapeSql(implode('; ', $changes)));
    }

    if (isset($_POST['test_log_forward'])) {
        if ($forward['host'] === '') {
            flashAlert($warning !== '' ? $warning : 'Give the server to send to first.', 'error');
            redirect();
        }
        $test = logForwardTest($forward, stripslashes($session_name));
        $target = logForwardTarget($forward);
        if ($test['ok']) {
            flashAlert(($forward['protocol'] === 'udp'
                ? "Saved, and a test message went to $target. UDP doesn't say whether it arrived - check the server for it."
                : "Saved, and $target took the test message.") . ($forward['enabled'] ? '' : ' Sending is still off - switch it on to send entries.'));
        } else {
            flashAlert("Saved, but the test message couldn't be sent to $target - " . $test['error'] . '.', 'error');
        }
        redirect();
    }

    flashAlert($warning !== '' ? "Log settings saved. $warning" : 'Log settings saved', $warning !== '' ? 'warning' : 'success');

    redirect();

}
