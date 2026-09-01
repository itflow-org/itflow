<?php

// Default Column Sortby Filter
$sort = "ip_address";
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

$network_id = intval($_GET['network_id'] ?? 0);

$sql = mysqli_query(
    $mysqli,
    "SELECT client_id, client_name, location_name, network, network_client_id, network_description,
        network_dhcp_range, network_gateway, network_id, network_name, network_notes,
        network_primary_dns, network_secondary_dns, network_vlan FROM networks
    LEFT JOIN clients ON client_id = network_client_id
    LEFT JOIN locations ON location_id = network_location_id
    WHERE network_id = $network_id
    " . clientScopeSql('network_client_id') . "
    $client_query
    LIMIT 1"
);

if (mysqli_num_rows($sql) == 0) {

    echo "<center><h1 class='text-secondary mt-5'>Nothing to see here</h1><a class='btn btn-lg btn-secondary mt-3' href='javascript:history.back()'><i class='fa fa-fw fa-arrow-left'></i> Go Back</a></center>";

} else {

    $row = mysqli_fetch_assoc($sql);
    $client_id = intval($row['network_client_id']);
    $client_name = escapeHtml($row['client_name']);
    $network_name = escapeHtml($row['network_name']);
    $network_description = escapeHtml($row['network_description']);
    $network_vlan = intval($row['network_vlan']);
    $network = escapeHtml($row['network']);
    $network_gateway = escapeHtml($row['network_gateway']);
    $network_primary_dns = escapeHtml($row['network_primary_dns']);
    $network_secondary_dns = escapeHtml($row['network_secondary_dns']);
    $network_dhcp_range = escapeHtml($row['network_dhcp_range']);
    $network_notes = escapeHtml($row['network_notes']);
    $location_name = escapeHtml($row['location_name']);

    // Belt and braces - the query above is already scoped, but the no-client_id
    // entry point relies entirely on clientScopeSql()
    enforceClientAccess();

    // Sorting - the search box posts back through filter_header.php, which will
    // accept any lowercase column name, so keep this to columns that exist
    if (!in_array($sort, ['ip_address', 'ip_hostname', 'ip_description'], true)) {
        $sort = 'ip_address';
    }

    // Addresses sort by value, not as text - .9 belongs before .10, and this is
    // the one ordering that works for IPv4 and IPv6 in the same column
    $order_by = ($sort === 'ip_address') ? "INET6_ATON(ip_address)" : $sort;

    $sql_ips = mysqli_query(
        $mysqli,
        "SELECT SQL_CALC_FOUND_ROWS ip_address, ip_description, ip_hostname, ip_id FROM network_ips
        WHERE ip_network_id = $network_id
        AND (ip_address LIKE '%$q%' OR ip_hostname LIKE '%$q%' OR ip_description LIKE '%$q%')
        ORDER BY $order_by $order LIMIT $record_from, $record_to"
    );

    $num_rows = mysqli_fetch_row(mysqli_query($mysqli, "SELECT FOUND_ROWS()"));

    // The count above reflects the search box - the sidebar wants the real total
    $ip_total = intval(mysqli_fetch_row(mysqli_query($mysqli, "SELECT COUNT(ip_id) FROM network_ips WHERE ip_network_id = $network_id"))[0]);

    ?>

    <ol class="breadcrumb d-print-none">
        <li class="breadcrumb-item">
            <a href="client_overview.php?client_id=<?= $client_id ?>"><?= $client_name ?></a>
        </li>
        <li class="breadcrumb-item">
            <a href="networks.php?<?= $client_url ?>">Networks</a>
        </li>
        <li class="breadcrumb-item active">
            <i class="fas fa-fw fa-network-wired"></i> <?= $network_name ?>
        </li>
    </ol>

    <div class="row">

        <div class="col-md-3">

            <div class="card mb-3">
                <div class="card-header">
                    <button type="button" class="btn btn-light float-end ajax-modal"
                        data-modal-url="modals/network/network_edit.php?id=<?= $network_id ?>">
                        <i class="fas fa-fw fa-edit"></i>
                    </button>
                    <h4 class="text-bold"><i class="fa fa-fw text-secondary fa-network-wired me-2"></i><?= $network_name ?></h4>
                    <?php if ($network_description) { ?>
                        <div class="text-secondary"><?= $network_description ?></div>
                    <?php } ?>
                </div>
                <div class="card-body">

                    <div class="text-secondary small text-uppercase">Network</div>
                    <div class="font-monospace mb-3"><i class="fa fa-fw fa-network-wired text-secondary me-2"></i><?= $network ?></div>

                    <?php if ($network_vlan) { ?>
                        <div class="text-secondary small text-uppercase">VLAN</div>
                        <div class="font-monospace mb-3"><i class="fa fa-fw fa-layer-group text-secondary me-2"></i><?= $network_vlan ?></div>
                    <?php }
                    if ($network_gateway) { ?>
                        <div class="text-secondary small text-uppercase">Gateway</div>
                        <div class="font-monospace mb-3"><i class="fa fa-fw fa-route text-secondary me-2"></i><?= $network_gateway ?></div>
                    <?php }
                    if ($network_dhcp_range) { ?>
                        <div class="text-secondary small text-uppercase">Assignable IP Range</div>
                        <div class="font-monospace mb-3"><i class="fa fa-fw fa-arrows-alt-h text-secondary me-2"></i><?= $network_dhcp_range ?></div>
                    <?php }
                    if ($network_primary_dns) { ?>
                        <div class="text-secondary small text-uppercase">Primary DNS</div>
                        <div class="font-monospace mb-3"><i class="fa fa-fw fa-globe text-secondary me-2"></i><?= $network_primary_dns ?></div>
                    <?php }
                    if ($network_secondary_dns) { ?>
                        <div class="text-secondary small text-uppercase">Secondary DNS</div>
                        <div class="font-monospace mb-3"><i class="fa fa-fw fa-globe text-secondary me-2"></i><?= $network_secondary_dns ?></div>
                    <?php }
                    if ($location_name) { ?>
                        <div class="text-secondary small text-uppercase">Location</div>
                        <div class="mb-3"><i class="fa fa-fw fa-map-marker-alt text-secondary me-2"></i><?= $location_name ?></div>
                    <?php } ?>

                    <div class="text-secondary small text-uppercase">Documented IPs</div>
                    <div class="font-monospace"><i class="fa fa-fw fa-map-pin text-secondary me-2"></i><?= $ip_total ?></div>

                </div>
            </div>

            <?php if ($network_notes) { ?>
            <div class="card card-dark mb-3">
                <div class="card-header">
                    <h5 class="card-title">Notes</h5>
                </div>
                <div class="card-body">
                    <?= nl2br($network_notes) ?>
                </div>
            </div>
            <?php } ?>

        </div>

        <div class="col-md-9">

            <div class="card mb-3">
                <div class="card-header bg-dark py-2">
                    <h3 class="card-title mt-2"><i class="fas fa-fw fa-map-pin me-2"></i>IP Addresses</h3>
                    <div class="card-tools">
                        <div class="btn-group">
                            <button type="button" class="btn btn-primary ajax-modal" data-modal-url="modals/network/network_ip_add.php?network_id=<?= $network_id ?>">
                                <i class="fas fa-plus me-2"></i>New IP
                            </button>
                            <button type="button" class="btn btn-primary dropdown-toggle dropdown-toggle-split" data-bs-toggle="dropdown"></button>
                            <div class="dropdown-menu">
                                <?php if ($num_rows[0] > 0) { ?>
                                <a class="dropdown-item text-dark ajax-modal" href="#"
                                    data-modal-url="modals/network/network_ip_export.php?network_id=<?= $network_id ?>">
                                    <i class="fa fa-fw fa-download me-2"></i>Export
                                </a>
                                <div class="dropdown-divider"></div>
                                <?php } ?>
                                <a class="dropdown-item text-dark ajax-modal" href="#"
                                    data-modal-url="modals/network/network_ip_import.php?network_id=<?= $network_id ?>">
                                    <i class="fa fa-fw fa-upload me-2"></i>Import
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-header py-3">
                    <form autocomplete="off">
                        <?php if ($client_url) { ?>
                        <input type="hidden" name="client_id" value="<?= $client_id ?>">
                        <?php } ?>
                        <input type="hidden" name="network_id" value="<?= $network_id ?>">
                        <div class="row g-2 align-items-center">

                            <div class="col-md-6">
                                <div class="input-group">
                                    <input type="search" class="form-control" name="q" value="<?php if (isset($q)) { echo stripslashes(escapeHtml($q)); } ?>" placeholder="Search IP, hostname or description">
                                        <button class="btn btn-dark"><i class="fa fa-search"></i></button>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="btn-group float-end">
                                    <div class="dropdown" id="bulkActionButton" hidden>
                                        <button class="btn btn-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                            <i class="fas fa-fw fa-layer-group me-2"></i>Bulk Action (<span id="selectedCount">0</span>)
                                        </button>
                                        <div class="dropdown-menu">
                                            <button class="dropdown-item text-danger text-bold confirm-link"
                                                    type="submit" form="bulkActions" name="bulk_delete_network_ips">
                                                <i class="fas fa-fw fa-trash me-2"></i>Delete
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                        </div>
                    </form>
                </div>
                <div class="table-responsive">

                    <form id="bulkActions" action="post.php" method="post">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

                        <table class="table table-striped table-borderless table-hover table-sm mb-0">
                            <thead class="text-dark <?php if ($num_rows[0] == 0) { echo "d-none"; } ?>">
                            <tr>
                                <td class="checkbox-column border-end">
                                    <div class="form-check">
                                        <input class="form-check-input" id="selectAllCheckbox" type="checkbox" onclick="checkAll(this)">
                                    </div>
                                </td>
                                <th>
                                    <a class="text-dark" href="?<?= $url_query_strings_sort ?>&sort=ip_address&order=<?= $disp ?>">
                                        IP Address <?php if ($sort == 'ip_address') { echo $order_icon; } ?>
                                    </a>
                                </th>
                                <th>
                                    <a class="text-dark" href="?<?= $url_query_strings_sort ?>&sort=ip_hostname&order=<?= $disp ?>">
                                        Hostname <?php if ($sort == 'ip_hostname') { echo $order_icon; } ?>
                                    </a>
                                </th>
                                <th>
                                    <a class="text-dark" href="?<?= $url_query_strings_sort ?>&sort=ip_description&order=<?= $disp ?>">
                                        Description <?php if ($sort == 'ip_description') { echo $order_icon; } ?>
                                    </a>
                                </th>
                                <th class="text-center">Action</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php

                            while ($row = mysqli_fetch_assoc($sql_ips)) {
                                $ip_id = intval($row['ip_id']);
                                $ip_address = escapeHtml($row['ip_address']);
                                $ip_hostname = escapeHtml($row['ip_hostname']) ?: '-';
                                $ip_description = escapeHtml($row['ip_description']) ?: '-';

                                ?>
                                <tr>
                                    <td class="checkbox-column bg-light border-end">
                                        <div class="form-check">
                                            <input class="form-check-input bulk-select" type="checkbox" name="network_ip_ids[]" value="<?= $ip_id ?>">
                                        </div>
                                    </td>
                                    <td>
                                        <a class="text-dark font-monospace ajax-modal" href="#" data-modal-url="modals/network/network_ip_edit.php?id=<?= $ip_id ?>">
                                            <?= $ip_address ?>
                                        </a>
                                    </td>
                                    <td><?= $ip_hostname ?></td>
                                    <td><?= $ip_description ?></td>
                                    <td>
                                        <div class="dropdown dropstart text-center">
                                            <button class="btn btn-secondary btn-sm" type="button" data-bs-toggle="dropdown">
                                                <i class="fas fa-ellipsis-h"></i>
                                            </button>
                                            <div class="dropdown-menu">
                                                <a class="dropdown-item ajax-modal" href="#" data-modal-url="modals/network/network_ip_edit.php?id=<?= $ip_id ?>">
                                                    <i class="fas fa-fw fa-edit me-2"></i>Edit
                                                </a>
                                                <div class="dropdown-divider"></div>
                                                <a class="dropdown-item text-danger text-bold confirm-link" href="post.php?delete_network_ip=<?= $ip_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>">
                                                    <i class="fas fa-fw fa-trash me-2"></i>Delete
                                                </a>
                                            </div>
                                        </div>
                                    </td>
                                </tr>

                            <?php } ?>

                            </tbody>
                        </table>

                    </form>
                </div>
                <?php require_once "../includes/filter_footer.php"; ?>
            </div>

        </div>

    </div>

    <script src="../js/bulk_actions.js"></script>

<?php

}

require_once "../includes/footer.php";
