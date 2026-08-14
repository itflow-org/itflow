<?php
require_once "includes/inc_all_admin.php";

?>

<div class="card card-dark">
    <div class="card-header py-3">
        <h3 class="card-title"><i class="fas fa-fw fa-shield-alt me-2"></i>Security</h3>
    </div>
    <div class="card-body">
        <form action="post.php" method="post" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

            <div class="mb-3">
                <label>Login Message</label>
                <textarea class="form-control" name="config_login_message" rows="5" placeholder="Enter a message to be displayed on the login screen"><?= escapeHtml($config_login_message) ?></textarea>
            </div>

            <div class="mb-3">
                <div class="form-check form-switch">
                    <input type="checkbox" class="form-check-input" name="config_login_key_required" <?php if ($config_login_key_required == 1) { echo "checked"; } ?> value="1" id="customSwitch1">
                    <label class="form-check-label" for="customSwitch1">Require a login key to access the technician login page?</label>
                </div>
            </div>

            <div class="mb-3">
                <label>Login key secret value <small class="text-secondary">(This must be provided in the URL as /login.php?key=<?= escapeHtml($config_login_key_secret) ?>)</small></label>
                <div class="input-group">
                        <span class="input-group-text"><i class="fa fa-fw fa-key"></i></span>
                    <input type="text" class="form-control" name="config_login_key_secret" pattern="\w{3,99}" placeholder="Something really easy for techs to remember: e.g. MYSECRET" maxlength="99" value="<?= escapeHtml($config_login_key_secret) ?>">
                </div>
            </div>

            <div class="mb-3">
                <label>2FA Remember Me Expire <small class="text-secondary">(The amount of days before a device 2FA remember me token will expire)</small></label>
                <div class="input-group">
                        <span class="input-group-text"><i class="fa fa-fw fa-clock"></i></span>
                    <input type="number" class="form-control" name="config_login_remember_me_expire" placeholder="Enter Days to Expire" value="<?= intval($config_login_remember_me_expire) ?>">
                </div>
            </div>

            <div class="mb-3">
                <label>Log retention <small class="text-secondary">(The amount of days before app/audit/auth logs are deleted during nightly cron)</small></label>
                <div class="input-group">
                        <span class="input-group-text"><i class="fa fa-fw fa-clock"></i></span>
                    <input type="number" class="form-control" name="config_log_retention" placeholder="Enter days to retain" value="<?= intval($config_log_retention) ?>">
                </div>
            </div>

            <hr>

            <button type="submit" name="edit_security_settings" class="btn btn-primary text-bold"><i class="fas fa-check me-2"></i>Save</button>

        </form>
    </div>
</div>

<?php
require_once "../includes/footer.php";

