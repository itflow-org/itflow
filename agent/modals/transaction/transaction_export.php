<?php

/*
 * Export transactions - one account's ledger with its balance, or every
 * account's - pre-filled from the ledger's filters, and editable: the export
 * runs whatever this form posts.
 */

require_once '../../../includes/modal_header.php';

enforceUserPermission('module_financial');

$account_filter = intval($_GET['account'] ?? 0);
$show_filter = (string) ($_GET['show'] ?? '');
$kind_filter = (string) ($_GET['kind'] ?? '');
if ($kind_filter === '' && isset(accountLedgerTypeNames()[(string) ($_GET['type'] ?? '')])) {
    $kind_filter = accountLedgerTypeNames()[(string) $_GET['type']];
}
$category_filter = intval($_GET['category'] ?? 0);
$client_filter = intval($_GET['client'] ?? 0);
$method_filter = (string) ($_GET['method'] ?? ($_GET['payment_method'] ?? ''));
$amount_min = (string) ($_GET['amount_min'] ?? '');
$amount_max = (string) ($_GET['amount_max'] ?? '');
$q_filter = (string) ($_GET['q'] ?? '');
$date_from_filter = (!empty($_GET['dtf']) && $_GET['dtf'] !== '1970-01-01') ? escapeHtml($_GET['dtf']) : '';
$date_to_filter = (!empty($_GET['dtt']) && $_GET['dtt'] !== '2099-12-31') ? escapeHtml($_GET['dtt']) : '';
$types = accountTypes();

ob_start();

?>

<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fas fa-fw fa-download me-2"></i>Export Transactions</h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>

<?php exportTabsNav(); ?>

<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

    <div class="modal-body">

        <?php exportTabsFiltersOpen(); ?>

        <div class="row g-3 mb-3">
            <div class="col-md-6">
                <label class="form-label">Account</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-piggy-bank"></i></span>
                    <select class="form-select select2" name="account">
                        <option value="">- Every account -</option>
                        <?php foreach (accountsList(null) as $id => $account) { ?>
                            <option value="<?= $id ?>" <?= $account_filter === $id ? 'selected' : '' ?> data-icon="fas <?= $types[$account['type']]['icon'] ?>"><?= escapeHtml($account['account_name']) ?><?= $account['account_archived_at'] ? ' (archived)' : '' ?></option>
                        <?php } ?>
                    </select>
                </div>
                <div class="form-text">One account comes with its balance after each line.</div>
            </div>
            <div class="col-md-6">
                <label class="form-label">Search</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-search"></i></span>
                    <input type="text" class="form-control" name="q" value="<?= stripslashes(escapeHtml($q_filter)) ?>" placeholder="Who or what, reference, amount, category, client">
                </div>
            </div>
            <div class="col-md-6">
                <label class="form-label">Showing</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-filter"></i></span>
                    <select class="form-select select2" name="show">
                        <?php foreach (accountLedgerShows() as $key => $s) { ?>
                            <option value="<?= escapeHtml($key) ?>" <?= $show_filter === $key ? 'selected' : '' ?>><?= escapeHtml($key === '' ? 'Everything' : $s['label']) ?></option>
                        <?php } ?>
                    </select>
                </div>
            </div>
            <div class="col-md-6">
                <label class="form-label">Kind</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-shapes"></i></span>
                    <select class="form-select select2" name="kind">
                        <option value="">- Any kind -</option>
                        <?php foreach (accountLedgerKinds() as $key => $info) { ?>
                            <option value="<?= $key ?>" <?= $kind_filter === $key ? 'selected' : '' ?>><?= escapeHtml($info['label']) ?></option>
                        <?php } ?>
                    </select>
                </div>
            </div>
            <div class="col-md-6">
                <label class="form-label">Category</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-tag"></i></span>
                    <select class="form-select select2" name="category">
                        <option value="">- Any category -</option>
                        <?php
                        $sql = mysqli_query($mysqli, "SELECT category_id, category_name, category_type, category_color, category_icon FROM categories
                            WHERE category_type IN ('Income', 'Expense') AND category_name <> 'Account Transfer' AND (category_archived_at IS NULL OR category_id = $category_filter)
                            ORDER BY category_type DESC, category_name ASC");
                        while ($row = mysqli_fetch_assoc($sql)) {
                            $option_id = intval($row['category_id']); ?>
                            <option <?= $category_filter === $option_id ? 'selected' : '' ?> value="<?= $option_id ?>"<?= selectOptionColor($row['category_color'] ?? '', $row['category_icon'] ?? '') ?>><?= escapeHtml($row['category_name']) ?> (<?= $row['category_type'] === 'Income' ? 'in' : 'out' ?>)</option>
                        <?php } ?>
                    </select>
                </div>
            </div>
            <div class="col-md-6">
                <label class="form-label">Client</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-building"></i></span>
                    <select class="form-select select2" name="client">
                        <option value="">- Any client -</option>
                        <?php
                        $sql = mysqli_query($mysqli, "SELECT client_id, client_name, client_abbreviation FROM clients WHERE (client_archived_at IS NULL OR client_id = $client_filter) " . clientScopeSql('clients.client_id') . " ORDER BY client_name ASC");
                        while ($row = mysqli_fetch_assoc($sql)) {
                            $option_id = intval($row['client_id']); ?>
                            <option <?= $client_filter === $option_id ? 'selected' : '' ?> value="<?= $option_id ?>"<?= selectOptionClient($row['client_id'], $row['client_name'], $row['client_abbreviation'] ?? '') ?>><?= escapeHtml($row['client_name']) ?></option>
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
                        <?php foreach (paymentMethods() as $method) { ?>
                            <option <?= $method_filter === $method ? 'selected' : '' ?> value="<?= escapeHtml($method) ?>"><?= escapeHtml($method) ?></option>
                        <?php } ?>
                    </select>
                </div>
            </div>
            <div class="col-md-6">
                <label class="form-label">Amount</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-dollar-sign"></i></span>
                    <input type="number" step="0.01" min="0" class="form-control" name="amount_min" value="<?= escapeHtml($amount_min) ?>" placeholder="At least">
                    <input type="number" step="0.01" min="0" class="form-control" name="amount_max" value="<?= escapeHtml($amount_max) ?>" placeholder="At most">
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

        <?php exportTabsColumns('transactions'); ?>

    </div>
    <div class="modal-footer">
        <?php renderExportButtons('export_transactions'); ?>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
