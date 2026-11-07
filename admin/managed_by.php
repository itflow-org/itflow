<?php

/*
 * Admin > Managed By - this ITFlow's side of managed mode (functions/managed.php). Not managed:
 * what being managed means, and the box a manager's connection code goes in. Managed: who by,
 * how its check-ins are going, what the manager sets here - and so what's locked - and exactly
 * what this ITFlow tells it. Disconnecting is here too, unless config.php keeps it managed
 * ($config_manager_required). The handlers are admin/post/managed_by.php.
 *
 * Off unless config.php turns it on ($config_enable_managed_by, managedEnabled()) - then the
 * page says how, and whether this ITFlow is still connected to a manager from before.
 */

require_once "includes/inc_all_admin.php";

if (!managedEnabled()) {
    $managed_off_connected = settingsColumnExists($mysqli, 'config_managed') && managedConnected();
    $managed_off_from = trim((string) (managedConfig()['manager_name'] ?? '')); ?>
    <div class="alert alert-secondary"><i class="fas fa-fw fa-toggle-off me-2"></i>Being managed by another ITFlow is off. To turn it on, add this line to the end of config.php: <code>$config_enable_managed_by = true;</code>
        <?php if ($managed_off_connected) { ?>
            <div class="mt-2">This ITFlow is still connected to <strong><?= escapeHtml($managed_off_from !== '' ? $managed_off_from : 'its manager') ?></strong>, but doesn't check in while this is off, and nothing it set is locked here. Turn it back on to carry on, or to disconnect.</div>
        <?php } ?>
    </div>
    <?php
    require_once "../includes/footer.php";
    exit;
}

if (!settingsColumnExists($mysqli, 'config_managed')) { ?>
    <div class="alert alert-warning"><i class="fas fa-fw fa-database me-2"></i>Being managed by another ITFlow needs this install's database update - apply it from <a href="update.php" class="alert-link">Maintenance &gt; Update</a> first.</div>
    <?php
    require_once "../includes/footer.php";
    exit;
}

$managed = managedIsManaged();
$config = managedConfig();
$state = managedState();
$locks = managedLocks();
$manager = managedManagerName();
$mode = $state['mode'];

// The check-in is a cron job - without cron there is no managing
$cron_last_dispatch_at = mysqli_fetch_row(mysqli_query($mysqli, "SELECT config_cron_last_dispatch_at FROM settings WHERE company_id = 1"))[0] ?? null;
$cron_is_running = !empty($config_enable_cron) && !empty($cron_last_dispatch_at) && strtotime($cron_last_dispatch_at) > strtotime('-5 minutes');

$last_support = $managed ? mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT log_created_at, log_description FROM logs
    WHERE log_type = 'Login' AND log_action = 'Support' ORDER BY log_id DESC LIMIT 1")) : null;

$csrf = $_SESSION['csrf_token'];

$fact = function (string $label, string $value_html) {
    return '<li class="list-group-item d-flex justify-content-between gap-3"><span class="text-body-secondary text-nowrap">' . $label . '</span><span class="text-end min-w-0">' . $value_html . '</span></li>';
};
$lock = '<i class="fas fa-fw fa-lock text-body-secondary ms-1" title="Set by ' . escapeHtml($manager) . '"></i>';

?>

<?php if (!$managed) { ?>

    <div class="row g-3">
        <div class="col-lg-8">

            <div class="card mb-3" data-managed-by="none">
                <div class="card-body p-4">
                    <div class="d-flex align-items-start gap-3">
                        <span class="itflow-asset-icon itflow-asset-icon-lg rounded-circle bg-secondary-subtle text-secondary-emphasis" aria-hidden="true"><i class="fas fa-handshake"></i></span>
                        <div class="flex-grow-1 min-w-0">
                            <h2 class="h4 mb-1">Not managed</h2>
                            <p class="text-body-secondary mb-0">
                                Another ITFlow can manage this one - usually the MSP or provider that looks after it. To connect, paste the code
                                from its <strong>Admin &gt; Managed Instances</strong> page.
                            </p>
                        </div>
                    </div>

                    <?php if (!$cron_is_running) { ?>
                        <div class="alert alert-warning small mt-3 mb-0"><i class="fas fa-fw fa-exclamation-circle me-1"></i>Cron isn't running here, and this ITFlow checks in with its manager from cron. Connect if you like, but set up <a href="cron.php" class="alert-link">cron</a> too, or the manager sees it as offline.</div>
                    <?php } ?>

                    <form action="post.php" method="post" autocomplete="off" class="mt-4" data-managed-connect>
                        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                        <div class="mb-3">
                            <label class="form-label">Connection code <strong class="text-danger">*</strong></label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-fw fa-key"></i></span>
                                <textarea class="form-control font-monospace small" name="code" rows="3" placeholder="ITFLOW-MANAGE:..." required autofocus spellcheck="false"></textarea>
                            </div>
                            <div class="form-text">It works once, for a week. If it has run out, ask for a new one.</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">This ITFlow's address <strong class="text-danger">*</strong></label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-fw fa-globe"></i></span>
                                <input type="url" class="form-control" name="url" value="<?= escapeHtml(managedRequestBaseUrl()) ?>" maxlength="255" required>
                            </div>
                            <div class="form-text">As people reach it in a browser - the manager's support sign-ins come here. Nothing else connects to it: it does the calling.</div>
                        </div>
                        <button type="submit" name="managed_connect" class="btn btn-primary confirm-link"
                            data-confirm-title="Let its manager manage this ITFlow?" data-confirm-text="It will see how this ITFlow is doing, update it, set its seat limit and white label, sign in to it as support, and can make it read-only or suspend it." data-confirm-button="Connect" data-confirm-icon="question">
                            <i class="fas fa-fw fa-plug me-2"></i>Connect
                        </button>
                    </form>
                </div>
            </div>

        </div>

        <div class="col-lg-4">
            <div class="card mb-3">
                <div class="card-header py-3">
                    <h3 class="card-title"><i class="fas fa-fw fa-info-circle me-2"></i>What a manager can do</h3>
                </div>
                <ul class="list-group list-group-flush small">
                    <li class="list-group-item d-flex gap-2"><i class="fas fa-fw fa-heartbeat text-body-secondary mt-1"></i><span>See how it's doing - versions, updates, cron, backups, errors and seats. No client data leaves here.</span></li>
                    <li class="list-group-item d-flex gap-2"><i class="fas fa-fw fa-download text-body-secondary mt-1"></i><span>Update it, by hand or automatically - and then updates come only from there.</span></li>
                    <li class="list-group-item d-flex gap-2"><i class="fas fa-fw fa-users text-body-secondary mt-1"></i><span>Set how many agents it can have, and its white-label key.</span></li>
                    <li class="list-group-item d-flex gap-2"><i class="fas fa-fw fa-headset text-body-secondary mt-1"></i><span>Sign in as support, for two hours at a time - each time in the audit log, and you're notified.</span></li>
                    <li class="list-group-item d-flex gap-2"><i class="fas fa-fw fa-lock text-body-secondary mt-1"></i><span>Make it read-only, or suspend it.</span></li>
                    <li class="list-group-item d-flex gap-2"><i class="fas fa-fw fa-user-shield text-body-secondary mt-1"></i><span>Everything else stays yours, and you can disconnect it.</span></li>
                </ul>
            </div>
        </div>
    </div>

<?php } else {

    $last_checkin = $config['last_checkin_at'] ?? null;
    $last_error = (string) ($config['last_error'] ?? '');
    $quiet = !$last_checkin || strtotime($last_checkin) < strtotime('-' . MANAGED_OFFLINE_MINUTES . ' minutes');
    $tone = $last_error !== '' || $quiet ? 'warning' : ($mode === 'suspended' ? 'danger' : ($mode === 'read_only' ? 'warning' : 'success'));
    ?>

    <div class="row g-3">
        <div class="col-lg-8">

            <div class="card mb-3" data-managed-by="managed">
                <div class="card-body p-4">
                    <div class="d-flex align-items-start gap-3">
                        <span class="itflow-asset-icon itflow-asset-icon-lg rounded-circle bg-<?= $tone ?>-subtle text-<?= $tone ?>-emphasis" aria-hidden="true"><i class="fas fa-handshake"></i></span>
                        <div class="flex-grow-1 min-w-0">
                            <h2 class="h4 mb-1 text-break">Managed by <?= escapeHtml($manager) ?>
                                <?php if ($mode !== 'active') { ?><span class="fs-6 align-middle"><?= managedModePill($mode) ?></span><?php } ?>
                            </h2>
                            <div class="small text-body-secondary mb-2 d-flex flex-wrap column-gap-3">
                                <span class="text-break"><i class="fas fa-fw fa-globe me-1"></i><?= escapeHtml(preg_replace('#^https?://#', '', (string) $config['manager_url'])) ?></span>
                                <?php if ($state['contact'] !== '') { ?><span><i class="fas fa-fw fa-headset me-1"></i><?= escapeHtml($state['contact']) ?></span><?php } ?>
                            </div>

                            <?php if ($last_error !== '') { ?>
                                <p class="mb-0 text-warning-emphasis" data-managed-checkin="failed"><i class="fas fa-fw fa-exclamation-circle me-1"></i>
                                    The last check-in didn't get through<?= !empty($config['last_error_at']) ? ', ' . escapeHtml(timeAgo($config['last_error_at'])) : '' ?>: <?= escapeHtml($last_error) ?>
                                </p>
                                <?php if ($last_checkin) { ?><p class="mb-0 small text-body-secondary mt-1">The last one that did was <?= escapeHtml(timeAgo($last_checkin)) ?>.</p><?php } ?>
                            <?php } elseif ($last_checkin) { ?>
                                <p class="mb-0 text-body-secondary" data-managed-checkin="ok">Checked in <?= escapeHtml(timeAgo($last_checkin)) ?>. It checks in every minute from cron.</p>
                            <?php } else { ?>
                                <p class="mb-0 text-body-secondary">Connected - it checks in every minute from cron.</p>
                            <?php } ?>
                            <?php if (!$cron_is_running) { ?>
                                <p class="mb-0 mt-2 text-danger-emphasis"><i class="fas fa-fw fa-exclamation-circle me-1"></i>Cron isn't running, so this ITFlow isn't checking in - <?= escapeHtml($manager) ?> sees it as offline. See <a href="cron.php">Maintenance &gt; Cron</a>.</p>
                            <?php } ?>

                            <div class="d-flex flex-wrap gap-2 mt-3">
                                <form action="post.php" method="post" class="d-inline">
                                    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                                    <button type="submit" name="managed_checkin_now" class="btn btn-light"><i class="fas fa-fw fa-sync-alt me-2"></i>Check in now</button>
                                </form>
                                <?php if (!managedRequired()) { ?>
                                    <form action="post.php" method="post" class="d-inline">
                                        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                                        <button type="submit" name="managed_disconnect" class="btn btn-outline-danger confirm-link"
                                            data-confirm-title="Disconnect from <?= escapeHtml($manager) ?>?" data-confirm-text="It stops managing this ITFlow: no more updates or support sign-ins from there, and what it set comes off - its white-label key and seat limit. Connecting again needs a new code." data-confirm-button="Disconnect">
                                            <i class="fas fa-fw fa-unlink me-2"></i>Disconnect
                                        </button>
                                    </form>
                                <?php } ?>
                            </div>
                            <?php if (managedRequired()) { ?>
                                <p class="small text-body-secondary mt-2 mb-0"><i class="fas fa-fw fa-lock me-1"></i>This install's config.php keeps it managed (<code>$config_manager_required</code>), so it can't be disconnected here.</p>
                            <?php } ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- What it tells the manager - all of it -->
            <div class="card mb-3">
                <div class="card-header py-3">
                    <h3 class="card-title"><i class="fas fa-fw fa-paper-plane me-2"></i>What it tells <?= escapeHtml($manager) ?></h3>
                </div>
                <div class="card-body small">
                    <p class="mb-2">Each check-in sends how this ITFlow is doing, and nothing about your clients beyond how many there are:</p>
                    <ul class="mb-2">
                        <li>its address, company name and time zone</li>
                        <li>its version, database version, PHP version and branch, and whether an update is waiting</li>
                        <li>how the last update went, whether cron is running, and when the last backup was</li>
                        <li>how many errors were logged today, how many agents and clients it has, and its storage</li>
                    </ul>
                    <p class="mb-0 text-body-secondary">No tickets, documents, credentials, invoices or contacts.</p>
                </div>
            </div>

        </div>

        <div class="col-lg-4">

            <!-- What the manager sets here, and so what's locked -->
            <div class="card mb-3">
                <div class="card-header py-3">
                    <h3 class="card-title"><i class="fas fa-fw fa-sliders-h me-2"></i>Set by <?= escapeHtml($manager) ?></h3>
                </div>
                <ul class="list-group list-group-flush small" data-managed-locks>
                    <?= $fact('Access', managedModePill($mode) . ($state['message'] !== '' && $mode !== 'active' ? '<div class="text-body-secondary mt-1">' . escapeHtml($state['message']) . '</div>' : '')) ?>
                    <?= $fact('Seat limit', $locks['seat_limit']
                        ? '<span class="fw-semibold">' . $state['seat_limit'] . ' agent' . ($state['seat_limit'] === 1 ? '' : 's') . '</span>' . $lock . '<div class="text-body-secondary">' . getAgentSeatsTaken() . ' taken</div>'
                        : '<span class="text-body-secondary">Not set from there</span>') ?>
                    <?= $fact('White label', $locks['white_label'] ? '<span class="fw-semibold">Set from there</span>' . $lock : '<span class="text-body-secondary">Yours to set</span> <a href="settings_module.php" class="ms-1">Modules</a>') ?>
                    <?= $fact('Updates', '<span class="fw-semibold">' . ($state['update_policy'] === 'auto' ? 'Automatic' : 'From there') . '</span>' . $lock
                        . ($state['update_policy'] === 'auto' ? '<div class="text-body-secondary">' . ($state['update_delay_days'] > 0 ? $state['update_delay_days'] . ' day' . ($state['update_delay_days'] === 1 ? '' : 's') . ' after a release' : 'As soon as a release is out') . ', security fixes straight away</div>' : '')) ?>
                    <?= $fact('Support sign-in', $last_support
                        ? 'Last <span title="' . escapeHtml($last_support['log_created_at']) . '">' . escapeHtml(timeAgo($last_support['log_created_at'])) . '</span><div class="text-body-secondary"><a href="audit_logs.php">Audit log</a></div>'
                        : '<span class="text-body-secondary">Not yet</span>') ?>
                </ul>
                <div class="card-footer bg-body small text-body-secondary">Everything else is yours, as it was.</div>
            </div>

        </div>
    </div>

<?php } ?>

<?php
require_once "../includes/footer.php";
