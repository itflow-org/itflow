<?php

require_once '../../../includes/modal_header.php';

enforceUserPermission('module_client');

// Pre-filled from the clients page's current filters. Everything here is editable -
// the export runs whatever this form posts, not whatever the page happened to show.
$client_id = intval($_GET['client_id'] ?? 0);
if ($client_id) {
    enforceClientAccess();
}

// Search Filter
$q_filter = $_GET['q'] ?? '';

// Showing Filter
$leads_filter = $_GET['leads'] ?? '';

// Industry Filter
$industry_filter = $_GET['industry'] ?? '';

// Referral Filter
$referral_filter = $_GET['referral'] ?? '';

// Tags Filter
$tag_filter = (isset($_GET['tags']) && is_array($_GET['tags'])) ? array_map('intval', $_GET['tags']) : [];

// Date Filter - the all-time sentinels from filter_header.php leave the fields blank
$date_from_filter = (!empty($_GET['dtf']) && $_GET['dtf'] !== '1970-01-01') ? escapeHtml($_GET['dtf']) : '';
$date_to_filter = (!empty($_GET['dtt']) && $_GET['dtt'] !== '2099-12-31') ? escapeHtml($_GET['dtt']) : '';

// Archived Filter
$archived_filter = (isset($_GET['archived']) && $_GET['archived'] == 1) ? 1 : 0;

ob_start();

?>

<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fas fa-fw fa-download me-2"></i>Export Clients</h5>
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
                <input type="text" class="form-control" name="q" value="<?= stripslashes(escapeHtml($q_filter)) ?>" placeholder="Name, contact, address, tag">
            </div>
        </div>

        <div class="mb-3">
            <label>Showing</label>
            <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-user-friends"></i></span>
                <select class="form-select select2" name="leads">
                    <option value="">- Clients -</option>
                    <option <?php if ($leads_filter === '1') { echo "selected"; } ?> value="1">Leads</option>
                </select>
            </div>
        </div>

        <div class="mb-3">
            <label>Industry</label>
            <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-industry"></i></span>
                <select class="form-select select2" name="industry">
                    <option value="">- All Industries -</option>
                    <?php
                    $sql_industry_filter = mysqli_query($mysqli, "SELECT DISTINCT client_type FROM clients WHERE client_type != '' ORDER BY client_type ASC");
                    while ($row = mysqli_fetch_assoc($sql_industry_filter)) {
                        $filter_industry = escapeHtml($row['client_type']);
                    ?>
                        <option <?php if ($industry_filter === $row['client_type']) { echo "selected"; } ?> value="<?= $filter_industry ?>"><?= $filter_industry ?></option>
                    <?php
                    }
                    ?>
                </select>
            </div>
        </div>

        <div class="mb-3">
            <label>Referral</label>
            <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-share-alt"></i></span>
                <select class="form-select select2" name="referral">
                    <option value="">- All Referrals -</option>
                    <?php
                    $sql_referral_filter = mysqli_query($mysqli, "SELECT DISTINCT client_referral FROM clients WHERE client_referral != '' ORDER BY client_referral ASC");
                    while ($row = mysqli_fetch_assoc($sql_referral_filter)) {
                        $filter_referral = escapeHtml($row['client_referral']);
                    ?>
                        <option <?php if ($referral_filter === $row['client_referral']) { echo "selected"; } ?> value="<?= $filter_referral ?>"><?= $filter_referral ?></option>
                    <?php
                    }
                    ?>
                </select>
            </div>
        </div>

        <div class="mb-3">
            <label>Tags</label>
            <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-tags"></i></span>
                <select class="form-select select2" name="tags[]" data-placeholder="- All Tags -" multiple>
                    <?php
                    $sql_tags_filter = mysqli_query($mysqli, "SELECT tag_id, tag_name FROM tags WHERE tag_type = 1 ORDER BY tag_name ASC");
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

        <?php exportTabsColumns('clients'); ?>

    </div>
    <div class="modal-footer">
        <?php renderExportButtons('export_clients'); ?>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
