<?php

/*
 * Export services - pre-filled from the services list's view and filters.
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
$importance_filter = (string) ($_GET['importance'] ?? '');
$category_filter = (string) ($_GET['category'] ?? '');
$tag_filter = tagFilterIds();
$views = serviceViews();

ob_start();

?>

<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fas fa-fw fa-download me-2"></i>Export Services</h5>
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

        <div class="row g-3 mb-3">
            <div class="col-md-6">
                <label class="form-label">Importance</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-fw fa-thermometer-half"></i></span>
                    <select class="form-select select2" name="importance">
                        <option value="">- Any importance -</option>
                        <?php foreach (SERVICE_IMPORTANCE as $importance) { ?>
                            <option value="<?= $importance ?>" <?= $importance_filter === $importance ? 'selected' : '' ?>><?= $importance ?></option>
                        <?php } ?>
                    </select>
                </div>
            </div>
            <div class="col-md-6">
                <label class="form-label">Category</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-fw fa-folder"></i></span>
                    <input type="text" class="form-control" name="category" value="<?= escapeHtml($category_filter) ?>" placeholder="Any category">
                </div>
            </div>
        </div>

        <?= tagExportFieldHtml('service', $tag_filter) ?>

        <div class="mb-3">
            <label class="form-label">Search</label>
            <div class="input-group">
                <span class="input-group-text"><i class="fas fa-fw fa-search"></i></span>
                <input type="text" class="form-control" name="q" value="<?= stripslashes(escapeHtml($q_filter)) ?>" placeholder="Name, description, category, client">
            </div>
        </div>

        <?php if (!$client_id) { ?>
            <div class="mb-3">
                <label class="form-label">Client</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-fw fa-user"></i></span>
                    <select class="form-select select2" name="client">
                        <option value="">- All Clients -</option>
                        <?php
                        $sql = mysqli_query($mysqli, "SELECT client_id, client_name, client_abbreviation FROM clients
                            WHERE client_archived_at IS NULL AND EXISTS (SELECT 1 FROM services WHERE service_client_id = client_id) " . clientScopeSql('clients.client_id') . " ORDER BY client_name ASC");
                        while ($row = mysqli_fetch_assoc($sql)) { ?>
                            <option value="<?= intval($row['client_id']) ?>" <?= $client_filter === (string) intval($row['client_id']) ? 'selected' : '' ?><?= selectOptionClient($row['client_id'], $row['client_name'], $row['client_abbreviation'] ?? '') ?>><?= escapeHtml($row['client_name']) ?></option>
                        <?php } ?>
                    </select>
                </div>
            </div>
        <?php } ?>

        <?php exportTabsColumns('services'); ?>

    </div>
    <div class="modal-footer">
        <?php renderExportButtons('export_services'); ?>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
