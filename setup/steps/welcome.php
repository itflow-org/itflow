<?php

/*
 * Setup - Welcome: what ITFlow setup needs, and the way in - begin, carry on
 * where an install stopped, or restore a backup into an empty one.
 */

defined('FROM_SETUP') || die("Direct file access is not allowed");

?>
<div class="card itflow-setup-card">
    <div class="card-body p-4 p-lg-5">
        <h1 class="h3 fw-semibold mb-2">Set up ITFlow</h1>

        <?php if ($install_is_live) { ?>
            <p class="lead text-body-secondary mb-4">This install was left part-way through setup. Pick up where it stopped - what was saved is kept.</p>
            <a href="?<?= $resume_step ?>" class="btn btn-primary btn-lg" data-setup-begin>Continue setup<i class="fas fa-arrow-right ms-2"></i></a>
        <?php } elseif ($database_ready) { ?>
            <p class="lead text-body-secondary mb-4">The database is ready. Make your account to carry on - or bring a backup back instead.</p>
            <div class="d-flex flex-wrap gap-2">
                <a href="?user" class="btn btn-primary btn-lg" data-setup-begin>Create your account<i class="fas fa-arrow-right ms-2"></i></a>
                <?php if ($can_show_restore) { ?>
                    <a href="?restore" class="btn btn-outline-secondary btn-lg"><i class="fas fa-upload me-2"></i>Restore a backup</a>
                <?php } ?>
            </div>
        <?php } else { ?>
            <p class="lead text-body-secondary mb-4">A few minutes and you are signed in to your own ITFlow. Have these to hand:</p>
            <ul class="list-unstyled itflow-setup-needs mb-4">
                <li><i class="fas fa-fw fa-database"></i><div><strong>An empty MySQL or MariaDB database</strong><span>and a user that can create tables in it - its name, host and password</span></div></li>
                <li><i class="fas fa-fw fa-user"></i><div><strong>Your name and email</strong><span>for the first account - an administrator</span></div></li>
                <li><i class="fas fa-fw fa-building"></i><div><strong>Your company's name and country</strong><span>the address, phone, logo and the rest can come later</span></div></li>
                <li><i class="fas fa-fw fa-globe-americas"></i><div><strong>Your language, currency and timezone</strong></div></li>
            </ul>
            <div class="d-flex flex-wrap align-items-center gap-3">
                <a href="?checks" class="btn btn-primary btn-lg" data-setup-begin>Begin setup<i class="fas fa-arrow-right ms-2"></i></a>
                <span class="small text-body-secondary">Bringing a backup back? Begin the same way - the database step comes first, then Restore a backup.</span>
            </div>
        <?php } ?>

        <?php if (!is_writable('.')) { ?>
            <div class="alert alert-warning mt-4 mb-0"><i class="fas fa-exclamation-triangle me-2"></i>The web server can't write to this folder. Make ITFlow's folder writable by it - Checks says how.</div>
        <?php } ?>

        <details class="mt-4 small text-body-secondary">
            <summary>Where to look when something goes wrong</summary>
            <div class="mt-2">PHP writes its errors to <code><?= escapeHtml($errorLog) ?></code>. The <a href="https://docs.itflow.org/installation" target="_blank" rel="noopener">installation docs</a> cover the common problems, and the <a href="https://forum.itflow.org/t/support" target="_blank" rel="noopener">forum</a> is there for the rest.</div>
        </details>
    </div>
</div>
