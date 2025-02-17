<?php

// Default Column Sortby Filter
$sort = "quote_number";
$order = "DESC";

require_once "includes/inc_all_client.php";

// Perms
enforceUserPermission('module_sales');

//Rebuild URL
$url_query_strings_sort = http_build_query($get_copy);

$sql = mysqli_query(
    $mysqli,
    "SELECT SQL_CALC_FOUND_ROWS * FROM quotes
    LEFT JOIN categories ON category_id = quote_category_id
    WHERE quote_client_id = $client_id
    AND (CONCAT(quote_prefix,quote_number) LIKE '%$q%' OR quote_scope LIKE '%$q%' OR category_name LIKE '%$q%' OR quote_status LIKE '%$q%')
    ORDER BY $sort $order LIMIT $record_from, $record_to"
);

$num_rows = mysqli_fetch_row(mysqli_query($mysqli, "SELECT FOUND_ROWS()"));

?>

    <div class="card card-dark">
        <div class="card-header py-2">
            <h3 class="card-title mt-2"><i class="fa fa-fw fa-comment-dollar mr-2"></i>Quotes</h3>
            <div class="card-tools">
                <div class="btn-group">
                    <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#addQuoteModal"><i class="fas fa-plus mr-2"></i>New Quote</button>
                    <?php if ($num_rows[0] > 0) { ?>
                        <button type="button" class="btn btn-primary dropdown-toggle dropdown-toggle-split" data-toggle="dropdown"></button>
                        <div class="dropdown-menu">
                            <a class="dropdown-item text-dark" href="#" data-toggle="modal" data-target="#exportQuoteModal">
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
                            <input type="search" class="form-control" name="q" value="<?php if (isset($q)) { echo stripslashes(nullable_htmlentities($q)); } ?>" placeholder="Search Quotes">
                            <div class="input-group-append">
                                <button class="btn btn-dark"><i class="fa fa-search"></i></button>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-8">
                        <div class="float-right">
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
                            <a class="text-secondary" href="?<?php echo $url_query_strings_sort; ?>&sort=quote_number&order=<?php echo $disp; ?>">
                                Number <?php if ($sort == 'quote_number') { echo $order_icon; } ?>
                            </a>
                        </th>
                        <th>
                            <a class="text-secondary" href="?<?php echo $url_query_strings_sort; ?>&sort=quote_scope&order=<?php echo $disp; ?>">
                                Scope <?php if ($sort == 'quote_scope') { echo $order_icon; } ?>
                            </a>
                        </th>
                        <th class="text-right">
                            <a class="text-secondary" href="?<?php echo $url_query_strings_sort; ?>&sort=quote_amount&order=<?php echo $disp; ?>">
                                Amount <?php if ($sort == 'quote_amount') { echo $order_icon; } ?>
                            </a>
                        </th>
                        <th>
                            <a class="text-secondary" href="?<?php echo $url_query_strings_sort; ?>&sort=quote_date&order=<?php echo $disp; ?>">
                                Date <?php if ($sort == 'quote_date') { echo $order_icon; } ?>
                            </a>
                        </th>
                        <th>
                            <a class="text-secondary" href="?<?php echo $url_query_strings_sort; ?>&sort=quote_expire&order=<?php echo $disp; ?>">
                                Expire <?php if ($sort == 'quote_expire') { echo $order_icon; } ?>
                            </a>
                        </th>
                        <th>
                            <a class="text-secondary" href="?<?php echo $url_query_strings_sort; ?>&sort=category_name&order=<?php echo $disp; ?>">
                                Category <?php if ($sort == 'category_name') { echo $order_icon; } ?>
                            </a>
                        </th>
                        <th>
                            <a class="text-secondary" href="?<?php echo $url_query_strings_sort; ?>&sort=quote_status&order=<?php echo $disp; ?>">
                                Status <?php if ($sort == 'quote_status') { echo $order_icon; } ?>
                            </a>
                        </th>
                        <th class="text-center">Action</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php

                    while ($row = mysqli_fetch_array($sql)) {
                        $quote_id = intval($row['quote_id']);
                        $quote_prefix = nullable_htmlentities($row['quote_prefix']);
                        $quote_number = nullable_htmlentities($row['quote_number']);
                        $quote_scope = nullable_htmlentities($row['quote_scope']);
                        if (empty($quote_scope)) {
                            $quote_scope_display = "-";
                        } else {
                            $quote_scope_display = $quote_scope;
                        }
                        $quote_status = nullable_htmlentities($row['quote_status']);
                        $quote_date = nullable_htmlentities($row['quote_date']);
                        $quote_expire = nullable_htmlentities($row['quote_expire']);
                        $quote_discount = floatval($row['quote_discount_amount']);
                        $quote_amount = floatval($row['quote_amount']);
                        $quote_currency_code = nullable_htmlentities($row['quote_currency_code']);
                        $quote_created_at = nullable_htmlentities($row['quote_created_at']);
                        $category_id = intval($row['category_id']);
                        $category_name = nullable_htmlentities($row['category_name']);

                        //Set Badge color based off of quote status
                        if ($quote_status == "Sent") {
                            $quote_badge_color = "warning text-white";
                        } elseif ($quote_status == "Viewed") {
                            $quote_badge_color = "primary";
                        } elseif ($quote_status == "Accepted") {
                            $quote_badge_color = "success";
                        } elseif ($quote_status == "Declined") {
                            $quote_badge_color = "danger";
                        } elseif ($quote_status == "Invoiced") {
                            $quote_badge_color = "info";
                        } else {
                            $quote_badge_color = "secondary";
                        }

                        ?>

                        <tr>
                            <td class="text-bold"><a href="quote.php?client_id=<?php echo $client_id; ?>&quote_id=<?php echo $quote_id; ?>"><?php echo "$quote_prefix$quote_number"; ?></a></td>
                            <td><?php echo $quote_scope_display; ?></td>
                            <td class="text-right text-bold"><?php echo numfmt_format_currency($currency_format, $quote_amount, $quote_currency_code); ?></td>
                            <td><?php echo $quote_date; ?></td>
                            <td><?php echo $quote_expire; ?></td>
                            <td><?php echo $category_name; ?></td>
                            <td>
                                <span class="p-2 badge badge-<?php echo $quote_badge_color; ?>">
                                    <?php echo $quote_status; ?>
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
                                            data-ajax-url = "ajax/ajax_quote_edit.php"
                                            data-ajax-id = "<?php echo $quote_id; ?>"
                                            >
                                            <i class="fas fa-fw fa-edit mr-2"></i>Edit
                                        </a>
                                        <a class="dropdown-item" href="#" data-toggle="modal" data-target="#addQuoteCopyModal<?php echo $quote_id; ?>">
                                            <i class="fas fa-fw fa-copy mr-2"></i>Copy
                                        </a>
                                        <div class="dropdown-divider"></div>
                                        <?php if (!empty($config_smtp_host)) { ?>
                                            <a class="dropdown-item" href="post.php?email_quote=<?php echo $quote_id; ?>">
                                                <i class="fas fa-fw fa-paper-plane mr-2"></i>Send
                                            </a>
                                            <div class="dropdown-divider"></div>
                                        <?php } ?>
                                        <a class="dropdown-item text-danger text-bold confirm-link" href="post.php?delete_quote=<?php echo $quote_id; ?>&client_id=<?php echo $client_id; ?>">
                                            <i class="fas fa-fw fa-trash mr-2"></i>Delete
                                        </a>
                                    </div>
                                </div>
                            </td>
                        </tr>

                        <?php

                        require "modals/quote_copy_modal.php";

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
require_once "modals/quote_add_modal.php";
require_once "modals/client_quote_export_modal.php";
require_once "includes/footer.php";
