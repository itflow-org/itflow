<?php

/*
 * Setup - Finish: whether to tell the ITFlow project about this install, what
 * to do once it is running, and Finish - which closes setup
 * ($config_enable_setup = 0, add_telemetry in setup/index.php) and goes to
 * the sign-in page.
 */

defined('FROM_SETUP') || die("Direct file access is not allowed");

?>
<div class="card itflow-setup-card">
    <div class="card-body p-4">
        <h1 class="h4 fw-semibold mb-1"><i class="fas fa-flag-checkered text-body-secondary me-2"></i>Finish</h1>
        <p class="text-body-secondary mb-4">Nearly there.</p>

        <?php if (!$localization_done) { ?>
            <div class="alert alert-info mb-3"><i class="fas fa-info-circle me-2"></i>There are steps still to do before setup can finish.</div>
            <a href="?<?= $resume_step ?>" class="btn btn-primary">Carry on where setup is<i class="fas fa-arrow-right ms-2"></i></a>
        <?php } else { ?>
            <form method="post" autocomplete="off">
                <h2 class="h6 fw-semibold">Tell the ITFlow project about this install?</h2>
                <div class="form-check form-switch mb-2">
                    <input type="checkbox" class="form-check-input" role="switch" name="share_data" value="1" id="setupShareData">
                    <label class="form-check-label" for="setupShareData">Share your company's name, website, city, state, country and currency</label>
                </div>
                <div class="small text-body-secondary mb-3">Once, now - it helps the project see who uses ITFlow. <a href="https://docs.itflow.org/telemetry" target="_blank" rel="noopener">What is sent<i class="fas fa-external-link-alt small ms-1"></i></a></div>
                <div class="mb-4">
                    <label class="form-label" for="setupComments">Anything to tell us?</label>
                    <textarea class="form-control" rows="3" id="setupComments" name="comments" placeholder="Sent with it, if you share"></textarea>
                </div>

                <h2 class="h6 fw-semibold">Once you are signed in</h2>
                <ul class="itflow-setup-needs list-unstyled mb-4">
                    <li><i class="fas fa-fw fa-clock"></i><div><strong>Cron</strong><span>ITFlow needs one crontab entry, for the user that owns its files - the install script adds it for you:</span>
                        <pre class="bg-dark text-white rounded p-2 mt-2 mb-1 small"><?= escapeHtml("* * * * * php " . dirname(__DIR__, 2) . "/cron/cron.php >/dev/null") ?></pre>
                        <span>Then turn it on in Maintenance &gt; Cron - it ships off.</span></div></li>
                    <li><i class="fas fa-fw fa-archive"></i><div><strong>Backups</strong><span><a href="https://docs.itflow.org/backups" target="_blank" rel="noopener">Set them up</a> in Maintenance &gt; Backup, and keep its key somewhere safe.</span></div></li>
                    <li><i class="fab fa-fw fa-github"></i><div><strong>Star ITFlow</strong><span>on <a href="https://github.com/itflow-org/itflow" target="_blank" rel="noopener">GitHub</a> :)</span></div></li>
                </ul>

                <div class="d-flex justify-content-end">
                    <button type="submit" name="add_telemetry" class="btn btn-primary btn-lg" data-setup-next>Finish and sign in<i class="fas fa-check ms-2"></i></button>
                </div>
            </form>
        <?php } ?>
    </div>
</div>
