<?php

require_once '../../../includes/modal_header.php';

// Pre-fill from the transactions page's current filters

// Account Filter
if (isset($_GET['account']) & !empty($_GET['account'])) {
    $account_filter = intval($_GET['account']);
} else {
    $account_filter = '';
}

// Type Filter
$transaction_types_array = ['Revenue', 'Payment', 'Expense', 'Transfer In', 'Transfer Out'];
if (isset($_GET['type']) & !empty($_GET['type']) && in_array($_GET['type'], $transaction_types_array)) {
    $type_filter = $_GET['type'];
} else {
    $type_filter = '';
}

// Category Filter
if (isset($_GET['category']) & !empty($_GET['category'])) {
    $category_filter = intval($_GET['category']);
} else {
    $category_filter = '';
}

// Client Filter
if (isset($_GET['client']) & !empty($_GET['client'])) {
    $client_filter = intval($_GET['client']);
} else {
    $client_filter = '';
}

// Payment Method Filter
if (isset($_GET['payment_method']) & !empty($_GET['payment_method'])) {
    $payment_method_filter = $_GET['payment_method'];
} else {
    $payment_method_filter = '';
}

// Amount Range Filter
if (isset($_GET['amount_min']) && $_GET['amount_min'] != '') {
    $amount_min_filter = floatval($_GET['amount_min']);
} else {
    $amount_min_filter = '';
}
if (isset($_GET['amount_max']) && $_GET['amount_max'] != '') {
    $amount_max_filter = floatval($_GET['amount_max']);
} else {
    $amount_max_filter = '';
}

// Search Filter
if (isset($_GET['q']) & !empty($_GET['q'])) {
    $q_filter = $_GET['q'];
} else {
    $q_filter = '';
}

// Date Filter - ignore the all-time defaults so the date fields stay clean
if (isset($_GET['dtf']) && !empty($_GET['dtf']) && $_GET['dtf'] !== '1970-01-01') {
    $date_from_filter = escapeHtml($_GET['dtf']);
} else {
    $date_from_filter = '';
}
if (isset($_GET['dtt']) && !empty($_GET['dtt']) && $_GET['dtt'] !== '2099-12-31') {
    $date_to_filter = escapeHtml($_GET['dtt']);
} else {
    $date_to_filter = '';
}

ob_start();

?>

<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fa fa-fw fa-download mr-2"></i>Exporting Transactions to CSV</h5>
    <button type="button" class="close text-white" data-dismiss="modal">
        <span>&times;</span>
    </button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

    <div class="modal-body">

        <div class="form-group">
            <label>Account</label>
            <div class="input-group">
                <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-piggy-bank"></i></span>
                </div>
                <select class="form-control select2" name="account" required>
                    <option value="">- Select an Account -</option>

                    <?php
                    $sql_accounts_filter = mysqli_query($mysqli, "SELECT * FROM accounts WHERE account_archived_at IS NULL ORDER BY account_name ASC");
                    while ($row = mysqli_fetch_assoc($sql_accounts_filter)) {
                        $account_id = intval($row['account_id']);
                        $account_name = escapeHtml($row['account_name']);
                    ?>
                        <option <?php if ($account_filter == $account_id) { echo "selected"; } ?> value="<?= $account_id ?>"><?= $account_name ?></option>
                    <?php
                    }
                    ?>

                </select>
            </div>
        </div>

        <div class="form-group">
            <label>Search</label>
            <div class="input-group">
                <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-search"></i></span>
                </div>
                <input type="text" class="form-control" name="q" value="<?= stripslashes(escapeHtml($q_filter)) ?>" placeholder="Description, category, reference or amount">
            </div>
        </div>

        <div class="form-group">
            <label>Type</label>
            <div class="input-group">
                <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-exchange-alt"></i></span>
                </div>
                <select class="form-control select2" name="type">
                    <option value="">- All Types -</option>

                    <?php foreach ($transaction_types_array as $transaction_type_option) { ?>
                        <option <?php if ($type_filter == $transaction_type_option) { echo "selected"; } ?> value="<?= $transaction_type_option ?>"><?= $transaction_type_option ?></option>
                    <?php } ?>

                </select>
            </div>
        </div>

        <div class="form-group">
            <label>Category</label>
            <div class="input-group">
                <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-list"></i></span>
                </div>
                <select class="form-control select2" name="category">
                    <option value="">- All Categories -</option>

                    <?php
                    $sql_categories_filter = mysqli_query($mysqli, "SELECT category_id, category_name FROM categories WHERE category_type IN ('Income', 'Expense') ORDER BY category_name ASC");
                    while ($row = mysqli_fetch_assoc($sql_categories_filter)) {
                        $category_id = intval($row['category_id']);
                        $category_name = escapeHtml($row['category_name']);
                    ?>
                        <option <?php if ($category_filter == $category_id) { echo "selected"; } ?> value="<?= $category_id ?>"><?= $category_name ?></option>
                    <?php
                    }
                    ?>

                </select>
            </div>
        </div>

        <div class="form-group">
            <label>Client</label>
            <div class="input-group">
                <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-user"></i></span>
                </div>
                <select class="form-control select2" name="client">
                    <option value="">- All Clients -</option>

                    <?php
                    $sql_clients_filter = mysqli_query($mysqli, "SELECT client_id, client_name FROM clients WHERE client_archived_at IS NULL AND (EXISTS (SELECT 1 FROM revenues WHERE revenue_client_id = client_id) OR EXISTS (SELECT 1 FROM expenses WHERE expense_client_id = client_id) OR EXISTS (SELECT 1 FROM invoices WHERE invoice_client_id = client_id)) ORDER BY client_name ASC");
                    while ($row = mysqli_fetch_assoc($sql_clients_filter)) {
                        $client_id = intval($row['client_id']);
                        $client_name = escapeHtml($row['client_name']);
                    ?>
                        <option <?php if ($client_filter == $client_id) { echo "selected"; } ?> value="<?= $client_id ?>"><?= $client_name ?></option>
                    <?php
                    }
                    ?>

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
                    <option value="">- All Methods -</option>

                    <?php
                    $sql_payment_methods_filter = mysqli_query($mysqli, "SELECT payment_method_name FROM payment_methods ORDER BY payment_method_name ASC");
                    while ($row = mysqli_fetch_assoc($sql_payment_methods_filter)) {
                        $payment_method_name = escapeHtml($row['payment_method_name']);
                    ?>
                        <option <?php if ($payment_method_filter == $payment_method_name) { echo "selected"; } ?> value="<?= $payment_method_name ?>"><?= $payment_method_name ?></option>
                    <?php
                    }
                    ?>

                </select>
            </div>
        </div>

        <div class="form-group">
            <label>Amount Range</label>
            <div class="input-group">
                <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-dollar-sign"></i></span>
                </div>
                <input type="number" step="0.01" min="0" class="form-control" name="amount_min" value="<?= $amount_min_filter ?>" placeholder="Min">
                <input type="number" step="0.01" min="0" class="form-control" name="amount_max" value="<?= $amount_max_filter ?>" placeholder="Max">
            </div>
        </div>

        <div class="form-group">
            <label>Date From</label>
            <div class="input-group">
                <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-calendar"></i></span>
                </div>
                <input type="date" class="form-control" name="date_from" value="<?= $date_from_filter ?>" max="2999-12-31">
            </div>
        </div>

        <div class="form-group">
            <label>Date To</label>
            <div class="input-group">
                <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-calendar"></i></span>
                </div>
                <input type="date" class="form-control" name="date_to" value="<?= $date_to_filter ?>" max="2999-12-31">
            </div>
        </div>

    </div>
    <div class="modal-footer">
        <button type="submit" name="export_transactions_csv" class="btn btn-primary text-bold"><i class="fas fa-fw fa-download mr-2"></i>Download CSV</button>
        <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fa fa-times mr-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
