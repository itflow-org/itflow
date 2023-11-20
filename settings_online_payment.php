<?php

require_once "inc_all_settings.php";


?>

    <div class="card card-dark">
        <div class="card-header py-3">
            <h3 class="card-title"><i class="fas fa-fw fa-credit-card mr-2"></i>Online Payment</h3>
        </div>
        <div class="card-body">
            <form action="post.php" method="post" autocomplete="off">

                <div class="form-group">
                    <div class="custom-control custom-switch">
                        <input type="checkbox" class="custom-control-input" name="config_stripe_enable" <?php if ($config_stripe_enable == 1) { echo "checked"; } ?> value="1" id="enableStripeSwitch">
                        <label class="custom-control-label" for="enableStripeSwitch">Enable Stripe</label>
                    </div>
                </div>

                <?php if ($config_stripe_enable == 1) { ?>

                    <div class="form-group">
                        <label>Publishable</label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fa fa-fw fa-eye"></i></span>
                            </div>
                            <input type="text" class="form-control" name="config_stripe_publishable" placeholder="Stripe Publishable API Key (pk_...)" value="<?php echo nullable_htmlentities($config_stripe_publishable); ?>">
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Secret</label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fa fa-fw fa-lock"></i></span>
                            </div>
                            <input type="text" class="form-control" name="config_stripe_secret" placeholder="Stripe Secret API Key (sk_...)" value="<?php echo nullable_htmlentities($config_stripe_secret); ?>">
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Account</label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fas fa-fw fa-piggy-bank"></i></span>
                            </div>
                            <select class="form-control select2" name="config_stripe_account" required>
                                <option value="">- Account -</option>
                                <?php
                                $sql_accounts = mysqli_query($mysqli, "SELECT * FROM accounts LEFT JOIN account_types ON account_types.account_type_id = accounts.account_type WHERE account_type_parent = 1 AND account_archived_at IS NULL ORDER BY account_name ASC");
                                while ($row = mysqli_fetch_array($sql_accounts)) {
                                    $account_id = intval($row['account_id']);
                                    $account_name = nullable_htmlentities($row['account_name']); 
                                    ?>
                                        
                                    <option value="<?php echo $account_id ?>" <?php if ($account_id == $config_stripe_account) { echo "selected"; } ?>><?php echo $account_name ?></option>
                                <?php
                                }
                                ?>
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Client Pays Fees</label>
                        <div class="custom-control custom-switch">
                            <input type="checkbox" class="custom-control-input" name="config_stripe_client_pays_fees" <?php if ($config_stripe_client_pays_fees == 1) { echo "checked"; } ?> value="1" id="clientPaysFeesSwitch">
                            <label class="custom-control-label" for="clientPaysFeesSwitch">Enable</label>
                    </div>


                <?php } ?>

                <hr>

                <button type="submit" name="edit_online_payment_settings" class="btn btn-primary text-bold"><i class="fas fa-check mr-2"></i>Save</button>

            </form>
        </div>
    </div>

<?php
require_once "footer.php";

