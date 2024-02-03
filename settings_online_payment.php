<?php

require_once "inc_all_settings.php";


?>

<div class="card card-dark">
    <div class="card-header py-3">
        <h3 class="card-title"><i class="fas fa-fw fa-credit-card mr-2"></i>Online Payment</h3>
    </div>
    <div class="card-body">
        <form action="post.php" method="post" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?>">
            <input type="hidden" name="config_stripe_client_pays_fees" value="0">

            <div class="form-group">
                <div class="custom-control custom-switch">
                    <input type="checkbox" class="custom-control-input" name="config_stripe_enable" <?php if ($config_stripe_enable == 1) { echo "checked"; } ?> value="1" id="enableStripeSwitch">
                    <label class="custom-control-label" for="enableStripeSwitch">Enable Stripe</label>
                </div>
            </div>

            <div class="<?php if ($config_stripe_enable == 0) { echo "d-none"; } ?>">

                <div class="form-group">
                    <label>Publishable key</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-eye"></i></span>
                        </div>
                        <input type="text" class="form-control" name="config_stripe_publishable" placeholder="Stripe Publishable API Key (pk_...)" value="<?php echo nullable_htmlentities($config_stripe_publishable); ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label>Secret key</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-lock"></i></span>
                        </div>
                        <input type="text" class="form-control" name="config_stripe_secret" placeholder="Stripe Secret API Key (sk_...)" value="<?php echo nullable_htmlentities($config_stripe_secret); ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label>Expense / Income Account</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fas fa-fw fa-piggy-bank"></i></span>
                        </div>
                        <select class="form-control select2" name="config_stripe_account">
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
                    <label>Percentage Fee</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-percent"></i></span>
                        </div>
                        <input type="text" class="form-control" inputmode="numeric" pattern="[0-9]*\.?[0-9]{0,2}" name="config_stripe_percentage_fee" placeholder="Enter Percentage" value="<?php echo $config_stripe_percentage_fee * 100; ?>">
                    </div>
                    <small class="form-text text-muted">Please click <a href="https://stripe.com/pricing" target="_blank">here <i class="fas fa-fw fa-external-link-alt"></i></a> for the latest Stripe Fees.</small>
                </div>

                <div class="form-group">
                    <label>Flat Fee</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-shopping-cart"></i></span>
                        </div>
                        <input type="text" class="form-control" inputmode="numeric" pattern="[0-9]*\.?[0-9]{0,2}" name="config_stripe_flat_fee" placeholder="0.030" value="<?php echo number_format($config_stripe_flat_fee, 2, '.', ''); ?>">
                    </div>
                    <small class="form-text text-muted">Please click <a href="https://stripe.com/pricing" target="_blank">here <i class="fas fa-fw fa-external-link-alt"></i></a> for the latest Stripe Fees.</small>
                </div>

                <div class="form-group">
                    <div class="custom-control custom-switch">
                        <input type="checkbox" class="custom-control-input" name="config_stripe_client_pays_fees" <?php if ($config_stripe_client_pays_fees == 1) { echo "checked"; } ?> value="1" id="clientPaysFeesSwitch">
                        <label class="custom-control-label" for="clientPaysFeesSwitch">Enable Client Pays Fees</label>
                        <small class="form-text text-muted">Note: It is illegal to pass payment gateway fees in certain countries, states and provinces. Please check with your local laws click <a href="https://support.stripe.com/questions/passing-the-stripe-fee-on-to-customers" target="_blank">here <i class="fas fa-fw fa-external-link-alt"></i></a> for more details.</small>
                    </div>
                </div>

                <div class="<?php if ($config_stripe_client_pays_fees == 1) { echo "d-none"; } ?>">

                    <div class="form-group">
                        <label>Expense Vendor</label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fa fa-fw fa-building"></i></span>
                            </div>
                            <select class="form-control select2" name="config_stripe_expense_vendor">
                                <option value="">- Do not Enable Account Expensure -</option>
                                <?php

                                $sql_select = mysqli_query($mysqli, "SELECT vendor_id, vendor_name FROM vendors WHERE vendor_client_id = 0 AND vendor_template = 0 AND vendor_archived_at IS NULL ORDER BY vendor_name ASC");
                                while ($row = mysqli_fetch_array($sql_select)) {
                                    $vendor_id = intval($row['vendor_id']);
                                    $vendor_name = nullable_htmlentities($row['vendor_name']);
                                    ?>
                                    <option <?php if ($config_stripe_expense_vendor == $vendor_id) { ?> selected <?php } ?> value="<?php echo $vendor_id; ?>"><?php echo $vendor_name; ?></option>
                                    <?php
                                }

                                ?>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Expense Category</label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fa fa-fw fa-list"></i></span>
                            </div>
                            <select class="form-control select2" name="config_stripe_expense_category">
                                <option value="">- Do not Enable Account Expensure -</option>
                                <?php

                                $sql_select = mysqli_query($mysqli, "SELECT category_id, category_name FROM categories WHERE category_type = 'Expense' AND category_archived_at IS NULL ORDER BY category_name ASC");
                                while ($row = mysqli_fetch_array($sql_select)) {
                                    $category_id = intval($row['category_id']);
                                    $category_name = nullable_htmlentities($row['category_name']);
                                    ?>
                                    <option <?php if ($config_stripe_expense_category == $category_id) { ?> selected <?php } ?> value="<?php echo $category_id; ?>"><?php echo $category_name; ?></option>
                                    <?php
                                }

                                ?>
                            </select>
                        </div>
                    </div>

                </div>

            </div>

            <hr>

            <button type="submit" name="edit_online_payment_settings" class="btn btn-primary text-bold"><i class="fas fa-check mr-2"></i>Save</button>

        </form>
    </div>
</div>

<?php
require_once "footer.php";

