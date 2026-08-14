<?php

// Default Column Sortby Filter
$sort = "app_log_id";
$order = "DESC";

require_once "includes/inc_all_admin.php";

// Log Type Filter
if (isset($_GET['type']) & !empty($_GET['type'])) {
    $log_type_query = "AND (app_log_type  = '" . escapeSql($_GET['type']) . "')";
    $type_filter = escapeHtml($_GET['type']);
} else {
    // Default - any
    $log_type_query = '';
    $type_filter = '';
}

// Log Category Filter
if (isset($_GET['category']) & !empty($_GET['catergory'])) {
    $log_category_query = "AND (app_log_category  = '" . escapeSql($_GET['category']) . "')";
    $category_filter = escapeHtml($_GET['category']);
} else {
    // Default - any
    $log_category_query = '';
    $category_filter = '';
}

$sql = mysqli_query(
    $mysqli,
    "SELECT SQL_CALC_FOUND_ROWS app_log_category, app_log_created_at, app_log_details, app_log_id, app_log_type FROM app_logs
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
            <h3 class="card-title"><i class="fas fa-fw fa-history me-2"></i>App Logs</h3>
        </div>
        <div class="card-body">
            <form autocomplete="off">
                <div class="row">
                    <div class="col-sm-4">
                        <div class="mb-3">
                            <div class="input-group">
                                <input type="search" class="form-control" name="q" value="<?php if (isset($q)) { echo stripslashes(escapeHtml($q)); } ?>" placeholder="Search app logs">
                                    <button class="btn btn-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#advancedFilter"><i class="fas fa-filter"></i></button>
                                    <button class="btn btn-primary"><i class="fa fa-search"></i></button>
                            </div>
                        </div>
                    </div>

                    <div class="col-sm-2">
                        <div class="mb-3">
                            <select class="form-control select2" name="type" onchange="this.form.submit()">
                                <option value="">- All Types -</option>

                                <?php
                                $sql_types_filter = mysqli_query($mysqli, "SELECT DISTINCT app_log_type FROM app_logs ORDER BY app_log_type ASC");
                                while ($row = mysqli_fetch_assoc($sql_types_filter)) {
                                    $log_type = escapeHtml($row['app_log_type']);
                                ?>
                                    <option <?php if ($type_filter == $log_type) { echo "selected"; } ?>><?= $log_type ?></option>
                                <?php
                                }
                                ?>

                            </select>
                        </div>
                    </div>

                    <div class="col-sm-2">
                        <div class="mb-3">
                            <select class="form-control select2" name="category" onchange="this.form.submit()">
                                <option value="">- All Categories -</option>

                                <?php
                                $sql_categories_filter = mysqli_query($mysqli, "SELECT DISTINCT app_log_category FROM app_logs ORDER BY app_log_category ASC");
                                while ($row = mysqli_fetch_assoc($sql_categories_filter)) {
                                    $log_category = escapeHtml($row['app_log_category']);
                                ?>
                                    <option <?php if ($category_filter == $log_category) { echo "selected"; } ?>><?= $log_category ?></option>
                                <?php
                                }
                                ?>

                            </select>
                        </div>
                    </div>
                </div>
                <div class="collapse mt-3 <?php if (isset($_GET['dtf']) && $_GET['dtf'] !== '1970-01-01') { echo "show"; } ?>" id="advancedFilter">
                    <div class="row">
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label>Date range</label>
                                <input type="text" id="dateFilter" class="form-control" autocomplete="off">
                                <input type="hidden" name="canned_date" id="canned_date" value="<?= escapeHtml($_GET['canned_date']) ?? '' ?>">
                                <input type="hidden" name="dtf" id="dtf" value="<?= escapeHtml($dtf ?? '') ?>">
                                <input type="hidden" name="dtt" id="dtt" value="<?= escapeHtml($dtt ?? '') ?>">
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
                            <a class="text-dark" href="?<?= $url_query_strings_sort ?>&sort=app_log_created_at&order=<?= $disp ?>">
                                Timestamp <?php if ($sort == 'app_log_created_at') { echo $order_icon; } ?>
                            </a>
                        </th>
                        <th>
                            <a class="text-dark" href="?<?= $url_query_strings_sort ?>&sort=app_log_type&order=<?= $disp ?>">
                                Type <?php if ($sort == 'app_log_type') { echo $order_icon; } ?>
                            </a>
                        </th>
                        <th>
                            <a class="text-dark" href="?<?= $url_query_strings_sort ?>&sort=app_log_category&order=<?= $disp ?>">
                                Category <?php if ($sort == 'app_log_category') { echo $order_icon; } ?>
                            </a>
                        </th>
                        <th>
                            <a class="text-dark" href="?<?= $url_query_strings_sort ?>&sort=app_log_details&order=<?= $disp ?>">
                                Details <?php if ($sort == 'app_log_details') { echo $order_icon; } ?>
                            </a>
                        </th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php

                    while ($row = mysqli_fetch_assoc($sql)) {
                        $log_id = intval($row['app_log_id']);
                        $log_type = escapeHtml($row['app_log_type']);
                        $log_category = escapeHtml($row['app_log_category']);
                        $log_details = escapeHtml($row['app_log_details']);
                        $log_created_at = escapeHtml($row['app_log_created_at']);

                        ?>

                        <tr>
                            <td class="font-monospace"><?= $log_created_at ?></td>
                            <td><?= $log_type ?></td>
                            <td><?= $log_category ?></td>
                            <td><?= $log_details ?></td>
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
require_once "../includes/footer.php";
