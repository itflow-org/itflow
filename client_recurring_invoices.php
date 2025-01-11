<?php

// Default Column Sortby Filter
$sort = "recurring_last_sent";
$order = "DESC";

require_once "includes/inc_all_client.php";

// Perms
enforceUserPermission('module_sales');

//Rebuild URL
$url_query_strings_sort = http_build_query($get_copy);

$sql = mysqli_query(
    $mysqli,
    "SELECT * FROM recurring
    LEFT JOIN categories ON recurring_category_id = category_id
    LEFT JOIN recurring_payments ON recurring_payment_recurring_invoice_id = recurring_id
    WHERE recurring_client_id = $client_id
    AND (CONCAT(recurring_prefix,recurring_number) LIKE '%$q%' OR recurring_frequency LIKE '%$q%' OR recurring_scope LIKE '%$q%' OR category_name LIKE '%$q%') 
    ORDER BY $sort $order LIMIT $record_from, $record_to");

$num_rows = mysqli_fetch_row(mysqli_query($mysqli, "SELECT FOUND_ROWS()"));

?>

    <div class="card card-dark">
        <div class="card-header py-2">
            <h3 class="card-title mt-2"><i class="fas fa-fw fa-redo-alt mr-2"></i>Recurring Invoices</h3>
            <div class="card-tools">
                <div class="btn-group">
                    <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#addRecurringModal"><i class="fas fa-plus mr-2"></i>New Recurring</button>
                    <?php if ($num_rows[0] > 0) { ?>
                        <button type="button" class="btn btn-primary dropdown-toggle dropdown-toggle-split" data-toggle="dropdown"></button>
                        <div class="dropdown-menu">
                            <a class="dropdown-item text-dark" href="#" data-toggle="modal" data-target="#exportRecurringModal">
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
                        <input type="search" class="form-control" name="q" value="<?php if (isset($q)) { echo stripslashes(nullable_htmlentities($q)); } ?>" placeholder="Search Recurring Invoices">
                        <div class="input-group-append">
                            <button class="btn btn-dark"><i class="fa fa-search"></i></button>
                        </div>
                    </div>
                </div>

                <div class="col-md-8">
                    <div class="float-right">
                        <div class="btn-group float-right">
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
                        <a class="text-secondary" href="?<?php echo $url_query_strings_sort; ?>&sort=recurring_number&order=<?php echo $disp; ?>">
                            Number <?php if ($sort == 'recurring_number') { echo $order_icon; } ?>
                        </a>
                    </th>
                    <th>
                        <a class="text-secondary" href="?<?php echo $url_query_strings_sort; ?>&sort=recurring_scope&order=<?php echo $disp; ?>">
                            Scope <?php if ($sort == 'recurring_scope') { echo $order_icon; } ?>
                        </a>
                    </th>
                    <th class="text-right">
                        <a class="text-secondary" href="?<?php echo $url_query_strings_sort; ?>&sort=recurring_amount&order=<?php echo $disp; ?>">
                            Amount <?php if ($sort == 'recurring_amount') { echo $order_icon; } ?>
                        </a>
                    </th>
                    <th>
                        <a class="text-secondary" href="?<?php echo $url_query_strings_sort; ?>&sort=recurring_frequency&order=<?php echo $disp; ?>">
                            Frequency <?php if ($sort == 'recurring_frequency') { echo $order_icon; } ?>
                        </a>
                    </th>
                    <th>
                        <a class="text-secondary" href="?<?php echo $url_query_strings_sort; ?>&sort=recurring_last_sent&order=<?php echo $disp; ?>">
                            Last Sent <?php if ($sort == 'recurring_last_sent') { echo $order_icon; } ?>
                        </a>
                    </th>
                    <th>
                        <a class="text-secondary" href="?<?php echo $url_query_strings_sort; ?>&sort=recurring_next_date&order=<?php echo $disp; ?>">
                            Next Date <?php if ($sort == 'recurring_next_date') { echo $order_icon; } ?>
                        </a>
                    </th>
                    <th>
                        <a class="text-secondary" href="?<?php echo $url_query_strings_sort; ?>&sort=category_name&order=<?php echo $disp; ?>">
                            Category <?php if ($sort == 'category_name') { echo $order_icon; } ?>
                        </a>
                    </th>
                    <th>
                        <a class="text-secondary" href="?<?php echo $url_query_strings_sort; ?>&sort=recurring_payment_recurring_invoice_id&order=<?php echo $disp; ?>">
                            Auto Pay <?php if ($sort == 'recurring_payment_recurring_invoice_id') { echo $order_icon; } ?>
                        </a>
                    </th>
                    <th>
                        <a class="text-secondary" href="?<?php echo $url_query_strings_sort; ?>&sort=recurring_status&order=<?php echo $disp; ?>">
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
                    $recurring_last_sent = nullable_htmlentities($row['recurring_last_sent']);
                    if ($recurring_last_sent == 0) {
                        $recurring_last_sent = "-";
                    }
                    $recurring_next_date = nullable_htmlentities($row['recurring_next_date']);
                    $recurring_amount = floatval($row['recurring_amount']);
                    $recurring_discount = floatval($row['recurring_discount_amount']);
                    $recurring_currency_code = nullable_htmlentities($row['recurring_currency_code']);
                    $recurring_created_at = nullable_htmlentities($row['recurring_created_at']);
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
                        require "recurring_payment_add_modal.php";
                    }

                    ?>

                    <tr>
                        <td class="text-bold"><a href="recurring_invoice.php?client_id=<?php echo $client_id; ?>&recurring_id=<?php echo $recurring_id; ?>"><?php echo "$recurring_prefix$recurring_number"; ?></a></td>
                        <td><?php echo $recurring_scope; ?></td>
                        <td class="text-bold text-right"><?php echo numfmt_format_currency($currency_format, $recurring_amount, $recurring_currency_code); ?></td>
                        <td><?php echo ucwords($recurring_frequency); ?>ly</td>
                        <td><?php echo $recurring_last_sent; ?></td>
                        <td><?php echo $recurring_next_date; ?></td>
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
                                    <a class="dropdown-item" href="recurring_invoice.php?client_id=<?php echo $client_id; ?>&recurring_id=<?php echo $recurring_id; ?>">
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
                    //require "recurring_invoice_edit_modal.php";
                    

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
require_once "recurring_invoice_add_modal.php";

require_once "client_recurring_export_modal.php";

require_once "includes/footer.php";

