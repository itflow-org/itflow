<?php

/*
 * Assets - one client's assets, or every asset the agent can see from All
 * Client Docs. Each row reads left to right the way a tech scans for a box:
 * what it is, how to reach it (IP, links), what it is physically (make,
 * model, serial, warranty), where it is (location, rack) and its status.
 * IPs, MACs and serials copy with one click.
 */

// Default Column Sortby Filter
$sort = "asset_name";
$order = "ASC";

// If client_id is in URI then show client Side Bar and client header
if (isset($_GET['client_id'])) {
    require_once "includes/inc_all_client.php";
    $client_query = "AND asset_client_id = $client_id";
    $client_url = "client_id=$client_id&";
    // Override Filter Header Archived
    if (isset($_GET['archived']) && $_GET['archived'] == 1) {
        $archived = 1;
        $archive_query = "asset_archived_at IS NOT NULL";
    } else {
        $archived = 0;
        $archive_query = "asset_archived_at IS NULL";
    }
} else {
    require_once "includes/inc_client_overview_all.php";
    $client_query = '';
    $client_url = '';
    // Override Filter Header Archived
    if (isset($_GET['archived']) && $_GET['archived'] == 1) {
        $archived = 1;
        $archive_query = "(client_archived_at IS NOT NULL OR asset_archived_at IS NOT NULL)";
    } else {
        $archived = 0;
        $archive_query = "(client_archived_at IS NULL AND asset_archived_at IS NULL)";
    }
}

// Perms
enforceUserPermission('module_support');

$can_edit = lookupUserPermission('module_support') >= 2;
$can_delete = lookupUserPermission('module_support') >= 3;

//Asset Type from GET
$asset_type_tabs = [
    'workstation' => ['label' => 'Workstations', 'icon' => 'fa-desktop', 'query' => "asset_type IN ('Desktop', 'Laptop')"],
    'server' => ['label' => 'Servers', 'icon' => 'fa-server', 'query' => "asset_type = 'Server'"],
    'virtual' => ['label' => 'Virtual', 'icon' => 'fa-cloud', 'query' => "asset_type = 'Virtual Machine'"],
    'network' => ['label' => 'Network', 'icon' => 'fa-network-wired', 'query' => "asset_type IN ('Firewall/Router', 'Switch', 'Access Point')"],
    'printer' => ['label' => 'Printers', 'icon' => 'fa-print', 'query' => "asset_type = 'Printer'"],
    'other' => ['label' => 'Other', 'icon' => 'fa-tag', 'query' => "asset_type NOT IN ('Desktop', 'Laptop', 'Server', 'Virtual Machine', 'Firewall/Router', 'Switch', 'Access Point', 'Printer')"],
];
$type_filter = (isset($_GET['type']) && isset($asset_type_tabs[$_GET['type']])) ? $_GET['type'] : '';
$_GET['type'] = $type_filter;
$type_query = $type_filter ? $asset_type_tabs[$type_filter]['query'] : "1 = 1";

$client = '';
if (!$client_url) {
    // Client Filter
    if (!empty($_GET['client'])) {
        $client = intval($_GET['client']);
        $client_query = "AND asset_client_id = $client";
    }
}

// Location Filter
if ($client_url && !empty($_GET['location'])) {
    $location_filter = intval($_GET['location']);
    $location_query = "AND asset_location_id = $location_filter";
} else {
    // Default - any
    $location_query = '';
    $location_filter = 0;
}

// Status Filter - the asset statuses (Admin > Categories), held by name
$status_filter = trim(strip_tags((string) ($_GET['status'] ?? '')));
$status_query = $status_filter !== '' ? "AND asset_status = '" . escapeSql($status_filter) . "'" : '';

// Tags Filter
if (isset($_GET['tags']) && is_array($_GET['tags']) && !empty($_GET['tags'])) {
    $tag_filter = implode(",", array_map('intval', $_GET['tags']));
    $tag_query = "AND asset_id IN (SELECT asset_tag_asset_id FROM asset_tags WHERE asset_tag_tag_id IN ($tag_filter))";
} else {
    $tag_filter = 0;
    $tag_query = '';
}

// Expiring In Filter
if (!empty($_GET['expire_days'])) {
    if ($_GET['expire_days'] == "expired") {
        $expire_days = "expired";
        $expire_query = "AND (asset_warranty_expire IS NOT NULL AND asset_warranty_expire != '0000-00-00' AND asset_warranty_expire < CURDATE())";
    } else {
        $expire_days = intval($_GET['expire_days']);
        $expire_query = "AND (asset_warranty_expire IS NOT NULL AND asset_warranty_expire != '0000-00-00' AND asset_warranty_expire BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL $expire_days DAY))";
    }
} else {
    // Default - any
    $expire_days = '';
    $expire_query = '';
}

// Extra columns, picked from the filter bar
$extra_column_options = [
    'Mac_Address' => 'MAC address',
    'Purchase_Date' => 'Purchase date',
    'Install_Date' => 'Install date',
    'Warranty_Expire' => 'Warranty',
];
$extra_columns = array_values(array_intersect(array_keys($extra_column_options), (array) ($_GET['show_column'] ?? [])));
$show_mac = in_array('Mac_Address', $extra_columns, true);
$show_purchase = in_array('Purchase_Date', $extra_columns, true);
$show_install = in_array('Install_Date', $extra_columns, true);
$show_warranty = in_array('Warranty_Expire', $extra_columns, true);

// Assigned To only means something for workstations and the like - printers are shared
$show_contact = !in_array($type_filter, ['network', 'server', 'printer', 'other'], true);
$show_model = $type_filter !== 'virtual';

// Only these sort - filter_header.php will hand through any lowercase word, and
// an unknown column takes the page down with a mysqli exception
$sortable_columns = [
    'asset_name', 'asset_type', 'asset_make', 'interface_ip', 'interface_mac', 'asset_purchase_date',
    'asset_install_date', 'asset_warranty_expire', 'contact_name', 'location_name', 'asset_status',
    'client_name', 'asset_created_at'
];
if (!in_array($sort, $sortable_columns, true)) {
    $sort = 'asset_name';
}
// Addresses sort by value - .9 before .10 - and this works for IPv4 and IPv6 alike
$order_by = $sort === 'interface_ip' ? "INET6_ATON(interface_ip) $order, interface_ip $order" : "$sort $order";

// Tab counts - within the client, status, tag and archive scope, before the search narrows it
$row = mysqli_fetch_assoc(mysqli_query($mysqli, "
    SELECT
        COUNT(*) AS all_count,
        SUM(asset_type IN ('Laptop', 'Desktop')) AS workstation_count,
        SUM(asset_type = 'Server') AS server_count,
        SUM(asset_type = 'Virtual Machine') AS virtual_count,
        SUM(asset_type IN ('Firewall/Router', 'Switch', 'Access Point')) AS network_count,
        SUM(asset_type = 'Printer') AS printer_count,
        SUM(asset_type NOT IN ('Laptop', 'Desktop', 'Server', 'Virtual Machine', 'Firewall/Router', 'Switch', 'Access Point', 'Printer')) AS other_count
    FROM assets
    LEFT JOIN clients ON client_id = asset_client_id
    WHERE $archive_query
    $status_query
    $tag_query
    " . clientScopeSql('asset_client_id') . "
    $client_query
"));
$tab_counts = [
    '' => intval($row['all_count']),
    'workstation' => intval($row['workstation_count']),
    'server' => intval($row['server_count']),
    'virtual' => intval($row['virtual_count']),
    'network' => intval($row['network_count']),
    'printer' => intval($row['printer_count']),
    'other' => intval($row['other_count']),
];

$sql = mysqli_query(
    $mysqli,
    "SELECT SQL_CALC_FOUND_ROWS asset_archived_at, asset_client_id, asset_contact_id, asset_description, asset_favorite,
        asset_id, asset_install_date, asset_location_id, asset_make, asset_model, asset_name, asset_os, asset_physical_location,
        asset_purchase_date, asset_serial, asset_status, asset_type, asset_uri, asset_uri_2,
        asset_uri_client, asset_warranty_expire, client_id, client_name,
        contact_archived_at, contact_name, contact_photo, interface_ip, interface_ipv6, interface_mac,
        location_archived_at, location_name FROM assets
    LEFT JOIN clients ON asset_client_id = client_id
    LEFT JOIN contacts ON asset_contact_id = contact_id
    LEFT JOIN locations ON asset_location_id = location_id
    LEFT JOIN asset_interfaces ON interface_asset_id = asset_id AND interface_primary = 1
    WHERE $archive_query
    $tag_query
    AND (asset_name LIKE '%$q%' OR asset_description LIKE '%$q%' OR asset_type LIKE '%$q%' OR interface_ip LIKE '%$q%' OR interface_ipv6 LIKE '%$q%' OR interface_mac LIKE '%$q%' OR asset_make LIKE '%$q%' OR asset_model LIKE '%$q%' OR asset_serial LIKE '%$q%' OR asset_os LIKE '%$q%' OR contact_name LIKE '%$q%' OR location_name LIKE '%$q%' OR client_name LIKE '%$q%'
        OR asset_id IN (SELECT asset_tag_asset_id FROM asset_tags JOIN tags ON tag_id = asset_tag_tag_id WHERE tag_name LIKE '%$q%'))
    AND ($type_query)
    " . clientScopeSql('asset_client_id') . "
    $status_query
    $location_query
    $expire_query
    $client_query
    GROUP BY asset_id
    ORDER BY $order_by LIMIT $record_from, $record_to"
);

$num_rows = mysqli_fetch_row(mysqli_query($mysqli, "SELECT FOUND_ROWS()"));

$assets = [];
while ($row = mysqli_fetch_assoc($sql)) {
    $assets[intval($row['asset_id'])] = $row;
}

// Tags and rack positions for the whole page in two queries, not two per row
$assets_tags = tagsForRecords('asset', array_keys($assets));
$assets_racked = [];
if ($assets) {
    $sql_racked = mysqli_query($mysqli, "SELECT unit_asset_id, unit_start_number, unit_end_number, rack_id, rack_name FROM rack_units
        JOIN racks ON rack_id = unit_rack_id
        WHERE unit_asset_id IN (" . implode(',', array_keys($assets)) . ")");
    while ($row = mysqli_fetch_assoc($sql_racked)) {
        $assets_racked[intval($row['unit_asset_id'])] = $row;
    }
}

$search_noun = $type_filter ? $asset_type_tabs[$type_filter]['label'] : 'assets';

?>

<div class="card">
    <div class="card-header bg-dark py-2">
        <h3 class="card-title mt-2"><i class="fas fa-fw fa-desktop me-2"></i>Assets</h3>
        <div class="card-tools">
            <?php if ($can_edit) { ?>
            <div class="btn-group">
                <button type="button" class="btn btn-primary ajax-modal" data-modal-size="lg" data-modal-url="modals/asset/asset_add.php?<?= $client_url ?>&type=<?= $type_filter ?>">
                    <i class="fas fa-plus me-2"></i>New <?= $type_filter ? rtrim($asset_type_tabs[$type_filter]['label'], 's') : 'Asset' ?>
                </button>
                <button type="button" class="btn btn-primary dropdown-toggle dropdown-toggle-split" data-bs-toggle="dropdown"></button>
                <div class="dropdown-menu">
                    <?php if ($client_url) { ?>
                    <a class="dropdown-item text-dark ajax-modal" href="#"
                        data-modal-url="modals/asset/asset_import.php?<?= $client_url ?>">
                        <i class="fa fa-fw fa-upload me-2"></i>Import
                    </a>
                    <?php } ?>
                    <?php if ($num_rows[0] > 0) { ?>
                        <?php if ($client_url) { ?>
                            <div class="dropdown-divider"></div>
                        <?php } ?>
                        <a class="dropdown-item text-dark ajax-modal" href="#"
                            data-modal-url="<?= buildExportModalUrl('modals/asset/asset_export.php', ['client_id', 'type', 'client', 'location', 'status', 'tags', 'expire_days', 'archived', 'q']) ?>">
                            <i class="fa fa-fw fa-download me-2"></i>Export
                        </a>
                    <?php } ?>
                </div>
            </div>
            <?php } ?>
        </div>
    </div>

    <?php if ($tab_counts['']) { ?>
        <!-- Kind of asset -->
        <div class="card-header py-2 itflow-asset-tabs">
            <nav class="nav nav-pills flex-nowrap overflow-auto">
                <a class="nav-link text-nowrap <?= $type_filter === '' ? 'active' : '' ?>" href="?<?= $url_query_strings_sort ?>&type=">
                    All <span class="badge rounded-pill ms-1"><?= $tab_counts[''] ?></span>
                </a>
                <?php foreach ($asset_type_tabs as $tab_key => $tab) {
                    if (!$tab_counts[$tab_key] && $type_filter !== $tab_key) {
                        continue;
                    } ?>
                    <a class="nav-link text-nowrap <?= $type_filter === $tab_key ? 'active' : '' ?>" href="?<?= $url_query_strings_sort ?>&type=<?= $tab_key ?>">
                        <i class="fas fa-fw <?= $tab['icon'] ?> me-1"></i><?= $tab['label'] ?> <span class="badge rounded-pill ms-1"><?= $tab_counts[$tab_key] ?></span>
                    </a>
                <?php } ?>
            </nav>
        </div>
    <?php } ?>

    <div class="card-header py-3">
        <form autocomplete="off">
            <?php if ($client_url) { ?>
            <input type="hidden" name="client_id" value="<?= $client_id ?>">
            <?php } ?>
            <input type="hidden" name="type" value="<?= $type_filter ?>">
            <input type="hidden" name="archived" value="<?= $archived ?>">
            <div class="row g-2 align-items-center">
                <div class="col-xl-3 col-lg-4 col-md-6">
                    <div class="input-group">
                        <input type="search" class="form-control" name="q" value="<?php if (isset($q)) { echo stripslashes(escapeHtml($q)); } ?>" placeholder="Search <?= strtolower($search_noun) ?>, IPs, serials...">
                        <button class="btn btn-dark" title="Search"><i class="fa fa-search"></i></button>
                    </div>
                </div>
                <?php if ($client_url) { ?>
                <div class="col-xl col-lg-4 col-md-6">
                    <select class="form-select select2" name="location" onchange="this.form.submit()">
                        <option value="">- All Locations -</option>
                        <?php
                        $sql_locations_filter = mysqli_query($mysqli, "
                            SELECT DISTINCT location_id, location_name
                            FROM locations
                            WHERE location_client_id = $client_id
                            AND ( EXISTS (SELECT 1 FROM assets WHERE asset_location_id = location_id  AND $archive_query) OR location_id = $location_filter)
                            ORDER BY location_name ASC
                        ");
                        while ($row = mysqli_fetch_assoc($sql_locations_filter)) {
                            $location_id = intval($row['location_id']);
                            $location_name = escapeHtml($row['location_name']);
                        ?>
                            <option <?php if ($location_filter == $location_id) { echo "selected"; } ?> value="<?= $location_id ?>"><?= $location_name ?></option>
                        <?php } ?>
                    </select>
                </div>
                <?php } else { ?>
                <div class="col-xl col-lg-4 col-md-6">
                    <select class="form-select select2" name="client" onchange="this.form.submit()">
                        <option value="" <?php if ($client == "") { echo "selected"; } ?>>- All Clients -</option>
                        <?php
                        $sql_clients_filter = mysqli_query($mysqli, "
                            SELECT DISTINCT client_id, client_name, client_abbreviation
                            FROM clients
                            JOIN assets ON asset_client_id = client_id
                            WHERE $archive_query
                            " . clientScopeSql('clients.client_id') . "
                            ORDER BY client_name ASC
                        ");
                        while ($row = mysqli_fetch_assoc($sql_clients_filter)) {
                            $client_id_filter = intval($row['client_id']);
                            $client_name_filter = escapeHtml($row['client_name']);
                        ?>
                            <option <?php if ($client == $client_id_filter) { echo "selected"; } ?> value="<?= $client_id_filter ?>"<?= selectOptionClient($row['client_id'], $row['client_name'], $row['client_abbreviation'] ?? '') ?>><?= $client_name_filter ?></option>
                        <?php } ?>
                    </select>
                </div>
                <?php } ?>
                <div class="col-xl col-lg-4 col-md-6">
                    <select class="form-select select2" name="status" onchange="this.form.submit()">
                        <option value="">- Status -</option>
                        <?= categoryFilterOptionsHtml('asset_status', $status_filter, "SELECT DISTINCT asset_status FROM assets
                            LEFT JOIN clients ON client_id = asset_client_id
                            WHERE $archive_query AND asset_status IS NOT NULL AND asset_status <> ''
                            " . clientScopeSql('asset_client_id') . " $client_query") ?>
                    </select>
                </div>
                <div class="col-xl col-lg-3 col-md-6">
                    <select onchange="this.form.submit()" class="form-select select2" name="tags[]" data-placeholder="- Tags -" multiple>
                        <?php
                        // Tags in use on assets in this scope, plus any already picked
                        $sql_tags_filter = mysqli_query($mysqli, "
                            SELECT tag_id, tag_name, tag_color, tag_icon
                            FROM tags
                            WHERE tag_type = 5 AND (tag_archived_at IS NULL OR tag_id IN ($tag_filter))
                            AND (
                                EXISTS (SELECT 1 FROM asset_tags JOIN assets ON asset_id = asset_tag_asset_id
                                    WHERE asset_tag_tag_id = tag_id " . clientScopeSql('asset_client_id') . " $client_query)
                                OR tag_id IN ($tag_filter)
                            )
                            ORDER BY tag_name ASC
                        ");
                        while ($row = mysqli_fetch_assoc($sql_tags_filter)) {
                            $tag_id = intval($row['tag_id']);
                            $tag_name = escapeHtml($row['tag_name']); ?>
                            <option value="<?= $tag_id ?>" <?php if (isset($_GET['tags']) && in_array($tag_id, $_GET['tags'])) { echo 'selected'; } ?><?= selectOptionColor($row['tag_color'] ?? '', $row['tag_icon'] ?? '') ?>><?= $tag_name ?></option>
                        <?php } ?>
                    </select>
                </div>
                <div class="col-xl col-lg-3 col-md-6">
                    <select class="form-select select2" name="expire_days" onchange="this.form.submit()">
                        <option value="" <?php if ($expire_days == "") { echo "selected"; } ?>>- Warranty -</option>
                        <option value="expired" <?php if ($expire_days === "expired") { echo "selected"; } ?>>Expired</option>
                        <?php foreach ([7, 30, 45, 60, 90] as $expire_option) { ?>
                            <option value="<?= $expire_option ?>" <?php if ($expire_days === $expire_option) { echo "selected"; } ?>>Expiring in <?= $expire_option ?> days</option>
                        <?php } ?>
                    </select>
                </div>
                <div class="col-xl col-lg-3 col-md-6">
                    <select onchange="this.form.submit()" class="form-select select2" name="show_column[]" data-placeholder="- More columns -" multiple>
                        <?php foreach ($extra_column_options as $column_key => $column_label) { ?>
                            <option value="<?= $column_key ?>" <?php if (in_array($column_key, $extra_columns, true)) { echo 'selected'; } ?>><?= $column_label ?></option>
                        <?php } ?>
                    </select>
                </div>
                <div class="col-xl-auto col-lg-3 col-md-12">
                    <div class="d-flex justify-content-end gap-2">
                        <a href="?<?= $client_url ?>archived=<?= $archived ? 0 : 1 ?>"
                            class="btn <?= $archived ? 'btn-dark' : 'btn-outline-dark' ?>" title="<?= $archived ? 'Back to active assets' : 'Show archived assets' ?>">
                            <i class="fa fa-fw fa-archive"></i><span class="d-lg-none ms-2">Archived</span>
                        </a>
                        <div class="dropdown" id="bulkActionButton" hidden>
                            <button class="btn btn-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                <i class="fas fa-fw fa-layer-group me-2"></i>Bulk Action (<span id="selectedCount"></span>)
                            </button>
                            <div class="dropdown-menu dropdown-menu-end">
                                <button class="dropdown-item"
                                    type="submit" form="bulkActions" name="bulk_favorite_assets">
                                    <i class="fas fa-fw fa-star text-warning me-2"></i>Favorite
                                </button>
                                <button class="dropdown-item"
                                    type="submit" form="bulkActions" name="bulk_unfavorite_assets">
                                    <i class="far fa-fw fa-star me-2"></i>Unfavorite
                                </button>
                                <div class="dropdown-divider"></div>
                                <?php if ($client_url) { ?>
                                <a class="dropdown-item ajax-modal" href="#"
                                    data-modal-url="modals/asset/asset_bulk_assign_contact.php?<?= $client_url ?>"
                                    data-bulk="true">
                                    <i class="fas fa-fw fa-user me-2"></i>Assign Contact
                                </a>
                                <a class="dropdown-item ajax-modal" href="#"
                                    data-modal-url="modals/asset/asset_bulk_assign_location.php?<?= $client_url ?>"
                                    data-bulk="true">
                                    <i class="fas fa-fw fa-map-marker-alt me-2"></i>Assign Location
                                </a>
                                <?php } ?>
                                <a class="dropdown-item ajax-modal" href="#"
                                    data-modal-url="modals/asset/asset_bulk_assign_physical_location.php"
                                    data-bulk="true">
                                    <i class="fas fa-fw fa-door-closed me-2"></i>Set Physical Location
                                </a>
                                <a class="dropdown-item ajax-modal" href="#"
                                    data-modal-url="modals/asset/asset_bulk_edit_status.php"
                                    data-bulk="true">
                                    <i class="fas fa-fw fa-info-circle me-2"></i>Set Status
                                </a>
                                <a class="dropdown-item" href="#" data-tag-picker data-tag-entity="asset" data-tag-bulk data-csrf="<?= $_SESSION['csrf_token'] ?>">
                                    <i class="fas fa-fw fa-tags me-2"></i>Tags
                                </a>
                                <div class="dropdown-divider"></div>
                                <a class="dropdown-item ajax-modal" href="#"
                                    data-modal-url="modals/asset/asset_bulk_add_ticket.php"
                                    data-modal-size="lg"
                                    data-bulk="true">
                                    <i class="fas fa-fw fa-life-ring me-2"></i>Create Tickets
                                </a>
                                <a class="dropdown-item ajax-modal" href="#"
                                    data-modal-url="modals/asset/asset_bulk_transfer_client.php?<?= $client_url ?>"
                                    data-bulk="true">
                                    <i class="fas fa-fw fa-arrow-right me-2"></i>Transfer to Client
                                </a>
                                <?php if ($archived) { ?>
                                    <div class="dropdown-divider"></div>
                                    <button class="dropdown-item text-info"
                                        type="submit" form="bulkActions" name="bulk_restore_assets">
                                        <i class="fas fa-fw fa-redo me-2"></i>Restore
                                    </button>
                                    <button class="dropdown-item text-danger text-bold"
                                        type="submit" form="bulkActions" name="bulk_delete_assets">
                                        <i class="fas fa-fw fa-trash me-2"></i>Delete
                                    </button>
                                <?php } else { ?>
                                    <div class="dropdown-divider"></div>
                                    <button class="dropdown-item text-danger confirm-link"
                                        type="submit" form="bulkActions" name="bulk_archive_assets">
                                        <i class="fas fa-fw fa-archive me-2"></i>Archive
                                    </button>
                                <?php } ?>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </form>
    </div>
    <form id="bulkActions" action="post.php" method="post" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 itflow-asset-table">
                <thead class="<?php if (!$num_rows[0]) { echo "d-none"; } ?> text-nowrap table-light">
                <tr>
                    <th class="checkbox-column border-end">
                        <div class="form-check">
                            <input class="form-check-input" id="selectAllCheckbox" type="checkbox" onclick="checkAll(this)" title="Select all on this page">
                        </div>
                    </th>
                    <th>
                        <a class="text-reset text-decoration-none" href="?<?= $url_query_strings_sort ?>&sort=asset_name&order=<?= $disp ?>">
                            Name <?php if ($sort == 'asset_name') { echo $order_icon; } ?>
                        </a>
                    </th>
                    <th>
                        <a class="text-reset text-decoration-none" href="?<?= $url_query_strings_sort ?>&sort=asset_type&order=<?= $disp ?>">
                            Type / OS <?php if ($sort == 'asset_type') { echo $order_icon; } ?>
                        </a>
                    </th>
                    <?php if ($show_model) { ?>
                        <th>
                            <a class="text-reset text-decoration-none" href="?<?= $url_query_strings_sort ?>&sort=asset_make&order=<?= $disp ?>">
                                Model / Serial <?php if ($sort == 'asset_make') { echo $order_icon; } ?>
                            </a>
                        </th>
                    <?php } ?>
                    <th>
                        <a class="text-reset text-decoration-none" href="?<?= $url_query_strings_sort ?>&sort=interface_ip&order=<?= $disp ?>">
                            IP <?php if ($sort == 'interface_ip') { echo $order_icon; } ?>
                        </a>
                    </th>
                    <?php if ($show_mac) { ?>
                        <th>
                            <a class="text-reset text-decoration-none" href="?<?= $url_query_strings_sort ?>&sort=interface_mac&order=<?= $disp ?>">
                                MAC <?php if ($sort == 'interface_mac') { echo $order_icon; } ?>
                            </a>
                        </th>
                    <?php } ?>
                    <?php if ($show_purchase) { ?>
                        <th>
                            <a class="text-reset text-decoration-none" href="?<?= $url_query_strings_sort ?>&sort=asset_purchase_date&order=<?= $disp ?>">
                                Purchased <?php if ($sort == 'asset_purchase_date') { echo $order_icon; } ?>
                            </a>
                        </th>
                    <?php } ?>
                    <?php if ($show_install) { ?>
                        <th>
                            <a class="text-reset text-decoration-none" href="?<?= $url_query_strings_sort ?>&sort=asset_install_date&order=<?= $disp ?>">
                                Installed <?php if ($sort == 'asset_install_date') { echo $order_icon; } ?>
                            </a>
                        </th>
                    <?php } ?>
                    <?php if ($show_warranty) { ?>
                        <th>
                            <a class="text-reset text-decoration-none" href="?<?= $url_query_strings_sort ?>&sort=asset_warranty_expire&order=<?= $disp ?>">
                                Warranty <?php if ($sort == 'asset_warranty_expire') { echo $order_icon; } ?>
                            </a>
                        </th>
                    <?php } ?>
                    <?php if ($show_contact) { ?>
                        <th>
                            <a class="text-reset text-decoration-none" href="?<?= $url_query_strings_sort ?>&sort=contact_name&order=<?= $disp ?>">
                                Assigned To <?php if ($sort == 'contact_name') { echo $order_icon; } ?>
                            </a>
                        </th>
                    <?php } ?>
                    <th>
                        <a class="text-reset text-decoration-none" href="?<?= $url_query_strings_sort ?>&sort=location_name&order=<?= $disp ?>">
                            Location <?php if ($sort == 'location_name') { echo $order_icon; } ?>
                        </a>
                    </th>
                    <th>
                        <a class="text-reset text-decoration-none" href="?<?= $url_query_strings_sort ?>&sort=asset_status&order=<?= $disp ?>">
                            Status <?php if ($sort == 'asset_status') { echo $order_icon; } ?>
                        </a>
                    </th>
                    <?php if (!$client_url) { ?>
                    <th>
                        <a class="text-reset text-decoration-none" href="?<?= $url_query_strings_sort ?>&sort=client_name&order=<?= $disp ?>">
                            Client <?php if ($sort == 'client_name') { echo $order_icon; } ?>
                        </a>
                    </th>
                    <?php } ?>
                    <th></th>
                </tr>

                </thead>
                <tbody>
                <?php

                foreach ($assets as $asset_id => $row) {
                    $client_id = intval($row['client_id']);
                    $client_name = escapeHtml($row['client_name']);
                    $asset_type = escapeHtml($row['asset_type']);
                    $asset_name = escapeHtml($row['asset_name']);
                    $asset_description = escapeHtml($row['asset_description']);
                    $asset_make_model = escapeHtml(trim($row['asset_make'] . ' ' . $row['asset_model']));
                    $asset_serial = escapeHtml($row['asset_serial']);
                    $asset_os = escapeHtml($row['asset_os']);
                    $asset_os_icon = assetOsIcon($row['asset_os']);
                    $asset_ip = escapeHtml($row['interface_ip']);
                    $asset_ipv6 = escapeHtml($row['interface_ipv6']);
                    $asset_mac = escapeHtml($row['interface_mac']);
                    $asset_links = assetLinks($row['asset_uri'], $row['asset_uri_2'], $row['asset_uri_client']);
                    $asset_status_pill = assetStatusPill($row['asset_status']);
                    $asset_purchase_date = escapeHtml($row['asset_purchase_date']);
                    $asset_install_date = escapeHtml($row['asset_install_date']);
                    $asset_warranty = assetWarranty($row['asset_warranty_expire']);
                    $asset_physical_location = escapeHtml($row['asset_physical_location']);
                    $asset_favorite = intval($row['asset_favorite']);
                    $asset_archived_at = escapeHtml($row['asset_archived_at']);
                    $asset_contact_id = intval($row['asset_contact_id']);
                    $asset_tone = assetTypeTone($row['asset_type']);
                    $device_icon = getAssetIcon($row['asset_type']);

                    $contact_name = escapeHtml($row['contact_name']);
                    $contact_archived = !empty($row['contact_archived_at']);
                    $location_name = escapeHtml($row['location_name']);
                    $location_archived = !empty($row['location_archived_at']);

                    $asset_rack = $assets_racked[$asset_id] ?? null;
                    $asset_tags = $assets_tags[$asset_id] ?? [];

                    ?>
                    <tr>
                        <td class="checkbox-column bg-light border-end">
                            <div class="form-check">
                                <input class="form-check-input bulk-select" type="checkbox" name="asset_ids[]" value="<?= $asset_id ?>" aria-label="Select <?= $asset_name ?>">
                            </div>
                        </td>
                        <!-- The whole cell opens the asset (stretched-link); the tag pills sit above it -->
                        <td class="position-relative itflow-asset-name-cell">
                            <div class="d-flex align-items-center gap-3">
                                <span class="itflow-asset-icon itflow-asset-tone-<?= $asset_tone ?>" aria-hidden="true"><i class="fas fa-fw fa-<?= $device_icon ?>"></i></span>
                                <div class="min-w-0">
                                    <a class="fw-semibold text-body stretched-link" href="asset.php?client_id=<?= $client_id ?>&asset_id=<?= $asset_id ?>"><?= $asset_name ?></a>
                                    <?php if ($asset_favorite) { ?><i class="fas fa-star text-warning ms-1" title="Favorite"></i><?php } ?>
                                    <?php if ($asset_archived_at) { ?><span class="badge rounded-pill text-bg-secondary fw-normal ms-1">Archived</span><?php } ?>
                                    <?php if ($asset_description) { ?>
                                        <div class="small text-body-secondary text-truncate itflow-asset-desc" title="<?= $asset_description ?>"><?= $asset_description ?></div>
                                    <?php } ?>
                                    <?php if ($asset_tags || lookupUserPermission('module_client') >= 2) { ?>
                                        <div class="mt-1 itflow-above-stretch"><?= tagPills('asset', $asset_id, $asset_tags, "assets.php?$client_url") ?></div>
                                    <?php } ?>
                                </div>
                            </div>
                        </td>

                        <td>
                            <div><?= $asset_type ?></div>
                            <?php if ($asset_os) { ?>
                                <div class="small text-body-secondary text-nowrap"><i class="<?= $asset_os_icon ?> fa-fw me-1"></i><?= $asset_os ?></div>
                            <?php } ?>
                        </td>

                        <?php if ($show_model) { ?>
                            <td class="itflow-asset-model-cell">
                                <div><?= $asset_make_model ?: '<span class="text-body-secondary">-</span>' ?></div>
                                <?php if ($asset_serial) { ?>
                                    <div class="small text-body-secondary text-nowrap">
                                        <span class="font-monospace"><?= $asset_serial ?></span><button type="button" class="itflow-copy clipboardjs" title="Copy serial" data-clipboard-text="<?= $asset_serial ?>"><i class="far fa-copy"></i></button>
                                    </div>
                                <?php } ?>
                                <?php if ($asset_warranty && $asset_warranty['tone'] !== 'success' && !$show_warranty) { ?>
                                    <span class="badge rounded-pill bg-<?= $asset_warranty['tone'] ?>-subtle text-<?= $asset_warranty['tone'] ?>-emphasis border border-<?= $asset_warranty['tone'] ?>-subtle fw-normal mt-1" title="Warranty <?= escapeHtml($asset_warranty['date']) ?>">
                                        <i class="fas fa-shield-alt me-1"></i>Warranty <?= strtolower($asset_warranty['short']) ?>
                                    </span>
                                <?php } ?>
                            </td>
                        <?php } ?>

                        <td class="text-nowrap">
                            <?php if ($asset_ip) { ?>
                                <span class="font-monospace"><?= $asset_ip ?></span><?php if ($asset_ip !== 'DHCP') { ?><button type="button" class="itflow-copy clipboardjs" title="Copy IP" data-clipboard-text="<?= $asset_ip ?>"><i class="far fa-copy"></i></button><?php } ?>
                            <?php } else { ?>
                                <span class="text-body-secondary">-</span>
                            <?php } ?>
                            <?php if ($asset_ipv6) { ?>
                                <div class="small text-body-secondary font-monospace text-truncate" style="max-width: 14rem" title="<?= $asset_ipv6 ?>"><?= $asset_ipv6 ?></div>
                            <?php } ?>
                            <?php if ($asset_links) { ?>
                                <div class="mt-1 d-flex gap-1">
                                    <?php foreach ($asset_links as [$link_label, $link_url]) { ?>
                                        <a class="btn btn-xs btn-outline-secondary" href="<?= escapeUrl($link_url) ?>" target="_blank" rel="noopener" title="<?= $link_label ?>: <?= escapeHtml($link_url) ?>">
                                            <i class="<?= assetLinkIcon($link_url) ?> me-1"></i><?= escapeHtml(assetLinkLabel($link_url)) ?>
                                        </a>
                                    <?php } ?>
                                </div>
                            <?php } ?>
                        </td>
                        <?php if ($show_mac) { ?>
                            <td class="text-nowrap">
                                <?php if ($asset_mac) { ?>
                                    <span class="font-monospace"><?= $asset_mac ?></span><button type="button" class="itflow-copy clipboardjs" title="Copy MAC" data-clipboard-text="<?= $asset_mac ?>"><i class="far fa-copy"></i></button>
                                <?php } else { ?>
                                    <span class="text-body-secondary">-</span>
                                <?php } ?>
                            </td>
                        <?php } ?>
                        <?php if ($show_purchase) { ?>
                            <td class="text-nowrap">
                                <?= $asset_purchase_date ?: '<span class="text-body-secondary">-</span>' ?>
                                <?php if ($asset_purchase_date) { ?><div class="small text-body-secondary"><?= assetAge($row['asset_purchase_date']) ?> old</div><?php } ?>
                            </td>
                        <?php } ?>
                        <?php if ($show_install) { ?>
                            <td class="text-nowrap"><?= $asset_install_date ?: '<span class="text-body-secondary">-</span>' ?></td>
                        <?php } ?>
                        <?php if ($show_warranty) { ?>
                            <td class="text-nowrap">
                                <?php if ($asset_warranty) { ?>
                                    <?= escapeHtml($asset_warranty['date']) ?>
                                    <div class="small text-<?= $asset_warranty['tone'] ?>-emphasis"><?= $asset_warranty['tone'] === 'success' ? 'Covered for ' . escapeHtml(str_replace('in ', '', timeAgo($asset_warranty['date']))) : escapeHtml($asset_warranty['text']) ?></div>
                                <?php } else { ?>
                                    <span class="text-body-secondary">-</span>
                                <?php } ?>
                            </td>
                        <?php } ?>
                        <?php if ($show_contact) { ?>
                            <td>
                                <?php if ($can_edit) { ?>
                                    <?= quickPick('asset_contact', $asset_id, (string) $asset_contact_id, assetQuickHtml('asset_contact', $row), 'list', 0, $client_id) ?>
                                <?php } elseif ($contact_name) { ?>
                                    <?= contactLink($client_id, $asset_contact_id, $row['contact_name'], $row['contact_photo'], ['archived' => $contact_archived]) ?>
                                <?php } else { ?>
                                    <span class="text-body-secondary">-</span>
                                <?php } ?>
                            </td>
                        <?php } ?>
                        <td>
                            <?php if ($can_edit) { ?>
                                <?= quickPick('asset_location', $asset_id, (string) intval($row['asset_location_id']), assetQuickHtml('asset_location', $row), 'list', 0, $client_id) ?>
                            <?php } elseif ($location_name) { ?>
                                <div class="<?= $location_archived ? 'text-danger text-decoration-line-through' : '' ?>" <?= $location_archived ? 'title="Archived location"' : '' ?>><?= $location_name ?></div>
                            <?php } elseif (!$asset_physical_location && !$asset_rack) { ?>
                                <span class="text-body-secondary">-</span>
                            <?php } ?>
                            <?php if ($asset_physical_location) { ?>
                                <div class="small text-body-secondary <?= $can_edit ? 'mt-1' : '' ?>"><?= $asset_physical_location ?></div>
                            <?php } ?>
                            <?php if ($asset_rack) { ?>
                                <a class="small text-nowrap d-inline-block <?= $can_edit && !$asset_physical_location ? 'mt-1' : '' ?>" href="rack.php?client_id=<?= $client_id ?>&rack_id=<?= intval($asset_rack['rack_id']) ?>" title="Racked">
                                    <i class="fas fa-server me-1"></i><?= escapeHtml($asset_rack['rack_name']) ?> &middot; <?= rackPositionLabel($asset_rack['unit_start_number'], $asset_rack['unit_end_number']) ?>
                                </a>
                            <?php } ?>
                        </td>
                        <td>
                            <?php if ($can_edit) { ?>
                                <?= quickPick('asset_status', $asset_id, (string) $row['asset_status'], assetQuickHtml('asset_status', $row), 'list', 0, $client_id) ?>
                            <?php } else { ?>
                                <?= $asset_status_pill ?: '<span class="text-body-secondary">-</span>' ?>
                            <?php } ?>
                        </td>
                        <?php if (!$client_url) { ?>
                        <td><a href="assets.php?client_id=<?= $client_id ?>"><?= $client_name ?></a></td>
                        <?php } ?>
                        <td class="text-end">
                            <?php if ($can_edit) { ?>
                                <div class="dropdown dropstart">
                                    <button class="btn btn-sm btn-light" title="More actions" type="button" data-bs-toggle="dropdown"><i class="fas fa-fw fa-ellipsis-v"></i></button>
                                    <div class="dropdown-menu">
                                        <a class="dropdown-item ajax-modal" href="#" data-modal-size="lg" data-modal-url="modals/asset/asset_edit.php?id=<?= $asset_id ?>">
                                            <i class="fas fa-fw fa-edit text-secondary me-2"></i>Edit
                                        </a>
                                        <a class="dropdown-item ajax-modal" href="#" data-modal-size="lg" data-modal-url="modals/asset/asset_copy.php?id=<?= $asset_id ?>">
                                            <i class="fas fa-fw fa-copy text-secondary me-2"></i>Copy
                                        </a>
                                        <div class="dropdown-divider"></div>
                                        <?php if ($asset_archived_at) { ?>
                                            <a class="dropdown-item" href="post.php?restore_asset=<?= $asset_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>">
                                                <i class="fas fa-fw fa-redo text-secondary me-2"></i>Restore
                                            </a>
                                            <?php if ($can_delete) { ?>
                                                <a class="dropdown-item text-danger text-bold confirm-link" href="post.php?delete_asset=<?= $asset_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>">
                                                    <i class="fas fa-fw fa-trash me-2"></i>Delete
                                                </a>
                                            <?php } ?>
                                        <?php } else { ?>
                                            <a class="dropdown-item text-danger confirm-link" href="post.php?archive_asset=<?= $asset_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>">
                                                <i class="fas fa-fw fa-archive me-2"></i>Archive
                                            </a>
                                        <?php } ?>
                                    </div>
                                </div>
                            <?php } ?>
                        </td>
                    </tr>

                <?php } ?>

                </tbody>
            </table>
        </div>
    </form>
    <?php
    $empty_state = [
        'thing' => $type_filter ? strtolower($asset_type_tabs[$type_filter]['label']) : 'assets',
        'icon' => $type_filter ? $asset_type_tabs[$type_filter]['icon'] : 'fa-desktop',
        'views' => ['type'],
        'text' => $type_filter ? '' : 'Their computers, servers, network gear and phones - what each is, where it is and who uses it.',
        'actions' => $can_edit ? array_merge(
            [['label' => 'New ' . ($type_filter ? rtrim($asset_type_tabs[$type_filter]['label'], 's') : 'Asset'), 'modal' => "modals/asset/asset_add.php?$client_url&type=$type_filter", 'size' => 'lg']],
            $client_url ? [['label' => 'Import', 'modal' => "modals/asset/asset_import.php?$client_url", 'icon' => 'fa-upload']] : []
        ) : [],
    ];
    ?>
    <?php require_once "../includes/filter_footer.php"; ?>
</div>

<script src="../js/bulk_actions.js"></script>

<?php
require_once "../includes/footer.php";
