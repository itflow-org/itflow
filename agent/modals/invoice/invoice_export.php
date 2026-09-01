<?php

require_once '../../../includes/modal_header.php';

enforceUserPermission('module_sales');

// Pre-filled from the invoices page's current filters. Everything here is editable -
// the export runs whatever this form posts, not whatever the page happened to show.
$client_id = intval($_GET['client_id'] ?? 0);
if ($client_id) {
    enforceClientAccess();
}

// Search Filter
$q_filter = $_GET['q'] ?? '';

// Status Filter
$status_filter = $_GET['status'] ?? '';

// Category Filter
$category_filter = intval($_GET['category'] ?? 0);

// Date Filter - the all-time sentinels from filter_header.php leave the fields blank
$date_from_filter = (!empty($_GET['dtf']) && $_GET['dtf'] !== '1970-01-01') ? escapeHtml($_GET['dtf']) : '';
$date_to_filter = (!empty($_GET['dtt']) && $_GET['dtt'] !== '2099-12-31') ? escapeHtml($_GET['dtt']) : '';

ob_start();

?>

<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fas fa-fw fa-download me-2"></i>Export Invoices</h5>
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
                <input type="text" class="form-control" name="q" value="<?= stripslashes(escapeHtml($q_filter)) ?>" placeholder="Number, scope, client, amount">
            </div>
        </div>

        <div class="mb-3">
            <label>Status</label>
            <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-file-invoice-dollar"></i></span>
                <select class="form-select select2" name="status">
                    <option value="">- All Statuses -</option>
                    <option <?php if ($status_filter === 'Draft') { echo "selected"; } ?> value="Draft">Draft</option>
                    <option <?php if ($status_filter === 'Unpaid') { echo "selected"; } ?> value="Unpaid">Unpaid</option>
                    <option <?php if ($status_filter === 'Overdue') { echo "selected"; } ?> value="Overdue">Overdue</option>
                </select>
            </div>
        </div>

        <div class="mb-3">
            <label>Category</label>
            <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-list"></i></span>
                <select class="form-select select2" name="category">
                    <option value="">- All Categories -</option>
                    <?php
                    $sql_category_filter = mysqli_query($mysqli, "SELECT category_id, category_name FROM categories WHERE category_type = 'Income' ORDER BY category_name ASC");
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
            <label>Issued From</label>
            <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-calendar"></i></span>
                <input type="date" class="form-control" name="dtf" value="<?= $date_from_filter ?>" max="2999-12-31">
            </div>
        </div>

        <div class="mb-3">
            <label>Issued To</label>
            <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-calendar"></i></span>
                <input type="date" class="form-control" name="dtt" value="<?= $date_to_filter ?>" max="2999-12-31">
            </div>
        </div>

        <?php exportTabsColumns('invoices'); ?>

    </div>
    <div class="modal-footer">
        <?php renderExportButtons('export_invoices'); ?>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
