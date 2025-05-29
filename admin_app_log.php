<?php

// Default Column Sortby Filter
$sort = "app_log_id";
$order = "DESC";

require_once "includes/inc_all_admin.php";

// Log Type Filter
if (isset($_GET['type']) & !empty($_GET['type'])) {
    $log_type_query = "AND (app_log_type  = '" . sanitizeInput($_GET['type']) . "')";
    $type_filter = nullable_htmlentities($_GET['type']);
} else {
    // Default - any
    $log_type_query = '';
    $type_filter = '';
}

// Log Category Filter
if (isset($_GET['category']) & !empty($_GET['catergory'])) {
    $log_category_query = "AND (app_log_category  = '" . sanitizeInput($_GET['category']) . "')";
    $category_filter = nullable_htmlentities($_GET['category']);
} else {
    // Default - any
    $log_category_query = '';
    $category_filter = '';
}

//Rebuild URL
$url_query_strings_sort = http_build_query($get_copy);

$sql = mysqli_query(
    $mysqli,
    "SELECT SQL_CALC_FOUND_ROWS * FROM app_logs
    WHERE (app_log_type LIKE '%$q%' OR app_log_category LIKE '%$q%' OR app_log_details LIKE '%$q%')
    AND DATE(app_log_created_at) BETWEEN '$dtf' AND '$dtt'
    $log_type_query
    $log_category_query
    ORDER BY $sort $order LIMIT $record_from, $record_to"
);

$num_rows = mysqli_fetch_row(mysqli_query($mysqli, "SELECT FOUND_ROWS()"));

?>

    <div class="card card-dark">
        <div class="card-header py-3">
            <h3 class="card-title"><i class="fas fa-fw fa-history mr-2"></i>App Logs</h3>
        </div>
        <div class="card-body">
            <form autocomplete="off">
                <div class="row">
                    <div class="col-sm-4">
                        <div class="form-group">
                            <div class="input-group">
                                <input type="search" class="form-control" name="q" value="<?php if (isset($q)) { echo stripslashes(nullable_htmlentities($q)); } ?>" placeholder="Search app logs">
                                <div class="input-group-append">
                                    <button class="btn btn-secondary" type="button" data-toggle="collapse" data-target="#advancedFilter"><i class="fas fa-filter"></i></button>
                                    <button class="btn btn-primary"><i class="fa fa-search"></i></button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-sm-2">
                        <div class="form-group">
                            <select class="form-control select2" name="type" onchange="this.form.submit()">
                                <option value="">- All Types -</option>

                                <?php
                                $sql_types_filter = mysqli_query($mysqli, "SELECT DISTINCT app_log_type FROM app_logs ORDER BY app_log_type ASC");
                                while ($row = mysqli_fetch_array($sql_types_filter)) {
                                    $log_type = nullable_htmlentities($row['app_log_type']);
                                ?>
                                    <option <?php if ($type_filter == $log_type) { echo "selected"; } ?>><?php echo $log_type; ?></option>
                                <?php
                                }
                                ?>

                            </select>
                        </div>
                    </div>

                    <div class="col-sm-2">
                        <div class="form-group">
                            <select class="form-control select2" name="category" onchange="this.form.submit()">
                                <option value="">- All Categories -</option>

                                <?php
                                $sql_categories_filter = mysqli_query($mysqli, "SELECT DISTINCT app_log_category FROM app_logs ORDER BY app_log_category ASC");
                                while ($row = mysqli_fetch_array($sql_categories_filter)) {
                                    $log_category = nullable_htmlentities($row['app_log_category']);
                                ?>
                                    <option <?php if ($category_filter == $log_category) { echo "selected"; } ?>><?php echo $log_category; ?></option>
                                <?php
                                }
                                ?>

                            </select>
                        </div>
                    </div>
                </div>
                <div class="collapse mt-3 <?php if (!empty($_GET['dtf']) || $_GET['canned_date'] !== "custom" ) { echo "show"; } ?>" id="advancedFilter">
                    <div class="row">
                        <div class="col-md-2">
                            <div class="form-group">
                                <label>Canned Date</label>
                                <select onchange="this.form.submit()" class="form-control select2" name="canned_date">
                                    <option <?php if ($_GET['canned_date'] == "custom") { echo "selected"; } ?> value="">Custom</option>
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
                <table class="table table-sm table-striped table-borderless table-hover">
                    <thead class="text-dark <?php if ($num_rows[0] == 0) { echo "d-none"; } ?>">
                    <tr>
                        <th>
                            <a class="text-dark" href="?<?php echo $url_query_strings_sort; ?>&sort=app_log_created_at&order=<?php echo $disp; ?>">
                                Timestamp <?php if ($sort == 'app_log_created_at') { echo $order_icon; } ?>
                            </a>
                        </th>
                        <th>
                            <a class="text-dark" href="?<?php echo $url_query_strings_sort; ?>&sort=app_log_type&order=<?php echo $disp; ?>">
                                Type <?php if ($sort == 'app_log_type') { echo $order_icon; } ?>
                            </a>
                        </th>
                        <th>
                            <a class="text-dark" href="?<?php echo $url_query_strings_sort; ?>&sort=app_log_category&order=<?php echo $disp; ?>">
                                Category <?php if ($sort == 'app_log_category') { echo $order_icon; } ?>
                            </a>
                        </th>
                        <th>
                            <a class="text-dark" href="?<?php echo $url_query_strings_sort; ?>&sort=app_log_details&order=<?php echo $disp; ?>">
                                Details <?php if ($sort == 'app_log_details') { echo $order_icon; } ?>
                            </a>
                        </th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php

                    while ($row = mysqli_fetch_array($sql)) {
                        $log_id = intval($row['app_log_id']);
                        $log_type = nullable_htmlentities($row['app_log_type']);
                        $log_category = nullable_htmlentities($row['app_log_category']);
                        $log_details = nullable_htmlentities($row['app_log_details']);
                        $log_created_at = nullable_htmlentities($row['app_log_created_at']);

                        ?>

                        <tr>
                            <td><?php echo $log_created_at; ?></td>
                            <td><?php echo $log_type; ?></td>
                            <td><?php echo $log_category; ?></td>
                            <td><?php echo $log_details; ?></td>
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
require_once "includes/footer.php";
