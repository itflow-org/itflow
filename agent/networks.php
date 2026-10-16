<?php

/*
 * Networks - one client's, or every network the agent can see from All Client
 * Docs. Each row is a subnet: what it is for (its type, as the tile), the
 * CIDR, VLAN and gateway, the DHCP range, the site, and how full it is - the
 * addresses documented on it and on the devices set to it, against its size -
 * with an amber edge when its address plan has a problem (two devices on one
 * address, a fixed address inside the DHCP range, a device outside the
 * subnet). The pills along the top split them by type.
 *
 * Searching for an address finds the networks it is in - 192.168.10.57 finds
 * the 192.168.10.0/24 networks - and the networks it is documented on.
 */

// Default Column Sortby Filter
$sort = "network_name";
$order = "ASC";

// If client_id is in URI then show client Side Bar and client header
if (isset($_GET['client_id'])) {
    require_once "includes/inc_all_client.php";
    $client_query = "AND network_client_id = $client_id";
    $client_url = "client_id=$client_id&";
} else {
    require_once "includes/inc_client_overview_all.php";
    $client_query = 'AND clients.client_archived_at IS NULL';
    $client_url = '';
}

// Perms
enforceUserPermission('module_support');

// filter_header.php fills in canned_date=custom when no range was asked for - not a filter here, so not carried into links
if (($_GET['canned_date'] ?? '') === 'custom' && empty($_GET['dtf'])) {
    unset($_GET['canned_date']);
}

$can_edit = networkCan(2);
$can_delete = networkCan(3) && !empty($config_destructive_deletes_enable);

// Client (all clients)
$client_filter = 0;
if (!$client_url && !empty($_GET['client'])) {
    $client_filter = intval($_GET['client']);
    $client_query .= " AND network_client_id = $client_filter";
}

// Site (one client)
$location_filter = $client_url ? intval($_GET['location'] ?? 0) : 0;
$location_query = $location_filter ? "AND network_location_id = $location_filter" : '';

// Searching for an address finds the networks it is in - IPv4 or IPv6, done here rather than in SQL
$q_raw = trim((string) ($_GET['q'] ?? ''));
$contains_query = '';
$q_ip = normalizeIpAddress($q_raw);
if ($q_ip !== false) {
    $ids = [];
    $sql = mysqli_query($mysqli, "SELECT network_id, network FROM networks LEFT JOIN clients ON client_id = network_client_id WHERE 1 " . clientScopeSql('network_client_id') . " $client_query");
    while ($row = mysqli_fetch_assoc($sql)) {
        if (networkSubnetInfo($row['network']) && isIpInSubnet($q_ip, $row['network'])) {
            $ids[] = intval($row['network_id']);
        }
    }
    $contains_query = $ids ? ' OR network_id IN (' . implode(',', $ids) . ')' : '';
}

$filters = "(network_name LIKE '%$q%' OR network_description LIKE '%$q%' OR network_type LIKE '%$q%' OR network_vlan LIKE '%$q%' OR network LIKE '%$q%'
        OR network_gateway LIKE '%$q%' OR network_primary_dns LIKE '%$q%' OR network_secondary_dns LIKE '%$q%' OR network_dhcp_range LIKE '%$q%'
        OR location_name LIKE '%$q%' OR vendor_name LIKE '%$q%' OR client_name LIKE '%$q%'
        OR EXISTS (SELECT 1 FROM network_ips WHERE ip_network_id = network_id AND (ip_address LIKE '%$q%' OR ip_hostname LIKE '%$q%' OR ip_mac LIKE '%$q%'))
        $contains_query)
    " . clientScopeSql('network_client_id') . " $client_query $location_query";

$joins = "LEFT JOIN clients ON client_id = network_client_id
    LEFT JOIN locations ON location_id = network_location_id AND network_location_id > 0
    LEFT JOIN vendors ON vendor_id = network_vendor_id AND network_vendor_id > 0";

// The views and the type
$shows = [
    '' => ['label' => 'Active', 'icon' => 'fa-network-wired', 'where' => 'network_archived_at IS NULL'],
    'starred' => ['label' => 'Starred', 'icon' => 'fa-star', 'where' => 'network_archived_at IS NULL AND network_favorite = 1'],
    'archived' => ['label' => 'Archived', 'icon' => 'fa-archive', 'where' => 'network_archived_at IS NOT NULL'],
];
$show = isset($shows[$_GET['show'] ?? '']) ? ($_GET['show'] ?? '') : '';
$type_filter = isset(NETWORK_TYPES[$_GET['type'] ?? '']) ? $_GET['type'] : '';
$view_query = 'AND ' . $shows[$show]['where'] . ($type_filter ? " AND network_type = '$type_filter'" : '');

// Only these sort - an unknown column would take the page down
$sortable_columns = [
    'network_name' => "network_name $order",
    'client_name' => "client_name $order, network_name ASC",
    'network' => "INET6_ATON(SUBSTRING_INDEX(network, '/', 1)) $order, network_name ASC",
    'network_vlan' => "network_vlan IS NULL, network_vlan $order, network_name ASC",
    'network_type' => "FIELD(network_type, '" . implode("', '", array_keys(NETWORK_TYPES)) . "') $order, network_name ASC",
    'location_name' => "location_name IS NULL, location_name $order, network_name ASC",
];
if (!isset($sortable_columns[$sort])) {
    $sort = 'network_name';
}

$sql = mysqli_query($mysqli, "SELECT SQL_CALC_FOUND_ROWS networks.*, client_name, location_name, vendor_name FROM networks $joins
    WHERE $filters $view_query
    ORDER BY network_favorite DESC, {$sortable_columns[$sort]}, network_id DESC
    LIMIT $record_from, $record_to");

$num_rows = mysqli_fetch_row(mysqli_query($mysqli, "SELECT FOUND_ROWS()"));

$networks = [];
while ($row = mysqli_fetch_assoc($sql)) {
    $networks[intval($row['network_id'])] = $row;
}
$addresses = networkAddresses(array_keys($networks));
$usage = networkUsage($networks, $addresses);
$problems = [];
foreach ($networks as $network_id => $row) {
    $problems[$network_id] = networkAddressPlan($row, $addresses)['problems'];
}

// How many of each, for the pills - the views over what the filters leave, the types within the view
$view_counts = [];
$row = mysqli_fetch_row(mysqli_query($mysqli, "SELECT " . implode(', ', array_map(function ($s) {
    return "COALESCE(SUM({$s['where']}), 0)";
}, $shows)) . " FROM networks $joins WHERE $filters"));
foreach (array_keys($shows) as $i => $key) {
    $view_counts[$key] = intval($row[$i]);
}
$type_counts = [];
$sql_types = mysqli_query($mysqli, "SELECT network_type, COUNT(*) FROM networks $joins WHERE $filters AND {$shows[$show]['where']} GROUP BY network_type");
while ($row = mysqli_fetch_row($sql_types)) {
    $key = isset(NETWORK_TYPES[$row[0]]) ? $row[0] : 'Other';
    $type_counts[$key] = ($type_counts[$key] ?? 0) + intval($row[1]);
}

$filter_url = function ($changes) {
    $params = array_merge($_GET, $changes);
    unset($params['page']);
    return '?' . http_build_query(array_filter($params, function ($v) { return $v !== '' && $v !== null; }));
};

$sort_link = function ($column, $label) use ($url_query_strings_sort, $sort, $disp, $order_icon) {
    return '<a class="text-reset text-decoration-none" href="?' . escapeHtml($url_query_strings_sort) . '&amp;sort=' . $column . '&amp;order=' . $disp . '">'
        . $label . ($sort === $column ? " $order_icon" : '') . '</a>';
};

$csrf = $_SESSION['csrf_token'];

?>

<div class="card">
    <div class="card-header bg-dark py-2">
        <h3 class="card-title mt-2"><i class="fas fa-fw fa-network-wired me-2"></i>Networks</h3>
        <div class="card-tools">
            <div class="btn-group">
                <?php if ($can_edit) { ?>
                    <button type="button" class="btn btn-primary ajax-modal" data-modal-size="lg" data-modal-url="modals/network/network_add.php?<?= $client_url ?>location_id=<?= $location_filter ?>&type=<?= escapeHtml($type_filter) ?>">
                        <i class="fas fa-plus"></i><span class="d-none d-lg-inline ms-2">New Network</span>
                    </button>
                <?php } ?>
                <button type="button" class="btn btn-primary dropdown-toggle <?= $can_edit ? 'dropdown-toggle-split' : '' ?>" data-bs-toggle="dropdown"><?= $can_edit ? '' : '<i class="fas fa-ellipsis-h"></i>' ?></button>
                <div class="dropdown-menu dropdown-menu-end">
                    <?php if ($num_rows[0] > 0) { ?>
                        <a class="dropdown-item text-dark ajax-modal" href="#" data-modal-url="<?= buildExportModalUrl('modals/network/network_export.php', ['client_id', 'client', 'location', 'type', 'q']) ?>&archived=<?= $show === 'archived' ? 1 : 0 ?>">
                            <i class="fa fa-fw fa-download me-2"></i>Export
                        </a>
                    <?php } ?>
                    <?php if ($can_edit && $client_url) { ?>
                        <a class="dropdown-item text-dark ajax-modal" href="#" data-modal-url="modals/network/network_import.php?<?= $client_url ?>">
                            <i class="fa fa-fw fa-upload me-2"></i>Import
                        </a>
                    <?php } ?>
                </div>
            </div>
        </div>
    </div>

    <div class="card-header py-3">
        <form autocomplete="off">
            <?php if ($client_url) { ?>
                <input type="hidden" name="client_id" value="<?= $client_id ?>">
            <?php } ?>
            <input type="hidden" name="show" value="<?= escapeHtml($show) ?>">
            <input type="hidden" name="type" value="<?= escapeHtml($type_filter) ?>">
            <div class="row g-2 align-items-center">
                <div class="col-lg-4 col-md-6">
                    <div class="input-group">
                        <input type="search" class="form-control" name="q" value="<?= escapeHtml($q_raw) ?>" placeholder="Search networks or an address..." title="Name, subnet, VLAN, gateway, DNS, site, provider - or an IP address, to find the network it is in">
                        <button class="btn btn-dark" title="Search"><i class="fa fa-search"></i></button>
                    </div>
                </div>
                <?php if ($client_url) { ?>
                    <div class="col-lg-3 col-md-6">
                        <select class="form-select select2" name="location" onchange="this.form.submit()">
                            <option value="">- Any site -</option>
                            <?php
                            $sql_locations_filter = mysqli_query($mysqli, "SELECT location_id, location_name, location_primary FROM locations
                                WHERE location_client_id = $client_id AND (EXISTS (SELECT 1 FROM networks WHERE network_location_id = location_id) OR location_id = $location_filter)
                                ORDER BY location_name ASC");
                            while ($row = mysqli_fetch_assoc($sql_locations_filter)) { ?>
                                <option value="<?= intval($row['location_id']) ?>" <?= $location_filter === intval($row['location_id']) ? 'selected' : '' ?> data-icon="fas fa-<?= intval($row['location_primary']) ? 'building' : 'map-marker-alt' ?>"><?= escapeHtml($row['location_name']) ?></option>
                            <?php } ?>
                        </select>
                    </div>
                <?php } else { ?>
                    <div class="col-lg-3 col-md-6">
                        <select class="form-select select2" name="client" onchange="this.form.submit()">
                            <option value="">- All Clients -</option>
                            <?php
                            $sql_clients_filter = mysqli_query($mysqli, "SELECT client_id, client_name, client_abbreviation FROM clients
                                WHERE client_archived_at IS NULL AND (EXISTS (SELECT 1 FROM networks WHERE network_client_id = client_id) OR client_id = $client_filter)
                                " . clientScopeSql('clients.client_id') . " ORDER BY client_name ASC");
                            while ($row = mysqli_fetch_assoc($sql_clients_filter)) {
                                $client_id_filter = intval($row['client_id']); ?>
                                <option <?= $client_filter === $client_id_filter ? 'selected' : '' ?> value="<?= $client_id_filter ?>"<?= selectOptionClient($row['client_id'], $row['client_name'], $row['client_abbreviation'] ?? '') ?>><?= escapeHtml($row['client_name']) ?></option>
                            <?php } ?>
                        </select>
                    </div>
                <?php } ?>
                <div class="col-lg-5 col-md-12">
                    <div class="d-flex justify-content-end gap-2">
                        <?php if ($can_edit) { ?>
                            <div class="dropdown" id="bulkActionButton" hidden>
                                <button class="btn btn-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                    <i class="fas fa-fw fa-layer-group me-2"></i>Bulk Action (<span id="selectedCount">0</span>)
                                </button>
                                <div class="dropdown-menu dropdown-menu-end">
                                    <a class="dropdown-item ajax-modal" href="#" data-modal-url="modals/network/network_bulk_edit.php?<?= $client_url ?>" data-bulk="true">
                                        <i class="fas fa-fw fa-edit me-2"></i>Type and site
                                    </a>
                                    <button class="dropdown-item" type="submit" form="bulkActions" name="bulk_favorite_networks">
                                        <i class="fas fa-fw fa-star text-warning me-2"></i>Star
                                    </button>
                                    <button class="dropdown-item" type="submit" form="bulkActions" name="bulk_unfavorite_networks">
                                        <i class="far fa-fw fa-star me-2"></i>Unstar
                                    </button>
                                    <div class="dropdown-divider"></div>
                                    <?php if ($show === 'archived') { ?>
                                        <button class="dropdown-item" type="submit" form="bulkActions" name="bulk_restore_networks">
                                            <i class="fas fa-fw fa-redo me-2"></i>Restore
                                        </button>
                                        <?php if ($can_delete) { ?>
                                            <button class="dropdown-item text-danger text-bold confirm-link" type="submit" form="bulkActions" name="bulk_delete_networks"
                                                data-confirm-text="The networks and the addresses documented on them go for good. The devices on them are left on no network.">
                                                <i class="fas fa-fw fa-trash me-2"></i>Delete
                                            </button>
                                        <?php } ?>
                                    <?php } else { ?>
                                        <button class="dropdown-item text-danger confirm-link" type="submit" form="bulkActions" name="bulk_archive_networks"
                                            data-confirm-text="They move to Archived, off the lists and pickers, until restored. The devices on them keep them.">
                                            <i class="fas fa-fw fa-archive me-2"></i>Archive
                                        </button>
                                    <?php } ?>
                                </div>
                            </div>
                        <?php } ?>
                    </div>
                </div>
            </div>
        </form>

        <!-- The types, counted over what the filters above leave - then starred and archived -->
        <nav class="nav nav-pills itflow-asset-tabs mt-2 flex-nowrap overflow-auto">
            <a class="nav-link text-nowrap <?= $show === '' && !$type_filter ? 'active' : '' ?>" href="<?= escapeHtml($filter_url(['show' => null, 'type' => null])) ?>">
                <i class="fas fa-fw fa-network-wired me-1"></i>Active <span class="badge rounded-pill ms-1"><?= $view_counts[''] ?></span>
            </a>
            <?php foreach (NETWORK_TYPES as $type => [$icon]) {
                if (empty($type_counts[$type]) && $type_filter !== $type) {
                    continue;
                } ?>
                <a class="nav-link text-nowrap <?= $type_filter === $type ? 'active' : '' ?>" href="<?= escapeHtml($filter_url(['type' => $type_filter === $type ? null : $type])) ?>" title="<?= escapeHtml(NETWORK_TYPES[$type][2]) ?>">
                    <i class="fas fa-fw <?= $icon ?> me-1"></i><?= $type ?> <span class="badge rounded-pill ms-1"><?= intval($type_counts[$type] ?? 0) ?></span>
                </a>
            <?php } ?>
            <span class="vr mx-1 my-1"></span>
            <?php foreach (['starred', 'archived'] as $key) { ?>
                <a class="nav-link text-nowrap <?= $show === $key ? 'active' : '' ?>" href="<?= escapeHtml($filter_url(['show' => $show === $key ? null : $key])) ?>">
                    <i class="fas fa-fw <?= $shows[$key]['icon'] ?> me-1"></i><?= $shows[$key]['label'] ?> <span class="badge rounded-pill ms-1"><?= $view_counts[$key] ?></span>
                </a>
            <?php } ?>
        </nav>
    </div>

    <form id="bulkActions" action="post.php" method="post">
        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 itflow-asset-table itflow-ticket-list itflow-net-list">
                <thead class="<?php if (!$networks) { echo "d-none"; } ?> text-nowrap table-light">
                <tr>
                    <?php if ($can_edit) { ?>
                        <th class="checkbox-column border-end">
                            <div class="form-check">
                                <input class="form-check-input" id="selectAllCheckbox" type="checkbox" onclick="checkAll(this)" title="Select all on this page">
                            </div>
                        </th>
                    <?php } ?>
                    <th><?= $sort_link('network_name', 'Network') ?> <span class="fw-normal text-body-secondary small ms-2"><?= $sort_link('network_type', 'by type') ?></span></th>
                    <?php if (!$client_url) { ?><th><?= $sort_link('client_name', 'Client') ?></th><?php } ?>
                    <th class="d-none d-md-table-cell"><?= $sort_link('network', 'Subnet') ?></th>
                    <th class="d-none <?= $client_url ? 'd-lg-table-cell' : 'd-xxl-table-cell' ?>">Gateway, DNS &amp; DHCP</th>
                    <th>In use</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($networks as $network_id => $row) {
                    $row_client_id = intval($row['network_client_id']);
                    $type = networkType($row);
                    $info = networkSubnetInfo($row['network']);
                    $is_archived = !empty($row['network_archived_at']);
                    $is_favorite = intval($row['network_favorite']) === 1;
                    $row_problems = $problems[$network_id];
                    $url = escapeHtml(networkUrl($network_id, $row_client_id));
                    $name = escapeHtml($row['network_name']);
                    $dns = array_filter([$row['network_primary_dns'], $row['network_secondary_dns']]);
                    $u = $usage[$network_id];
                    ?>
                    <tr class="itflow-ticket-row <?= $row_problems && !$is_archived ? 'is-warning' : '' ?> <?= $is_archived ? 'is-done' : '' ?>">
                        <?php if ($can_edit) { ?>
                            <td class="checkbox-column bg-light border-end">
                                <div class="form-check">
                                    <input class="form-check-input bulk-select" type="checkbox" name="network_ids[]" value="<?= $network_id ?>" aria-label="Select <?= $name ?>">
                                </div>
                            </td>
                        <?php } ?>

                        <!-- What it is -->
                        <td class="itflow-ticket-main itflow-net-main">
                            <div class="d-flex align-items-center gap-3">
                                <?= networkTile($type) ?>
                                <div class="min-w-0">
                                    <a class="<?= $is_archived ? 'text-body-secondary' : 'fw-semibold text-body' ?>" href="<?= $url ?>"><?= $name ?></a>
                                    <?php if ($is_favorite) { ?><i class="fas fa-star text-warning small ms-1" title="Starred"></i><?php } ?>
                                    <?php if ($is_archived) { ?><span class="badge rounded-pill text-bg-secondary fw-normal ms-1">Archived</span><?php } ?>
                                    <div class="small text-body-secondary d-flex flex-wrap align-items-center column-gap-2">
                                        <?= $can_edit && !$is_archived ? quickPick('network_type', $network_id, $type, networkTypeLabel($type)) : networkTypeLabel($type) ?>
                                        <?php if ($row['network_vlan']) { ?><span class="itflow-net-vlan" title="VLAN">VLAN <?= intval($row['network_vlan']) ?></span><?php } ?>
                                        <?php if ($row['location_name']) { ?><span class="text-truncate"><i class="fas fa-map-marker-alt fa-fw me-1 opacity-75"></i><?= escapeHtml($row['location_name']) ?></span><?php } ?>
                                        <?php if ($type === 'WAN' && $row['vendor_name']) { ?><span class="text-truncate"><i class="fas fa-building fa-fw me-1 opacity-75"></i><?= escapeHtml($row['vendor_name']) ?></span><?php } ?>
                                        <?php if ($row['network_description']) { ?><span class="text-truncate itflow-service-desc" title="<?= escapeHtml($row['network_description']) ?>"><?= escapeHtml($row['network_description']) ?></span><?php } ?>
                                    </div>
                                    <!-- On a phone the other columns are off to the side - the subnet comes along -->
                                    <div class="d-md-none small font-monospace mt-1"><?= escapeHtml($row['network']) ?><?= $row['network_gateway'] ? ' · gw ' . escapeHtml($row['network_gateway']) : '' ?></div>
                                </div>
                            </div>
                        </td>

                        <?php if (!$client_url) { ?>
                            <td class="itflow-ticket-who-cell"><a class="d-block text-truncate text-body fw-semibold small" href="networks.php?client_id=<?= $row_client_id ?>"><?= escapeHtml($row['client_name']) ?></a></td>
                        <?php } ?>

                        <td class="text-nowrap d-none d-md-table-cell">
                            <span class="font-monospace fw-semibold"><?= escapeHtml($row['network']) ?></span><button type="button" class="itflow-copy clipboardjs" title="Copy subnet" data-clipboard-text="<?= escapeHtml($row['network']) ?>"><i class="far fa-copy"></i></button>
                            <div class="small text-body-secondary"><?= $info ? escapeHtml(networkSizeText($info)) : '<span class="text-warning-emphasis"><i class="fas fa-exclamation-triangle me-1"></i>Not a subnet</span>' ?></div>
                        </td>

                        <td class="small d-none <?= $client_url ? 'd-lg-table-cell' : 'd-xxl-table-cell' ?> text-nowrap">
                            <?php if ($row['network_gateway']) { ?><div title="Gateway"><i class="fas fa-fw fa-door-open text-body-secondary me-1"></i><span class="font-monospace"><?= escapeHtml($row['network_gateway']) ?></span></div><?php } ?>
                            <?php if ($dns) { ?><div class="text-body-secondary" title="DNS"><i class="fas fa-fw fa-search-location me-1"></i><span class="font-monospace"><?= escapeHtml(implode(', ', $dns)) ?></span></div><?php } ?>
                            <?php $pool = networkParseRange($row['network_dhcp_range'], $info);
                            if ($pool) { ?>
                                <div class="text-body-secondary" title="DHCP range"><i class="fas fa-fw fa-random me-1"></i><span class="font-monospace"><?= escapeHtml(networkRangeText(intToIpv4($pool[0]), intToIpv4($pool[1]))) ?></span></div>
                            <?php } elseif ($row['network_dhcp_range']) { ?>
                                <div class="text-body-secondary text-truncate" title="DHCP"><i class="fas fa-fw fa-random me-1"></i><?= escapeHtml($row['network_dhcp_range']) ?></div>
                            <?php } ?>
                            <?php if (!$row['network_gateway'] && !$dns && !$row['network_dhcp_range']) { ?><span class="text-body-secondary">-</span><?php } ?>
                        </td>


                        <td class="itflow-net-usage-cell">
                            <a class="text-reset text-decoration-none d-block" href="<?= $url ?>"><?= networkUsageBar($u) ?></a>
                            <div class="small text-body-secondary d-flex flex-wrap column-gap-2">
                                <?php if ($u['devices']) { ?><span title="Devices with an interface on it"><i class="fas fa-fw fa-desktop me-1"></i><?= $u['devices'] ?></span><?php } ?>
                                <?php if ($u['documented']) { ?><span title="Documented addresses"><i class="fas fa-fw fa-list-ol me-1"></i><?= $u['documented'] ?></span><?php } ?>
                                <?php if ($row_problems) { ?><a class="text-warning-emphasis text-nowrap" href="<?= $url ?>#addresses" title="Two devices on one address, a fixed address inside the DHCP range, a device outside the subnet"><i class="fas fa-fw fa-exclamation-triangle me-1"></i><?= $row_problems ?> to check</a><?php } ?>
                            </div>
                        </td>

                        <td class="text-end">
                            <div class="dropdown dropstart">
                                <button class="btn btn-sm btn-light" title="More actions" type="button" data-bs-toggle="dropdown"><i class="fas fa-fw fa-ellipsis-v"></i></button>
                                <div class="dropdown-menu">
                                    <a class="dropdown-item ajax-modal" href="#" data-modal-size="lg" data-modal-url="modals/network/network.php?id=<?= $network_id ?>">
                                        <i class="fas fa-fw fa-eye text-secondary me-2"></i>Quick look
                                    </a>
                                    <a class="dropdown-item" href="<?= $url ?>" target="_blank">
                                        <i class="fas fa-fw fa-external-link-alt text-secondary me-2"></i>Open in a new tab
                                    </a>
                                    <?php if ($can_edit && !$is_archived) { ?>
                                        <a class="dropdown-item ajax-modal" href="#" data-modal-size="lg" data-modal-url="modals/network/network_edit.php?id=<?= $network_id ?>">
                                            <i class="fas fa-fw fa-edit text-secondary me-2"></i>Edit
                                        </a>
                                        <a class="dropdown-item ajax-modal" href="#" data-modal-url="modals/network/network_ip_add.php?network_id=<?= $network_id ?>&next=1">
                                            <i class="fas fa-fw fa-plus text-secondary me-2"></i>Document an address
                                        </a>
                                        <a class="dropdown-item" href="post.php?<?= $is_favorite ? 'unfavorite_network' : 'favorite_network' ?>=<?= $network_id ?>&csrf_token=<?= $csrf ?>">
                                            <i class="<?= $is_favorite ? 'far' : 'fas' ?> fa-fw fa-star text-secondary me-2"></i><?= $is_favorite ? 'Unstar' : 'Star' ?>
                                        </a>
                                    <?php } ?>
                                    <?php if ($can_edit) { ?>
                                        <div class="dropdown-divider"></div>
                                        <?php if ($is_archived) { ?>
                                            <a class="dropdown-item" href="post.php?restore_network=<?= $network_id ?>&csrf_token=<?= $csrf ?>">
                                                <i class="fas fa-fw fa-redo text-secondary me-2"></i>Restore
                                            </a>
                                            <?php if ($can_delete) { ?>
                                                <a class="dropdown-item text-danger text-bold confirm-link" href="post.php?delete_network=<?= $network_id ?>&csrf_token=<?= $csrf ?>"
                                                    data-confirm-text="<?= $name ?> and the addresses documented on it go for good. The devices on it are left on no network.">
                                                    <i class="fas fa-fw fa-trash me-2"></i>Delete
                                                </a>
                                            <?php } ?>
                                        <?php } else { ?>
                                            <a class="dropdown-item text-danger confirm-link" href="post.php?archive_network=<?= $network_id ?>&csrf_token=<?= $csrf ?>"
                                                data-confirm-text="<?= $name ?> moves to Archived, off the lists and pickers, until restored. The devices on it keep it.">
                                                <i class="fas fa-fw fa-archive me-2"></i>Archive
                                            </a>
                                        <?php } ?>
                                    <?php } ?>
                                </div>
                            </div>
                        </td>
                    </tr>
                <?php } ?>
                </tbody>
            </table>
        </div>
    </form>
    <?php if (!$networks) { ?>
        <div class="itflow-asset-empty">
            <i class="fas fa-network-wired fa-2x mb-2 opacity-50"></i>
            <div>
                <?php if ($q_ip !== false) { ?>
                    No network has <?= escapeHtml($q_ip) ?> in it<?= $show || $type_filter ? ' in this view' : '' ?>.
                <?php } elseif ($q !== '' || $show || $type_filter || $client_filter || $location_filter) { ?>
                    No networks match.
                <?php } else { ?>
                    No networks yet - start with the office LAN, the guest wireless and the internet connection.
                <?php } ?>
            </div>
        </div>
    <?php } ?>
    <?php require_once "../includes/filter_footer.php"; ?>
</div>

<script src="../js/bulk_actions.js"></script>

<?php
require_once "../includes/footer.php";
