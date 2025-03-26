<?php

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
    $client_query = '';
    $client_url = '';
}

// Perms
enforceUserPermission('module_support');

if (!$client_url) {
    // Client Filter
    if (isset($_GET['client']) & !empty($_GET['client'])) {
        $client_query = 'AND (network_client_id = ' . intval($_GET['client']) . ')';
        $client = intval($_GET['client']);
    } else {
        // Default - any
        $client_query = '';
        $client = '';
    }
}

if ($client_url && isset($_GET['location']) && !empty($_GET['location'])) {
    // Location Filter
    $location_query = 'AND (network_location_id = ' . intval($_GET['location']) . ')';
    $location_filter = intval($_GET['location']);
} else {
    // Default - any
    $location_query = '';
    $location_filter = 0;
}

$sql = mysqli_query(
    $mysqli,
    "SELECT SQL_CALC_FOUND_ROWS * FROM networks
    LEFT JOIN clients ON client_id = network_client_id
    LEFT JOIN locations ON location_id = network_location_id
    WHERE network_$archive_query
    AND (network_name LIKE '%$q%' OR network_description LIKE '%$q%' OR network_vlan LIKE '%$q%' OR network LIKE '%$q%' OR network_gateway LIKE '%$q%' OR network_subnet LIKE '%$q%' OR network_primary_dns LIKE '%$q%' OR network_secondary_dns LIKE '%$q%' OR network_dhcp_range LIKE '%$q%' OR location_name LIKE '%$q%' OR client_name LIKE '%$q%')
    $access_permission_query
    $location_query
    $client_query
    ORDER BY $sort $order LIMIT $record_from, $record_to"
);

$num_rows = mysqli_fetch_row(mysqli_query($mysqli, "SELECT FOUND_ROWS()"));

?>

<div class="card card-dark">
    <div class="card-header py-2">
        <h3 class="card-title mt-2"><i class="fas fa-fw fa-network-wired mr-2"></i>Networks</h3>
        <div class="card-tools">
            <div class="btn-group">
                <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#addNetworkModal"><i class="fas fa-plus mr-2"></i>New Network</button>
                <?php if ($num_rows[0] > 0) { ?>
                    <button type="button" class="btn btn-primary dropdown-toggle dropdown-toggle-split" data-toggle="dropdown"></button>
                    <div class="dropdown-menu">
                        <a class="dropdown-item text-dark" href="#" data-toggle="modal" data-target="#exportNetworkModal">
                            <i class="fa fa-fw fa-download mr-2"></i>Export
                        </a>
                    </div>
                <?php } ?>
            </div>

        </div>
    </div>
    <div class="card-body">
        <form autocomplete="off">
            <?php if ($client_url) { ?>
            <input type="hidden" name="client_id" value="<?php echo $client_id; ?>">
            <?php } ?>
            <div class="row">

                <div class="col-md-4">
                    <div class="input-group mb-3 mb-md-0">
                        <input type="search" class="form-control" name="q" value="<?php if (isset($q)) { echo stripslashes(nullable_htmlentities($q)); } ?>" placeholder="Search Networks">
                        <div class="input-group-append">
                            <button class="btn btn-dark"><i class="fa fa-search"></i></button>
                        </div>
                    </div>
                </div>

                <?php if ($client_url) { ?> 
                <div class="col-md-2">
                    <div class="input-group">
                        <select class="form-control select2" name="location" onchange="this.form.submit()">
                            <option value="">- All Locations -</option>

                            <?php
                            $sql_locations_filter = mysqli_query($mysqli, "
                                SELECT DISTINCT location_id, location_name
                                FROM locations
                                LEFT JOIN networks ON network_location_id = location_id
                                WHERE location_client_id = $client_id 
                                AND location_archived_at IS NULL 
                                AND (network_location_id != 0 OR location_id = $location_filter)
                                ORDER BY location_name ASC
                            ");
                            while ($row = mysqli_fetch_array($sql_locations_filter)) {
                                $location_id = intval($row['location_id']);
                                $location_name = nullable_htmlentities($row['location_name']);
                            ?>
                                <option <?php if ($location_filter == $location_id) { echo "selected"; } ?> value="<?php echo $location_id; ?>"><?php echo $location_name; ?></option>
                            <?php
                            }
                            ?>

                        </select>
                    </div>
                </div>
                <?php } else { ?>
                <div class="col-md-2">
                    <div class="input-group">
                        <select class="form-control select2" name="client" onchange="this.form.submit()">
                            <option value="" <?php if ($client == "") { echo "selected"; } ?>>- All Clients -</option>

                            <?php
                            $sql_clients_filter = mysqli_query($mysqli, "
                                SELECT DISTINCT client_id, client_name 
                                FROM clients
                                JOIN networks ON network_client_id = client_id
                                WHERE client_archived_at IS NULL 
                                $access_permission_query
                                ORDER BY client_name ASC
                            ");
                            while ($row = mysqli_fetch_array($sql_clients_filter)) {
                                $client_id = intval($row['client_id']);
                                $client_name = nullable_htmlentities($row['client_name']);
                            ?>
                                <option <?php if ($client == $client_id) { echo "selected"; } ?> value="<?php echo $client_id; ?>"><?php echo $client_name; ?></option>
                            <?php
                            }
                            ?>

                        </select>
                    </div>
                </div>
                <?php } ?>

                <div class="col-md-6">
                    <div class="btn-group float-right">
                        <div class="dropdown ml-2" id="bulkActionButton" hidden>
                            <button class="btn btn-secondary dropdown-toggle" type="button" data-toggle="dropdown">
                                <i class="fas fa-fw fa-layer-group mr-2"></i>Bulk Action (<span id="selectedCount">0</span>)
                            </button>
                            <div class="dropdown-menu">
                                <button class="dropdown-item text-danger text-bold confirm-link"
                                        type="submit" form="bulkActions" name="bulk_delete_networks">
                                    <i class="fas fa-fw fa-trash mr-2"></i>Delete
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </form>
        <hr>
        <div class="table-responsive-sm">

            <form id="bulkActions" action="post.php" method="post">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?>">

                <table class="table table-striped table-borderless table-hover">
                    <thead class="text-dark <?php if ($num_rows[0] == 0) { echo "d-none"; } ?>">
                    <tr>
                        <td class="pr-0">
                            <div class="form-check">
                                <input class="form-check-input" id="selectAllCheckbox" type="checkbox" onclick="checkAll(this)">
                            </div>
                        </td>
                        <th>
                            <a class="text-secondary" href="?<?php echo $url_query_strings_sb; ?>&sort=network_name&order=<?php echo $disp; ?>">
                                Name <?php if ($sort == 'network_name') { echo $order_icon; } ?>
                            </a>
                        </th>
                        <th>
                            <a class="text-secondary" href="?<?php echo $url_query_strings_sb; ?>&sort=network_vlan&order=<?php echo $disp; ?>">
                                vLAN <?php if ($sort == 'network_vlan') { echo $order_icon; } ?>
                            </a>
                        </th>
                        <th>
                            <a class="text-secondary" href="?<?php echo $url_query_strings_sb; ?>&sort=network&order=<?php echo $disp; ?>">
                                IP / Network <?php if ($sort == 'network') { echo $order_icon; } ?>
                            </a>
                        </th>
                        <th>
                            <a class="text-secondary" href="?<?php echo $url_query_strings_sb; ?>&sort=network_gateway&order=<?php echo $disp; ?>">
                                Gateway <?php if ($sort == 'network_gateway') { echo $order_icon; } ?>
                            </a>
                        </th>
                        <th>
                            <a class="text-secondary" href="?<?php echo $url_query_strings_sb; ?>&sort=network_primary_dns&order=<?php echo $disp; ?>">
                                DNS <?php if ($sort == 'network_primary_dns') { echo $order_icon; } ?>
                            </a>
                        </th>
                        <th>
                            <a class="text-secondary" href="?<?php echo $url_query_strings_sb; ?>&sort=network_dhcp_range&order=<?php echo $disp; ?>">
                                DHCP Range <?php if ($sort == 'network_dhcp_range') { echo $order_icon; } ?>
                            </a>
                        </th>
                        <th>
                            <a class="text-secondary" href="?<?php echo $url_query_strings_sb; ?>&sort=location_name&order=<?php echo $disp; ?>">
                                Location <?php if ($sort == 'location_name') { echo $order_icon; } ?>
                            </a>
                        </th>
                        <?php if (!$client_url) { ?>
                        <th>
                            <a class="text-secondary" href="?<?php echo $url_query_strings_sort; ?>&sort=client_name&order=<?php echo $disp; ?>">
                                Client <?php if ($sort == 'client_name') { echo $order_icon; } ?>
                            </a>
                        </th>
                        <?php } ?>
                        <th class="text-center">Action</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php

                    while ($row = mysqli_fetch_array($sql)) {
                        $client_id = intval($row['client_id']);
                        $client_name = nullable_htmlentities($row['client_name']);
                        $network_id = intval($row['network_id']);
                        $network_name = nullable_htmlentities($row['network_name']);
                        $network_description = nullable_htmlentities($row['network_description']);
                        $network_vlan = intval($row['network_vlan']);
                        if ($network_vlan) {
                            $network_vlan_display = $network_vlan;
                        } else {
                            $network_vlan_display = "-";
                        }
                        $network = nullable_htmlentities($row['network']);
                        $network_subnet = nullable_htmlentities($row['network_subnet']);
                        $network_gateway = nullable_htmlentities($row['network_gateway']);
                        $network_primary_dns = nullable_htmlentities($row['network_primary_dns']);
                        $network_secondary_dns = nullable_htmlentities($row['network_secondary_dns']);
                        if ($network_primary_dns) {
                            $network_dns_display = "$network_primary_dns<div class='text-secondary mt-1'>$network_secondary_dns</div>";
                        } else {
                            $network_dns_display = "-";
                        }
                        $network_dhcp_range = nullable_htmlentities($row['network_dhcp_range']);
                        if (empty($network_dhcp_range)) {
                            $network_dhcp_range_display = "-";
                        } else {
                            $network_dhcp_range_display = $network_dhcp_range;
                        }
                        $network_location_id = intval($row['network_location_id']);
                        $location_name = nullable_htmlentities($row['location_name']);
                        if (empty($location_name)) {
                            $location_name_display = "-";
                        } else {
                            $location_name_display = $location_name;
                        }

                        ?>
                        <tr>
                            <td class="pr-0">
                                <div class="form-check">
                                    <input class="form-check-input bulk-select" type="checkbox" name="network_ids[]" value="<?php echo $network_id ?>">
                                </div>
                            </td>
                            <td>
                                <a class="text-dark" href="#"
                                    data-toggle="ajax-modal"
                                    data-ajax-url="ajax/ajax_network_edit.php"
                                    data-ajax-id="<?php echo $network_id; ?>"
                                    >
                                    <div class="media">
                                        <i class="fa fa-fw fa-2x fa-network-wired mr-3"></i>
                                        <div class="media-body">
                                            <div><?php echo $network_name; ?></div>
                                            <div><small class="text-secondary"><?php echo $network_description; ?></small></div>
                                        </div>
                                    </div>
                                </a>
                            </td>
                            <td><?php echo $network_vlan_display; ?></td>
                            <td>
                                <?php echo $network; ?>
                                <div class="text-secondary mt-1"><?php echo $network_subnet; ?></div>
                            </td>
                            <td><?php echo $network_gateway; ?></td>
                            <td><?php echo $network_dns_display; ?></td>
                            <td><?php echo $network_dhcp_range_display; ?></td>
                            <td><?php echo $location_name_display; ?></td>
                            <?php if (!$client_url) { ?>
                            <td><a href="networks.php?client_id=<?php echo $client_id; ?>"><?php echo $client_name; ?></a></td>
                            <?php } ?>
                            <td>
                                <div class="dropdown dropleft text-center">
                                    <button class="btn btn-secondary btn-sm" type="button" data-toggle="dropdown">
                                        <i class="fas fa-ellipsis-h"></i>
                                    </button>
                                    <div class="dropdown-menu">
                                        <a class="dropdown-item" href="#"
                                            data-toggle="ajax-modal"
                                            data-ajax-url="ajax/ajax_network_edit.php"
                                            data-ajax-id="<?php echo $network_id; ?>"
                                            >
                                            <i class="fas fa-fw fa-edit mr-2"></i>Edit
                                        </a>
                                        <?php if ($session_user_role == 3) { ?>
                                            <div class="dropdown-divider"></div>
                                            <a class="dropdown-item text-danger confirm-link" href="post.php?archive_network=<?php echo $network_id; ?>">
                                                <i class="fas fa-fw fa-archive mr-2"></i>Archive
                                            </a>
                                            <div class="dropdown-divider"></div>
                                            <a class="dropdown-item text-danger text-bold confirm-link" href="post.php?delete_network=<?php echo $network_id; ?>">
                                                <i class="fas fa-fw fa-trash mr-2"></i>Delete
                                            </a>
                                        <?php } ?>
                                    </div>
                                </div>
                            </td>
                        </tr>

                    <?php } ?>

                    </tbody>
                </table>

            </form>
        </div>
        <?php require_once "includes/filter_footer.php";
        ?>
    </div>
</div>

<?php
require_once "modals/network_add_modal.php";
require_once "modals/network_export_modal.php";

?>

<script src="js/bulk_actions.js"></script>

<?php
require_once "includes/footer.php";
