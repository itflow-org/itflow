<?php

/*
 * Export the income - pre-filled from the Income list's filters, and
 * editable: the export runs whatever this form posts.
 */

require_once '../../../includes/modal_header.php';

enforceUserPermission('module_financial');

$client_id = intval($_GET['client_id'] ?? 0);
if ($client_id) {
    enforceClientAccess();
}

$q_filter = $_GET['q'] ?? '';
$client_filter = intval($_GET['client'] ?? 0);
$category_filter = intval($_GET['category'] ?? 0);
$account_filter = intval($_GET['account'] ?? 0);
$method_filter = (string) ($_GET['method'] ?? '');
$shows = incomeShows();
$show_filter = incomeShowFrom($_GET);

// The all-time sentinels from filter_header.php leave the dates blank
$date_from_filter = (!empty($_GET['dtf']) && $_GET['dtf'] !== '1970-01-01') ? escapeHtml($_GET['dtf']) : '';
$date_to_filter = (!empty($_GET['dtt']) && $_GET['dtt'] !== '2099-12-31') ? escapeHtml($_GET['dtt']) : '';

ob_start();

?>

<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fas fa-fw fa-download me-2"></i>Export Income</h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>

<?php exportTabsNav(); ?>

<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="client_id" value="<?= $client_id ?>">

    <div class="modal-body">

        <?php exportTabsFiltersOpen(); ?>

        <div class="mb-3">
            <label class="form-label">Search</label>
            <div class="input-group">
                <span class="input-group-text"><i class="fa fa-fw fa-search"></i></span>
                <input type="text" class="form-control" name="q" value="<?= stripslashes(escapeHtml($q_filter)) ?>" placeholder="What for, invoice number, reference, amount, client, category, account">
            </div>
        </div>

        <div class="row g-3 mb-3">
            <div class="col-md-6">
                <label class="form-label">Showing</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-filter"></i></span>
                    <select class="form-select select2" name="show">
                        <?php foreach ($shows as $key => $s) { ?>
                            <option <?= $show_filter === $key ? 'selected' : '' ?> value="<?= escapeHtml($key) ?>"><?= escapeHtml($key === '' ? 'All income' : $s['label']) ?></option>
                        <?php } ?>
                    </select>
                </div>
            </div>
            <?php if (!$client_id) { ?>
                <div class="col-md-6">
                    <label class="form-label">Client</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fa fa-fw fa-building"></i></span>
                        <select class="form-select select2" name="client">
                            <option value="">- Any client -</option>
                            <?php
                            $sql = mysqli_query($mysqli, "SELECT client_id, client_name, client_abbreviation FROM clients
                                WHERE (EXISTS (SELECT 1 FROM invoices JOIN payments ON payment_invoice_id = invoice_id WHERE invoice_client_id = client_id)
                                    OR EXISTS (SELECT 1 FROM revenues WHERE revenue_client_id = client_id)) " . clientScopeSql('clients.client_id') . " ORDER BY client_name ASC");
                            while ($row = mysqli_fetch_assoc($sql)) {
                                $option_id = intval($row['client_id']); ?>
                                <option <?= $client_filter === $option_id ? 'selected' : '' ?> value="<?= $option_id ?>"<?= selectOptionClient($row['client_id'], $row['client_name'], $row['client_abbreviation'] ?? '') ?>><?= escapeHtml($row['client_name']) ?></option>
                            <?php } ?>
                        </select>
                    </div>
                </div>
            <?php } ?>
            <div class="col-md-6">
                <label class="form-label">Category</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-tag"></i></span>
                    <select class="form-select select2" name="category">
                        <option value="">- Any category -</option>
                        <?php foreach (incomeCategories($category_filter) as $option_id => $category) { ?>
                            <option <?= $category_filter === $option_id ? 'selected' : '' ?> value="<?= $option_id ?>"<?= selectOptionColor($category['category_color'] ?? '', $category['category_icon'] ?? '') ?>><?= escapeHtml($category['category_name']) ?></option>
                        <?php } ?>
                    </select>
                </div>
            </div>
            <div class="col-md-6">
                <label class="form-label">Paid into</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-piggy-bank"></i></span>
                    <select class="form-select select2" name="account">
                        <option value="">- Any account -</option>
                        <?php foreach (paymentAccounts($account_filter) as $option_id => $account) { ?>
                            <option <?= $account_filter === $option_id ? 'selected' : '' ?> value="<?= $option_id ?>"><?= escapeHtml($account['account_name']) ?></option>
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
                        $sql = mysqli_query($mysqli, "SELECT DISTINCT payment_method AS method FROM payments WHERE COALESCE(payment_method, '') <> ''
                            UNION SELECT DISTINCT revenue_payment_method FROM revenues WHERE COALESCE(revenue_payment_method, '') <> '' ORDER BY method ASC");
                        while ($row = mysqli_fetch_row($sql)) { ?>
                            <option <?= $method_filter === $row[0] ? 'selected' : '' ?> value="<?= escapeHtml($row[0]) ?>"><?= escapeHtml($row[0]) ?></option>
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

        <?php exportTabsColumns('income'); ?>

    </div>
    <div class="modal-footer">
        <?php renderExportButtons('export_income'); ?>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
