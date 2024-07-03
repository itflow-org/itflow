<?php

// Default Column Sortby Filter
$sort = "asset_name";
$order = "ASC";

require_once "inc_all_client.php";

//Asset Type from GET
if (isset($_GET['type']) && ($_GET['type']) == 'workstation') {
    $type_query = "asset_type = 'desktop' OR asset_type = 'laptop'";
} elseif (isset($_GET['type']) && ($_GET['type']) == 'server') {
    $type_query = "asset_type = 'server'";
} elseif (isset($_GET['type']) && ($_GET['type']) == 'virtual') {
    $type_query = "asset_type = 'Virtual Machine'";
} elseif (isset($_GET['type']) && ($_GET['type']) == 'network') {
    $type_query = "asset_type = 'Firewall/Router' OR asset_type = 'Switch' OR asset_type = 'Access Point'";
} elseif (isset($_GET['type']) && ($_GET['type']) == 'other') {
    $type_query = "asset_type NOT LIKE 'laptop' AND asset_type NOT LIKE 'desktop' AND asset_type NOT LIKE 'server' AND asset_type NOT LIKE 'virtual machine' AND asset_type NOT LIKE 'firewall/router' AND asset_type NOT LIKE 'switch' AND asset_type NOT LIKE 'access point'";
} else {
    $type_query = "asset_type LIKE '%'";
    $_GET['type'] = '';
}

// Location Filter
if (isset($_GET['location']) & !empty($_GET['location'])) {
    $location_query = 'AND (asset_location_id = ' . intval($_GET['location']) . ')';
    $location = intval($_GET['location']);
} else {
    // Default - any
    $location_query = '';
    $location = '';
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
        LEFT JOIN contacts ON asset_contact_id = contact_id 
        LEFT JOIN locations ON asset_location_id = location_id 
        LEFT JOIN asset_interfaces ON interface_asset_id = asset_id AND interface_primary = 1
        WHERE asset_client_id = $client_id
        AND asset_$archive_query
        AND (asset_name LIKE '%$q%' OR asset_description LIKE '%$q%' OR asset_type LIKE '%$q%' OR interface_ip LIKE '%$q%' OR interface_ipv6 LIKE '%$q%' OR asset_make LIKE '%$q%' OR asset_model LIKE '%$q%' OR asset_serial LIKE '%$q%' OR asset_os LIKE '%$q%' OR contact_name LIKE '%$q%' OR location_name LIKE '%$q%')
        $location_query
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

//Rebuild URL
$url_query_strings_sort = http_build_query($get_copy);

$sql = mysqli_query(
    $mysqli,
    "SELECT SQL_CALC_FOUND_ROWS * FROM assets 
    LEFT JOIN contacts ON asset_contact_id = contact_id 
    LEFT JOIN locations ON asset_location_id = location_id 
    LEFT JOIN asset_interfaces ON interface_asset_id = asset_id AND interface_primary = 1
    WHERE asset_client_id = $client_id
    AND asset_$archive_query
    AND (asset_name LIKE '%$q%' OR asset_description LIKE '%$q%' OR asset_type LIKE '%$q%' OR interface_ip LIKE '%$q%' OR interface_ipv6 LIKE '%$q%' OR asset_make LIKE '%$q%' OR asset_model LIKE '%$q%' OR asset_serial LIKE '%$q%' OR asset_os LIKE '%$q%' OR contact_name LIKE '%$q%' OR location_name LIKE '%$q%')
    AND ($type_query)
    $location_query
    ORDER BY $sort $order LIMIT $record_from, $record_to"
);

$num_rows = mysqli_fetch_row(mysqli_query($mysqli, "SELECT FOUND_ROWS()"));

?>

    <div class="card card-dark">
        <div class="card-header py-2">
            <h3 class="card-title mt-2"><i class="fa fa-fw fa-desktop mr-2"></i>Assets</h3>
            <div class="card-tools">
                <div class="btn-group">
                    <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#addAssetModal">
                        <i class="fas fa-plus mr-2"></i>New <?php if (!empty($_GET['type'])) { echo ucwords(strip_tags(nullable_htmlentities($_GET['type']))); } else { echo "Asset"; } ?>
                    </button>
                    <button type="button" class="btn btn-primary dropdown-toggle dropdown-toggle-split" data-toggle="dropdown"></button>
                    <div class="dropdown-menu">
                        <a class="dropdown-item text-dark" href="#" data-toggle="modal" data-target="#importAssetModal">
                            <i class="fa fa-fw fa-upload mr-2"></i>Import
                        </a>
                        <div class="dropdown-divider"></div>
                        <a class="dropdown-item text-dark" href="#" data-toggle="modal" data-target="#exportAssetModal">
                            <i class="fa fa-fw fa-download mr-2"></i>Export
                        </a>
                    </div>
                </div>
            </div>
        </div>
        <div class="card-body">
            <form autocomplete="off">
                <input type="hidden" name="client_id" value="<?php echo $client_id; ?>">
                <input type="hidden" name="type" value="<?php echo stripslashes(nullable_htmlentities($_GET['type'])); ?>">
                <input type="hidden" name="archived" value="<?php echo $archived; ?>">
                <div class="row">

                    <div class="col-md-4">
                        <div class="input-group mb-3 mb-md-0">
                            <input type="search" class="form-control" name="q" value="<?php if (isset($q)) { echo stripslashes(nullable_htmlentities($q)); } ?>" placeholder="Search <?php if (!empty($_GET['type'])) { echo ucwords(stripslashes(nullable_htmlentities($_GET['type']))); } else { echo "Asset"; } ?>s">
                            <div class="input-group-append">
                                <button class="btn btn-dark"><i class="fa fa-search"></i></button>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="input-group">
                            <select class="form-control select2" name="location" onchange="this.form.submit()">
                                <option value="" <?php if ($location == "") { echo "selected"; } ?>>- All Locations -</option>

                                <?php
                                $sql_locations_filter = mysqli_query($mysqli, "SELECT * FROM locations WHERE location_client_id = $client_id AND location_archived_at IS NULL ORDER BY location_name ASC");
                                while ($row = mysqli_fetch_array($sql_locations_filter)) {
                                    $location_id = intval($row['location_id']);
                                    $location_name = nullable_htmlentities($row['location_name']);
                                ?>
                                    <option <?php if ($location == $location_id) { echo "selected"; } ?> value="<?php echo $location_id; ?>"><?php echo $location_name; ?></option>
                                <?php
                                }
                                ?>

                            </select>
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="btn-toolbar float-right">
                            <div class="btn-group mr-5">
                                <?php if($all_count) { ?>
                                <a href="?<?php echo $url_query_strings_sort; ?>&type=" class="btn <?php if ($_GET['type'] == 'all' || empty($_GET['type'])) { echo 'btn-primary'; } else { echo 'btn-default'; } ?>">All Assets<span class="right badge badge-light ml-2"><?php echo $all_count; ?></span></a>
                                <?php } ?>
                                <?php
                                if ($workstation_count > 0) { ?>
                                    <a href="?<?php echo $url_query_strings_sort; ?>&type=workstation" class="btn <?php if ($_GET['type'] == 'workstation') { echo 'btn-primary'; } else { echo 'btn-default'; } ?>"><i class="fa fa-fw fa-desktop mr-2"></i>Workstations<span class="right badge badge-light ml-2"><?php echo $workstation_count; ?></span></a>
                                    <?php
                                }
                                if ($server_count > 0) { ?>
                                    <a href="?<?php echo $url_query_strings_sort; ?>&type=server" class="btn <?php if ($_GET['type'] == 'server') { echo 'btn-primary'; } else { echo 'btn-default'; } ?>"><i class="fa fa-fw fa-server mr-2"></i>Servers<span class="right badge badge-light ml-2"><?php echo $server_count; ?></span></a>
                                    <?php
                                }
                                if ($virtual_count > 0) { ?>
                                    <a href="?<?php echo $url_query_strings_sort; ?>&type=virtual" class="btn <?php if ($_GET['type'] == 'virtual') { echo 'btn-primary'; } else { echo 'btn-default'; } ?>"><i class="fa fa-fw fa-cloud mr-2"></i>Virtual<span class="right badge badge-light ml-2"><?php echo $virtual_count; ?></span></a>
                                    <?php
                                }
                                if ($network_count > 0) { ?>
                                    <a href="?<?php echo $url_query_strings_sort; ?>&type=network" class="btn <?php if ($_GET['type'] == 'network') { echo 'btn-primary'; } else { echo 'btn-default'; } ?>"><i class="fa fa-fw fa-network-wired mr-2"></i>Network<span class="right badge badge-light ml-2"><?php echo $network_count; ?></span></a>
                                    <?php
                                }
                                if ($other_count > 0) { ?>
                                    <a href="?<?php echo $url_query_strings_sort; ?>&type=other" class="btn <?php if ($_GET['type'] == 'other') { echo 'btn-primary'; } else { echo 'btn-default'; } ?>"><i class="fa fa-fw fa-tag mr-2"></i>Other<span class="right badge badge-light ml-2"><?php echo $other_count; ?></span></a>
                                    <?php
                                } ?>
                            </div>
                            <div class="btn-group mr-2">
                                <a href="?client_id=<?php echo $client_id; ?>&archived=<?php if($archived == 1){ echo 0; } else { echo 1; } ?>" 
                                    class="btn btn-<?php if($archived == 1){ echo "primary"; } else { echo "default"; } ?>">
                                    <i class="fa fa-fw fa-archive mr-2"></i>Archived
                                </a>
                                <div class="dropdown ml-2" id="bulkActionButton" hidden>
                                    <button class="btn btn-secondary dropdown-toggle" type="button" data-toggle="dropdown">
                                        <i class="fas fa-fw fa-layer-group mr-2"></i>Bulk Action (<span id="selectedCount">0</span>)
                                    </button>
                                    <div class="dropdown-menu">
                                        <a class="dropdown-item" href="#" data-toggle="modal" data-target="#bulkAssignContactModal">
                                            <i class="fas fa-fw fa-user mr-2"></i>Assign Contact
                                        </a>
                                        <div class="dropdown-divider"></div>
                                        <a class="dropdown-item" href="#" data-toggle="modal" data-target="#bulkAssignLocationModal">
                                            <i class="fas fa-fw fa-map-marker-alt mr-2"></i>Assign Location
                                        </a>
                                        <div class="dropdown-divider"></div>
                                        <a class="dropdown-item" href="#" data-toggle="modal" data-target="#bulkEditStatusModal">
                                            <i class="fas fa-fw fa-info mr-2"></i>Set Status
                                        </a>
                                        <?php if ($archived) { ?>
                                        <div class="dropdown-divider"></div>
                                        <button class="dropdown-item text-info"
                                            type="submit" form="bulkActions" name="bulk_unarchive_assets">
                                            <i class="fas fa-fw fa-redo mr-2"></i>Unarchive
                                        </button>
                                        <?php } else { ?>
                                        <div class="dropdown-divider"></div>
                                        <button class="dropdown-item text-danger confirm-link"
                                            type="submit" form="bulkActions" name="bulk_archive_assets">
                                            <i class="fas fa-fw fa-archive mr-2"></i>Archive
                                        </button>
                                        <?php } ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
            <hr>
            <form id="bulkActions" action="post.php" method="post">
                <div class="table-responsive">
                    <table class="table border table-hover">
                        <thead class="thead-light <?php if (!$num_rows[0]) { echo "d-none"; } ?>">
                        <tr>
                            <td class="bg-light pr-0">
                                <div class="form-check">
                                    <input class="form-check-input" id="selectAllCheckbox" type="checkbox" onclick="checkAll(this)">
                                </div>
                            </td>
                            <th><a class="text-secondary" href="?<?php echo $url_query_strings_sort; ?>&sort=asset_name&order=<?php echo $disp; ?>">Name</a></th>
                            <?php if ($_GET['type'] !== 'virtual' && $_GET['type'] !== 'servers') { ?>
                                <th><a class="text-secondary" href="?<?php echo $url_query_strings_sort; ?>&sort=asset_type&order=<?php echo $disp; ?>">Type</a></th>
                            <?php }
                            if ($_GET['type'] !== 'virtual') { ?>
                                <th><a class="text-secondary" href="?<?php echo $url_query_strings_sort; ?>&sort=asset_make&order=<?php echo $disp; ?>">Model</a></th>
                            <?php }
                            if ($_GET['type'] !== 'virtual') { ?>
                                <th><a class="text-secondary" href="?<?php echo $url_query_strings_sort; ?>&sort=asset_serial&order=<?php echo $disp; ?>">Serial</a></th>
                            <?php }
                            if ($_GET['type'] !== 'network' && $_GET['type'] !== 'other') { ?>
                                <th><a class="text-secondary" href="?<?php echo $url_query_strings_sort; ?>&sort=asset_os&order=<?php echo $disp; ?>">OS</a></th>
                            <?php } ?>
                            <th><a class="text-secondary" href="?<?php echo $url_query_strings_sort; ?>&sort=asset_ip&order=<?php echo $disp; ?>">IP</a></th>
                            <th><a class="text-secondary" href="?<?php echo $url_query_strings_sort; ?>&sort=asset_install_date&order=<?php echo $disp; ?>">Install Date</a></th>
                            <?php if ($_GET['type'] !== 'network' && $_GET['type'] !== 'servers' && $_GET['type'] !== 'other') { ?>
                                <th><a class="text-secondary" href="?<?php echo $url_query_strings_sort; ?>&sort=contact_name&order=<?php echo $disp; ?>">Assigned To</a></th>
                            <?php } ?>
                            <th><a class="text-secondary" href="?<?php echo $url_query_strings_sort; ?>&sort=location_name&order=<?php echo $disp; ?>">Location</a></th>
                            <th><a class="text-secondary" href="?<?php echo $url_query_strings_sort; ?>&sort=asset_status&order=<?php echo $disp; ?>">Status</a></th>
                            <th class="text-center">Action</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php

                        while ($row = mysqli_fetch_array($sql)) {
                            $asset_id = intval($row['asset_id']);
                            $asset_type = nullable_htmlentities($row['asset_type']);
                            $asset_name = nullable_htmlentities($row['asset_name']);
                            $asset_description = nullable_htmlentities($row['asset_description']);
                            if (empty($asset_description)) {
                                $asset_description_display = "-";
                            } else {
                                $asset_description_display = $asset_description;
                            }
                            $asset_make = nullable_htmlentities($row['asset_make']);
                            $asset_model = nullable_htmlentities($row['asset_model']);
                            $asset_serial = nullable_htmlentities($row['asset_serial']);
                            if (empty($asset_serial)) {
                                $asset_serial_display = "-";
                            } else {
                                $asset_serial_display = $asset_serial;
                            }
                            $asset_os = nullable_htmlentities($row['asset_os']);
                            if (empty($asset_os)) {
                                $asset_os_display = "-";
                            } else {
                                $asset_os_display = $asset_os;
                            }
                            $asset_ip = nullable_htmlentities($row['interface_ip']);
                            if (empty($asset_ip)) {
                                $asset_ip_display = "-";
                            } else {
                                $asset_ip_display = $asset_ip;
                            }
                            $asset_ipv6 = nullable_htmlentities($row['interface_ipv6']);
                            $asset_nat_ip = nullable_htmlentities($row['interface_nat_ip']);
                            $asset_mac = nullable_htmlentities($row['interface_mac']);
                            $asset_uri = nullable_htmlentities($row['asset_uri']);
                            $asset_uri_2 = nullable_htmlentities($row['asset_uri_2']);
                            $asset_status = nullable_htmlentities($row['asset_status']);
                            $asset_purchase_date = nullable_htmlentities($row['asset_purchase_date']);
                            $asset_warranty_expire = nullable_htmlentities($row['asset_warranty_expire']);
                            $asset_install_date = nullable_htmlentities($row['asset_install_date']);
                            if (empty($asset_install_date)) {
                                $asset_install_date_display = "-";
                            } else {
                                $asset_install_date_display = $asset_install_date;
                            }
                            $asset_photo = nullable_htmlentities($row['asset_photo']);
                            $asset_physical_location = nullable_htmlentities($row['asset_physical_location']);
                            $asset_notes = nullable_htmlentities($row['asset_notes']);
                            $asset_created_at = nullable_htmlentities($row['asset_created_at']);
                            $asset_archived_at = nullable_htmlentities($row['asset_archived_at']);
                            $asset_vendor_id = intval($row['asset_vendor_id']);
                            $asset_location_id = intval($row['asset_location_id']);
                            $asset_contact_id = intval($row['asset_contact_id']);
                            $asset_network_id = intval($row['interface_network_id']);

                            $device_icon = getAssetIcon($asset_type);

                            $contact_name = nullable_htmlentities($row['contact_name']);
                            if (empty($contact_name)) {
                                $contact_name = "-";
                            }
                            $contact_archived_at = nullable_htmlentities($row['contact_archived_at']);
                            if ($contact_archived_at) {
                                $contact_name_display = "<div class='text-danger' title='Archived'><s>$contact_name</s></div>";
                            } else {
                                $contact_name_display = $contact_name;
                            }

                            $location_name = nullable_htmlentities($row['location_name']);
                            if (empty($location_name)) {
                                $location_name = "-";
                            }
                            $location_archived_at = nullable_htmlentities($row['location_archived_at']);
                            if ($location_archived_at) {
                                $location_name_display = "<div class='text-danger' title='Archived'><s>$location_name</s></div>";
                            } else {
                                $location_name_display = $location_name;
                            }

                            $sql_logins = mysqli_query($mysqli, "SELECT * FROM logins WHERE login_asset_id = $asset_id");
                            $login_count = mysqli_num_rows($sql_logins);

                            ?>
                            <tr>
                                <td class="pr-0 bg-light">
                                    <div class="form-check">
                                        <input class="form-check-input bulk-select" type="checkbox" name="asset_ids[]" value="<?php echo $asset_id ?>">
                                    </div>
                                </td>
                                <td>
                                    <a href="client_asset_details.php?client_id=<?php echo $client_id; ?>&asset_id=<?php echo $asset_id; ?>" class="text-dark">
                                        <div class="media">
                                            <i class="fa fa-fw fa-2x fa-<?php echo $device_icon; ?> mr-3 mt-1"></i>
                                            <div class="media-body">
                                                <div><?php echo $asset_name; ?></div>
                                                <div><small class="text-secondary"><?php echo $asset_description; ?></small></div>
                                            </div>
                                        </div>
                                    </a>
                                </td>

                                <?php if ($_GET['type'] !== 'virtual' && $_GET['type'] !== 'servers') { ?>
                                    <td><?php echo $asset_type; ?></td>
                                <?php } ?>
                                <?php if ($_GET['type'] !== 'virtual') { ?>
                                    <td>
                                        <?php echo $asset_make; ?>
                                        <div class="mt-0">
                                            <small class="text-muted"><?php echo $asset_model; ?></small>
                                        </div>
                                    </td>
                                <?php } ?>
                                <?php if ($_GET['type'] !== 'virtual') { ?>
                                    <td><?php echo $asset_serial_display; ?></td>
                                <?php } ?>
                                <?php if ($_GET['type'] !== 'network' && $_GET['type'] !== 'other') { ?>
                                    <td><?php echo $asset_os_display; ?></td>
                                <?php } ?>
                                <td><?php echo $asset_ip_display; ?></td>
                                <td><?php echo $asset_install_date_display; ?></td>
                                <?php if ($_GET['type'] !== 'network' && $_GET['type'] !== 'other' && $_GET['type'] !== 'servers') { ?>
                                    <td><?php echo $contact_name_display; ?></td>
                                <?php } ?>
                                <td><?php echo $location_name_display; ?></td>
                                <td><?php echo $asset_status; ?></td>
                                <td class="text-center">
                                    <div class="btn-group">
                                        <?php if ($login_count > 0) { ?>
                                        <button type="button" class="btn btn-default btn-sm" data-toggle="modal" data-target="#viewPasswordModal<?php echo $asset_id; ?>"><i class="fas fa-key text-dark"></i></button>

                                        <div class="modal" id="viewPasswordModal<?php echo $asset_id; ?>" tabindex="-1">
                                            <div class="modal-dialog">
                                                <div class="modal-content bg-dark">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title"><i class="fa fa-fw fa-key mr-2"></i><?php echo $asset_name; ?></h5>
                                                        <button type="button" class="close text-white" data-dismiss="modal">
                                                            <span>&times;</span>
                                                        </button>
                                                    </div>
                                                    <div class="modal-body bg-white">

                                                        <?php while ($row = mysqli_fetch_array($sql_logins)) {
                                                            $login_id = intval($row['login_id']);
                                                            $login_username = nullable_htmlentities(decryptLoginEntry($row['login_username']));
                                                            $login_password = nullable_htmlentities(decryptLoginEntry($row['login_password']));
                                                            ?>

                                                            <div class="form-group">
                                                                <div class="input-group">
                                                                    <div class="input-group-prepend">
                                                                        <span class="input-group-text"><i class="fa fa-user"></i></span>
                                                                    </div>
                                                                    <input type="text" class="form-control" value="<?php echo $login_username; ?>" readonly>
                                                                    <div class="input-group-append">
                                                                        <button class="btn btn-default clipboardjs" type="button" data-clipboard-text="<?php echo $login_username; ?>"><i class="fa fa-fw fa-copy"></i></button>
                                                                    </div>
                                                                </div>
                                                            </div>

                                                            <div class="form-group">
                                                                <div class="input-group">
                                                                    <div class="input-group-prepend">
                                                                        <span class="input-group-text"><i class="fa fa-lock"></i></span>
                                                                    </div>
                                                                    <input type="text" class="form-control" value="<?php echo $login_password; ?>" readonly autocomplete="off">
                                                                    <div class="input-group-append">
                                                                        <button class="btn btn-default clipboardjs" type="button" data-clipboard-text="<?php echo $login_password; ?>"><i class="fa fa-fw fa-copy"></i></button>
                                                                    </div>
                                                                </div>
                                                            </div>

                                                        <?php } ?>

                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <?php } ?>
                                        <?php if ( !empty($asset_uri) || !empty($asset_uri_2) ) { ?>
                                        <div class="dropdown dropleft text-center">
                                            <button class="btn btn-default btn-sm" type="button" data-toggle="dropdown">
                                                <i class="fa fa-fw fa-external-link-alt"></i>
                                            </button>
                                            <div class="dropdown-menu">
                                                <?php if ($asset_uri) { ?>
                                                <a href="<?php echo $asset_uri; ?>" alt="<?php echo $asset_uri; ?>" target="_blank" class="dropdown-item" >
                                                    <i class="fa fa-fw fa-external-link-alt"></i> <?php echo truncate($asset_uri,40); ?>
                                                </a>
                                                <?php } ?>
                                                <?php if ($asset_uri_2) { ?>
                                                <div class="dropdown-divider"></div>
                                                <a href="<?php echo $asset_uri_2; ?>" target="_blank" class="dropdown-item" >
                                                    <i class="fa fa-fw fa-external-link-alt"></i> <?php echo truncate($asset_uri_2,40); ?>
                                                </a>
                                                <?php } ?>
                                            </div>
                                        </div>
                                        <?php } ?>
                                        <div class="dropdown dropleft text-center">
                                            <button class="btn btn-secondary btn-sm" type="button" data-toggle="dropdown"><i class="fas fa-ellipsis-h"></i></button>
                                            <div class="dropdown-menu">
                                                <a class="dropdown-item" href="#" data-toggle="modal" data-target="#editAssetModal<?php echo $asset_id; ?>">
                                                    <i class="fas fa-fw fa-edit mr-2"></i>Edit
                                                </a>
                                                <a class="dropdown-item" href="#" data-toggle="modal" data-target="#copyAssetModal<?php echo $asset_id; ?>">
                                                    <i class="fas fa-fw fa-copy mr-2"></i>Copy
                                                </a>
                                                <?php if ($session_user_role > 2) { ?>
                                                    <?php if ($asset_archived_at) { ?>
                                                    <a class="dropdown-item text-info" href="post.php?unarchive_asset=<?php echo $asset_id; ?>">
                                                        <i class="fas fa-fw fa-redo mr-2"></i>Unarchive
                                                    </a>
                                                    <?php } else { ?>
                                                    <a class="dropdown-item text-danger confirm-link" href="post.php?archive_asset=<?php echo $asset_id; ?>">
                                                        <i class="fas fa-fw fa-archive mr-2"></i>Archive
                                                    </a>
                                                    <?php } ?>
                                                    <?php if ($config_destructive_deletes_enable) { ?>
                                                    <a class="dropdown-item text-danger text-bold confirm-link" href="post.php?delete_asset=<?php echo $asset_id; ?>">
                                                        <i class="fas fa-fw fa-archive mr-2"></i>Delete
                                                    </a>
                                                    <?php } ?>

                                                <?php } ?>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                            </tr>

                            <?php

                            require "client_asset_edit_modal.php";

                            require "client_asset_copy_modal.php";


                        }

                        ?>

                        </tbody>
                    </table>
                </div>
                <?php require_once "client_asset_bulk_assign_location_modal.php"; ?>
                <?php require_once "client_asset_bulk_assign_contact_modal.php"; ?>
                <?php require_once "client_asset_bulk_edit_status_modal.php"; ?>
            </form>
            <?php require_once "pagination.php"; ?>
        </div>
    </div>

<script src="js/bulk_actions.js"></script>

<?php
require_once "client_asset_add_modal.php";

require_once "client_asset_import_modal.php";

require_once "client_asset_export_modal.php";

require_once "footer.php";

