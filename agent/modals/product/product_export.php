<?php

require_once '../../../includes/modal_header.php';

enforceUserPermission('module_sales');

// Pre-filled from the products page's current filters. Everything here is editable -
// the export runs whatever this form posts, not whatever the page happened to show.
$client_id = intval($_GET['client_id'] ?? 0);
if ($client_id) {
    enforceClientAccess();
}

// Search Filter
$q_filter = $_GET['q'] ?? '';

// Type Filter
$type_filter = $_GET['type'] ?? '';

// Category Filter
$category_filter = intval($_GET['category'] ?? 0);

// Vendor Filter
$vendor_filter = intval($_GET['vendor'] ?? 0);

// Archived Filter
$archived_filter = (isset($_GET['archived']) && $_GET['archived'] == 1) ? 1 : 0;

ob_start();

?>

<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fas fa-fw fa-download me-2"></i>Export Products</h5>
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
                <input type="text" class="form-control" name="q" value="<?= stripslashes(escapeHtml($q_filter)) ?>" placeholder="Name, code, description">
            </div>
        </div>

        <div class="mb-3">
            <label>Type</label>
            <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-box"></i></span>
                <select class="form-select select2" name="type">
                    <option value="">- Everything -</option>
                    <option <?php if ($type_filter === 'service') { echo "selected"; } ?> value="service">Services</option>
                    <option <?php if ($type_filter === 'product') { echo "selected"; } ?> value="product">Products</option>
                    <option <?php if ($type_filter === 'recurring') { echo "selected"; } ?> value="recurring">Billed each month, quarter or year</option>
                    <option <?php if ($type_filter === 'stocked') { echo "selected"; } ?> value="stocked">Stock tracked</option>
                    <option <?php if ($type_filter === 'reorder') { echo "selected"; } ?> value="reorder">To reorder</option>
                    <option <?php if ($type_filter === 'hardware') { echo "selected"; } ?> value="hardware">Hardware - becomes a client asset</option>
                </select>
            </div>
        </div>

        <div class="mb-3">
            <label>Category</label>
            <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-list"></i></span>
                <select class="form-select select2" name="category">
                    <option value="">- All Categories -</option>
                    <?= categoryFilterOptionsHtml('Income', $category_filter, "SELECT DISTINCT product_category_id FROM products") ?>
                </select>
            </div>
        </div>

        <div class="mb-3">
            <label>Vendor</label>
            <div class="input-group">
                <span class="input-group-text"><i class="fa fa-fw fa-building"></i></span>
                <select class="form-select select2" name="vendor">
                    <option value="">- All Vendors -</option>
                    <?php foreach (productVendors() as $filter_vendor_id => $filter_vendor_name) { ?>
                        <option <?php if ($vendor_filter === $filter_vendor_id) { echo "selected"; } ?> value="<?= $filter_vendor_id ?>"><?= escapeHtml($filter_vendor_name) ?></option>
                    <?php } ?>
                </select>
            </div>
        </div>

        <div class="mb-3">
            <label>Archived</label>
            <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-archive"></i></span>
                <select class="form-select select2" name="archived">
                    <option <?php if (!$archived_filter) { echo "selected"; } ?> value="0">Active only</option>
                    <option <?php if ($archived_filter) { echo "selected"; } ?> value="1">Archived only</option>
                </select>
            </div>
        </div>

        <?php exportTabsColumns('products'); ?>

    </div>
    <div class="modal-footer">
        <?php renderExportButtons('export_products'); ?>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
