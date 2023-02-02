<?php require_once("inc_all.php");

$sql = mysqli_query($mysqli, "SELECT * FROM notifications LEFT JOIN clients ON notification_client_id = client_id WHERE notification_dismissed_at IS NULL AND (notification_user_id = $session_user_id OR notification_user_id = 0) AND notifications.company_id = $session_company_id ORDER BY notification_id DESC");

?>

    <div class="card card-dark">
        <div class="card-header py-2">
            <h3 class="card-title mt-2"><i class="fa fa-fw fa-bell"></i> Notifications</h3>
            <div class="card-tools">

                <?php if (mysqli_num_rows($sql) > 0) { ?><a href="post.php?dismiss_all_notifications" class="btn btn-primary"><i class="fa fa-check"></i> Dismiss All</a><?php } ?>
                <a href="notifications_dismissed.php" class="btn btn-secondary"><i class="fa fa-history"></i> Dismissed</a>
            </div>
        </div>
        <div class="card-body">
            <?php if (mysqli_num_rows($sql) > 0) { ?>

            <div class="table-responsive">
                <table class="table table-striped table-borderless table-hover">
                    <thead>
                    <tr>
                        <th>Timestamp</th>
                        <th>Type</th>
                        <th>Notification</th>
                        <th>Client</th>
                        <th class="text-center">Dismiss</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php

                    while ($row = mysqli_fetch_array($sql)) {
                        $notification_id = $row['notification_id'];
                        $notification_type = htmlentities($row['notification_type']);
                        $notification = htmlentities($row['notification']);
                        $notification_timestamp = $row['notification_timestamp'];
                        $client_name = htmlentities($row['client_name']);
                        $client_id = $row['client_id'];
                        if (empty($client_name)) {
                            $client_name_display = "-";
                        } else {
                            $client_name_display = "<a href='client_overview.php?client_id=$client_id'>$client_name</a>";
                        }

                        ?>
                        <tr class="row-danger">
                            <td><?php echo $notification_timestamp; ?></td>
                            <td><?php echo $notification_type; ?></td>
                            <td><?php echo $notification; ?></td>
                            <td><?php echo $client_name_display; ?></td>
                            <td class="text-center"><a class="btn btn-info btn-sm" href="post.php?dismiss_notification=<?php echo $notification_id; ?>"><i class="fa fa-check"></a></td>
                        </tr>

                        <?php
                    }
                    ?>

                    </tbody>
                </table>
            </div>
        </div>

        <?php } else { ?>
            <div class="my-5" style="text-align: center">
                <i class='far fa-fw fa-6x fa-bell-slash text-secondary'></i><h3 class='text-secondary mt-3'>No Notifications</h3>
            </div>
        <?php } ?>

    </div>

<?php require_once("footer.php");
