<?php
require_once("inc_all_client.php");

if (!empty($_GET['sb'])) {
    $sb = strip_tags(mysqli_real_escape_string($mysqli, $_GET['sb']));
} else {
    $sb = "invoice_number";
}

// Reverse default sort
if (!isset($_GET['o'])) {
    $o = "DESC";
    $disp = "ASC";
}

//Rebuild URL
$url_query_strings_sb = http_build_query(array_merge($_GET, array('sb' => $sb, 'o' => $o)));

$sql = mysqli_query(
    $mysqli,
    "SELECT SQL_CALC_FOUND_ROWS * FROM invoices
    LEFT JOIN categories ON invoice_category_id = category_id
    WHERE invoice_client_id = $client_id 
    AND (CONCAT(invoice_prefix,invoice_number) LIKE '%$q%' OR invoice_scope LIKE '%$q%' OR category_name LIKE '%$q%' OR invoice_status LIKE '%$q%' OR invoice_amount LIKE '%$q%') 
    ORDER BY $sb $o LIMIT $record_from, $record_to"
);

$num_rows = mysqli_fetch_row(mysqli_query($mysqli, "SELECT FOUND_ROWS()"));

?>

    <div class="card card-dark">
        <div class="card-header py-2">
            <h3 class="card-title mt-2"><i class="fa fa-fw fa-file"></i> Invoices</h3>
            <div class="card-tools">
                <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#addInvoiceModal"><i class="fas fa-fw fa-plus"></i> New Invoice</button>
            </div>
        </div>
        <div class="card-body">
            <form autocomplete="off">
                <input type="hidden" name="client_id" value="<?php echo $client_id; ?>">
                <div class="row">

                    <div class="col-md-4">
                        <div class="input-group mb-3 mb-md-0">
                            <input type="search" class="form-control" name="q" value="<?php if (isset($q)) { echo strip_tags(htmlentities($q)); } ?>" placeholder="Search Invoices">
                            <div class="input-group-append">
                                <button class="btn btn-dark"><i class="fa fa-search"></i></button>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-8">
                        <div class="float-right">
                            <a href="post.php?export_client_invoices_csv=<?php echo $client_id; ?>" class="btn btn-default"><i class="fa fa-fw fa-download"></i> Export</a>
                        </div>
                    </div>

                </div>
            </form>
            <hr>
            <div class="table-responsive">
                <table class="table table-striped table-borderless table-hover">
                    <thead class="text-dark <?php if ($num_rows[0] == 0) { echo "d-none"; } ?>">
                    <tr>
                        <th><a class="text-secondary" href="?<?php echo $url_query_strings_sb; ?>&sb=invoice_number&o=<?php echo $disp; ?>">Number</a></th>
                        <th><a class="text-secondary" href="?<?php echo $url_query_strings_sb; ?>&sb=invoice_scope&o=<?php echo $disp; ?>">Scope</a></th>
                        <th class="text-right"><a class="text-secondary" href="?<?php echo $url_query_strings_sb; ?>&sb=invoice_amount&o=<?php echo $disp; ?>">Amount</a></th>
                        <th><a class="text-secondary" href="?<?php echo $url_query_strings_sb; ?>&sb=invoice_date&o=<?php echo $disp; ?>">Date</a></th>
                        <th><a class="text-secondary" href="?<?php echo $url_query_strings_sb; ?>&sb=invoice_due&o=<?php echo $disp; ?>">Due</a></th>
                        <th><a class="text-secondary" href="?<?php echo $url_query_strings_sb; ?>&sb=category_name&o=<?php echo $disp; ?>">Category</a></th>
                        <th><a class="text-secondary" href="?<?php echo $url_query_strings_sb; ?>&sb=invoice_status&o=<?php echo $disp; ?>">Status</a></th>
                        <th class="text-center">Action</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php

                    while ($row = mysqli_fetch_array($sql)) {
                        $invoice_id = $row['invoice_id'];
                        $invoice_prefix = htmlentities($row['invoice_prefix']);
                        $invoice_number = htmlentities($row['invoice_number']);
                        $invoice_scope = htmlentities($row['invoice_scope']);
                        if (empty($invoice_scope)) {
                            $invoice_scope_display = "-";
                        } else {
                            $invoice_scope_display = $invoice_scope;
                        }
                        $invoice_status = htmlentities($row['invoice_status']);
                        $invoice_date = $row['invoice_date'];
                        $invoice_due = $row['invoice_due'];
                        $invoice_amount = floatval($row['invoice_amount']);
                        $invoice_currency_code = htmlentities($row['invoice_currency_code']);
                        $invoice_created_at = $row['invoice_created_at'];
                        $category_id = $row['category_id'];
                        $category_name = htmlentities($row['category_name']);

                        if (($invoice_status == "Sent" || $invoice_status == "Partial" || $invoice_status == "Viewed") && strtotime($invoice_due) < time()) {
                            $overdue_color = "text-danger font-weight-bold";
                        } else {
                            $overdue_color = "";
                        }

                        //Set Badge color based off of invoice status
                        if ($invoice_status == "Sent") {
                            $invoice_badge_color = "warning";
                        } elseif ($invoice_status == "Viewed") {
                            $invoice_badge_color = "info";
                        } elseif ($invoice_status == "Partial") {
                            $invoice_badge_color = "primary";
                        } elseif ($invoice_status == "Paid") {
                            $invoice_badge_color = "success";
                        } elseif ($invoice_status == "Cancelled") {
                            $invoice_badge_color = "danger";
                        } else {
                            $invoice_badge_color = "secondary";
                        }

                        ?>

                        <tr>
                            <td><a href="invoice.php?invoice_id=<?php echo $invoice_id; ?>"><?php echo "$invoice_prefix$invoice_number"; ?></a></td>
                            <td><?php echo $invoice_scope_display; ?></td>
                            <td class="text-right"><?php echo numfmt_format_currency($currency_format, $invoice_amount, $invoice_currency_code); ?></td>
                            <td><?php echo $invoice_date; ?></td>
                            <td><div class="<?php echo $overdue_color; ?>"><?php echo $invoice_due; ?></div></td>
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
                                        <?php if (!empty($config_smtp_host)) { ?>
                                            <a class="dropdown-item" href="post.php?email_invoice=<?php echo $invoice_id; ?>">Send</a>
                                            <div class="dropdown-divider"></div>
                                        <?php } ?>
                                        <a class="dropdown-item" href="#" data-toggle="modal" data-target="#editInvoiceModal<?php echo $invoice_id; ?>">Edit</a>
                                        <a class="dropdown-item" href="#" data-toggle="modal" data-target="#addInvoiceCopyModal<?php echo $invoice_id; ?>">Copy</a>
                                        <div class="dropdown-divider"></div>
                                        <a class="dropdown-item text-danger" href="post.php?delete_invoice=<?php echo $invoice_id; ?>">Delete</a>
                                    </div>
                                </div>
                            </td>
                        </tr>

                        <?php

                        require("invoice_copy_modal.php");
                        require("invoice_edit_modal.php");
                    }

                    ?>

                    </tbody>
                </table>
            </div>
            <?php require_once("pagination.php"); ?>
        </div>
    </div>

<?php
require_once("invoice_add_modal.php");
require_once("footer.php");
