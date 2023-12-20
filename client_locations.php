<?php

// Default Column Sortby Filter
$sort = "location_name";
$order = "ASC";

require_once "inc_all_client.php";


//Rebuild URL
$url_query_strings_sort = http_build_query($get_copy);

$sql = mysqli_query(
    $mysqli,
    "SELECT SQL_CALC_FOUND_ROWS * FROM locations
    WHERE location_client_id = $client_id
    AND location_$archive_query
    AND (location_name LIKE '%$q%' OR location_address LIKE '%$q%' OR location_phone LIKE '%$phone_query%')
    ORDER BY location_primary DESC, $sort $order LIMIT $record_from, $record_to"
);

$num_rows = mysqli_fetch_row(mysqli_query($mysqli, "SELECT FOUND_ROWS()"));

?>

<div class="card card-dark">
    <div class="card-header py-2">
        <h3 class="card-title mt-2"><i class="fa fa-fw fa-map-marker-alt mr-2"></i>Locations</h3>
        <div class="card-tools">
            <div class="btn-group">
                <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#addLocationModal">
                    <i class="fas fa-plus mr-2"></i>New Location
                </button>
                <button type="button" class="btn btn-primary dropdown-toggle dropdown-toggle-split"
                    data-toggle="dropdown"></button>
                <div class="dropdown-menu">
                    <a class="dropdown-item text-dark" href="#" data-toggle="modal" data-target="#importLocationModal">
                        <i class="fa fa-fw fa-upload mr-2"></i>Import
                    </a>
                    <div class="dropdown-divider"></div>
                    <a class="dropdown-item text-dark" href="#" data-toggle="modal" data-target="#exportLocationModal">
                        <i class="fa fa-fw fa-download mr-2"></i>Export
                    </a>
                </div>
            </div>
        </div>
    </div>
    <div class="card-body">
        <form autocomplete="off">
            <input type="hidden" name="client_id" value="<?php echo $client_id; ?>">
            <input type="hidden" name="archived" value="<?php echo $archived; ?>">
            <div class="row">

                <div class="col-md-4">
                    <div class="input-group mb-3 mb-md-0">
                        <input type="search" class="form-control" name="q"
                            value="<?php if (isset($q)) { echo stripslashes(nullable_htmlentities($q)); } ?>"
                            placeholder="Search Locations">
                        <div class="input-group-append">
                            <button class="btn btn-dark"><i class="fa fa-search"></i></button>
                        </div>
                    </div>
                </div>

                <div class="col-md-8">
                    <div class="float-right">
                        <?php if($archived == 1){ ?>
                        <a href="?client_id=<?php echo $client_id; ?>&archived=0" class="btn btn-primary"><i
                                class="fa fa-fw fa-archive mr-2"></i>Archived</a>
                        <?php } else { ?>
                        <a href="?client_id=<?php echo $client_id; ?>&archived=1" class="btn btn-default"><i
                                class="fa fa-fw fa-archive mr-2"></i>Archived</a>
                        <?php } ?>
                    </div>
                </div>

            </div>
        </form>
        <hr>
        <div class="table-responsive-sm">
            <table class="table table-striped table-borderless table-hover">
                <thead class="<?php if ($num_rows[0] == 0) { echo "d-none"; } ?>">
                <tr>
                    <?php
                    $locationColumns = [
                        'location_name' => ['Name', 'sort-location-name'],
                        'location_address' => ['Address', 'sort-location-address'],
                        'location_phone' => ['Phone', 'sort-location-phone'],
                        'location_hours' => ['Hours', 'sort-location-hours']
                    ];

                    foreach ($locationColumns as $sortParam => $columnData) {
                        list($columnName, $columnId) = $columnData;
                        echo "<th id='$columnId'><a class='text-secondary' href='?
                        $url_query_strings_sort&sort=$sortParam&order=$disp'>$columnName</a></th>";
                    }
                    ?>
                    <th id="sort-location-action" class="text-center">Action</th>
                </tr>

                </thead>
                <tbody>
                    <?php

                while ($row = mysqli_fetch_array($sql)) {
                    $location_id = intval($row['location_id']);
                    $location_name = nullable_htmlentities($row['location_name']);
                    $location_country = nullable_htmlentities($row['location_country']);
                    $location_address = nullable_htmlentities($row['location_address']);
                    $location_city = nullable_htmlentities($row['location_city']);
                    $location_state = nullable_htmlentities($row['location_state']);
                    $location_zip = nullable_htmlentities($row['location_zip']);
                    $location_phone = formatPhoneNumber($row['location_phone']);
                    if (empty($location_phone)) {
                        $location_phone_display = "-";
                    } else {
                        $location_phone_display = $location_phone;
                    }
                    $location_hours = nullable_htmlentities($row['location_hours']);
                    if (empty($location_hours)) {
                        $location_hours_display = "-";
                    } else {
                        $location_hours_display = $location_hours;
                    }
                    $location_photo = nullable_htmlentities($row['location_photo']);
                    $location_notes = nullable_htmlentities($row['location_notes']);
                    $location_created_at = nullable_htmlentities($row['location_created_at']);
                    $location_contact_id = intval($row['location_contact_id']);
                    $location_primary = intval($row['location_primary']);
                    if ( $location_primary == 1 ) {
                        $location_primary_display = "<p class='text-success'>Primary Location</p>";
                    } else {
                        $location_primary_display = "";
                    }

                    ?>
                    <tr>
                        <th id="location-<?php echo $location_id; ?>">
                            <i class="fa fa-fw fa-map-marker-alt text-secondary"></i>
                            <a class="text-dark" href="#" data-toggle="modal"
                                data-target="#editLocationModal<?php echo $location_id; ?>">
                                <?php echo $location_name; ?></a>
                            <?php echo $location_primary_display; ?>
                        </th>
                        <td><a href="//maps.<?php echo $session_map_source; ?>.com?
                            q=<?php echo "$location_address $location_zip"; ?>"
                                target="_blank"><?php echo $location_address; ?><br>
                                <?php echo "$location_city $location_state $location_zip"; ?>
                            </a>
                        </td>
                        <td><?php echo $location_phone_display; ?></td>
                        <td><?php echo $location_hours_display; ?></td>
                        <td>
                            <div class="dropdown dropleft text-center">
                                <button class="btn btn-secondary btn-sm" type="button" data-toggle="dropdown">
                                    <i class="fas fa-ellipsis-h"></i>
                                </button>
                                <div class="dropdown-menu">
                                    <a class="dropdown-item" href="#" data-toggle="modal"
                                        data-target="#editLocationModal<?php echo $location_id; ?>">
                                        <i class="fas fa-fw fa-edit mr-2"></i>Edit
                                    </a>
                                    <?php if ($session_user_role == 3 && $location_primary == 0) { ?>
                                    <div class="dropdown-divider"></div>
                                    <a class="dropdown-item text-danger confirm-link"
                                        href="post.php?archive_location=<?php echo $location_id; ?>">
                                        <i class="fas fa-fw fa-archive mr-2"></i>Archive
                                    </a>
                                    <div class="dropdown-divider"></div>
                                    <a class="dropdown-item text-danger text-bold confirm-link"
                                        href="post.php?delete_location=<?php echo $location_id; ?>">
                                        <i class="fas fa-fw fa-trash mr-2"></i>Delete
                                    </a>
                                    <?php } ?>
                                </div>
                            </div>
                            <?php require "client_location_edit_modal.php";
 ?>
                        </td>
                    </tr>

                    <?php } ?>

                </tbody>
            </table>
        </div>
        <?php require_once "pagination.php";
 ?>
    </div>
</div>

<?php

require_once "client_location_add_modal.php";

require_once "client_location_import_modal.php";

require_once "client_location_export_modal.php";

require_once "footer.php";
