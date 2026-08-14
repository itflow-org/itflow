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
    $log_type_query = "AND (log_type  = '" . escapeSql($_GET['type']) . "')";
    $type_filter = escapeHtml($_GET['type']);
} else {
    // Default - any
    $log_type_query = '';
    $type_filter = '';
}

// Log Action Filter
if (isset($_GET['action']) & !empty($_GET['action'])) {
    $log_action_query = "AND (log_action  = '" . escapeSql($_GET['action']) . "')";
    $action_filter = escapeHtml($_GET['action']);
} else {
    // Default - any
    $log_action_query = '';
    $action_filter = '';
}

$sql = mysqli_query(
    $mysqli,
    "SELECT SQL_CALC_FOUND_ROWS client_id, client_name, log_action, log_created_at, log_description, log_entity_id, log_id,
        log_ip, log_type, log_user_agent, user_id, user_name FROM logs
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
            <h3 class="card-title"><i class="fas fa-fw fa-history me-2"></i>Audit Logs</h3>
        </div>
        <div class="card-body">
            <form class="mb-4" autocomplete="off">
                <div class="row">
                    <div class="col-sm-4">
                        <div class="input-group mb-3 mb-md-0">
                            <input type="search" class="form-control" name="q" value="<?php if (isset($q)) { echo stripslashes(escapeHtml($q)); } ?>" placeholder="Search audit logs">
                                <button class="btn btn-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#advancedFilter"><i class="fas fa-filter"></i></button>
                                <button class="btn btn-primary"><i class="fa fa-search"></i></button>
                        </div>
                    </div>

                    <div class="col-sm-2">
                        <div class="input-group mb-3 mb-md-0">
                            <select class="form-control select2" name="client" onchange="this.form.submit()">
                                <option value="">- All Clients -</option>

                                <?php
                                $sql_clients_filter = mysqli_query($mysqli, "SELECT client_id, client_name FROM clients ORDER BY client_name ASC");
                                while ($row = mysqli_fetch_assoc($sql_clients_filter)) {
                                    $client_id = intval($row['client_id']);
                                    $client_name = escapeHtml($row['client_name']);
                                ?>
                                    <option <?php if ($client_filter == $client_id) { echo "selected"; } ?> value="<?= $client_id ?>"><?= $client_name ?></option>
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
                                $sql_users_filter = mysqli_query($mysqli, "SELECT user_id, user_name FROM users ORDER BY user_name ASC");
                                while ($row = mysqli_fetch_assoc($sql_users_filter)) {
                                    $user_id = intval($row['user_id']);
                                    $user_name = escapeHtml($row['user_name']);
                                ?>
                                    <option <?php if ($user_filter == $user_id) { echo "selected"; } ?> value="<?= $user_id ?>"><?= $user_name ?></option>
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
                                while ($row = mysqli_fetch_assoc($sql_types_filter)) {
                                    $log_type = escapeHtml($row['log_type']);
                                ?>
                                    <option <?php if ($type_filter == $log_type) { echo "selected"; } ?>><?= $log_type ?></option>
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
                                while ($row = mysqli_fetch_assoc($sql_actions_filter)) {
                                    $log_action = escapeHtml($row['log_action']);
                                ?>
                                    <option <?php if ($action_filter == $log_action) { echo "selected"; } ?>><?= $log_action ?></option>
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
                    <thead class="text-dark <?php if ($num_rows[0] == 0) { echo "d-none"; } ?> text-nowrap">
                    <tr>
                        <th>
                            <a class="text-dark" href="?<?= $url_query_strings_sort ?>&sort=log_created_at&order=<?= $disp ?>">
                                Timestamp <?php if ($sort == 'log_created_at') { echo $order_icon; } ?>
                            </a>
                        </th>
                        <th>
                            <a class="text-dark" href="?<?= $url_query_strings_sort ?>&sort=user_name&order=<?= $disp ?>">
                                User <?php if ($sort == 'user_name') { echo $order_icon; } ?>
                            </a>
                        </th>
                        <?php if (empty($client)) { ?>
                            <th>
                                <a class="text-dark" href="?<?= $url_query_strings_sort ?>&sort=client_name&order=<?= $disp ?>">
                                    Client <?php if ($sort == 'client_name') { echo $order_icon; } ?>
                                </a>
                            </th>
                        <?php } ?>
                        <th>
                            <a class="text-dark" href="?<?= $url_query_strings_sort ?>&sort=log_type&order=<?= $disp ?>">
                                Type <?php if ($sort == 'log_type') { echo $order_icon; } ?>
                            </a>
                        </th>
                        <th>
                            <a class="text-dark" href="?<?= $url_query_strings_sort ?>&sort=log_action&order=<?= $disp ?>">
                                Action <?php if ($sort == 'log_action') { echo $order_icon; } ?>
                            </a>
                        </th>
                        <th>
                            <a class="text-dark" href="?<?= $url_query_strings_sort ?>&sort=log_description&order=<?= $disp ?>">
                                Description <?php if ($sort == 'log_description') { echo $order_icon; } ?>
                            </a>
                        </th>
                        <th>
                            <a class="text-dark" href="?<?= $url_query_strings_sort ?>&sort=log_ip&order=<?= $disp ?>">
                                IP Address <?php if ($sort == 'log_ip') { echo $order_icon; } ?>
                            </a>
                        </th>
                        <th>
                            <a class="text-dark" href="?<?= $url_query_strings_sort ?>&sort=log_user_agent&order=<?= $disp ?>">
                                User Agent <?php if ($sort == 'log_user_agent') { echo $order_icon; } ?>
                            </a>
                        </th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php

                    while ($row = mysqli_fetch_assoc($sql)) {
                        $log_id = intval($row['log_id']);
                        $log_type = escapeHtml($row['log_type']);
                        $log_action = escapeHtml($row['log_action']);
                        $log_description = escapeHtml($row['log_description']);
                        $log_ip = escapeHtml($row['log_ip']);
                        $log_user_agent = escapeHtml($row['log_user_agent']);
                        $log_user_os = getOS($log_user_agent);
                        $log_user_browser = getWebBrowser($log_user_agent);
                        $log_created_at = escapeHtml($row['log_created_at']);
                        $user_id = intval($row['user_id']);
                        $user_name = escapeHtml($row['user_name']);
                        if (empty($user_name)) {
                            $user_name_display = "-";
                        } else {
                            $user_name_display = $user_name;
                        }
                        $client_name = escapeHtml($row['client_name']);
                        $client_id = intval($row['client_id']);
                        if (empty($client_name)) {
                            $client_name_display = "-";
                        } else {
                            $client_name_display = "<a href='../agent/client_overview.php?client_id=$client_id'>$client_name</a>";
                        }
                        $log_entity_id = intval($row['log_entity_id']);

                        ?>

                        <tr>
                            <td class="font-monospace"><?= $log_created_at ?></td>
                            <td><?= $user_name_display ?></td>
                            <?php if(empty($client)) { ?>
                            <td><?= $client_name_display ?></td>
                            <?php } ?>
                            <td><?= $log_type ?></td>
                            <td><?= $log_action ?></td>
                            <td><?= $log_description ?></td>
                            <td class="font-monospace"><?= $log_ip ?></td>
                            <td><?= "$log_user_os<div class='text-secondary'>$log_user_browser</div>" ?></td>
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
