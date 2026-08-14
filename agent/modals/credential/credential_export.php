<?php

require_once '../../../includes/modal_header.php';

enforceUserPermission('module_credential');

// Pre-filled from the credentials page's current filters. Everything here is editable -
// the export runs whatever this form posts, not whatever the page happened to show.
$client_id = intval($_GET['client_id'] ?? 0);
if ($client_id) {
    enforceClientAccess();
}

// Search Filter
$q_filter = $_GET['q'] ?? '';

// Client Filter
$client_filter = intval($_GET['client'] ?? 0);

// Tags Filter
$tag_filter = (isset($_GET['tags']) && is_array($_GET['tags'])) ? array_map('intval', $_GET['tags']) : [];

// Archived Filter
$archived_filter = (isset($_GET['archived']) && $_GET['archived'] == 1) ? 1 : 0;

ob_start();

?>

<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fas fa-fw fa-download me-2"></i>Export Credentials</h5>
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
                <input type="text" class="form-control" name="q" value="<?= stripslashes(escapeHtml($q_filter)) ?>" placeholder="Name, description, URI, tag">
            </div>
        </div>

        <?php if (!$client_id) { ?>
        <div class="mb-3">
            <label>Client</label>
            <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-user"></i></span>
                <select class="form-control select2" name="client">
                    <option value="">- All Clients -</option>
                    <?php
                    $sql_clients_filter = mysqli_query($mysqli, "SELECT client_id, client_name FROM clients WHERE EXISTS (SELECT 1 FROM credentials WHERE credential_client_id = client_id) ORDER BY client_name ASC");
                    while ($row = mysqli_fetch_assoc($sql_clients_filter)) {
                        $filter_client_id = intval($row['client_id']);
                        $filter_client_name = escapeHtml($row['client_name']);
                    ?>
                        <option <?php if ($client_filter == $filter_client_id) { echo "selected"; } ?> value="<?= $filter_client_id ?>"><?= $filter_client_name ?></option>
                    <?php
                    }
                    ?>
                </select>
            </div>
        </div>
        <?php } ?>

        <div class="mb-3">
            <label>Tags</label>
            <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-tags"></i></span>
                <select class="form-control select2" name="tags[]" data-placeholder="- All Tags -" multiple>
                    <?php
                    $sql_tags_filter = mysqli_query($mysqli, "SELECT tag_id, tag_name FROM tags WHERE tag_type = 4 ORDER BY tag_name ASC");
                    while ($row = mysqli_fetch_assoc($sql_tags_filter)) {
                        $filter_tag_id = intval($row['tag_id']);
                        $filter_tag_name = escapeHtml($row['tag_name']);
                    ?>
                        <option <?php if (in_array($filter_tag_id, $tag_filter, true)) { echo "selected"; } ?> value="<?= $filter_tag_id ?>"><?= $filter_tag_name ?></option>
                    <?php
                    }
                    ?>
                </select>
            </div>
        </div>

        <div class="mb-3">
            <label>Archived</label>
            <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-archive"></i></span>
                <select class="form-control select2" name="archived">
                    <option <?php if (!$archived_filter) { echo "selected"; } ?> value="0">Active only</option>
                    <option <?php if ($archived_filter) { echo "selected"; } ?> value="1">Archived only</option>
                </select>
            </div>
        </div>

        <?php exportTabsColumns('credentials'); ?>

    </div>
    <div class="modal-footer">
        <?php renderExportButtons('export_credentials'); ?>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
