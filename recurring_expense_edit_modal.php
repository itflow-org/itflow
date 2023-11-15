<div class="modal" id="editRecurringExpenseModal<?php echo $recurring_expense_id; ?>" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content bg-dark">
            <div class="modal-header">
                <h5 class="modal-title text-white"><i class="fa fa-fw fa-clock mr-2"></i>Editing recurring expense</h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form action="post.php" method="post" autocomplete="off">
                <div class="modal-body bg-white">
                    <input type="hidden" name="recurring_expense_id" value="<?php echo $recurring_expense_id; ?>">

                    <div class="form-row">

                        <div class="form-group col-md">
                            <label>Frequency <strong class="text-danger">*</strong></label>
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text"><i class="fa fa-fw fa-sync-alt"></i></span>
                                </div>
                                <select class="form-control select2" name="frequency" required>
                                    <option value="1" <?php if($recurring_expense_frequency == 1) { echo "selected"; } ?>>Monthly</option>
                                    <option value="2" <?php if($recurring_expense_frequency == 2) { echo "selected"; } ?>>Annually</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-group col-md">
                            <label>Month <strong class="text-danger">*</strong></label>
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text"><i class="fa fa-fw fa-calendar"></i></span>
                                </div>
                                <select class="form-control select2" name="month" required>
                                    <option value="">- Select a Month -</option>
                                    <option value="1" <?php if($recurring_expense_month == 1) { echo "selected"; } ?>>01 - January</option>
                                    <option value="2" <?php if($recurring_expense_month == 2) { echo "selected"; } ?>>02 - February</option>
                                    <option value="3" <?php if($recurring_expense_month == 3) { echo "selected"; } ?>>03 - March</option>
                                    <option value="4" <?php if($recurring_expense_month == 4) { echo "selected"; } ?>>04 - April</option>
                                    <option value="5" <?php if($recurring_expense_month == 5) { echo "selected"; } ?>>05 - May</option>
                                    <option value="6" <?php if($recurring_expense_month == 6) { echo "selected"; } ?>>06 - June</option>
                                    <option value="7" <?php if($recurring_expense_month == 7) { echo "selected"; } ?>>07 - July</option>
                                    <option value="8" <?php if($recurring_expense_month == 8) { echo "selected"; } ?>>08 - August</option>
                                    <option value="9" <?php if($recurring_expense_month == 9) { echo "selected"; } ?>>09 - September</option>
                                    <option value="10" <?php if($recurring_expense_month == 10) { echo "selected"; } ?>>10 - October</option>
                                    <option value="11" <?php if($recurring_expense_month == 11) { echo "selected"; } ?>>11 - November</option>
                                    <option value="12" <?php if($recurring_expense_month == 12) { echo "selected"; } ?>>12 - December</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-group col-md">
                            <label>Day <strong class="text-danger">*</strong></label>
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text"><i class="fa fa-fw fa-calendar"></i></span>
                                </div>
                                <input type="text" class="form-control" inputmode="numeric" pattern="(1[0-9]|2[0-8]|[1-9])" name="day" placeholder="Enter a day (1-28)" value="<?php echo $recurring_expense_day; ?>" required>
                            </div>
                        </div>

                    </div>

                    <div class="form-row">
                        <div class="form-group col-md">
                            <label>Amount <strong class="text-danger">*</strong></label>
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text"><i class="fa fa-fw fa-dollar-sign"></i></span>
                                </div>
                                <input type="text" class="form-control" inputmode="numeric" pattern="-?[0-9]*\.?[0-9]{0,2}" name="amount" value="<?php echo number_format($recurring_expense_amount, 2, '.', ''); ?>" required>
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
                                    <?php

                                    $sql_accounts = mysqli_query($mysqli, "SELECT account_id, account_name, opening_balance, account_archived_at FROM accounts WHERE (account_archived_at > '$expense_created_at' OR account_archived_at IS NULL) ORDER BY account_archived_at ASC, account_name ASC");
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
                                        <option <?php if ($recurring_expense_account_id == $account_id_select) { ?> selected <?php } ?> value="<?php echo $account_id_select; ?>"><?php echo "$account_archived_display$account_name_select"; ?> [$<?php echo number_format($balance, 2); ?>]</option>
                                        <?php
                                    }

                                    ?>
                                </select>
                            </div>
                        </div>

                        <div class="form-group col-md">
                            <label>Vendor <strong class="text-danger">*</strong></label>
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text"><i class="fa fa-fw fa-building"></i></span>
                                </div>
                                <select class="form-control select2" name="vendor" required>
                                    <?php

                                    $sql_select = mysqli_query($mysqli, "SELECT vendor_id, vendor_name FROM vendors WHERE vendor_client_id = 0 AND vendor_template = 0 AND (vendor_archived_at > '$expense_created_at' OR vendor_archived_at IS NULL) ORDER BY vendor_name ASC");
                                    while ($row = mysqli_fetch_array($sql_select)) {
                                        $vendor_id_select = intval($row['vendor_id']);
                                        $vendor_name_select = nullable_htmlentities($row['vendor_name']);
                                        ?>
                                        <option <?php if ($recurring_expense_vendor_id == $vendor_id_select) { ?> selected <?php } ?> value="<?php echo $vendor_id_select; ?>"><?php echo $vendor_name_select; ?></option>
                                        <?php
                                    }

                                    ?>
                                </select>
                                <div class="input-group-append">
                                    <a class="btn btn-secondary" href="vendors.php" target="_blank"><i class="fas fa-fw fa-plus"></i></a>
                                </div>
                            </div>
                        </div>

                    </div>

                    <div class="form-group">
                        <label>Description <strong class="text-danger">*</strong></label>
                        <textarea class="form-control" rows="6" name="description" placeholder="Enter a description" required><?php echo $recurring_expense_description; ?></textarea>
                    </div>

                    <div class="form-group">
                        <label>Reference</label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fa fa-fw fa-file-alt"></i></span>
                            </div>
                            <input type="text" class="form-control" name="reference" placeholder="Enter a reference" value="<?php echo $recurring_expense_reference; ?>">
                        </div>
                    </div>

                    <div class="form-row">

                        <div class="form-group col-md">
                            <label>Category <strong class="text-danger">*</strong></label>
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text"><i class="fa fa-fw fa-list"></i></span>
                                </div>
                                <select class="form-control select2" name="category" required>
                                    <?php

                                    $sql_select = mysqli_query($mysqli, "SELECT category_id, category_name FROM categories WHERE category_type = 'Expense' AND (category_archived_at > '$expense_created_at' OR category_archived_at IS NULL) ORDER BY category_name ASC");
                                    while ($row = mysqli_fetch_array($sql_select)) {
                                        $category_id_select = intval($row['category_id']);
                                        $category_name_select = nullable_htmlentities($row['category_name']);
                                        ?>
                                        <option <?php if ($recurring_expense_category_id == $category_id_select) { ?> selected <?php } ?> value="<?php echo $category_id_select; ?>"><?php echo $category_name_select; ?></option>
                                        <?php
                                    }

                                    ?>
                                </select>
                                <div class="input-group-append">
                                    <a class="btn btn-secondary" href="categories.php?category=Expense" target="_blank"><i class="fas fa-fw fa-plus"></i></a>
                                </div>
                            </div>
                        </div>

                        <?php if (isset($_GET['client_id'])) { ?>
                            <input type="hidden" name="client" value="<?php echo $client_id; ?>">
                        <?php } else { ?>

                            <div class="form-group col-md">
                                <label>Client</label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fa fa-fw fa-user"></i></span>
                                    </div>
                                    <select class="form-control select2" name="client">
                                        <option value="">- Client (Optional) -</option>
                                        <?php

                                        $sql_clients = mysqli_query($mysqli, "SELECT client_id, client_name FROM clients ORDER BY client_name ASC");
                                        while ($row = mysqli_fetch_array($sql_clients)) {
                                            $client_id_select = intval($row['client_id']);
                                            $client_name_select = nullable_htmlentities($row['client_name']);
                                            ?>
                                            <option <?php if ($recurring_expense_client_id == $client_id_select) { echo "selected"; } ?> value="<?php echo $client_id_select; ?>"><?php echo $client_name_select; ?></option>

                                            <?php
                                        }
                                        ?>
                                    </select>
                                </div>
                            </div>

                        <?php } ?>

                    </div>

                </div>
                <div class="modal-footer bg-white">
                    <button type="submit" name="edit_recurring_expense" class="btn btn-primary text-bold"><i class="fas fa-check mr-2"></i>Save</button>
                    <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fas fa-times mr-2"></i>Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>
