<?php
require_once "inc_all_settings.php";
 ?>

    <div class="card card-dark">
        <div class="card-header py-3">
            <h3 class="card-title"><i class="fas fa-fw fa-cube mr-2"></i>Modules</h3>
        </div>
        <div class="card-body">
            <form action="post.php" method="post" autocomplete="off">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?>">

                <div class="form-group">
                    <div class="custom-control custom-switch">
                        <input type="checkbox" class="custom-control-input" name="config_module_enable_itdoc" <?php if ($config_module_enable_itdoc == 1) { echo "checked"; } ?> value="1" id="customSwitch1">
                        <label class="custom-control-label" for="customSwitch1">Show IT Documentation</label>
                    </div>
                </div>

                <div class="form-group">
                    <div class="custom-control custom-switch">
                        <input type="checkbox" class="custom-control-input" name="config_module_enable_ticketing" <?php if ($config_module_enable_ticketing == 1) { echo "checked"; } ?> value="1" id="customSwitch2">
                        <label class="custom-control-label" for="customSwitch2">Show Ticketing</label>
                    </div>
                </div>

                <div class="form-group">
                    <div class="custom-control custom-switch">
                        <input type="checkbox" class="custom-control-input" name="config_module_enable_accounting" <?php if ($config_module_enable_accounting == 1) { echo "checked"; } ?> value="1" id="customSwitch3">
                        <label class="custom-control-label" for="customSwitch3">Show Invoicing / Accounting</label>
                    </div>
                </div>

                <div class="form-group">
                    <div class="custom-control custom-switch">
                        <input type="checkbox" class="custom-control-input" name="config_client_portal_enable" <?php if ($config_client_portal_enable == 1) { echo "checked"; } ?> value="1" id="customSwitch4">
                        <label class="custom-control-label" for="customSwitch4">Enable Client Portal</label>
                    </div>
                </div>

                <hr>

                <button type="submit" name="edit_module_settings" class="btn btn-primary text-bold"><i class="fas fa-check mr-2"></i>Save</button>

            </form>
        </div>
    </div>

<?php
require_once "footer.php";

