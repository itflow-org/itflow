<?php

/*
 * Admin > Settings > Logs - how long the audit log (with API key use) and
 * the app logs are kept, each its own number of days or forever, and sending
 * their entries on to a syslog server or SIEM as they are written
 * (functions/log_forward.php). Saved by edit_log_settings, or
 * test_log_forward, which saves and sends a test message
 * (admin/post/settings_logs.php).
 */

require_once "includes/inc_all_admin.php";

// Read here rather than with every page's settings, so the page still opens before the update that adds them
$retention = appLogRetention();
$forward = logForwardSettings();
$audit_size = appLogSize('logs');
$app_size = appLogSize('app_logs');

// The last time sending failed, in the last day
$forward_failure = null;
if ($forward['enabled']) {
    $forward_failure = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT app_log_id, app_log_details, app_log_created_at FROM app_logs
        WHERE app_log_category = '" . escapeSql(LOG_FORWARD_CATEGORY) . "' AND app_log_type = 'error' AND app_log_created_at > NOW() - INTERVAL 1 DAY
        ORDER BY app_log_id DESC LIMIT 1"));
}

// What an entry looks like as it is sent - the latest audit log entry, in the format picked
$sample_row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT log_id, log_type, log_action, log_description, log_ip, log_user_agent, log_created_at, log_client_id, log_user_id, log_entity_id,
        user_name, user_email, user_type, client_name
    FROM logs LEFT JOIN users ON user_id = log_user_id AND log_user_id > 0 LEFT JOIN clients ON client_id = log_client_id AND log_client_id > 0
    ORDER BY log_id DESC LIMIT 1"));
$samples = [];
if ($sample_row) {
    foreach (array_keys(LOG_FORWARD_FORMATS) as $format) {
        $samples[$format] = logForwardAuditMessage($sample_row, $format);
    }
}

$size_line = function ($size, $url) {
    if (!$size['count']) {
        return 'Nothing in it yet.';
    }
    return '<a href="' . $url . '">' . number_format($size['count']) . ' entr' . ($size['count'] === 1 ? 'y' : 'ies') . '</a>, the oldest from ' . escapeHtml(date('M j, Y', strtotime($size['oldest']))) . '.';
};

// The days field and its keep forever switch, for one log
$retention_field = function ($name, $forever_name, $days, $label, $help) {
    $id = preg_replace('/[^a-z_]/', '', $name);
    ob_start(); ?>
    <div class="mb-3" data-log-retention="<?= $id ?>">
        <label class="form-label" for="<?= $id ?>"><?= $label ?></label>
        <div class="row g-2 align-items-center">
            <div class="col-sm-6 col-lg-4">
                <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-calendar-alt"></i></span>
                    <input type="number" class="form-control" id="<?= $id ?>" name="<?= $name ?>" min="1" max="36500" placeholder="Days" value="<?= $days > 0 ? $days : '' ?>" <?= $days > 0 ? 'required' : 'disabled' ?>>
                    <span class="input-group-text">days</span>
                </div>
            </div>
            <div class="col-auto">
                <div class="form-check form-switch mb-0">
                    <input type="checkbox" class="form-check-input" name="<?= $forever_name ?>" value="1" id="<?= $id ?>_forever" data-log-forever="<?= $id ?>" <?= $days > 0 ? '' : 'checked' ?>>
                    <label class="form-check-label" for="<?= $id ?>_forever">Keep forever</label>
                </div>
            </div>
        </div>
        <div class="form-text"><?= $help ?></div>
    </div>
    <?php return ob_get_clean();
};

?>

<div class="card card-dark">
    <div class="card-header py-3">
        <h3 class="card-title"><i class="fas fa-fw fa-scroll me-2"></i>Logs</h3>
    </div>
    <div class="card-body">
        <form action="post.php" method="post" autocomplete="off" id="logSettingsForm">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

            <h4>Keeping logs</h4>
            <p class="text-secondary small">Each night, entries older than this are deleted for good. Keep the audit log as long as your clients or insurers ask you to be able to say who did what.</p>

            <?= $retention_field('config_log_retention', 'audit_forever', $retention['audit'], 'Audit log', 'Who did what, signed in, looked at a password or exported something. How each API key is used is kept as long. ' . $size_line($audit_size, 'audit_logs.php')) ?>

            <?= $retention_field('config_app_log_retention', 'app_forever', $retention['app'], 'App logs', 'What ITFlow itself does - cron runs, mail, payment providers - and the errors it runs into. They can also be cleared by hand. ' . $size_line($app_size, 'app_logs.php')) ?>

            <hr>

            <h4 id="forwarding">Sending logs on</h4>
            <p class="text-secondary small">Each entry can also go to a syslog server or SIEM - Graylog, Splunk, Wazuh, Microsoft Sentinel and the like - as it is written, as a syslog message (RFC 5424, facility local0). A server that can't be reached doesn't slow ITFlow down: it is tried again a minute later, and the entries in between stay in ITFlow's own logs.</p>

            <div class="mb-3">
                <div class="form-check form-switch">
                    <input type="checkbox" class="form-check-input" name="config_log_forward_enabled" value="1" id="logForwardEnabled" <?= $forward['enabled'] ? 'checked' : '' ?>>
                    <label class="form-check-label" for="logForwardEnabled">Send log entries to a syslog server or SIEM</label>
                </div>
            </div>

            <?php if ($forward_failure) { ?>
                <div class="alert alert-warning small" data-log-forward-failure>
                    <i class="fas fa-fw fa-exclamation-triangle me-1"></i>Sending failed <?= escapeHtml(timeAgo($forward_failure['app_log_created_at'])) ?>: <?= escapeHtml($forward_failure['app_log_details']) ?>
                    <a class="ms-1" href="app_logs.php?category=<?= urlencode(LOG_FORWARD_CATEGORY) ?>&amp;open=<?= intval($forward_failure['app_log_id']) ?>">See it</a>
                </div>
            <?php } elseif (logForwardOn($forward)) { ?>
                <div class="alert alert-info small" data-log-forward-status>
                    <i class="fas fa-fw fa-share-square me-1"></i>Sending to <?= escapeHtml(logForwardTarget($forward)) ?> as <?= escapeHtml(LOG_FORWARD_FORMATS[$forward['format']]) ?>.
                </div>
            <?php } ?>

            <div data-log-forward-fields>
                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label">Server</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-server"></i></span>
                            <input type="text" class="form-control" name="config_log_forward_host" maxlength="200" placeholder="logs.example.com or 10.0.0.20" value="<?= escapeHtml($forward['host']) ?>" pattern="\[?[A-Za-z0-9.:_\-]{1,200}\]?" title="A name or an IP address">
                        </div>
                    </div>
                    <div class="col-md-2 col-sm-4">
                        <label class="form-label">Port</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-plug"></i></span>
                            <input type="number" class="form-control" name="config_log_forward_port" min="1" max="65535" value="<?= intval($forward['port']) ?>" required>
                        </div>
                    </div>
                    <div class="col-md-4 col-sm-8">
                        <label class="form-label">Over</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-exchange-alt"></i></span>
                            <select class="form-select select2" name="config_log_forward_protocol" id="logForwardProtocol">
                                <?php foreach (LOG_FORWARD_PROTOCOLS as $key => $label) { ?>
                                    <option value="<?= $key ?>" <?= $forward['protocol'] === $key ? 'selected' : '' ?><?= selectOptionData(['description' => logForwardProtocolHelp()[$key]]) ?>><?= $label ?></option>
                                <?php } ?>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="mb-3 <?= $forward['protocol'] === 'tls' ? '' : 'd-none' ?>" data-log-forward-tls>
                    <div class="form-check form-switch">
                        <input type="checkbox" class="form-check-input" name="config_log_forward_tls_verify" value="1" id="logForwardVerify" <?= $forward['tls_verify'] ? 'checked' : '' ?>>
                        <label class="form-check-label" for="logForwardVerify">Check the server's certificate</label>
                    </div>
                    <div class="form-text">Turn off only for a server on your own network with a certificate it made itself - anyone in between could otherwise read the entries.</div>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label">As</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-code"></i></span>
                            <select class="form-select select2" name="config_log_forward_format" id="logForwardFormat">
                                <?php foreach (LOG_FORWARD_FORMATS as $key => $label) { ?>
                                    <option value="<?= $key ?>" <?= $forward['format'] === $key ? 'selected' : '' ?><?= selectOptionData(['description' => logForwardFormatHelp()[$key]]) ?>><?= $label ?></option>
                                <?php } ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">App logs</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-clipboard-list"></i></span>
                            <select class="form-select select2" name="config_log_forward_app_level">
                                <?php foreach (logForwardAppLevels() as $key => $label) { ?>
                                    <option value="<?= $key ?>" <?= $forward['app_level'] === $key ? 'selected' : '' ?>><?= $label ?></option>
                                <?php } ?>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="mb-3">
                    <div class="form-check form-switch">
                        <input type="checkbox" class="form-check-input" name="config_log_forward_audit" value="1" id="logForwardAudit" <?= $forward['audit'] ? 'checked' : '' ?>>
                        <label class="form-check-label" for="logForwardAudit">The audit log - every entry</label>
                    </div>
                </div>

                <?php if ($samples) { ?>
                    <details class="mb-3" data-log-forward-sample>
                        <summary class="small">What an entry looks like</summary>
                        <div class="small text-body-secondary mt-2">The latest audit log entry, as it would be sent:</div>
                        <?php foreach ($samples as $format => $sample) { ?>
                            <pre class="small bg-body-tertiary border rounded p-2 mt-1 mb-0 text-wrap text-break <?= $format === $forward['format'] ? '' : 'd-none' ?>" data-log-forward-sample-format="<?= $format ?>"><?= escapeHtml($sample) ?></pre>
                        <?php } ?>
                    </details>
                <?php } ?>
            </div>

            <hr>

            <button type="submit" name="edit_log_settings" class="btn btn-primary text-bold"><i class="fas fa-check me-2"></i>Save</button>
            <button type="submit" name="test_log_forward" class="btn btn-outline-secondary ms-2" title="Saves, then sends one test message to the server"><i class="fas fa-paper-plane me-2"></i>Save and send a test</button>

        </form>
    </div>
</div>

<script>
    (function () {
        var form = document.getElementById('logSettingsForm');

        // Keep forever leaves the days alone, greyed out
        form.querySelectorAll('[data-log-forever]').forEach(function (toggle) {
            var days = document.getElementById(toggle.dataset.logForever);
            var sync = function () {
                days.disabled = toggle.checked;
                days.required = !toggle.checked;
                if (!toggle.checked && days.value === '') {
                    days.value = 90;
                }
            };
            toggle.addEventListener('change', sync);
        });

        // The certificate switch is for TLS, and the sample follows the format
        var protocol = document.getElementById('logForwardProtocol');
        var format = document.getElementById('logForwardFormat');
        var tls = form.querySelector('[data-log-forward-tls]');
        var follow = function () {
            tls.classList.toggle('d-none', protocol.value !== 'tls');
            form.querySelectorAll('[data-log-forward-sample-format]').forEach(function (pre) {
                pre.classList.toggle('d-none', pre.dataset.logForwardSampleFormat !== format.value);
            });
        };
        [protocol, format].forEach(function (select) {
            select.addEventListener('change', follow);
            if (window.jQuery) {
                window.jQuery(select).on('change', follow);
            }
        });
    })();
</script>

<?php
require_once "../includes/footer.php";
