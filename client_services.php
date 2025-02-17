<?php

// Default Column Sortby Filter
$sort = "service_name";
$order = "ASC";

require_once "includes/inc_all_client.php";

// Perms
enforceUserPermission('module_support');

//Rebuild URL
$url_query_strings_sort = http_build_query($get_copy);

// Overview SQL query
$sql = mysqli_query(
    $mysqli,
    "SELECT SQL_CALC_FOUND_ROWS * FROM services
    WHERE service_client_id = '$client_id'
    AND (service_name LIKE '%$q%' OR service_description LIKE '%$q%' OR service_category LIKE '%$q%')
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
                <input type="hidden" name="client_id" value="<?php echo $client_id; ?>">
                <div class="row">
                    <div class="col-md-4">
                        <div class="input-group mb-3 mb-md-0">
                            <input type="search" class="form-control" name="q" value="<?php if (isset($q)) { echo stripslashes(nullable_htmlentities($q)); } ?>" placeholder="Search Services">
                            <div class="input-group-append">
                                <button class="btn btn-dark"><i class="fa fa-search"></i></button>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-8">
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
                        <th><a class="text-dark">Name</a></th>
                        <th><a class="text-dark">Category</a></th>
                        <th><a class="text-dark">Importance</a></th>
                        <th><a class="text-dark">Updated</a></th>
                        <th class="text-center">Action</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php

                    while ($row = mysqli_fetch_array($sql)) {
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
                                <a class="text-dark" href="#"
                                    data-toggle="ajax-modal"
                                    data-modal-size="xl"
                                    data-ajax-url="ajax/ajax_service_details.php"
                                    data-ajax-id="<?php echo $service_id; ?>"
                                    >
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

                            <!-- Action -->
                            <td>
                                <div class="dropdown dropleft text-center">
                                    <button class="btn btn-secondary btn-sm" type="button" data-toggle="dropdown">
                                        <i class="fas fa-ellipsis-h"></i>
                                    </button>
                                    <div class="dropdown-menu">
                                        <a class="dropdown-item" href="#"
                                            data-toggle="ajax-modal"
                                            data-ajax-url="ajax/ajax_service_edit.php"
                                            data-ajax-id="<?php echo $service_id; ?>"
                                            >
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
            <?php require_once "includes/filter_footer.php";
 ?>
        </div>
    </div>

<?php
require_once "modals/client_service_add_modal.php";
require_once "includes/footer.php";
