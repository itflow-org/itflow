<?php

/*
 * Get started - Cron: the crontab line, whether cron has been heard from
 * (checked again every 10 seconds while it hasn't - js/getting_started.js),
 * and its switch, which ships off (getting_started_cron_on).
 */

defined('FROM_GETTING_STARTED') || die("Direct file access is not allowed");

$gs_cron = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT config_enable_cron, config_cron_last_dispatch_at FROM settings WHERE company_id = 1")) ?: [];
$gs_cron_heard = $gs_cron['config_cron_last_dispatch_at'] ?? null;
$gs_cron_running = $gs_cron_heard && time() - strtotime($gs_cron_heard) < 300;
$gs_cron_on = intval($gs_cron['config_enable_cron'] ?? 0) === 1;
$gs_cron_command = gettingStartedCronCommand();

?>
<ol class="itflow-gs-tasks list-unstyled mb-0">
    <li class="<?= $gs_cron_running ? 'is-done' : '' ?>" data-gs-cron-heard="<?= $gs_cron_running ? 'yes' : 'no' ?>">
        <span class="itflow-gs-task-mark"><i class="fas <?= $gs_cron_running ? 'fa-check' : 'fa-terminal' ?>"></i></span>
        <div class="min-w-0 flex-grow-1">
            <div class="fw-semibold">Add one line to the crontab</div>
            <div class="small text-body-secondary mb-2">For the user that owns the ITFlow files - <code>crontab -e</code> as them. The install script adds it for you.</div>
            <div class="input-group input-group-sm">
                <input type="text" class="form-control font-monospace" value="<?= escapeHtml($gs_cron_command) ?>" aria-label="Crontab line" readonly>
                <button type="button" class="btn btn-outline-secondary clipboardjs" data-clipboard-text="<?= escapeHtml($gs_cron_command) ?>" title="Copy"><i class="far fa-copy"></i></button>
            </div>
            <div class="small mt-2" data-gs-cron-status>
                <?php if ($gs_cron_running) { ?>
                    <span class="text-success"><i class="fas fa-check-circle me-1"></i>Cron ran <?= escapeHtml(timeAgo($gs_cron_heard)) ?>.</span>
                <?php } else { ?>
                    <span class="text-body-secondary"><span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span><?= $gs_cron_heard ? 'Cron last ran ' . escapeHtml(timeAgo($gs_cron_heard)) . ' - waiting to hear from it again' : 'Waiting to hear from it - this page checks every few seconds' ?></span>
                <?php } ?>
            </div>
        </div>
    </li>
    <li class="<?= $gs_cron_on ? 'is-done' : '' ?>" data-gs-cron-switch="<?= $gs_cron_on ? 'on' : 'off' ?>">
        <span class="itflow-gs-task-mark"><i class="fas <?= $gs_cron_on ? 'fa-check' : 'fa-toggle-off' ?>"></i></span>
        <div class="min-w-0 flex-grow-1">
            <div class="fw-semibold">Turn cron on</div>
            <div class="small text-body-secondary mb-2">It ships off, so a copy of ITFlow never emails clients or charges cards by surprise. Every job stops itself while it is off.</div>
            <?php if ($gs_cron_on) { ?>
                <div class="small text-success"><i class="fas fa-check-circle me-1"></i>On. Each job's schedule is in Maintenance &gt; Cron.</div>
            <?php } else { ?>
                <form action="post.php" method="post" class="m-0">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <button type="submit" name="getting_started_cron_on" value="1" class="btn btn-primary"><i class="fas fa-toggle-on me-2"></i>Turn cron on</button>
                </form>
            <?php } ?>
        </div>
    </li>
</ol>
<?php if (!$gs_cron_running) { ?>
    <div class="small text-body-secondary mt-3" data-gs-cron-poll="<?= gettingStartedUrl() ?>?cron_status=1">Nothing is emailed or backed up until cron runs - the other steps can be done meanwhile.</div>
<?php } ?>
