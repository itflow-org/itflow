<div class="modal" id="editTransferModal<?php echo $transfer_id; ?>" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content bg-dark">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-fw fa-exchange-alt mr-2"></i>Editing Transfer</h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form action="post.php" method="post" autocomplete="off">
                <input type="hidden" name="transfer_id" value="<?php echo $transfer_id; ?>">
                <input type="hidden" name="expense_id" value="<?php echo $expense_id; ?>">
                <input type="hidden" name="revenue_id" value="<?php echo $revenue_id; ?>">

                <div class="modal-body bg-white">

                    <div class="form-row">

                        <div class="form-group col-sm">
                            <label>Date <strong class="text-danger">*</strong></label>
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text"><i class="fa fa-fw fa-calendar"></i></span>
                                </div>
                                <input type="date" class="form-control" name="date" max="2999-12-31" value="<?php echo $transfer_date; ?>" required>
                            </div>
                        </div>

                        <div class="form-group col-sm">
                            <label>Amount <strong class="text-danger">*</strong></label>
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text"><i class="fa fa-fw fa-dollar-sign"></i></span>
                                </div>
                                <input type="text" class="form-control" inputmode="numeric" pattern="[0-9]*\.?[0-9]{0,2}" name="amount" placeholder="0.00" value="<?php echo number_format($transfer_amount, 2, '.', ''); ?>" required>
                            </div>
                        </div>

                    </div>

                    <div class="form-group">
                        <label>Transfer <strong class="text-danger">*</strong></label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fa fa-fw fa-piggy-bank"></i></span>
                            </div>
                            <select class="form-control select2" name="account_from" required>
                                <?php

                                $sql_accounts = mysqli_query($mysqli, "SELECT * FROM accounts WHERE (account_archived_at > '$transfer_created_at' OR account_archived_at IS NULL) ORDER BY account_archived_at ASC, account_name ASC");
                                while ($row = mysqli_fetch_array($sql_accounts)) {
                                    $account_id_select = intval($row['account_id']);
                                    $account_name_select = nullable_htmlentities($row['account_name']);
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
                                    <option <?php if ($transfer_account_from == $account_id_select) { echo "selected"; } ?> value="<?php echo $account_id_select; ?>"><?php echo "$account_archived_display$account_name_select"; ?> [$<?php echo number_format($balance, 2); ?>]</option>
                                    <?php
                                }

                                ?>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fa fa-fw fa-arrow-right"></i></span>
                            </div>
                            <select class="form-control select2" name="account_to" required>
                                <?php

                                $sql2 = mysqli_query($mysqli, "SELECT * FROM accounts WHERE (account_archived_at > '$transfer_created_at' OR account_archived_at IS NULL) ORDER BY account_archived_at ASC, account_name ASC");
                                while ($row = mysqli_fetch_array($sql2)) {
                                    $account_id2 = intval($row['account_id']);
                                    $account_name = nullable_htmlentities($row['account_name']);
                                    $opening_balance = floatval($row['opening_balance']);
                                    $account_archived_at = nullable_htmlentities($row['account_archived_at']);
                                    if (empty($account_archived_at)) {
                                        $account_archived_display = "";
                                    } else {
                                        $account_archived_display = "Archived - ";
                                    }

                                    $sql_payments = mysqli_query($mysqli, "SELECT SUM(payment_amount) AS total_payments FROM payments WHERE payment_account_id = $account_id2");
                                    $row = mysqli_fetch_array($sql_payments);
                                    $total_payments = floatval($row['total_payments']);

                                    $sql_revenues = mysqli_query($mysqli, "SELECT SUM(revenue_amount) AS total_revenues FROM revenues WHERE revenue_account_id = $account_id2");
                                    $row = mysqli_fetch_array($sql_revenues);
                                    $total_revenues = floatval($row['total_revenues']);

                                    $sql_expenses = mysqli_query($mysqli, "SELECT SUM(expense_amount) AS total_expenses FROM expenses WHERE expense_account_id = $account_id2");
                                    $row = mysqli_fetch_array($sql_expenses);
                                    $total_expenses = floatval($row['total_expenses']);

                                    $balance = $opening_balance + $total_payments + $total_revenues - $total_expenses;

                                    ?>
                                    <option <?php if ($transfer_account_to == $account_id2) { echo "selected"; } ?> value="<?php echo $account_id2; ?>"><?php echo "$account_archived_display$account_name"; ?> [$<?php echo number_format($balance, 2); ?>]</option>
                                    <?php
                                }

                                ?>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <textarea class="form-control" rows="5" name="notes" placeholder="Enter some notes"><?php echo $transfer_notes; ?></textarea>
                    </div>

                    <div class="form-group">
                        <label>Transfer Method</label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fa fa-fw fa-money-check-alt"></i></span>
                            </div>
                            <select class="form-control select2" name="transfer_method">
                                <option value="">- Method of Transfer -</option>
                                <?php

                                $sql_transfer_method_select = mysqli_query($mysqli, "SELECT * FROM categories WHERE category_type = 'Payment Method' AND category_archived_at IS NULL ORDER BY category_name ASC");
                                while ($row = mysqli_fetch_array($sql_transfer_method_select)) {
                                    $category_name_select = nullable_htmlentities($row['category_name']);
                                ?>
                                    <option <?php if($transfer_method == $category_name_select) { echo "selected"; } ?> ><?php echo $category_name_select; ?></option>

                                <?php
                                }
                                ?>
                            </select>
                        </div>
                    </div>

                </div>

                <div class="modal-footer bg-white">
                    <button type="submit" name="edit_transfer" class="btn btn-primary text-bold"><i class="fas fa-check mr-2"></i>Save</button>
                    <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fa fa-times mr-2"></i>Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>
