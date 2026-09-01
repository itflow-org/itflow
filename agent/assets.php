<?php

// Default Column Sortby Filter
$sort = "asset_name";
$order = "ASC";

// If client_id is in URI then show client Side Bar and client header
if (isset($_GET['client_id'])) {
    require_once "includes/inc_all_client.php";
    $client_query = "AND asset_client_id = $client_id";
    $client_url = "client_id=$client_id&";
    // Overide Filter Header Archived
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
    // Overide Filter Header Archived
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

//Asset Type from GET
if (isset($_GET['type']) && ($_GET['type']) == 'workstation') {
    $type_query = "asset_type = 'desktop' OR asset_type = 'laptop'";
    $type_filter = "workstation";
} elseif (isset($_GET['type']) && ($_GET['type']) == 'server') {
    $type_query = "asset_type = 'server'";
    $type_filter = "server";
} elseif (isset($_GET['type']) && ($_GET['type']) == 'virtual') {
    $type_query = "asset_type = 'Virtual Machine'";
    $type_filter = "virtual";
} elseif (isset($_GET['type']) && ($_GET['type']) == 'network') {
    $type_query = "asset_type = 'Firewall/Router' OR asset_type = 'Switch' OR asset_type = 'Access Point'";
    $type_filter = "network";
} elseif (isset($_GET['type']) && ($_GET['type']) == 'other') {
    $type_query = "asset_type NOT LIKE 'laptop' AND asset_type NOT LIKE 'desktop' AND asset_type NOT LIKE 'server' AND asset_type NOT LIKE 'virtual machine' AND asset_type NOT LIKE 'firewall/router' AND asset_type NOT LIKE 'switch' AND asset_type NOT LIKE 'access point'";
    $type_filter = "other";
} else {
    $type_query = "asset_type LIKE '%'";
    $_GET['type'] = '';
    $type_filter = '';
}

if (!$client_url) {
    // Client Filter
    if (isset($_GET['client']) & !empty($_GET['client'])) {
        $client_query = 'AND (asset_client_id = ' . intval($_GET['client']) . ')';
        $client = intval($_GET['client']);
    } else {
        // Default - any
        $client_query = '';
        $client = '';
    }
}

// Location Filter
if ($client_url && isset($_GET['location']) && !empty($_GET['location'])) {
    $location_query = 'AND (asset_location_id = ' . intval($_GET['location']) . ')';
    $location_filter = intval($_GET['location']);
} else {
    // Default - any
    $location_query = '';
    $location_filter = 0;
}

// Tags Filter
if (isset($_GET['tags']) && is_array($_GET['tags']) && !empty($_GET['tags'])) {
    // Sanitize each element of the tags array
    $sanitizedTags = array_map('intval', $_GET['tags']);
    // Convert the sanitized tags into a comma-separated string
    $tag_filter = implode(",", $sanitizedTags);
    $tag_query = "AND tag_id IN ($tag_filter)";
} else {
    $tag_filter = 0;
    $tag_query = '';
}

// Expiring In Filter
if (isset($_GET['expire_days']) && !empty($_GET['expire_days'])) {
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

//Get Asset Counts
$row = mysqli_fetch_assoc(mysqli_query($mysqli, "
    SELECT
        COUNT(*) AS all_count,
        SUM(CASE WHEN asset_type IN ('laptop', 'desktop') THEN 1 ELSE 0 END) AS workstation_count,
        SUM(CASE WHEN asset_type = 'server' THEN 1 ELSE 0 END) AS server_count,
        SUM(CASE WHEN asset_type = 'virtual machine' THEN 1 ELSE 0 END) AS virtual_count,
        SUM(CASE WHEN asset_type IN ('Firewall/Router', 'switch', 'access point') THEN 1 ELSE 0 END) AS network_count,
        SUM(CASE WHEN asset_type NOT IN ('laptop', 'desktop', 'server', 'virtual machine', 'Firewall/Router', 'switch', 'access point') THEN 1 ELSE 0 END) AS other_count
    FROM (
        SELECT assets.* FROM assets
        LEFT JOIN clients ON client_id = asset_client_id
        LEFT JOIN contacts ON asset_contact_id = contact_id
        LEFT JOIN locations ON asset_location_id = location_id
        LEFT JOIN asset_interfaces ON interface_asset_id = asset_id AND interface_primary = 1
        LEFT JOIN asset_tags ON asset_tag_asset_id = asset_id
        LEFT JOIN tags ON tag_id = asset_tag_tag_id
        WHERE $archive_query
        $tag_query
        " . clientScopeSql('asset_client_id') . "
        $client_query
        GROUP BY asset_id
    ) AS filtered_assets;
"));

//All Asset Count
$all_count = intval($row['all_count']);

//Workstation Count
$workstation_count = intval($row['workstation_count']);

//Server Count
$server_count = intval($row['server_count']);

//Virtual Server Count
$virtual_count = intval($row['virtual_count']);

//Network Device Count
$network_count = intval($row['network_count']);

//Other Count
$other_count = intval($row['other_count']);

$sql = mysqli_query(
    $mysqli,
    "SELECT SQL_CALC_FOUND_ROWS asset_archived_at, asset_contact_id, asset_created_at, asset_description, asset_favorite,
        asset_id, asset_install_date, asset_location_id, asset_make, asset_model, asset_name,
        asset_notes, asset_os, asset_photo, asset_physical_location, asset_purchase_date,
        asset_purchase_reference, asset_serial, asset_status, asset_type, asset_uri, asset_uri_2,
        asset_uri_client, asset_vendor_id, asset_warranty_expire, client_id, client_name,
        contact_archived_at, contact_name, interface_ip, interface_ipv6, interface_mac,
        interface_nat_ip, interface_network_id, location_archived_at, location_name, tag_color,
        tag_icon, tag_id, tag_name FROM assets
    LEFT JOIN clients ON asset_client_id = client_id
    LEFT JOIN contacts ON asset_contact_id = contact_id
    LEFT JOIN locations ON asset_location_id = location_id
    LEFT JOIN asset_interfaces ON interface_asset_id = asset_id AND interface_primary = 1
    LEFT JOIN asset_tags ON asset_tag_asset_id = asset_id
    LEFT JOIN tags ON tag_id = asset_tag_tag_id
    WHERE $archive_query
    $tag_query
    AND (asset_name LIKE '%$q%' OR asset_description LIKE '%$q%' OR asset_type LIKE '%$q%' OR interface_ip LIKE '%$q%' OR interface_ipv6 LIKE '%$q%' OR interface_mac LIKE '%$q%' OR asset_make LIKE '%$q%' OR asset_model LIKE '%$q%' OR asset_serial LIKE '%$q%' OR asset_os LIKE '%$q%' OR contact_name LIKE '%$q%' OR location_name LIKE '%$q%' OR client_name LIKE '%$q%' OR tag_name LIKE '%$q%')
    AND ($type_query)
    " . clientScopeSql('asset_client_id') . "
    $location_query
    $expire_query
    $client_query
    GROUP BY asset_id
    ORDER BY $sort $order LIMIT $record_from, $record_to"
);

$num_rows = mysqli_fetch_row(mysqli_query($mysqli, "SELECT FOUND_ROWS()"));

?>

<div class="col-sm-12 mb-3">
    <div class="btn-toolbar">
        <div class="btn-group w-100">
            <?php if($all_count) { ?>
            <a href="?<?= $url_query_strings_sort ?>&type=" class="btn <?php if ($_GET['type'] == 'all' || empty($_GET['type'])) { echo 'btn-primary'; } else { echo 'btn-default'; } ?>">All Assets<span class="right badge bg-light text-dark ms-2"><?= $all_count ?></span></a>
            <?php } ?>
            <?php
            if ($workstation_count > 0) { ?>
                <a href="?<?= $url_query_strings_sort ?>&type=workstation" class="btn <?php if ($_GET['type'] == 'workstation') { echo 'btn-primary'; } else { echo 'btn-default'; } ?>"><i class="fa fa-fw fa-desktop me-2"></i>Workstations<span class="right badge bg-light text-dark ms-2"><?= $workstation_count ?></span></a>
                <?php
            }
            if ($server_count > 0) { ?>
                <a href="?<?= $url_query_strings_sort ?>&type=server" class="btn <?php if ($_GET['type'] == 'server') { echo 'btn-primary'; } else { echo 'btn-default'; } ?>"><i class="fa fa-fw fa-server me-2"></i>Servers<span class="right badge bg-light text-dark ms-2"><?= $server_count ?></span></a>
                <?php
            }
            if ($virtual_count > 0) { ?>
                <a href="?<?= $url_query_strings_sort ?>&type=virtual" class="btn <?php if ($_GET['type'] == 'virtual') { echo 'btn-primary'; } else { echo 'btn-default'; } ?>"><i class="fa fa-fw fa-cloud me-2"></i>Virtual<span class="right badge bg-light text-dark ms-2"><?= $virtual_count ?></span></a>
                <?php
            }
            if ($network_count > 0) { ?>
                <a href="?<?= $url_query_strings_sort ?>&type=network" class="btn <?php if ($_GET['type'] == 'network') { echo 'btn-primary'; } else { echo 'btn-default'; } ?>"><i class="fa fa-fw fa-network-wired me-2"></i>Network<span class="right badge bg-light text-dark ms-2"><?= $network_count ?></span></a>
                <?php
            }
            if ($other_count > 0) { ?>
                <a href="?<?= $url_query_strings_sort ?>&type=other" class="btn <?php if ($_GET['type'] == 'other') { echo 'btn-primary'; } else { echo 'btn-default'; } ?>"><i class="fa fa-fw fa-tag me-2"></i>Other<span class="right badge bg-light text-dark ms-2"><?= $other_count ?></span></a>
                <?php
            } ?>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header bg-dark py-2">
        <h3 class="card-title mt-2"><i class="fas fa-fw fa-desktop me-2"></i>Assets</h3>
        <div class="card-tools">
            <?php if (lookupUserPermission("module_support") >= 2) { ?>
            <div class="btn-group">
                <button type="button" class="btn btn-primary ajax-modal" data-modal-url="modals/asset/asset_add.php?<?= $client_url ?>&type=<?= $type_filter ?>">
                    <i class="fas fa-plus me-2"></i>New <?php if ($type_filter) { echo ucwords($type_filter); } else { echo "Asset"; } ?>
                </button>
                <button type="button" class="btn btn-primary dropdown-toggle dropdown-toggle-split" data-bs-toggle="dropdown"></button>
                <div class="dropdown-menu">
                    <?php if ($client_url) { ?>
                    <a class="dropdown-item text-dark ajax-modal" href="#"
                        data-modal-url="modals/asset/asset_import.php?<?= $client_url ?>">
                        <i class="fa fa-fw fa-upload me-2"></i>Import
                    </a>
                    <div class="dropdown-divider"></div>
                    <?php } ?>
                    <?php if ($num_rows[0] > 0) { ?>

                        <a class="dropdown-item text-dark ajax-modal" href="#"
                            data-modal-url="<?= buildExportModalUrl('modals/asset/asset_export.php', ['client_id', 'type', 'client', 'location', 'tags', 'expire_days', 'archived', 'q']) ?>">
                            <i class="fa fa-fw fa-download me-2"></i>Export
                        </a>
                    <?php } ?>
                </div>
            </div>
            <?php } ?>
        </div>
    </div>
    <div class="card-header py-3">
        <form autocomplete="off">
            <?php if ($client_url) { ?>
            <input type="hidden" name="client_id" value="<?= $client_id ?>">
            <?php } ?>
            <input type="hidden" name="type" value="<?= stripslashes(escapeHtml($_GET['type'])) ?>">
            <input type="hidden" name="archived" value="<?= $archived ?>">
            <div class="row g-2 align-items-center">
                <div class="col-md-3">
                    <div class="input-group">
                        <input type="search" class="form-control" name="q" value="<?php if (isset($q)) { echo stripslashes(escapeHtml($q)); } ?>" placeholder="Search <?php if (!empty($_GET['type'])) { echo ucwords(stripslashes(escapeHtml($_GET['type']))); } else { echo "Asset"; } ?>s">
                            <button class="btn btn-dark"><i class="fa fa-search"></i></button>
                    </div>
                </div>
                <?php if ($client_url) { ?>
                <div class="col-md-2">
                    <div class="input-group">
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
                            <?php
                            }
                            ?>

                        </select>
                    </div>
                </div>
                <?php } else { ?>
                <div class="col-md-2">
                    <div class="input-group">
                        <select class="form-select select2" name="client" onchange="this.form.submit()">
                            <option value="" <?php if ($client == "") { echo "selected"; } ?>>- All Clients -</option>

                            <?php
                            $sql_clients_filter = mysqli_query($mysqli, "
                                SELECT DISTINCT client_id, client_name
                                FROM clients
                                JOIN assets ON asset_client_id = client_id
                                WHERE $archive_query
                                " . clientScopeSql('clients.client_id') . "
                                ORDER BY client_name ASC
                            ");
                            while ($row = mysqli_fetch_assoc($sql_clients_filter)) {
                                $client_id = intval($row['client_id']);
                                $client_name = escapeHtml($row['client_name']);
                            ?>
                                <option <?php if ($client == $client_id) { echo "selected"; } ?> value="<?= $client_id ?>"><?= $client_name ?></option>
                            <?php
                            }
                            ?>

                        </select>
                    </div>
                </div>
                <?php } ?>
                <div class="col-md-2">
                    <div class="input-group">
                        <select onchange="this.form.submit()" class="form-select select2" name="tags[]" data-placeholder="- Select Tags -" multiple>

                            <?php
                            $sql_tags_filter = mysqli_query($mysqli, "
                                SELECT tag_id, tag_name
                                FROM tags
                                LEFT JOIN asset_tags ON asset_tag_tag_id = tag_id
                                LEFT JOIN assets ON asset_tag_asset_id = asset_id
                                WHERE tag_type = 5
                                $client_query OR tag_id IN ($tag_filter)
                                GROUP BY tag_id
                                HAVING COUNT(asset_tag_asset_id) > 0 OR tag_id IN ($tag_filter)
                            ");
                            while ($row = mysqli_fetch_assoc($sql_tags_filter)) {
                                $tag_id = intval($row['tag_id']);
                                $tag_name = escapeHtml($row['tag_name']); ?>

                                <option value="<?= $tag_id ?>" <?php if (isset($_GET['tags']) && in_array($tag_id, $_GET['tags'])) { echo 'selected'; } ?>> <?= $tag_name ?> </option>

                            <?php } ?>
                        </select>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="input-group">
                        <select class="form-select select2" name="expire_days" onchange="this.form.submit()">
                            <option value="" <?php if ($expire_days == "") { echo "selected"; } ?>>- Expiring In -</option>
                            <option value="expired" <?php if ($expire_days === "expired") { echo "selected"; } ?>>Expired</option>
                            <option value="7" <?php if ($expire_days === 7) { echo "selected"; } ?>>7 Days</option>
                            <option value="30" <?php if ($expire_days === 30) { echo "selected"; } ?>>30 Days</option>
                            <option value="45" <?php if ($expire_days === 45) { echo "selected"; } ?>>45 Days</option>
                            <option value="60" <?php if ($expire_days === 60) { echo "selected"; } ?>>60 Days</option>
                            <option value="90" <?php if ($expire_days === 90) { echo "selected"; } ?>>90 Days</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-2">
                    <div>
                        <select onchange="this.form.submit()" class="form-select select2" name="show_column[]" data-placeholder="- Show Additional Columns -" multiple>
                            <option
                                <?php if (isset($_GET['show_column']) && is_array($_GET['show_column']) && in_array('Mac_Address', $_GET['show_column'])) { echo 'selected'; } ?>>Mac_Address
                            </option>
                            <option
                                <?php if (isset($_GET['show_column']) && is_array($_GET['show_column']) && in_array('Purchase_Date', $_GET['show_column'])) { echo 'selected'; } ?>>Purchase_Date
                            </option>
                            <option
                                <?php if (isset($_GET['show_column']) && is_array($_GET['show_column']) && in_array('Install_Date', $_GET['show_column'])) { echo 'selected'; } ?>>Install_Date
                            </option>
                            <option
                                <?php if (isset($_GET['show_column']) && is_array($_GET['show_column']) && in_array('Warranty_Expire', $_GET['show_column'])) { echo 'selected'; } ?>>Warranty_Expire
                            </option>
                        </select>
                    </div>
                </div>
                <div class="col-md-1">
                    <div class="btn-group float-end">
                        <a href="?<?= $client_url ?>&archived=<?php if($archived == 1){ echo 0; } else { echo 1; } ?>"
                            class="btn btn-<?php if($archived == 1){ echo"primary"; } else { echo "default"; } ?>">
                            <i class="fa fa-fw fa-archive me-2"></i>Archived
                        </a>
                        <div class="dropdown ms-2" id="bulkActionButton" hidden>
                            <button class="btn btn-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                <i class="fas fa-fw fa-layer-group me-2"></i>Bulk Action (<span id="selectedCount"></span>)
                            </button>
                            <div class="dropdown-menu">
                                <button class="dropdown-item"
                                    type="submit" form="bulkActions" name="bulk_favorite_assets">
                                    <i class="fas fa-fw fa-star text-warning me-2"></i>Favorite
                                </button>
                                <div class="dropdown-divider"></div>
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
                                <div class="dropdown-divider"></div>
                                <a class="dropdown-item ajax-modal" href="#"
                                    data-modal-url="modals/asset/asset_bulk_assign_location.php?<?= $client_url ?>"
                                    data-bulk="true">
                                    <i class="fas fa-fw fa-map-marker-alt me-2"></i>Assign Location
                                </a>
                                <div class="dropdown-divider"></div>
                                <?php } ?>
                                <a class="dropdown-item ajax-modal" href="#"
                                    data-modal-url="modals/asset/asset_bulk_assign_tags.php"
                                    data-bulk="true">
                                    <i class="fas fa-fw fa-tags me-2"></i>Assign Tags
                                </a>
                                <div class="dropdown-divider"></div>
                                <a class="dropdown-item ajax-modal" href="#"
                                    data-modal-url="modals/asset/asset_bulk_assign_physical_location.php"
                                    data-bulk="true">
                                    <i class="fas fa-fw fa-map-marker-alt me-2"></i>Set Physical Location
                                </a>
                                <div class="dropdown-divider"></div>
                                <a class="dropdown-item ajax-modal" href="#"
                                    data-modal-url="modals/asset/asset_bulk_edit_status.php"
                                    data-bulk="true">
                                    <i class="fas fa-fw fa-info me-2"></i>Set Status
                                </a>
                                <div class="dropdown-divider"></div>
                                <a class="dropdown-item ajax-modal" href="#"
                                    data-modal-url="modals/asset/asset_bulk_add_ticket.php"
                                    data-modal-size="lg"
                                    data-bulk="true">
                                    <i class="fas fa-fw fa-life-ring me-2"></i>Create Tickets
                                </a>
                                <div class="dropdown-divider"></div>
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
                                    <div class="dropdown-divider"></div>
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
            <table class="table border table-hover mb-0">
                <thead class="table-light <?php if (!$num_rows[0]) { echo "d-none"; } ?> text-nowrap">
                <tr>
                    <td class="checkbox-column border-end">
                        <div class="form-check">
                            <input class="form-check-input" id="selectAllCheckbox" type="checkbox" onclick="checkAll(this)">
                        </div>
                    </td>
                    <th>
                        <a class="text-secondary" href="?<?= $url_query_strings_sort ?>&sort=asset_name&order=<?= $disp ?>">
                            Name <?php if ($sort == 'asset_name') { echo $order_icon; } ?>
                        </a>
                    </th>
                        <th>
                            <a class="text-secondary" href="?<?= $url_query_strings_sort ?>&sort=asset_type&order=<?= $disp ?>">
                                Type <?php if ($sort == 'asset_type') { echo $order_icon; } ?>
                            </a>
                        </th>
                    <?php if ($_GET['type'] !== 'virtual') { ?>
                        <th>
                            <a class="text-secondary" href="?<?= $url_query_strings_sort ?>&sort=asset_make&order=<?= $disp ?>">
                                Model <?php if ($sort == 'asset_make') { echo $order_icon; } ?>
                            </a>
                        </th>
                    <?php } ?>
                        <th>
                            <a class="text-secondary" href="?<?= $url_query_strings_sort ?>&sort=interface_ip&order=<?= $disp ?>">
                                IP <?php if ($sort == 'interface_ip') { echo $order_icon; } ?>
                            </a>
                        </th>
                    <?php if (isset($_GET['show_column']) && is_array($_GET['show_column']) && in_array('Mac_Address', $_GET['show_column'])) { ?>
                        <th>
                            <a class="text-secondary" href="?<?= $url_query_strings_sort ?>&sort=interface_mac&order=<?= $disp ?>">
                                MAC Address <?php if ($sort == 'interface_mac') { echo $order_icon; } ?>
                            </a>
                        </th>
                    <?php } ?>
                    <?php if (isset($_GET['show_column']) && is_array($_GET['show_column']) && in_array('Purchase_Date', $_GET['show_column'])) { ?>
                        <th>
                            <a class="text-secondary" href="?<?= $url_query_strings_sort ?>&sort=asset_purchase_date&order=<?= $disp ?>">
                                Purchase Date <?php if ($sort == 'asset_purchase_date') { echo $order_icon; } ?>
                            </a>
                        </th>
                    <?php } ?>
                    <?php if (isset($_GET['show_column']) && is_array($_GET['show_column']) && in_array('Install_Date', $_GET['show_column'])) { ?>
                        <th>
                            <a class="text-secondary" href="?<?= $url_query_strings_sort ?>&sort=asset_install_date&order=<?= $disp ?>">
                                Install Date <?php if ($sort == 'asset_install_date') { echo $order_icon; } ?>
                            </a>
                        </th>
                    <?php } ?>
                    <?php if (isset($_GET['show_column']) && is_array($_GET['show_column']) && in_array('Warranty_Expire', $_GET['show_column'])) { ?>
                        <th>
                            <a class="text-secondary" href="?<?= $url_query_strings_sort ?>&sort=asset_warranty_expire&order=<?= $disp ?>">
                                Warranty Expire <?php if ($sort == 'asset_warranty_expire') { echo $order_icon; } ?>
                            </a>
                        </th>
                    <?php } ?>
                    <?php if ($_GET['type'] !== 'network' && $_GET['type'] !== 'server' && $_GET['type'] !== 'other') { ?>
                        <th>
                            <a class="text-secondary" href="?<?= $url_query_strings_sort ?>&sort=contact_name&order=<?= $disp ?>">
                                Assigned To <?php if ($sort == 'contact_name') { echo $order_icon; } ?>
                            </a>
                        </th>
                    <?php } ?>
                    <th>
                        <a class="text-secondary" href="?<?= $url_query_strings_sort ?>&sort=location_name&order=<?= $disp ?>">
                            Location <?php if ($sort == 'location_name') { echo $order_icon; } ?>
                        </a>
                    </th>
                    <th>
                        <a class="text-secondary" href="?<?= $url_query_strings_sort ?>&sort=asset_status&order=<?= $disp ?>">
                            Status <?php if ($sort == 'asset_status') { echo $order_icon; } ?>
                        </a>
                    </th>
                    <?php if (!$client_url) { ?>
                    <th>
                        <a class="text-secondary" href="?<?= $url_query_strings_sort ?>&sort=client_name&order=<?= $disp ?>">
                            Client <?php if ($sort == 'client_name') { echo $order_icon; } ?>
                        </a>
                    </th>
                    <?php } ?>
                    <th class="text-center">Action</th>
                </tr>

                </thead>
                <tbody>
                <?php

                while ($row = mysqli_fetch_assoc($sql)) {
                    $client_id = intval($row['client_id']);
                    $client_name = escapeHtml($row['client_name']);
                    $asset_id = intval($row['asset_id']);
                    $asset_type = escapeHtml($row['asset_type']);
                    $asset_name = escapeHtml($row['asset_name']);
                    $asset_description = escapeHtml($row['asset_description']);
                    $asset_make = escapeHtml($row['asset_make']);
                    $asset_model = escapeHtml($row['asset_model']);
                    $asset_serial = escapeHtml($row['asset_serial']);
                    if ($asset_serial) {
                        $asset_serial_display = "<span class='font-monospace'>$asset_serial</span>";
                    } else {
                        $asset_serial_display = "-";
                    }
                    $asset_os = escapeHtml($row['asset_os']);
                    $asset_ip = escapeHtml($row['interface_ip']) ?: '-';
                    $asset_ipv6 = escapeHtml($row['interface_ipv6']);
                    $asset_nat_ip = escapeHtml($row['interface_nat_ip']);
                    $asset_mac = escapeHtml($row['interface_mac']) ?: '-';
                    $asset_uri = escapeUrl($row['asset_uri']);
                    $asset_uri_2 = escapeUrl($row['asset_uri_2']);
                    $asset_uri_client = escapeUrl($row['asset_uri_client']);
                    $asset_status = escapeHtml($row['asset_status']);
                    $asset_purchase_reference = escapeHtml($row['asset_purchase_reference']);
                    $asset_purchase_date = escapeHtml($row['asset_purchase_date']);
                    if ($asset_purchase_date) {
                        $asset_purchase_date_display = $asset_purchase_date;
                    } else {
                        $asset_purchase_date_display = "-";
                    }
                    $asset_warranty_expire = escapeHtml($row['asset_warranty_expire']);
                    if ($asset_warranty_expire) {
                        $asset_warranty_expire_display = $asset_warranty_expire;
                    } else {
                        $asset_warranty_expire_display = "-";
                    }
                    $asset_install_date = escapeHtml($row['asset_install_date']);
                    if ($asset_install_date) {
                        $asset_install_date_display = $asset_install_date;
                    } else {
                        $asset_install_date_display = "-";
                    }
                    $asset_photo = escapeHtml($row['asset_photo']);
                    $asset_physical_location = escapeHtml($row['asset_physical_location']);
                    if ($asset_physical_location) {
                        $asset_physical_location_display = "<div class='text-secondary'>$asset_physical_location</div>";
                    } else {
                        $asset_physical_location_display = "";
                    }
                    $asset_notes = escapeHtml($row['asset_notes']);
                    $asset_favorite = intval($row['asset_favorite']);
                    $asset_created_at = escapeHtml($row['asset_created_at']);
                    $asset_archived_at = escapeHtml($row['asset_archived_at']);
                    $asset_vendor_id = intval($row['asset_vendor_id']);
                    $asset_location_id = intval($row['asset_location_id']);
                    $asset_contact_id = intval($row['asset_contact_id']);
                    $asset_network_id = intval($row['interface_network_id']);

                    $device_icon = getAssetIcon($asset_type);

                    $contact_archived_at = escapeHtml($row['contact_archived_at']);
                    if ($contact_archived_at) {
                        $contact_archive_display = "<span class='text-danger'>(Archived)</span>";
                    } else {
                        $contact_archive_display = '';
                    }
                    $contact_name = escapeHtml($row['contact_name']);
                    if ($contact_name) {
                        $contact_name_display = "<a class='ajax-modal' href='#' data-modal-url='modals/contact/contact.php?id=$asset_contact_id' data-modal-size='lg'>$contact_name $contact_archive_display</a>";
                    } else {
                        $contact_name_display = "-";
                    }

                    $location_name = escapeHtml($row['location_name']);
                    if (empty($location_name)) {
                        $location_name = "-";
                    }
                    $location_archived_at = escapeHtml($row['location_archived_at']);
                    if ($location_archived_at) {
                        $location_name_display = "<div class='text-danger' title='Archived'><s>$location_name</s></div>";
                    } else {
                        $location_name_display = $location_name;
                    }

                    $sql_credentials = mysqli_query($mysqli, "SELECT 1 FROM credentials WHERE credential_asset_id = $asset_id");
                    $credential_count = mysqli_num_rows($sql_credentials);

                    // Tags
                    $asset_tag_name_display_array = array();
                    $asset_tag_id_array = array();
                    $sql_asset_tags = mysqli_query($mysqli, "SELECT tag_color, tag_icon, tag_id, tag_name FROM asset_tags LEFT JOIN tags ON asset_tag_tag_id = tag_id WHERE asset_tag_asset_id = $asset_id ORDER BY tag_name ASC");
                    while ($row = mysqli_fetch_assoc($sql_asset_tags)) {

                        $asset_tag_id = intval($row['tag_id']);
                        $asset_tag_name = escapeHtml($row['tag_name']);
                        $asset_tag_color = escapeHtml($row['tag_color']);
                        if (empty($asset_tag_color)) {
                            $asset_tag_color = "dark";
                        }
                        $asset_tag_icon = escapeHtml($row['tag_icon']);
                        if (empty($asset_tag_icon)) {
                            $asset_tag_icon = "tag";
                        }

                        $asset_tag_id_array[] = $asset_tag_id;
                        $asset_tag_name_display_array[] = "<a href='assets.php?$client_url tags[]=$asset_tag_id'><span class='badge text-light p-1 me-1' style='background-color: $asset_tag_color;'><i class='fa fa-fw fa-$asset_tag_icon me-1'></i>$asset_tag_name</span></a>";
                    }
                    $asset_tags_display = implode('', $asset_tag_name_display_array);

                    ?>
                    <tr>
                        <td class="checkbox-column bg-light border-end">
                            <div class="form-check">
                                <input class="form-check-input bulk-select" type="checkbox" name="asset_ids[]" value="<?= $asset_id ?>">
                            </div>
                        </td>
                        <td>
                            <a class="text-dark" href="asset.php?client_id=<?= $client_id ?>&asset_id=<?= $asset_id ?>">
                                <div class="d-flex">
                                    <i class="fa fa-fw fa-2x fa-<?= $device_icon ?> me-3 mt-1"></i>
                                    <div class="flex-grow-1">
                                        <div>
                                            <?= $asset_name ?>

                                            <?php if ($asset_favorite) { echo "<i class='fas fa-fw fa-star text-warning' title='Favorite'></i>"; } ?></div>
                                        <div><small class="text-secondary"><?= $asset_description ?></small></div>
                                        <?php if ($asset_tags_display) { echo $asset_tags_display; } ?>
                                    </div>
                                </div>
                            </a>
                        </td>

                        <td>
                            <div>
                                <?= $asset_type ?>
                                <?php if ( !empty($asset_uri) || !empty($asset_uri_2) || !empty($asset_uri_client)) { ?>
                                <div class="dropdown d-inline">
                                    <button class="btn btn-tool" type="button" data-bs-toggle="dropdown">
                                        <i class="fas fa-external-link-alt"></i>
                                    </button>
                                    <div class="dropdown-menu">
                                        <?php if ($asset_uri) { ?>
                                        <a href="<?= $asset_uri ?>" alt="<?= $asset_uri ?>" target="_blank" class="dropdown-item" >
                                            <i class="fa fa-fw fa-external-link-alt me-2"></i><?= truncate($asset_uri,40) ?>
                                        </a>
                                        <?php } ?>
                                        <?php if ($asset_uri_2) { ?>
                                        <div class="dropdown-divider"></div>
                                        <a href="<?= $asset_uri_2 ?>" target="_blank" class="dropdown-item" >
                                            <i class="fa fa-fw fa-external-link-alt me-2"></i><?= truncate($asset_uri_2,40) ?>
                                        </a>
                                        <?php } ?>
                                        <?php if ($asset_uri_client) { ?>
                                        <div class="dropdown-divider"></div>
                                        <a href="<?= $asset_uri_client ?>" target="_blank" class="dropdown-item" >
                                            <i class="fa fa-fw fa-external-link-alt me-2"></i>Client URI: <?= truncate($asset_uri_client,40) ?>
                                        </a>
                                        <?php } ?>
                                    </div>
                                </div>
                                <?php } ?>
                            </div>
                            <div><small class="text-secondary"><?= $asset_os ?></small></div>
                        </td>

                        <?php if ($_GET['type'] !== 'virtual') { ?>
                            <td>
                                <div><?= "$asset_make $asset_model" ?></div>
                                <div><small class="text-secondary"><?= $asset_serial_display ?></small></div>
                            </td>
                        <?php } ?>
                            <td class="font-monospace">
                                <?= $asset_ip ?>
                                <div class="text-secondary"><small><?= $asset_ipv6 ?></small></div>
                            </td>
                        <?php if (isset($_GET['show_column']) && is_array($_GET['show_column']) && in_array('Mac_Address', $_GET['show_column'])) { ?>
                            <td class="font-monospace"><?= $asset_mac ?></td>
                        <?php } ?>
                        <?php if (isset($_GET['show_column']) && is_array($_GET['show_column']) && in_array('Purchase_Date', $_GET['show_column'])) { ?>
                            <td><?= $asset_purchase_date_display ?></td>
                        <?php } ?>
                        <?php if (isset($_GET['show_column']) && is_array($_GET['show_column']) && in_array('Install_Date', $_GET['show_column'])) { ?>
                            <td><?= $asset_install_date_display ?></td>
                        <?php } ?>
                        <?php if (isset($_GET['show_column']) && is_array($_GET['show_column']) && in_array('Warranty_Expire', $_GET['show_column'])) { ?>
                            <td><?= $asset_warranty_expire_display ?></td>
                        <?php } ?>
                        <?php if ($_GET['type'] !== 'network' && $_GET['type'] !== 'other' && $_GET['type'] !== 'server') { ?>
                            <td><?= $contact_name_display ?></td>
                        <?php } ?>
                        <td>
                            <div><?= $location_name_display ?></div>
                            <div><small><?= $asset_physical_location_display ?></small></div>
                        </td>
                        <td><span class="badge rounded-pill bg-secondary p-2"><?= $asset_status ?></span></td>
                        <?php if (!$client_url) { ?>
                        <td><a href="assets.php?client_id=<?= $client_id ?>"><?= $client_name ?></a></td>
                        <?php } ?>
                        <td class="text-center">
                            <div class="btn-group">
                                <div class="dropdown dropstart text-center">
                                    <button class="btn btn-secondary btn-sm" type="button" data-bs-toggle="dropdown"><i class="fas fa-ellipsis-h"></i></button>
                                    <div class="dropdown-menu">
                                        <a class="dropdown-item ajax-modal" href="#" data-modal-url="modals/asset/asset_edit.php?id=<?= $asset_id ?>">
                                            <i class="fas fa-fw fa-edit me-2"></i>Edit
                                        </a>
                                        <a class="dropdown-item ajax-modal" href="#" data-modal-url="modals/asset/asset_copy.php?id=<?= $asset_id ?>">
                                            <i class="fas fa-fw fa-copy me-2"></i>Copy
                                        </a>
                                        <?php if ($session_user_role > 2) { ?>
                                            <?php if ($asset_archived_at) { ?>
                                            <a class="dropdown-item text-info" href="post.php?restore_asset=<?= $asset_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>">
                                                <i class="fas fa-fw fa-redo me-2"></i>Restore
                                            </a>
                                            <a class="dropdown-item text-danger text-bold confirm-link" href="post.php?delete_asset=<?= $asset_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>">
                                                <i class="fas fa-fw fa-trash me-2"></i>Delete
                                            </a>
                                            <?php } else { ?>
                                            <a class="dropdown-item text-danger confirm-link" href="post.php?archive_asset=<?= $asset_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>">
                                                <i class="fas fa-fw fa-archive me-2"></i>Archive
                                            </a>
                                            <?php } ?>

                                        <?php } ?>
                                    </div>
                                </div>
                            </div>
                        </td>
                    </tr>

                    <?php

                }

                ?>

                </tbody>
            </table>
        </div>
    </form>
    <?php require_once "../includes/filter_footer.php"; ?>
</div>

<script src="../js/bulk_actions.js"></script>

<?php
require_once "../includes/footer.php";
