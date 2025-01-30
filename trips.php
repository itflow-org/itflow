<?php 

// Sprachdatei laden
include_once 'languages/lang.php';

// Sprache setzen
$selectedLang = $_SESSION['language'] ?? 'en'; // Standard: Englisch
$langArray = loadLanguage($selectedLang);

// Default Column Sortby/Order Filter
$sort = "trip_date";
$order = "DESC";

require_once "inc_all.php";
// ... (Rebuild URL Code)
$url_query_strings_sort = http_build_query($get_copy);

$sql = mysqli_query(
    $mysqli,
    "SELECT SQL_CALC_FOUND_ROWS * FROM trips
    LEFT JOIN clients ON trip_client_id = client_id
    LEFT JOIN users ON trip_user_id = user_id
    WHERE (trip_purpose LIKE '%$q%' OR trip_source LIKE '%$q%' OR trip_destination LIKE '%$q%' OR trip_miles LIKE '%$q%' OR client_name LIKE '%$q%' OR user_name LIKE '%$q%')
    AND DATE(trip_date) BETWEEN '$dtf' AND '$dtt'
    AND trip_archived_at IS NULL
    ORDER BY $sort $order LIMIT $record_from, $record_to"
);

$num_rows = mysqli_fetch_row(mysqli_query($mysqli, "SELECT FOUND_ROWS()"));
?>

<div class="card card-dark">
    <div class="card-header py-2">
        <h3 class="card-title mt-2"><i class="fa fa-route mr-2"></i><?php echo lang('trips'); ?></h3>
        <div class="card-tools">
            <div class="btn-group">
                <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#addTripModal">
                    <i class="fas fa-plus mr-2"></i><?php echo lang('new_trip'); ?>
                </button>
                <button type="button" class="btn btn-primary dropdown-toggle dropdown-toggle-split" data-toggle="dropdown"></button>
                <div class="dropdown-menu">
                    <a class="dropdown-item text-dark" href="#" data-toggle="modal" data-target="#exportTripsModal">
                        <i class="fa fa-fw fa-download mr-2"></i><?php echo lang('export'); ?>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="card-body">
        <form class="mb-4" autocomplete="off">
            <div class="row">
                <div class="col-sm-4">
                    <div class="input-group">
                        <input type="search" class="form-control" name="q" value="<?php if (isset($q)) { echo stripslashes(nullable_htmlentities($q)); } ?>" placeholder="<?php echo lang('search_trips'); ?>">
                        <div class="input-group-append">
                            <button class="btn btn-secondary" type="button" data-toggle="collapse" data-target="#advancedFilter"><i class="fas fa-filter"></i></button>
                            <button class="btn btn-primary"><i class="fa fa-search"></i></button>
                        </div>
                    </div>
                </div>
            </div>
            <div class="collapse mt-3 <?php if (!empty($_GET['dtf']) || $_GET['canned_date'] !== "custom" ) { echo "show"; } ?>" id="advancedFilter">
                <div class="row">
                    <div class="col-md-2">
                        <div class="form-group">
                            <label><?php echo lang('canned_date'); ?></label>
                            <select onchange="this.form.submit()" class="form-control select2" name="canned_date">
                                <option <?php if ($_GET['canned_date'] == "custom") { echo "selected"; } ?> value="custom"><?php echo lang('custom'); ?></option>
                                <option <?php if ($_GET['canned_date'] == "today") { echo "selected"; } ?> value="today"><?php echo lang('today'); ?></option>
                                <option <?php if ($_GET['canned_date'] == "yesterday") { echo "selected"; } ?> value="yesterday"><?php echo lang('yesterday'); ?></option>
                                <option <?php if ($_GET['canned_date'] == "thisweek") { echo "selected"; } ?> value="thisweek"><?php echo lang('this_week'); ?></option>
                                <option <?php if ($_GET['canned_date'] == "lastweek") { echo "selected"; } ?> value="lastweek"><?php echo lang('last_week'); ?></option>
                                <option <?php if ($_GET['canned_date'] == "thismonth") { echo "selected"; } ?> value="thismonth"><?php echo lang('this_month'); ?></option>
                                <option <?php if ($_GET['canned_date'] == "lastmonth") { echo "selected"; } ?> value="lastmonth"><?php echo lang('last_month'); ?></option>
                                <option <?php if ($_GET['canned_date'] == "thisyear") { echo "selected"; } ?> value="thisyear"><?php echo lang('this_year'); ?></option>
                                <option <?php if ($_GET['canned_date'] == "lastyear") { echo "selected"; } ?> value="lastyear"><?php echo lang('last_year'); ?></option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="form-group">
                            <label><?php echo lang('date_from'); ?></label>
                            <input onchange="this.form.submit()" type="date" class="form-control" name="dtf" max="2999-12-31" value="<?php echo nullable_htmlentities($dtf); ?>">
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="form-group">
                            <label><?php echo lang('date_to'); ?></label>
                            <input onchange="this.form.submit()" type="date" class="form-control" name="dtt" max="2999-12-31" value="<?php echo nullable_htmlentities($dtt); ?>">
                        </div>
                    </div>
                </div>
            </div>
        </form>
        
        <div class="table-responsive-sm">
            <table class="table table-striped table-borderless table-hover">
                <thead class="text-dark <?php if ($num_rows[0] == 0) { echo "d-none"; } ?>">
                <tr>
                    <th>
                        <a class="text-dark" href="?<?php echo $url_query_strings_sort; ?>&sort=trip_date&order=<?php echo $disp; ?>">
                            <?php echo lang('date'); ?> <?php if ($sort == 'trip_date') { echo $order_icon; } ?>
                        </a>
                    </th>
                    <th>
                        <a class="text-dark" href="?<?php echo $url_query_strings_sort; ?>&sort=client_name&order=<?php echo $disp; ?>">
                            <?php echo lang('client'); ?> <?php if ($sort == 'client_name') { echo $order_icon; } ?>
                        </a>
                    </th>
                    <th>
                        <a class="text-dark" href="?<?php echo $url_query_strings_sort; ?>&sort=user_name&order=<?php echo $disp; ?>">
                            <?php echo lang('driver'); ?> <?php if ($sort == 'user_name') { echo $order_icon; } ?>
                        </a>
                    </th>
                    <th>
                        <a class="text-dark" href="?<?php echo $url_query_strings_sort; ?>&sort=trip_purpose&order=<?php echo $disp; ?>">
                            <?php echo lang('purpose'); ?> <?php if ($sort == 'trip_purpose') { echo $order_icon; } ?>
                        </a>
                    </th>
                    <th>
                        <a class="text-dark" href="?<?php echo $url_query_strings_sort; ?>&sort=trip_source&order=<?php echo $disp; ?>">
                            <?php echo lang('source'); ?> <?php if ($sort == 'trip_source') { echo $order_icon; } ?>
                        </a>
                    </th>
                    <th>
                        <a class="text-dark" href="?<?php echo $url_query_strings_sort; ?>&sort=trip_destination&order=<?php echo $disp; ?>">
                            <?php echo lang('destination'); ?> <?php if ($sort == 'trip_destination') { echo $order_icon; } ?>
                        </a>
                    </th>
                    <th>
                        <a class="text-dark" href="?<?php echo $url_query_strings_sort; ?>&sort=trip_miles&order=<?php echo $disp; ?>">
                            <?php echo lang('miles'); ?> <?php if ($sort == 'trip_miles') { echo $order_icon; } ?>
                        </a>
                    </th>
                    <th class="text-center"><?php echo lang('action'); ?></th>
                </tr>
                </thead>
                <tbody>
                <?php while ($row = mysqli_fetch_array($sql)) { 
                    // ... (Variablenzuweisungen)
                ?>
                    <tr>
                        <td><a class="text-dark" href="#" data-toggle="modal" data-target="#editTripModal<?php echo $trip_id; ?>"><?php echo $trip_date; ?></a></td>
                        <td><?php echo $client_name_display; ?></td>
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
                                        <i class="fa fa-fw fa-map-marker-alt mr-2"></i><?php echo lang('map_it'); ?><i class="fa fa-fw fa-external-link-alt ml-2"></i>
                                    </a>
                                    <div class="dropdown-divider"></div>
                                    <a class="dropdown-item" href="#" data-toggle="modal" data-target="#editTripModal<?php echo $trip_id; ?>">
                                        <i class="fa fa-fw fa-edit mr-2"></i><?php echo lang('edit'); ?>
                                    </a>
                                    <a class="dropdown-item" href="#" data-toggle="modal" data-target="#addTripCopyModal<?php echo $trip_id; ?>">
                                        <i class="fa fa-fw fa-copy mr-2"></i><?php echo lang('copy'); ?>
                                    </a>
                                    <div class="dropdown-divider"></div>
                                    <a class="dropdown-item text-danger text-bold confirm-link" href="post.php?delete_trip=<?php echo $trip_id; ?>">
                                        <i class="fa fa-fw fa-trash mr-2"></i><?php echo lang('delete'); ?>
                                    </a>
                                </div>
                            </div>
                        </td>
                    </tr>

                    <?php
                    require "trip_copy_modal.php";
                    require "trip_edit_modal.php";
                    require "trip_export_modal.php";
                    }
                    ?>
                </tbody>
            </table>
        </div>
        <?php require_once "pagination.php"; ?>
    </div>
</div>

<?php
require_once "trip_add_modal.php";
require_once "footer.php";
