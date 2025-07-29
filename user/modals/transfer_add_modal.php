<div class="modal" id="addTransferModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-dark">
                <h5 class="modal-title"><i class="fas fa-fw fa-exchange-alt mr-2"></i>Transfering Funds</h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form action="post.php" method="post" autocomplete="off">
                <div class="modal-body">

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
                                <input type="text" class="form-control" inputmode="numeric" pattern="[0-9]*\.?[0-9]{0,2}" name="amount" placeholder="0.00" required>
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

                                $sql = mysqli_query($mysqli, "SELECT * FROM accounts WHERE account_archived_at IS NULL ORDER BY account_name ASC");
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
                                    <option <?php if ($config_default_transfer_from_account == $account_id) { echo "selected"; } ?> value="<?php echo $account_id; ?>"><?php echo $account_name; ?> [$<?php echo number_format($balance, 2); ?>]</option>

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

                                $sql = mysqli_query($mysqli, "SELECT * FROM accounts WHERE account_archived_at IS NULL ORDER BY account_name ASC");
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
                                    <option <?php if ($config_default_transfer_to_account == $account_id) { echo "selected"; } ?> value="<?php echo $account_id; ?>"><?php echo $account_name; ?> [$<?php echo number_format($balance, 2); ?>]</option>

                                <?php } ?>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <textarea class="form-control" rows="5" name="notes" id="transferNotes" placeholder="Enter some notes"></textarea>
                    </div>

                    <div class="form-group">
                        <div class="input-group">
                            <select class="form-control" id="paymentSelect">
                                <option value="">Select a Payment to Add to Notes</option>
                                <?php

                                $sql = mysqli_query($mysqli, "SELECT client_name, payment_method, payment_reference, payment_amount FROM payments
                                    LEFT JOIN invoices ON payment_invoice_id = invoice_id
                                    LEFT JOIN clients ON invoice_client_id = client_id
                                    ORDER BY payment_id DESC LIMIT 25
                                ");
                                while ($row = mysqli_fetch_array($sql)) {
                                    $client_name = nullable_htmlentities($row['client_name']);
                                    $payment_method = nullable_htmlentities($row['payment_method']);
                                    $payment_reference = nullable_htmlentities($row['payment_reference']);
                                    $payment_amount = floatval($row['payment_amount']);

                                    ?>
                                    <option><?php echo "$client_name - $payment_method $payment_reference - " . numfmt_format_currency($currency_format, $payment_amount, $session_company_currency); ?></option>

                                <?php } ?>

                            </select>
                            <div class="input-group-append">
                                <button type="button" class="btn btn-secondary" onclick="addOptionToTextbox()"><i class="fas fa-fw fa-plus"></i></button>
                            </div>
                        </div>
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

                                $sql = mysqli_query($mysqli, "SELECT * FROM payment_methods WHERE payment_method_provider_id = 0 ORDER BY payment_method_name ASC");
                                while ($row = mysqli_fetch_array($sql)) {
                                    $payment_method_name = nullable_htmlentities($row['payment_method_name']);
                                ?>
                                    <option><?php echo $payment_method_name; ?></option>

                                <?php
                                }
                                ?>
                            </select>
                        </div>
                    </div>

                </div>

                <div class="modal-footer">
                    <button type="submit" name="add_transfer" class="btn btn-primary text-bold"><i class="fas fa-check mr-2"></i>Transfer</button>
                    <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fa fa-times mr-2"></i>Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    function addOptionToTextbox() {
      var selectElement = document.getElementById("paymentSelect");
      var selectedOption = selectElement.options[selectElement.selectedIndex];
      
      var textboxElement = document.getElementById("transferNotes");
      textboxElement.value += selectedOption.value + "\n";
    }
</script>
