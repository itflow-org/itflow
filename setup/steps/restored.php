<?php

/*
 * Setup - a backup restored (restore in setup/index.php): the one page setup
 * still shows once the restore has closed it, once, saying where to sign in.
 */

defined('FROM_SETUP') || die("Direct file access is not allowed");

$setup_closed = ($_SESSION['setup_restored'] ?? '') === 'closed';
unset($_SESSION['setup_restored']);

?>
<div class="card itflow-setup-card">
    <div class="card-body p-4 p-lg-5 text-center" data-restored="<?= $setup_closed ? 'closed' : 'open' ?>">
        <i class="fas fa-check-circle text-success fa-3x mb-3"></i>
        <h1 class="h3 fw-semibold mb-2">Backup restored</h1>
        <p class="lead text-body-secondary mb-4">Its database and files are back. Sign in with an account from the backup - its password and two-step sign-in are as they were when the backup was made.</p>
        <?php if (!$setup_closed) { ?>
            <div class="alert alert-warning text-start"><i class="fas fa-exclamation-triangle me-2"></i>config.php could not be written to, so setup is still open. Add this line to the end of it now: <code>$config_enable_setup = 0;</code></div>
        <?php } ?>
        <a href="../login.php" class="btn btn-primary btn-lg" data-setup-next>Sign in<i class="fas fa-arrow-right ms-2"></i></a>
    </div>
</div>
