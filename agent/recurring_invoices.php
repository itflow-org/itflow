<?php

// Default Column Sortby/Order Filter
$sort = "recurring_invoice_next_date";
$order = "ASC";

// If client_id is in URI then show client Side Bar and client header
if (isset($_GET['client_id'])) {
    require_once "includes/inc_all_client.php";
    $client_query = "AND recurring_invoice_client_id = $client_id";
    $client_url = "client_id=$client_id&";
} else {
    require_once "includes/inc_all.php";
    $client_query = '';
    $client_url = '';
}

// Perms
enforceUserPermission('module_sales');

// Status Filter
if (isset($_GET['status']) && $_GET['status'] == "inactive") {
    $status_filter = "inactive";
    $status_query = "AND recurring_invoice_status = 0";
} else {
    $status_filter = "active";
    $status_query = "AND recurring_invoice_status = 1";
}

$sql = mysqli_query(
    $mysqli,
    "SELECT SQL_CALC_FOUND_ROWS * FROM recurring_invoices
    LEFT JOIN clients ON recurring_invoice_client_id = client_id
    LEFT JOIN categories ON recurring_invoice_category_id = category_id
    LEFT JOIN recurring_payments ON recurring_payment_recurring_invoice_id = recurring_invoice_id
    WHERE (CONCAT(recurring_invoice_prefix,recurring_invoice_number) LIKE '%$q%' OR recurring_invoice_frequency LIKE '%$q%' OR recurring_invoice_scope LIKE '%$q%' OR client_name LIKE '%$q%' OR category_name LIKE '%$q%')
    AND DATE(recurring_invoice_created_at) BETWEEN '$dtf' AND '$dtt'
    $status_query
    $client_query
    ORDER BY $sort $order LIMIT $record_from, $record_to");

$num_rows = mysqli_fetch_row(mysqli_query($mysqli, "SELECT FOUND_ROWS()"));

?>

<div class="card card-dark">
    <div class="card-header py-2">
        <h3 class="card-title mt-2"><i class="fa fa-redo-alt mr-2"></i>Recurring Invoices</h3>
        <div class="card-tools">
            <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#addRecurringInvoiceModal"><i class="fas fa-plus"></i><span class="d-none d-lg-inline ml-2">New Recurring Invoice</span></button>
        </div>
    </div>

    <div class="card-body">
        <form class="mb-4" autocomplete="off">
            <?php if ($client_url) { ?>
                <input type="hidden" name="client_id" value="<?php echo $client_id; ?>">
            <?php } ?>
            <input type="hidden" name="status" value="<?php echo $status_filter; ?>">
            <div class="row">
                <div class="col-sm-4">
                    <div class="input-group mb-3 mb-sm-0">
                        <input type="search" class="form-control" name="q" value="<?php if (isset($q)) {echo strip_tags(nullable_htmlentities($q));} ?>" placeholder="Search Recurring Invoices">
                        <div class="input-group-append">
                            <button class="btn btn-secondary" type="button" data-toggle="collapse" data-target="#advancedFilter"><i class="fas fa-filter"></i></button>
                            <button class="btn btn-primary"><i class="fa fa-search"></i></button>
                        </div>
                    </div>
                </div>
                <div class="col-sm-8">
                    <div class="btn-toolbar float-right">
                        <div class="btn-group">
                            <a href="?<?php echo $client_url; ?>status=active" class="btn btn-<?php if ($status_filter == "active"){ echo "primary"; } else { echo "default"; } ?>"><i class="fa fa-fw fa-check mr-2"></i>Active</a>
                            <a href="?<?php echo $client_url; ?>status=inactive" class="btn btn-<?php if ($status_filter == "inactive"){ echo "primary"; } else { echo "default"; } ?>"><i class="fa fa-fw fa-ban mr-2"></i>Inactive</a>
                        </div>
                    </div>
                </div>
            </div>
            <div class="collapse mt-3 <?php if (isset($_GET['dtf'])) { echo "show"; } ?>" id="advancedFilter">
                <div class="row">
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>Date range</label>
                            <input type="text" id="dateFilter" class="form-control" autocomplete="off">
                            <input type="hidden" name="canned_date" id="canned_date" value="<?php echo nullable_htmlentities($_GET['canned_date']) ?? ''; ?>">
                            <input type="hidden" name="dtf" id="dtf" value="<?php echo nullable_htmlentities($dtf ?? ''); ?>">
                            <input type="hidden" name="dtt" id="dtt" value="<?php echo nullable_htmlentities($dtt ?? ''); ?>">
                        </div>
                    </div>
                </div>
            </div>
        </form>
        <hr>
        <div class="table-responsive-sm">
            <table class="table table-striped table-borderless table-hover">
                <thead class="text-dark <?php if ($num_rows[0] == 0) { echo "d-none"; } ?> text-nowrap">
                <tr>
                    <th>
                        <a class="text-dark" href="?<?php echo $url_query_strings_sort; ?>&sort=recurring_invoice_number&order=<?php echo $disp; ?>">
                            Number <?php if ($sort == 'recurring_invoice_number') { echo $order_icon; } ?>
                        </a>
                    </th>
                    <th>
                        <a class="text-dark" href="?<?php echo $url_query_strings_sort; ?>&sort=recurring_invoice_next_date&order=<?php echo $disp; ?>">
                            Next Date <?php if ($sort == 'recurring_invoice_next_date') { echo $order_icon; } ?>
                        </a>
                    </th>
                    <th>
                        <a class="text-dark" href="?<?php echo $url_query_strings_sort; ?>&sort=recurring_invoice_scope&order=<?php echo $disp; ?>">
                            Scope <?php if ($sort == 'recurring_invoice_scope') { echo $order_icon; } ?>
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
                        <a class="text-dark" href="?<?php echo $url_query_strings_sort; ?>&sort=recurring_invoice_amount&order=<?php echo $disp; ?>">
                            Amount <?php if ($sort == 'recurring_invoice_amount') { echo $order_icon; } ?>
                        </a>
                    </th>
                    <th>
                        <a class="text-dark" href="?<?php echo $url_query_strings_sort; ?>&sort=recurring_invoice_frequency&order=<?php echo $disp; ?>">
                            Frequency <?php if ($sort == 'recurring_invoice_frequency') { echo $order_icon; } ?>
                        </a>
                    </th>
                    <th>
                        <a class="text-dark" href="?<?php echo $url_query_strings_sort; ?>&sort=recurring_invoice_last_sent&order=<?php echo $disp; ?>">
                            Last Sent <?php if ($sort == 'recurring_invoice_last_sent') { echo $order_icon; } ?>
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
                        <a class="text-dark" href="?<?php echo $url_query_strings_sort; ?>&sort=recurring_invoice_status&order=<?php echo $disp; ?>">
                            Status <?php if ($sort == 'recurring_invoice_status') { echo $order_icon; } ?>
                        </a>
                    </th>
                    <th class="text-center">Action</th>
                </tr>
                </thead>
                <tbody>
                <?php

                while ($row = mysqli_fetch_array($sql)) {
                    $recurring_invoice_id = intval($row['recurring_invoice_id']);
                    $recurring_invoice_prefix = nullable_htmlentities($row['recurring_invoice_prefix']);
                    $recurring_invoice_number = intval($row['recurring_invoice_number']);
                    $recurring_invoice_scope = nullable_htmlentities($row['recurring_invoice_scope']);
                    $recurring_invoice_frequency = nullable_htmlentities($row['recurring_invoice_frequency']);
                    $recurring_invoice_status = nullable_htmlentities($row['recurring_invoice_status']);
                    $recurring_invoice_discount = floatval($row['recurring_invoice_discount_amount']);
                    $recurring_invoice_last_sent = $row['recurring_invoice_last_sent'];
                    if ($recurring_invoice_last_sent == 0) {
                        $recurring_invoice_last_sent = "-";
                    }
                    $recurring_invoice_next_date = nullable_htmlentities($row['recurring_invoice_next_date']);
                    $recurring_invoice_amount = floatval($row['recurring_invoice_amount']);
                    $recurring_invoice_currency_code = nullable_htmlentities($row['recurring_invoice_currency_code']);
                    $recurring_invoice_created_at = nullable_htmlentities($row['recurring_invoice_created_at']);
                    $client_id = intval($row['client_id']);
                    $client_name = nullable_htmlentities($row['client_name']);
                    $client_currency_code = nullable_htmlentities($row['client_currency_code']);
                    $category_id = intval($row['category_id']);
                    $category_name = nullable_htmlentities($row['category_name']);
                    if ($recurring_invoice_status == 1) {
                        $status = "Active";
                        $status_badge_color = "success";
                    } else {
                        $status = "Inactive";
                        $status_badge_color = "secondary";
                    }
                    $recurring_payment_id = intval($row['recurring_payment_id']);
                    $recurring_payment_recurring_invoice_id = intval($row['recurring_payment_recurring_invoice_id']);
                    $recurring_payment_saved_payment_id = intval($row['recurring_payment_saved_payment_id']);

                    ?>

                    <tr>
                        <td class="text-bold">
                            <a href="recurring_invoice.php?<?php echo $client_url; ?>recurring_invoice_id=<?php echo $recurring_invoice_id; ?>">
                                <?php echo "$recurring_invoice_prefix$recurring_invoice_number"; ?>
                            </a>
                        </td>
                        <td class="text-bold"><?php echo $recurring_invoice_next_date; ?></td>
                        <td><?php echo $recurring_invoice_scope; ?></td>
                        <?php if (!$client_url) { ?>
                        <td class="text-bold"><a href="recurring_invoices.php?client_id=<?php echo $client_id; ?>"><?php echo $client_name; ?></a></td>
                        <?php } ?>
                        <td class="text-bold text-right"><?php echo numfmt_format_currency($currency_format, $recurring_invoice_amount, $recurring_invoice_currency_code); ?></td>
                        <td><?php echo ucwords($recurring_invoice_frequency); ?>ly</td>
                        <td><?php echo $recurring_invoice_last_sent; ?></td>
                        <td><?php echo $category_name; ?></td>
                        <td>
                            <?php $sql_saved_payments = mysqli_query($mysqli, "SELECT * FROM client_saved_payment_methods WHERE saved_payment_client_id = $client_id");
                            if (mysqli_num_rows($sql_saved_payments) > 0) { ?>
                                <form class="form" action="post.php" method="post">
                                    <input type="hidden" name="set_recurring_payment" value="1">
                                    <input type="hidden" name="recurring_invoice_id" value="<?php echo $recurring_invoice_id; ?>">
                                    <select class="form-control select2" name="saved_payment_id" onchange="this.form.submit()">
                                        <option value="0">Disabled</option>
                                        <?php
                                            while ($row = mysqli_fetch_array($sql_saved_payments)) {
                                                $saved_payment_id = intval($row['saved_payment_id']);
                                                $saved_payment_description = nullable_htmlentities($row['saved_payment_description']);

                                            ?>
                                            <option <?php if ($recurring_payment_saved_payment_id == $saved_payment_id) { echo "selected"; } ?> value="<?php echo $saved_payment_id; ?>"><?php echo $saved_payment_description; ?></option>
                                        <?php } ?>
                                    </select>
                                </form>
                            <?php } else { ?>
                                No Cards on File
                            <?php } ?>  
                        </td>
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
                                    <a class="dropdown-item ajax-modal" href="#"
                                        data-modal-url="modals/recurring_invoice/recurring_invoice_edit.php?id=<?= $recurring_invoice_id ?>">
                                        <i class="fas fa-fw fa-edit mr-2"></i>Edit
                                    </a>
                                    <?php if ($status !== 'Active') { ?>
                                        <div class="dropdown-divider"></div>
                                        <a class="dropdown-item text-danger text-bold confirm-link" href="post.php?delete_recurring_invoice=<?php echo $recurring_invoice_id; ?>">
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
        <?php require_once "../includes/filter_footer.php";
 ?>
    </div>
</div>

<?php

require_once "modals/recurring_invoice/recurring_invoice_add.php";
require_once "../includes/footer.php";
