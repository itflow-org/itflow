<?php

require_once '../../../includes/modal_header.php';

enforceUserPermission('module_support');

// Pre-filled from the assets page's current filters. Everything here is editable -
// the export runs whatever this form posts, not whatever the page happened to show.
$client_id = intval($_GET['client_id'] ?? 0);
if ($client_id) {
    enforceClientAccess();
}

// Search Filter
$q_filter = $_GET['q'] ?? '';

// Type Filter
$type_filter = $_GET['type'] ?? '';

// Client Filter
$client_filter = intval($_GET['client'] ?? 0);

// Location Filter
$location_filter = intval($_GET['location'] ?? 0);

// Tags Filter
$tag_filter = (isset($_GET['tags']) && is_array($_GET['tags'])) ? array_map('intval', $_GET['tags']) : [];

// Expiring In Filter
$expire_filter = $_GET['expire_days'] ?? '';

// Archived Filter
$archived_filter = (isset($_GET['archived']) && $_GET['archived'] == 1) ? 1 : 0;

ob_start();

?>

<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fas fa-fw fa-download mr-2"></i>Export Assets</h5>
    <button type="button" class="close text-white" data-dismiss="modal">
        <span>&times;</span>
    </button>
</div>

<?php exportTabsNav(); ?>

<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="client_id" value="<?= $client_id ?>">

    <div class="modal-body">

        <?php exportTabsFiltersOpen(); ?>

        <div class="form-group">
            <label>Search</label>
            <div class="input-group">
                <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-search"></i></span>
                </div>
                <input type="text" class="form-control" name="q" value="<?= stripslashes(escapeHtml($q_filter)) ?>" placeholder="Name, serial, IP, OS">
            </div>
        </div>

        <div class="form-group">
            <label>Type</label>
            <div class="input-group">
                <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-desktop"></i></span>
                </div>
                <select class="form-control select2" name="type">
                    <option value="">- All Types -</option>
                    <option <?php if ($type_filter === 'workstation') { echo "selected"; } ?> value="workstation">Workstations</option>
                    <option <?php if ($type_filter === 'server') { echo "selected"; } ?> value="server">Servers</option>
                    <option <?php if ($type_filter === 'virtual') { echo "selected"; } ?> value="virtual">Virtual Machines</option>
                    <option <?php if ($type_filter === 'network') { echo "selected"; } ?> value="network">Network Devices</option>
                    <option <?php if ($type_filter === 'other') { echo "selected"; } ?> value="other">Other</option>
                </select>
            </div>
        </div>

        <?php if (!$client_id) { ?>
        <div class="form-group">
            <label>Client</label>
            <div class="input-group">
                <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-user"></i></span>
                </div>
                <select class="form-control select2" name="client">
                    <option value="">- All Clients -</option>
                    <?php
                    $sql_clients_filter = mysqli_query($mysqli, "SELECT client_id, client_name FROM clients WHERE EXISTS (SELECT 1 FROM assets WHERE asset_client_id = client_id) ORDER BY client_name ASC");
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

        <?php if ($client_id) { ?>
        <div class="form-group">
            <label>Location</label>
            <div class="input-group">
                <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-map-marker-alt"></i></span>
                </div>
                <select class="form-control select2" name="location">
                    <option value="">- All Locations -</option>
                    <?php
                    $sql_locations_filter = mysqli_query($mysqli, "SELECT location_id, location_name FROM locations WHERE location_client_id = $client_id AND location_archived_at IS NULL ORDER BY location_name ASC");
                    while ($row = mysqli_fetch_assoc($sql_locations_filter)) {
                        $filter_location_id = intval($row['location_id']);
                        $filter_location_name = escapeHtml($row['location_name']);
                    ?>
                        <option <?php if ($location_filter == $filter_location_id) { echo "selected"; } ?> value="<?= $filter_location_id ?>"><?= $filter_location_name ?></option>
                    <?php
                    }
                    ?>
                </select>
            </div>
        </div>
        <?php } ?>

        <div class="form-group">
            <label>Tags</label>
            <div class="input-group">
                <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-tags"></i></span>
                </div>
                <select class="form-control select2" name="tags[]" data-placeholder="- All Tags -" multiple>
                    <?php
                    $sql_tags_filter = mysqli_query($mysqli, "SELECT tag_id, tag_name FROM tags WHERE tag_type = 5 ORDER BY tag_name ASC");
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

        <div class="form-group">
            <label>Warranty Expiring In</label>
            <div class="input-group">
                <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-hourglass-half"></i></span>
                </div>
                <select class="form-control select2" name="expire_days">
                    <option value="">- Any -</option>
                    <option <?php if ($expire_filter === 'expired') { echo "selected"; } ?> value="expired">Expired</option>
                    <?php foreach ([7, 30, 45, 60, 90] as $expire_option) { ?>
                        <option <?php if ($expire_filter !== '' && $expire_filter == $expire_option) { echo "selected"; } ?> value="<?= $expire_option ?>"><?= $expire_option ?> Days</option>
                    <?php } ?>
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

        <?php exportTabsColumns('assets'); ?>

    </div>
    <div class="modal-footer">
        <?php renderExportButtons('export_assets'); ?>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
