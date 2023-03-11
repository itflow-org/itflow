<?php

// Default Column Sortby Filter
$sb = "log_id";
$o = "DESC";

require_once("inc_all_client.php");

//Rebuild URL
$url_query_strings_sb = http_build_query(array_merge($_GET, array('sb' => $sb, 'o' => $o)));

$sql = mysqli_query(
    $mysqli,
    "SELECT SQL_CALC_FOUND_ROWS * FROM logs
    LEFT JOIN users ON log_user_id = user_id
    WHERE (log_type LIKE '%$q%' OR log_action LIKE '%$q%' OR log_description LIKE '%$q%' OR log_ip LIKE '%$q%' OR log_user_agent LIKE '%$q%' OR user_name LIKE '%$q%')
    AND log_client_id = $client_id
    ORDER BY $sb $o LIMIT $record_from, $record_to"
);

$num_rows = mysqli_fetch_row(mysqli_query($mysqli, "SELECT FOUND_ROWS()"));

?>

<div class="card card-dark">
    <div class="card-header py-3">
        <h3 class="card-title"><i class="fa fa-fw fa-eye mr-2"></i>Audit Logs</h3>
    </div>

    <div class="card-body">
        <form autocomplete="off">
            <input type="hidden" name="client_id" value="<?php echo $client_id; ?>">
            <div class="row">

                <div class="col-md-4">
                    <div class="input-group mb-3 mb-md-0">
                        <input type="search" class="form-control" name="q" value="<?php if (isset($q)) { echo stripslashes(htmlentities($q)); } ?>" placeholder="Search Logs">
                        <div class="input-group-append">
                            <button class="btn btn-dark"><i class="fa fa-search"></i></button>
                        </div>
                    </div>
                </div>

                <div class="col-md-8">
                </div>

            </div>
        </form>
        <hr>
        <div class="table-responsive-sm border">
            <table class="table table-hover">
                <thead class="text-dark <?php if ($num_rows[0] == 0) { echo "d-none"; } ?>">
                <tr>
                    <th><a class="text-dark" href="?<?php echo $url_query_strings_sb; ?>&sb=log_created_at&o=<?php echo $disp; ?>">Timestamp</a></th>
                    <th><a class="text-dark" href="?<?php echo $url_query_strings_sb; ?>&sb=user_name&o=<?php echo $disp; ?>">User</a></th>
                    <th><a class="text-dark" href="?<?php echo $url_query_strings_sb; ?>&sb=log_type&o=<?php echo $disp; ?>">Type</a></th>
                    <th><a class="text-dark" href="?<?php echo $url_query_strings_sb; ?>&sb=log_action&o=<?php echo $disp; ?>">Action</a></th>
                    <th><a class="text-dark" href="?<?php echo $url_query_strings_sb; ?>&sb=log_description&o=<?php echo $disp; ?>">Description</a></th>
                    <th><a class="text-dark" href="?<?php echo $url_query_strings_sb; ?>&sb=log_ip&o=<?php echo $disp; ?>">IP Address</a></th>
                    <th><a class="text-dark" href="?<?php echo $url_query_strings_sb; ?>&sb=log_user_agent&o=<?php echo $disp; ?>">User Agent</a></th>
                    <th><a class="text-dark" href="?<?php echo $url_query_strings_sb; ?>&sb=log_entity_id&o=<?php echo $disp; ?>">Entity ID</a></th>
                </tr>
                </thead>
                <tbody>
                <?php

                while ($row = mysqli_fetch_array($sql)) {
                    $log_id = intval($row['log_id']);
                    $log_type = htmlentities($row['log_type']);
                    $log_action = htmlentities($row['log_action']);
                    $log_description = htmlentities($row['log_description']);
                    $log_ip = htmlentities($row['log_ip']);
                    $log_user_agent = htmlentities($row['log_user_agent']);
                    $log_user_os = getOS($log_user_agent);
                    $log_user_browser = getWebBrowser($log_user_agent);
                    $log_created_at = htmlentities($row['log_created_at']);
                    $user_id = intval($row['user_id']);
                    $user_name = htmlentities($row['user_name']);
                    if (empty($user_name)) {
                        $user_name_display = "-";
                    } else {
                        $user_name_display = $user_name;
                    }
                    $log_entity_id = intval($row['log_entity_id']);

                    ?>

                    <tr>
                        <td><?php echo $log_created_at; ?></td>
                        <td><?php echo $user_name_display; ?></td>
                        <td><?php echo $log_type; ?></td>
                        <td><?php echo $log_action; ?></td>
                        <td><?php echo $log_description; ?></td>
                        <td><?php echo $log_ip; ?></td>
                        <td><?php echo "$log_user_os<br>$log_user_browser"; ?></td>
                        <td><?php echo $log_entity_id; ?></td>
                    </tr>

                    <?php
                }
                ?>

                </tbody>
            </table>
        </div>
        <?php require_once("pagination.php"); ?>
    </div>
</div>

<?php
require_once("footer.php");
