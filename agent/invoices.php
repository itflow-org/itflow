<?php

// Default Column Sortby/Order Filter
$sort = "invoice_number";
$order = "DESC";

// If client_id is in URI then show client Side Bar and client header
if (isset($_GET['client_id'])) {
    require_once "includes/inc_all_client.php";
    $client_query = "AND invoice_client_id = $client_id";
    $client_url = "client_id=$client_id&";
} else {
    require_once "includes/inc_all.php";
    $client_query = '';
    $client_url = '';
}

// Perms
enforceUserPermission('module_sales');

$row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT('invoice_id') AS num FROM invoices WHERE invoice_status = 'Sent' $client_query"));
$sent_count = $row['num'];

$row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT('invoice_id') AS num FROM invoices WHERE invoice_status = 'Viewed' $client_query"));
$viewed_count = $row['num'];

$row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT('invoice_id') AS num FROM invoices WHERE invoice_status = 'Partial' $client_query"));
$partial_count = $row['num'];

$row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT('invoice_id') AS num FROM invoices WHERE invoice_status = 'Draft' $client_query"));
$draft_count = $row['num'];

$row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT('invoice_id') AS num FROM invoices WHERE invoice_status = 'Cancelled' $client_query"));
$cancelled_count = $row['num'];

$row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT('invoice_id') AS num FROM invoices WHERE invoice_status NOT LIKE 'Draft' AND invoice_status NOT LIKE 'Paid' AND invoice_status NOT LIKE 'Cancelled' AND invoice_status NOT LIKE 'Non-Billable' AND invoice_due < CURDATE() $client_query"));
$overdue_count = $row['num'];

$sql_total_draft_amount = mysqli_query($mysqli, "SELECT SUM(invoice_amount) AS total_draft_amount FROM invoices WHERE invoice_status = 'Draft' $client_query");
$row = mysqli_fetch_array($sql_total_draft_amount);
$total_draft_amount = floatval($row['total_draft_amount']);

$sql_total_sent_amount = mysqli_query($mysqli, "SELECT SUM(invoice_amount) AS total_sent_amount FROM invoices WHERE invoice_status = 'Sent' $client_query");
$row = mysqli_fetch_array($sql_total_sent_amount);
$total_sent_amount = floatval($row['total_sent_amount']);

$sql_total_viewed_amount = mysqli_query($mysqli, "SELECT SUM(invoice_amount) AS total_viewed_amount FROM invoices WHERE invoice_status = 'Viewed' $client_query");
$row = mysqli_fetch_array($sql_total_viewed_amount);
$total_viewed_amount = floatval($row['total_viewed_amount']);

$sql_total_cancelled_amount = mysqli_query($mysqli, "SELECT SUM(invoice_amount) AS total_cancelled_amount FROM invoices WHERE invoice_status = 'Cancelled' $client_query");
$row = mysqli_fetch_array($sql_total_cancelled_amount);
$total_cancelled_amount = floatval($row['total_cancelled_amount']);

$sql_total_partial_amount = mysqli_query($mysqli, "SELECT SUM(invoice_amount) AS total_partial_amount FROM payments, invoices WHERE payment_invoice_id = invoice_id AND invoice_status = 'Partial' $client_query");
$row = mysqli_fetch_array($sql_total_partial_amount);
$total_partial_amount = floatval($row['total_partial_amount']);
$total_partial_count = mysqli_num_rows($sql_total_partial_amount);

$sql_total_overdue_partial_amount = mysqli_query($mysqli, "SELECT SUM(payment_amount) AS total_overdue_partial_amount FROM payments, invoices WHERE payment_invoice_id = invoice_id AND invoice_status = 'Partial' AND invoice_due < CURDATE() $client_query");
$row = mysqli_fetch_array($sql_total_overdue_partial_amount);
$total_overdue_partial_amount = floatval($row['total_overdue_partial_amount']);

$sql_total_overdue_amount = mysqli_query($mysqli, "SELECT SUM(invoice_amount) AS total_overdue_amount FROM invoices WHERE invoice_status != 'Draft' AND invoice_status != 'Paid' AND invoice_status != 'Cancelled' AND invoice_status != 'Non-Billable' AND invoice_due < CURDATE() $client_query");
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

// Category Filter
if (isset($_GET['category']) & !empty($_GET['category'])) {
    $category_query = 'AND (category_id = ' . intval($_GET['category']) . ')';
    $category_filter = intval($_GET['category']);
} else {
    // Default - any
    $category_query = '';
    $category_filter = '';
}

$sql = mysqli_query(
    $mysqli,
    "SELECT SQL_CALC_FOUND_ROWS * FROM invoices
    LEFT JOIN clients ON invoice_client_id = client_id
    LEFT JOIN categories ON invoice_category_id = category_id
    WHERE ($status_query)
    $overdue_query
    $category_query
    AND DATE(invoice_date) BETWEEN '$dtf' AND '$dtt'
    AND (CONCAT(invoice_prefix,invoice_number) LIKE '%$q%' OR invoice_scope LIKE '%$q%' OR client_name LIKE '%$q%' OR invoice_status LIKE '%$q%' OR invoice_amount LIKE '%$q%' OR category_name LIKE '%$q%')
    $access_permission_query
    $client_query
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
            <?php if ($client_url) { ?>
                <input type="hidden" name="client_id" value="<?php echo $client_id; ?>">
            <?php } ?>
            <div class="row">
                <div class="col-sm-4">
                    <div class="form-group mb-md-0">
                        <div class="input-group">
                            <input type="search" class="form-control" name="q" value="<?php if (isset($q)) {echo stripslashes(nullable_htmlentities($q));} ?>" placeholder="Search Invoices">
                            <div class="input-group-append">
                                <button class="btn btn-secondary" type="button" data-toggle="collapse" data-target="#advancedFilter"><i class="fas fa-filter"></i></button>
                                <button class="btn btn-primary"><i class="fa fa-search"></i></button>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-sm-3">
                    <div class="form-group mb-md-0">
                        <select class="form-control select2" name="category" onchange="this.form.submit()">
                            <option value="">- All Categories -</option>

                            <?php
                            $sql_categories_filter = mysqli_query($mysqli, "SELECT category_id, category_name FROM categories WHERE category_type = 'Income' AND EXISTS (SELECT 1 FROM invoices WHERE invoice_category_id = category_id) ORDER BY category_name ASC");
                            while ($row = mysqli_fetch_array($sql_categories_filter)) {
                                $category_id = intval($row['category_id']);
                                $category_name = nullable_htmlentities($row['category_name']);
                            ?>
                                <option <?php if ($category_filter == $category_id) { echo "selected"; } ?> value="<?php echo $category_id; ?>"><?php echo $category_name; ?></option>
                            <?php
                            }
                            ?>

                        </select>
                    </div>
                </div>
                <div class="col-md-5">
                    <div class="btn-group float-right">
                        <div class="dropdown ml-2" id="bulkActionButton" hidden>
                            <button class="btn btn-secondary dropdown-toggle" type="button" data-toggle="dropdown">
                                <i class="fas fa-fw fa-layer-group mr-2"></i>Bulk Action (<span id="selectedCount">0</span>)
                            </button>
                            <div class="dropdown-menu">
                                <?php if ($client_url && $balance > 0) { ?> 
                                    <a class="dropdown-item" href="#" data-toggle="modal" data-target="#addBulkPaymentModal">
                                        <i class="fa fa-credit-card mr-2"></i>Batch Payment
                                    </a>
                                    <div class="dropdown-divider"></div>
                                <?php } ?>
                                <a class="dropdown-item" href="#" data-toggle="modal" data-target="#bulkEditCategoryModal">
                                    <i class="fas fa-fw fa-list-ul mr-2"></i>Set Category
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="collapse mt-3 <?php if (isset($_GET['dtf']) && $_GET['dtf'] !== '1970-01-01') { echo "show"; } ?>" id="advancedFilter">
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
        <form id="bulkActions" action="post.php" method="post" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?>">
            <div class="table-responsive-sm">
                <table class="table table-striped table-borderless table-hover">
                    <thead class="text-dark <?php if ($num_rows[0] == 0) { echo "d-none"; } ?> text-nowrap">
                        <tr>
                            <td class="bg-light pr-0">
                                <div class="form-check">
                                    <input class="form-check-input" id="selectAllCheckbox" type="checkbox" onclick="checkAll(this)">
                                </div>
                            </td>
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
                            <?php if (!$client_url) { ?>
                            <th>
                                <a class="text-dark" href="?<?php echo $url_query_strings_sort; ?>&sort=client_name&order=<?php echo $disp; ?>">
                                    Client <?php if ($sort == 'client_name') { echo $order_icon; } ?>
                                </a>
                            </th>
                            <?php } ?>
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
                            <td class="pr-0 bg-light">
                                <div class="form-check">
                                    <input class="form-check-input bulk-select" type="checkbox" name="invoice_ids[]" value="<?php echo $invoice_id ?>">
                                </div>
                            </td>
                            <td class="text-bold">
                                <a href="invoice.php?<?php echo $client_url; ?>invoice_id=<?php echo $invoice_id; ?>">
                                <?php echo "$invoice_prefix$invoice_number"; ?>
                                </a>
                            </td>
                            <td><?php echo $invoice_scope_display; ?></td>
                            <?php if (!$client_url) { ?>
                            <td class="text-bold"><a href="invoices.php?client_id=<?php echo $client_id; ?>"><?php echo $client_name; ?></a></td>
                            <?php } ?>
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
                                        <?php if ($invoice_status !== 'Paid' && $invoice_status !== 'Cancelled' && $invoice_status !== 'Draft' && $invoice_status !== 'Non-Billable' && $invoice_amount != 0) { ?>
                                            <a class="dropdown-item ajax-modal" href="#"
                                                data-modal-url="modals/invoice/invoice_pay.php?id=<?= $invoice_id ?>">
                                                <i class="fa fa-fw fa-credit-card mr-2"></i>Add Payment
                                            </a>
                                            <div class="dropdown-divider"></div>
                                            <?php if ($invoice_status !== 'Partial' && $config_stripe_enable && $stripe_id && $stripe_pm) { ?>
                                                <a class="dropdown-item confirm-link" href="post.php?add_payment_stripe&invoice_id=<?php echo $invoice_id; ?>&csrf_token=<?php echo $_SESSION['csrf_token']; ?>">
                                                    <i class="fa fa-fw fa-credit-card mr-2"></i>Pay via saved card
                                                </a>
                                                <div class="dropdown-divider"></div>
                                            <?php } ?>
                                        <?php } ?>
                                        <a class="dropdown-item ajax-modal" href="#"
                                            data-modal-url="modals/invoice/invoice_edit.php?id=<?= $invoice_id ?>">
                                            <i class="fas fa-fw fa-edit mr-2"></i>Edit
                                        </a>
                                        <a class="dropdown-item ajax-modal" href="#"
                                            data-modal-url="modals/invoice/invoice_copy.php?id=<?= $invoice_id ?>">
                                            <i class="fas fa-fw fa-copy mr-2"></i>Copy
                                        </a>
                                        <div class="dropdown-divider"></div>
                                        <?php if (!empty($config_smtp_host)) { ?>
                                            <a class="dropdown-item" href="post.php?email_invoice=<?php echo $invoice_id; ?>">
                                                <i class="fas fa-fw fa-paper-plane mr-2"></i>Send Email
                                            </a>
                                            <div class="dropdown-divider"></div>
                                        <?php } ?>
                                        <?php if ($invoice_status == 'Draft') { ?>
                                        <a class="dropdown-item" href="post.php?mark_invoice_sent=<?php echo $invoice_id; ?>">
                                            <i class="fas fa-fw fa-check mr-2"></i>Mark Sent
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

                    }

                    ?>

                    </tbody>
                </table>
            </div>
            <?php require_once "modals/invoice/invoice_bulk_edit_category.php"; ?>
        </form>
        <?php require_once "../includes/filter_footer.php";
?>
    </div>
</div>

<script src="../js/bulk_actions.js"></script>

<?php
require_once "modals/invoice/invoice_add.php";
if ($client_url) { require_once "modals/invoice/invoice_payment_add_bulk.php"; }
require_once "modals/invoice/invoice_export.php";
require_once "../includes/footer.php";
