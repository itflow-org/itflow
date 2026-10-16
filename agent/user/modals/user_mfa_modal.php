<?php

/*
 * Turn on MFA - from Your account > Security. The secret is made once and
 * kept in the session until a code from it is checked, so the modal opening
 * again (a wrong code) shows the same one.
 */

require_once dirname(__DIR__, 3) . '/libs/totp/totp.php';

if (empty($_SESSION['mfa_token'])) {
    $_SESSION['mfa_token'] = generateTotpSecret();
}
$mfa_secret = $_SESSION['mfa_token'];

?>

<div class="modal" id="enableMFAModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-dark">
                <h5 class="modal-title"><i class="fas fa-fw fa-mobile-alt me-2"></i>Turn on multi-factor authentication</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form action="post.php" method="post" autocomplete="off">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <div class="modal-body">
                    <?php require __DIR__ . '/../includes/mfa_setup.php'; ?>
                </div>
                <div class="modal-footer">
                    <button type="submit" name="enable_mfa" class="btn btn-primary text-bold"><i class="fa fa-check me-2"></i>Turn on</button>
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fa fa-times me-2"></i>Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>
