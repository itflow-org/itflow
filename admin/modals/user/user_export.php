<?php

require_once '../../includes/modal_header.php';

// Pre-filled from the users page's current filters. Everything here is editable -
// the export runs whatever this form posts, not whatever the page happened to show.
$q_filter = $_GET['q'] ?? '';
$archived_filter = (isset($_GET['archived']) && $_GET['archived'] == 1) ? 1 : 0;
$roles = userRoles();
$role_filter = isset($roles[intval($_GET['role'] ?? 0)]) ? intval($_GET['role']) : 0;

ob_start();

?>

<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fas fa-fw fa-download me-2"></i>Export Users</h5>
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
                <input type="text" class="form-control" name="q" value="<?= escapeHtml($q_filter) ?>" placeholder="Name, email or role">
            </div>
        </div>

        <div class="mb-3">
            <label class="form-label">Role</label>
            <div class="input-group">
                <span class="input-group-text"><i class="fa fa-fw fa-user-shield"></i></span>
                <select class="form-select select2" name="role">
                    <option value="">- Any role -</option>
                    <?php foreach ($roles as $role_id => $role) { ?>
                        <option value="<?= $role_id ?>" <?= $role_filter === $role_id ? 'selected' : '' ?><?= selectOptionRole($role) ?>><?= escapeHtml($role['role_name']) ?></option>
                    <?php } ?>
                </select>
            </div>
        </div>

        <div class="mb-3">
            <label class="form-label">Archived</label>
            <div class="input-group">
                <span class="input-group-text"><i class="fa fa-fw fa-archive"></i></span>
                <select class="form-select select2" name="archived">
                    <option <?php if (!$archived_filter) { echo "selected"; } ?> value="0">Not archived</option>
                    <option <?php if ($archived_filter) { echo "selected"; } ?> value="1">Archived only</option>
                </select>
            </div>
        </div>

        <?php exportTabsColumns('users'); ?>

    </div>
    <div class="modal-footer">
        <?php renderExportButtons('export_users'); ?>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
