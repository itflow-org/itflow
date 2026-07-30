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

// Archived Filter
$archived_filter = (isset($_GET['archived']) && $_GET['archived'] == 1) ? 1 : 0;

ob_start();

?>

<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fas fa-fw fa-download mr-2"></i>Export Products</h5>
    <button type="button" class="close text-white" data-dismiss="modal">
        <span>&times;</span>
    </button>
</div>

<?php exportTabsNav(); ?>

<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

    <div class="modal-body">

        <?php exportTabsFiltersOpen(); ?>

        <div class="form-group">
            <label>Search</label>
            <div class="input-group">
                <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-search"></i></span>
                </div>
                <input type="text" class="form-control" name="q" value="<?= stripslashes(escapeHtml($q_filter)) ?>" placeholder="Name, code, description">
            </div>
        </div>

        <div class="form-group">
            <label>Type</label>
            <div class="input-group">
                <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-box"></i></span>
                </div>
                <select class="form-control select2" name="type">
                    <option value="">- All Types -</option>
                    <option <?php if ($type_filter === 'product') { echo "selected"; } ?> value="product">Products</option>
                    <option <?php if ($type_filter === 'service') { echo "selected"; } ?> value="service">Services</option>
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

        <div class="form-group">
            <label>Archived</label>
            <div class="input-group">
                <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-archive"></i></span>
                </div>
                <select class="form-control select2" name="archived">
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
