<div class="form-group">
    <div class="modal" id="addPaymentProviderModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content bg-dark">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fa fa-fw fa-credit-card mr-2"></i>Add Payment Provider</h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form action="post.php" method="post" autocomplete="off">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?>">

                <div class="modal-body bg-white">

                    <div class="form-group">
                        <label>Provider <strong class="text-danger">*</strong></label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fa fa-fw fa-credit-card"></i></span>
                            </div>
                            <select class="form-control select2" name="provider">
                                <option>Stripe</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Publishable key <strong class="text-danger">*</strong></label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fa fa-fw fa-eye"></i></span>
                            </div>
                            <input type="text" class="form-control" name="public_key" placeholder="Publishable API Key (pk_...)">
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Secret key <strong class="text-danger">*</strong></label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fa fa-fw fa-key"></i></span>
                            </div>
                            <input type="text" class="form-control" name="private_key" placeholder="Secret API Key (sk_...)">
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Account <strong class="text-danger">*</strong></label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fas fa-fw fa-piggy-bank"></i></span>
                            </div>
                            <select class="form-control select2" name="account">
                                <option value="">- Select an Account -</option>
                                <?php
                                $sql_accounts = mysqli_query($mysqli, "SELECT * FROM accounts WHERE account_archived_at IS NULL ORDER BY account_name ASC");
                                while ($row = mysqli_fetch_array($sql_accounts)) {
                                    $account_id = intval($row['account_id']);
                                    $account_name = nullable_htmlentities($row['account_name']);
                                    ?>

                                    <option value="<?php echo $account_id ?>"><?php echo $account_name ?></option>
                                    <?php
                                }
                                ?>
                            </select>
                        </div>
                    </div>

                    <hr>

                    <div class="form-group">
                        <label>Expense Vendor</label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fa fa-fw fa-building"></i></span>
                            </div>
                            <select class="form-control select2" name="expense_vendor">
                                <option value="">- Do not expense Payment Provider fees -</option>
                                <?php

                                $sql_select = mysqli_query($mysqli, "SELECT vendor_id, vendor_name FROM vendors WHERE vendor_client_id = 0 AND vendor_archived_at IS NULL ORDER BY vendor_name ASC");
                                while ($row = mysqli_fetch_array($sql_select)) {
                                    $vendor_id = intval($row['vendor_id']);
                                    $vendor_name = nullable_htmlentities($row['vendor_name']);
                                    ?>
                                    <option value="<?php echo $vendor_id; ?>"><?php echo $vendor_name; ?></option>
                                <?php
                                }
                                ?>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Expense Category</label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fa fa-fw fa-list"></i></span>
                            </div>
                            <select class="form-control select2" name="expense_category">
                                <option value="">- Do not expense Payment Provider fees -</option>
                                <?php

                                $sql_select = mysqli_query($mysqli, "SELECT category_id, category_name FROM categories WHERE category_type = 'Expense' AND category_archived_at IS NULL ORDER BY category_name ASC");
                                while ($row = mysqli_fetch_array($sql_select)) {
                                    $category_id = intval($row['category_id']);
                                    $category_name = nullable_htmlentities($row['category_name']);
                                    ?>
                                    <option value="<?php echo $category_id; ?>"><?php echo $category_name; ?></option>
                                    <?php
                                }

                                ?>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Percentage Fee to expense</label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fa fa-fw fa-percent"></i></span>
                            </div>
                            <input type="text" class="form-control" inputmode="numeric" pattern="[0-9]*\.?[0-9]{0,2}" name="percentage_fee" placeholder="Enter Percentage">
                        </div>
                        <small class="form-text text-muted">See <a href="https://stripe.com/pricing" target="_blank">here <i class="fas fa-fw fa-external-link-alt"></i></a> for the latest Stripe Fees.</small>
                    </div>

                    <div class="form-group">
                        <label>Flat Fee to expense</label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fa fa-fw fa-shopping-cart"></i></span>
                            </div>
                            <input type="text" class="form-control" inputmode="numeric" pattern="[0-9]*\.?[0-9]{0,2}" name="flat_fee" placeholder="0.030">
                        </div>
                        <small class="form-text text-muted">See <a href="https://stripe.com/pricing" target="_blank">here <i class="fas fa-fw fa-external-link-alt"></i></a> for the latest Stripe Fees.</small>
                    </div>

                </div>
                <div class="modal-footer bg-white">
                    <button type="submit" name="add_payment_provider" class="btn btn-primary text-bold"><i class="fa fa-check mr-2"></i>Add</button>
                    <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fa fa-times mr-2"></i>Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>
