<?php

// Default Column Sortby Filter
$sort = "invoice_number";
$order = "DESC";

require_once "includes/inc_all_client.php";

// Perms
enforceUserPermission('module_sales');

//Rebuild URL
$url_query_strings_sort = http_build_query($get_copy);

$sql = mysqli_query(
    $mysqli,
    "SELECT SQL_CALC_FOUND_ROWS * FROM invoices
    LEFT JOIN categories ON invoice_category_id = category_id
    WHERE invoice_client_id = $client_id
    AND (CONCAT(invoice_prefix,invoice_number) LIKE '%$q%' OR invoice_scope LIKE '%$q%' OR category_name LIKE '%$q%' OR invoice_status LIKE '%$q%' OR invoice_amount LIKE '%$q%') 
    ORDER BY $sort $order LIMIT $record_from, $record_to"
);

$num_rows = mysqli_fetch_row(mysqli_query($mysqli, "SELECT FOUND_ROWS()"));

?>

<div class="card card-dark">
    <div class="card-header py-2">
        <h3 class="card-title mt-2"><i class="fa fa-fw fa-file-invoice mr-2"></i>Invoices</h3>
        <div class="card-tools">
            <div class="btn-group">
                <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#addInvoiceModal"><i class="fas fa-plus mr-2"></i>New Invoice</button>
                <?php if ($num_rows[0] > 0) { ?>
                    <button type="button" class="btn btn-primary dropdown-toggle dropdown-toggle-split" data-toggle="dropdown"></button>
                    <div class="dropdown-menu">
                        <a class="dropdown-item text-dark" href="#" data-toggle="modal" data-target="#exportInvoiceModal">
                            <i class="fa fa-fw fa-download mr-2"></i>Export
                        </a>
                    </div>
                <?php } ?>
            </div>
        </div>
    </div>
    <div class="card-body">
        <form autocomplete="off">
            <input type="hidden" name="client_id" value="<?php echo $client_id; ?>">
            <div class="row">

                <div class="col-md-4">
                    <div class="input-group mb-3 mb-md-0">
                        <input type="search" class="form-control" name="q" 
                            value="<?php if (isset($q)) {echo stripslashes(nullable_htmlentities($q)); } ?>"
                            placeholder="Search Invoices"
                        >
                        <div class="input-group-append">
                            <button class="btn btn-dark"><i class="fa fa-search"></i></button>
                        </div>
                    </div>
                </div>

                <div class="col-md-8">
                    <div class="float-right">
                        <div class="btn-group float-right">
                            <?php if ($balance > 0) { ?>
                                <button type="button" class="btn btn-default" data-toggle="modal" data-target="#addBulkPaymentModal"><i class="fa fa-credit-card mr-2"></i>Batch Payment</button>
                            <?php } ?>
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
                        <a class="text-secondary" href="?<?php echo $url_query_strings_sort; ?>&sort=invoice_number&order=<?php echo $disp; ?>">
                            Number <?php if ($sort == 'invoice_number') { echo $order_icon; } ?>
                        </a>
                    </th>
                    <th>
                        <a class="text-secondary" href="?<?php echo $url_query_strings_sort; ?>&sort=invoice_scope&order=<?php echo $disp; ?>">
                            Scope <?php if ($sort == 'invoice_scope') { echo $order_icon; } ?>
                        </a>
                    </th>
                    <th class="text-right">
                        <a class="text-secondary" href="?<?php echo $url_query_strings_sort; ?>&sort=invoice_amount&order=<?php echo $disp; ?>">
                            Amount <?php if ($sort == 'invoice_amount') { echo $order_icon; } ?>
                        </a>
                    </th>
                    <th>
                        <a class="text-secondary" href="?<?php echo $url_query_strings_sort; ?>&sort=invoice_date&order=<?php echo $disp; ?>">
                            Date <?php if ($sort == 'invoice_date') { echo $order_icon; } ?>
                        </a>
                    </th>
                    <th>
                        <a class="text-secondary" href="?<?php echo $url_query_strings_sort; ?>&sort=invoice_due&order=<?php echo $disp; ?>">
                            Due <?php if ($sort == 'invoice_due') { echo $order_icon; } ?>
                        </a>
                    </th>
                    <th>
                        <a class="text-secondary" href="?<?php echo $url_query_strings_sort; ?>&sort=category_name&order=<?php echo $disp; ?>">
                            Category <?php if ($sort == 'category_name') { echo $order_icon; } ?>
                        </a>
                    </th>
                    <th>
                        <a class="text-secondary" href="?<?php echo $url_query_strings_sort; ?>&sort=invoice_status&order=<?php echo $disp; ?>">
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
                        $category_id = intval($row['category_id']);
                        $category_name = nullable_htmlentities($row['category_name']);

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
                            <td class="text-bold"><a href="invoice.php?client_id=<?php echo $client_id; ?>&invoice_id=<?php echo $invoice_id; ?>"><?php echo "$invoice_prefix$invoice_number"; ?></a></td>
                            <td><?php echo $invoice_scope_display; ?></td>
                            <td class="text-bold text-right"><?php echo numfmt_format_currency($currency_format, $invoice_amount, $invoice_currency_code); ?></td>
                            <td><?php echo $invoice_date; ?></td>
                            <td>
                                <div class="<?php echo $overdue_color; ?>"><?php echo $invoice_due; ?></div>
                            </td>
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
                                            <a class="dropdown-item" href="post.php?email_invoice=<?php echo $invoice_id; ?>">
                                                <i class="fas fa-fw fa-paper-plane mr-2"></i>Send
                                            </a>
                                            <div class="dropdown-divider"></div>
                                        <?php } ?>
                                        <a class="dropdown-item" href="#" data-toggle="modal" data-target="#editInvoiceModal<?php echo $invoice_id; ?>">
                                            <i class="fas fa-fw fa-edit mr-2"></i>Edit
                                        </a>
                                        <a class="dropdown-item" href="#" data-toggle="modal" data-target="#addInvoiceCopyModal<?php echo $invoice_id; ?>">
                                            <i class="fas fa-fw fa-copy mr-2"></i>Copy
                                        </a>
                                        <div class="dropdown-divider"></div>
                                        <a class="dropdown-item text-danger text-bold confirm-link" href="post.php?delete_invoice=<?php echo $invoice_id; ?>">
                                            <i class="fas fa-fw fa-trash mr-2"></i>Delete
                                        </a>
                                    </div>
                                </div>
                            </td>
                        </tr>

                    <?php

                        require "modals/invoice_copy_modal.php";
                        require "modals/invoice_edit_modal.php";
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
require_once "modals/invoice_add_modal.php";
require_once "modals/invoice_payment_add_bulk_modal.php";
require_once "modals/client_invoice_export_modal.php";
require_once "includes/footer.php";
