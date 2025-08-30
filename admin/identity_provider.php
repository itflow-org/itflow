<?php
require_once "includes/inc_all_admin.php";
 ?>

<div class="card card-dark">
    <div class="card-header py-3">
        <h3 class="card-title"><i class="fas fa-fw fa-fingerprint mr-2"></i>Identity Providers</h3>
    </div>
    <div class="card-body">
        <form action="post.php" method="post" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?>">

            <h4>Client Portal SSO via Microsoft Entra</h4>

            <div class="form-group">
                <label>Identity Provider <small class='text-secondary'>(Currently only works with Microsoft Entra ID/AAD)</small></label>
                <div class="input-group">
                    <div class="input-group-prepend">
                        <span class="input-group-text"><i class="fa fa-fw fa-fingerprint"></i></span>
                    </div>
                    <select class="form-control select2" readonly>
                        <option <?php if (empty($config_azure_client_id)) { echo "selected"; } ?>>Disabled</option>
                        <option <?php if ($config_azure_client_id) { echo "selected"; } ?>>Microsoft Entra</option>
                        <option>Google (WIP)</option>
                        <option>Custom SSO (WIP)</option>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label>MS Entra OAuth App (Client) ID</label>
                <div class="input-group">
                    <div class="input-group-prepend">
                        <span class="input-group-text"><i class="fa fa-fw fa-user"></i></span>
                    </div>
                    <input type="text" class="form-control" name="azure_client_id" placeholder="e721e3b6-01d6-50e8-7f22-c84d951a52e7" value="<?php echo nullable_htmlentities($config_azure_client_id); ?>">
                </div>
            </div>

            <div class="form-group">
                <label>MS Entra OAuth Secret</label>
                <div class="input-group">
                    <div class="input-group-prepend">
                        <span class="input-group-text"><i class="fa fa-fw fa-key"></i></span>
                    </div>
                    <input type="password" class="form-control" name="azure_client_secret" placeholder="Auto-generated from App Registration" value="<?php echo nullable_htmlentities($config_azure_client_secret); ?>" autocomplete="new-password">
                </div>
            </div>

            <hr>

            <button type="submit" name="edit_identity_provider" class="btn btn-primary text-bold"><i class="fa fa-check mr-2"></i>Save</button>

        </form>
    </div>
</div>

<?php require_once "../includes/footer.php";
