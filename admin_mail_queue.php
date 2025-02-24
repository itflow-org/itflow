<?php

// Default Column Sortby Filter
$sort = "email_id";
$order = "DESC";

require_once "includes/inc_all_admin.php";

//Rebuild URL
$url_query_strings_sort = http_build_query($get_copy);

$sql = mysqli_query(
    $mysqli,
    "SELECT SQL_CALC_FOUND_ROWS * FROM email_queue
    WHERE (email_id LIKE '%$q%' OR email_from LIKE '%$q%' OR email_from_name LIKE '%$q%' OR email_recipient LIKE '%$q%' OR email_recipient_name LIKE '%$q%' OR email_subject LIKE '%$q%')
    AND DATE(email_queued_at) BETWEEN '$dtf' AND '$dtt'
    ORDER BY $sort $order LIMIT $record_from, $record_to"
);

$num_rows = mysqli_fetch_row(mysqli_query($mysqli, "SELECT FOUND_ROWS()"));

?>

    <div class="card card-dark">
        <div class="card-header py-3">
            <h3 class="card-title"><i class="fas fa-fw fa-mail-bulk mr-2"></i>Email Queue</h3>
        </div>
        <div class="card-body">
            <form class="mb-4" autocomplete="off">
                <div class="row">
                    <div class="col-sm-4">
                        <div class="input-group">
                            <input type="search" class="form-control" name="q" value="<?php if (isset($q)) { echo stripslashes(nullable_htmlentities($q)); } ?>" placeholder="Search mail queue">
                            <div class="input-group-append">
                                <button class="btn btn-secondary" type="button" data-toggle="collapse" data-target="#advancedFilter"><i class="fas fa-filter"></i></button>
                                <button class="btn btn-primary"><i class="fa fa-search"></i></button>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-8">
                        <div class="dropdown float-right" id="bulkActionButton" hidden>
                            <button class="btn btn-secondary dropdown-toggle" type="button" data-toggle="dropdown">
                                <i class="fas fa-fw fa-layer-group mr-2"></i>Bulk Action (<span id="selectedCount">0</span>)
                            </button>
                            <div class="dropdown-menu">
                                <button class="dropdown-item"
                                        type="submit" form="bulkActions" name="bulk_cancel_emails">
                                    <i class="fas fa-fw fa-ban mr-2"></i>Cancel
                                </button>
                                <div class="dropdown-divider"></div>
                                <button class="dropdown-item text-danger text-bold"
                                        type="submit" form="bulkActions" name="bulk_delete_emails">
                                    <i class="fas fa-fw fa-trash mr-2"></i>Delete
                                </button>
                            </div>
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
            <form id="bulkActions" action="post.php" method="post">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?>">

                <div class="table-responsive-sm">
                    <table class="table table-sm table-striped table-borderless table-hover">
                        <thead class="text-dark <?php if ($num_rows[0] == 0) { echo "d-none"; } ?>">
                        <tr>
                            <td class="bg-light pr-0">
                                <div class="form-check">
                                    <input class="form-check-input" id="selectAllCheckbox" type="checkbox" onclick="checkAll(this)">
                                </div>
                            </td>
                            <th>
                                <a class="text-dark" href="?<?php echo $url_query_strings_sort; ?>&sort=email_queued_at&order=<?php echo $disp; ?>">
                                    Queued <?php if ($sort == 'email_queued_at') { echo $order_icon; } ?>
                                </a>
                            </th>
                            <th>
                                <a class="text-dark" href="?<?php echo $url_query_strings_sort; ?>&sort=email_from&order=<?php echo $disp; ?>">
                                    From <?php if ($sort == 'email_from') { echo $order_icon; } ?>
                                </a>
                            </th>
                            <th>
                                <a class="text-dark" href="?<?php echo $url_query_strings_sort; ?>&sort=email_recipient&order=<?php echo $disp; ?>">
                                    To <?php if ($sort == 'email_recipient') { echo $order_icon; } ?>
                                </a>
                            </th>
                            <th>
                                <a class="text-dark" href="?<?php echo $url_query_strings_sort; ?>&sort=email_subject&order=<?php echo $disp; ?>">
                                    Subject <?php if ($sort == 'email_subject') { echo $order_icon; } ?>
                                </a>
                            </th>
                            <th>
                                <a class="text-dark" href="?<?php echo $url_query_strings_sort; ?>&sort=email_status&order=<?php echo $disp; ?>">
                                    Status <?php if ($sort == 'email_status') { echo $order_icon; } ?>
                                </a>
                            </th>
                            <th>
                                <a class="text-dark" href="?<?php echo $url_query_strings_sort; ?>&sort=email_attempts&order=<?php echo $disp; ?>">
                                    Attempts <?php if ($sort == 'email_attempts') { echo $order_icon; } ?>
                                </a>
                            </th>
                            <th class="text-center">Action</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php

                        while ($row = mysqli_fetch_array($sql)) {
                            $email_id = intval($row['email_id']);
                            $email_from = nullable_htmlentities($row['email_from']);
                            $email_from_name = nullable_htmlentities($row['email_from_name']);
                            $email_recipient = nullable_htmlentities($row['email_recipient']);
                            $email_recipient_name = nullable_htmlentities($row['email_recipient_name']);
                            $email_subject = nullable_htmlentities($row['email_subject']);
                            $email_attempts = intval($row['email_attempts']);
                            $email_queued_at = nullable_htmlentities($row['email_queued_at']);
                            $email_failed_at = nullable_htmlentities($row['email_failed_at']);
                            $email_sent_at = nullable_htmlentities($row['email_sent_at']);
                            $email_status = intval($row['email_status']);
                            if ($email_status == 0) {
                                $email_status_display = "<div class='text-primary'>Queued</div>";
                            } elseif($email_status == 1) {
                                $email_status_display = "<div class='text-warning'>Sending</div>";
                            } elseif($email_status == 2) {
                                $email_status_display = "<div class='text-danger'>Failed</div><small class='text-secondary'>$email_failed_at</small>";
                            } else {
                                $email_status_display = "<div class='text-success'>Sent</div><small class='text-secondary'>$email_sent_at</small>";
                            }

                            ?>

                            <tr>
                                <td class="pr-0 bg-light">
                                    <?php if ($email_status !== 3) { ?>
                                    <div class="form-check">
                                        <input class="form-check-input bulk-select" type="checkbox" name="email_ids[]" value="<?php echo $email_id ?>">
                                    </div>
                                    <?php } ?>
                                </td>
                                <td><?php echo $email_queued_at; ?></td>
                                <td><?php echo "$email_from<br><small class='text-secondary'>$email_from_name</small>"?></td>
                                <td><?php echo "$email_recipient<br><small class='text-secondary'>$email_recipient_name</small>"?></td>
                                <td><?php echo $email_subject; ?></td>
                                <td><?php echo $email_status_display; ?></td>
                                <td><?php echo $email_attempts; ?></td>
                                <td class="text-center">
                                    <a class="btn btn-sm btn-secondary" href="#"
                                        data-toggle = "ajax-modal"
                                        data-modal-size = "lg"
                                        data-ajax-url = "ajax/ajax_admin_mail_queue_message_view.php"
                                        data-ajax-id = "<?php echo $email_id; ?>"
                                        >
                                        <i class="fas fa-fw fa-eye"></i>
                                    </a>

                                    <!-- Show force resend if all retries have failed -->
                                    <?php if ($email_status == 2 && $email_attempts > 3) { ?>
                                        <a class="btn btn-sm btn-success" href="post.php?send_failed_mail=<?php echo $email_id; ?>"><i class="fas fa-fw fa-paper-plane"></i></a>
                                    <?php } ?>

                                    <!-- Allow cancelling a message if it hasn't yet been picked up (e.g. stuck/bugged) -->
                                    <?php if ($email_status !== 3) { ?>
                                        <a class="btn btn-sm btn-danger confirm-link" href="post.php?cancel_mail=<?php echo $email_id; ?>"><i class="fas fa-fw fa-trash"></i></a>
                                    <?php } ?>

                                </td>
                            </tr>

                            <?php
                        }
                        ?>

                        </tbody>
                    </table>
                </div>
            </form>

            <?php require_once "includes/filter_footer.php"; ?>
        </div>
    </div>

<script src="js/bulk_actions.js"></script>

<?php
require_once "includes/footer.php";
