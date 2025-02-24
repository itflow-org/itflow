<?php

// Default Column Sortby/Order Filter
$sort = "recurring_next_date";
$order = "ASC";

// If client_id is in URI then show client Side Bar and client header
if (isset($_GET['client_id'])) {
    require_once "includes/inc_all_client.php";
    $client_query = "AND recurring_client_id = $client_id";
    $client_url = "client_id=$client_id&";
} else {
    require_once "includes/inc_all.php";
    $client_query = '';
    $client_url = '';
}

// Perms
enforceUserPermission('module_sales');

//Rebuild URL
$url_query_strings_sort = http_build_query($get_copy);

$sql = mysqli_query(
    $mysqli,
    "SELECT SQL_CALC_FOUND_ROWS * FROM recurring
    LEFT JOIN clients ON recurring_client_id = client_id
    LEFT JOIN categories ON recurring_category_id = category_id
    LEFT JOIN recurring_payments ON recurring_payment_recurring_invoice_id = recurring_id
    WHERE (CONCAT(recurring_prefix,recurring_number) LIKE '%$q%' OR recurring_frequency LIKE '%$q%' OR recurring_scope LIKE '%$q%' OR client_name LIKE '%$q%' OR category_name LIKE '%$q%')
    AND DATE(recurring_created_at) BETWEEN '$dtf' AND '$dtt'
    $client_query
    ORDER BY $sort $order LIMIT $record_from, $record_to");

$num_rows = mysqli_fetch_row(mysqli_query($mysqli, "SELECT FOUND_ROWS()"));

?>

<div class="card card-dark">
    <div class="card-header py-2">
        <h3 class="card-title mt-2"><i class="fa fa-redo-alt mr-2"></i>Recurring Invoices</h3>
        <div class="card-tools">
            <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#addRecurringModal"><i class="fas fa-plus mr-2"></i>New Recurring Invoice</button>
        </div>
    </div>

    <div class="card-body">
        <form class="mb-4" autocomplete="off">
            <?php if ($client_url) { ?>
                <input type="hidden" name="client_id" value="<?php echo $client_id; ?>">
            <?php } ?>
            <div class="row">
                <div class="col-sm-4">
                    <div class="input-group">
                        <input type="search" class="form-control" name="q" value="<?php if (isset($q)) {echo strip_tags(nullable_htmlentities($q));} ?>" placeholder="Search Recurring Invoices">
                        <div class="input-group-append">
                            <button class="btn btn-secondary" type="button" data-toggle="collapse" data-target="#advancedFilter"><i class="fas fa-filter"></i></button>
                            <button class="btn btn-primary"><i class="fa fa-search"></i></button>
                        </div>
                    </div>
                </div>
                <div class="col-sm-8">
                    <div class="btn-group float-right">
                    </div>
                </div>
            </div>
            <div class="collapse mt-3 <?php if (!empty($_GET['dtf']) || $_GET['canned_date'] !== "custom" ) { echo "show"; } ?>" id="advancedFilter">
                <div class="row">
                    <div class="col-md-2">
                        <div class="form-group">
                            <label>Canned Date</label>
                            <select class="form-control select2" name="canned_date">
                                <option <?php if ($_GET['canned_date'] == "custom") { echo "selected"; } ?> value="custom">Custom</option>
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
        <hr>
        <div class="table-responsive-sm">
            <table class="table table-striped table-borderless table-hover">
                <thead class="text-dark <?php if ($num_rows[0] == 0) { echo "d-none"; } ?>">
                <tr>
                    <th>
                        <a class="text-dark" href="?<?php echo $url_query_strings_sort; ?>&sort=recurring_number&order=<?php echo $disp; ?>">
                            Number <?php if ($sort == 'recurring_number') { echo $order_icon; } ?>
                        </a>
                    </th>
                    <th>
                        <a class="text-dark" href="?<?php echo $url_query_strings_sort; ?>&sort=recurring_next_date&order=<?php echo $disp; ?>">
                            Next Date <?php if ($sort == 'recurring_next_date') { echo $order_icon; } ?>
                        </a>
                    </th>
                    <th>
                        <a class="text-dark" href="?<?php echo $url_query_strings_sort; ?>&sort=recurring_scope&order=<?php echo $disp; ?>">
                            Scope <?php if ($sort == 'recurring_scope') { echo $order_icon; } ?>
                        </a>
                    </th>
                    <?php if (!$client_url) { ?>
                    <th>
                        <a class="text-dark" href="?<?php echo $url_query_strings_sort; ?>&sort=client_name&order=<?php echo $disp; ?>">
                            Client <?php if ($sort == 'client_name') { echo $order_icon; } ?>
                        </a>
                    </th>
                    <?php } ?>
                    <th class="text-right">
                        <a class="text-dark" href="?<?php echo $url_query_strings_sort; ?>&sort=recurring_amount&order=<?php echo $disp; ?>">
                            Amount <?php if ($sort == 'recurring_amount') { echo $order_icon; } ?>
                        </a>
                    </th>
                    <th>
                        <a class="text-dark" href="?<?php echo $url_query_strings_sort; ?>&sort=recurring_frequency&order=<?php echo $disp; ?>">
                            Frequency <?php if ($sort == 'recurring_frequency') { echo $order_icon; } ?>
                        </a>
                    </th>
                    <th>
                        <a class="text-dark" href="?<?php echo $url_query_strings_sort; ?>&sort=recurring_last_sent&order=<?php echo $disp; ?>">
                            Last Sent <?php if ($sort == 'recurring_last_sent') { echo $order_icon; } ?>
                        </a>
                    </th>
                    <th>
                        <a class="text-dark" href="?<?php echo $url_query_strings_sort; ?>&sort=category_name&order=<?php echo $disp; ?>">
                            Category <?php if ($sort == 'category_name') { echo $order_icon; } ?>
                        </a>
                    </th>
                    <th>
                        <a class="text-dark" href="?<?php echo $url_query_strings_sort; ?>&sort=recurring_payment_recurring_invoice_id&order=<?php echo $disp; ?>">
                            Auto Pay <?php if ($sort == 'recurring_payment_recurring_invoice_id') { echo $order_icon; } ?>
                        </a>
                    </th>
                    <th>
                        <a class="text-dark" href="?<?php echo $url_query_strings_sort; ?>&sort=recurring_status&order=<?php echo $disp; ?>">
                            Status <?php if ($sort == 'recurring_status') { echo $order_icon; } ?>
                        </a>
                    </th>
                    <th class="text-center">Action</th>
                </tr>
                </thead>
                <tbody>
                <?php

                while ($row = mysqli_fetch_array($sql)) {
                    $recurring_id = intval($row['recurring_id']);
                    $recurring_prefix = nullable_htmlentities($row['recurring_prefix']);
                    $recurring_number = intval($row['recurring_number']);
                    $recurring_scope = nullable_htmlentities($row['recurring_scope']);
                    $recurring_frequency = nullable_htmlentities($row['recurring_frequency']);
                    $recurring_status = nullable_htmlentities($row['recurring_status']);
                    $recurring_discount = floatval($row['recurring_discount_amount']);
                    $recurring_last_sent = $row['recurring_last_sent'];
                    if ($recurring_last_sent == 0) {
                        $recurring_last_sent = "-";
                    }
                    $recurring_next_date = nullable_htmlentities($row['recurring_next_date']);
                    $recurring_amount = floatval($row['recurring_amount']);
                    $recurring_currency_code = nullable_htmlentities($row['recurring_currency_code']);
                    $recurring_created_at = nullable_htmlentities($row['recurring_created_at']);
                    $client_id = intval($row['client_id']);
                    $client_name = nullable_htmlentities($row['client_name']);
                    $client_currency_code = nullable_htmlentities($row['client_currency_code']);
                    $category_id = intval($row['category_id']);
                    $category_name = nullable_htmlentities($row['category_name']);
                    if ($recurring_status == 1) {
                        $status = "Active";
                        $status_badge_color = "success";
                    } else {
                        $status = "Inactive";
                        $status_badge_color = "secondary";
                    }
                    $recurring_payment_id = intval($row['recurring_payment_id']);
                    $recurring_payment_recurring_invoice_id = intval($row['recurring_payment_recurring_invoice_id']);
                    if ($recurring_payment_recurring_invoice_id) {
                        $auto_pay_display = "
                            Yes
                            <a href='post.php?delete_recurring_payment=$recurring_payment_id' title='Remove'>
                                <i class='fas fa-fw fa-times-circle'></i>
                            </a>
                        ";
                    } else {
                        $auto_pay_display = "
                            <a href='#' data-toggle='modal' data-target='#addRecurringPaymentModal$recurring_id'>
                                Create
                            </a>
                        ";
                        require "modals/recurring_payment_add_modal.php";
                    }

                    ?>

                    <tr>
                        <td class="text-bold">
                            <a href="recurring_invoice.php?<?php echo $client_url; ?>recurring_id=<?php echo $recurring_id; ?>">
                                <?php echo "$recurring_prefix$recurring_number"; ?>
                            </a>
                        </td>
                        <td class="text-bold"><?php echo $recurring_next_date; ?></td>
                        <td><?php echo $recurring_scope; ?></td>
                        <?php if (!$client_url) { ?>
                        <td class="text-bold"><a href="recurring_invoices.php?client_id=<?php echo $client_id; ?>"><?php echo $client_name; ?></a></td>
                        <?php } ?>
                        <td class="text-bold text-right"><?php echo numfmt_format_currency($currency_format, $recurring_amount, $recurring_currency_code); ?></td>
                        <td><?php echo ucwords($recurring_frequency); ?>ly</td>
                        <td><?php echo $recurring_last_sent; ?></td>
                        <td><?php echo $category_name; ?></td>
                        <td><?php echo $auto_pay_display; ?></td>
                        <td>
                            <span class="p-2 badge badge-<?php echo $status_badge_color; ?>">
                                <?php echo $status; ?>
                            </span>

                        </td>
                        <td>
                            <div class="dropdown dropleft text-center">
                                <button class="btn btn-secondary btn-sm" type="button" data-toggle="dropdown">
                                    <i class="fas fa-ellipsis-h"></i>
                                </button>
                                <div class="dropdown-menu">
                                    <a class="dropdown-item" href="#"
                                        data-toggle = "ajax-modal"
                                        data-ajax-url = "ajax/ajax_recurring_invoice_edit.php"
                                        data-ajax-id = "<?php echo $recurring_id; ?>"
                                        >
                                        <i class="fas fa-fw fa-edit mr-2"></i>Edit
                                    </a>
                                    <?php if ($status !== 'Active') { ?>
                                        <div class="dropdown-divider"></div>
                                        <a class="dropdown-item text-danger text-bold confirm-link" href="post.php?delete_recurring=<?php echo $recurring_id; ?>">
                                            <i class="fas fa-fw fa-trash mr-2"></i>Delete
                                        </a>
                                    <?php } ?>
                                </div>
                            </div>
                        </td>
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

require_once "modals/recurring_invoice_add_modal.php";
require_once "includes/footer.php";
