<?php

/*
 * Export the app logs to CSV or PDF - pre-filled from the list's filters and
 * level, every one editable: the export runs what this form posts, not what
 * the page happened to show (export_app_logs, admin/post/app_log.php).
 */

require_once '../../includes/modal_header.php';

$views = appLogViews();
$q_filter = is_string($_GET['q'] ?? null) ? $_GET['q'] : '';
$view_filter = is_string($_GET['view'] ?? null) && isset($views[$_GET['view']]) ? $_GET['view'] : '';
$category_filter = is_string($_GET['category'] ?? null) ? $_GET['category'] : '';
$date_from_filter = (is_string($_GET['dtf'] ?? null) && appLogDate($_GET['dtf']) && $_GET['dtf'] !== '1970-01-01') ? $_GET['dtf'] : '';
$date_to_filter = (is_string($_GET['dtt'] ?? null) && appLogDate($_GET['dtt']) && $_GET['dtt'] !== '2099-12-31') ? $_GET['dtt'] : '';

ob_start();

?>

<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fas fa-fw fa-download me-2"></i>Export App Logs</h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>

<?php exportTabsNav(); ?>

<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

    <div class="modal-body">

        <?php exportTabsFiltersOpen(); ?>

        <?php require 'app_log_filters.php'; ?>

        <?php exportTabsColumns('app_logs'); ?>

    </div>
    <div class="modal-footer">
        <?php renderExportButtons('export_app_logs'); ?>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
