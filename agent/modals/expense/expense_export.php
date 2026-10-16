<?php

require_once '../../../includes/modal_header.php';

enforceUserPermission('module_financial');

// Pre-filled from the expenses page's current filters. Everything here is editable -
// the export runs whatever this form posts, not whatever the page happened to show.
$q_filter = $_GET['q'] ?? '';
$account_filter = intval($_GET['account'] ?? 0);
$vendor_filter = intval($_GET['vendor'] ?? 0);
$category_filter = intval($_GET['category'] ?? 0);
$client_filter = intval($_GET['client'] ?? 0);
$method_filter = (string) ($_GET['method'] ?? '');
$shows = expenseShows();
$show_filter = isset($shows[$_GET['show'] ?? '']) ? (string) ($_GET['show'] ?? '') : '';

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
            <label class="form-label">Search</label>
            <div class="input-group">
                <span class="input-group-text"><i class="fa fa-fw fa-search"></i></span>
                <input type="text" class="form-control" name="q" value="<?= stripslashes(escapeHtml($q_filter)) ?>" placeholder="Vendor, description, reference, amount">
            </div>
        </div>

        <div class="row g-3 mb-3">
            <div class="col-md-6">
                <label class="form-label">Vendor</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-building"></i></span>
                    <select class="form-select select2" name="vendor">
                        <option value="">- Any vendor -</option>
                        <?php
                        $sql = mysqli_query($mysqli, "SELECT vendor_id, vendor_name, vendor_description FROM vendors WHERE vendor_client_id = 0 AND EXISTS (SELECT 1 FROM expenses WHERE expense_vendor_id = vendor_id) ORDER BY vendor_name ASC");
                        while ($row = mysqli_fetch_assoc($sql)) {
                            $option_id = intval($row['vendor_id']); ?>
                            <option <?= $vendor_filter === $option_id ? 'selected' : '' ?> value="<?= $option_id ?>"<?= selectOptionVendor($row['vendor_name'], $row['vendor_description']) ?>><?= escapeHtml($row['vendor_name']) ?></option>
                        <?php } ?>
                    </select>
                </div>
            </div>
            <div class="col-md-6">
                <label class="form-label">Category</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-list"></i></span>
                    <select class="form-select select2" name="category">
                        <option value="">- Any category -</option>
                        <?php
                        $sql = mysqli_query($mysqli, "SELECT category_id, category_name, category_color, category_icon FROM categories WHERE category_type = 'Expense' ORDER BY category_name ASC");
                        while ($row = mysqli_fetch_assoc($sql)) {
                            $option_id = intval($row['category_id']); ?>
                            <option <?= $category_filter === $option_id ? 'selected' : '' ?> value="<?= $option_id ?>"<?= selectOptionColor($row['category_color'] ?? '', $row['category_icon'] ?? '') ?>><?= escapeHtml($row['category_name']) ?></option>
                        <?php } ?>
                    </select>
                </div>
            </div>
            <div class="col-md-6">
                <label class="form-label">Client</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-user"></i></span>
                    <select class="form-select select2" name="client">
                        <option value="">- Any client -</option>
                        <?php
                        $sql = mysqli_query($mysqli, "SELECT client_id, client_name, client_abbreviation FROM clients WHERE EXISTS (SELECT 1 FROM expenses WHERE expense_client_id = client_id) " . clientScopeSql('clients.client_id') . " ORDER BY client_name ASC");
                        while ($row = mysqli_fetch_assoc($sql)) {
                            $option_id = intval($row['client_id']); ?>
                            <option <?= $client_filter === $option_id ? 'selected' : '' ?> value="<?= $option_id ?>"<?= selectOptionClient($row['client_id'], $row['client_name'], $row['client_abbreviation'] ?? '') ?>><?= escapeHtml($row['client_name']) ?></option>
                        <?php } ?>
                    </select>
                </div>
            </div>
            <div class="col-md-6">
                <label class="form-label">Paid from</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-piggy-bank"></i></span>
                    <select class="form-select select2" name="account">
                        <option value="">- Any account -</option>
                        <?php
                        $sql = mysqli_query($mysqli, "SELECT account_id, account_name FROM accounts WHERE account_archived_at IS NULL OR account_id = $account_filter ORDER BY account_name ASC");
                        while ($row = mysqli_fetch_assoc($sql)) {
                            $option_id = intval($row['account_id']); ?>
                            <option <?= $account_filter === $option_id ? 'selected' : '' ?> value="<?= $option_id ?>"><?= escapeHtml($row['account_name']) ?></option>
                        <?php } ?>
                    </select>
                </div>
            </div>
            <div class="col-md-6">
                <label class="form-label">Paid by</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-credit-card"></i></span>
                    <select class="form-select select2" name="method">
                        <option value="">- Any payment method -</option>
                        <?php
                        $sql = mysqli_query($mysqli, "SELECT DISTINCT expense_payment_method FROM expenses WHERE expense_vendor_id > 0 AND COALESCE(expense_payment_method, '') <> '' ORDER BY expense_payment_method ASC");
                        while ($row = mysqli_fetch_row($sql)) { ?>
                            <option <?= $method_filter === $row[0] ? 'selected' : '' ?> value="<?= escapeHtml($row[0]) ?>"><?= escapeHtml($row[0]) ?></option>
                        <?php } ?>
                    </select>
                </div>
            </div>
            <div class="col-md-6">
                <label class="form-label">Showing</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-filter"></i></span>
                    <select class="form-select select2" name="show">
                        <?php foreach ($shows as $key => $s) { ?>
                            <option <?= $show_filter === $key ? 'selected' : '' ?> value="<?= escapeHtml($key) ?>"><?= escapeHtml($key === '' ? 'All expenses' : $s['label']) ?></option>
                        <?php } ?>
                    </select>
                </div>
            </div>
            <div class="col-md-6">
                <label class="form-label">Dated from</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-calendar"></i></span>
                    <input type="date" class="form-control" name="dtf" value="<?= $date_from_filter ?>" max="2999-12-31">
                </div>
            </div>
            <div class="col-md-6">
                <label class="form-label">Dated to</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-calendar"></i></span>
                    <input type="date" class="form-control" name="dtt" value="<?= $date_to_filter ?>" max="2999-12-31">
                </div>
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
