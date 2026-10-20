<?php

/*
 * Setup - Finish: what to know before signing in (HTTPS, the address in links,
 * the backup key - made now, so it can be kept now), what to do once signed in,
 * whether to tell the ITFlow project about this install, and Finish - which
 * closes setup (add_telemetry in setup/index.php) and goes to the sign-in page.
 */

defined('FROM_SETUP') || die("Direct file access is not allowed");

$copy_field = function ($id, $value, $label) {
    ob_start(); ?>
    <div class="input-group input-group-sm mt-2">
        <input type="text" class="form-control font-monospace" id="<?= $id ?>" value="<?= escapeHtml($value) ?>" aria-label="<?= escapeHtml($label) ?>" readonly>
        <button type="button" class="btn btn-outline-secondary clipboardjs" data-clipboard-text="<?= escapeHtml($value) ?>" title="Copy"><i class="far fa-copy"></i></button>
    </div>
    <?php return ob_get_clean();
};

if ($localization_done) {
    // Made once and kept in config.php - functions/backup.php
    $setup_backup_key = backupEncryptionKey();
    setupConfigChanged(dirname(__DIR__, 2) . '/config.php');
    $setup_https = setupRequestIsHttps();
    $setup_base_url = trim((string) ($config_base_url ?? ''));
    $setup_base_host = strtolower(preg_replace('#[:/].*$#', '', $setup_base_url));
    $setup_base_is_name = (bool) preg_match('/^(?=.{1,253}$)([a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,}$/i', $setup_base_host);
    $setup_sharing = setupTelemetryDetails($mysqli);
}

?>
<div class="card itflow-setup-card">
    <div class="card-body p-4">
        <h1 class="h4 fw-semibold mb-1"><i class="fas fa-flag-checkered text-body-secondary me-2"></i>Finish</h1>
        <p class="text-body-secondary mb-4">Nearly there - a few things to know before you sign in.</p>

        <?php if (!$localization_done) { ?>
            <div class="alert alert-info mb-3"><i class="fas fa-info-circle me-2"></i>There are steps still to do before setup can finish.</div>
            <a href="?<?= $resume_step ?>" class="btn btn-primary">Carry on where setup is<i class="fas fa-arrow-right ms-2"></i></a>
        <?php } else { ?>
            <form method="post" action="?telemetry" autocomplete="off">

                <?php if (!$setup_https && ($config_https_only ?? true)) { ?>
                    <div class="alert alert-warning" data-finish-https>
                        <div class="fw-semibold mb-1"><i class="fas fa-lock-open me-2"></i>Signing in needs HTTPS</div>
                        This page came over http://, and ITFlow only lets you sign in over https:// - so the sign-in page will turn you away until the site has a certificate (Let's Encrypt is free).
                        Only for a test install that never leaves your network, change this line in <code>config.php</code> instead:
                        <?= $copy_field('setupHttpsOnly', '$config_https_only = FALSE;', 'The line to change in config.php') ?>
                    </div>
                <?php } ?>

                <ul class="itflow-setup-needs list-unstyled mb-4">
                    <li data-finish-key>
                        <i class="fas fa-fw fa-key"></i>
                        <div class="flex-grow-1">
                            <strong>Your backup key</strong>
                            <?php if ($setup_backup_key !== '') { ?>
                                <span>Backups are locked with it. Keep a copy somewhere safe, away from this server - a backup can't be restored without it. It is in Maintenance &gt; Backup too.</span>
                                <?= $copy_field('setupBackupKey', $setup_backup_key, 'Backup key') ?>
                            <?php } else { ?>
                                <span>config.php couldn't be written to, so there isn't one yet - Maintenance &gt; Backup says how to add it.</span>
                            <?php } ?>
                        </div>
                    </li>
                    <li data-finish-address>
                        <i class="fas fa-fw fa-link"></i>
                        <div class="flex-grow-1">
                            <strong>Its address</strong>
                            <?php if ($setup_base_url !== '') { ?>
                                <span>Links in emails and the client portal go to <a href="https://<?= escapeHtml($setup_base_url) ?>" target="_blank" rel="noopener">https://<?= escapeHtml($setup_base_url) ?></a>.<?= $setup_base_is_name ? '' : ' That is an address only this network knows - give ITFlow a domain name and put it in <code>$config_base_url</code> in config.php before you invite clients.' ?></span>
                            <?php } else { ?>
                                <span>Put the address people reach ITFlow at in <code>$config_base_url</code> in config.php - links in emails go to it.</span>
                            <?php } ?>
                        </div>
                    </li>
                </ul>

                <h2 class="h6 fw-semibold">Once you are signed in</h2>
                <ol class="itflow-setup-next list-unstyled mb-4">
                    <li data-finish-cron>
                        <strong>Cron</strong> - ITFlow needs one crontab entry, for the user that owns its files. The install script adds it for you; by hand, it is:
                        <?= $copy_field('setupCron', "* * * * * php " . dirname(__DIR__, 2) . "/cron/cron.php >/dev/null", 'Crontab entry') ?>
                        <span class="d-block small text-body-secondary mt-1">Then turn it on in Maintenance &gt; Cron - it ships off.</span>
                    </li>
                    <li><strong>Mail</strong> - the mailboxes ITFlow sends from and turns emails into tickets from, in Admin &gt; Settings &gt; Mail.</li>
                    <li><strong>Backups</strong> - when they run, in Maintenance &gt; Backup.</li>
                    <li><strong>Starter content</strong> - templates, categories and checklists to start from, in Maintenance &gt; Starter Content.</li>
                    <li><strong>Your team</strong> - invite them from Admin &gt; Users.</li>
                </ol>

                <div class="itflow-setup-share mb-4" data-finish-share>
                    <div class="form-check form-switch mb-1">
                        <input type="checkbox" class="form-check-input" role="switch" name="share_data" value="1" id="setupShareData" data-setup-reveal="setupShareComments">
                        <label class="form-check-label fw-semibold" for="setupShareData">Tell the ITFlow project about this install</label>
                    </div>
                    <div class="small text-body-secondary">Once, now - so the project can see who uses ITFlow. Nothing else is ever sent. <a href="https://docs.itflow.org/telemetry" target="_blank" rel="noopener">More<i class="fas fa-external-link-alt small ms-1"></i></a></div>
                    <div class="small fw-semibold mt-3 mb-1">What is sent</div>
                    <dl class="itflow-setup-share-list small mb-0">
                        <?php foreach (['company_name' => 'Company', 'website' => 'Website', 'city' => 'City', 'state' => 'State', 'country' => 'Country', 'currency' => 'Currency'] as $key => $label) { ?>
                            <dt><?= $label ?></dt>
                            <dd><?= $setup_sharing[$key] !== '' ? escapeHtml($setup_sharing[$key]) : '<span class="text-body-secondary">-</span>' ?></dd>
                        <?php } ?>
                        <dt>Install</dt>
                        <dd class="text-body-secondary">a random id made for it</dd>
                    </dl>
                    <div id="setupShareComments" class="mt-3" data-setup-reveal-target>
                        <label class="form-label small" for="setupComments">Anything to tell us?</label>
                        <textarea class="form-control" rows="2" id="setupComments" name="comments" maxlength="1000" placeholder="Sent with it"></textarea>
                    </div>
                </div>

                <div class="d-flex justify-content-end">
                    <button type="submit" name="add_telemetry" class="btn btn-primary btn-lg" data-setup-next>Finish and sign in<i class="fas fa-check ms-2"></i></button>
                </div>
            </form>
        <?php } ?>
    </div>
</div>
