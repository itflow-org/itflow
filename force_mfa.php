<?php
require_once "config.php";

include_once "functions.php";

require_once "check_login.php";

require_once "header.php";

?>

<div class="card card-dark">
    <div class="card-header py-3">
        <h3 class="card-title"><i class="fas fa-fw fa-sheild mr-2"></i>2FA Setup</h3>
    </div>
    <div class="card-body">

        <form action="post.php" method="post" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?>">

            <?php if (empty($session_token)) { ?>
                <button type="submit" name="enable_2fa" class="btn btn-success btn-block mt-3"><i class="fa fa-fw fa-lock"></i><br> Enable 2FA</button>
            <?php } else { ?>
                <p>You have set up 2FA. Your QR code is below.</p>
                <button type="submit" name="disable_2fa" class="btn btn-danger btn-block mt-3"><i class="fa fa-fw fa-unlock"></i><br>Disable 2FA</button>
            <?php } ?>

            <center>
                <?php

                require_once 'rfc6238.php';


                //Generate a base32 Key
                $secretkey = key32gen();

                if (!empty($session_token)) {

                    // Generate QR Code
                    $data = "otpauth://totp/ITFlow:$session_email?secret=$session_token";
                    print "<img src='plugins/barcode/barcode.php?f=png&s=qr&d=$data'>";

                    echo "<p class='text-secondary'>$session_token</p>";
                }

                ?>
            </center>

            <input type="hidden" name="token" value="<?php echo $secretkey; ?>">

        </form>

        <?php if (!empty($session_token)) { ?>
            <form action="post.php" method="post" autocomplete="off">
                <div class="form-group">
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-key"></i></span>
                        </div>
                        <input type="text" class="form-control" name="code" placeholder="Verify 2FA Code" required>
                        <div class="input-group-append">
                            <button type="submit" name="verify" class="btn btn-success">Verify</button>
                        </div>
                    </div>
                </div>

            </form>
        <?php } ?>
    </div>
</div>

<?php
require_once "includes/footer.php";

