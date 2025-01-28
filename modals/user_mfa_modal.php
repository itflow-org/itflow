<?php
require_once 'plugins/totp/totp.php';

//Generate a base32 Key
$token = key32gen();

// Generate QR Code
$data = "otpauth://totp/ITFlow:$session_email?secret=$token";

?>

<div class="modal" id="enableMFAModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content bg-dark">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fa fa-fw fa-lock mr-2"></i>Enabling Multi-Factor Authentication</h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form action="post.php" method="post" autocomplete="off">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?>">
                <input type="hidden" name="token" value="<?php echo $token; ?>">
                <div class="modal-body bg-white">
                        
                    <div class="text-center">
                        <img src='plugins/barcode/barcode.php?f=png&s=qr&d=<?php echo $data; ?>'>
                        <p><span class='text-secondary'>Secret:</span> <?php echo $token; ?>
                            <button type="button" class='btn btn-sm clipboardjs' data-clipboard-text='<?php echo $token; ?>'><i class='far fa-copy text-secondary'></i></button>
                        </p>
                    </div>

                    <div class="form-group">
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fa fa-fw fa-lock"></i></span>
                            </div>
                            <input type="text" class="form-control" inputmode="numeric" pattern="[0-9]*" name="verify_code" placeholder="Enter 6 digit code to verify MFA" required>
                        </div>
                    </div>

                </div>
                <div class="modal-footer bg-white">
                    <button type="submit" name="enable_mfa" class="btn btn-primary text-bold"><i class="fa fa-check mr-2"></i>Enable</button>
                    <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fa fa-times mr-2"></i>Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>
