<?php

require_once '../../../includes/modal_header.php';

enforceUserPermission('module_sales');

// Pre-filled from the recurring invoices page's current filters. Everything here is editable -
// the export runs whatever this form posts, not whatever the page happened to show.
$client_id = intval($_GET['client_id'] ?? 0);
if ($client_id) {
    enforceClientAccess();
}

// Search Filter
$q_filter = $_GET['q'] ?? '';

// Status Filter
$status_filter = $_GET['status'] ?? '';

// Date Filter - the all-time sentinels from filter_header.php leave the fields blank
$date_from_filter = (!empty($_GET['dtf']) && $_GET['dtf'] !== '1970-01-01') ? escapeHtml($_GET['dtf']) : '';
$date_to_filter = (!empty($_GET['dtt']) && $_GET['dtt'] !== '2099-12-31') ? escapeHtml($_GET['dtt']) : '';

ob_start();

?>

<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fas fa-fw fa-download me-2"></i>Export Recurring Invoices</h5>
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
                <input type="text" class="form-control" name="q" value="<?= stripslashes(escapeHtml($q_filter)) ?>" placeholder="Number, scope, frequency, client">
            </div>
        </div>

        <div class="mb-3">
            <label>Status</label>
            <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-toggle-on"></i></span>
                <select class="form-select select2" name="status">
                    <option value="">- Any Status -</option>
                    <option <?php if ($status_filter === 'active') { echo "selected"; } ?> value="active">Active</option>
                    <option <?php if ($status_filter === 'inactive') { echo "selected"; } ?> value="inactive">Inactive</option>
                </select>
            </div>
        </div>

        <div class="mb-3">
            <label>Created From</label>
            <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-calendar"></i></span>
                <input type="date" class="form-control" name="dtf" value="<?= $date_from_filter ?>" max="2999-12-31">
            </div>
        </div>

        <div class="mb-3">
            <label>Created To</label>
            <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-calendar"></i></span>
                <input type="date" class="form-control" name="dtt" value="<?= $date_to_filter ?>" max="2999-12-31">
            </div>
        </div>

        <?php exportTabsColumns('recurring_invoices'); ?>

    </div>
    <div class="modal-footer">
        <?php renderExportButtons('export_recurring_invoices'); ?>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
