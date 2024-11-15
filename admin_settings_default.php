<?php

require_once "inc_all_admin.php";
 ?>

<div class="card card-dark">
    <div class="card-header py-3">
        <h3 class="card-title"><i class="fas fa-fw fa-cogs mr-2"></i>Defaults</h3>
    </div>
    <div class="card-body">
        <form action="post.php" method="post" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?>">

            <div class="form-group">
                <label>Start Page</label>
                <div class="input-group">
                    <div class="input-group-prepend">
                        <span class="input-group-text"><i class="fa fa-fw fa-home"></i></span>
                    </div>
                    <select class="form-control select2" name="start_page" data-tags="true" required>
                        <?php if (!in_array($config_start_page, array_keys($start_page_select_array))) { ?>
                            <option selected> <?php echo nullable_htmlentities($config_start_page); ?></option>
                        <?php } ?>
                        <?php foreach ($start_page_select_array as $start_page_value => $start_page_name) { ?>
                            <option <?php if ($start_page_value == $config_start_page) { echo "selected"; } ?>
                                value="<?php echo nullable_htmlentities($start_page_value); ?>">
                                <?php echo nullable_htmlentities($start_page_name); ?>
                            </option>
                        <?php }?>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label>Calendar</label>
                <div class="input-group">
                    <div class="input-group-prepend">
                        <span class="input-group-text"><i class="fa fa-fw fa-calendar"></i></span>
                    </div>
                    <select class="form-control select2" name="calendar">
                        <option value="0">- None -</option>
                        <?php

                        $sql = mysqli_query($mysqli, "SELECT * FROM calendars ORDER BY calendar_name ASC");
                        while ($row = mysqli_fetch_array($sql)) {
                            $calendar_id = intval($row['calendar_id']);
                            $calendar_name = nullable_htmlentities($row['calendar_name']); ?>
                            <option <?php if ($config_default_calendar == $calendar_id) {
                                        echo "selected";
                                    } ?> value="<?php echo $calendar_id; ?>"><?php echo $calendar_name; ?></option>
                        <?php } ?>

                    </select>
                </div>
            </div>

            <div class="form-group">
                <label>Transfer From Account</label>
                <div class="input-group">
                    <div class="input-group-prepend">
                        <span class="input-group-text"><i class="fa fa-fw fa-exchange-alt"></i></span>
                    </div>
                    <select class="form-control select2" name="transfer_from_account">
                        <option value="0">- None -</option>
                        <?php

                        $sql = mysqli_query($mysqli, "SELECT * FROM accounts WHERE account_archived_at IS NULL ORDER BY account_name ASC");
                        while ($row = mysqli_fetch_array($sql)) {
                            $account_id = intval($row['account_id']);
                            $account_name = nullable_htmlentities($row['account_name']); ?>
                            <option <?php if ($config_default_transfer_from_account == $account_id) {
                                        echo "selected";
                                    } ?> value="<?php echo $account_id; ?>"><?php echo $account_name; ?></option>
                        <?php } ?>

                    </select>
                </div>
            </div>

            <div class="form-group">
                <label>Transfer To Account</label>
                <div class="input-group">
                    <div class="input-group-prepend">
                        <span class="input-group-text"><i class="fa fa-fw fa-exchange-alt"></i></span>
                    </div>
                    <select class="form-control select2" name="transfer_to_account">
                        <option value="0">- None -</option>
                        <?php

                        $sql = mysqli_query($mysqli, "SELECT * FROM accounts WHERE account_archived_at IS NULL ORDER BY account_name ASC");
                        while ($row = mysqli_fetch_array($sql)) {
                            $account_id = intval($row['account_id']);
                            $account_name = nullable_htmlentities($row['account_name']); ?>
                            <option <?php if ($config_default_transfer_to_account == $account_id) {
                                        echo "selected";
                                    } ?> value="<?php echo $account_id; ?>"><?php echo $account_name; ?></option>
                        <?php } ?>

                    </select>
                </div>
            </div>

            <div class="form-group">
                <label>Payment Account</label>
                <div class="input-group">
                    <div class="input-group-prepend">
                        <span class="input-group-text"><i class="fa fa-fw fa-credit-card"></i></span>
                    </div>
                    <select class="form-control select2" name="payment_account">
                        <option value="0">- None -</option>
                        <?php

                        $sql = mysqli_query($mysqli, "SELECT * FROM accounts WHERE account_archived_at IS NULL ORDER BY account_name ASC");
                        while ($row = mysqli_fetch_array($sql)) {
                            $account_id = intval($row['account_id']);
                            $account_name = nullable_htmlentities($row['account_name']); ?>
                            <option <?php if ($config_default_payment_account == $account_id) {
                                        echo "selected";
                                    } ?> value="<?php echo $account_id; ?>"><?php echo $account_name; ?></option>
                        <?php
                        }
                        ?>

                    </select>
                </div>
            </div>

            <div class="form-group">
                <label>Expense Account</label>
                <div class="input-group">
                    <div class="input-group-prepend">
                        <span class="input-group-text"><i class="fa fa-fw fa-shopping-cart"></i></span>
                    </div>
                    <select class="form-control select2" name="expense_account">
                        <option value="0">- None -</option>
                        <?php

                        $sql = mysqli_query($mysqli, "SELECT * FROM accounts WHERE account_archived_at IS NULL ORDER BY account_name ASC");
                        while ($row = mysqli_fetch_array($sql)) {
                            $account_id = intval($row['account_id']);
                            $account_name = nullable_htmlentities($row['account_name']); ?>
                            <option <?php if ($config_default_expense_account == $account_id) {
                                        echo "selected";
                                    } ?> value="<?php echo $account_id; ?>"><?php echo $account_name; ?></option>
                        <?php } ?>

                    </select>
                </div>
            </div>

            <div class="form-group">
                <label>Payment Method</label>
                <div class="input-group">
                    <div class="input-group-prepend">
                        <span class="input-group-text"><i class="fa fa-fw fa-credit-card"></i></span>
                    </div>
                    <select class="form-control select2" name="payment_method">
                        <option value="">- None -</option>
                        <?php

                        $sql = mysqli_query($mysqli, "SELECT * FROM categories WHERE category_type = 'Payment Method' ORDER BY category_name ASC");
                        while ($row = mysqli_fetch_array($sql)) {
                            $payment_method = nullable_htmlentities($row['category_name']); ?>
                            <option <?php if ($config_default_payment_method == $payment_method) {
                                        echo "selected";
                                    } ?>><?php echo $payment_method; ?></option>
                        <?php } ?>

                    </select>
                </div>
            </div>

            <div class="form-group">
                <label>Expense Payment Method</label>
                <div class="input-group">
                    <div class="input-group-prepend">
                        <span class="input-group-text"><i class="fa fa-fw fa-credit-card"></i></span>
                    </div>
                    <select class="form-control select2" name="expense_payment_method">
                        <option value="">- None -</option>
                        <?php

                        $sql = mysqli_query($mysqli, "SELECT * FROM categories WHERE category_type = 'Payment Method' ORDER BY category_name ASC");
                        while ($row = mysqli_fetch_array($sql)) {
                            $payment_method = nullable_htmlentities($row['category_name']); ?>
                            <option <?php if ($config_default_expense_payment_method == $payment_method) {
                                        echo "selected";
                                    } ?>><?php echo $payment_method; ?></option>
                        <?php } ?>

                    </select>
                </div>
            </div>

            <div class="form-group">
                <label>Net Terms</label>
                <div class="input-group">
                    <div class="input-group-prepend">
                        <span class="input-group-text"><i class="fa fa-fw fa-calendar"></i></span>
                    </div>
                    <select class="form-control select2" name="net_terms">
                        <?php foreach ($net_terms_array as $net_term_value => $net_term_name) { ?>
                            <option <?php if ($config_default_net_terms == $net_term_value) {
                                        echo "selected";
                                    } ?> value="<?php echo $net_term_value; ?>"><?php echo $net_term_name; ?></option>
                        <?php } ?>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label>Client Hourly Rate</label>
                <div class="input-group">
                    <div class="input-group-prepend">
                        <span class="input-group-text"><i class="fa fa-fw fa-clock"></i></span>
                    </div>
                    <input type="text" class="form-control" inputmode="numeric" pattern="[0-9]*\.?[0-9]{0,2}" name="hourly_rate" value="<?php echo number_format($config_default_hourly_rate, 2, '.', ''); ?>" placeholder="0.00" required>
                </div>
            </div>

            <div class="form-group">
                <label>Phone Mask</label>
                <div class="input-group">
                    <div class="input-group-prepend">
                        <span class="input-group-text"><i class="fa fa-phone"></i></span>
                    </div>
                    <select class="form-control select2" name="phone_mask">
                        <?php
                            $sql = mysqli_query($mysqli, "SELECT config_phone_mask FROM settings WHERE company_id = 1");
                            while ($row = mysqli_fetch_array($sql)) {
                                $phone_mask = intval($row['config_phone_mask']);
                            } ?>
                            <option <?php if ($phone_mask == 1) { echo "selected"; }?> value=1>
                                US Format - e.g. (412) 888-9999
                            </option>
                            <option <?php if ($phone_mask == 0) { echo "selected"; }?> value=0>
                                Non-US Format - e.g. 4128889999
                            </option>
                    </select>
                </div>
            </div>

            <hr>

            <button type="submit" name="edit_default_settings" class="btn btn-primary text-bold"><i class="fa fa-check mr-2"></i>Save</button>

        </form>
    </div>
</div>

<?php
require_once "footer.php";
