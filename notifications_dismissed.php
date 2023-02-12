<?php
require_once("inc_all.php");

//Column Filter
if (!empty($_GET['sb'])) {
    $sb = strip_tags(mysqli_real_escape_string($mysqli, $_GET['sb']));
} else {
    $sb = "notification_timestamp";
}

// Reverse default sort
if (!isset($_GET['o'])) {
    $o = "DESC";
    $disp = "ASC";
}

//Date From and Date To Filter
if (!empty($_GET['dtf'])) {
    $dtf = strip_tags(mysqli_real_escape_string($mysqli, $_GET['dtf']));
    $dtt = strip_tags(mysqli_real_escape_string($mysqli, $_GET['dtt']));
} else {
    $dtf = "0000-00-00";
    $dtt = "9999-00-00";
}

//Rebuild URL

$url_query_strings_sb = http_build_query(array_merge($_GET, array('sb' => $sb, 'o' => $o)));

$sql = mysqli_query(
    $mysqli,
    "SELECT SQL_CALC_FOUND_ROWS * FROM notifications 
    LEFT JOIN users ON notification_dismissed_by = user_id 
    LEFT JOIN clients ON notification_client_id = client_id
    WHERE (notification_type LIKE '%$q%' OR notification LIKE '%$q%' OR user_name LIKE '%$q%' OR client_name LIKE '%$q%')
    AND DATE(notification_timestamp) BETWEEN '$dtf' AND '$dtt'
    AND (notification_user_id = $session_user_id OR notification_user_id = 0)
    AND notifications.company_id = $session_company_id
    AND notification_dismissed_at IS NOT NULL
    ORDER BY $sb $o
    LIMIT $record_from, $record_to
");

$num_rows = mysqli_fetch_row(mysqli_query($mysqli, "SELECT FOUND_ROWS()"));

?>

    <div class="card card-dark">
        <div class="card-header py-3">
            <h3 class="card-title"><i class="fa fa-fw fa-bell"></i> Dismissed Notications</h3>
        </div>
        <div class="card-body">
            <form class="mb-4" autocomplete="off">
                <div class="row">
                    <div class="col-sm-4">
                        <div class="input-group">
                            <input type="search" class="form-control" name="q" value="<?php if (isset($q)) { echo strip_tags(htmlentities($q)); } ?>" placeholder="Search Dismissed Notifications">
                            <div class="input-group-append">
                                <button class="btn btn-primary"><i class="fa fa-search"></i></button>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-8">
                        <button class="btn btn-primary float-right" type="button" data-toggle="collapse" data-target="#advancedFilter"><i class="fas fa-filter"></i></button>
                    </div>
                </div>
                <div class="collapse mt-3 <?php if (!empty($_GET['dtf'])) { echo "show"; } ?>" id="advancedFilter">
                    <div class="row">
                        <div class="col-md-2">
                            <div class="form-group">
                                <label>Date From</label>
                                <input type="date" class="form-control" name="dtf" max="2999-12-31" value="<?php echo htmlentities($dtf); ?>">
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-group">
                                <label>Date To</label>
                                <input type="date" class="form-control" name="dtt" max="2999-12-31" value="<?php echo htmlentities($dtt); ?>">
                            </div>
                        </div>
                    </div>
                </div>
            </form>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead class="<?php if ($num_rows[0] == 0) { echo "d-none"; } ?>">
                    <tr>
                        <th><a class="text-dark" href="?<?php echo $url_query_strings_sb; ?>&sb=notification_timestamp&o=<?php echo $disp; ?>">Timestamp <i class="fa fa-sort-numeric<?php if ($disp=='ASC') { echo "-up"; } else { echo "-down"; }?>"></i></a></th>
                        <th><a class="text-dark" href="?<?php echo $url_query_strings_sb; ?>&sb=notification_type&o=<?php echo $disp; ?>">Type <i class="fa fa-sort-alpha<?php if ($disp=='ASC') { echo "-up"; } else { echo "-down"; }?>"></i></a></th>
                        <th><a class="text-dark" href="?<?php echo $url_query_strings_sb; ?>&sb=notification&o=<?php echo $disp; ?>">Notification <i class="fa fa-sort-alpha<?php if ($disp=='ASC') { echo "-up"; } else { echo "-down"; }?>"></i></a></th>
                        <th><a class="text-dark" href="?<?php echo $url_query_strings_sb; ?>&sb=client_name&o=<?php echo $disp; ?>">Client <i class="fa fa-sort-numeric<?php if ($disp=='ASC') { echo "-up"; } else { echo "-down"; }?>"></i></a></th>
                        <th><a class="text-dark" href="?<?php echo $url_query_strings_sb; ?>&sb=notification_dismissed_at&o=<?php echo $disp; ?>">Dismissed At <i class="fa fa-sort-numeric<?php if ($disp=='ASC') { echo "-up"; } else { echo "-down"; }?>"></i></a></th>
                        <th><a class="text-dark" href="?<?php echo $url_query_strings_sb; ?>&sb=user_name&o=<?php echo $disp; ?>">Dismissed By <i class="fa fa-sort-numeric<?php if ($disp=='ASC') { echo "-up"; } else { echo "-down"; }?>"></i></a></th>

                    </tr>
                    </thead>
                    <tbody>
                    <?php

                    while ($row = mysqli_fetch_array($sql)) {
                    $notification_id = $row['notification_id'];
                    $notification_timestamp = $row['notification_timestamp'];
                    $notification_type = htmlentities($row['notification_type']);
                    $notification = htmlentities($row['notification']);
                    $notification_dismissed_at = $row['notification_dismissed_at'];
                    $user_name = htmlentities($row['user_name']);
                    $client_name = htmlentities($row['client_name']);
                    $client_id = $row['client_id'];
                    if (empty($client_name)) {
                        $client_name_display = "-";
                    } else {
                        $client_name_display = "<a href='client_overview.php?client_id=$client_id'>$client_name</a>";
                    }

                    ?>
                    <tr>
                        <td><?php echo $notification_timestamp; ?></td>
                        <td><?php echo $notification_type; ?></td>
                        <td><?php echo $notification; ?></td>
                        <td><?php echo $client_name_display; ?></td>
                        <td><?php echo $notification_dismissed_at; ?></td>
                        <td><?php echo $user_name; ?></td>

                        <?php } ?>


                    </tbody>
                </table>
            </div>
            <?php require_once("pagination.php"); ?>
        </div>
    </div>

<?php
require_once("footer.php");
