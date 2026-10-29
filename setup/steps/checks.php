<?php

/*
 * Setup - Checks: what the server needs (setup_checks.php). The required ones
 * stop setup until they pass; the recommended ones say what ITFlow works
 * better with, and how to get it.
 */

defined('FROM_SETUP') || die("Direct file access is not allowed");

$setup_checks = setupChecks(dirname(__DIR__, 2));
$required_failed = setupChecksFailed($setup_checks['required']);
$recommended_failed = setupChecksFailed($setup_checks['recommended']);
$next_step = $resume_step === 'checks' ? 'database' : $resume_step;

$check_list = function (array $checks, $tone) {
    ob_start(); ?>
    <ul class="list-group list-group-flush itflow-setup-checks">
        <?php foreach ($checks as $check) { ?>
            <li class="list-group-item px-0 d-flex gap-3" data-check-passed="<?= $check['passed'] ? '1' : '0' ?>">
                <i class="fas fa-fw mt-1 <?= $check['passed'] ? 'fa-check-circle text-success' : ($tone === 'danger' ? 'fa-times-circle text-danger' : 'fa-exclamation-triangle text-warning') ?>"></i>
                <div class="min-w-0 flex-grow-1">
                    <div class="d-flex flex-wrap justify-content-between column-gap-3">
                        <span class="fw-semibold"><?= escapeHtml($check['label']) ?></span>
                        <span class="small text-body-secondary text-break"><?= escapeHtml($check['value']) ?></span>
                    </div>
                    <?php if ($check['fix'] !== '') { ?>
                        <div class="small mt-1 text-body-secondary"><?= $check['fix'] ?></div>
                    <?php } ?>
                </div>
            </li>
        <?php } ?>
    </ul>
    <?php return ob_get_clean();
};

?>
<div class="card itflow-setup-card">
    <div class="card-body p-4">
        <h1 class="h4 fw-semibold mb-1"><i class="fas fa-clipboard-check text-body-secondary me-2"></i>Checks</h1>
        <p class="text-body-secondary mb-3">What this server has, against what ITFlow needs.</p>

        <?php if ($required_failed) { ?>
            <div class="alert alert-danger" data-checks-summary="blocked"><i class="fas fa-times-circle me-2"></i><strong><?= $required_failed ?> required <?= $required_failed === 1 ? 'check needs' : 'checks need' ?> fixing</strong> before setup can carry on. Fix <?= $required_failed === 1 ? 'it' : 'them' ?>, then check again.</div>
        <?php } elseif ($recommended_failed) { ?>
            <div class="alert alert-warning" data-checks-summary="ready"><i class="fas fa-check-circle me-2"></i><strong>Ready to set up.</strong> <?= $recommended_failed ?> recommended <?= $recommended_failed === 1 ? 'thing is' : 'things are' ?> worth sorting out - now, or once ITFlow is running.</div>
        <?php } else { ?>
            <div class="alert alert-success" data-checks-summary="ready"><i class="fas fa-check-circle me-2"></i><strong>Everything ITFlow needs is here.</strong></div>
        <?php } ?>

        <h2 class="h6 text-uppercase text-body-secondary mt-4 mb-1">Required</h2>
        <?= $check_list($setup_checks['required'], 'danger') ?>

        <h2 class="h6 text-uppercase text-body-secondary mt-4 mb-1">Recommended</h2>
        <?= $check_list($setup_checks['recommended'], 'warning') ?>
    </div>
    <div class="card-footer d-flex flex-wrap gap-2 justify-content-between p-3">
        <a href="?checks" class="btn btn-outline-secondary"><i class="fas fa-redo me-2"></i>Check again</a>
        <?php if ($required_failed) { ?>
            <button type="button" class="btn btn-primary" disabled>Continue<i class="fas fa-arrow-right ms-2"></i></button>
        <?php } else { ?>
            <a href="?<?= $next_step ?>" class="btn btn-primary" data-setup-next>Continue<i class="fas fa-arrow-right ms-2"></i></a>
        <?php } ?>
    </div>
</div>
