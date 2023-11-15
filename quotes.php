<?php

// Default Column Sortby/Order Filter
$sort = "quote_number";
$order = "DESC";

require_once "inc_all.php";


//Rebuild URL
$url_query_strings_sort = http_build_query($get_copy);

$sql = mysqli_query(
    $mysqli,
    "SELECT SQL_CALC_FOUND_ROWS * FROM quotes
    LEFT JOIN clients ON quote_client_id = client_id
    LEFT JOIN categories ON quote_category_id = category_id
    WHERE (CONCAT(quote_prefix,quote_number) LIKE '%$q%' OR quote_scope LIKE '%$q%' OR category_name LIKE '%$q%' OR quote_status LIKE '%$q%' OR quote_amount LIKE '%$q%' OR client_name LIKE '%$q%')
    AND DATE(quote_date) BETWEEN '$dtf' AND '$dtt'
    ORDER BY $sort $order LIMIT $record_from, $record_to");

$num_rows = mysqli_fetch_row(mysqli_query($mysqli, "SELECT FOUND_ROWS()"));

?>

    <div class="card card-dark">
        <div class="card-header py-2">
            <h3 class="card-title mt-2"><i class="fa fa-comment-dollar mr-2"></i>Quotes</h3>
            <div class="card-tools">
                <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#addQuoteModal"><i class="fas fa-plus mr-2"></i>New Quote</button>
            </div>
        </div>

        <div class="card-body">
            <form class="mb-4" autocomplete="off">
                <div class="row">
                    <div class="col-sm-4">
                        <div class="input-group">
                            <input type="search" class="form-control" name="q" value="<?php if (isset($q)) { echo stripslashes(nullable_htmlentities($q)); } ?>" placeholder="Search Quotes">
                            <div class="input-group-append">
                                <button class="btn btn-secondary" type="button" data-toggle="collapse" data-target="#advancedFilter"><i class="fas fa-filter"></i></button>
                                <button class="btn btn-primary"><i class="fa fa-search"></i></button>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-8">
                        <div class="float-right">
                            
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
                        <th><a class="text-dark" href="?<?php echo $url_query_strings_sort; ?>&sort=quote_number&order=<?php echo $disp; ?>">Number</a></th>
                        <th><a class="text-dark" href="?<?php echo $url_query_strings_sort; ?>&sort=quote_scope&order=<?php echo $disp; ?>">Scope</a></th>
                        <th><a class="text-dark" href="?<?php echo $url_query_strings_sort; ?>&sort=client_name&order=<?php echo $disp; ?>">Client</a></th>
                        <th class="text-right"><a class="text-dark" href="?<?php echo $url_query_strings_sort; ?>&sort=quote_amount&order=<?php echo $disp; ?>">Amount</a></th>
                        <th><a class="text-dark" href="?<?php echo $url_query_strings_sort; ?>&sort=quote_date&order=<?php echo $disp; ?>">Date</a></th>
                        <th><a class="text-dark" href="?<?php echo $url_query_strings_sort; ?>&sort=quote_expire&order=<?php echo $disp; ?>">Expire</a></th>
                        <th><a class="text-dark" href="?<?php echo $url_query_strings_sort; ?>&sort=category_name&order=<?php echo $disp; ?>">Category</a></th>
                        <th><a class="text-dark" href="?<?php echo $url_query_strings_sort; ?>&sort=quote_status&order=<?php echo $disp; ?>">Status</a></th>
                        <th class="text-center">Action</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php

                    while ($row = mysqli_fetch_array($sql)) {
                        $quote_id = intval($row['quote_id']);
                        $quote_prefix = nullable_htmlentities($row['quote_prefix']);
                        $quote_number = intval($row['quote_number']);
                        $quote_scope = nullable_htmlentities($row['quote_scope']);
                        if (empty($quote_scope)) {
                            $quote_scope_display = "-";
                        } else {
                            $quote_scope_display = $quote_scope;
                        }
                        $quote_status = nullable_htmlentities($row['quote_status']);
                        $quote_date = nullable_htmlentities($row['quote_date']);
                        $quote_expire = nullable_htmlentities($row['quote_expire']);
                        $quote_amount = floatval($row['quote_amount']);
                        $quote_discount = floatval($row['quote_discount']);
                        $quote_currency_code = nullable_htmlentities($row['quote_currency_code']);
                        $quote_created_at = nullable_htmlentities($row['quote_created_at']);
                        $client_id = intval($row['client_id']);
                        $client_name = nullable_htmlentities($row['client_name']);
                        $client_currency_code = nullable_htmlentities($row['client_currency_code']);
                        $category_id = intval($row['category_id']);
                        $category_name = nullable_htmlentities($row['category_name']);
                        $client_net_terms = intval($row['client_net_terms']);
                        if ($client_net_terms == 0) {
                            $client_net_terms = $config_default_net_terms;
                        }

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
                            <td class="text-bold"><a href="quote.php?quote_id=<?php echo $quote_id; ?>"><?php echo "$quote_prefix$quote_number"; ?></a></td>
                            <td><?php echo $quote_scope_display; ?></td>
                            <td class="text-bold"><a href="client_quotes.php?client_id=<?php echo $client_id; ?>"><?php echo $client_name; ?></a></td>
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
                                        <a class="dropdown-item" href="#" data-toggle="modal" onclick="populateQuoteEditModal(<?php echo $quote_id ?>)" data-target="#editQuoteModal">
                                            <i class="fas fa-fw fa-edit mr-2"></i>Edit
                                        </a>
                                        <a class="dropdown-item" href="#" data-toggle="modal" data-target="#addQuoteCopyModal<?php echo $quote_id; ?>">
                                            <i class="fas fa-fw fa-copy mr-2"></i>Copy
                                        </a>
                                        <div class="dropdown-divider"></div>
                                        <?php if (!empty($config_smtp_host)) { ?>
                                            <a class="dropdown-item" href="post.php?email_quote=<?php echo $quote_id; ?>">
                                                <i class="fas fa-fw fa-paper-plane mr-2"></i>Email
                                            </a>
                                            <div class="dropdown-divider"></div>
                                        <?php } ?>
                                        <a class="dropdown-item text-danger text-bold confirm-link" href="post.php?delete_quote=<?php echo $quote_id; ?>">
                                            <i class="fas fa-fw fa-trash mr-2"></i>Delete
                                        </a>
                                    </div>
                                </div>
                            </td>
                        </tr>

                        <?php

                        require "quote_copy_modal.php";


                    }

                    ?>

                    </tbody>
                </table>
            </div>
            <?php require_once "pagination.php";
 ?>
        </div>
    </div>

<?php

require_once "quote_add_modal.php";

require_once "quote_edit_modal.php";

require_once "footer.php";

