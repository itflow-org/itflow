<div class="modal" id="addExpenseModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-dark">
                <h5 class="modal-title"><i class="fa fa-fw fa-cart-plus mr-2"></i>New Expense</h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form action="post.php" method="post" enctype="multipart/form-data" autocomplete="off">
                <div class="modal-body">

                    <div class="form-row">
                        <div class="form-group col-md">
                            <label>Date <strong class="text-danger">*</strong></label>
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text"><i class="fa fa-fw fa-calendar"></i></span>
                                </div>
                                <input type="date" class="form-control" name="date" max="2999-12-31" required>
                            </div>
                        </div>

                        <div class="form-group col-md">
                            <label>Amount <strong class="text-danger">*</strong></label>
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text"><i class="fa fa-fw fa-dollar-sign"></i></span>
                                </div>
                                <input type="text" class="form-control" inputmode="numeric" pattern="[0-9]*\.?[0-9]{0,2}" name="amount" placeholder="0.00" required>
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

                        <div class="form-group col-md">
                            <label>Vendor <strong class="text-danger">*</strong></label>
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text"><i class="fa fa-fw fa-building"></i></span>
                                </div>
                                <select class="form-control select2" name="vendor" required>
                                    <option value="">- Vendor -</option>
                                    <?php

                                    $sql = mysqli_query($mysqli, "SELECT vendor_id, vendor_name FROM vendors WHERE vendor_client_id = 0 AND vendor_archived_at IS NULL ORDER BY vendor_name ASC");
                                    while ($row = mysqli_fetch_array($sql)) {
                                        $vendor_id = intval($row['vendor_id']);
                                        $vendor_name = nullable_htmlentities($row['vendor_name']);
                                        ?>
                                        <option value="<?php echo $vendor_id; ?>"><?php echo $vendor_name; ?></option>

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
                        <textarea class="form-control" rows="6" name="description" placeholder="Enter a description" required></textarea>
                    </div>

                    <div class="form-group">
                        <label>Reference</label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fa fa-fw fa-file-alt"></i></span>
                            </div>
                            <input type="text" class="form-control" name="reference" placeholder="Enter a reference" maxlength="200">
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
                                    <option value="">- Category -</option>
                                    <?php

                                    $sql = mysqli_query($mysqli, "SELECT category_id, category_name FROM categories WHERE category_type = 'Expense' AND category_archived_at IS NULL ORDER BY category_name ASC");
                                    while ($row = mysqli_fetch_array($sql)) {
                                        $category_id = intval($row['category_id']);
                                        $category_name = nullable_htmlentities($row['category_name']);
                                        ?>
                                        <option value="<?php echo $category_id; ?>"><?php echo $category_name; ?></option>

                                        <?php
                                    }
                                    ?>
                                </select>
                                <div class="input-group-append">
                                    <button class="btn btn-secondary" type="button"
                                        data-toggle="ajax-modal"
                                        data-modal-size="sm"
                                        data-ajax-url="ajax/ajax_category_add.php?category=Expense">
                                        <i class="fas fa-plus"></i>
                                    </button>
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
                                    <select class="form-control select2" name="client" required>
                                        <option value="0">- Client (Optional) -</option>
                                        <?php

                                        $sql = mysqli_query($mysqli, "SELECT client_id, client_name FROM clients ORDER BY client_name ASC");
                                        while ($row = mysqli_fetch_array($sql)) {
                                            $client_id = intval($row['client_id']);
                                            $client_name = nullable_htmlentities($row['client_name']);
                                            ?>
                                            <option value="<?php echo $client_id; ?>"><?php echo $client_name; ?></option>

                                            <?php
                                        }
                                        ?>
                                    </select>
                                </div>
                            </div>

                        <?php } ?>

                    </div>

                    <div class="form-group col-md">
                        <label>Receipt</label>
                        <input type="file" class="form-control-file" name="file" accept="image/*, application/pdf">
                    </div>

                </div>
                <div class="modal-footer">
                    <button type="submit" name="add_expense" class="btn btn-primary text-bold"><i class="fa fa-fw fa-check mr-2"></i>Create</button>
                    <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fa fa-times mr-2"></i>Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>
