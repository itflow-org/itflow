<?php

// Default Column Sortby Filter
$sort = "asset_name";
$order = "ASC";

require_once "inc_all_client.php";


//Get Asset Counts
//All Asset Count
$row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT(*) AS count FROM assets WHERE asset_archived_at IS NULL AND asset_client_id = $client_id"));
$all_count = intval($row['count']);
//Workstation Count
$row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT(*) AS count FROM assets WHERE (asset_type = 'laptop' OR asset_type = 'desktop') 
  AND asset_archived_at IS NULL AND asset_client_id = $client_id"));
$workstation_count = intval($row['count']);

//Server Count
$row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT(*) AS count FROM assets WHERE (asset_type = 'server') 
  AND asset_archived_at IS NULL AND asset_client_id = $client_id"));
$server_count = intval($row['count']);

//Virtual Server Count
$row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT(*) AS count FROM assets WHERE (asset_type = 'virtual machine') 
  AND asset_archived_at IS NULL AND asset_client_id = $client_id"));
$virtual_count = intval($row['count']);

//Network Device Count
$row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT(*) AS count FROM assets WHERE (asset_type = 'Firewall/Router' OR asset_type = 'switch' OR asset_type = 'access point')
  AND asset_archived_at IS NULL AND asset_client_id = $client_id"));
$network_count = intval($row['count']);

//Other Count
$row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT(*) AS count FROM assets WHERE (asset_type NOT LIKE 'laptop' AND asset_type NOT LIKE 'desktop' AND asset_type NOT LIKE 'server' AND asset_type NOT LIKE 'virtual machine' AND asset_type NOT LIKE 'firewall/router' AND asset_type NOT LIKE 'switch' AND asset_type NOT LIKE 'access point')
  AND asset_archived_at IS NULL AND asset_client_id = $client_id"));
$other_count = intval($row['count']);

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

//Rebuild URL
//Rebuild URL
$url_query_strings_sort = http_build_query($get_copy);

$sql = mysqli_query(
    $mysqli,
    "SELECT SQL_CALC_FOUND_ROWS * FROM assets 
    LEFT JOIN contacts ON asset_contact_id = contact_id 
    LEFT JOIN locations ON asset_location_id = location_id 
    LEFT JOIN logins ON login_asset_id = asset_id
    WHERE asset_client_id = $client_id
    AND asset_archived_at IS NULL
    AND (asset_name LIKE '%$q%' OR asset_description LIKE '%$q%' OR asset_type LIKE '%$q%' OR asset_ip LIKE '%$q%' OR asset_make LIKE '%$q%' OR asset_model LIKE '%$q%' OR asset_serial LIKE '%$q%' OR asset_os LIKE '%$q%' OR contact_name LIKE '%$q%' OR location_name LIKE '%$q%')
    AND ($type_query)
    ORDER BY $sort $order LIMIT $record_from, $record_to"
);

$num_rows = mysqli_fetch_row(mysqli_query($mysqli, "SELECT FOUND_ROWS()"));

?>

    <div class="card card-dark">
        <div class="card-header py-2">
            <h3 class="card-title mt-2"><i class="fa fa-fw fa-desktop mr-2"></i>Assets</h3>
            <div class="card-tools">
                <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#addAssetModal"><i class="fas fa-plus mr-2"></i>New <?php if (!empty($_GET['type'])) { echo ucwords(strip_tags(nullable_htmlentities($_GET['type']))); } else { echo "Asset"; } ?></button>
            </div>
        </div>
        <div class="card-body">
            <form autocomplete="off">
                <input type="hidden" name="client_id" value="<?php echo $client_id; ?>">
                <input type="hidden" name="type" value="<?php echo stripslashes(nullable_htmlentities($_GET['type'])); ?>">
                <div class="row">

                    <div class="col-md-4">
                        <div class="input-group mb-3 mb-md-0">
                            <input type="search" class="form-control" name="q" value="<?php if (isset($q)) { echo stripslashes(nullable_htmlentities($q)); } ?>" placeholder="Search <?php if (!empty($_GET['type'])) { echo ucwords(stripslashes(nullable_htmlentities($_GET['type']))); } else { echo "Asset"; } ?>s">
                            <div class="input-group-append">
                                <button class="btn btn-dark"><i class="fa fa-search"></i></button>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="btn-group btn-group-lg">
                            <a href="?<?php echo $url_query_strings_sort; ?>&type=" class="btn <?php if ($_GET['type'] == 'all' || empty($_GET['type'])) { echo 'btn-primary'; } else { echo 'btn-default'; } ?>">All Assets <span class="right badge badge-light"><?php echo $all_count; ?></span></a>
                            <?php
                            if ($workstation_count > 0) { ?>
                                <a href="?<?php echo $url_query_strings_sort; ?>&type=workstation" class="btn <?php if ($_GET['type'] == 'workstation') { echo 'btn-primary'; } else { echo 'btn-default'; } ?>"><i class="fa fa-fw fa-desktop"></i> Workstations <span class="right badge badge-light"><?php echo $workstation_count; ?></span></a>
                                <?php
                            }
                            if ($server_count > 0) { ?>
                                <a href="?<?php echo $url_query_strings_sort; ?>&type=server" class="btn <?php if ($_GET['type'] == 'server') { echo 'btn-primary'; } else { echo 'btn-default'; } ?>"><i class="fa fa-fw fa-server"></i> Servers <span class="right badge badge-light"><?php echo $server_count; ?></span></a>
                                <?php
                            }
                            if ($virtual_count > 0) { ?>
                                <a href="?<?php echo $url_query_strings_sort; ?>&type=virtual" class="btn <?php if ($_GET['type'] == 'virtual') { echo 'btn-primary'; } else { echo 'btn-default'; } ?>"><i class="fa fa-fw fa-cloud"></i> Virtual <span class="right badge badge-light"><?php echo $virtual_count; ?></span></a>
                                <?php
                            }
                            if ($network_count > 0) { ?>
                                <a href="?<?php echo $url_query_strings_sort; ?>&type=network" class="btn <?php if ($_GET['type'] == 'network') { echo 'btn-primary'; } else { echo 'btn-default'; } ?>"><i class="fa fa-fw fa-network-wired"></i> Network <span class="right badge badge-light"><?php echo $network_count; ?></span></a>
                                <?php
                            }
                            if ($network_count > 0) { ?>
                                <a href="?<?php echo $url_query_strings_sort; ?>&type=other" class="btn <?php if ($_GET['type'] == 'other') { echo 'btn-primary'; } else { echo 'btn-default'; } ?>"><i class="fa fa-fw fa-tag"></i> Other <span class="right badge badge-light"><?php echo $other_count; ?></span></a>
                                <?php
                            } ?>
                        </div>
                    </div>

                    <div class="col-md-2">
                        <div class="float-right">
                            <button type="button" class="btn btn-default" data-toggle="modal" data-target="#exportAssetModal"><i class="fa fa-fw fa-download mr-2"></i>Export</button>
                            <button type="button" class="btn btn-default" data-toggle="modal" data-target="#importAssetModal"><i class="fa fa-fw fa-upload mr-2"></i>Import</button>
                        </div>
                    </div>

                </div>
            </form>
            <hr>
            <div class="table-responsive">
                <table class="table border table-hover">
                    <thead class="thead-light <?php if ($num_rows[0] == 0) { echo "d-none"; } ?>">
                    <tr>
                        <th><a class="text-secondary" href="?<?php echo $url_query_strings_sort; ?>&sort=asset_name&order=<?php echo $disp; ?>">Name</a></th>
                        <th><a class="text-secondary" href="?<?php echo $url_query_strings_sort; ?>&sort=asset_description&order=<?php echo $disp; ?>">Description</a></th>
                        <?php if ($_GET['type'] !== 'virtual' && $_GET['type'] !== 'servers') { ?>
                            <th><a class="text-secondary" href="?<?php echo $url_query_strings_sort; ?>&sort=asset_type&order=<?php echo $disp; ?>">Type</a></th>
                        <?php }
                        if ($_GET['type'] !== 'virtual') { ?>
                            <th><a class="text-secondary" href="?<?php echo $url_query_strings_sort; ?>&sort=asset_make&order=<?php echo $disp; ?>">Make/Model</a></th>
                        <?php }
                        if ($_GET['type'] !== 'virtual') { ?>
                            <th><a class="text-secondary" href="?<?php echo $url_query_strings_sort; ?>&sort=asset_serial&order=<?php echo $disp; ?>">Serial Number</a></th>
                        <?php }
                        if ($_GET['type'] !== 'network' && $_GET['type'] !== 'other') { ?>
                            <th><a class="text-secondary" href="?<?php echo $url_query_strings_sort; ?>&sort=asset_os&order=<?php echo $disp; ?>">Operating System</a></th>
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
                        $asset_ip = nullable_htmlentities($row['asset_ip']);
                        if (empty($asset_ip)) {
                            $asset_ip_display = "-";
                        } else {
                            $asset_ip_display = "$asset_ip<button class='btn btn-sm' data-clipboard-text=" . $asset_ip . "><i class='far fa-copy text-secondary'></i></button>";
                        }
                        $asset_mac = nullable_htmlentities($row['asset_mac']);
                        $asset_uri = nullable_htmlentities($row['asset_uri']);
                        $asset_status = nullable_htmlentities($row['asset_status']);
                        $asset_purchase_date = nullable_htmlentities($row['asset_purchase_date']);
                        $asset_warranty_expire = nullable_htmlentities($row['asset_warranty_expire']);
                        $asset_install_date = nullable_htmlentities($row['asset_install_date']);
                        if (empty($asset_install_date)) {
                            $asset_install_date_display = "-";
                        } else {
                            $asset_install_date_display = $asset_install_date;
                        }
                        $asset_notes = nullable_htmlentities($row['asset_notes']);
                        $asset_created_at = nullable_htmlentities($row['asset_created_at']);
                        $asset_vendor_id = intval($row['asset_vendor_id']);
                        $asset_location_id = intval($row['asset_location_id']);
                        $asset_contact_id = intval($row['asset_contact_id']);
                        $asset_network_id = intval($row['asset_network_id']);

                        $device_icon = getAssetIcon($asset_type);

                        $contact_name = nullable_htmlentities($row['contact_name']);
                        if (empty($contact_name)) {
                            $contact_name = "-";
                        }
                        $contact_archived_at = nullable_htmlentities($row['contact_archived_at']);
                        if (empty($contact_archived_at)) {
                            $contact_archived_display = "";
                        } else {
                            $contact_archived_display = "Archived - ";
                        }

                        $location_name = nullable_htmlentities($row['location_name']);
                        if (empty($location_name)) {
                            $location_name = "-";
                        }
                        $location_archived_at = nullable_htmlentities($row['location_archived_at']);
                        if (empty($location_archived_at)) {
                            $location_archived_display = "";
                        } else {
                            $location_archived_display = "Archived - ";
                        }

                        $login_id = intval($row['login_id']);
                        $login_username = nullable_htmlentities(decryptLoginEntry($row['login_username']));
                        $login_password = nullable_htmlentities(decryptLoginEntry($row['login_password']));

                        // Related tickets
                        $sql_tickets = mysqli_query($mysqli, "SELECT * FROM tickets WHERE ticket_asset_id = $asset_id ORDER BY ticket_number DESC");
                        $ticket_count = mysqli_num_rows($sql_tickets);

                        // Related Documents
                        $sql_related_documents = mysqli_query($mysqli, "SELECT * FROM documents, asset_documents WHERE documents.document_id = asset_documents.document_id AND document_archived_at IS NULL AND asset_documents.asset_id = $asset_id ORDER BY documents.document_name DESC");
                        $document_count = mysqli_num_rows($sql_related_documents);


                        // Related File
                        $sql_related_files = mysqli_query($mysqli, "SELECT * FROM files, asset_files WHERE files.file_id = asset_files.file_id AND asset_files.asset_id = $asset_id ORDER BY files.file_name DESC");
                        $file_count = mysqli_num_rows($sql_related_files);

                        ?>
                        <tr>
                            <th>
                                <i class="fa fa-fw text-secondary fa-<?php echo $device_icon; ?> mr-2"></i>
                                <a class="text-secondary" href="client_asset_details.php?client_id=<?php echo $client_id; ?>&asset_id=<?php echo $asset_id; ?>"><?php echo $asset_name; ?></a>
                                <?php if(!empty($asset_uri)){ ?>
                                    <a href="<?php echo $asset_uri; ?>" target="_blank"><i class="fas fa-fw fa-external-link-alt ml-2"></i></a>
                                <?php } ?>
                                <?php
                                if ($login_id > 0) {
                                    ?>
                                    <button type="button" class="btn btn-link btn-sm" data-toggle="modal" data-target="#viewPasswordModal<?php echo $login_id; ?>"><i class="fas fa-key text-dark"></i></button>

                                    <div class="modal" id="viewPasswordModal<?php echo $login_id; ?>" tabindex="-1">
                                        <div class="modal-dialog modal-sm">
                                            <div class="modal-content bg-dark">
                                                <div class="modal-header">
                                                    <h5 class="modal-title"><i class="fa fa-fw fa-key mr-2"></i><?php echo $asset_name; ?></h5>
                                                    <button type="button" class="close text-white" data-dismiss="modal">
                                                        <span>&times;</span>
                                                    </button>
                                                </div>
                                                <div class="modal-body bg-white">
                                                    <div class="form-group">
                                                        <div class="input-group">
                                                            <div class="input-group-prepend">
                                                                <span class="input-group-text"><i class="fa fa-user"></i></span>
                                                            </div>
                                                            <input type="text" class="form-control" value="<?php echo $login_username; ?>" readonly>
                                                        </div>
                                                    </div>
                                                    <div class="form-group">
                                                        <div class="input-group">
                                                            <div class="input-group-prepend">
                                                                <span class="input-group-text"><i class="fa fa-lock"></i></span>
                                                            </div>
                                                            <input type="password" class="form-control" data-toggle="password" value="<?php echo $login_password; ?>" readonly autocomplete="off">
                                                            <div class="input-group-append">
                                                                <span class="input-group-text"><i class="fa fa-fw fa-eye"></i></span>
                                                            </div>
                                                            <div class="input-group-append">
                                                                <button class="btn btn-default clipboardjs" type="button" data-clipboard-text="<?php echo $login_password; ?>"><i class="fa fa-fw fa-copy"></i></button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                <?php } ?>

                            </th>
                            <td><?php echo $asset_description_display; ?></td>
                            <?php if ($_GET['type'] !== 'virtual' && $_GET['type'] !== 'servers') { ?>
                                <td><?php echo $asset_type; ?></td>
                            <?php } ?>
                            <?php if ($_GET['type'] !== 'virtual') { ?>
                                <td><?php echo "$asset_make $asset_model"; ?></td>
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
                                <td><?php echo "$contact_archived_display$contact_name"; ?></td>
                            <?php } ?>
                            <td><?php echo "$location_archived_display$location_name"; ?></td>
                            <td><?php echo $asset_status; ?></td>
                            <td>
                                <div class="dropdown dropleft text-center">
                                    <button class="btn btn-secondary btn-sm" type="button" data-toggle="dropdown"><i class="fas fa-ellipsis-h"></i></button>
                                    <div class="dropdown-menu">
                                        <!-- Interfaces is still in Development also we may not complete this and may recommend to document in notes or seperate document linking to the asset
                                        <a class="dropdown-item" href="#" data-toggle="modal" data-target="#addAssetInterfaceModal<?php echo $asset_id; ?>">
                                            <i class="fas fa-fw fa-ethernet mr-2"></i>Interfaces
                                        </a>
                                        <div class="dropdown-divider"></div>
                                        -->
                                        <a class="dropdown-item" href="#" data-toggle="modal" data-target="#editAssetModal<?php echo $asset_id; ?>">
                                            <i class="fas fa-fw fa-edit mr-2"></i>Edit
                                        </a>
                                        <a class="dropdown-item" href="#" data-toggle="modal" data-target="#copyAssetModal<?php echo $asset_id; ?>">
                                            <i class="fas fa-fw fa-copy mr-2"></i>Copy
                                        </a>
                                        <?php if ($document_count > 0) { ?>
                                            <a class="dropdown-item" href="#" data-toggle="modal" data-target="#assetDocumentsModal<?php echo $asset_id; ?>">
                                                <i class="fas fa-fw fa-document mr-2"></i>Documents (<?php echo $document_count; ?>)
                                            </a>
                                        <?php } ?>
                                        <?php if ($ticket_count > 0) { ?>
                                            <a class="dropdown-item" href="#" data-toggle="modal" data-target="#assetTicketsModal<?php echo $asset_id; ?>">
                                                <i class="fas fa-fw fa-life-ring mr-2"></i>Tickets (<?php echo $ticket_count; ?>)
                                            </a>
                                        <?php } ?>
                                        <?php if ($session_user_role == 3) { ?>
                                            <div class="dropdown-divider"></div>
                                            <a class="dropdown-item text-danger confirm-link" href="post.php?archive_asset=<?php echo $asset_id; ?>">
                                                <i class="fas fa-fw fa-archive mr-2"></i>Archive
                                            </a>
                                            <div class="dropdown-divider"></div>
                                            <a class="dropdown-item text-danger text-bold confirm-link" href="post.php?delete_asset=<?php echo $asset_id; ?>">
                                                <i class="fas fa-fw fa-trash mr-2"></i>Delete</a>
                                        <?php } ?>
                                    </div>
                                </div>
                            </td>
                        </tr>

                        <?php

                        require "client_asset_edit_modal.php";

                        require "client_asset_copy_modal.php";

                        require "client_asset_tickets_modal.php";

                        //require "client_asset_interface_add_modal.php";

                    }

                    ?>

                    </tbody>
                </table>
            </div>
            <?php require_once "pagination.php";
 ?>
        </div>
    </div>

<?php
require_once "client_asset_add_modal.php";

require_once "client_asset_import_modal.php";

require_once "client_asset_export_modal.php";

require_once "footer.php";

