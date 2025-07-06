<?php

// Default Column Sortby/Order Filter
$sort = "trip_date";
$order = "DESC";

// If client_id is in URI then show client Side Bar and client header
if (isset($_GET['client_id'])) {
    require_once "includes/inc_all_client.php";
    $client_query = "AND trip_client_id = $client_id";
    $client_url = "client_id=$client_id&";
} else {
    require_once "includes/inc_all.php";
    $client_query = '';
    $client_url = '';
}

//Rebuild URL
$url_query_strings_sort = http_build_query($get_copy);

$sql = mysqli_query(
    $mysqli,
    "SELECT SQL_CALC_FOUND_ROWS * FROM trips
    LEFT JOIN clients ON trip_client_id = client_id
    LEFT JOIN users ON trip_user_id = user_id
    WHERE (trip_purpose LIKE '%$q%' OR trip_source LIKE '%$q%' OR trip_destination LIKE '%$q%' OR trip_miles LIKE '%$q%' OR client_name LIKE '%$q%' OR user_name LIKE '%$q%')
    AND DATE(trip_date) BETWEEN '$dtf' AND '$dtt'
    AND trip_archived_at IS NULL
    $client_query
    ORDER BY $sort $order LIMIT $record_from, $record_to"
);

$num_rows = mysqli_fetch_row(mysqli_query($mysqli, "SELECT FOUND_ROWS()"));

?>

    <div class="card card-dark">
        <div class="card-header py-2">
            <h3 class="card-title mt-2"><i class="fa fa-route mr-2"></i>Trips</h3>
            <div class="card-tools">
                <div class="btn-group">
                    <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#addTripModal"><i class="fas fa-plus mr-2"></i>New Trip</button>
                    <button type="button" class="btn btn-primary dropdown-toggle dropdown-toggle-split" data-toggle="dropdown"></button>
                    <div class="dropdown-menu">
                        <a class="dropdown-item text-dark" href="#" data-toggle="modal" data-target="#exportTripsModal">
                            <i class="fa fa-fw fa-download mr-2"></i>Export
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <div class="card-body">
            <form class="mb-4" autocomplete="off">
                <?php if ($client_url) { ?>
                    <input type="hidden" name="client_id" value="<?php echo $client_id; ?>">
                <?php } ?>
                <div class="row">
                    <div class="col-sm-4">
                        <div class="input-group">
                            <input type="search" class="form-control" name="q" value="<?php if (isset($q)) { echo stripslashes(nullable_htmlentities($q)); } ?>" placeholder="Search Trips">
                            <div class="input-group-append">
                                <button class="btn btn-secondary" type="button" data-toggle="collapse" data-target="#advancedFilter"><i class="fas fa-filter"></i></button>
                                <button class="btn btn-primary"><i class="fa fa-search"></i></button>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-8">
                        <div class="float-right">
                        </div>
                    </div>
                </div>
                <div class="collapse mt-3 <?php if (!empty($_GET['dtf']) || $_GET['canned_date'] !== "custom" ) { echo "show"; } ?>" id="advancedFilter">
                    <div class="row">
                        <div class="col-md-2">
                            <div class="form-group">
                                <label>Canned Date</label>
                                <select onchange="this.form.submit()" class="form-control select2" name="canned_date">
                                    <option <?php if ($_GET['canned_date'] == "custom") { echo "selected"; } ?> value="custom">Custom</option>
                                    <option <?php if ($_GET['canned_date'] == "today") { echo "selected"; } ?> value="today">Today</option>
                                    <option <?php if ($_GET['canned_date'] == "yesterday") { echo "selected"; } ?> value="yesterday">Yesterday</option>
                                    <option <?php if ($_GET['canned_date'] == "thisweek") { echo "selected"; } ?> value="thisweek">This Week</option>
                                    <option <?php if ($_GET['canned_date'] == "lastweek") { echo "selected"; } ?> value="lastweek">Last Week</option>
                                    <option <?php if ($_GET['canned_date'] == "thismonth") { echo "selected"; } ?> value="thismonth">This Month</option>
                                    <option <?php if ($_GET['canned_date'] == "lastmonth") { echo "selected"; } ?> value="lastmonth">Last Month</option>
                                    <option <?php if ($_GET['canned_date'] == "thisyear") { echo "selected"; } ?> value="thisyear">This Year</option>
                                    <option <?php if ($_GET['canned_date'] == "lastyear") { echo "selected"; } ?> value="lastyear">Last Year</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-group">
                                <label>Date From</label>
                                <input onchange="this.form.submit()" type="date" class="form-control" name="dtf" max="2999-12-31" value="<?php echo nullable_htmlentities($dtf); ?>">
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-group">
                                <label>Date To</label>
                                <input onchange="this.form.submit()" type="date" class="form-control" name="dtt" max="2999-12-31" value="<?php echo nullable_htmlentities($dtt); ?>">
                            </div>
                        </div>
                    </div>
                </div>
            </form>
            <hr>
            <div class="table-responsive-sm">
                <table class="table table-striped table-borderless table-hover">
                    <thead class="text-dark <?php if ($num_rows[0] == 0) { echo "d-none"; } ?>">
                    <tr>
                        <th>
                            <a class="text-dark" href="?<?php echo $url_query_strings_sort; ?>&sort=trip_date&order=<?php echo $disp; ?>">
                                Date <?php if ($sort == 'trip_date') { echo $order_icon; } ?>
                            </a>
                        </th>
                        <th>
                            <a class="text-dark" href="?<?php echo $url_query_strings_sort; ?>&sort=user_name&order=<?php echo $disp; ?>">
                                Driver <?php if ($sort == 'user_name') { echo $order_icon; } ?>
                            </a>
                        </th>
                        <th>
                            <a class="text-dark" href="?<?php echo $url_query_strings_sort; ?>&sort=trip_purpose&order=<?php echo $disp; ?>">
                                Purpose <?php if ($sort == 'trip_purpose') { echo $order_icon; } ?>
                            </a>
                        </th>
                        <th>
                            <a class="text-dark" href="?<?php echo $url_query_strings_sort; ?>&sort=trip_source&order=<?php echo $disp; ?>">
                                Source <?php if ($sort == 'trip_source') { echo $order_icon; } ?>
                            </a>
                        </th>
                        <th>
                            <a class="text-dark" href="?<?php echo $url_query_strings_sort; ?>&sort=trip_destination&order=<?php echo $disp; ?>">
                                Destination <?php if ($sort == 'trip_destination') { echo $order_icon; } ?>
                            </a>
                        </th>
                        <th>
                            <a class="text-dark" href="?<?php echo $url_query_strings_sort; ?>&sort=trip_miles&order=<?php echo $disp; ?>">
                                Miles <?php if ($sort == 'trip_miles') { echo $order_icon; } ?>
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
                        $trip_id = intval($row['trip_id']);
                        $trip_date = nullable_htmlentities($row['trip_date']);
                        $trip_purpose = nullable_htmlentities($row['trip_purpose']);
                        $trip_source = nullable_htmlentities($row['trip_source']);
                        $trip_destination = nullable_htmlentities($row['trip_destination']);
                        $trip_miles = number_format(floatval($row['trip_miles']),1);
                        $trip_user_id = intval($row['trip_user_id']);
                        $trip_created_at = nullable_htmlentities($row['trip_created_at']);
                        $trip_archived_at = nullable_htmlentities($row['trip_archived_at']);
                        $round_trip = nullable_htmlentities($row['round_trip']);
                        $client_id = intval($row['client_id']);
                        $client_name = nullable_htmlentities($row['client_name']);
                        if (empty($client_name)) {
                            $client_name_display = "-";
                        } else {
                            $client_name_display = "<a href='trips.php?client_id=$client_id'>$client_name</a>";
                        }
                        if ($round_trip == 1) {
                            $round_trip_display = "<i class='fa fa-fw fa-sync-alt text-secondary'></i>";
                        } else {
                            $round_trip_display = "";
                        }
                        $user_name = nullable_htmlentities($row['user_name']);
                        if (empty($user_name)) {
                            $user_name_display = "-";
                        } else {
                            $user_name_display = $user_name;
                        }

                        ?>
                        <tr>
                            <td>
                                <a class="text-dark" href="#"
                                    data-toggle="ajax-modal"
                                    data-ajax-url="ajax/ajax_trip_edit.php?<?php echo $client_url; ?>"
                                    data-ajax-id="<?php echo $trip_id; ?>"
                                    >
                                    <?php echo $trip_date; ?>
                                </a>
                            </td>
                            <td><?php echo $user_name_display; ?></td>
                            <td><?php echo $trip_purpose; ?></td>
                            <td><?php echo $trip_source; ?></td>
                            <td><?php echo $trip_destination; ?></td>
                            <td><?php echo "$trip_miles $round_trip_display"; ?></td>
                            <?php if (!$client_url) { ?>
                            <td><?php echo $client_name_display; ?></td>
                            <?php } ?>
                            <td>
                                <div class="dropdown dropleft text-center">
                                    <button class="btn btn-secondary btn-sm" type="button" data-toggle="dropdown">
                                        <i class="fas fa-ellipsis-h"></i>
                                    </button>
                                    <div class="dropdown-menu">
                                        <a class="dropdown-item" href="//maps.google.com?q=<?php echo $trip_source; ?> to <?php echo $trip_destination; ?>" target="_blank">
                                            <i class="fa fa-fw fa-map-marker-alt mr-2"></i>Map it<i class="fa fa-fw fa-external-link-alt ml-2"></i>
                                        </a>
                                        <div class="dropdown-divider"></div>
                                        <a class="dropdown-item" href="#"
                                            data-toggle="ajax-modal"
                                            data-ajax-url="ajax/ajax_trip_edit.php?<?php echo $client_url; ?>"
                                            data-ajax-id="<?php echo $trip_id; ?>"
                                            >
                                            <i class="fa fa-fw fa-edit mr-2"></i>Edit
                                        </a>
                                        <a class="dropdown-item" href="#"
                                            data-toggle="ajax-modal"
                                            data-ajax-url="ajax/ajax_trip_copy.php?<?php echo $client_url; ?>"
                                            data-ajax-id="<?php echo $trip_id; ?>"
                                            >
                                            <i class="fa fa-fw fa-copy mr-2"></i>Copy
                                        </a>
                                        <div class="dropdown-divider"></div>
                                        <a class="dropdown-item text-danger text-bold confirm-link" href="post.php?delete_trip=<?php echo $trip_id; ?>">
                                            <i class="fa fa-fw fa-trash mr-2"></i>Delete
                                        </a>
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
require_once "modals/trip_add_modal.php";
require_once "modals/trip_export_modal.php";
require_once "includes/footer.php";
