<div class="modal" id="addExpenseModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content bg-dark">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fa fa-fw fa-cart-plus"></i> New Expense</h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form action="post.php" method="post" enctype="multipart/form-data" autocomplete="off">
                <div class="modal-body bg-white">

                    <div class="form-row">
                        <div class="form-group col-md">
                            <label>Date <strong class="text-danger">*</strong></label>
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text"><i class="fa fa-fw fa-calendar"></i></span>
                                </div>
                                <input type="date" class="form-control" name="date" max="2999-12-31" value="<?php echo date("Y-m-d"); ?>" required>
                            </div>
                        </div>

                        <div class="form-group col-md">
                            <label>Amount <strong class="text-danger">*</strong></label>
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text"><i class="fa fa-fw fa-dollar-sign"></i></span>
                                </div>
                                <input type="number" class="form-control" step="0.01" name="amount" placeholder="Enter amount" autofocus required>
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
                                        <option <?php if ($config_default_expense_account == $account_id) { echo "selected"; } ?> value="<?php echo $account_id; ?>"><div class="float-left"><?php echo $account_name; ?></div><div class="float-right"> [$<?php echo number_format($balance,2); ?>]</div></option>

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

                                    $sql = mysqli_query($mysqli,"SELECT * FROM vendors WHERE vendor_client_id = 0 AND vendor_template = 0 AND vendor_archived_at IS NULL AND company_id = $session_company_id ORDER BY vendor_name ASC");
                                    while ($row = mysqli_fetch_array($sql)) {
                                        $vendor_id = $row['vendor_id'];
                                        $vendor_name = htmlentities($row['vendor_name']);
                                        ?>
                                        <option value="<?php echo $vendor_id; ?>"><?php echo $vendor_name; ?></option>

                                        <?php
                                    }
                                    ?>
                                </select>
                                <div class="input-group-append">
                                    <button type="button" class="btn btn-secondary" data-toggle="modal" data-target="#addQuickVendorModal"><i class="fas fa-fw fa-plus"></i></button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Description <strong class="text-danger">*</strong></label>
                        <textarea class="form-control" rows="6" name="description" placeholder="Enter a description" required></textarea>
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

                                    $sql = mysqli_query($mysqli,"SELECT * FROM categories WHERE category_type = 'Expense' AND category_archived_at IS NULL AND company_id = $session_company_id ORDER BY category_name ASC");
                                    while ($row = mysqli_fetch_array($sql)) {
                                        $category_id = $row['category_id'];
                                        $category_name = htmlentities($row['category_name']);
                                        ?>
                                        <option value="<?php echo $category_id; ?>"><?php echo $category_name; ?></option>

                                        <?php
                                    }
                                    ?>
                                </select>
                                <div class="input-group-append">
                                    <button type="button" class="btn btn-secondary" data-toggle="modal" data-target="#addQuickCategoryExpenseModal"><i class="fas fa-fw fa-plus"></i></button>
                                </div>
                            </div>


                        </div>

                        <div class="form-group col-md">
                            <label>Reference</label>
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text"><i class="fa fa-fw fa-file-alt"></i></span>
                                </div>
                                <input type="text" class="form-control" name="reference" placeholder="Enter a reference">
                            </div>
                        </div>

                    </div>

                    <div class="form-group">
                        <label>Receipt</label>
                        <input type="file" class="form-control-file" name="file">
                    </div>

                </div>
                <div class="modal-footer bg-white">
                    <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_expense" class="btn btn-primary text-bold"><i class="fa fa-fw fa-check"></i> Create</button>
                </div>
            </form>
        </div>
    </div>
</div>
