<?php

/*
 * Racks - every rack a client has (or, from All Client Docs, every rack the
 * agent can see), each as a card with a scale drawing of its front, how full it
 * is and what it draws. A card opens the rack (rack.php).
 */

// Default Column Sortby Filter
$sort = "rack_name";
$order = "ASC";

// If client_id is in URI then show client Side Bar and client header
if (isset($_GET['client_id'])) {
    require_once "includes/inc_all_client.php";
    $client_query = "AND rack_client_id = $client_id";
    $client_url = "client_id=$client_id&";
} else {
    require_once "includes/inc_client_overview_all.php";
    $client_query = '';
    $client_url = '';
}

// Perms
enforceUserPermission('module_support');

$can_edit = lookupUserPermission('module_support') >= 2;
$can_delete = lookupUserPermission('module_support') >= 3;

// Archived - from All Client Docs, an archived client's racks count as archived too
$archived = (isset($_GET['archived']) && $_GET['archived'] == 1) ? 1 : 0;
if ($client_url) {
    $archive_query = $archived ? "rack_archived_at IS NOT NULL" : "rack_archived_at IS NULL";
} else {
    $archive_query = $archived
        ? "(rack_archived_at IS NOT NULL OR client_archived_at IS NOT NULL)"
        : "(rack_archived_at IS NULL AND client_archived_at IS NULL)";
}

// Client filter (All Client Docs only)
$client_filter = 0;
if (!$client_url && !empty($_GET['client'])) {
    $client_filter = intval($_GET['client']);
    $client_query = "AND rack_client_id = $client_filter";
}

// Location filter (one client only)
$location_filter = 0;
$location_query = '';
if ($client_url && !empty($_GET['location'])) {
    $location_filter = intval($_GET['location']);
    $location_query = "AND rack_location_id = $location_filter";
}

// Type filter - the rack types (Admin > Categories), held by name
$type_filter = trim(strip_tags((string) ($_GET['type'] ?? '')));
$type_query = $type_filter !== '' ? "AND rack_type = '" . escapeSql($type_filter) . "'" : '';

// Only these sort - filter_header.php will hand through any lowercase word
if (!in_array($sort, ['rack_name', 'rack_units', 'client_name', 'location_name', 'rack_created_at'], true)) {
    $sort = 'rack_name';
}

$sql = mysqli_query(
    $mysqli,
    "SELECT SQL_CALC_FOUND_ROWS client_id, client_name, location_name, rack_id, rack_client_id, rack_name, rack_type,
        rack_model, rack_units, rack_units_descending, rack_power_capacity, rack_physical_location, rack_archived_at
    FROM racks
    LEFT JOIN clients ON client_id = rack_client_id
    LEFT JOIN locations ON location_id = rack_location_id
    WHERE $archive_query
    AND (rack_name LIKE '%$q%' OR rack_type LIKE '%$q%' OR rack_model LIKE '%$q%' OR rack_description LIKE '%$q%'
        OR rack_physical_location LIKE '%$q%' OR location_name LIKE '%$q%' OR client_name LIKE '%$q%'
        OR EXISTS (SELECT 1 FROM rack_units LEFT JOIN assets ON asset_id = unit_asset_id
            WHERE unit_rack_id = rack_id AND (unit_device LIKE '%$q%' OR asset_name LIKE '%$q%' OR asset_serial LIKE '%$q%')))
    " . clientScopeSql('rack_client_id') . "
    $client_query
    $location_query
    $type_query
    ORDER BY $sort $order LIMIT $record_from, $record_to"
);

$num_rows = mysqli_fetch_row(mysqli_query($mysqli, "SELECT FOUND_ROWS()"));

$racks = [];
while ($row = mysqli_fetch_assoc($sql)) {
    $racks[intval($row['rack_id'])] = $row;
}

// Every item on the page in one query, not one per rack
$racks_units = rackLoadUnits(array_keys($racks));

// Totals across what's listed, for the summary line
$total_units = 0;
$total_used = 0;
$total_items = 0;
foreach ($racks as $rack_id => $row) {
    $racks[$rack_id]['occupancy'] = rackOccupancy($racks_units[$rack_id], max(1, intval($row['rack_units'])));
    $total_units += max(1, intval($row['rack_units']));
    $total_used += $racks[$rack_id]['occupancy']['any'];
    $total_items += count($racks_units[$rack_id]);
}

?>

<div class="card">
    <div class="card-header bg-dark py-2">
        <h3 class="card-title mt-2"><i class="fas fa-fw fa-server me-2"></i>Racks</h3>
        <?php if ($client_url && $can_edit) { ?>
            <div class="card-tools">
                <button type="button" class="btn btn-primary ajax-modal" data-modal-size="lg" data-modal-url="modals/rack/rack_add.php?client_id=<?= $client_id ?>">
                    <i class="fas fa-plus me-2"></i>New Rack
                </button>
            </div>
        <?php } ?>
    </div>
    <div class="card-header py-3">
        <form autocomplete="off">
            <?php if ($client_url) { ?>
                <input type="hidden" name="client_id" value="<?= $client_id ?>">
            <?php } ?>
            <input type="hidden" name="archived" value="<?= $archived ?>">
            <div class="row g-2 align-items-center">

                <div class="col-lg-3 col-md-4">
                    <div class="input-group">
                        <input type="search" class="form-control" name="q" value="<?php if (isset($q)) { echo stripslashes(escapeHtml($q)); } ?>" placeholder="Search racks and what's in them">
                        <button class="btn btn-dark" title="Search"><i class="fa fa-search"></i></button>
                    </div>
                </div>

                <?php if ($client_url) { ?>
                    <div class="col-lg-2 col-md-4">
                        <select class="form-select select2" name="location" onchange="this.form.submit()">
                            <option value="">- All Locations -</option>
                            <?php
                            $sql_locations_filter = mysqli_query($mysqli, "SELECT location_id, location_name FROM locations
                                WHERE location_client_id = $client_id
                                AND (EXISTS (SELECT 1 FROM racks WHERE rack_location_id = location_id) OR location_id = $location_filter)
                                ORDER BY location_name ASC");
                            while ($row = mysqli_fetch_assoc($sql_locations_filter)) {
                                $location_id_filter = intval($row['location_id']);
                                ?>
                                <option value="<?= $location_id_filter ?>" <?php if ($location_filter === $location_id_filter) { echo "selected"; } ?>><?= escapeHtml($row['location_name']) ?></option>
                            <?php } ?>
                        </select>
                    </div>
                <?php } else { ?>
                    <div class="col-lg-2 col-md-4">
                        <select class="form-select select2" name="client" onchange="this.form.submit()">
                            <option value="">- All Clients -</option>
                            <?php
                            $sql_clients_filter = mysqli_query($mysqli, "SELECT DISTINCT client_id, client_name, client_abbreviation FROM clients
                                JOIN racks ON rack_client_id = client_id
                                WHERE 1 = 1
                                " . clientScopeSql('clients.client_id') . "
                                ORDER BY client_name ASC");
                            while ($row = mysqli_fetch_assoc($sql_clients_filter)) {
                                $client_id_filter = intval($row['client_id']);
                                ?>
                                <option value="<?= $client_id_filter ?>" <?php if ($client_filter === $client_id_filter) { echo "selected"; } ?><?= selectOptionClient($row['client_id'], $row['client_name'], $row['client_abbreviation'] ?? '') ?>><?= escapeHtml($row['client_name']) ?></option>
                            <?php } ?>
                        </select>
                    </div>
                <?php } ?>

                <div class="col-lg-2 col-md-4">
                    <select class="form-select select2" name="type" onchange="this.form.submit()">
                        <option value="">- All Types -</option>
                        <?= categoryFilterOptionsHtml('rack_type', $type_filter, "SELECT DISTINCT rack_type FROM racks
                            LEFT JOIN clients ON client_id = rack_client_id
                            WHERE rack_type IS NOT NULL AND rack_type <> '' AND $archive_query
                            " . clientScopeSql('rack_client_id') . " $client_query") ?>
                    </select>
                </div>

                <div class="col-lg-5 col-md-12 d-flex align-items-center justify-content-md-end gap-3">
                    <?php if ($racks) { ?>
                        <span class="small text-body-secondary d-none d-xl-inline">
                            <?= number_format($total_used) ?> of <?= number_format($total_units) ?>U in use &middot; <?= number_format($total_items) ?> items<?= intval($num_rows[0]) > count($racks) ? ' on this page' : '' ?>
                        </span>
                    <?php } ?>
                    <a href="?<?= $client_url ?>archived=<?= $archived ? 0 : 1 ?>" class="btn btn-<?= $archived ? 'primary' : 'default' ?> text-nowrap">
                        <i class="fa fa-fw fa-archive me-2"></i>Archived
                    </a>
                </div>

            </div>
        </form>
    </div>

    <div class="card-body">

        <?php if (!$racks) { ?>

            <?= emptyStateHtml([
                'thing' => 'racks',
                'icon' => 'fa-server',
                'filtered' => $q || $location_filter || $client_filter || $type_filter !== '',
                'archived' => $archived,
                'text' => "Document the comms closet: what's racked where, front and rear, and how much room and power is left.",
                'actions' => $client_url && $can_edit ? [['label' => 'New Rack', 'modal' => "modals/rack/rack_add.php?client_id=$client_id", 'size' => 'lg']] : [],
            ]) ?>

        <?php } else { ?>

            <div class="row g-3">
                <?php foreach ($racks as $rack_id => $row) {
                    $rack_client_id = intval($row['rack_client_id']);
                    $rack_name = escapeHtml($row['rack_name']);
                    $rack_type = escapeHtml($row['rack_type']);
                    $rack_units = max(1, intval($row['rack_units']));
                    $rack_units_descending = intval($row['rack_units_descending']);
                    $rack_power_capacity = intval($row['rack_power_capacity']);
                    $rack_physical_location = escapeHtml($row['rack_physical_location']);
                    $rack_location_name = escapeHtml($row['location_name']);
                    $rack_client_name = escapeHtml($row['client_name']);
                    $rack_archived_at = $row['rack_archived_at'];
                    $occupancy = $row['occupancy'];
                    $used_percent = (int) round($occupancy['any'] / $rack_units * 100);
                    $usage_tone = $used_percent >= 90 ? 'danger' : ($used_percent >= 75 ? 'warning' : 'primary');
                    $rack_items = array_map('rackUnitView', $racks_units[$rack_id]);
                    $power_used = array_sum(array_column($rack_items, 'power'));
                    $rack_url = "rack.php?client_id=$rack_client_id&rack_id=$rack_id";
                    ?>
                    <div class="col-md-6 col-xl-4 col-xxl-3">
                        <div class="card h-100 mb-0 itflow-rack-card">
                            <div class="card-body d-flex gap-3">

                                <?php /* The front, to scale: a full-depth item from the rear fills it too */ ?>
                                <div class="itflow-rack-mini" style="--rack-rows: <?= $rack_units ?>" aria-hidden="true">
                                    <?php foreach ($rack_items as $item) {
                                        if (!rackUnitOnFace($item['face'], $item['full_depth'], 'front')) {
                                            continue;
                                        } ?>
                                        <span class="itflow-rack-tone-<?= $item['tone'] ?><?= $item['face'] !== 'front' ? ' is-ghost' : '' ?>" style="grid-row: <?= rackGridRow($item['start'], $item['end'], $rack_units, $rack_units_descending) ?> / span <?= $item['height'] ?>"></span>
                                    <?php } ?>
                                </div>

                                <div class="flex-grow-1 min-w-0 d-flex flex-column">
                                    <div class="d-flex align-items-start gap-2">
                                        <div class="min-w-0 flex-grow-1">
                                            <a class="stretched-link text-reset text-decoration-none" href="<?= $rack_url ?>">
                                                <h3 class="h6 fw-semibold mb-0 text-truncate" title="<?= $rack_name ?>"><?= $rack_name ?></h3>
                                            </a>
                                            <?php if (!$client_url) { ?>
                                                <div class="small text-truncate"><?= $rack_client_name ?></div>
                                            <?php } ?>
                                            <div class="small text-body-secondary text-truncate">
                                                <?= $rack_units ?>U<?= $rack_type ? " &middot; $rack_type" : '' ?>
                                            </div>
                                        </div>
                                        <?php if ($rack_archived_at) { ?>
                                            <span class="badge rounded-pill text-bg-secondary fw-normal">Archived</span>
                                        <?php } ?>
                                        <?php if ($can_edit) { ?>
                                            <div class="dropdown dropstart itflow-rack-card-menu">
                                                <button class="btn btn-sm btn-link text-body-secondary px-1" title="More actions" type="button" data-bs-toggle="dropdown">
                                                    <i class="fas fa-fw fa-ellipsis-v"></i>
                                                </button>
                                                <div class="dropdown-menu">
                                                    <?php if (!$rack_archived_at) { ?>
                                                        <a class="dropdown-item ajax-modal" href="#" data-modal-url="modals/rack/rack_device_add.php?id=<?= $rack_id ?>">
                                                            <i class="fas fa-fw fa-plus text-secondary me-2"></i>Add Item
                                                        </a>
                                                    <?php } ?>
                                                    <a class="dropdown-item ajax-modal" href="#" data-modal-size="lg" data-modal-url="modals/rack/rack_edit.php?id=<?= $rack_id ?>">
                                                        <i class="fas fa-fw fa-edit text-secondary me-2"></i>Edit
                                                    </a>
                                                    <div class="dropdown-divider"></div>
                                                    <?php if ($rack_archived_at) { ?>
                                                        <a class="dropdown-item confirm-link" href="post.php?restore_rack=<?= $rack_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>">
                                                            <i class="fas fa-fw fa-redo text-secondary me-2"></i>Restore
                                                        </a>
                                                    <?php } else { ?>
                                                        <a class="dropdown-item text-danger confirm-link" href="post.php?archive_rack=<?= $rack_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>">
                                                            <i class="fas fa-fw fa-archive me-2"></i>Archive
                                                        </a>
                                                    <?php } ?>
                                                    <?php if ($can_delete) { ?>
                                                        <a class="dropdown-item text-danger text-bold confirm-link" href="post.php?delete_rack=<?= $rack_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>">
                                                            <i class="fas fa-fw fa-trash me-2"></i>Delete
                                                        </a>
                                                    <?php } ?>
                                                </div>
                                            </div>
                                        <?php } ?>
                                    </div>

                                    <?php if ($rack_location_name || $rack_physical_location) { ?>
                                        <div class="small text-body-secondary text-truncate mt-1">
                                            <i class="fas fa-fw fa-map-marker-alt me-1"></i><?= $rack_location_name ?><?= $rack_location_name && $rack_physical_location ? ' &rsaquo; ' : '' ?><?= $rack_physical_location ?>
                                        </div>
                                    <?php } ?>

                                    <div class="mt-auto pt-3">
                                        <div class="d-flex justify-content-between small mb-1">
                                            <span><span class="fw-semibold"><?= $occupancy['any'] ?></span><span class="text-body-secondary"> / <?= $rack_units ?>U used</span></span>
                                            <span class="text-<?= $usage_tone ?>-emphasis fw-semibold"><?= $used_percent ?>%</span>
                                        </div>
                                        <div class="progress" style="height: .375rem" role="progressbar" aria-label="Units used" aria-valuenow="<?= $used_percent ?>" aria-valuemin="0" aria-valuemax="100">
                                            <div class="progress-bar bg-<?= $usage_tone ?>" style="width: <?= $used_percent ?>%"></div>
                                        </div>
                                        <div class="d-flex flex-wrap column-gap-3 small text-body-secondary mt-2">
                                            <span><i class="fas fa-fw fa-cubes me-1"></i><?= count($rack_items) ?> item<?= count($rack_items) === 1 ? '' : 's' ?></span>
                                            <?php if ($occupancy['largest_gap']['size']) { ?>
                                                <span title="The tallest full-depth item that still fits"><i class="fas fa-fw fa-arrows-alt-v me-1"></i><?= $occupancy['largest_gap']['size'] ?>U gap</span>
                                            <?php } ?>
                                            <?php if ($power_used || $rack_power_capacity) { ?>
                                                <span class="<?= $rack_power_capacity && $power_used > $rack_power_capacity ? 'text-danger' : '' ?>">
                                                    <i class="fas fa-fw fa-bolt me-1"></i><?= number_format($power_used) ?><?= $rack_power_capacity ? ' / ' . number_format($rack_power_capacity) : '' ?> W
                                                </span>
                                            <?php } ?>
                                        </div>
                                    </div>
                                </div>

                            </div>
                        </div>
                    </div>
                <?php } ?>
            </div>

        <?php } ?>

    </div>
    <?php
    // Paging only - the empty state above is this page's own
    if (intval($num_rows[0]) > 0) {
        require_once "../includes/filter_footer.php";
    }
    ?>
</div>

<?php
require_once "../includes/footer.php";
