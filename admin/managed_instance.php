<?php

/*
 * Admin > Managed Instances > one instance. At the top, how it is - waiting to connect,
 * checking in or gone quiet - with what can be done to it: sign in as support, update it,
 * check for updates, make it read-only or suspend it. Under that, what needs a look from its
 * last report, the connection code while it's waiting for one, who holds its seats, its history
 * a day at a time over 30 days, 90 days or a year (managedInstanceHistory()), and what it has
 * been asked and how that went; alongside, what this manager sets for it and what it reported
 * about itself.
 *
 * A connection code shows once, on the page its making redirects to - only its hash is kept
 * (managedInstanceNewCode()). The handlers are admin/post/managed_instances.php.
 */

require_once "includes/inc_all_admin.php";

// Off unless config.php turns it on - the list says how
if (!managerEnabled() || !settingsColumnExists($mysqli, 'config_manager_keys')) {
    redirect('managed_instances.php');
}

$instance_id = intval($_GET['id'] ?? 0);
$row = managedInstanceRow($instance_id);

if (!$row) { ?>
    <div class="alert alert-secondary"><i class="fas fa-fw fa-question-circle me-2"></i>That instance is gone - it may have been deleted. <a href="managed_instances.php" class="alert-link">Managed Instances</a></div>
    <?php
    require_once "../includes/footer.php";
    exit;
}

$name = escapeHtml($row['managed_instance_name']);
$page_title = $row['managed_instance_name'];
$removed = !empty($row['managed_instance_archived_at']);
$health = managedInstanceHealth($row);
// The tickets its problems are on (managerIssueTickets())
$issue_tickets = managedInstanceIssueTickets($instance_id);
$state = $health['state'];
$report = $health['report'];
$app = (array) ($report['app'] ?? []);
$update = (array) ($report['update'] ?? []);
$agents = (array) ($report['agents'] ?? []);
$backup = (array) ($report['backup'] ?? []);
$cron = (array) ($report['cron'] ?? []);
$disk = (array) ($report['disk'] ?? []);
$url = (string) $row['managed_instance_url'];
$mode = (string) $row['managed_instance_mode'];
$seat_limit = intval($row['managed_instance_seat_limit']);
$csrf = $_SESSION['csrf_token'];
$connected = !empty($row['managed_instance_secret_hash']);
$client_id = intval($row['managed_instance_client_id']);

// A code made a moment ago - shown this once
$new_code = (string) ($_SESSION['managed_instance_code'][$instance_id] ?? '');
unset($_SESSION['managed_instance_code'][$instance_id]);

$worst = $health['issues'][0][0] ?? '';
$hero = match (true) {
    $removed => ['tone' => 'secondary', 'icon' => 'fa-unlink'],
    $state === 'pending' => ['tone' => 'secondary', 'icon' => 'fa-plug'],
    $state === 'waiting' => ['tone' => 'warning', 'icon' => 'fa-hourglass-half'],
    $state === 'offline' => ['tone' => 'danger', 'icon' => 'fa-server'],
    $worst === 'danger' => ['tone' => 'danger', 'icon' => 'fa-server'],
    $worst === 'warning' => ['tone' => 'warning', 'icon' => 'fa-server'],
    default => ['tone' => 'success', 'icon' => 'fa-server'],
};

// What it has been asked, newest first
$commands = [];
$sql = mysqli_query($mysqli, "SELECT * FROM managed_instance_commands WHERE managed_command_instance_id = $instance_id
    ORDER BY managed_command_created_at DESC, managed_command_id DESC LIMIT 15");
while ($command = mysqli_fetch_assoc($sql)) {
    $commands[] = $command;
}
$waiting = (bool) array_filter($commands, function ($command) {
    return in_array($command['managed_command_status'], ['queued', 'sent', 'running'], true);
});
$updating = (bool) array_filter($commands, function ($command) {
    return $command['managed_command'] === 'update_now' && in_array($command['managed_command_status'], ['queued', 'sent', 'running'], true);
});

$can_act = !$removed && $connected && $url !== '';

$status_pill = function (string $status) {
    $tone = ['queued' => 'secondary', 'sent' => 'info', 'running' => 'primary', 'done' => 'success', 'failed' => 'danger', 'cancelled' => 'secondary'][$status] ?? 'secondary';
    $words = ['queued' => 'Waiting for its check-in', 'sent' => 'Sent', 'running' => 'Running', 'done' => 'Done', 'failed' => 'Failed', 'cancelled' => 'Cancelled'][$status] ?? $status;
    return '<span class="badge rounded-pill bg-' . $tone . '-subtle text-' . $tone . '-emphasis border border-' . $tone . '-subtle fw-normal">' . escapeHtml($words) . '</span>';
};

$fact = function (string $label, string $value_html) {
    return '<li class="list-group-item d-flex justify-content-between gap-3"><span class="text-body-secondary text-nowrap">' . $label . '</span><span class="text-end min-w-0">' . $value_html . '</span></li>';
};
$when = function ($datetime, string $never = 'Never') {
    return $datetime ? '<span title="' . escapeHtml($datetime) . '">' . escapeHtml(timeAgo($datetime)) . '</span>' : '<span class="text-body-secondary">' . $never . '</span>';
};
$size = function ($mb) {
    $mb = floatval($mb);
    return $mb >= 1024 ? round($mb / 1024, 1) . ' GB' : round($mb) . ' MB';
};

// Errors today, and the categories they were logged under - each opening its error log on the
// instance, signed in as support. Only once it sends the categories: an older instance would
// land on its start page
$error_count = intval($report['errors_24h'] ?? 0);
$error_log_link = $can_act && array_key_exists('errors_by_category', $report);
$error_link = function (string $text_html, string $category = '') use ($instance_id, $csrf, $error_log_link) {
    if (!$error_log_link) {
        return $text_html;
    }
    $href = "post.php?managed_support_sign_in=$instance_id&to=errors" . ($category !== '' ? '&category=' . rawurlencode($category) : '') . "&csrf_token=$csrf";
    return '<a href="' . escapeHtml($href) . '" target="_blank" rel="noopener" title="Opens its error log in a new tab, signed in as its support user">' . $text_html . '</a>';
};
$errors_html = '0';
if ($error_count > 0) {
    $errors_html = '<span class="text-warning-emphasis fw-semibold">' . $error_link((string) $error_count) . '</span>';
    foreach (managedInstanceErrorCategories($report) as $category => $logged) {
        $errors_html .= '<div class="text-body-secondary">' . $error_link(escapeHtml($category !== '' ? $category : 'No category'), $category) . ' &middot; ' . intval($logged) . '</div>';
    }
}

?>

<nav class="mb-2 small"><a href="managed_instances.php<?= $removed ? '?show=removed' : '' ?>" class="text-body-secondary"><i class="fas fa-fw fa-arrow-left me-1"></i>Managed Instances</a></nav>

<div class="row g-3">

    <div class="col-lg-8">

        <!-- How it is, and what can be done to it -->
        <div class="card mb-3" data-managed-state="<?= $removed ? 'removed' : $state ?>">
            <div class="card-body p-4"<?= $waiting && !$removed ? ' data-itflow-reload-seconds="20"' : '' ?>>
                <div class="d-flex align-items-start gap-3">
                    <span class="itflow-asset-icon itflow-asset-icon-lg rounded-circle bg-<?= $hero['tone'] ?>-subtle text-<?= $hero['tone'] ?>-emphasis" aria-hidden="true"><i class="fas <?= $hero['icon'] ?>"></i></span>
                    <div class="flex-grow-1 min-w-0">
                        <h2 class="h4 mb-1 text-break"><?= $name ?>
                            <?php if ($removed) { ?>
                                <span class="badge rounded-pill bg-secondary-subtle text-secondary-emphasis border border-secondary-subtle fw-normal fs-6 align-middle"><i class="fas fa-fw fa-unlink me-1"></i>Removed</span>
                            <?php } elseif ($mode !== 'active') { ?>
                                <span class="fs-6 align-middle"><?= managedModePill($mode) ?></span>
                            <?php } ?>
                        </h2>
                        <div class="text-body-secondary small mb-2 d-flex flex-wrap column-gap-3">
                            <?php if ($url !== '') { ?>
                                <a href="<?= escapeHtml($url) ?>" target="_blank" rel="noopener" class="text-reset text-break"><i class="fas fa-fw fa-globe me-1"></i><?= escapeHtml(preg_replace('#^https?://#', '', $url)) ?></a>
                            <?php } ?>
                            <?php if ($row['client_name'] !== null) { ?>
                                <a href="/agent/client_overview.php?client_id=<?= $client_id ?>" class="text-reset"><i class="fas fa-fw fa-building me-1"></i><?= escapeHtml($row['client_name']) ?></a>
                            <?php } ?>
                            <?php if (!empty($report['company']) && $report['company'] !== $row['managed_instance_name']) { ?>
                                <span title="The company name it has set"><i class="fas fa-fw fa-id-badge me-1"></i><?= escapeHtml($report['company']) ?></span>
                            <?php } ?>
                        </div>

                        <p class="mb-0 text-body-secondary">
                            <?php if ($removed) { ?>
                                Removed <?= escapeHtml(timeAgo($row['managed_instance_archived_at'])) ?>. It lets go at its next check-in - what this manager set comes off it, and its support user is archived.
                            <?php } elseif ($state === 'pending') { ?>
                                <?= $row['managed_instance_enrolled_at'] ? 'It disconnected itself. To manage it again, it needs a new connection code.' : 'Waiting to connect - give its admin the connection code below.' ?>
                            <?php } elseif ($state === 'waiting') {
                                $cron_command = (string) ($report['cron']['command'] ?? ''); ?>
                                <span class="text-warning-emphasis"><i class="fas fa-fw fa-hourglass-half me-1"></i>Connected <?= escapeHtml(timeAgo($row['managed_instance_enrolled_at'] ?? $row['managed_instance_last_checkin_at'])) ?> - waiting for its cron job.</span>
                                It checks in from cron every minute, so add the cron job in its hosting control panel: every minute (<code>* * * * *</code>)<?= $cron_command !== '' ? ', running' : '' ?>
                                <?php if ($cron_command !== '') { ?>
                                    <span class="d-flex align-items-center gap-2 mt-2" data-managed-cron-command>
                                        <code class="text-break"><?= escapeHtml($cron_command) ?></code>
                                        <button type="button" class="btn btn-sm btn-link p-0 clipboardjs" title="Copy" data-clipboard-text="<?= escapeHtml($cron_command) ?>"><i class="far fa-copy"></i></button>
                                    </span>
                                <?php } ?>
                            <?php } elseif ($state === 'offline') { ?>
                                <span class="text-danger-emphasis"><i class="fas fa-fw fa-exclamation-circle me-1"></i><?= $row['managed_instance_last_checkin_at'] ? 'Not checked in since ' . escapeHtml(timeAgo($row['managed_instance_last_checkin_at'])) : 'Connected, but it hasn\'t checked in' ?>.</span>
                                It checks in every minute from its own cron - either cron has stopped there, the server is down, or it can't reach this ITFlow.
                            <?php } else { ?>
                                Checked in <?= escapeHtml(timeAgo($row['managed_instance_last_checkin_at'])) ?><?= !empty($app['version']) ? ', on ' . escapeHtml($app['version']) : '' ?>.
                                <?= $health['issues'] ? count($health['issues']) . ' thing' . (count($health['issues']) === 1 ? '' : 's') . ' to look at below.' : 'Everything looks right.' ?>
                            <?php } ?>
                        </p>

                        <?php if ($updating) { ?>
                            <p class="mb-0 mt-2 text-primary-emphasis"><i class="fas fa-fw fa-sync-alt fa-spin me-1"></i>Updating - it picks the request up at its next check-in and updates in the background. This page refreshes itself.</p>
                        <?php } elseif (!$removed && $connected && !empty($update['available']) && $row['managed_instance_update_policy'] === 'auto' && !empty($row['managed_instance_update_seen_at'])) {
                            // When the automatic update comes - managerCheckin() decides at each check-in
                            $auto_at = strtotime($row['managed_instance_update_seen_at']) + intval($row['managed_instance_update_delay_days']) * 86400; ?>
                            <p class="mb-0 mt-2 small text-body-secondary" data-managed-auto-update><i class="fas fa-fw fa-robot me-1"></i>It updates itself
                                <?= !empty($update['security']) || $auto_at <= time() ? 'at its next check-in' : 'on ' . escapeHtml(date('M j', $auto_at)) . ', ' . intval($row['managed_instance_update_delay_days']) . ' days after it was first seen - or update it now' ?>.</p>
                        <?php } ?>

                        <?php if (!$removed) { ?>
                            <div class="d-flex flex-wrap gap-2 mt-3" data-managed-actions>
                                <?php if ($can_act) { ?>
                                    <a class="btn btn-primary" href="post.php?managed_support_sign_in=<?= $instance_id ?>&csrf_token=<?= $csrf ?>" target="_blank" rel="noopener" data-managed-support
                                        title="Opens it in a new tab, signed in as its support user - for two hours"><i class="fas fa-fw fa-sign-in-alt me-2"></i>Sign In as Support</a>
                                    <?php if (!empty($update['available']) && !$updating) { ?>
                                        <a class="btn btn-outline-primary confirm-link" href="post.php?managed_command=update_now&id=<?= $instance_id ?>&csrf_token=<?= $csrf ?>"
                                            data-confirm-title="Update <?= $name ?>?" data-confirm-text="It updates its files and then its database, starting at its next check-in. Make sure it has a backup." data-confirm-button="Update" data-confirm-icon="question">
                                            <i class="fas fa-fw fa-download me-2"></i>Update<?= !empty($update['release']) ? ' to ' . escapeHtml($update['release']) : ' now' ?>
                                        </a>
                                    <?php } ?>
                                <?php } ?>
                                <button type="button" class="btn btn-light ajax-modal" data-modal-url="modals/managed_instance/managed_instance_form.php?id=<?= $instance_id ?>"><i class="fas fa-fw fa-edit me-2"></i>Edit</button>
                                <div class="dropdown">
                                    <button class="btn btn-light" type="button" data-bs-toggle="dropdown" title="More actions"><i class="fas fa-fw fa-ellipsis-v"></i></button>
                                    <div class="dropdown-menu dropdown-menu-end">
                                        <?php if ($can_act) { ?>
                                            <a class="dropdown-item" href="post.php?managed_command=check_updates&id=<?= $instance_id ?>&csrf_token=<?= $csrf ?>" title="It checks at its next check-in, and the answer comes with the one after"><i class="fas fa-fw fa-sync-alt text-secondary me-2"></i>Check for Updates</a>
                                            <div class="dropdown-divider"></div>
                                        <?php } ?>
                                        <?php foreach (MANAGED_MODES as $mode_key => $mode_label) {
                                            if ($mode_key === $mode) {
                                                continue;
                                            }
                                            $mode_text = [
                                                'active' => ['Make it active', 'fa-check-circle', 'Everything works again for its users.'],
                                                'read_only' => ['Make it read-only', 'fa-lock', 'Its users can sign in and look, export and download - but nothing can be changed. The portal and guest links work the same way.'],
                                                'suspended' => ['Suspend it', 'fa-ban', 'Nobody can use it - agents, the portal, guest links and the API get a notice instead. Its data stays as it is, and backups and updates still run.'],
                                            ][$mode_key]; ?>
                                            <a class="dropdown-item confirm-link<?= $mode_key === 'suspended' ? ' text-danger' : '' ?>" href="post.php?managed_instance_mode=<?= $mode_key ?>&id=<?= $instance_id ?>&csrf_token=<?= $csrf ?>"
                                                data-confirm-title="<?= escapeHtml($mode_text[0]) ?>?" data-confirm-text="<?= escapeHtml($mode_text[2] . ($connected ? ' It takes effect at its next check-in, within a minute.' : '')) ?>" data-confirm-button="<?= escapeHtml(MANAGED_MODES[$mode_key]) ?>">
                                                <i class="fas fa-fw <?= $mode_text[1] ?> me-2 <?= $mode_key === 'suspended' ? '' : 'text-secondary' ?>"></i><?= escapeHtml($mode_text[0]) ?>
                                            </a>
                                        <?php } ?>
                                        <div class="dropdown-divider"></div>
                                        <a class="dropdown-item confirm-link" href="post.php?managed_instance_new_code=<?= $instance_id ?>&csrf_token=<?= $csrf ?>"
                                            data-confirm-title="Make a new connection code?" data-confirm-text="<?= $connected ? 'For moving it to a new server, or connecting a restored copy. Whatever connects with the new code takes the place of what\'s connected now.' : 'The code made before stops working.' ?>" data-confirm-button="Make a code">
                                            <i class="fas fa-fw fa-key text-secondary me-2"></i>New Connection Code
                                        </a>
                                        <div class="dropdown-divider"></div>
                                        <a class="dropdown-item text-danger confirm-link" href="post.php?remove_managed_instance=<?= $instance_id ?>&csrf_token=<?= $csrf ?>"
                                            data-confirm-title="Remove <?= $name ?>?" data-confirm-text="It stops being managed at its next check-in - what this manager set comes off it, and its support user is archived. Its data stays where it is." data-confirm-button="Remove">
                                            <i class="fas fa-fw fa-unlink me-2"></i>Remove
                                        </a>
                                    </div>
                                </div>
                            </div>
                        <?php } else { ?>
                            <div class="d-flex flex-wrap gap-2 mt-3">
                                <a class="btn btn-outline-danger confirm-link" href="post.php?delete_managed_instance=<?= $instance_id ?>&csrf_token=<?= $csrf ?>"
                                    data-confirm-title="Delete <?= $name ?>?" data-confirm-text="Its record and history here go for good. The instance itself isn't touched." data-confirm-button="Delete">
                                    <i class="fas fa-fw fa-trash me-2"></i>Delete
                                </a>
                            </div>
                        <?php } ?>
                    </div>
                </div>
            </div>
        </div>

        <?php if (!$removed && ($state === 'pending' || $new_code !== '')) { ?>
            <!-- Connecting it - the code, the once it's shown, and where it goes -->
            <div class="card mb-3 border-primary-subtle" data-managed-connect>
                <div class="card-header py-3">
                    <h3 class="card-title"><i class="fas fa-fw fa-plug me-2"></i>Connect it</h3>
                </div>
                <div class="card-body">
                    <ol class="mb-3 ps-3">
                        <li>On that ITFlow, sign in as an admin and open <strong>Admin &gt; Managed By</strong>. It needs to be on this version or newer.</li>
                        <li>Paste the code there and choose <strong>Connect</strong>. It works once, until <?= escapeHtml(date('M j', strtotime((string) ($row['managed_instance_enroll_expires_at'] ?? 'now')))) ?>.</li>
                    </ol>
                    <?php if ($new_code !== '') { ?>
                        <div class="position-relative">
                            <pre class="itflow-command bg-body-tertiary border rounded p-3 pe-5 mb-2" data-managed-code><code><?= escapeHtml($new_code) ?></code></pre>
                            <button type="button" class="btn btn-sm btn-link position-absolute top-0 end-0 m-1 clipboardjs" title="Copy" data-clipboard-text="<?= escapeHtml($new_code) ?>"><i class="far fa-copy"></i></button>
                        </div>
                        <p class="small text-warning-emphasis mb-0"><i class="fas fa-fw fa-eye-slash me-1"></i>Copy it now - it isn't shown again. If it's lost, make a new one.</p>

                        <?php $setup_command = managedSetupCommand($row, $new_code); ?>
                        <div class="border-top mt-3 pt-3" data-managed-setup-command>
                            <div class="fw-semibold mb-1"><i class="fas fa-fw fa-terminal me-1"></i>Setting up a new install for it?</div>
                            <p class="small text-body-secondary mb-2">Run this in the new install's folder, as the user that owns its files - it sets ITFlow up, connects it with this code and keeps it connected. It asks for the database<?= trim((string) ($row['managed_instance_url'] ?? '')) === '' ? ', the address' : '' ?> and anything else it needs<?= $client_id ? ", and fills in the rest from " . escapeHtml((string) $row['client_name']) : '' ?>.</p>
                            <div class="position-relative">
                                <pre class="itflow-command bg-body-tertiary border rounded p-3 pe-5 mb-2"><code><?= escapeHtml($setup_command) ?></code></pre>
                                <button type="button" class="btn btn-sm btn-link position-absolute top-0 end-0 m-1 clipboardjs" title="Copy" data-clipboard-text="<?= escapeHtml($setup_command) ?>"><i class="far fa-copy"></i></button>
                            </div>
                            <p class="small text-body-secondary mb-0">When it's done, it shows the password made for the first admin<?= $client_id ? '' : ' (or asks for one)' ?> and the cron job to add in the hosting panel. This page says it's waiting for that cron job until it runs. Leave off <code>--manager-required</code> if its admins should be able to disconnect it.</p>
                        </div>
                    <?php } elseif (!empty($row['managed_instance_enroll_expires_at']) && strtotime($row['managed_instance_enroll_expires_at']) > time()) { ?>
                        <p class="small text-body-secondary mb-2">The code was shown when it was made - only a fingerprint of it is kept here. If it's lost, make a new one; the old one stops working.</p>
                        <a class="btn btn-sm btn-outline-primary" href="post.php?managed_instance_new_code=<?= $instance_id ?>&csrf_token=<?= $csrf ?>"><i class="fas fa-fw fa-key me-2"></i>New Connection Code</a>
                    <?php } else { ?>
                        <p class="small text-body-secondary mb-2">There's no code that works - <?= $row['managed_instance_enroll_expires_at'] ? 'the last one ran out' : 'make one to connect it' ?>.</p>
                        <a class="btn btn-sm btn-primary" href="post.php?managed_instance_new_code=<?= $instance_id ?>&csrf_token=<?= $csrf ?>"><i class="fas fa-fw fa-key me-2"></i>Make a Connection Code</a>
                    <?php } ?>
                    <p class="small text-body-secondary mt-3 mb-0">
                        <i class="fas fa-fw fa-info-circle me-1"></i>It checks in with this ITFlow at <strong><?= escapeHtml(managedRequestBaseUrl()) ?></strong>, so that has to be reachable from where it is.
                        Nothing here ever connects to it - it can be behind a firewall.
                    </p>
                </div>
            </div>
        <?php } ?>

        <?php if ($report) { ?>
            <!-- What needs a look, from its last report -->
            <div class="card mb-3">
                <div class="card-header py-3">
                    <h3 class="card-title"><i class="fas fa-fw fa-heartbeat me-2"></i>Health</h3>
                    <div class="card-tools small text-body-secondary pt-1">Reported <?= $when($report['reported_at'] ?? null) ?></div>
                </div>
                <?php if (!$health['issues']) { ?>
                    <div class="card-body text-success-emphasis"><i class="fas fa-fw fa-check-circle me-2"></i>All good - cron is running, it's up to date, backups are recent and nothing has gone wrong today.</div>
                <?php } else { ?>
                    <ul class="list-group list-group-flush" data-managed-issues>
                        <?php foreach ($health['issues'] as [$tone, $text, $key]) { ?>
                            <li class="list-group-item d-flex gap-2 align-items-start">
                                <i class="fas fa-fw fa-<?= $tone === 'info' ? 'info-circle' : 'exclamation-circle' ?> text-<?= $tone ?>-emphasis mt-1"></i>
                                <span class="flex-grow-1"><?= escapeHtml($text) ?></span>
                                <?php if (isset($issue_tickets[$key])) { ?>
                                    <a class="badge rounded-pill bg-secondary-subtle text-secondary-emphasis border border-secondary-subtle fw-normal text-decoration-none mt-1" data-managed-issue-ticket
                                        href="/agent/<?= ticketUrl($issue_tickets[$key]['ticket_id'], $issue_tickets[$key]['client_id']) ?>" title="The ticket this opened"><i class="fas fa-fw fa-ticket-alt me-1"></i><?= escapeHtml($issue_tickets[$key]['reference']) ?></a>
                                <?php } ?>
                            </li>
                        <?php } ?>
                    </ul>
                <?php } ?>
                <?php if (!empty($update['last']['error'])) { ?>
                    <div class="card-footer bg-body small">
                        <div class="text-body-secondary mb-1">The last update stopped with:</div>
                        <pre class="bg-dark text-white p-2 mb-0 text-wrap"><?= escapeHtml($update['last']['error']) ?></pre>
                    </div>
                <?php } ?>
            </div>

            <!-- Who holds its seats - its active agents, then the invites waiting -->
            <div class="card mb-3" data-managed-agents>
                <div class="card-header py-3">
                    <h3 class="card-title"><i class="fas fa-fw fa-users me-2"></i>Agents</h3>
                    <?php if ($agents) { ?>
                        <div class="card-tools small text-body-secondary pt-1"><?= intval($agents['active'] ?? 0) + intval($agents['invites'] ?? 0) ?><?= intval($agents['limit'] ?? 0) > 0 ? ' of ' . intval($agents['limit']) : '' ?> seats taken</div>
                    <?php } ?>
                </div>
                <?php if (!is_array($agents['list'] ?? null)) { ?>
                    <div class="card-body text-body-secondary small">Who they are shows once it runs a version that sends them - until then, only how many.</div>
                <?php } elseif (!$agents['list']) { ?>
                    <div class="card-body text-body-secondary small">No agents yet.</div>
                <?php } else { ?>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0 small">
                            <thead class="table-light">
                            <tr>
                                <th class="ps-3">Agent</th>
                                <th>Role</th>
                                <th class="text-center">MFA</th>
                                <th class="d-none d-md-table-cell">Last signed in</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($agents['list'] as $agent) {
                                if (!is_array($agent)) {
                                    continue;
                                }
                                $invited = !empty($agent['invited']); ?>
                                <tr>
                                    <td class="ps-3">
                                        <div class="fw-semibold"><?= escapeHtml((string) ($agent['name'] ?? '')) ?>
                                            <?php if ($invited) { ?><span class="badge rounded-pill bg-info-subtle text-info-emphasis border border-info-subtle fw-normal ms-1">Invited</span><?php } ?>
                                            <?php if (!empty($agent['service'])) { ?><span class="badge rounded-pill bg-secondary-subtle text-secondary-emphasis border border-secondary-subtle fw-normal ms-1">API account</span><?php } ?>
                                        </div>
                                        <div class="text-body-secondary text-break"><?= escapeHtml((string) ($agent['email'] ?? '')) ?></div>
                                    </td>
                                    <td><?= escapeHtml((string) ($agent['role'] ?? '')) ?></td>
                                    <td class="text-center">
                                        <?php if ($invited) { ?>
                                            <span class="text-body-secondary">-</span>
                                        <?php } elseif (!empty($agent['mfa'])) { ?>
                                            <i class="fas fa-fw fa-lock text-success" title="Signs in with MFA"></i>
                                        <?php } else { ?>
                                            <i class="fas fa-fw fa-unlock text-warning" title="No MFA"></i>
                                        <?php } ?>
                                    </td>
                                    <td class="d-none d-md-table-cell text-nowrap">
                                        <?php if ($invited) { ?>
                                            <span class="text-body-secondary"><?= !empty($agent['expires_at']) ? 'Invite runs out ' . escapeHtml(date('M j', strtotime((string) $agent['expires_at']))) : 'Invite waiting' ?></span>
                                        <?php } else { ?>
                                            <?= $when($agent['last_login'] ?? null) ?>
                                        <?php } ?>
                                    </td>
                                </tr>
                            <?php } ?>
                            </tbody>
                        </table>
                    </div>
                <?php } ?>
            </div>
        <?php } ?>

        <?php
        // Its history, a day at a time, over the period picked (managedInstanceHistory())
        $history_periods = [30 => '30 days', 90 => '90 days', 365 => '1 year'];
        $history_days = intval($_GET['history'] ?? 30);
        $history_days = isset($history_periods[$history_days]) ? $history_days : 30;
        $history = managedInstanceHistory($row, $history_days);
        $history_list = $history['days'];
        $history_now = [];
        foreach (array_reverse($history_list) as $history_day) {
            if ($history_day['agents'] !== null) {
                $history_now = $history_day;
                break;
            }
        }
        $history_column = function ($key) use ($history_list) {
            return array_column($history_list, $key);
        };
        $history_labels = $history_column('label');
        // Over a year the bars and the backup strip go by the week, so each stays readable
        $history_weekly = count($history_list) > 120;
        $history_bars = $history_list;
        if ($history_weekly) {
            $history_bars = [];
            foreach (array_chunk($history_list, 7) as $week) {
                $sum = function ($key) use ($week) {
                    $values = array_filter(array_column($week, $key), function ($v) { return $v !== null; });
                    return $values ? array_sum($values) : null;
                };
                $backups = array_filter(array_column($week, 'backup'), function ($v) { return $v !== null; });
                $history_bars[] = [
                    'label' => $week[0]['label'],
                    'title' => $week[0]['label'] . ' - ' . end($week)['label'],
                    'errors' => $sum('errors'),
                    'down' => $sum('down'),
                    'backup' => !$backups ? null : (in_array('failed', $backups, true) ? 'failed' : (in_array('', $backups, true) ? '' : 'ok')),
                    'made' => count(array_keys($backups, 'ok', true)),
                    'heard' => count($backups),
                ];
            }
        }
        $bar_labels = array_column($history_bars, 'label');
        $bar_period = $history_weekly ? ' that week' : '';
        $history_block = function ($title, $figure, $body) {
            return '<div class="col-md-6"><div class="fw-semibold small">' . escapeHtml($title) . '</div>'
                . '<div class="small text-body-secondary mb-2">' . $figure . '</div>' . $body . '</div>';
        };
        $history_span = function ($seconds) {
            $minutes = max(1, (int) round($seconds / 60));
            if ($minutes < 90) {
                return $minutes . ' minute' . ($minutes === 1 ? '' : 's');
            }
            $hours = round($minutes / 60, 1);
            return $hours < 48 ? rtrim(rtrim(number_format($hours, 1), '0'), '.') . ' hours' : round($hours / 24) . ' days';
        };
        ?>

        <!-- How it has done, day by day -->
        <div class="card mb-3" id="history" data-managed-history>
            <div class="card-header py-3">
                <h3 class="card-title"><i class="fas fa-fw fa-chart-line me-2"></i>History</h3>
                <?php if ($history['since']) { ?>
                    <div class="card-tools">
                        <div class="btn-group btn-group-sm" role="group" aria-label="Period">
                            <?php foreach ($history_periods as $period_days => $period_label) { ?>
                                <a class="btn btn-<?= $period_days === $history_days ? 'secondary' : 'outline-secondary' ?>" href="?id=<?= $instance_id ?>&history=<?= $period_days ?>#history"
                                    <?= $period_days === $history_days ? 'aria-current="true"' : '' ?>><?= $period_label ?></a>
                            <?php } ?>
                        </div>
                    </div>
                <?php } ?>
            </div>
            <?php if (!managedStatsReady()) { ?>
                <div class="card-body text-body-secondary small">Its history needs this install's database update - apply it from Maintenance &gt; Update.</div>
            <?php } elseif (!$history['since']) { ?>
                <div class="card-body text-body-secondary small">Its history starts with its next check-in - a line a day of its agents, clients, storage, uptime, errors and backups, kept for good.</div>
            <?php } else {
                $since_shown = strtotime($history['since']) > strtotime('-' . ($history_days - 1) . ' days'); ?>
                <div class="card-body">
                    <?php if ($since_shown) { ?>
                        <div class="small text-body-secondary mb-3"><i class="fas fa-fw fa-info-circle me-1"></i>Its history starts on <?= date('M j, Y', strtotime($history['since'])) ?>.</div>
                    <?php } ?>
                    <div class="row g-4">
                        <?php
                        // Agents, and seats taken with the invites, against the seat limit
                        $series = [['label' => 'Agents', 'data' => $history_column('agents'), 'tone' => 'accent', 'fill' => true]];
                        if (array_sum(array_map('intval', $history_column('invites'))) > 0) {
                            $series[] = ['label' => 'With invites waiting', 'tone' => 'muted', 'data' => array_map(function ($d) {
                                return $d['agents'] === null ? null : $d['agents'] + $d['invites'];
                            }, $history_list)];
                        }
                        if (max(array_map('intval', $history_column('limit')) ?: [0]) > 0) {
                            $series[] = ['label' => 'Seat limit', 'data' => array_map(function ($d) {
                                return $d['limit'] ? $d['limit'] : null;
                            }, $history_list), 'tone' => 'faint', 'dashed' => true];
                        }
                        $figure = $history_now ? intval($history_now['agents']) . ' agent' . (intval($history_now['agents']) === 1 ? '' : 's')
                            . ($history_now['invites'] ? ', ' . intval($history_now['invites']) . ' invited' : '')
                            . ($history_now['limit'] ? ' - ' . intval($history_now['limit']) . ' seats' : '') : '';
                        echo $history_block('Agents', $figure, reportChart(['type' => 'line', 'labels' => $history_labels, 'series' => $series, 'format' => 'count'], 'sm', 'Agents by day'));

                        // Clients
                        $clients = array_values(array_filter($history_column('clients'), function ($v) { return $v !== null; }));
                        $change = $clients ? end($clients) - $clients[0] : 0;
                        $figure = $clients ? end($clients) . ' client' . (end($clients) === 1 ? '' : 's') . ($change ? ' - ' . ($change > 0 ? '+' : '') . $change . ' in this period' : '') : '';
                        echo $history_block('Clients', $figure, reportChart(['type' => 'line', 'labels' => $history_labels, 'legend' => false,
                            'series' => [['label' => 'Clients', 'data' => $history_column('clients'), 'tone' => 'accent', 'fill' => true]], 'format' => 'count'], 'sm', 'Clients by day'));

                        // Storage - uploads and the database, in MB, or GB once it's 2 GB
                        $unit = $history['unit'];
                        $size = function ($mb) use ($unit) {
                            return $mb === null ? null : round($unit === 'GB' ? $mb / 1024 : $mb, 1);
                        };
                        $figure = $history_now ? $size($history_now['uploads'] + $history_now['db']) . " $unit - " . $size($history_now['uploads']) . " $unit uploads, " . $size($history_now['db']) . " $unit database" : '';
                        echo $history_block('Storage', $figure, reportChart(['type' => 'line', 'labels' => $history_labels, 'format' => 'number', 'series' => [
                            ['label' => "Uploads ($unit)", 'data' => array_map($size, $history_column('uploads')), 'tone' => 'accent', 'fill' => true],
                            ['label' => "Database ($unit)", 'data' => array_map($size, $history_column('db')), 'tone' => 'muted'],
                        ]], 'sm', 'Storage by day'));

                        // Uptime - the minutes each day it wasn't checking in, so the days it went quiet stand out
                        $gaps_html = '';
                        if ($history['gaps']) {
                            $gaps_html .= '<ul class="list-unstyled small mt-2 mb-0" data-managed-gaps>';
                            foreach (array_slice($history['gaps'], 0, 5) as $gap) {
                                $same_day = date('Y-m-d', $gap['from']) === date('Y-m-d', $gap['to']);
                                $gaps_html .= '<li class="d-flex gap-2"><i class="fas fa-fw fa-exclamation-circle text-danger-emphasis mt-1"></i><span>'
                                    . ($gap['ongoing'] ? 'Since ' . date('M j, g:i A', $gap['from']) . ' - not checking in now'
                                        : date('M j, g:i A', $gap['from']) . ' - ' . date($same_day ? 'g:i A' : 'M j, g:i A', $gap['to']))
                                    . ' <span class="text-body-secondary">' . $history_span($gap['to'] - $gap['from']) . '</span></span></li>';
                            }
                            if (count($history['gaps']) > 5) {
                                $gaps_html .= '<li class="text-body-secondary ps-4">and ' . (count($history['gaps']) - 5) . ' more</li>';
                            }
                            $gaps_html .= '</ul>';
                        }
                        $quiet = count($history['gaps']);
                        $figure = $history['uptime'] !== null ? rtrim(rtrim(number_format($history['uptime'], 2), '0'), '.') . '% - '
                            . ($quiet ? 'went quiet ' . ($quiet === 1 ? 'once' : "$quiet times") : 'never went quiet') : '';
                        echo $history_block('Uptime', $figure, $quiet ? reportChart(['type' => 'bar', 'labels' => $bar_labels, 'format' => 'count', 'legend' => false,
                            'series' => [['label' => "Minutes not checking in$bar_period", 'data' => array_column($history_bars, 'down'), 'tone' => 'danger']]], 'sm', 'Minutes not checking in') . $gaps_html
                            : '<div class="small text-success-emphasis"><i class="fas fa-fw fa-check-circle me-1"></i>Checking in throughout - no gap of more than ' . MANAGED_OFFLINE_MINUTES . ' minutes.</div>');

                        // Errors - each day's, and the categories they came under over the period
                        $categories_html = '';
                        if ($history['categories']) {
                            $named = [];
                            foreach (array_slice($history['categories'], 0, 5, true) as $category => $logged) {
                                $named[] = escapeHtml($category !== '' ? $category : 'No category') . ' <span class="text-body-secondary">' . intval($logged) . '</span>';
                            }
                            $more = count($history['categories']) - count($named);
                            $categories_html = '<div class="small mt-2">' . implode(' &middot; ', $named) . ($more > 0 ? " <span class=\"text-body-secondary\">and $more more</span>" : '') . '</div>';
                        }
                        $figure = $history['errors'] . ' error' . ($history['errors'] === 1 ? '' : 's') . ' in this period';
                        echo $history_block('Errors', $figure, reportChart(['type' => 'bar', 'labels' => $bar_labels, 'format' => 'count', 'legend' => false,
                            'series' => [['label' => "Errors$bar_period", 'data' => array_column($history_bars, 'errors'), 'tone' => 'accent']]], 'sm', 'Errors') . $categories_html);

                        // Backups - a strip of the days (or weeks): made, failed, none, or nothing heard
                        $backups = $history['backups'];
                        $strip = '<div class="itflow-history-strip' . (count($history_bars) > 60 ? ' is-dense' : '') . '" role="img" aria-label="Backups: made on '
                            . $backups['ok'] . ' of ' . $backups['days'] . ' days' . ($backups['failed'] ? ', failed on ' . $backups['failed'] : '') . '">';
                        $words = ['ok' => ['bg-success', 'backed up'], 'failed' => ['bg-danger', 'a backup failed'], '' => ['bg-secondary bg-opacity-25', 'no backup']];
                        foreach ($history_bars as $d) {
                            [$class, $what] = $d['backup'] === null ? ['is-empty', 'nothing heard'] : $words[$d['backup']] ?? $words[''];
                            $title = $history_weekly ? $d['title'] . ': ' . ($d['heard'] ? 'backed up on ' . $d['made'] . ' of ' . $d['heard'] . ' day' . ($d['heard'] === 1 ? '' : 's') . ($d['backup'] === 'failed' ? ', and one failed' : '') : $what)
                                : $d['label'] . ': ' . $what;
                            $strip .= '<span class="' . $class . '" title="' . escapeHtml($title) . '"></span>';
                        }
                        $strip .= '</div><div class="d-flex flex-wrap gap-3 small text-body-secondary mt-2">'
                            . '<span><span class="itflow-history-key bg-success"></span>Backed up</span>'
                            . '<span><span class="itflow-history-key bg-danger"></span>Failed</span>'
                            . '<span><span class="itflow-history-key bg-secondary bg-opacity-25"></span>None</span></div>';
                        $figure = 'Backed up on ' . $backups['ok'] . ' of ' . $backups['days'] . ' day' . ($backups['days'] === 1 ? '' : 's') . ($backups['failed'] ? ' - ' . $backups['failed'] . ' failed' : '');
                        echo $history_block('Backups', $figure, $strip);
                        ?>
                    </div>
                </div>
            <?php } ?>
        </div>

        <!-- What it has been asked, and how that went -->
        <div class="card mb-3">
            <div class="card-header py-3">
                <h3 class="card-title"><i class="fas fa-fw fa-history me-2"></i>Requests</h3>
            </div>
            <?php if (!$commands) { ?>
                <div class="card-body text-body-secondary small">Nothing asked of it yet. Updates you start here, and automatic ones, show here with how they went.</div>
            <?php } else { ?>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0 small" data-managed-commands>
                        <thead class="table-light">
                        <tr>
                            <th class="ps-3">Request</th>
                            <th>Status</th>
                            <th class="d-none d-md-table-cell">Asked</th>
                            <th class="d-none d-md-table-cell">Result</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($commands as $command) { ?>
                            <tr data-managed-command="<?= escapeHtml($command['managed_command']) ?>" data-managed-command-status="<?= escapeHtml($command['managed_command_status']) ?>">
                                <td class="ps-3">
                                    <div class="fw-semibold"><?= escapeHtml(managedCommandLabel($command['managed_command'])) ?></div>
                                    <div class="text-body-secondary"><?= $command['managed_command_source'] === 'auto' ? '<i class="fas fa-fw fa-robot me-1"></i>Automatic' : escapeHtml((string) $command['managed_command_requested_by']) ?></div>
                                    <div class="d-md-none text-body-secondary"><?= escapeHtml((string) $command['managed_command_result']) ?></div>
                                </td>
                                <td><?= $status_pill($command['managed_command_status']) ?></td>
                                <td class="d-none d-md-table-cell text-nowrap"><?= $when($command['managed_command_created_at']) ?></td>
                                <td class="d-none d-md-table-cell text-break"><?= escapeHtml((string) $command['managed_command_result']) ?></td>
                            </tr>
                        <?php } ?>
                        </tbody>
                    </table>
                </div>
            <?php } ?>
        </div>

    </div>

    <div class="col-lg-4">

        <!-- What this manager sets for it -->
        <div class="card mb-3">
            <div class="card-header py-3">
                <h3 class="card-title"><i class="fas fa-fw fa-sliders-h me-2"></i>Set from here</h3>
                <?php if (!$removed) { ?>
                    <div class="card-tools"><button type="button" class="btn btn-sm btn-light ajax-modal" data-modal-url="modals/managed_instance/managed_instance_form.php?id=<?= $instance_id ?>"><i class="fas fa-fw fa-edit"></i><span class="visually-hidden">Edit</span></button></div>
                <?php } ?>
            </div>
            <ul class="list-group list-group-flush small" data-managed-policy>
                <?= $fact('Mode', managedModePill($mode) . ($mode !== 'active' && $row['managed_instance_mode_message'] ? '<div class="text-body-secondary mt-1">' . escapeHtml($row['managed_instance_mode_message']) . '</div>' : '')) ?>
                <?= $fact('Seat limit', $seat_limit > 0 ? '<span class="fw-semibold">' . $seat_limit . ' agent' . ($seat_limit === 1 ? '' : 's') . '</span>' : '<span class="text-body-secondary">None - its own config decides</span>') ?>
                <?php
                $key = trim((string) $row['managed_instance_white_label_key']);
                $key_info = $key !== '' ? validateWhitelabelKey($key) : false;
                ?>
                <?= $fact('White label', $key === '' ? '<span class="text-body-secondary">Not set from here</span>'
                    : ($key_info ? '<span class="fw-semibold">On</span><div class="text-body-secondary">until ' . escapeHtml(date('M j, Y', strtotime((string) $key_info['expires']))) . '</div>' : '<span class="text-warning-emphasis">The key has run out</span>')) ?>
                <?= $fact('Updates', $row['managed_instance_update_policy'] === 'auto'
                    ? '<span class="fw-semibold">Automatic</span><div class="text-body-secondary">' . (intval($row['managed_instance_update_delay_days']) > 0 ? intval($row['managed_instance_update_delay_days']) . ' day' . (intval($row['managed_instance_update_delay_days']) === 1 ? '' : 's') . ' after one comes out' : 'As soon as one comes out') . ' - security fixes straight away</div>'
                    : '<span class="fw-semibold">By hand</span><div class="text-body-secondary">From here - its admins can\'t</div>') ?>
            </ul>
        </div>

        <!-- What it says about itself -->
        <div class="card mb-3">
            <div class="card-header py-3">
                <h3 class="card-title"><i class="fas fa-fw fa-server me-2"></i>The instance</h3>
            </div>
            <?php if (!$report) { ?>
                <div class="card-body small text-body-secondary">Its details show once it has checked in.</div>
            <?php } else { ?>
                <ul class="list-group list-group-flush small" data-managed-facts>
                    <?= $fact('Version', '<span class="fw-semibold">' . escapeHtml((string) ($app['version'] ?? '')) . '</span>'
                        . (!empty($update['available']) ? ' <span class="text-' . (!empty($update['security']) ? 'danger' : 'primary') . '-emphasis">&rarr; ' . escapeHtml(!empty($update['release']) ? $update['release'] : intval($update['commits'] ?? 0) . ' new') . '</span>' : '')
                        . (!empty($app['branch']) ? '<div class="text-body-secondary">' . escapeHtml($app['branch']) . (!empty($app['commit']) ? ' · <code>' . escapeHtml($app['commit']) . '</code>' : '') . '</div>' : '')) ?>
                    <?= $fact('Database', escapeHtml((string) ($app['db_version'] ?? '')) . (!empty($app['db_latest']) && version_compare((string) $app['db_latest'], (string) ($app['db_version'] ?? ''), '>') ? ' <span class="text-warning-emphasis">&rarr; ' . escapeHtml($app['db_latest']) . '</span>' : '')) ?>
                    <?= $fact('Updates checked', $when($update['checked_at'] ?? null)) ?>
                    <?= $fact('Agents', $agents ? '<span class="fw-semibold">' . (intval($agents['active'] ?? 0) + intval($agents['invites'] ?? 0)) . '</span>' . (intval($agents['limit'] ?? 0) > 0 ? ' of ' . intval($agents['limit']) : '')
                        . (intval($agents['invites'] ?? 0) > 0 ? '<div class="text-body-secondary">' . intval($agents['invites']) . ' invited</div>' : '') : '<span class="text-body-secondary">-</span>') ?>
                    <?= $fact('Clients', intval($report['clients'] ?? 0)) ?>
                    <?= $fact('Last backup', $when($backup['last_ok_at'] ?? null, 'None')) ?>
                    <?= $fact('Cron', empty($cron['enabled']) ? '<span class="text-danger-emphasis">Switched off</span>' : 'Last ran ' . $when($cron['last_dispatch_at'] ?? null)) ?>
                    <?= $fact('Errors today', $errors_html) ?>
                    <?php if (!empty($disk['measured_at'])) { ?>
                        <?= $fact('Storage', $size($disk['uploads_mb'] ?? 0) . ' uploads<div class="text-body-secondary">' . $size($disk['db_mb'] ?? 0) . ' database</div>') ?>
                    <?php } ?>
                    <?= $fact('PHP', escapeHtml((string) ($app['php'] ?? ''))) ?>
                    <?= $fact('Time zone', escapeHtml((string) ($report['timezone'] ?? ''))) ?>
                    <?= $fact('Checks in from', '<code>' . escapeHtml((string) $row['managed_instance_last_ip']) . '</code>') ?>
                    <?= $fact('Connected', $when($row['managed_instance_enrolled_at'])) ?>
                </ul>
            <?php } ?>
        </div>

        <?php if (trim((string) $row['managed_instance_notes']) !== '') { ?>
            <div class="card mb-3">
                <div class="card-header py-3">
                    <h3 class="card-title"><i class="fas fa-fw fa-sticky-note me-2"></i>Notes</h3>
                </div>
                <div class="card-body small" style="white-space: pre-wrap"><?= escapeHtml($row['managed_instance_notes']) ?></div>
            </div>
        <?php } ?>

    </div>

</div>

<?php if ($waiting && !$removed) { ?>
    <script src="<?= staticFileUrl('/js/auto_reload.js') ?>"></script>
<?php } ?>

<?php
require_once "../includes/footer.php";
// The History card's charts - drawn by the reports' chart code, after Chart.js in the footer
if (!empty($history['since'])) { ?>
    <script src="<?= staticFileUrl('/js/reports.js') ?>"></script>
<?php }
