<?php
require_once("inc_all_settings.php");
?>

    <div class="card card-dark">
        <div class="card-header py-3">
            <h3 class="card-title"><i class="fas fa-fw fa-shield-alt mr-2"></i>Security</h3>
        </div>
        <div class="card-body">
            <form action="post.php" method="post" autocomplete="off">

                <div class="form-group">
                    <div class="custom-control custom-switch">
                        <input type="checkbox" class="custom-control-input" name="config_login_key_required" <?php if ($config_login_key_required == 1) { echo "checked"; } ?> value="1" id="customSwitch1">
                        <label class="custom-control-label" for="customSwitch1">Require a login key to access the technician login page?</label>
                    </div>
                </div>

                <div class="form-group">
                    <label>Login key secret value <small class="text-secondary">(This must be provided in the URL as /login.php?key=<?php echo htmlentities($config_login_key_secret)?>)</small></label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-key"></i></span>
                        </div>
                        <input type="text" class="form-control" name="config_login_key_secret" required pattern="\w{3,99}" placeholder="Something really easy for techs to remember: e.g. MYSECRET" value="<?php echo htmlentities($config_login_key_secret); ?>">
                    </div>
                </div>

                <hr>

                <button type="submit" name="edit_security_settings" class="btn btn-primary text-bold"><i class="fas fa-check mr-2"></i>Save</button>

            </form>
        </div>
    </div>

<?php
require_once("footer.php");
