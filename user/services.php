<?php

// Default Column Sortby Filter
$sort = "service_name";
$order = "ASC";

// If client_id is in URI then show client Side Bar and client header
if (isset($_GET['client_id'])) {
    require_once "includes/inc_all_client.php";
    $client_query = "AND service_client_id = $client_id";
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
        $client_query = 'AND (service_client_id = ' . intval($_GET['client']) . ')';
        $client = intval($_GET['client']);
    } else {
        // Default - any
        $client_query = '';
        $client = '';
    }
}

// Overview SQL query
$sql = mysqli_query(
    $mysqli,
    "SELECT SQL_CALC_FOUND_ROWS * FROM services
    LEFT JOIN clients ON client_id = service_client_id
    WHERE (service_name LIKE '%$q%' OR service_description LIKE '%$q%' OR service_category LIKE '%$q%' OR client_name LIKE '%$q%')
    AND client_archived_at IS NULL
    $access_permission_query
    $client_query
    ORDER BY $sort $order LIMIT $record_from, $record_to"
);

$num_rows = mysqli_fetch_row(mysqli_query($mysqli, "SELECT FOUND_ROWS()"));

?>
    <div class="card card-dark">
        <div class="card-header py-2">
            <h3 class="card-title mt-2"><i class="fa fa-fw fa-stream mr-2"></i>Services</h3>
            <div class="card-tools">
                <?php if (lookupUserPermission("module_support") >= 2) { ?>
                    <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#addServiceModal"><i class="fas fa-plus mr-2"></i>New Service</button>
                <?php } ?>
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
                            <input type="search" class="form-control" name="q" value="<?php if (isset($q)) { echo stripslashes(nullable_htmlentities($q)); } ?>" placeholder="Search Services">
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
                                    JOIN services ON service_client_id = client_id
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
                        </div>
                    </div>
                </div>
            </form>
            <hr>

            <div class="table-responsive-sm">
                <table class="table table-striped table-borderless table-hover">
                    <thead class="<?php if ($num_rows[0] == 0) { echo "d-none"; } ?>">
                    <tr>
                        <th>
                            <a class="text-dark" href="?<?php echo $url_query_strings_sort; ?>&sort=service_name&order=<?php echo $disp; ?>">
                                Name <?php if ($sort == 'service_name') { echo $order_icon; } ?>
                            </a>
                        </th>
                        <th>
                            <a class="text-dark" href="?<?php echo $url_query_strings_sort; ?>&sort=service_category&order=<?php echo $disp; ?>">
                                Category <?php if ($sort == 'service_category') { echo $order_icon; } ?>
                            </a>
                        </th>
                        <th>
                            <a class="text-dark" href="?<?php echo $url_query_strings_sort; ?>&sort=service_importance&order=<?php echo $disp; ?>">
                                Importance <?php if ($sort == 'service_importance') { echo $order_icon; } ?>
                            </a>
                        </th>
                        <th>
                            <a class="text-dark" href="?<?php echo $url_query_strings_sort; ?>&sort=service_updated_at&order=<?php echo $disp; ?>">
                                Updated <?php if ($sort == 'service_updated_at') { echo $order_icon; } ?>
                            </a>
                        </th>
                        <?php if (!$client_url) { ?>
                        <th>
                            <a class="text-dark" href="?<?php echo $url_query_strings_sort; ?>&sort=client_name&order=<?php echo $disp; ?>">
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
                        $service_id = intval($row['service_id']);
                        $service_name = nullable_htmlentities($row['service_name']);
                        $service_description = nullable_htmlentities($row['service_description']);
                        $service_category = nullable_htmlentities($row['service_category']);
                        $service_importance = nullable_htmlentities($row['service_importance']);
                        $service_backup = nullable_htmlentities($row['service_backup']);
                        $service_notes = nullable_htmlentities($row['service_notes']);
                        $service_created_at = nullable_htmlentities($row['service_created_at']);
                        $service_updated_at = nullable_htmlentities($row['service_updated_at']);
                        $service_review_due = nullable_htmlentities($row['service_review_due']);

                        ?>

                        <tr>
                            <!-- Name/Category/Updated/Importance from DB -->
                            <td>
                                <a class="text-dark ajax-modal" href="#"
                                    data-modal-size="xl"
                                    data-modal-url="modals/service/service_details.php?id=<?= $service_id ?>">
                                    <div class="media">
                                        <i class="fa fa-fw fa-2x fa-stream mr-3"></i>
                                        <div class="media-body">
                                            <div><?php echo $service_name; ?></div>
                                            <div><small class="text-secondary"><?php echo $service_description; ?></small></div>
                                        </div>
                                    </div>
                                </a>
                        
                            </td>
                            <td><?php echo $service_category ?></td>
                            <td><?php echo $service_importance ?></td>
                            <td><?php echo $service_updated_at ?></td>
                            <?php if (!$client_url) { ?>
                            <td><a href="services.php?client_id=<?php echo $client_id; ?>"><?php echo $client_name; ?></a></td>
                            <?php } ?>

                            <!-- Action -->
                            <td>
                                <div class="dropdown dropleft text-center">
                                    <button class="btn btn-secondary btn-sm" type="button" data-toggle="dropdown">
                                        <i class="fas fa-ellipsis-h"></i>
                                    </button>
                                    <div class="dropdown-menu">
                                        <a class="dropdown-item ajax-modal" href="#"
                                            data-modal-url="modals/service/service_edit.php?id=<?= $service_id ?>">
                                            <i class="fas fa-fw fa-edit mr-2"></i>Edit
                                        </a>
                                        <?php if (lookupUserPermission("module_support") >= 3) { ?>
                                            <div class="dropdown-divider"></div>
                                            <a class="dropdown-item text-danger text-bold confirm-link" href="post.php?delete_service=<?php echo $service_id; ?>&csrf_token=<?php echo $_SESSION['csrf_token']; ?>">
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
            </div>
            <?php require_once "../includes/filter_footer.php";
 ?>
        </div>
    </div>

<?php
require_once "modals/service/service_add.php";
require_once "../includes/footer.php";
