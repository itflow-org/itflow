<?php
require_once "includes/inc_all_user.php";

// User remember me tokens
$sql_remember_tokens = mysqli_query($mysqli, "SELECT remember_token_created_at, remember_token_id FROM remember_tokens WHERE remember_token_user_id = $session_user_id");
$remember_token_count = mysqli_num_rows($sql_remember_tokens);

?>

<div class="card card-dark">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-shield-alt me-2"></i>Your Password</h3>
    </div>
    <div class="card-body">
        <form action="post.php" method="post" enctype="multipart/form-data" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

            <div class="mb-3">
                <label>Your New Password <strong class="text-danger">*</strong></label>
                <div class="input-group">
                        <span class="input-group-text"><i class="fa fa-fw fa-lock"></i></span>
                    <input type="password" class="form-control" data-toggle="password" name="new_password" placeholder="Leave blank for no change" autocomplete="new-password" minlength="8" required>
                        <span class="input-group-text"><i class="fa fa-fw fa-eye"></i></span>
                </div>
            </div>

            <button type="submit" name="edit_your_user_password" class="btn btn-primary"><i class="fas fa-check me-2"></i>Change</button>

        </form>

         <div class="float-end">
            <?php if (empty($session_token)) { ?>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#enableMFAModal">
                    <i class="fas fa-lock me-2"></i>Enable MFA
                </button>

                <?php require_once "modals/user_mfa_modal.php"; ?>

            <?php } else { ?>
                <a href="post.php?disable_mfa&csrf_token=<?= $_SESSION['csrf_token'] ?>" class="btn btn-danger"><i class="fas fa-unlock me-2"></i>Disable MFA</a>
            <?php } ?>
        </div>

    </div>
</div>

<?php if ($remember_token_count > 0) { ?>
    <div class="card card-dark">
        <div class="card-header py-3">
            <h3 class="card-title"><i class="fas fa-fw fa-clock me-2"></i>2FA Remember-Me Tokens</h3>
        </div>
        <div class="card-body">

            <ul>
                <?php while ($row = mysqli_fetch_assoc($sql_remember_tokens)) {
                    $token_id = intval($row['remember_token_id']);
                    $token_created = escapeHtml($row['remember_token_created_at']);

                    echo "<li>ID: $token_id | Created: $token_created</li>";
                } ?>
            </ul>

            <form action="post.php" method="post" autocomplete="off">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

                <button type="submit" name="revoke_your_2fa_remember_tokens" class="btn btn-danger w-100 mt-3"><i class="fas fa-exclamation-triangle me-2"></i>Revoke Remember-Me Tokens</button>

            </form>

        </div>
    </div>
<?php } ?>

<?php

// Show the error alert if it exists:
if (!empty($_SESSION['alert_type']) && $_SESSION['alert_type'] == 'error') {
    echo "<div class='alert alert-danger'>{$_SESSION['alert_message']}</div>";
    // Clear it so it doesn't persist on refresh
    unset($_SESSION['alert_type']);
    unset($_SESSION['alert_message']);
}

// If the user just failed a TOTP verification, auto-open the modal:
if (!empty($_SESSION['show_mfa_modal'])) {
    echo "
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // jQuery or vanilla JS to open the modal
            bootstrap.Modal.getOrCreateInstance(document.getElementById('enableMFAModal')).show();
        });
    </script>";
    unset($_SESSION['show_mfa_modal']);
}

require_once "../../includes/footer.php";
