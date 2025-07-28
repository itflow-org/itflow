<?php

// Default Column Sortby Filter
$sort = "notification_timestamp";
$order = "DESC";

require_once "includes/inc_all.php";

// Dismissed Filter
if (isset($_GET['dismissed'])) {
    $dismissed_query = 'AND notification_dismissed_at IS NOT NULL';
    $dismissed_filter = 1;
} else {
    // Default - any
    $dismissed_query = 'AND notification_dismissed_at IS NULL';
    $dismissed_filter = 0;
}

$sql = mysqli_query(
    $mysqli,
    "SELECT SQL_CALC_FOUND_ROWS * FROM notifications
    LEFT JOIN clients ON notification_client_id = client_id
    WHERE (notification_type LIKE '%$q%' OR notification LIKE '%$q%')
    AND DATE(notification_timestamp) BETWEEN '$dtf' AND '$dtt'
    AND notification_user_id = $session_user_id
    $dismissed_query
    ORDER BY $sort $order
    LIMIT $record_from, $record_to
");

$num_rows = mysqli_fetch_row(mysqli_query($mysqli, "SELECT FOUND_ROWS()"));

?>

<div class="card card-dark">
    <div class="card-header py-2">
        <h3 class="card-title mt-2">
            <i class="fas fa-fw fa-bell mr-2"></i><?php if($dismissed_filter) { echo "Dismissed "; } ?>Notifications
        </h3>
        <div class="card-tools">
            <?php if($dismissed_filter) { ?>
            <a href="notifications.php" class="btn btn-primary"><i class="fas fa-fw fa-history mr-2"></i>Dismissed</a>
            <?php } else { ?>
            <a href="notifications.php?dismissed" class="btn btn-outline-secondary"><i class="fas fa-fw fa-history mr-2"></i>Dismissed</a>
            <?php } ?>
        </div>
    </div>
    <div class="card-body">
        <form class="mb-4" autocomplete="off">
            <?php if ($dismissed_filter) { ?>
                <input type="hidden" name="dismissed" value="">
            <?php } ?>
            <div class="row">
                <div class="col-sm-4">
                    <div class="input-group">
                        <input type="search" class="form-control" name="q" value="<?php if (isset($q)) { echo stripslashes(nullable_htmlentities($q)); } ?>" placeholder="Search <?php if($dismissed_filter) { echo "Dismissed "; } ?>Notifications">
                        <div class="input-group-append">
                            <button class="btn btn-primary text-strong"><i class="fa fa-search"></i></button>
                            <button class="btn btn-secondary" type="button" data-toggle="collapse" data-target="#advancedFilter"><i class="fas fa-filter"></i></button>
                        </div>
                    </div>
                </div>
                <div class="col-sm-8">
                    
                    
                </div>
            </div>
            <div class="collapse mt-3 <?php if (!empty($_GET['dtf'])) { echo "show"; } ?>" id="advancedFilter">
                <div class="row">
                    <div class="col-md-2">
                        <div class="form-group">
                            <label>Date From</label>
                            <input type="date" class="form-control" name="dtf" max="2999-12-31" value="<?php echo nullable_htmlentities($dtf); ?>">
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="form-group">
                            <label>Date To</label>
                            <input type="date" class="form-control" name="dtt" max="2999-12-31" value="<?php echo nullable_htmlentities($dtt); ?>">
                        </div>
                    </div>
                </div>
            </div>
        </form>
        <div class="table-responsive-sm">
            <table class="table table-hover">
                <thead class="<?php if ($num_rows[0] == 0) { echo "d-none"; } ?>">
                <tr>
                    <th>
                        <a class="text-dark" href="?<?php echo $url_query_strings_sort; ?>&sort=notification_timestamp&order=<?php echo $disp; ?>">
                            Timestamp <?php if ($sort == 'notification_timestamp') { echo $order_icon; } ?>
                        </a>
                    </th>
                    <th>
                        <a class="text-dark" href="?<?php echo $url_query_strings_sort; ?>&sort=notification_type&order=<?php echo $disp; ?>">
                            Type <?php if ($sort == 'notification_type') { echo $order_icon; } ?>
                        </a>
                    </th>
                    <th>
                        <a class="text-dark" href="?<?php echo $url_query_strings_sort; ?>&sort=notification&order=<?php echo $disp; ?>">
                            Notification <?php if ($sort == 'notification') { echo $order_icon; } ?>
                        </a>
                    </th>
                    <?php if($dismissed_filter) { ?>
                    <th>
                        <a class="text-dark" href="?<?php echo $url_query_strings_sort; ?>&sort=notification_dismissed_at&order=<?php echo $disp; ?>">
                            Dismissed At <?php if ($sort == 'notification_dismissed_at') { echo $order_icon; } ?>
                        </a>
                    </th>
                    <?php } ?>
                    <?php if(!$dismissed_filter) { ?>
                    <th class="text-center p-0">
                        <?php if (mysqli_num_rows($sql) > 0) { ?>
                        <a href="post.php?dismiss_all_notifications&csrf_token=<?php echo $_SESSION["csrf_token"]; ?>" 
                            class="btn btn-sm btn-dark mb-2" title="Dismiss All">
                            <i class="fas fa-fw fa-check-double"></i>
                        </a>
                        <?php } ?>
                    </th>
                    <?php } ?>
                </tr>
                </thead>
                <tbody>
                <?php

                while ($row = mysqli_fetch_array($sql)) {
                $notification_id = intval($row['notification_id']);
                $notification_timestamp = nullable_htmlentities($row['notification_timestamp']);
                $notification_type = nullable_htmlentities($row['notification_type']);
                $notification = nullable_htmlentities($row['notification']);
                $notification_dismissed_at = nullable_htmlentities($row['notification_dismissed_at']);
                $client_name = nullable_htmlentities($row['client_name']);
                $client_id = intval($row['client_id']);

                ?>
                <tr>
                    <td><?php echo $notification_timestamp; ?></td>
                    <td><?php echo $notification_type; ?></td>
                    <td><?php echo $notification; ?></td>
                    <?php if($dismissed_filter) { ?>
                    <td><?php echo $notification_dismissed_at; ?></td>
                    <?php } ?>
                    <?php if(!$dismissed_filter) { ?>
                    <td class="text-center"><a class="btn btn-secondary btn-sm" href="post.php?dismiss_notification=<?php echo $notification_id; ?>" title="Dismiss"><i class="fas fa-check"></i></a></td>
                    <?php } ?>
                </tr>
                
                <?php } ?>

                </tbody>
            </table>
        </div>
        <?php require_once "../includes/filter_footer.php"; ?>
    </div>
</div>

<?php

require_once "../includes/footer.php";
