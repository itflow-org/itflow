<div class="modal" id="editRevenueModal<?php echo $revenue_id; ?>" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content bg-dark">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-fw fa-hand-holding-usd mr-2"></i>Edit Revenue</h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form action="post.php" method="post" autocomplete="off">
                <input type="hidden" name="revenue_id" value="<?php echo $revenue_id; ?>">
                <div class="modal-body bg-white">

                    <div class="form-row">

                        <div class="form-group col-md">
                            <label>Date <strong class="text-danger">*</strong></label>
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text"><i class="fa fa-fw fa-calendar"></i></span>
                                </div>
                                <input type="date" class="form-control" name="date" max="2999-12-31" value="<?php echo $revenue_date; ?>" required>
                            </div>
                        </div>

                        <div class="form-group col-md">
                            <label>Currency <strong class="text-danger">*</strong></label>
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text"><i class="fa fa-fw fa-money-bill"></i></span>
                                </div>
                                <select class="form-control select2" name="currency_code" required>
                                    <option value="">- Currency -</option>
                                    <?php foreach($currencies_array as $currency_code => $currency_name) { ?>
                                        <option <?php if ($revenue_currency_code == $currency_code) { echo "selected"; } ?> value="<?php echo $currency_code; ?>"><?php echo "$currency_code - $currency_name"; ?></option>
                                    <?php } ?>
                                </select>
                            </div>
                        </div>

                        <div class="form-group col-md">
                            <label>Amount <strong class="text-danger">*</strong></label>
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text"><i class="fa fa-fw fa-dollar-sign"></i></span>
                                </div>
                                <input type="text" class="form-control" inputmode="numeric" pattern="[0-9]*\.?[0-9]{0,2}" name="amount" value="<?php echo number_format($revenue_amount, 2, '.', ''); ?>" placeholder="0.00" required>
                            </div>
                        </div>

                    </div>

                    <div class="form-row">

                        <div class="form-group col-md">
                            <label>Account <strong class="text-danger">*</strong></label>
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text"><i class="fa fa-fw fa-piggy-bank"></i></span>
                                </div>
                                <select class="form-control select2" name="account" required>
                                    <option value="">- Account -</option>
                                    <?php

                                    $sql_accounts = mysqli_query($mysqli, "SELECT * FROM accounts WHERE (account_archived_at > '$revenue_created_at' OR account_archived_at IS NULL) ORDER BY account_archived_at ASC, account_name ASC");
                                    while ($row = mysqli_fetch_array($sql_accounts)) {
                                        $account_id_select = intval($row['account_id']);
                                        $account_name_select = nullable_htmlentities($row['account_name']);
                                        $account_currency_code_select = nullable_htmlentities($row['account_currency_code']);
                                        $opening_balance = floatval($row['opening_balance']);
                                        $account_archived_at = nullable_htmlentities($row['account_archived_at']);
                                        if (empty($account_archived_at)) {
                                            $account_archived_display = "";
                                        } else {
                                            $account_archived_display = "Archived - ";
                                        }
                                        $sql_payments = mysqli_query($mysqli, "SELECT SUM(payment_amount) AS total_payments FROM payments WHERE payment_account_id = $account_id_select");
                                        $row = mysqli_fetch_array($sql_payments);
                                        $total_payments = floatval($row['total_payments']);

                                        $sql_revenues = mysqli_query($mysqli, "SELECT SUM(revenue_amount) AS total_revenues FROM revenues WHERE revenue_account_id = $account_id_select");
                                        $row = mysqli_fetch_array($sql_revenues);
                                        $total_revenues = floatval($row['total_revenues']);

                                        $sql_expenses = mysqli_query($mysqli, "SELECT SUM(expense_amount) AS total_expenses FROM expenses WHERE expense_account_id = $account_id_select");
                                        $row = mysqli_fetch_array($sql_expenses);
                                        $total_expenses = floatval($row['total_expenses']);

                                        $balance = $opening_balance + $total_payments + $total_revenues - $total_expenses;

                                        ?>
                                        <option <?php if ($account_id == $account_id_select) { echo "selected"; } ?> value="<?php echo $account_id_select; ?>"><?php echo $account_archived_display; ?> <?php echo $account_name_select; ?> [ <?php echo numfmt_format_currency($currency_format, $balance, $account_currency_code_select); ?> ]</option>

                                        <?php
                                    }
                                    ?>
                                </select>
                            </div>
                        </div>

                        <div class="form-group col-md">
                            <label>Category <strong class="text-danger">*</strong></label>
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text"><i class="fa fa-fw fa-money-check-alt"></i></span>
                                </div>
                                <select class="form-control select2" name="category" required>
                                    <option value="">- Category -</option>
                                    <?php

                                    $sql_category = mysqli_query($mysqli, "SELECT * FROM categories WHERE category_type = 'Income' AND (category_archived_at > '$revenue_created_at' OR category_archived_at IS NULL) ORDER BY category_name ASC");
                                    while ($row = mysqli_fetch_array($sql_category)) {
                                        $category_id_select = intval($row['category_id']);
                                        $category_name = nullable_htmlentities($row['category_name']);
                                        ?>
                                        <option <?php if ($category_id_select == $category_id) { echo "selected"; } ?> value="<?php echo $category_id_select; ?>"><?php echo $category_name; ?></option>

                                        <?php
                                    }
                                    ?>
                                </select>
                                <div class="input-group-append">
                                    <a class="btn btn-secondary" href="admin_categories.php?category=Income" target="_blank"><i class="fas fa-fw fa-plus"></i></a>
                                </div>
                            </div>
                        </div>

                    </div>

                    <div class="form-group">
                        <label>Description</label>
                        <textarea class="form-control" rows="4" name="description"><?php echo $revenue_description; ?></textarea>
                    </div>

                    <div class="form-row">

                        <div class="form-group col-md">
                            <label>Payment Method <strong class="text-danger">*</strong></label>
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text"><i class="fa fa-fw fa-money-check-alt"></i></span>
                                </div>
                                <select class="form-control select2" name="payment_method" required>
                                    <option value="">- Method of Payment -</option>
                                    <?php

                                    $sql_categories = mysqli_query($mysqli, "SELECT * FROM categories WHERE category_type = 'Payment Method' AND (category_archived_at > '$revenue_created_at' OR category_archived_at IS NULL) ORDER BY category_name ASC");
                                    while ($row = mysqli_fetch_array($sql_categories)) {
                                        $category_name_select = nullable_htmlentities($row['category_name']);
                                        ?>
                                        <option <?php if ($revenue_payment_method == $category_name_select) { echo "selected"; } ?>><?php echo "$category_name_select"; ?></option>

                                        <?php
                                    }
                                    ?>
                                </select>
                            </div>
                        </div>

                        <div class="form-group col-md">
                            <label>Reference</label>
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text"><i class="fa fa-fw fa-file-alt"></i></span>
                                </div>
                                <input type="text" class="form-control" name="reference" placeholder="Check #, trans #, etc" value="<?php echo $revenue_reference; ?>">
                            </div>
                        </div>

                    </div>

                </div>
                <div class="modal-footer bg-white">
                    <button type="submit" name="edit_revenue" class="btn btn-primary text-bold"><i class="fas fa-check mr-2"></i>Save</button>
                    <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fas fa-times mr-2"></i>Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>
