<?php

// Default Column Sortby Filter
$sort = "log_id";
$order = "DESC";

require_once "includes/inc_all_admin.php";

// User Filter
if (isset($_GET['user']) & !empty($_GET['user'])) {
    $user_query = 'AND (log_user_id = ' . intval($_GET['user']) . ')';
    $user_filter = intval($_GET['user']);
} else {
    // Default - any
    $user_query = '';
    $user_filter = '';
}

// Client Filter
if (isset($_GET['client']) & !empty($_GET['client'])) {
    $client_query = 'AND (log_client_id = ' . intval($_GET['client']) . ')';
    $client_filter = intval($_GET['client']);
} else {
    // Default - any
    $client_query = '';
    $client_filter = '';
}

// Log Type Filter
if (isset($_GET['type']) & !empty($_GET['type'])) {
    $log_type_query = "AND (log_type  = '" . sanitizeInput($_GET['type']) . "')";
    $type_filter = nullable_htmlentities($_GET['type']);
} else {
    // Default - any
    $log_type_query = '';
    $type_filter = '';
}

// Log Action Filter
if (isset($_GET['action']) & !empty($_GET['action'])) {
    $log_action_query = "AND (log_action  = '" . sanitizeInput($_GET['action']) . "')";
    $action_filter = nullable_htmlentities($_GET['action']);
} else {
    // Default - any
    $log_action_query = '';
    $action_filter = '';
}

$sql = mysqli_query(
    $mysqli,
    "SELECT SQL_CALC_FOUND_ROWS * FROM logs
    LEFT JOIN users ON log_user_id = user_id
    LEFT JOIN clients ON log_client_id = client_id
    WHERE (log_type LIKE '%$q%' OR log_action LIKE '%$q%' OR log_description LIKE '%$q%' OR log_ip LIKE '%$q%' OR log_user_agent LIKE '%$q%' OR user_name LIKE '%$q%' OR client_name LIKE '%$q%')
    AND DATE(log_created_at) BETWEEN '$dtf' AND '$dtt'
    $user_query
    $client_query
    $log_type_query
    $log_action_query
    ORDER BY $sort $order LIMIT $record_from, $record_to"
);

$num_rows = mysqli_fetch_row(mysqli_query($mysqli, "SELECT FOUND_ROWS()"));

?>

    <div class="card card-dark">
        <div class="card-header py-3">
            <h3 class="card-title"><i class="fas fa-fw fa-history mr-2"></i>Audit Logs</h3>
        </div>
        <div class="card-body">
            <form class="mb-4" autocomplete="off">
                <div class="row">
                    <div class="col-sm-4">
                        <div class="input-group mb-3 mb-md-0">
                            <input type="search" class="form-control" name="q" value="<?php if (isset($q)) { echo stripslashes(nullable_htmlentities($q)); } ?>" placeholder="Search audit logs">
                            <div class="input-group-append">
                                <button class="btn btn-secondary" type="button" data-toggle="collapse" data-target="#advancedFilter"><i class="fas fa-filter"></i></button>
                                <button class="btn btn-primary"><i class="fa fa-search"></i></button>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-sm-2">
                        <div class="input-group mb-3 mb-md-0">
                            <select class="form-control select2" name="client" onchange="this.form.submit()">
                                <option value="">- All Clients -</option>

                                <?php
                                $sql_clients_filter = mysqli_query($mysqli, "SELECT * FROM clients ORDER BY client_name ASC");
                                while ($row = mysqli_fetch_array($sql_clients_filter)) {
                                    $client_id = intval($row['client_id']);
                                    $client_name = nullable_htmlentities($row['client_name']);
                                ?>
                                    <option <?php if ($client_filter == $client_id) { echo "selected"; } ?> value="<?php echo $client_id; ?>"><?php echo $client_name; ?></option>
                                <?php
                                }
                                ?>

                            </select>
                        </div>
                    </div>

                    <div class="col-sm-2">
                        <div class="input-group mb-3 mb-md-0">
                            <select class="form-control select2" name="user" onchange="this.form.submit()">
                                <option value="">- All Users -</option>

                                <?php
                                $sql_users_filter = mysqli_query($mysqli, "SELECT * FROM users ORDER BY user_name ASC");
                                while ($row = mysqli_fetch_array($sql_users_filter)) {
                                    $user_id = intval($row['user_id']);
                                    $user_name = nullable_htmlentities($row['user_name']);
                                ?>
                                    <option <?php if ($user_filter == $user_id) { echo "selected"; } ?> value="<?php echo $user_id; ?>"><?php echo $user_name; ?></option>
                                <?php
                                }
                                ?>

                            </select>
                        </div>
                    </div>

                    <div class="col-sm-2">
                        <div class="input-group mb-3 mb-md-0">
                            <select class="form-control select2" name="type" onchange="this.form.submit()">
                                <option value="">- All Types -</option>

                                <?php
                                $sql_types_filter = mysqli_query($mysqli, "SELECT DISTINCT log_type FROM logs ORDER BY log_type ASC");
                                while ($row = mysqli_fetch_array($sql_types_filter)) {
                                    $log_type = nullable_htmlentities($row['log_type']);
                                ?>
                                    <option <?php if ($type_filter == $log_type) { echo "selected"; } ?>><?php echo $log_type; ?></option>
                                <?php
                                }
                                ?>

                            </select>
                        </div>
                    </div>

                    <div class="col-sm-2">
                        <div class="input-group mb-3 mb-md-0">
                            <select class="form-control select2" name="action" onchange="this.form.submit()">
                                <option value="">- All Actions -</option>

                                <?php
                                $sql_actions_filter = mysqli_query($mysqli, "SELECT DISTINCT log_action FROM logs ORDER BY log_action ASC");
                                while ($row = mysqli_fetch_array($sql_actions_filter)) {
                                    $log_action = nullable_htmlentities($row['log_action']);
                                ?>
                                    <option <?php if ($action_filter == $log_action) { echo "selected"; } ?>><?php echo $log_action; ?></option>
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
                    <thead class="text-dark <?php if ($num_rows[0] == 0) { echo "d-none"; } ?> text-nowrap">
                    <tr>
                        <th>
                            <a class="text-dark" href="?<?php echo $url_query_strings_sort; ?>&sort=log_created_at&order=<?php echo $disp; ?>">
                                Timestamp <?php if ($sort == 'log_created_at') { echo $order_icon; } ?>
                            </a>
                        </th>
                        <th>
                            <a class="text-dark" href="?<?php echo $url_query_strings_sort; ?>&sort=user_name&order=<?php echo $disp; ?>">
                                User <?php if ($sort == 'user_name') { echo $order_icon; } ?>
                            </a>
                        </th>
                        <?php if (empty($client)) { ?>
                            <th>
                                <a class="text-dark" href="?<?php echo $url_query_strings_sort; ?>&sort=client_name&order=<?php echo $disp; ?>">
                                    Client <?php if ($sort == 'client_name') { echo $order_icon; } ?>
                                </a>
                            </th>
                        <?php } ?>
                        <th>
                            <a class="text-dark" href="?<?php echo $url_query_strings_sort; ?>&sort=log_type&order=<?php echo $disp; ?>">
                                Type <?php if ($sort == 'log_type') { echo $order_icon; } ?>
                            </a>
                        </th>
                        <th>
                            <a class="text-dark" href="?<?php echo $url_query_strings_sort; ?>&sort=log_action&order=<?php echo $disp; ?>">
                                Action <?php if ($sort == 'log_action') { echo $order_icon; } ?>
                            </a>
                        </th>
                        <th>
                            <a class="text-dark" href="?<?php echo $url_query_strings_sort; ?>&sort=log_description&order=<?php echo $disp; ?>">
                                Description <?php if ($sort == 'log_description') { echo $order_icon; } ?>
                            </a>
                        </th>
                        <th>
                            <a class="text-dark" href="?<?php echo $url_query_strings_sort; ?>&sort=log_ip&order=<?php echo $disp; ?>">
                                IP Address <?php if ($sort == 'log_ip') { echo $order_icon; } ?>
                            </a>
                        </th>
                        <th>
                            <a class="text-dark" href="?<?php echo $url_query_strings_sort; ?>&sort=log_user_agent&order=<?php echo $disp; ?>">
                                User Agent <?php if ($sort == 'log_user_agent') { echo $order_icon; } ?>
                            </a>
                        </th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php

                    while ($row = mysqli_fetch_array($sql)) {
                        $log_id = intval($row['log_id']);
                        $log_type = nullable_htmlentities($row['log_type']);
                        $log_action = nullable_htmlentities($row['log_action']);
                        $log_description = nullable_htmlentities($row['log_description']);
                        $log_ip = nullable_htmlentities($row['log_ip']);
                        $log_user_agent = nullable_htmlentities($row['log_user_agent']);
                        $log_user_os = getOS($log_user_agent);
                        $log_user_browser = getWebBrowser($log_user_agent);
                        $log_created_at = nullable_htmlentities($row['log_created_at']);
                        $user_id = intval($row['user_id']);
                        $user_name = nullable_htmlentities($row['user_name']);
                        if (empty($user_name)) {
                            $user_name_display = "-";
                        } else {
                            $user_name_display = $user_name;
                        }
                        $client_name = nullable_htmlentities($row['client_name']);
                        $client_id = intval($row['client_id']);
                        if (empty($client_name)) {
                            $client_name_display = "-";
                        } else {
                            $client_name_display = "<a href='client_overview.php?client_id=$client_id'>$client_name</a>";
                        }
                        $log_entity_id = intval($row['log_entity_id']);

                        ?>

                        <tr>
                            <td><?php echo $log_created_at; ?></td>
                            <td><?php echo $user_name_display; ?></td>
                            <?php if(empty($client)) { ?>
                            <td><?php echo $client_name_display; ?></td>
                            <?php } ?>
                            <td><?php echo $log_type; ?></td>
                            <td><?php echo $log_action; ?></td>
                            <td><?php echo $log_description; ?></td>
                            <td><?php echo $log_ip; ?></td>
                            <td><?php echo "$log_user_os<div class='text-secondary'>$log_user_browser</div>"; ?></td>
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

