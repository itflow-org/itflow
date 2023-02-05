<div class="modal" id="addTransferModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content bg-dark">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fa fa-fw fa-exchange-alt"></i> Transfering Funds</h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form action="post.php" method="post" autocomplete="off">
                <div class="modal-body bg-white">

                    <div class="form-row">

                        <div class="form-group col">
                            <label>Date <strong class="text-danger">*</strong></label>
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text"><i class="fa fa-fw fa-calendar"></i></span>
                                </div>
                                <input type="date" class="form-control" name="date" max="2999-12-31" value="<?php echo date("Y-m-d"); ?>" required>
                            </div>
                        </div>

                        <div class="form-group col">
                            <label>Amount <strong class="text-danger">*</strong></label>
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text"><i class="fa fa-fw fa-dollar-sign"></i></span>
                                </div>
                                <input type="number" class="form-control" step="0.01" min="0" name="amount" placeholder="Amount to transfer" required autofocus>
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
                                <option value="">- Account From -</option>
                                <?php

                                $sql = mysqli_query($mysqli,"SELECT * FROM accounts WHERE account_archived_at IS NULL AND company_id = $session_company_id ORDER BY account_name ASC");
                                while ($row = mysqli_fetch_array($sql)) {
                                    $account_id = $row['account_id'];
                                    $account_name = htmlentities($row['account_name']);
                                    $opening_balance = floatval($row['opening_balance']);

                                    $sql_payments = mysqli_query($mysqli,"SELECT SUM(payment_amount) AS total_payments FROM payments WHERE payment_account_id = $account_id");
                                    $row = mysqli_fetch_array($sql_payments);
                                    $total_payments = $row['total_payments'];

                                    $sql_revenues = mysqli_query($mysqli,"SELECT SUM(revenue_amount) AS total_revenues FROM revenues WHERE revenue_account_id = $account_id");
                                    $row = mysqli_fetch_array($sql_revenues);
                                    $total_revenues = $row['total_revenues'];

                                    $sql_expenses = mysqli_query($mysqli,"SELECT SUM(expense_amount) AS total_expenses FROM expenses WHERE expense_account_id = $account_id");
                                    $row = mysqli_fetch_array($sql_expenses);
                                    $total_expenses = $row['total_expenses'];

                                    $balance = $opening_balance + $total_payments + $total_revenues - $total_expenses;

                                    ?>
                                    <option <?php if ($config_default_transfer_from_account == $account_id) { echo "selected"; } ?> value="<?php echo $account_id; ?>"><?php echo $account_name; ?> [$<?php echo number_format($balance,2); ?>]</option>

                                <?php } ?>

                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fa fa-fw fa-arrow-right"></i></span>
                            </div>
                            <select class="form-control select2" name="account_to" required>
                                <option value="">- Account To -</option>
                                <?php

                                $sql = mysqli_query($mysqli,"SELECT * FROM accounts WHERE account_archived_at IS NULL AND company_id = $session_company_id ORDER BY account_name ASC");
                                while ($row = mysqli_fetch_array($sql)) {
                                    $account_id = $row['account_id'];
                                    $account_name = htmlentities($row['account_name']);
                                    $opening_balance = floatval($row['opening_balance']);

                                    $sql_payments = mysqli_query($mysqli,"SELECT SUM(payment_amount) AS total_payments FROM payments WHERE payment_account_id = $account_id");
                                    $row = mysqli_fetch_array($sql_payments);
                                    $total_payments = $row['total_payments'];

                                    $sql_revenues = mysqli_query($mysqli,"SELECT SUM(revenue_amount) AS total_revenues FROM revenues WHERE revenue_account_id = $account_id");
                                    $row = mysqli_fetch_array($sql_revenues);
                                    $total_revenues = $row['total_revenues'];

                                    $sql_expenses = mysqli_query($mysqli,"SELECT SUM(expense_amount) AS total_expenses FROM expenses WHERE expense_account_id = $account_id");
                                    $row = mysqli_fetch_array($sql_expenses);
                                    $total_expenses = $row['total_expenses'];

                                    $balance = $opening_balance + $total_payments + $total_revenues - $total_expenses;

                                    ?>
                                    <option <?php if ($config_default_transfer_to_account == $account_id) { echo "selected"; } ?> value="<?php echo $account_id; ?>"><?php echo $account_name; ?> [$<?php echo number_format($balance,2); ?>]</option>

                                <?php } ?>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <textarea class="form-control" rows="5" name="notes" placeholder="Enter some notes"></textarea>
                    </div>

                </div>

                <div class="modal-footer bg-white">
                    <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_transfer" class="btn btn-primary text-bold"><i class="fa fa-fw fa-check"></i> Transfer</button>
                </div>
            </form>
        </div>
    </div>
</div>
