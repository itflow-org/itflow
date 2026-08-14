<?php

require_once '../../../includes/modal_header.php';

enforceUserPermission('module_financial');

// Pre-filled from the expenses page's current filters. Everything here is editable -
// the export runs whatever this form posts, not whatever the page happened to show.
$client_id = intval($_GET['client_id'] ?? 0);
if ($client_id) {
    enforceClientAccess();
}

// Search Filter
$q_filter = $_GET['q'] ?? '';

// Account Filter
$account_filter = intval($_GET['account'] ?? 0);

// Vendor Filter
$vendor_filter = intval($_GET['vendor'] ?? 0);

// Category Filter
$category_filter = intval($_GET['category'] ?? 0);

// Date Filter - the all-time sentinels from filter_header.php leave the fields blank
$date_from_filter = (!empty($_GET['dtf']) && $_GET['dtf'] !== '1970-01-01') ? escapeHtml($_GET['dtf']) : '';
$date_to_filter = (!empty($_GET['dtt']) && $_GET['dtt'] !== '2099-12-31') ? escapeHtml($_GET['dtt']) : '';

ob_start();

?>

<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fas fa-fw fa-download me-2"></i>Export Expenses</h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>

<?php exportTabsNav(); ?>

<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

    <div class="modal-body">

        <?php exportTabsFiltersOpen(); ?>

        <div class="mb-3">
            <label>Search</label>
            <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-search"></i></span>
                <input type="text" class="form-control" name="q" value="<?= stripslashes(escapeHtml($q_filter)) ?>" placeholder="Vendor, client, category, description">
            </div>
        </div>

        <div class="mb-3">
            <label>Account</label>
            <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-piggy-bank"></i></span>
                <select class="form-control select2" name="account">
                    <option value="">- All Accounts -</option>
                    <?php
                    $sql_account_filter = mysqli_query($mysqli, "SELECT account_id, account_name FROM accounts WHERE account_archived_at IS NULL ORDER BY account_name ASC");
                    while ($row = mysqli_fetch_assoc($sql_account_filter)) {
                        $filter_option_id = intval($row['account_id']);
                        $filter_option_name = escapeHtml($row['account_name']);
                    ?>
                        <option <?php if ($account_filter == $filter_option_id) { echo "selected"; } ?> value="<?= $filter_option_id ?>"><?= $filter_option_name ?></option>
                    <?php
                    }
                    ?>
                </select>
            </div>
        </div>

        <div class="mb-3">
            <label>Vendor</label>
            <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-building"></i></span>
                <select class="form-control select2" name="vendor">
                    <option value="">- All Vendors -</option>
                    <?php
                    $sql_vendor_filter = mysqli_query($mysqli, "SELECT vendor_id, vendor_name FROM vendors WHERE EXISTS (SELECT 1 FROM expenses WHERE expense_vendor_id = vendor_id) ORDER BY vendor_name ASC");
                    while ($row = mysqli_fetch_assoc($sql_vendor_filter)) {
                        $filter_option_id = intval($row['vendor_id']);
                        $filter_option_name = escapeHtml($row['vendor_name']);
                    ?>
                        <option <?php if ($vendor_filter == $filter_option_id) { echo "selected"; } ?> value="<?= $filter_option_id ?>"><?= $filter_option_name ?></option>
                    <?php
                    }
                    ?>
                </select>
            </div>
        </div>

        <div class="mb-3">
            <label>Category</label>
            <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-list"></i></span>
                <select class="form-control select2" name="category">
                    <option value="">- All Categories -</option>
                    <?php
                    $sql_category_filter = mysqli_query($mysqli, "SELECT category_id, category_name FROM categories WHERE category_type = 'Expense' ORDER BY category_name ASC");
                    while ($row = mysqli_fetch_assoc($sql_category_filter)) {
                        $filter_option_id = intval($row['category_id']);
                        $filter_option_name = escapeHtml($row['category_name']);
                    ?>
                        <option <?php if ($category_filter == $filter_option_id) { echo "selected"; } ?> value="<?= $filter_option_id ?>"><?= $filter_option_name ?></option>
                    <?php
                    }
                    ?>
                </select>
            </div>
        </div>

        <div class="mb-3">
            <label>Dated From</label>
            <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-calendar"></i></span>
                <input type="date" class="form-control" name="dtf" value="<?= $date_from_filter ?>" max="2999-12-31">
            </div>
        </div>

        <div class="mb-3">
            <label>Dated To</label>
            <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-calendar"></i></span>
                <input type="date" class="form-control" name="dtt" value="<?= $date_to_filter ?>" max="2999-12-31">
            </div>
        </div>

        <?php exportTabsColumns('expenses'); ?>

    </div>
    <div class="modal-footer">
        <?php renderExportButtons('export_expenses'); ?>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
