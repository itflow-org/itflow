<?php

/*
 * Export projects - pre-filled from the projects list's view and filters.
 * Everything here is editable; the export runs whatever this form posts.
 */

require_once '../../../includes/modal_header.php';

enforceUserPermission('module_support');

$client_id = intval($_GET['client_id'] ?? 0);
if ($client_id) {
    enforceClientAccess();
}

$q_filter = $_GET['q'] ?? '';
$show_filter = (string) ($_GET['show'] ?? '');
$client_filter = (string) ($_GET['client'] ?? '');
$manager_filter = (string) ($_GET['manager'] ?? '');
$views = projectViews();

ob_start();

?>

<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fas fa-fw fa-download me-2"></i>Export Projects</h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>

<?php exportTabsNav(); ?>

<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="client_id" value="<?= $client_id ?>">

    <div class="modal-body">

        <?php exportTabsFiltersOpen(); ?>

        <div class="mb-3">
            <label class="form-label">Which</label>
            <div class="input-group">
                <span class="input-group-text"><i class="fas fa-fw fa-eye"></i></span>
                <select class="form-select select2" name="show">
                    <?php foreach ($views as $key => $view) { ?>
                        <option value="<?= escapeHtml($key) ?>" <?= $show_filter === $key ? 'selected' : '' ?>><?= escapeHtml($view['label']) ?></option>
                    <?php } ?>
                </select>
            </div>
        </div>

        <div class="mb-3">
            <label class="form-label">Search</label>
            <div class="input-group">
                <span class="input-group-text"><i class="fas fa-fw fa-search"></i></span>
                <input type="text" class="form-control" name="q" value="<?= stripslashes(escapeHtml($q_filter)) ?>" placeholder="Number, name, client, manager">
            </div>
        </div>

        <div class="mb-3">
            <label class="form-label">Manager</label>
            <div class="input-group">
                <span class="input-group-text"><i class="fas fa-fw fa-user-tie"></i></span>
                <select class="form-select select2" name="manager">
                    <option value="">- Any manager -</option>
                    <option value="none" <?= $manager_filter === 'none' ? 'selected' : '' ?>>No manager</option>
                    <?php
                    $sql = mysqli_query($mysqli, "SELECT user_id, user_name, user_avatar FROM users WHERE user_type = 1 AND EXISTS (SELECT 1 FROM projects WHERE project_manager = user_id) ORDER BY user_name ASC");
                    while ($row = mysqli_fetch_assoc($sql)) { ?>
                        <option value="<?= intval($row['user_id']) ?>" <?= $manager_filter === (string) intval($row['user_id']) ? 'selected' : '' ?><?= selectOptionUser($row['user_id'], $row['user_name'], $row['user_avatar'] ?? '') ?>><?= escapeHtml($row['user_name']) ?></option>
                    <?php } ?>
                </select>
            </div>
        </div>

        <?php if (!$client_id) { ?>
            <div class="mb-3">
                <label class="form-label">Client</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-fw fa-user"></i></span>
                    <select class="form-select select2" name="client">
                        <option value="">- All Clients -</option>
                        <option value="internal" <?= $client_filter === 'internal' ? 'selected' : '' ?>>Internal (no client)</option>
                        <?php
                        $sql = mysqli_query($mysqli, "SELECT client_id, client_name, client_abbreviation FROM clients
                            WHERE EXISTS (SELECT 1 FROM projects WHERE project_client_id = client_id) " . clientScopeSql('clients.client_id') . " ORDER BY client_name ASC");
                        while ($row = mysqli_fetch_assoc($sql)) { ?>
                            <option value="<?= intval($row['client_id']) ?>" <?= $client_filter === (string) intval($row['client_id']) ? 'selected' : '' ?><?= selectOptionClient($row['client_id'], $row['client_name'], $row['client_abbreviation'] ?? '') ?>><?= escapeHtml($row['client_name']) ?></option>
                        <?php } ?>
                    </select>
                </div>
            </div>
        <?php } ?>

        <?php exportTabsColumns('projects'); ?>

    </div>
    <div class="modal-footer">
        <?php renderExportButtons('export_projects'); ?>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
