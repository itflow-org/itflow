<?php

// Default Column Sortby/Order Filter
$sort = "invoice_number";
$order = "DESC";

require_once "includes/inc_all.php";

// Perms
enforceUserPermission('module_sales');

$row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT('invoice_id') AS num FROM invoices WHERE invoice_status = 'Sent'"));
$sent_count = $row['num'];

$row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT('invoice_id') AS num FROM invoices WHERE invoice_status = 'Viewed'"));
$viewed_count = $row['num'];

$row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT('invoice_id') AS num FROM invoices WHERE invoice_status = 'Partial'"));
$partial_count = $row['num'];

$row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT('invoice_id') AS num FROM invoices WHERE invoice_status = 'Draft'"));
$draft_count = $row['num'];

$row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT('invoice_id') AS num FROM invoices WHERE invoice_status = 'Cancelled'"));
$cancelled_count = $row['num'];

$row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT('invoice_id') AS num FROM invoices WHERE invoice_status NOT LIKE 'Draft' AND invoice_status NOT LIKE 'Paid' AND invoice_status NOT LIKE 'Cancelled' AND invoice_due < CURDATE()"));
$overdue_count = $row['num'];

$sql_total_draft_amount = mysqli_query($mysqli, "SELECT SUM(invoice_amount) AS total_draft_amount FROM invoices WHERE invoice_status = 'Draft'");
$row = mysqli_fetch_array($sql_total_draft_amount);
$total_draft_amount = floatval($row['total_draft_amount']);

$sql_total_sent_amount = mysqli_query($mysqli, "SELECT SUM(invoice_amount) AS total_sent_amount FROM invoices WHERE invoice_status = 'Sent'");
$row = mysqli_fetch_array($sql_total_sent_amount);
$total_sent_amount = floatval($row['total_sent_amount']);

$sql_total_viewed_amount = mysqli_query($mysqli, "SELECT SUM(invoice_amount) AS total_viewed_amount FROM invoices WHERE invoice_status = 'Viewed'");
$row = mysqli_fetch_array($sql_total_viewed_amount);
$total_viewed_amount = floatval($row['total_viewed_amount']);

$sql_total_cancelled_amount = mysqli_query($mysqli, "SELECT SUM(invoice_amount) AS total_cancelled_amount FROM invoices WHERE invoice_status = 'Cancelled'");
$row = mysqli_fetch_array($sql_total_cancelled_amount);
$total_cancelled_amount = floatval($row['total_cancelled_amount']);

$sql_total_partial_amount = mysqli_query($mysqli, "SELECT SUM(invoice_amount) AS total_partial_amount FROM payments, invoices WHERE payment_invoice_id = invoice_id AND invoice_status = 'Partial'");
$row = mysqli_fetch_array($sql_total_partial_amount);
$total_partial_amount = floatval($row['total_partial_amount']);
$total_partial_count = mysqli_num_rows($sql_total_partial_amount);

$sql_total_overdue_partial_amount = mysqli_query($mysqli, "SELECT SUM(payment_amount) AS total_overdue_partial_amount FROM payments, invoices WHERE payment_invoice_id = invoice_id AND invoice_status = 'Partial' AND invoice_due < CURDATE()");
$row = mysqli_fetch_array($sql_total_overdue_partial_amount);
$total_overdue_partial_amount = floatval($row['total_overdue_partial_amount']);

$sql_total_overdue_amount = mysqli_query($mysqli, "SELECT SUM(invoice_amount) AS total_overdue_amount FROM invoices WHERE invoice_status NOT LIKE 'Draft' AND invoice_status NOT LIKE 'Paid' AND invoice_status NOT LIKE 'Cancelled' AND invoice_due < CURDATE()");
$row = mysqli_fetch_array($sql_total_overdue_amount);
$total_overdue_amount = floatval($row['total_overdue_amount']);

$real_overdue_amount = $total_overdue_amount - $total_overdue_partial_amount;
$total_unpaid_amount = $total_sent_amount + $total_viewed_amount + $total_partial_amount;
$unpaid_count = $sent_count + $viewed_count + $partial_count;

$overdue_query = '';
//Invoice status from GET
if (isset($_GET['status']) && ($_GET['status']) == 'Draft') {
    $status_query = "invoice_status = 'Draft'";
} elseif (isset($_GET['status']) && ($_GET['status']) == 'Unpaid') {
    $status_query = "invoice_status = 'Sent' OR invoice_status = 'Viewed' OR invoice_status = 'Partial'";
} elseif (isset($_GET['status']) && ($_GET['status']) == 'Overdue') {
    $status_query = "invoice_status = 'Sent' OR invoice_status = 'Viewed' OR invoice_status = 'Partial'";
    $overdue_query = "AND (invoice_due < CURDATE())";
} else {
    $status_query = "invoice_status LIKE '%'";
}

//Rebuild URL
$url_query_strings_sort = http_build_query($get_copy);

$sql = mysqli_query(
    $mysqli,
    "SELECT SQL_CALC_FOUND_ROWS * FROM invoices
    LEFT JOIN clients ON invoice_client_id = client_id
    LEFT JOIN categories ON invoice_category_id = category_id
    WHERE ($status_query)
    $overdue_query
    AND DATE(invoice_date) BETWEEN '$dtf' AND '$dtt'
    AND (CONCAT(invoice_prefix,invoice_number) LIKE '%$q%' OR invoice_scope LIKE '%$q%' OR client_name LIKE '%$q%' OR invoice_status LIKE '%$q%' OR invoice_amount LIKE '%$q%' OR category_name LIKE '%$q%')
    ORDER BY $sort $order LIMIT $record_from, $record_to"
);

$num_rows = mysqli_fetch_row(mysqli_query($mysqli, "SELECT FOUND_ROWS()"));

?>

    <div class="row">
        <div class="col-lg-4">
            <!-- small box -->
            <a href="?<?php echo $url_query_strings_sort; ?>&status=Draft" class="small-box bg-secondary">
                <div class="inner">
                    <h3><?php echo numfmt_format_currency($currency_format, $total_draft_amount, $session_company_currency); ?></h3>
                    <p><?php echo $draft_count; ?> Draft</p>
                </div>
                <div class="icon">
                    <i class="fa fa-pencil-ruler"></i>
                </div>
            </a>
        </div>
        <!-- ./col -->

        <div class="col-lg-4">
            <!-- small box -->
            <a href="?<?php echo $url_query_strings_sort; ?>&status=Unpaid" class="small-box bg-info">
                <div class="inner text-white">
                    <h3><?php echo numfmt_format_currency($currency_format, $total_unpaid_amount, $session_company_currency); ?></h3>
                    <p><?php echo $unpaid_count; ?> Unpaid</p>
                </div>
                <div class="icon">
                    <i class="fa fa-hand-holding-usd"></i>
                </div>
            </a>
        </div>
        <!-- ./col -->

        <div class="col-lg-4">
            <!-- small box -->
            <a href="?<?php echo $url_query_strings_sort; ?>&status=Overdue" class="small-box bg-danger">
                <div class="inner">
                    <h3><?php echo numfmt_format_currency($currency_format, $real_overdue_amount, $session_company_currency); ?></h3>
                    <p><?php echo $overdue_count; ?> Overdue</p>
                </div>
                <div class="icon">
                    <i class="fa fa-exclamation-triangle"></i>
                </div>
            </a>
        </div>
        <!-- ./col -->

    </div>

    <div class="card card-dark">
        <div class="card-header py-2">
            <h3 class="card-title mt-2"><i class="fa fa-fw fa-file-invoice mr-2"></i>Invoices</h3>
            <div class="card-tools">
                <div class="btn-group">
                    <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#addInvoiceModal"><i class="fas fa-plus mr-2"></i>New Invoice</button>
                    <button type="button" class="btn btn-primary dropdown-toggle dropdown-toggle-split" data-toggle="dropdown"></button>
                    <div class="dropdown-menu">
                        <a class="dropdown-item text-dark" href="#" data-toggle="modal" data-target="#exportInvoicesModal">
                            <i class="fa fa-fw fa-download mr-2"></i>Export
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <div class="card-body">
            <form class="mb-4" autocomplete="off">
                <input type="hidden" name="status" value="<?php if (isset($_GET['status'])) { echo nullable_htmlentities($_GET['status']); } ?>">
                <div class="row">
                    <div class="col-sm-4">
                        <div class="input-group">
                            <input type="search" class="form-control" name="q" value="<?php if (isset($q)) {echo stripslashes(nullable_htmlentities($q));} ?>" placeholder="Search Invoices">
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
                                <select onchange="this.form.submit()" class="form-control select2" name="canned_date">
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
                                <input onchange="this.form.submit()" type="date" class="form-control" name="dtf" max="2999-12-31" value="<?php echo $dtf; ?>">
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-group">
                                <label>Date To</label>
                                <input onchange="this.form.submit()" type="date" class="form-control" name="dtt" max="2999-12-31" value="<?php echo $dtt; ?>">
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
                                <a class="text-dark" href="?<?php echo $url_query_strings_sort; ?>&sort=invoice_number&order=<?php echo $disp ?>">
                                    Number <?php if ($sort == 'invoice_number') { echo $order_icon; } ?>
                                </a>
                            </th>
                            <th>
                                <a class="text-dark" href="?<?php echo $url_query_strings_sort; ?>&sort=invoice_scope&order=<?php echo $disp; ?>">
                                    Scope <?php if ($sort == 'invoice_scope') { echo $order_icon; } ?>
                                </a>
                            </th>
                            <th>
                                <a class="text-dark" href="?<?php echo $url_query_strings_sort; ?>&sort=client_name&order=<?php echo $disp; ?>">
                                    Client <?php if ($sort == 'client_name') { echo $order_icon; } ?>
                                </a>
                            </th>
                            <th class="text-right">
                                <a class="text-dark" href="?<?php echo $url_query_strings_sort; ?>&sort=invoice_amount&order=<?php echo $disp; ?>">
                                    Amount <?php if ($sort == 'invoice_amount') { echo $order_icon; } ?>
                                </a>
                            </th>
                            <th>
                                <a class="text-dark" href="?<?php echo $url_query_strings_sort; ?>&sort=invoice_date&order=<?php echo $disp; ?>">
                                    Date <?php if ($sort == 'invoice_date') { echo $order_icon; } ?>
                                </a>
                            </th>
                            <th>
                                <a class="text-dark" href="?<?php echo $url_query_strings_sort; ?>&sort=invoice_due&order=<?php echo $disp; ?>">
                                    Due <?php if ($sort == 'invoice_due') { echo $order_icon; } ?>
                                </a>
                            </th>
                            <th>
                                <a class="text-dark" href="?<?php echo $url_query_strings_sort; ?>&sort=category_name&order=<?php echo $disp; ?>">
                                    Category <?php if ($sort == 'category_name') { echo $order_icon; } ?>
                                </a>
                            </th>
                            <th>
                                <a class="text-dark" href="?<?php echo $url_query_strings_sort; ?>&sort=invoice_status&order=<?php echo $disp; ?>">
                                    Status <?php if ($sort == 'invoice_status') { echo $order_icon; } ?>
                                </a>
                            </th>
                            <th class="text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php

                    while ($row = mysqli_fetch_array($sql)) {
                        $invoice_id = intval($row['invoice_id']);
                        $invoice_prefix = nullable_htmlentities($row['invoice_prefix']);
                        $invoice_number = nullable_htmlentities($row['invoice_number']);
                        $invoice_scope = nullable_htmlentities($row['invoice_scope']);
                        if (empty($invoice_scope)) {
                            $invoice_scope_display = "-";
                        } else {
                            $invoice_scope_display = $invoice_scope;
                        }
                        $invoice_status = nullable_htmlentities($row['invoice_status']);
                        $invoice_date = nullable_htmlentities($row['invoice_date']);
                        $invoice_due = nullable_htmlentities($row['invoice_due']);
                        $invoice_discount = floatval($row['invoice_discount_amount']);
                        $invoice_amount = floatval($row['invoice_amount']);
                        $invoice_currency_code = nullable_htmlentities($row['invoice_currency_code']);
                        $invoice_created_at = nullable_htmlentities($row['invoice_created_at']);
                        $client_id = intval($row['client_id']);
                        $client_name = nullable_htmlentities($row['client_name']);
                        $category_id = intval($row['category_id']);
                        $category_name = nullable_htmlentities($row['category_name']);
                        $client_currency_code = nullable_htmlentities($row['client_currency_code']);
                        $client_net_terms = intval($row['client_net_terms']);
                        if ($client_net_terms == 0) {
                            $client_net_terms = $config_default_net_terms;
                        }

                        $now = time();

                        if (($invoice_status == "Sent" || $invoice_status == "Partial" || $invoice_status == "Viewed") && strtotime($invoice_due) + 86400 < $now) {
                            $overdue_color = "text-danger font-weight-bold";
                        } else {
                            $overdue_color = "";
                        }

                        $invoice_badge_color = getInvoiceBadgeColor($invoice_status);

                        ?>

                        <tr>
                            <td class="text-bold"><a href="invoice.php?invoice_id=<?php echo $invoice_id; ?>"><?php echo "$invoice_prefix$invoice_number"; ?></a></td>
                            <td><?php echo $invoice_scope_display; ?></td>
                            <td class="text-bold"><a href="client_invoices.php?client_id=<?php echo $client_id; ?>"><?php echo $client_name; ?></a></td>
                            <td class="text-bold text-right"><?php echo numfmt_format_currency($currency_format, $invoice_amount, $invoice_currency_code); ?></td>
                            <td><?php echo $invoice_date; ?></td>
                            <td class="<?php echo $overdue_color; ?>"><?php echo $invoice_due; ?></td>
                            <td><?php echo $category_name; ?></td>
                            <td>
                              <span class="p-2 badge badge-<?php echo $invoice_badge_color; ?>">
                                  <?php echo $invoice_status; ?>
                              </span>
                            </td>
                            <td>
                                <div class="dropdown dropleft text-center">
                                    <button class="btn btn-secondary btn-sm" type="button" data-toggle="dropdown">
                                        <i class="fas fa-ellipsis-h"></i>
                                    </button>
                                    <div class="dropdown-menu">
                                        <a class="dropdown-item" href="#" data-toggle="modal" data-target="#editInvoiceModal<?php echo $invoice_id; ?>">
                                            <i class="fas fa-fw fa-edit mr-2"></i>Edit
                                        </a>
                                        <a class="dropdown-item" href="#" data-toggle="modal" data-target="#addInvoiceCopyModal<?php echo $invoice_id; ?>">
                                            <i class="fas fa-fw fa-copy mr-2"></i>Copy
                                        </a>
                                        <div class="dropdown-divider"></div>
                                        <?php if (!empty($config_smtp_host)) { ?>
                                            <a class="dropdown-item" href="post.php?email_invoice=<?php echo $invoice_id; ?>">
                                                <i class="fas fa-fw fa-paper-plane mr-2"></i>Send
                                            </a>
                                            <div class="dropdown-divider"></div>
                                        <?php } ?>
                                        <a class="dropdown-item text-danger text-bold confirm-link" href="post.php?delete_invoice=<?php echo $invoice_id; ?>">
                                            <i class="fas fa-fw fa-trash mr-2"></i>Delete
                                        </a>
                                    </div>
                                </div>
                            </td>
                        </tr>

                        <?php


                        require "invoice_edit_modal.php";

                        require "invoice_copy_modal.php";


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
require_once "invoice_add_modal.php";
require_once "invoice_export_modal.php";

require_once "includes/footer.php";

