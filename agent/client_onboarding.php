<?php

/*
 * Onboard a client - taking a client on a step at a time
 * (functions/client_onboarding.php): the company, locations, people, the
 * agreement, billing, IT basics and a kickoff project. ?client_id= is the
 * client (none for a new one - the company step makes it), ?step= the step,
 * or done for the summary; with none, the first step not done or skipped.
 * Each step's page is in client_onboarding/; what they save is in
 * post/client_onboarding.php, which comes back here.
 *
 * A page of its own, like Get started - no menu, the steps along the top -
 * in the agent's light or dark. Do it later goes to the client's overview,
 * which shows how far it has got and carries on from there.
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/functions.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/check_login.php';

if (!clientOnboardingReady()) {
    flashAlert('Onboarding a client needs the database update - ask an admin to run it from Maintenance &gt; Update', 'error');
    redirect('/agent/clients.php');
}
enforceUserPermission('module_client', 2);

$client_id = intval($_GET['client_id'] ?? 0);
$co_client = null;
if ($client_id) {
    $co_client = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT * FROM clients WHERE client_id = $client_id LIMIT 1"));
    if (!$co_client) {
        flashAlert('That client no longer exists.', 'error');
        redirect('/agent/clients.php');
    }
    enforceClientAccess($client_id);
    if ($co_client['client_archived_at']) {
        flashAlert('An archived client isn\'t onboarded - restore it first.', 'error');
        redirect("/agent/client_overview.php?client_id=$client_id");
    }
    $co_row = clientOnboardingRow($client_id) ?? clientOnboardingStart($client_id);
} else {
    $co_row = null;
}

$co_steps = clientOnboardingSteps($client_id, $co_row);
$co_keys_all = clientOnboardingStepKeys();
$co_progress = clientOnboardingProgress($co_steps);
$co_step = (string) ($_GET['step'] ?? '');
if (!$client_id) {
    $co_step = 'company';
} elseif ($co_step !== 'done' && !isset($co_steps[$co_step])) {
    $co_step = $co_progress['next'] !== '' ? $co_progress['next'] : 'done';
}
$co_keys = array_keys($co_steps);
$co_at = array_search($co_step, $co_keys, true);
$co_prev = $co_step === 'done' ? (end($co_keys) ?: '') : ($co_at > 0 ? $co_keys[$co_at - 1] : '');
$co_next = $co_step === 'done' ? '' : clientOnboardingAfter($co_step, $co_steps);
$co_info = $co_steps[$co_step] ?? null;
$co_total = count($co_keys_all);

// What was typed, when a save came back with something to put right (settingsRefuse())
$settings_kept = [];
if (($_SESSION['settings_kept']['page'] ?? '') === "client_onboarding_$co_step" && time() - ($_SESSION['settings_kept']['at'] ?? 0) < 300) {
    $settings_kept = $_SESSION['settings_kept'];
}
unset($_SESSION['settings_kept']);

// The message a save left for this page
$co_alert = '';
$co_alert_type = 'info';
if (!empty($_SESSION['alert_message'])) {
    $co_alert = (string) $_SESSION['alert_message'];
    $co_alert_type = alertStyleClass($_SESSION['alert_type'] ?? 'success');
    unset($_SESSION['alert_message'], $_SESSION['alert_type']);
}

$co_company = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT company_name, company_logo FROM companies WHERE company_id = 1")) ?: [];
$co_name = $co_client ? (string) $co_client['client_name'] : '';
$co_title = $co_step === 'done' ? 'Where it stands' : $co_info['title'];
$co_leave = $client_id ? "/agent/client_overview.php?client_id=$client_id" : '/agent/clients.php';
define('FROM_CLIENT_ONBOARDING', true);

?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="<?= $user_config_theme_dark ? 'dark' : 'light' ?>" data-lte-color-mode="off">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="<?= $user_config_theme_dark ? 'dark' : 'light' ?>">
    <meta name="robots" content="noindex">
    <title><?= escapeHtml($co_title) ?> - <?= escapeHtml($co_name !== '' ? "Onboarding $co_name" : 'Onboard a client') ?> - <?= escapeHtml($co_company['company_name'] ?? 'ITFlow') ?></title>
    <?php if (file_exists($_SERVER['DOCUMENT_ROOT'] . '/uploads/favicon.ico')) { ?>
        <link rel="icon" type="image/x-icon" href="/uploads/favicon.ico">
    <?php } ?>
    <link rel="stylesheet" href="/libs/fontawesome-free/css/all.min.css">
    <link rel="stylesheet" href="/libs/adminlte/css/adminlte.min.css">
    <link rel="stylesheet" href="/libs/sweetalert2/css/sweetalert2.min.css">
    <link rel="stylesheet" href="/libs/intl-tel-input/css/intlTelInput.min.css">
    <link rel="stylesheet" href="/css/itflow_custom.css">
</head>

<body class="itflow-setup itflow-gs itflow-co bg-body-tertiary" data-itflow-phone-country="<?= escapeHtml($country_iso2_array[$session_company_country] ?? '') ?>">

<div class="itflow-setup-wrap itflow-gs-wrap">

    <header class="itflow-gs-top">
        <a href="<?= $client_id ? clientOnboardingUrl($client_id) : $co_leave ?>" class="itflow-gs-brand text-reset text-decoration-none min-w-0">
            <span class="itflow-gs-card-icon flex-shrink-0 align-self-center" aria-hidden="true"><i class="fas fa-route"></i></span>
            <span class="text-truncate" data-co-name><?= escapeHtml($co_name !== '' ? $co_name : 'A new client') ?></span>
            <span class="itflow-setup-brand-sub">Onboarding</span>
        </a>
        <a href="<?= escapeHtml($co_leave) ?>" class="btn btn-sm btn-outline-secondary flex-shrink-0" data-co-later
            title="<?= $client_id ? 'Their overview shows what is left, to carry on from' : 'Back to the clients' ?>"><?= $client_id && !$co_progress['finished'] ? 'Do it later' : ($client_id ? 'Go to the client' : 'Cancel') ?><i class="fas fa-arrow-right ms-2"></i></a>
    </header>

    <!-- The steps, and where each stands -->
    <nav class="itflow-setup-steps" aria-label="Onboarding steps">
        <ol class="list-unstyled d-none d-md-flex">
            <?php foreach ($co_keys_all as $key) {
                $step = $co_steps[$key] ?? (CLIENT_ONBOARDING_STEPS[$key] + ['done' => false, 'skipped' => false]);
                $state = $key === $co_step ? 'current' : ($step['done'] ? 'done' : ($step['skipped'] ? 'skipped' : 'todo'));
                $marker = $step['done'] ? '<i class="fas fa-check"></i>' : ($step['skipped'] ? '<i class="fas fa-minus"></i>' : '<i class="fas fa-fw ' . $step['icon'] . '"></i>'); ?>
                <li class="itflow-setup-step is-<?= $state ?><?= $step['done'] ? ' has-done' : '' ?>" data-co-step="<?= $key ?>" data-co-state="<?= $step['done'] ? 'done' : ($step['skipped'] ? 'skipped' : 'todo') ?>"<?= $state === 'current' ? ' aria-current="step"' : '' ?>>
                    <?php if ($client_id) { ?>
                        <a href="<?= clientOnboardingUrl($client_id, $key) ?>" title="<?= escapeHtml($step['title'] . ' - ' . ($step['done'] ? 'done' : ($step['skipped'] ? 'skipped for now' : 'to do'))) ?>">
                            <span class="itflow-setup-step-marker"><?= $marker ?></span><span class="itflow-setup-step-label"><?= escapeHtml($step['label']) ?></span>
                        </a>
                    <?php } else { ?>
                        <span title="<?= escapeHtml($step['title']) ?>"><span class="itflow-setup-step-marker"><?= $marker ?></span><span class="itflow-setup-step-label"><?= escapeHtml($step['label']) ?></span></span>
                    <?php } ?>
                </li>
            <?php } ?>
        </ol>
        <div class="d-md-none">
            <div class="d-flex justify-content-between small mb-1">
                <span class="fw-semibold"><?= escapeHtml($co_title) ?></span>
                <span class="text-body-secondary"><?= $client_id ? $co_progress['done'] : 0 ?> of <?= $co_total ?> done</span>
            </div>
            <div class="progress itflow-gs-progress" role="progressbar" aria-label="How far onboarding has got" aria-valuenow="<?= $client_id ? $co_progress['done'] : 0 ?>" aria-valuemin="0" aria-valuemax="<?= $co_total ?>">
                <div class="progress-bar" style="width: <?= $client_id && $co_total ? round($co_progress['done'] * 100 / $co_total) : 0 ?>%"></div>
            </div>
        </div>
    </nav>

    <main>
        <?php if ($co_alert !== '') { ?>
            <div class="alert alert-<?= $co_alert_type ?> d-flex gap-2 align-items-start" role="alert" data-co-alert="<?= $co_alert_type ?>">
                <i class="fas fa-fw mt-1 <?= ['success' => 'fa-check-circle', 'info' => 'fa-info-circle', 'warning' => 'fa-exclamation-triangle', 'danger' => 'fa-times-circle'][$co_alert_type] ?? 'fa-info-circle' ?>"></i>
                <div class="flex-grow-1"><?= alertMessageHtml($co_alert) ?></div>
            </div>
        <?php } ?>

        <div class="card itflow-setup-card" data-co-page="<?= escapeHtml($co_step) ?>">
            <?php if ($co_info) { ?>
                <div class="card-body p-4">
                    <div class="d-flex flex-wrap align-items-start gap-2 mb-1">
                        <h1 class="h4 fw-semibold mb-0 me-auto"><i class="fas fa-fw <?= $co_info['icon'] ?> text-body-secondary me-2"></i><?= escapeHtml($co_info['title']) ?></h1>
                        <?php if ($co_info['done']) { ?>
                            <span class="badge rounded-pill text-bg-success" data-co-done><i class="fas fa-check me-1"></i>Done</span>
                        <?php } elseif ($co_info['skipped']) { ?>
                            <span class="badge rounded-pill text-bg-secondary" data-co-skipped>Skipped for now</span>
                        <?php } ?>
                    </div>
                    <p class="text-body-secondary mb-4"><?= escapeHtml($co_info['about']) ?></p>
                    <?php require __DIR__ . "/client_onboarding/$co_step.php"; ?>
                </div>
                <?php if ($client_id) { ?>
                    <div class="card-footer d-flex flex-wrap align-items-center gap-2 py-3 px-4">
                        <?php if ($co_prev !== '') { ?>
                            <a href="<?= clientOnboardingUrl($client_id, $co_prev) ?>" class="btn btn-link text-decoration-none ps-0"><i class="fas fa-arrow-left me-2"></i>Back</a>
                        <?php } ?>
                        <span class="me-auto small text-body-secondary"><?= $co_progress['done'] ?> of <?= $co_progress['total'] ?> done</span>
                        <?php if ($co_info['done'] || $co_info['skipped']) { ?>
                            <a href="<?= clientOnboardingUrl($client_id, $co_next) ?>" class="btn btn-outline-primary" data-co-continue><?= $co_next === 'done' ? 'Finish' : 'Next: ' . escapeHtml($co_steps[$co_next]['title']) ?><i class="fas fa-arrow-right ms-2"></i></a>
                        <?php } elseif ($co_step !== 'company') { ?>
                            <form action="post.php" method="post" class="m-0">
                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                <input type="hidden" name="client_id" value="<?= $client_id ?>">
                                <button type="submit" name="client_onboarding_skip" value="<?= escapeHtml($co_step) ?>" class="btn btn-outline-secondary" data-co-skip>Skip for now</button>
                            </form>
                        <?php } ?>
                    </div>
                <?php } ?>
            <?php } else { ?>
                <div class="card-body p-4">
                    <?php require __DIR__ . '/client_onboarding/done.php'; ?>
                </div>
            <?php } ?>
        </div>
    </main>

    <footer class="itflow-setup-footer">
        <?= $client_id ? 'Carry on any time from ' . escapeHtml(contractPossessive($co_name)) . ' overview.' : 'Saving the company makes the client - the rest can wait.' ?>
    </footer>

</div>

<script src="/js/itflow_select.js"></script>
<script src="/libs/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="/libs/sweetalert2/js/sweetalert2.min.js"></script>
<script src="/libs/intl-tel-input/js/intlTelInputWithUtils.min.js"></script>
<script src="/js/phone_inputs.js"></script>
<script src="/js/confirm_modal.js"></script>
<script src="/js/app.js"></script>
<script src="/js/getting_started.js"></script>

</body>

</html>
