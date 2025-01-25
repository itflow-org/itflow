<?php
require_once "includes/inc_all_user.php";

// User remember me tokens
$sql_remember_tokens = mysqli_query($mysqli, "SELECT * FROM remember_tokens WHERE remember_token_user_id = $session_user_id");
$remember_token_count = mysqli_num_rows($sql_remember_tokens);

?>

<div class="card card-dark">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-shield-alt mr-2"></i>Your Password</h3>
    </div>
    <div class="card-body">
        <form action="post.php" method="post" enctype="multipart/form-data" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?>">

            <div class="form-group">
                <label>Your New Password <strong class="text-danger">*</strong></label>
                <div class="input-group">
                    <div class="input-group-prepend">
                        <span class="input-group-text"><i class="fa fa-fw fa-lock"></i></span>
                    </div>
                    <input type="password" class="form-control" data-toggle="password" name="new_password" placeholder="Leave blank for no change" autocomplete="new-password" minlength="8" required>
                    <div class="input-group-append">
                        <span class="input-group-text"><i class="fa fa-fw fa-eye"></i></span>
                    </div>
                </div>
            </div>

            <button type="submit" name="edit_your_user_password" class="btn btn-primary"><i class="fas fa-check mr-2"></i>Change</button>

        </form>
    </div>
</div>

<div class="card card-dark">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-lock mr-2"></i>Mult-Factor Authentication</h3>
    </div>
    <div class="card-body">
        <form action="post.php" method="post" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?>">

            <?php if (empty($session_token)) { ?>
                <button type="submit" name="enable_2fa" class="btn btn-success"><i class="fa fa-fw fa-lock"></i><br> Enable 2FA</button>
            <?php } else { ?>
                <p>You have set up 2FA. Your QR code is below.</p>
                <button type="submit" name="disable_2fa" class="btn btn-danger"><i class="fa fa-fw fa-unlock"></i><br>Disable 2FA</button>
            <?php } ?>

            <center>
                <?php

                require_once 'includes/rfc6238.php';

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
                        <input type="text" class="form-control" inputmode="numeric" pattern="[0-9]*" name="code" placeholder="Verify 2FA Code" required>
                        <div class="input-group-append">
                            <button type="submit" name="verify" class="btn btn-success">Verify</button>
                        </div>
                    </div>
                </div>

            </form>
        <?php } ?>
    </div>
</div>

<?php if ($remember_token_count > 0) { ?>
    <div class="card card-dark">
        <div class="card-header py-3">
            <h3 class="card-title"><i class="fas fa-fw fa-clock mr-2"></i>2FA Remember-Me Tokens</h3>
        </div>
        <div class="card-body">

            <ul>
                <?php while ($row = mysqli_fetch_array($sql_remember_tokens)) {
                    $token_id = intval($row['remember_token_id']);
                    $token_created = nullable_htmlentities($row['remember_token_created_at']);

                    echo "<li>ID: $token_id | Created: $token_created</li>";
                } ?>
            </ul>

            <form action="post.php" method="post" autocomplete="off">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?>">

                <button type="submit" name="revoke_your_2fa_remember_tokens" class="btn btn-danger btn-block mt-3"><i class="fas fa-exclamation-triangle mr-2"></i>Revoke Remember-Me Tokens</button>

            </form>

        </div>
    </div>
<?php } ?>

<?php
require_once "includes/footer.php";
