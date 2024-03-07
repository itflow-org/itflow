<?php

// Default Column Sortby/Order Filter
$sort = "inventory_created_at";
$order = "DESC";

require_once "inc_all.php";


//Rebuild URL
$url_query_strings_sort = http_build_query($get_copy);

$sql = mysqli_query(
    $mysqli,
    "SELECT 
    inventory_locations_id,
    inventory_locations_name,
    inventory_locations_description,
    user_name
    FROM inventory_locations
        LEFT JOIN users on inventory_locations_user_id = user_id
        ");

$num_rows = mysqli_fetch_row(mysqli_query($mysqli, "SELECT FOUND_ROWS()"));

?>

    <div class="card card-dark">
        <div class="card-header py-2">
            <h3 class="card-title mt-2"><i class="fas fa-fw fa-box mr-2"></i>Inventory locations </h3>
        </div>

        <div class="card-body">
            <form class="mb-4" autocomplete="off">
                <div class="row">
                    <div class="col-sm-4">
                        <div class="input-group">
                            <input type="search" class="form-control" name="q" value="<?php if (isset($q)) { echo stripslashes(nullable_htmlentities($q)); } ?>" placeholder="Search <?php echo $product?>">
                            <div class="input-group-append">
                                <button class="btn btn-primary"><i class="fa fa-search"></i></button>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-8">
                        <div class="btn-group float-right">
                            <a href="inventory.php" class="btn btn-outline-primary"><i class="fa fa-fw fa-arrow-left mr-2"></i>Back to full inventory</a>
                            <div class="dropdown ml-2" id="bulkActionButton" hidden>
                                <button class="btn btn-secondary dropdown-toggle" type="button" data-toggle="dropdown">
                                    <i class="fas fa-fw fa-layer-group mr-2"></i>Bulk Action (<span id="selectedCount">0</span>)
                                </button>
                                <div class="dropdown-menu">
                                    <a class="dropdown-item" href="#" data-toggle="modal" data-target="#bulkEditCategoryModal">
                                        <i class="fas fa-fw fa-list mr-2"></i>Set Category
                                    </a>
                                    <div class="dropdown-divider"></div>
                                    <a class="dropdown-item" href="#" data-toggle="modal" data-target="#bulkEditAccountModal">
                                        <i class="fas fa-fw fa-piggy-bank mr-2"></i>Set Account
                                    </a>
                                    <div class="dropdown-divider"></div>
                                    <a class="dropdown-item" href="#" data-toggle="modal" data-target="#bulkEditClientModal">
                                        <i class="fas fa-fw fa-user mr-2"></i>Set Client
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
            <hr>
            <form id="bulkActions" action="post.php" method="post">
                <div class="table-responsive-sm">
                    <table class="table table-striped table-borderless table-hover">
                        <thead class="text-dark <?php if ($num_rows[0] == 0) { echo "d-none"; } ?>">
                        <tr>
                            <td class="bg-light pr-0">
                                <div class="form-check">
                                    <input class="form-check-input" id="selectAllCheckbox" type="checkbox" onclick="checkAll(this)">
                                </div>
                            </td>
                            <th><a class="text-dark" href="?<?php echo $url_query_strings_sort; ?>&sort=vendor_name&order=<?php echo $disp; ?>">Name</a></th>
                            <th><a class="text-dark" href="?<?php echo $url_query_strings_sort; ?>&sort=category_name&order=<?php echo $disp; ?>">Description</a></th>
                            <th><a class="text-dark" href="?<?php echo $url_query_strings_sort; ?>&sort=category_name&order=<?php echo $disp; ?>">User Responsible</a></th>
                            <th><a class="text-dark" href="?<?php echo $url_query_strings_sort; ?>&sort=category_name&order=<?php echo $disp; ?>">Total Quantity</a></th>
                            <th class="text-center">Manage Inventory</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php

                        while ($row = mysqli_fetch_array($sql)) {
                            $location_id = $row["inventory_locations_id"];
                            $location_name = $row["inventory_locations_name"];
                            $location_description = $row["inventory_locations_description"];
                            $location_user = $row["user_name"];
                            
                            //Calculate number of items in location in DB
                            $location_qty_sql = mysqli_query($mysqli, "SELECT SUM(inventory_quantity) FROM inventory WHERE inventory_location_id = $location_id");
                            $location_qty = mysqli_fetch_row($location_qty_sql)[0];

                            ?>

                            <tr>
                                <td class="bg-light pr-0">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="selected[]" value="<?php echo $location_id; ?>">
                                    </div>
                                </td>
                                <td><?php echo $location_name; ?></td>
                                <td><?php echo $location_description; ?></td>
                                <td><?php echo $location_user; ?></td>
                                <td><?php echo $location_qty; ?></td>
                                <td class="text-center">
                                    <div class="btn-group">
                                        <a href="inventory_locations_manage.php?inventory_location_id=<?php echo $location_id; ?>" class="btn btn-primary btn-sm"><i class="fas fa-fw fa-edit"></i></a>
                                    </div>
                                </td>

                            <?php
                        }

                        ?>

                        </tbody>
                    </table>
                </div>

            </form>
            <?php require_once "pagination.php";
 ?>
        </div>
    </div>

<script src="js/bulk_actions.js"></script>

<?php

require_once "footer.php";
