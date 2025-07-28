<div class="modal" id="bulkEditAccountModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content bg-dark">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fa fa-fw fa-piggy-bank mr-2"></i>Bulk Set Account</h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body bg-white">

                <div class="form-group">
                    <label>Account <strong class="text-danger">*</strong></label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-piggy-bank"></i></span>
                        </div>
                        <select class="form-control select2" name="bulk_account_id">
                            <?php

                            $sql = mysqli_query($mysqli, "SELECT account_id, account_name, opening_balance FROM accounts WHERE account_archived_at IS NULL ORDER BY account_name ASC");
                            while ($row = mysqli_fetch_array($sql)) {
                                $account_id = intval($row['account_id']);
                                $account_name = nullable_htmlentities($row['account_name']);
                                $opening_balance = floatval($row['opening_balance']);

                                $sql_payments = mysqli_query($mysqli, "SELECT SUM(payment_amount) AS total_payments FROM payments WHERE payment_account_id = $account_id");
                                $row = mysqli_fetch_array($sql_payments);
                                $total_payments = floatval($row['total_payments']);

                                $sql_revenues = mysqli_query($mysqli, "SELECT SUM(revenue_amount) AS total_revenues FROM revenues WHERE revenue_account_id = $account_id");
                                $row = mysqli_fetch_array($sql_revenues);
                                $total_revenues = floatval($row['total_revenues']);

                                $sql_expenses = mysqli_query($mysqli, "SELECT SUM(expense_amount) AS total_expenses FROM expenses WHERE expense_account_id = $account_id");
                                $row = mysqli_fetch_array($sql_expenses);
                                $total_expenses = floatval($row['total_expenses']);

                                $balance = $opening_balance + $total_payments + $total_revenues - $total_expenses;

                                ?>
                                <option <?php if ($config_default_expense_account == $account_id) { echo "selected"; } ?> value="<?php echo $account_id; ?>"><div class="float-left"><?php echo $account_name; ?></div><div class="float-right"> [$<?php echo number_format($balance, 2); ?>]</div></option>

                                <?php
                            }
                            ?>
                        </select>
                    </div>
                </div>

            </div>
            <div class="modal-footer bg-white">
                <button type="submit" name="bulk_edit_expense_account" class="btn btn-primary text-bold"><i class="fa fa-fw fa-check mr-2"></i>Set</button>
                <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fa fa-times mr-2"></i>Cancel</button>
            </div>
        </div>
    </div>
</div>
