<div class="modal" id="addRecurringPaymentModal<?php echo $recurring_id; ?>" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content bg-dark">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fa fa-fw fa-credit-card mr-2"></i><?php echo "$recurring_prefix$recurring_number"; ?>: Create Recurring Payment</h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form action="post.php" method="post" autocomplete="off">
                <input type="hidden" name="recurring_id" value="<?php echo $recurring_id; ?>">
                <input type="hidden" name="currency_code" value="<?php echo $recurring_currency_code; ?>">
                <div class="modal-body bg-white">

                    <div class="form-group">
                        <label>Account <strong class="text-danger">*</strong></label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fa fa-fw fa-piggy-bank"></i></span>
                            </div>
                            <select class="form-control select2" name="account" required>
                                <option value="">- Select an Account -</option>
                                <?php

                                $sql_account_select = mysqli_query($mysqli, "SELECT * FROM accounts WHERE account_archived_at IS NULL ORDER BY account_name ASC");
                                while ($row = mysqli_fetch_array($sql_account_select)) {
                                    $account_id_select = intval($row['account_id']);
                                    $account_name_select = nullable_htmlentities($row['account_name']);

                                ?>
                                    <option <?php if ($config_default_payment_account == $account_id_select) { echo "selected"; } ?>
                                        value="<?php echo $account_id_select; ?>">
                                        <?php echo $account_name_select; ?>
                                    </option>

                                <?php
                                }
                                ?>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Payment Method <strong class="text-danger">*</strong></label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fa fa-fw fa-money-check-alt"></i></span>
                            </div>
                            <select class="form-control select2" name="payment_method" required>
                                <option value="">- Method of Payment -</option>
                                <?php

                                $sql_payment_method_select = mysqli_query($mysqli, "SELECT * FROM categories WHERE category_type = 'Payment Method' AND category_archived_at IS NULL ORDER BY category_name ASC");
                                while ($row = mysqli_fetch_array($sql_payment_method_select)) {
                                    $category_name_select = nullable_htmlentities($row['category_name']);
                                ?>
                                    <option <?php if ($config_default_payment_method == $category_name_select) {
                                                echo "selected";
                                            } ?>><?php echo $category_name_select; ?></option>

                                <?php
                                }
                                ?>
                            </select>
                        </div>
                    </div>

                </div>

                <div class="modal-footer bg-white">
                    <button type="submit" name="add_recurring_payment" class="btn btn-primary text-bold"><i class="fas fa-check mr-2"></i>Create</button>
                    <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fas fa-times mr-2"></i>Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>