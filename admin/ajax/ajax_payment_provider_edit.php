<?php

require_once '../../includes/modal_header.php';

$provider_id = intval($_GET['id']);

$sql = mysqli_query($mysqli, "SELECT * FROM payment_providers WHERE payment_provider_id = $provider_id LIMIT 1"
);

$row = mysqli_fetch_array($sql);
$provider_name = nullable_htmlentities($row['payment_provider_name']);
$public_key = nullable_htmlentities($row['payment_provider_public_key']);
$private_key = nullable_htmlentities($row['payment_provider_private_key']);
$account_id = nullable_htmlentities($row['payment_provider_account_']);
$threshold = floatval($row['payment_provider_treshold']);
$vendor_id = nullable_htmlentities($row['payment_provider_expense_vendor']);
$category_id = nullable_htmlentities($row['payment_provider_expense_category']);
$percent_fee = floatval($row['payment_provider_expense_percentage_fee']) * 100;
$flat_fee = floatval($row['payment_provider_expense_flat_fee']);

// Generate the HTML form content using output buffering.
ob_start();
?>
<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fa fa-fw fa-credit-card mr-2"></i>Editing: <strong><?php echo $provider_name; ?></strong></h5>
    <button type="button" class="close text-white" data-dismiss="modal">
        <span>&times;</span>
    </button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="provider_id" value="<?php echo $provider_id; ?>">

    <div class="modal-body">

        <div class="form-group">
            <label>Publishable key <strong class="text-danger">*</strong></label>
            <div class="input-group">
                <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-eye"></i></span>
                </div>
                <input type="text" class="form-control" name="public_key" placeholder="Publishable API Key (pk_...)" value="<?php echo $public_key; ?>">
            </div>
        </div>

        <div class="form-group">
            <label>Secret key <strong class="text-danger">*</strong></label>
            <div class="input-group">
                <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-key"></i></span>
                </div>
                <input type="text" class="form-control" name="private_key" placeholder="Secret API Key (sk_...)" value="<?php echo $private_key; ?>">
            </div>
        </div>

        <div class="form-group">
            <label>Threshold</label>
            <div class="input-group">
                <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-shopping-cart"></i></span>
                </div>
                <input type="text" class="form-control" inputmode="numeric" pattern="[0-9]*\.?[0-9]{0,2}" name="Threshold" placeholder="1000.00" value="<?php echo $threshold; ?>">
            </div>
            <small class="form-text text-muted">Will not show as an option at Checkout if above this number</small>
        </div>

        <hr>

        <div class="form-group">
            <div class="custom-control custom-switch">
                <input type="checkbox" class="custom-control-input" name="enable_expense" <?php if ($vendor_id) { echo "checked";  } ?> value="1" id="enableEditExpenseSwitch">
                <label class="custom-control-label" for="enableEditExpenseSwitch">Enable Expense</label>
            </div>
            <small>(Category: Payment Processing -- Vendor: <?php echo $provider_name; ?></small>
        </div>

        <div class="form-group">
            <label>Percentage Fee to expense</label>
            <div class="input-group">
                <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-percent"></i></span>
                </div>
                <input type="text" class="form-control" inputmode="numeric" pattern="[0-9]*\.?[0-9]{0,2}" name="percentage_fee" value="<?php echo $percent_fee; ?>" placeholder="Enter Percentage">
            </div>
            <small class="form-text text-muted">See <a href="https://stripe.com/pricing" target="_blank">here <i class="fas fa-fw fa-external-link-alt"></i></a> for the latest Stripe Fees.</small>
        </div>

        <div class="form-group">
            <label>Flat Fee to expense</label>
            <div class="input-group">
                <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-shopping-cart"></i></span>
                </div>
                <input type="text" class="form-control" inputmode="numeric" pattern="[0-9]*\.?[0-9]{0,3}" name="flat_fee" value="<?php echo $flat_fee; ?>" placeholder="0.030">
            </div>
            <small class="form-text text-muted">See <a href="https://stripe.com/pricing" target="_blank">here <i class="fas fa-fw fa-external-link-alt"></i></a> for the latest Stripe Fees.</small>
        </div>

    </div>
    <div class="modal-footer">
        <button type="submit" name="edit_payment_provider" class="btn btn-primary text-bold"><i class="fa fa-check mr-2"></i>Save</button>
        <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fa fa-times mr-2"></i>Cancel</button>
    </div>
</form>

<?php

require_once '../../includes/modal_footer.php';
