<?php

// Default Column Sortby Filter
$sort = "certificate_name";
$order = "ASC";

// If client_id is in URI then show client Side Bar and client header
if (isset($_GET['client_id'])) {
    require_once "includes/inc_all_client.php";
    $client_query = "AND certificate_client_id = $client_id";
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
        $client_query = 'AND (certificate_client_id = ' . intval($_GET['client']) . ')';
        $client = intval($_GET['client']);
    } else {
        // Default - any
        $client_query = '';
        $client = '';
    }
}

$sql = mysqli_query($mysqli, "SELECT SQL_CALC_FOUND_ROWS * FROM certificates
    LEFT JOIN clients ON client_id = certificate_client_id
    WHERE certificate_archived_at IS NULL
    AND (certificate_name LIKE '%$q%' OR certificate_domain LIKE '%$q%' OR certificate_issued_by LIKE '%$q%' OR client_name LIKE '%$q%')
    $access_permission_query
    $client_query
    ORDER BY $sort $order LIMIT $record_from, $record_to"
);

$num_rows = mysqli_fetch_row(mysqli_query($mysqli, "SELECT FOUND_ROWS()"));

?>

<div class="card card-dark">
    <div class="card-header py-2">
        <h3 class="card-title mt-2"><i class="fas fa-fw fa-lock mr-2"></i>Certificates</h3>
        <div class="card-tools">
            <div class="btn-group">
                <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#addCertificateModal"><i class="fas fa-plus mr-2"></i>New Certificate</button>
                <?php if ($num_rows[0] > 0) { ?>
                    <button type="button" class="btn btn-primary dropdown-toggle dropdown-toggle-split" data-toggle="dropdown"></button>
                    <div class="dropdown-menu">
                        <a class="dropdown-item text-dark" href="#" data-toggle="modal" data-target="#exportCertificateModal">
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
                        <input type="search" class="form-control" name="q" value="<?php if (isset($q)) { echo stripslashes(nullable_htmlentities($q)); } ?>" placeholder="Search Certificates">
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
                            $sql_clients_filter = mysqli_query($mysqli, "SELECT * FROM clients WHERE client_archived_at IS NULL $access_permission_query ORDER BY client_name ASC");
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
                                <button class="dropdown-item text-danger text-bold"
                                        type="submit" form="bulkActions" name="bulk_delete_certificates">
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
                            <a class="text-secondary" href="?<?php echo $url_query_strings_sort; ?>&sort=certificate_name&order=<?php echo $disp; ?>">
                                Name <?php if ($sort == 'certificate_name') { echo $order_icon; } ?>
                            </a>
                        </th>
                        <th>
                            <a class="text-secondary" href="?<?php echo $url_query_strings_sort; ?>&sort=certificate_domain&order=<?php echo $disp; ?>">
                                Domain <?php if ($sort == 'certificate_domain') { echo $order_icon; } ?>
                            </a>
                        </th>
                        <th>
                            <a class="text-secondary" href="?<?php echo $url_query_strings_sort; ?>&sort=certificate_issued_by&order=<?php echo $disp; ?>">
                                Issued By <?php if ($sort == 'certificate_issued_by') { echo $order_icon; } ?>
                            </a>
                        </th>
                        <th>
                            <a class="text-secondary" href="?<?php echo $url_query_strings_sort; ?>&sort=certificate_expire&order=<?php echo $disp; ?>">
                                Expire <?php if ($sort == 'certificate_expire') { echo $order_icon; } ?>
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
                        $certificate_id = intval($row['certificate_id']);
                        $certificate_name = nullable_htmlentities($row['certificate_name']);
                        $certificate_description = nullable_htmlentities($row['certificate_description']);
                        $certificate_domain = nullable_htmlentities($row['certificate_domain']);
                        $certificate_issued_by = nullable_htmlentities($row['certificate_issued_by']);
                        $certificate_expire = nullable_htmlentities($row['certificate_expire']);
                        $certificate_created_at = nullable_htmlentities($row['certificate_created_at']);

                        $certificate_expire_ago = timeAgo($certificate_expire);
                        // Convert the expiry date to a timestamp
                        $certificate_expire_timestamp = strtotime($row['certificate_expire']);
                        $current_timestamp = time(); // Get current timestamp

                        // Calculate the difference in days
                        $days_until_expiry = ($certificate_expire_timestamp - $current_timestamp) / (60 * 60 * 24);

                        // Determine the class based on the number of days until expiry
                        if ($days_until_expiry <= 0) {
                            $tr_class = "table-secondary";
                        } elseif ($days_until_expiry <= 14) {
                            $tr_class = "table-danger";
                        } elseif ($days_until_expiry <= 90) {
                            $tr_class = "table-warning";
                        } else {
                            $tr_class = '';
                        }

                        ?>
                        <tr class="<?php echo $tr_class; ?>">
                            <td class="pr-0">
                                <div class="form-check">
                                    <input class="form-check-input bulk-select" type="checkbox" name="certificate_ids[]" value="<?php echo $certificate_id ?>">
                                    <input type="hidden" name="client_id" value="<?php echo $client_id; ?>">
                                </div>
                            </td>
                            <td>
                                <a class="text-dark" href="#"
                                    data-toggle="ajax-modal"
                                    data-ajax-url="ajax/ajax_certificate_edit.php"
                                    data-ajax-id="<?php echo $certificate_id; ?>"
                                    >
                                    <div class="media">
                                        <i class="fa fa-fw fa-2x fa-lock mr-3"></i>
                                        <div class="media-body">
                                            <div><?php echo $certificate_name; ?></div>
                                            <div><small class="text-secondary"><?php echo $certificate_description; ?></small></div>
                                        </div>
                                    </div>
                                </a>
                            </td>
                            <td><?php echo $certificate_domain; ?></td>

                            <td><?php echo $certificate_issued_by; ?></td>

                            <td>
                                <div><?php echo $certificate_expire; ?></div>
                                <div><small><?php echo $certificate_expire_ago; ?></small></div>
                            </td>
                            <?php if (!$client_url) { ?>
                            <td><a href="certificates.php?client_id=<?php echo $client_id; ?>"><?php echo $client_name; ?></a></td>
                            <?php } ?>
                            <td>
                                <div class="dropdown dropleft text-center">
                                    <button class="btn btn-secondary btn-sm" type="button" data-toggle="dropdown">
                                        <i class="fas fa-ellipsis-h"></i>
                                    </button>
                                    <div class="dropdown-menu">
                                        <a class="dropdown-item" href="#"
                                            data-toggle="ajax-modal"
                                            data-ajax-url="ajax/ajax_certificate_edit.php"
                                            data-ajax-id="<?php echo $certificate_id; ?>"
                                            >
                                            <i class="fas fa-fw fa-edit mr-2"></i>Edit
                                        </a>
                                        <?php if ($session_user_role == 3) { ?>
                                            <div class="dropdown-divider"></div>
                                            <a class="dropdown-item text-danger confirm-link" href="post.php?archive_certificate=<?php echo $certificate_id; ?>">
                                                <i class="fas fa-fw fa-archive mr-2"></i>Archive
                                            </a>
                                            <div class="dropdown-divider"></div>
                                            <a class="dropdown-item text-danger text-bold confirm-link" href="post.php?delete_certificate=<?php echo $certificate_id; ?>">
                                                <i class="fas fa-fw fa-trash mr-2"></i>Delete
                                            </a>
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

            </form>
        </div>
        <?php require_once "includes/filter_footer.php";
 ?>
    </div>
</div>

<?php
require_once "modals/certificate_add_modal.php";
require_once "modals/certificate_export_modal.php";
?>

<script src="js/bulk_actions.js"></script>
<script src="js/certificate_fetch_ssl.js"></script>

<?php require_once "includes/footer.php";