<?php

/*
 * Setting up MFA - the QR code to scan (drawn here, so the secret never goes
 * out in an image URL), the secret to type in when it can't be scanned, and
 * the code from the app to prove it worked. Inside the enable_mfa form of
 * user_mfa_modal.php and mfa_enforcement.php, which set $mfa_secret.
 */

isset($mysqli) || die("Direct file access is not allowed");

$mfa_qr = userMfaQrSvg($mfa_secret, $session_email);
$mfa_secret_grouped = trim(chunk_split($mfa_secret, 4, ' '));

?>
<ol class="itflow-mfa-steps small text-start ps-3 mb-3">
    <li>Open your authenticator app - Microsoft or Google Authenticator, 1Password, Authy, Bitwarden - and add an account.</li>
    <li>Scan this code<?= $mfa_qr ? '' : ' - or, as it can\'t be shown here, type the secret in' ?>:</li>
</ol>

<div class="text-center mb-3">
    <?php if ($mfa_qr) { ?>
        <div class="itflow-mfa-qr-box"><?= $mfa_qr ?></div>
    <?php } ?>
    <div class="small text-body-secondary mt-2">Can't scan it? Type this secret in:</div>
    <div class="itflow-copy-always">
        <span class="font-monospace fw-semibold user-select-all"><?= escapeHtml($mfa_secret_grouped) ?></span>
        <button type="button" class="itflow-copy clipboardjs" title="Copy secret" data-clipboard-text="<?= escapeHtml($mfa_secret) ?>"><i class="far fa-copy"></i></button>
    </div>
</div>

<ol class="itflow-mfa-steps small text-start ps-3 mb-2" start="3">
    <li>Enter the 6-digit code it shows:</li>
</ol>
<div class="input-group">
    <span class="input-group-text"><i class="fas fa-fw fa-mobile-alt"></i></span>
    <input type="text" class="form-control form-control-lg font-monospace text-center itflow-mfa-code" inputmode="numeric" pattern="[0-9]{6}" minlength="6" maxlength="6" name="verify_code" placeholder="000000" autocomplete="one-time-code" required>
</div>
