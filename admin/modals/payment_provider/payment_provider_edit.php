<?php

require_once '../../../includes/modal_header.php';

$provider_id = intval($_GET['id']);

$sql = mysqli_query($mysqli, "SELECT * FROM payment_providers WHERE payment_provider_id = $provider_id LIMIT 1");

$row = mysqli_fetch_assoc($sql);
$provider_name = escapeHtml($row['payment_provider_name']);
$public_key = escapeHtml($row['payment_provider_public_key']);
$private_key = escapeHtml($row['payment_provider_private_key']);
$account_id = intval($row['payment_provider_account']);
$threshold = floatval($row['payment_provider_threshold']);
$vendor_id = intval($row['payment_provider_expense_vendor']);
$category_id = intval($row['payment_provider_expense_category']);

ob_start();

?>

<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fa fa-fw fa-credit-card mr-2"></i>Editing: <strong><?= $provider_name ?></strong></h5>
    <button type="button" class="close text-white" data-dismiss="modal">
        <span>&times;</span>
    </button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="provider_id" value="<?= $provider_id ?>">

    <div class="modal-body">

        <ul class="nav nav-pills nav-justified mb-3">
            <li class="nav-item">
                <a class="nav-link active" data-toggle="pill" href="#pills-details">Details</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-toggle="pill" href="#pills-expense">Expense</a>
            </li>
        </ul>

        <hr>

        <div class="tab-content">

            <div class="tab-pane fade show active" id="pills-details">

                <div class="form-group">
                    <label>Publishable key <strong class="text-danger">*</strong></label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-eye"></i></span>
                        </div>
                        <input type="text" class="form-control" name="public_key" placeholder="Publishable API Key (pk_...)" value="<?= $public_key ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label>Secret key <strong class="text-danger">*</strong></label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-key"></i></span>
                        </div>
                        <input type="text" class="form-control" name="private_key" placeholder="Secret API Key (sk_...)" value="<?= $private_key ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label>Income / Expense Account <strong class="text-danger">*</strong></label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-piggy-bank"></i></span>
                        </div>
                        <select class="form-control select2" name="account" required>
                            <option value="">- Select an Account -</option>
                            <?php

                            $sql = mysqli_query($mysqli, "SELECT account_id, account_name FROM accounts WHERE account_archived_at IS NULL ORDER BY account_name ASC");
                            while ($row = mysqli_fetch_assoc($sql)) {
                                $account_id_select = intval($row['account_id']);
                                $account_name = escapeHtml($row['account_name']);
                                ?>
                                <option <?php if ($account_id === $account_id_select) { echo "selected"; } ?> value="<?= $account_id_select ?>"><?= $account_name ?></option>

                                <?php
                            }
                            ?>
                        </select>
                    </div>
                    <small class="form-text text-muted">Should have a seperate account created off the payment provider's name e.g. Stripe</small>
                </div>

                <div class="form-group">
                    <label>Threshold</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-shopping-cart"></i></span>
                        </div>
                        <input type="text" class="form-control" inputmode="decimal" pattern="[0-9]*\.?[0-9]{0,2}" name="threshold" placeholder="1000.00" value="<?php echo $threshold; ?>">
                    </div>
                    <small class="form-text text-muted">Will not show as an option at Checkout if above this number</small>
                </div>

            </div>

            <div class="tab-pane fade" id="pills-expense">

                <div class="alert alert-info text-center">
                    Payment Processing Fee Expenses get reconciled nighly via the cron
                </div>

                <div class="form-group">
                    <label>Payment Provider Vendor <strong class="text-danger">*</strong></label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-building"></i></span>
                        </div>
                        <select class="form-control select2" name="expense_vendor" required>
                            <option value="0">Expense Disabled</option>
                            <?php

                            $sql = mysqli_query($mysqli, "SELECT vendor_id, vendor_name FROM vendors WHERE vendor_client_id = 0 AND vendor_archived_at IS NULL ORDER BY vendor_name ASC");
                            while ($row = mysqli_fetch_assoc($sql)) {
                                $vendor_id_select = intval($row['vendor_id']);
                                $vendor_name = escapeHtml($row['vendor_name']);
                                ?>
                                <option <?php if ($vendor_id === $vendor_id_select) { echo "selected"; } ?>
                                    value="<?= $vendor_id_select ?>"><?= $vendor_name ?>
                                </option>

                                <?php
                            }
                            ?>
                        </select>
                    </div>
                    <small class="form-text text-muted">Payment Privider name e.g. Stripe</small>
                </div>

                <div class="form-group">
                    <label>Expense Category <strong class="text-danger">*</strong></label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-list"></i></span>
                        </div>
                        <select class="form-control select2" name="expense_category" required>
                            <option value="">- Select a Category -</option>
                            <?php

                            $sql_category = mysqli_query($mysqli, "SELECT category_id, category_name FROM categories WHERE category_type = 'Expense' AND category_archived_at IS NULL ORDER BY category_name ASC");
                            while ($row = mysqli_fetch_assoc($sql_category)) {
                                $category_id_select = intval($row['category_id']);
                                $category_name = escapeHtml($row['category_name']);
                                ?>
                                <option <?php if ($category_id === $category_id_select) { echo "selected"; } ?> value="<?= $category_id_select ?>"><?= $category_name ?></option>

                                <?php
                            }
                            ?>
                        </select>
                        <div class="input-group-append">
                            <button class="btn btn-secondary ajax-modal" type="button"
                                data-modal-url="../admin/modals/category/category_add.php?category=Expense">
                                <i class="fas fa-plus"></i>
                            </button>
                        </div>
                    </div>
                    <small class="form-text text-muted">Processing Fee, Credit Card Fee etc</small>
                </div>
            </div>
        </div>
    </div>
    <div class="modal-footer">
        <button type="submit" name="edit_payment_provider" class="btn btn-primary text-bold"><i class="fa fa-check mr-2"></i>Save</button>
        <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fa fa-times mr-2"></i>Cancel</button>
    </div>
</form>

<?php

require_once '../../../includes/modal_footer.php';
