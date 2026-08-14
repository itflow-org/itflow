<?php
require_once "includes/inc_all_admin.php";

$start_page_select_array = array (
    'dashboard.php'=>'Dashboard',
    'clients.php'=> 'Client Management',
    'tickets.php'=> 'Support Tickets',
    'invoices.php' => 'Invoices'
);

$net_terms_array = array (
    '0'=>'On Receipt',
    '7'=>'7 Days',
    '10'=>'10 Days',
    '15'=>'15 Days',
    '30'=>'30 Days',
    '45'=>'45 Days',
    '60'=>'60 Days',
    '90'=>'90 Days'
);

?>

<div class="card card-dark">
    <div class="card-header py-3">
        <h3 class="card-title"><i class="fas fa-fw fa-cogs me-2"></i>Defaults</h3>
    </div>
    <div class="card-body">
        <form action="post.php" method="post" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

            <div class="mb-3">
                <label>Start Page</label>
                <div class="input-group">
                        <span class="input-group-text"><i class="fa fa-fw fa-home"></i></span>
                    <select class="form-control select2" name="start_page" data-tags="true" required>
                        <?php if (!in_array($config_start_page, array_keys($start_page_select_array))) { ?>
                            <option selected> <?= escapeHtml($config_start_page) ?></option>
                        <?php } ?>
                        <?php foreach ($start_page_select_array as $start_page_value => $start_page_name) { ?>
                            <option <?php if ($start_page_value == $config_start_page) { echo "selected"; } ?>
                                value="<?= escapeHtml($start_page_value) ?>">
                                <?= escapeHtml($start_page_name) ?>
                            </option>
                        <?php }?>
                    </select>
                </div>
            </div>

            <div class="mb-3">
                <label>Calendar</label>
                <div class="input-group">
                        <span class="input-group-text"><i class="fa fa-fw fa-calendar"></i></span>
                    <select class="form-control select2" name="calendar">
                        <option value="0">- None -</option>
                        <?php

                        $sql = mysqli_query($mysqli, "SELECT calendar_id, calendar_name FROM calendars ORDER BY calendar_name ASC");
                        while ($row = mysqli_fetch_assoc($sql)) {
                            $calendar_id = intval($row['calendar_id']);
                            $calendar_name = escapeHtml($row['calendar_name']); ?>
                            <option <?php if ($config_default_calendar == $calendar_id) {
                                        echo "selected";
                                    } ?> value="<?= $calendar_id ?>"><?= $calendar_name ?></option>
                        <?php } ?>

                    </select>
                </div>
            </div>

            <div class="mb-3">
                <label>Transfer From Account</label>
                <div class="input-group">
                        <span class="input-group-text"><i class="fa fa-fw fa-exchange-alt"></i></span>
                    <select class="form-control select2" name="transfer_from_account">
                        <option value="0">- None -</option>
                        <?php

                        $sql = mysqli_query($mysqli, "SELECT account_id, account_name FROM accounts WHERE account_archived_at IS NULL ORDER BY account_name ASC");
                        while ($row = mysqli_fetch_assoc($sql)) {
                            $account_id = intval($row['account_id']);
                            $account_name = escapeHtml($row['account_name']); ?>
                            <option <?php if ($config_default_transfer_from_account == $account_id) {
                                        echo "selected";
                                    } ?> value="<?= $account_id ?>"><?= $account_name ?></option>
                        <?php } ?>

                    </select>
                </div>
            </div>

            <div class="mb-3">
                <label>Transfer To Account</label>
                <div class="input-group">
                        <span class="input-group-text"><i class="fa fa-fw fa-exchange-alt"></i></span>
                    <select class="form-control select2" name="transfer_to_account">
                        <option value="0">- None -</option>
                        <?php

                        $sql = mysqli_query($mysqli, "SELECT account_id, account_name FROM accounts WHERE account_archived_at IS NULL ORDER BY account_name ASC");
                        while ($row = mysqli_fetch_assoc($sql)) {
                            $account_id = intval($row['account_id']);
                            $account_name = escapeHtml($row['account_name']); ?>
                            <option <?php if ($config_default_transfer_to_account == $account_id) {
                                        echo "selected";
                                    } ?> value="<?= $account_id ?>"><?= $account_name ?></option>
                        <?php } ?>

                    </select>
                </div>
            </div>

            <div class="mb-3">
                <label>Payment Account</label>
                <div class="input-group">
                        <span class="input-group-text"><i class="fa fa-fw fa-credit-card"></i></span>
                    <select class="form-control select2" name="payment_account">
                        <option value="0">- None -</option>
                        <?php

                        $sql = mysqli_query($mysqli, "SELECT account_id, account_name FROM accounts WHERE account_archived_at IS NULL ORDER BY account_name ASC");
                        while ($row = mysqli_fetch_assoc($sql)) {
                            $account_id = intval($row['account_id']);
                            $account_name = escapeHtml($row['account_name']); ?>
                            <option <?php if ($config_default_payment_account == $account_id) {
                                        echo "selected";
                                    } ?> value="<?= $account_id ?>"><?= $account_name ?></option>
                        <?php
                        }
                        ?>

                    </select>
                </div>
            </div>

            <div class="mb-3">
                <label>Expense Account</label>
                <div class="input-group">
                        <span class="input-group-text"><i class="fa fa-fw fa-shopping-cart"></i></span>
                    <select class="form-control select2" name="expense_account">
                        <option value="0">- None -</option>
                        <?php

                        $sql = mysqli_query($mysqli, "SELECT account_id, account_name FROM accounts WHERE account_archived_at IS NULL ORDER BY account_name ASC");
                        while ($row = mysqli_fetch_assoc($sql)) {
                            $account_id = intval($row['account_id']);
                            $account_name = escapeHtml($row['account_name']); ?>
                            <option <?php if ($config_default_expense_account == $account_id) {
                                        echo "selected";
                                    } ?> value="<?= $account_id ?>"><?= $account_name ?></option>
                        <?php } ?>

                    </select>
                </div>
            </div>

            <div class="mb-3">
                <label>Payment Method</label>
                <div class="input-group">
                        <span class="input-group-text"><i class="fa fa-fw fa-credit-card"></i></span>
                    <select class="form-control select2" name="payment_method">
                        <option value="">- None -</option>
                        <?php

                        $sql = mysqli_query($mysqli, "SELECT category_name FROM categories WHERE category_type = 'Payment Method' ORDER BY category_name ASC");
                        while ($row = mysqli_fetch_assoc($sql)) {
                            $payment_method = escapeHtml($row['category_name']); ?>
                            <option <?php if ($config_default_payment_method == $payment_method) {
                                        echo "selected";
                                    } ?>><?= $payment_method ?></option>
                        <?php } ?>

                    </select>
                </div>
            </div>

            <div class="mb-3">
                <label>Expense Payment Method</label>
                <div class="input-group">
                        <span class="input-group-text"><i class="fa fa-fw fa-credit-card"></i></span>
                    <select class="form-control select2" name="expense_payment_method">
                        <option value="">- None -</option>
                        <?php

                        $sql = mysqli_query($mysqli, "SELECT category_name FROM categories WHERE category_type = 'Payment Method' ORDER BY category_name ASC");
                        while ($row = mysqli_fetch_assoc($sql)) {
                            $payment_method = escapeHtml($row['category_name']); ?>
                            <option <?php if ($config_default_expense_payment_method == $payment_method) {
                                        echo "selected";
                                    } ?>><?= $payment_method ?></option>
                        <?php } ?>

                    </select>
                </div>
            </div>

            <div class="mb-3">
                <label>Net Terms</label>
                <div class="input-group">
                        <span class="input-group-text"><i class="fa fa-fw fa-calendar"></i></span>
                    <select class="form-control select2" name="net_terms">
                        <?php foreach ($net_terms_array as $net_term_value => $net_term_name) { ?>
                            <option <?php if ($config_default_net_terms == $net_term_value) {
                                        echo "selected";
                                    } ?> value="<?= $net_term_value ?>"><?= $net_term_name ?></option>
                        <?php } ?>
                    </select>
                </div>
            </div>

            <div class="mb-3">
                <label>Client Hourly Rate</label>
                <div class="input-group">
                        <span class="input-group-text"><i class="fa fa-fw fa-clock"></i></span>
                    <input type="text" class="form-control" inputmode="decimal" pattern="[0-9]*\.?[0-9]{0,2}" name="hourly_rate" value="<?= number_format($config_default_hourly_rate, 2, '.', '') ?>" placeholder="0.00" required>
                </div>
            </div>

            <hr>

            <button type="submit" name="edit_default_settings" class="btn btn-primary text-bold"><i class="fa fa-check me-2"></i>Save</button>

        </form>
    </div>
</div>

<?php
require_once "../includes/footer.php";
