<?php

// Default Column Sortby Filter
$sort = "software_name";
$order = "ASC";

// If client_id is in URI then show client Side Bar and client header
if (isset($_GET['client_id'])) {
    require_once "includes/inc_all_client.php";
    $client_query = "AND software_client_id = $client_id";
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
        $client_query = 'AND (software_client_id = ' . intval($_GET['client']) . ')';
        $client = intval($_GET['client']);
    } else {
        // Default - any
        $client_query = '';
        $client = '';
    }
}

$sql = mysqli_query(
    $mysqli,
    "SELECT SQL_CALC_FOUND_ROWS * FROM software
    LEFT JOIN clients ON client_id = software_client_id
    LEFT JOIN vendors ON vendor_id = software_vendor_id
    WHERE software_template = 0
    AND software_$archive_query
    AND (software_name LIKE '%$q%' OR software_type LIKE '%$q%' OR software_key LIKE '%$q%' OR client_name LIKE '%$q%')
    $access_permission_query
    $client_query
    ORDER BY $sort $order LIMIT $record_from, $record_to");

$num_rows = mysqli_fetch_row(mysqli_query($mysqli, "SELECT FOUND_ROWS()"));

?>

    <div class="card card-dark">
        <div class="card-header py-2">
            <h3 class="card-title mt-2"><i class="fas fa-fw fa-cube mr-2"></i>Software & Licenses</h3>
            <div class="card-tools">
                <div class="btn-group">
                    <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#addSoftwareModal">
                        <i class="fas fa-plus mr-2"></i>New License
                    </button>
                    <button type="button" class="btn btn-primary dropdown-toggle dropdown-toggle-split" data-toggle="dropdown"></button>
                    <div class="dropdown-menu">
                        <a class="dropdown-item text-dark" href="#" data-toggle="modal" data-target="#addSoftwareFromTemplateModal">
                            <i class="fas fa-fw fa-puzzle-piece mr-2"></i>Create from Template
                        </a>
                        <?php if ($num_rows[0] > 0) { ?>
                            <div class="dropdown-divider"></div>
                            <a class="dropdown-item text-dark" href="#" data-toggle="modal" data-target="#exportSoftwareModal">
                                <i class="fa fa-fw fa-download mr-2"></i>Export
                            </a>
                        <?php } ?>
                    </div>
                </div>
            </div>
        </div>
        <div class="card-body">
            <form autocomplete="off">
                <?php if($client_url) { ?>
                <input type="hidden" name="client_id" value="<?php echo $client_id; ?>">
                <?php } ?>
                <input type="hidden" name="archived" value="<?php echo $archived; ?>">
                <div class="row">

                    <div class="col-md-4">
                        <div class="input-group mb-3 mb-md-0">
                            <input type="search" class="form-control" name="q" value="<?php if (isset($q)) { echo stripslashes(nullable_htmlentities($q)); } ?>" placeholder="Search Licenses">
                            <div class="input-group-append">
                                <button class="btn btn-dark"><i class="fa fa-search"></i></button>
                            </div>
                        </div>
                    </div>

                    <?php if ($client_url) { ?>
                    <div class="col-md-2"></div>
                    <?php } else { ?>
                    <div class="col-md-2">
                        <div class="input-group">
                            <select class="form-control select2" name="client" onchange="this.form.submit()">
                                <option value="" <?php if ($client == "") { echo "selected"; } ?>>- All Clients -</option>

                                <?php
                                    $sql_clients_filter = mysqli_query($mysqli, "
                                    SELECT DISTINCT client_id, client_name 
                                    FROM clients
                                    JOIN software ON software_client_id = client_id
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
                        <div class="float-right">
                            <a href="?<?php echo $client_url; ?>archived=<?php if($archived == 1){ echo 0; } else { echo 1; } ?>"
                                class="btn btn-<?php if($archived == 1){ echo "primary"; } else { echo "default"; } ?>">
                                <i class="fa fa-fw fa-archive mr-2"></i>Archived
                            </a>
                        </div>
                    </div>

                </div>
            </form>
            <hr>
            <div class="table-responsive-sm">
                <table class="table table-borderless table-hover">
                    <thead class="text-dark <?php if ($num_rows[0] == 0) { echo "d-none"; } ?>">
                    <tr>
                        <th>
                            <a class="text-secondary" href="?<?php echo $url_query_strings_sort; ?>&sort=software_name&order=<?php echo $disp; ?>">
                                Software <?php if ($sort == 'software_name') { echo $order_icon; } ?>
                            </a>
                        </th>
                        <th>
                            <a class="text-secondary" href="?<?php echo $url_query_strings_sort; ?>&sort=software_type&order=<?php echo $disp; ?>">
                                Type <?php if ($sort == 'software_type') { echo $order_icon; } ?>
                            </a>
                        </th>
                        <th>
                            <a class="text-secondary" href="?<?php echo $url_query_strings_sort; ?>&sort=software_license_type&order=<?php echo $disp; ?>">
                                License Type <?php if ($sort == 'software_license_type') { echo $order_icon; } ?>
                            </a>
                        </th>
                        <th>
                            <a class="text-secondary" href="?<?php echo $url_query_strings_sort; ?>&sort=software_seats&order=<?php echo $disp; ?>">
                                Seats <?php if ($sort == 'software_seats') { echo $order_icon; } ?>
                            </a>
                        </th>
                        <th>
                            <a class="text-secondary" href="?<?php echo $url_query_strings_sort; ?>&sort=software_expire&order=<?php echo $disp; ?>">
                                Expire <?php if ($sort == 'software_expire') { echo $order_icon; } ?>
                            </a>
                        </th>
                        <th>
                            <a class="text-secondary" href="?<?php echo $url_query_strings_sort; ?>&sort=vendor_name&order=<?php echo $disp; ?>">
                                Vendor <?php if ($sort == 'vendor_name') { echo $order_icon; } ?>
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
                        $software_id = intval($row['software_id']);
                        $software_name = nullable_htmlentities($row['software_name']);
                        $software_description = nullable_htmlentities($row['software_description']);
                        $software_version = nullable_htmlentities($row['software_version']);
                        $software_type = nullable_htmlentities($row['software_type']);
                        $software_license_type = getFallBack(nullable_htmlentities($row['software_license_type']));
                        $software_seats = nullable_htmlentities($row['software_seats']);
                        $software_expire = nullable_htmlentities($row['software_expire']);
                        $vendor_name = nullable_htmlentities($row['vendor_name']);
                        $vendor_id = intval($row['vendor_id']);
                        if ($vendor_name) {
                            $vendor_display = "<a href='#' data-toggle='ajax-modal' data-ajax-url='ajax/ajax_vendor_details.php' data-ajax-id='$vendor_id'>$vendor_name</a>";
                        } else {
                            $vendor_display = "<span class='text-muted'>N/A</span>";
                        }
                        if ($software_expire) {
                            $software_expire_ago = timeAgo($software_expire);
                            $software_expire_display = "<div>$software_expire</div><div><small>$software_expire_ago</small></div>";
                            
                            // Convert the expiry date to a timestamp
                            $software_expire_timestamp = strtotime($row['software_expire']);
                            $current_timestamp = time(); // Get current timestamp

                            // Calculate the difference in days
                            $days_until_expiry = ($software_expire_timestamp - $current_timestamp) / (60 * 60 * 24);

                            // Determine the class based on the number of days until expiry
                            if ($days_until_expiry <= 0) {
                                $tr_class = "table-secondary";
                            } elseif ($days_until_expiry <= 7) {
                                $tr_class = "table-danger";
                            } elseif ($days_until_expiry <= 45) {
                                $tr_class = "table-warning";    
                            } else {
                                $tr_class = '';
                            }
                            
                        } else {
                            $software_expire_display = "<span class='text-muted'>N/A</span>";
                            $tr_class = '';
                        }
     
                        $software_created_at = nullable_htmlentities($row['software_created_at']);

                        $seat_count = 0;

                        // Asset Licenses
                        $asset_licenses_sql = mysqli_query($mysqli, "SELECT asset_id FROM software_assets WHERE software_id = $software_id");
                        $asset_licenses_array = array();
                        while ($row = mysqli_fetch_array($asset_licenses_sql)) {
                            $asset_licenses_array[] = intval($row['asset_id']);
                            $seat_count = $seat_count + 1;
                        }
                        $asset_licenses = implode(',', $asset_licenses_array);

                        // Contact Licenses
                        $contact_licenses_sql = mysqli_query($mysqli, "SELECT contact_id FROM software_contacts WHERE software_id = $software_id");
                        $contact_licenses_array = array();
                        while ($row = mysqli_fetch_array($contact_licenses_sql)) {
                            $contact_licenses_array[] = intval($row['contact_id']);
                            $seat_count = $seat_count + 1;
                        }
                        $contact_licenses = implode(',', $contact_licenses_array);



                        ?>
                        <tr class="<?php echo $tr_class; ?>">
                            <td>
                                <a class="text-dark" href="#"
                                    data-toggle="ajax-modal"
                                    data-ajax-url="ajax/ajax_software_edit.php"
                                    data-ajax-id="<?php echo $software_id; ?>"
                                    >
                                    <div class="media">
                                        <i class="fa fa-fw fa-2x fa-cube mr-3"></i>
                                        <div class="media-body">
                                            <div><?php echo "$software_name <span>$software_version</span>"; ?></div>
                                            <div><small class="text-secondary"><?php echo $software_description; ?></small></div>
                                        </div>
                                    </div>
                                </a>
                            </td>
                            <td><?php echo $software_type; ?></td>
                            <td><?php echo $software_license_type; ?></td>
                            <td><?php echo "$seat_count / $software_seats"; ?></td>
                            <td><?php echo $software_expire_display; ?></td>
                            <td><?php echo $vendor_display; ?></td>
                            <?php if (!$client_url) { ?>
                            <td><a href="software.php?client_id=<?php echo $client_id; ?>"><?php echo $client_name; ?></a></td>
                            <?php } ?>
                            <td>
                                <div class="dropdown dropleft text-center">
                                    <button class="btn btn-secondary btn-sm" data-toggle="dropdown">
                                        <i class="fas fa-ellipsis-h"></i>
                                    </button>
                                    <div class="dropdown-menu">
                                        <a class="dropdown-item" href="#"
                                            data-toggle="ajax-modal"
                                            data-ajax-url="ajax/ajax_software_edit.php"
                                            data-ajax-id="<?php echo $software_id; ?>"
                                            >
                                            <i class="fas fa-fw fa-edit mr-2"></i>Edit
                                        </a>
                                        <div class="dropdown-divider"></div>
                                        <a class="dropdown-item text-danger confirm-link" href="post.php?archive_software=<?php echo $software_id; ?>">
                                            <i class="fas fa-fw fa-archive mr-2"></i>Archive and<br><small>Remove Licenses</small></a>
                                        <?php if ($session_user_role == 3) { ?>
                                            <?php if ($config_destructive_deletes_enable) { ?>
                                            <div class="dropdown-divider"></div>
                                            <a class="dropdown-item text-danger text-bold confirm-link" href="post.php?delete_software=<?php echo $software_id; ?>">
                                                <i class="fas fa-fw fa-trash mr-2"></i>Delete and<br><small>Remove Licenses</small></a>
                                            <?php } ?>
                                        <?php } ?>
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
            <?php require_once "includes/filter_footer.php";
 ?>
        </div>
    </div>

<?php

require_once "modals/software_add_modal.php";
require_once "modals/software_add_from_template_modal.php";
require_once "modals/software_export_modal.php";
require_once "includes/footer.php";
