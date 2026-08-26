<?php
require_once '../../libs/totp/totp.php';

// Only generate the token once and store it in session:
if (empty($_SESSION['mfa_token'])) {
    $token = generateTotpSecret();
    $_SESSION['mfa_token'] = $token;
}
$token = $_SESSION['mfa_token'];

// Generate QR Code
$data = "otpauth://totp/ITFlow:$session_email?secret=$token";

?>

<div class="modal" id="enableMFAModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-dark">
                <h5 class="modal-title"><i class="fa fa-fw fa-lock me-2"></i>Multi-Factor Authentication</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form action="post.php" method="post" autocomplete="off">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <div class="modal-body">

                    <div class="text-center">
                        <img src='../../libs/barcode/barcode.php?f=png&s=qr&d=<?= $data ?>'>
                        <p><span class='text-secondary'>Secret:</span> <?= $token ?>
                            <button type="button" class='btn btn-sm btn-link clipboardjs' data-clipboard-text='<?= $token ?>'><i class='far fa-copy text-secondary'></i></button>
                        </p>
                    </div>

                    <div class="mb-3">
                        <div class="input-group">
                                <span class="input-group-text"><i class="fa fa-fw fa-lock"></i></span>
                            <input type="text" class="form-control" inputmode="numeric" pattern="[0-9]*" minlength="6" maxlength="6" name="verify_code" placeholder="Enter 6 digit code to verify MFA" required>
                        </div>
                    </div>

                </div>
                <div class="modal-footer">
                    <button type="submit" name="enable_mfa" class="btn btn-primary text-bold"><i class="fa fa-check me-2"></i>Enable</button>
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fa fa-times me-2"></i>Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>
