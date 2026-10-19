<?php
require_once "includes/inc_all_admin.php";

// Read here rather than with every page's settings, so a page still opens before the update that adds it
$config_login_alert_failures = signInAlertLimit();

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
                <label>Tell administrators about failed sign-ins <small class="text-secondary">(In <?= SIGN_IN_WINDOW_MINUTES ?> minutes, from one IP address or against one account - each admin gets a notification and an email. An address is turned away after <?= SIGN_IN_LOCKOUT ?>.)</small></label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-bell"></i></span>
                    <select class="form-select" name="config_login_alert_failures">
                        <?php foreach (signInAlertOptions() as $failures => $label) { ?>
                            <option value="<?= $failures ?>" <?= intval($config_login_alert_failures) === $failures ? 'selected' : '' ?>><?= $label ?></option>
                        <?php } ?>
                    </select>
                </div>
            </div>

            <h5 class="mt-4 mb-3"><i class="fas fa-fw fa-share-alt me-2"></i>Shared links</h5>

            <div class="mb-3">
                <label>Longest a shared link can last <small class="text-secondary">(The longest choice agents get when they share a login, note, document or file)</small></label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-clock"></i></span>
                    <select class="form-select" name="config_share_max_hours">
                        <?php foreach (shareMaxOptions() as $hours => $label) { ?>
                            <option value="<?= $hours ?>" <?= intval($config_share_max_hours) === $hours ? 'selected' : '' ?>><?= $label ?></option>
                        <?php } ?>
                    </select>
                </div>
            </div>

            <div class="mb-3">
                <div class="form-check form-switch">
                    <input type="checkbox" class="form-check-input" name="config_share_require_protection" <?php if ($config_share_require_protection == 1) { echo "checked"; } ?> value="1" id="shareRequireProtection">
                    <label class="form-check-label" for="shareRequireProtection">Shared passwords and secure notes need a passphrase or an emailed code to open</label>
                </div>
            </div>

            <hr>

            <button type="submit" name="edit_security_settings" class="btn btn-primary text-bold"><i class="fas fa-check me-2"></i>Save</button>

        </form>
    </div>
</div>

<?php
require_once "../includes/footer.php";

