<?php

require_once("inc_all_client.php");

if (!empty($_GET['sb'])) {
    $sb = sanitizeInput($_GET['sb']);
} else {
    $sb = "trip_date";
}

// Reverse default sort
if (!isset($_GET['o'])) {
    $o = "DESC";
    $disp = "ASC";
}

//Date From and Date To Filter
if (isset($_GET['dtf'])) {
    $dtf = sanitizeInput($_GET['dtf']);
    $dtt = sanitizeInput($_GET['dtt']);
} else {
    $dtf = "1000-01-01";
    $dtt = "9999-01-01";
}

//Rebuild URL
$url_query_strings_sb = http_build_query(array_merge($_GET, array('sb' => $sb, 'o' => $o)));

$sql = mysqli_query(
    $mysqli,
    "SELECT SQL_CALC_FOUND_ROWS * FROM trips
    LEFT JOIN users ON trip_user_id = user_id
    WHERE (trip_purpose LIKE '%$q%' OR trip_source LIKE '%$q%' OR trip_destination LIKE '%$q%' OR user_name LIKE '%$q%')
    AND DATE(trip_date) BETWEEN '$dtf' AND '$dtt'
    AND company_id = $session_company_id
    AND trip_client_id = $client_id
    ORDER BY $sb $o LIMIT $record_from, $record_to");

$num_rows = mysqli_fetch_row(mysqli_query($mysqli, "SELECT FOUND_ROWS()"));

?>

    <div class="card card-dark">
        <div class="card-header py-2">
            <h3 class="card-title mt-2"><i class="fas fa-route mr-2"></i>Trips</h3>
            <div class="card-tools">
                <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#addTripModal"><i class="fas fa-plus mr-2"></i>New Trip</button>
            </div>
        </div>
        <div class="card-body">
            <form autocomplete="off">
                <input type="hidden" name="client_id" value="<?php echo $client_id; ?>">
                <div class="row">

                    <div class="col-md-4">
                        <div class="input-group mb-3 mb-md-0">
                            <input type="search" class="form-control" name="q" value="<?php if (isset($q)) { echo stripslashes(htmlentities($q)); } ?>" placeholder="Search Trips">
                            <div class="input-group-append">
                                <button class="btn btn-dark"><i class="fa fa-search"></i></button>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-8">
                        <div class="float-right">
                            <a href="post.php?export_client_trips_csv=<?php echo $client_id; ?>" class="btn btn-default"><i class="fa fa-fw fa-download mr-2"></i>Export</a>
                            <a href="#" class="btn btn-default"><i class="fa fa-fw fa-upload mr-2"></i>Import</a>
                        </div>
                    </div>

                </div>
            </form>
            <hr>
            <div class="table-responsive">
                <table class="table table-striped table-borderless table-hover">
                    <thead class="text-dark <?php if ($num_rows[0] == 0) { echo "d-none"; } ?>">
                    <tr>
                        <th><a class="text-dark" href="?<?php echo $url_query_strings_sb; ?>&sb=trip_date&o=<?php echo $disp; ?>">Date</a></th>
                        <th><a class="text-dark" href="?<?php echo $url_query_strings_sb; ?>&sb=user_name&o=<?php echo $disp; ?>">Driver</a></th>
                        <th><a class="text-dark" href="?<?php echo $url_query_strings_sb; ?>&sb=trip_purpose&o=<?php echo $disp; ?>">Purpose</a></th>
                        <th><a class="text-dark" href="?<?php echo $url_query_strings_sb; ?>&sb=trip_source&o=<?php echo $disp; ?>">From</a></th>
                        <th><a class="text-dark" href="?<?php echo $url_query_strings_sb; ?>&sb=trip_destination&o=<?php echo $disp; ?>">To</a></th>
                        <th><a class="text-dark" href="?<?php echo $url_query_strings_sb; ?>&sb=trip_miles&o=<?php echo $disp; ?>">Miles</a></th>
                        <th class="text-center">Action</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php

                    while ($row = mysqli_fetch_array($sql)) {
                        $trip_id = intval($row['trip_id']);
                        $trip_date = htmlentities($row['trip_date']);
                        $trip_purpose = htmlentities($row['trip_purpose']);
                        $trip_source = htmlentities($row['trip_source']);
                        $trip_destination = htmlentities($row['trip_destination']);
                        $trip_miles = htmlentities($row['trip_miles']);
                        $trip_user_id = intval($row['trip_user_id']);
                        $round_trip = htmlentities($row['round_trip']);
                        $client_id = intval($row['trip_client_id']);

                        if ($round_trip == 1) {
                            $round_trip_display = "<i class='fa fa-fw fa-sync-alt text-secondary'></i>";
                        } else {
                            $round_trip_display = "";
                        }
                        $user_name = htmlentities($row['user_name']);
                        if (empty($user_name)) {
                            $user_name_display = "-";
                        } else {
                            $user_name_display = $user_name;
                        }

                        ?>
                        <tr>
                            <td><a class="text-dark" href="#" data-toggle="modal" data-target="#editTripModal<?php echo $trip_id; ?>"><?php echo $trip_date; ?></a></td>
                            <td><?php echo $user_name_display; ?></td>
                            <td><?php echo $trip_purpose; ?></td>
                            <td><?php echo $trip_source; ?></td>
                            <td><?php echo $trip_destination; ?></td>
                            <td><?php echo "$trip_miles $round_trip_display"; ?></td>
                            <td>
                                <div class="dropdown dropleft text-center">
                                    <button class="btn btn-secondary btn-sm" type="button" data-toggle="dropdown">
                                        <i class="fas fa-ellipsis-h"></i>
                                    </button>
                                    <div class="dropdown-menu">
                                        <a class="dropdown-item" href="//maps.google.com?q=<?php echo $trip_source; ?> to <?php echo $trip_destination; ?>" target="_blank">
                                            <i class="fa fa-fw fa-map-marker mr-2"></i>Map it
                                        </a>
                                        <div class="dropdown-divider"></div>
                                        <a class="dropdown-item" href="#" data-toggle="modal" data-target="#editTripModal<?php echo $trip_id; ?>">
                                            <i class="fa fa-fw fa-edit mr-2"></i>Edit
                                        </a>
                                        <a class="dropdown-item" href="#" data-toggle="modal" data-target="#addTripCopyModal<?php echo $trip_id; ?>">
                                            <i class="fa fa-fw fa-copy mr-2"></i>Copy
                                        </a>
                                        <div class="dropdown-divider"></div>
                                        <a class="dropdown-item text-danger" href="post.php?delete_trip=<?php echo $trip_id; ?>">
                                            <i class="fa fa-fw fa-trash mr-2"></i>Delete
                                        </a>
                                    </div>
                                </div>
                            </td>
                        </tr>

                        <?php

                        require("trip_copy_modal.php");
                        require("trip_edit_modal.php");
                    }

                    ?>

                    </tbody>
                </table>
            </div>
            <?php require_once("pagination.php"); ?>
        </div>
    </div>

<?php
require_once("trip_add_modal.php");
require_once("footer.php");
