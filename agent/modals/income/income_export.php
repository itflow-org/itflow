<?php

require_once '../../../includes/modal_header.php';

// Pre-fill from the income page's current filters

// Client - carried through from the client-scoped Income page
$client_id = intval($_GET['client_id'] ?? 0);

// Type Filter
$income_types_array = ['Payment', 'Revenue'];
if (isset($_GET['type']) && !empty($_GET['type']) && in_array($_GET['type'], $income_types_array)) {
    $type_filter = $_GET['type'];
} else {
    $type_filter = '';
}

// Category Filter
if (isset($_GET['category']) && !empty($_GET['category'])) {
    $category_filter = intval($_GET['category']);
} else {
    $category_filter = '';
}

// Account Filter
if (isset($_GET['account']) && !empty($_GET['account'])) {
    $account_filter = intval($_GET['account']);
} else {
    $account_filter = '';
}

// Payment Method Filter
if (isset($_GET['method']) && !empty($_GET['method'])) {
    $method_filter = escapeHtml($_GET['method']);
} else {
    $method_filter = '';
}

// Search Filter
if (isset($_GET['q']) && !empty($_GET['q'])) {
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
    <h5 class="modal-title"><i class="fa fa-fw fa-download me-2"></i>Export Income</h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<?php exportTabsNav(); ?>

<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="client_id" value="<?= $client_id ?>">

    <div class="modal-body">

        <?php exportTabsFiltersOpen(); ?>

        <div class="mb-3">
            <label>Search</label>
            <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-search"></i></span>
                <input type="text" class="form-control" name="q" value="<?= stripslashes(escapeHtml($q_filter)) ?>" placeholder="Source, category, client, account, method, reference or amount">
            </div>
        </div>

        <div class="mb-3">
            <label>Type</label>
            <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-hand-holding-usd"></i></span>
                <select class="form-control select2" name="type">
                    <option value="">- All Types -</option>

                    <?php foreach ($income_types_array as $income_type_option) { ?>
                        <option <?php if ($type_filter == $income_type_option) { echo "selected"; } ?> value="<?= $income_type_option ?>"><?= $income_type_option ?></option>
                    <?php } ?>

                </select>
            </div>
        </div>

        <div class="mb-3">
            <label>Category</label>
            <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-tag"></i></span>
                <select class="form-control select2" name="category">
                    <option value="">- All Categories -</option>

                    <?php
                    $sql_categories_filter = mysqli_query($mysqli, "SELECT category_id, category_name FROM categories
                        WHERE category_type = 'Income'
                        AND (EXISTS (SELECT 1 FROM revenues WHERE revenue_category_id = category_id)
                            OR EXISTS (SELECT 1 FROM payments JOIN invoices ON payment_invoice_id = invoice_id WHERE invoice_category_id = category_id))
                        ORDER BY category_name ASC");
                    while ($row = mysqli_fetch_assoc($sql_categories_filter)) {
                        $filter_category_id = intval($row['category_id']);
                        $filter_category_name = escapeHtml($row['category_name']);
                    ?>
                        <option <?php if ($category_filter == $filter_category_id) { echo "selected"; } ?> value="<?= $filter_category_id ?>"><?= $filter_category_name ?></option>
                    <?php
                    }
                    ?>

                </select>
            </div>
        </div>

        <div class="mb-3">
            <label>Account</label>
            <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-piggy-bank"></i></span>
                <select class="form-control select2" name="account">
                    <option value="">- All Accounts -</option>

                    <?php
                    $sql_accounts_filter = mysqli_query($mysqli, "SELECT account_id, account_name FROM accounts
                        WHERE EXISTS (SELECT 1 FROM payments WHERE payment_account_id = account_id)
                        OR EXISTS (SELECT 1 FROM revenues WHERE revenue_account_id = account_id)
                        ORDER BY account_name ASC");
                    while ($row = mysqli_fetch_assoc($sql_accounts_filter)) {
                        $filter_account_id = intval($row['account_id']);
                        $filter_account_name = escapeHtml($row['account_name']);
                    ?>
                        <option <?php if ($account_filter == $filter_account_id) { echo "selected"; } ?> value="<?= $filter_account_id ?>"><?= $filter_account_name ?></option>
                    <?php
                    }
                    ?>

                </select>
            </div>
        </div>

        <div class="mb-3">
            <label>Payment Method</label>
            <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-credit-card"></i></span>
                <select class="form-control select2" name="method">
                    <option value="">- All Payment Methods -</option>

                    <?php
                    $sql_payment_methods_filter = mysqli_query($mysqli, "SELECT DISTINCT payment_method AS method FROM payments WHERE payment_method != ''
                        UNION SELECT DISTINCT revenue_payment_method FROM revenues WHERE revenue_payment_method != ''
                        ORDER BY method ASC");
                    while ($row = mysqli_fetch_assoc($sql_payment_methods_filter)) {
                        $filter_method = escapeHtml($row['method']);
                    ?>
                        <option <?php if ($method_filter == $filter_method) { echo "selected"; } ?> value="<?= $filter_method ?>"><?= $filter_method ?></option>
                    <?php
                    }
                    ?>

                </select>
            </div>
        </div>

        <div class="mb-3">
            <label>Date From</label>
            <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-calendar"></i></span>
                <input type="date" class="form-control" name="date_from" value="<?= $date_from_filter ?>" max="2999-12-31">
            </div>
        </div>

        <div class="mb-3">
            <label>Date To</label>
            <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-calendar"></i></span>
                <input type="date" class="form-control" name="date_to" value="<?= $date_to_filter ?>" max="2999-12-31">
            </div>
        </div>


        <?php exportTabsColumns('income'); ?>

    </div>
    <div class="modal-footer">
        <?php renderExportButtons('export_income'); ?>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
