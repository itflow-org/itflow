<?php

/*
 * Admin > Get started - what to set up once ITFlow is installed, a step at a
 * time (functions/getting_started.php): cron, email, backups, billing,
 * support, your team, starter content and a first client. ?step= is the step,
 * or done for the summary; with none, the first step not done or skipped.
 * Each step's page is in getting_started/; what they save is in
 * post/getting_started.php - and the first client goes to the same New Client
 * handler as the Clients page (agent/post/client.php), the demo data to the
 * one on Maintenance > Starter Content.
 *
 * A page of its own, like setup - no menu, the steps along the top - in the
 * agent's light or dark. ?cron_status=1 answers whether cron has been heard
 * from, for the Cron step to check while it waits (js/getting_started.js).
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/functions.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/check_login.php';
enforceAdminPermission();

if (!gettingStartedReady()) {
    flashAlert('Get started needs the database update - run it from Maintenance &gt; Update first', 'error');
    redirect('/admin/update.php');
}

$gs_steps = gettingStartedSteps();

if (isset($_GET['cron_status'])) {
    $heard = settingsCurrent(['config_cron_last_dispatch_at'])['config_cron_last_dispatch_at'];
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode(['running' => $heard && time() - strtotime($heard) < 300, 'done' => !empty($gs_steps['cron']['done']), 'status' => (string) ($gs_steps['cron']['status'] ?? '')]);
    exit;
}

gettingStartedOpened();
$gs_progress = gettingStartedProgress($gs_steps);
$gs_state = gettingStartedState();
$gs_step = (string) ($_GET['step'] ?? '');
if ($gs_step !== 'done' && !isset($gs_steps[$gs_step])) {
    $gs_step = $gs_progress['next'] !== '' ? $gs_progress['next'] : 'done';
}
$gs_keys = array_keys($gs_steps);
$gs_at = array_search($gs_step, $gs_keys, true);
$gs_prev = $gs_step === 'done' ? (end($gs_keys) ?: '') : ($gs_at > 0 ? $gs_keys[$gs_at - 1] : '');
$gs_next = $gs_step === 'done' ? '' : gettingStartedAfter($gs_step, $gs_steps);
$gs_info = $gs_steps[$gs_step] ?? null;

// What was typed, when a save came back with something to put right (settingsRefuse())
$settings_kept = [];
if (($_SESSION['settings_kept']['page'] ?? '') === "getting_started_$gs_step" && time() - ($_SESSION['settings_kept']['at'] ?? 0) < 300) {
    $settings_kept = $_SESSION['settings_kept'];
}
unset($_SESSION['settings_kept']);

// The message a save left for this page
$gs_alert = '';
$gs_alert_type = 'info';
if (!empty($_SESSION['alert_message'])) {
    $gs_alert = (string) $_SESSION['alert_message'];
    $gs_alert_type = alertStyleClass($_SESSION['alert_type'] ?? 'success');
    unset($_SESSION['alert_message'], $_SESSION['alert_type']);
}

$gs_company = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT company_name, company_logo FROM companies WHERE company_id = 1")) ?: [];
$gs_title = $gs_step === 'done' ? 'Where it stands' : $gs_info['title'];
define('FROM_GETTING_STARTED', true);

?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="<?= $user_config_theme_dark ? 'dark' : 'light' ?>" data-lte-color-mode="off">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="<?= $user_config_theme_dark ? 'dark' : 'light' ?>">
    <meta name="robots" content="noindex">
    <title><?= escapeHtml($gs_title) ?> - Get started - <?= escapeHtml($gs_company['company_name'] ?? 'ITFlow') ?></title>
    <?php if (file_exists($_SERVER['DOCUMENT_ROOT'] . '/uploads/favicon.ico')) { ?>
        <link rel="icon" type="image/x-icon" href="/uploads/favicon.ico">
    <?php } ?>
    <link rel="stylesheet" href="/libs/fontawesome-free/css/all.min.css">
    <link rel="stylesheet" href="/libs/adminlte/css/adminlte.min.css">
    <link rel="stylesheet" href="/css/itflow_custom.css">
</head>

<body class="itflow-setup itflow-gs bg-body-tertiary">

<div class="itflow-setup-wrap itflow-gs-wrap">

    <header class="itflow-gs-top">
        <a href="<?= gettingStartedUrl() ?>" class="itflow-gs-brand text-reset text-decoration-none">
            <?php if (!empty($gs_company['company_logo']) && file_exists($_SERVER['DOCUMENT_ROOT'] . '/uploads/settings/' . $gs_company['company_logo'])) { ?>
                <img src="/uploads/settings/<?= escapeHtml($gs_company['company_logo']) ?>" alt="" class="itflow-gs-logo">
            <?php } else { ?>
                <span><span class="text-primary fw-bold"><i class="fas fa-paper-plane me-2"></i>IT</span>Flow</span>
            <?php } ?>
            <span class="itflow-setup-brand-sub">Get started</span>
        </a>
        <a href="/agent/dashboard.php" class="btn btn-sm btn-outline-secondary" data-gs-leave>Go to ITFlow<i class="fas fa-arrow-right ms-2"></i></a>
    </header>

    <!-- The steps, and where each stands -->
    <nav class="itflow-setup-steps" aria-label="Get started steps">
        <ol class="list-unstyled d-none d-md-flex">
            <?php foreach ($gs_steps as $key => $step) {
                $state = $key === $gs_step ? 'current' : ($step['done'] ? 'done' : ($step['skipped'] ? 'skipped' : 'todo'));
                $marker = $step['done'] ? '<i class="fas fa-check"></i>' : ($step['skipped'] ? '<i class="fas fa-minus"></i>' : '<i class="fas fa-fw ' . $step['icon'] . '"></i>'); ?>
                <li class="itflow-setup-step is-<?= $state ?><?= $step['done'] ? ' has-done' : '' ?>" data-gs-step="<?= $key ?>" data-gs-state="<?= $step['done'] ? 'done' : ($step['skipped'] ? 'skipped' : 'todo') ?>"<?= $state === 'current' ? ' aria-current="step"' : '' ?>>
                    <a href="<?= gettingStartedUrl($key) ?>" title="<?= escapeHtml($step['title'] . ' - ' . ($step['done'] ? 'done' : ($step['skipped'] ? 'skipped for now' : 'to do'))) ?>">
                        <span class="itflow-setup-step-marker"><?= $marker ?></span><span class="itflow-setup-step-label"><?= escapeHtml($step['label']) ?></span>
                    </a>
                </li>
            <?php } ?>
        </ol>
        <div class="d-md-none">
            <div class="d-flex justify-content-between small mb-1">
                <span class="fw-semibold"><?= escapeHtml($gs_title) ?></span>
                <span class="text-body-secondary"><?= $gs_progress['done'] ?> of <?= $gs_progress['total'] ?> done</span>
            </div>
            <div class="progress itflow-gs-progress" role="progressbar" aria-label="How far Get started has got" aria-valuenow="<?= $gs_progress['done'] ?>" aria-valuemin="0" aria-valuemax="<?= $gs_progress['total'] ?>">
                <div class="progress-bar" style="width: <?= $gs_progress['total'] ? round($gs_progress['done'] * 100 / $gs_progress['total']) : 0 ?>%"></div>
            </div>
        </div>
    </nav>

    <main>
        <?php if ($gs_alert !== '') { ?>
            <div class="alert alert-<?= $gs_alert_type ?> d-flex gap-2 align-items-start" role="alert" data-gs-alert="<?= $gs_alert_type ?>">
                <i class="fas fa-fw mt-1 <?= ['success' => 'fa-check-circle', 'info' => 'fa-info-circle', 'warning' => 'fa-exclamation-triangle', 'danger' => 'fa-times-circle'][$gs_alert_type] ?? 'fa-info-circle' ?>"></i>
                <div class="flex-grow-1"><?= alertMessageHtml($gs_alert) ?></div>
            </div>
        <?php } ?>

        <div class="card itflow-setup-card" data-gs-page="<?= escapeHtml($gs_step) ?>">
            <?php if ($gs_info) { ?>
                <div class="card-body p-4">
                    <div class="d-flex flex-wrap align-items-start gap-2 mb-1">
                        <h1 class="h4 fw-semibold mb-0 me-auto"><i class="fas fa-fw <?= $gs_info['icon'] ?> text-body-secondary me-2"></i><?= escapeHtml($gs_info['title']) ?></h1>
                        <?php if ($gs_info['done']) { ?>
                            <span class="badge rounded-pill text-bg-success" data-gs-done><i class="fas fa-check me-1"></i>Done</span>
                        <?php } elseif ($gs_info['skipped']) { ?>
                            <span class="badge rounded-pill text-bg-secondary" data-gs-skipped>Skipped for now</span>
                        <?php } ?>
                    </div>
                    <p class="text-body-secondary mb-4"><?= escapeHtml($gs_info['about']) ?></p>
                    <?php require __DIR__ . "/getting_started/$gs_step.php"; ?>
                </div>
                <div class="card-footer d-flex flex-wrap align-items-center gap-2 py-3 px-4">
                    <?php if ($gs_prev !== '') { ?>
                        <a href="<?= gettingStartedUrl($gs_prev) ?>" class="btn btn-link text-decoration-none ps-0"><i class="fas fa-arrow-left me-2"></i>Back</a>
                    <?php } ?>
                    <span class="me-auto small text-body-secondary"><?= $gs_progress['done'] ?> of <?= $gs_progress['total'] ?> done</span>
                    <?php if ($gs_info['done'] || $gs_info['skipped']) { ?>
                        <a href="<?= gettingStartedUrl($gs_next) ?>" class="btn btn-outline-primary" data-gs-continue><?= $gs_next === 'done' ? 'Finish' : 'Next: ' . escapeHtml($gs_steps[$gs_next]['title']) ?><i class="fas fa-arrow-right ms-2"></i></a>
                    <?php } else { ?>
                        <form action="post.php" method="post" class="m-0">
                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                            <button type="submit" name="getting_started_skip" value="<?= escapeHtml($gs_step) ?>" class="btn btn-outline-secondary" data-gs-skip><?= $gs_step === 'team' ? "It's just me" : 'Skip for now' ?></button>
                        </form>
                    <?php } ?>
                </div>
            <?php } else { ?>
                <div class="card-body p-4">
                    <?php require __DIR__ . '/getting_started/done.php'; ?>
                </div>
            <?php } ?>
        </div>
    </main>

    <footer class="itflow-setup-footer">
        Get started is in Admin whenever you want it.
        <?php if ($gs_state['on']) { ?>
            <span aria-hidden="true">·</span>
            <form action="post.php" method="post" class="d-inline">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <button type="submit" name="getting_started_dismiss" value="1" class="btn btn-link btn-sm p-0 align-baseline text-reset" data-gs-dismiss>Don't show it on the dashboard</button>
            </form>
        <?php } ?>
    </footer>

</div>

<script src="/js/itflow_select.js"></script>
<script src="/libs/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="/js/app.js"></script>
<script src="/js/settings.js"></script>
<script src="/js/getting_started.js"></script>

</body>

</html>
