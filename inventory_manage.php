<?php

// Default Column Sortby/Order Filter
$sort = "inventory_created_at";
$order = "DESC";

require_once "inc_all.php";

$inventory_product_id = intval($_GET["inventory_product_id"]);

//Rebuild URL
$url_query_strings_sort = http_build_query($get_copy);

$sql = mysqli_query(
    $mysqli,
    "SELECT 
    product_name,
    sum(inventory_quantity),
    inventory_location_name,
    sum(inventory_cost),
    inventory_location_id,
    inventory_id
    FROM inventory
        LEFT JOIN inventory_locations ON inventory_location_id = inventory_location_id
        LEFT JOIN products ON inventory_product_id = product_id
        LEFT JOIN users on inventory_location_user_id = user_id
        LEFT JOIN vendors ON inventory_vendor_id = vendor_id
        WHERE inventory_product_id = $inventory_product_id
        GROUP BY inventory_location_id
        ");

$num_rows = mysqli_fetch_row(mysqli_query($mysqli, "SELECT FOUND_ROWS()"));

$product_name_row = mysqli_fetch_row(mysqli_query($mysqli,"SELECT product_name FROM products WHERE product_id = $inventory_product_id"));
$product_name = sanitizeInput($product_name_row[0]);

?>

    <div class="card card-dark">
        <div class="card-header py-2">
            <h3 class="card-title mt-2"><i class="fas fa-fw fa-box mr-2"></i>Inventory product: <?php echo $product_name?> </h3>
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
                            <th><a class="text-dark" href="?<?php echo $url_query_strings_sort; ?>&sort=category_name&order=<?php echo $disp; ?>">Location</a></th>
                            <th><a class="text-dark" href="?<?php echo $url_query_strings_sort; ?>&sort=vendor_name&order=<?php echo $disp; ?>">Quantity</a></th>
                            <th><a class="text-dark" href="?<?php echo $url_query_strings_sort; ?>&sort=category_name&order=<?php echo $disp; ?>">Unit Cost</a></th>
                            <th class="text-center">Manage location</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php

                        while ($row = mysqli_fetch_array($sql)) {
                            $inventory_id = $row['inventory_id'];
                            $inventory_cost = $row['sum(inventory_cost)'];
                            $inventory_product_id = $row['inventory_product_id'];
                            $inventory_locations = $row['inventory_location_name'];
                            $inventory_location_id = $row['inventory_location_id'];
                            $inventory_quantity = $row['sum(inventory_quantity)'];
                            $inventory_unit_cost = $inventory_cost / $inventory_quantity;
                            ?>

                            <tr>
                                <td class="bg-light pr-0">
                                    <div class="form-check
                                    ">
                                        <input class="form-check
                                        -input" type="checkbox" name="selected[]" value="<?php echo $inventory_id; ?>">

                                    </div>
                                </td>
                                <td><?php echo $inventory_locations; ?></td>
                                <td><?php echo $inventory_quantity; ?></td>
                                <td><?php echo numfmt_format_currency($currency_format, $inventory_unit_cost, $config_currency_format)?></td>
                                <td class="text-center">
                                    <div class="btn-group">
                                        <a href="inventory_location_manage.php?inventory_location_id=<?php echo $inventory_location_id; ?>" class="btn btn-primary btn-sm"><i class="fas fa-fw fa-edit"></i></a>
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
