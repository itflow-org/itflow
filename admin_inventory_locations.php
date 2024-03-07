<?php

// Default Column Sortby Filter
$sort = "inventory_locations_zip";
$order = "ASC";

require_once "inc_all_admin.php";


//Rebuild URL
$url_query_strings_sort = http_build_query($get_copy);

$sql = mysqli_query(
    $mysqli,
    "SELECT * FROM inventory_locations
    WHERE inventory_locations_archived_at IS NULL
    ORDER BY $sort $order"
);

$num_rows = mysqli_num_rows($sql);

?>

    <div class="card card-dark">
        <div class="card-header py-2">
            <h3 class="card-title mt-2"><i class="fas fa-fw fa-map-marker-alt mr-2"></i>Inventory Locations</h3>
            <div class="card-tools">
                <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#addLocationModal"><i class="fas fa-plus mr-2"></i>New Location</button>
            </div>
        </div>
        <div class="card-body">
            <div class="table-responsive-sm">
                <table class="table table-striped table-borderless table-hover">
                    <thead class="text-dark <?php if ($num_rows == 0) { echo "d-none"; } ?>">
                    <tr>
                        <th><a class="text-dark" href="?<?php echo $url_query_strings_sort; ?>&sort=inventory_locations_name&order=<?php echo $disp; ?>">Name</a></th>
                        <th><a class="text-dark" href="?<?php echo $url_query_strings_sort; ?>&sort=inventory_locations_description&order=<?php echo $disp; ?>">Description</a></th>
                        <th><a class="text-dark" href="?<?php echo $url_query_strings_sort; ?>&sort=inventory_locations_user_id&order=<?php echo $disp; ?>">User Assigned</a></th>
                        <th><a class="text-dark" href="?<?php echo $url_query_strings_sort; ?>&sort=inventory_locations_city&order=<?php echo $disp; ?>">City</a></th>
                        <th class="text-center">Action</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php

                    while ($row = mysqli_fetch_array($sql)) {
                        $inventory_locations_id = intval($row['inventory_locations_id']);
                        $inventory_locations_name = nullable_htmlentities($row['inventory_locations_name']);
                        $inventory_locations_description = nullable_htmlentities($row['inventory_locations_description']);
                        $inventory_locations_user_id = intval($row['inventory_locations_user_id']);
                        $inventory_locations_city = nullable_htmlentities($row['inventory_locations_city']);


                        //get username for display
                        $inventory_locations_sql_user = mysqli_query($mysqli, "SELECT * FROM users WHERE user_id = $inventory_locations_user_id");
                        $inventory_locations_user = mysqli_fetch_array($inventory_locations_sql_user);
                        if ($inventory_locations_user) {
                            $inventory_locations_user_name = nullable_htmlentities($inventory_locations_user['user_name']);
                        } else {
                            $inventory_locations_user_name = "Unassigned";
                        }
                        ?>

                        <tr>
                            <td><a class="text-dark text-bold" href="#" data-toggle="modal" data-target="#editTaxModal<?php echo $inventory_locations_id; ?>"><?php echo $inventory_locations_name; ?></a></td>
                            <td><?php echo $inventory_locations_description; ?></td>
                            <td><?php echo $inventory_locations_user_name; ?></td>
                            <td><?php echo $inventory_locations_city; ?></td>
                            <td>
                                <div class="dropdown dropleft text-center">
                                    <button class="btn btn-secondary btn-sm" type="button" data-toggle="dropdown">
                                        <i class="fas fa-ellipsis-h"></i>
                                    </button>
                                    <div class="dropdown-menu">
                                        <a class="dropdown-item" href="#" data-toggle="modal" data-target="#editLocationModal<?php echo $inventory_locations_id; ?>">
                                            <i class="fas fa-fw fa-edit mr-2"></i>Edit
                                        </a>
                                        <a class="dropdown-item" href="post.php?archive_inventory_location=<?php echo $inventory_locations_id; ?>">
                                            <i class="fas fa-fw fa-archive mr-2"></i>Archive
                                        </a>
                                    </div>
                                </div>
                            </td>

                        <?php
                        
                        require "admin_inventory_locations_edit_modal.php";

                    }

                    if ($num_rows == 0) {
                        echo "<h3 class='text-secondary mt-3' style='text-align: center'>No Records Here</h3>";
                    }

                    ?>

                    </tbody>
                </table>

            </div>
        </div>
    </div>

<?php
require_once "admin_inventory_locations_add_modal.php";

require_once "footer.php";
