<?php

// Default Column Sortby/Order Filter
$sort = "income_date";
$order = "DESC";

// If client_id is in URI then show client Side Bar and client header
if (isset($_GET['client_id'])) {
    require_once "includes/inc_all_client.php";
    $payment_client_query = "AND invoice_client_id = $client_id";
    $revenue_client_query = "AND revenue_client_id = $client_id";
    $client_url = "client_id=$client_id&";
} else {
    require_once "includes/inc_all.php";
    $payment_client_query = '';
    $revenue_client_query = '';
    $client_url = '';
}

// Perms
enforceUserPermission('module_financial');

// Type Filter
$income_types_array = ['Payment', 'Revenue'];
if (isset($_GET['type']) && !empty($_GET['type']) && in_array($_GET['type'], $income_types_array)) {
    $type_query = "AND (income_type = '" . escapeSql($_GET['type']) . "')";
    $type_filter = escapeSql($_GET['type']);
} else {
    // Default - any
    $type_query = '';
    $type_filter = '';
}

// Account Filter
if (isset($_GET['account']) && !empty($_GET['account'])) {
    $account_query = 'AND (income_account_id = ' . intval($_GET['account']) . ')';
    $account_filter = intval($_GET['account']);
} else {
    // Default - any
    $account_query = '';
    $account_filter = '';
}

// Payment Method Filter
if (isset($_GET['method']) && !empty($_GET['method'])) {
    $method_query = "AND (income_method = '" . escapeSql($_GET['method']) . "')";
    $method_filter = escapeHtml($_GET['method']);
} else {
    // Default - any
    $method_query = '';
    $method_filter = '';
}

// Money in comes from two places - payments applied to an invoice, and standalone revenues.
// Transfers between accounts are stored as a linked expense + revenue pair, so the revenue leg
//  is excluded here (transfer_id IS NULL) - moving your own money is not income.
$income_query =
    "SELECT
        'Payment' AS income_type,
        payment_id AS income_id,
        payment_date AS income_date,
        payment_created_at AS income_created_at,
        payment_invoice_id AS income_invoice_id,
        CONCAT(invoice_prefix, invoice_number) AS income_source,
        invoice_client_id AS income_client_id,
        client_name AS income_client,
        payment_amount AS income_amount,
        payment_currency_code AS income_currency_code,
        payment_method AS income_method,
        payment_reference AS income_reference,
        payment_account_id AS income_account_id,
        account_name AS income_account,
        account_archived_at AS income_account_archived
    FROM payments
    LEFT JOIN invoices ON payment_invoice_id = invoice_id
    LEFT JOIN clients ON invoice_client_id = client_id
    LEFT JOIN accounts ON payment_account_id = account_id
    WHERE payment_archived_at IS NULL
    $payment_client_query
    $access_permission_query

    UNION ALL

    SELECT
        'Revenue',
        revenue_id,
        revenue_date,
        revenue_created_at,
        0,
        category_name,
        revenue_client_id,
        client_name,
        revenue_amount,
        revenue_currency_code,
        revenue_payment_method,
        revenue_reference,
        revenue_account_id,
        account_name,
        account_archived_at
    FROM revenues
    LEFT JOIN categories ON revenue_category_id = category_id
    LEFT JOIN clients ON revenue_client_id = client_id
    LEFT JOIN accounts ON revenue_account_id = account_id
    LEFT JOIN transfers ON transfer_revenue_id = revenue_id
    WHERE revenue_archived_at IS NULL
    AND transfer_id IS NULL
    $revenue_client_query";

$income_filter_query =
    "WHERE DATE(income_date) BETWEEN '$dtf' AND '$dtt'
    AND (income_source LIKE '%$q%' OR income_client LIKE '%$q%' OR income_account LIKE '%$q%' OR income_method LIKE '%$q%' OR income_reference LIKE '%$q%' OR income_amount LIKE '%$q%')
    $type_query
    $account_query
    $method_query";

$sql = mysqli_query(
    $mysqli,
    "SELECT SQL_CALC_FOUND_ROWS * FROM (
        $income_query
    ) AS income
    $income_filter_query
    ORDER BY $sort $order LIMIT $record_from, $record_to"
);

$num_rows = mysqli_fetch_row(mysqli_query($mysqli, "SELECT FOUND_ROWS()"));

// Summary - totals for the current filtered view, split by where the money came from
$sql_summary = mysqli_query(
    $mysqli,
    "SELECT
        SUM(CASE WHEN income_type = 'Payment' THEN income_amount ELSE 0 END) AS total_payments,
        SUM(CASE WHEN income_type = 'Revenue' THEN income_amount ELSE 0 END) AS total_revenues,
        SUM(income_amount) AS total_income
    FROM (
        $income_query
    ) AS income
    $income_filter_query"
);
$row = mysqli_fetch_assoc($sql_summary);
$summary_total_payments = floatval($row['total_payments']);
$summary_total_revenues = floatval($row['total_revenues']);
$summary_total_income = floatval($row['total_income']);

?>

    <div class="card card-dark">
        <div class="card-header py-2">
            <h3 class="card-title mt-2"><i class="fas fa-fw fa-hand-holding-usd mr-2"></i>Income</h3>
            <?php if (lookupUserPermission("module_sales") >= 2) { ?>
            <div class="card-tools">
                <button type="button" class="btn btn-primary ajax-modal" data-modal-url="modals/revenue/revenue_add.php" data-modal-size="lg">
                    <i class="fas fa-plus mr-2"></i>New Revenue
                </button>
            </div>
            <?php } ?>
        </div>

        <div class="card-body">
            <form class="mb-4" autocomplete="off">
                <?php if ($client_url) { ?>
                    <input type="hidden" name="client_id" value="<?php echo $client_id; ?>">
                <?php } ?>
                <div class="row">
                    <div class="col-sm-4">
                        <div class="input-group mb-3 mb-sm-0">
                            <input type="search" class="form-control" name="q" value="<?php if (isset($q)) { echo stripslashes(escapeHtml($q)); } ?>" placeholder="Search Income">
                            <div class="input-group-append">
                                <button class="btn btn-secondary" type="button" data-toggle="collapse" data-target="#advancedFilter"><i class="fas fa-filter"></i></button>
                                <button class="btn btn-primary"><i class="fa fa-search"></i></button>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="input-group mb-3 mb-sm-0">
                            <select class="form-control select2" name="type" onchange="this.form.submit()">
                                <option value="">- All Types -</option>
                                <?php foreach ($income_types_array as $income_type_option) { ?>
                                    <option <?php if ($type_filter == $income_type_option) { echo "selected"; } ?>><?php echo $income_type_option; ?></option>
                                <?php } ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="input-group mb-3 mb-sm-0">
                            <select class="form-control select2" name="account" onchange="this.form.submit()">
                                <option value="">- All Accounts -</option>

                                <?php
                                $sql_accounts_filter = mysqli_query($mysqli, "SELECT account_id, account_name FROM accounts
                                    WHERE EXISTS (SELECT 1 FROM payments WHERE payment_account_id = account_id)
                                    OR EXISTS (SELECT 1 FROM revenues WHERE revenue_account_id = account_id)
                                    ORDER BY account_name ASC");
                                while ($row = mysqli_fetch_assoc($sql_accounts_filter)) {
                                    $filter_account_id = intval($row['account_id']);
                                    $filter_account_name = escapeHtml($row['account_name']);
                                ?>
                                    <option <?php if ($account_filter == $filter_account_id) { echo "selected"; } ?> value="<?php echo $filter_account_id; ?>"><?php echo $filter_account_name; ?></option>
                                <?php
                                }
                                ?>

                            </select>
                        </div>
                    </div>

                    <div class="col-sm-2">
                        <div class="input-group">
                            <select class="form-control select2" name="method" onchange="this.form.submit()">
                                <option value="">- All Payment Methods -</option>

                                <?php
                                $sql_payment_methods_filter = mysqli_query($mysqli, "SELECT DISTINCT payment_method AS method FROM payments WHERE payment_method != ''
                                    UNION SELECT DISTINCT revenue_payment_method FROM revenues WHERE revenue_payment_method != ''
                                    ORDER BY method ASC");
                                while ($row = mysqli_fetch_assoc($sql_payment_methods_filter)) {
                                    $filter_method = escapeHtml($row['method']);
                                ?>
                                    <option <?php if ($method_filter == $filter_method) { echo "selected"; } ?>><?php echo $filter_method; ?></option>
                                <?php
                                }
                                ?>

                            </select>
                        </div>
                    </div>
                </div>
                <div class="collapse mt-3 <?php if (isset($_GET['dtf']) && $_GET['dtf'] !== '1970-01-01') { echo "show"; } ?>" id="advancedFilter">
                    <div class="row">
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>Date range</label>
                                <input type="text" id="dateFilter" class="form-control" autocomplete="off">
                                <input type="hidden" name="canned_date" id="canned_date" value="<?php echo escapeHtml($_GET['canned_date']) ?? ''; ?>">
                                <input type="hidden" name="dtf" id="dtf" value="<?php echo escapeHtml($dtf ?? ''); ?>">
                                <input type="hidden" name="dtt" id="dtt" value="<?php echo escapeHtml($dtt ?? ''); ?>">
                            </div>
                        </div>
                    </div>
                </div>
            </form>
            <hr>

            <div class="row">
                <div class="col-lg-4 col-md-6 col-sm-12">
                    <!-- small box -->
                    <div class="small-box bg-primary">
                        <div class="inner">
                            <h3><?php echo numfmt_format_currency($currency_format, $summary_total_payments, $session_company_currency); ?></h3>
                            <p>Invoice Payments</p>
                        </div>
                        <div class="icon">
                            <i class="fa fa-credit-card"></i>
                        </div>
                    </div>
                </div>
                <!-- ./col -->

                <div class="col-lg-4 col-md-6 col-sm-12">
                    <!-- small box -->
                    <div class="small-box bg-info">
                        <div class="inner">
                            <h3><?php echo numfmt_format_currency($currency_format, $summary_total_revenues, $session_company_currency); ?></h3>
                            <p>Other Revenue</p>
                        </div>
                        <div class="icon">
                            <i class="fa fa-hand-holding-usd"></i>
                        </div>
                    </div>
                </div>
                <!-- ./col -->

                <div class="col-lg-4 col-md-6 col-sm-12">
                    <!-- small box -->
                    <div class="small-box bg-success">
                        <div class="inner">
                            <h3><?php echo numfmt_format_currency($currency_format, $summary_total_income, $session_company_currency); ?></h3>
                            <p>Total Income</p>
                        </div>
                        <div class="icon">
                            <i class="fa fa-balance-scale"></i>
                        </div>
                    </div>
                </div>
                <!-- ./col -->
            </div>

            <div class="table-responsive">
                <table class="table table-striped table-borderless table-hover">
                    <thead class="text-dark <?php if ($num_rows[0] == 0) { echo "d-none"; } ?> text-nowrap">
                    <tr>
                        <th>
                            <a class="text-dark" href="?<?php echo $url_query_strings_sort; ?>&sort=income_date&order=<?php echo $disp; ?>">
                                Date <?php if ($sort == 'income_date') { echo $order_icon; } ?>
                            </a>
                        </th>
                        <th>
                            <a class="text-dark" href="?<?php echo $url_query_strings_sort; ?>&sort=income_type&order=<?php echo $disp; ?>">
                                Type <?php if ($sort == 'income_type') { echo $order_icon; } ?>
                            </a>
                        </th>
                        <th>
                            <a class="text-dark" href="?<?php echo $url_query_strings_sort; ?>&sort=income_source&order=<?php echo $disp; ?>">
                                Source <?php if ($sort == 'income_source') { echo $order_icon; } ?>
                            </a>
                        </th>
                        <?php if (!$client_url) { ?>
                        <th>
                            <a class="text-dark" href="?<?php echo $url_query_strings_sort; ?>&sort=income_client&order=<?php echo $disp; ?>">
                                Client <?php if ($sort == 'income_client') { echo $order_icon; } ?>
                            </a>
                        </th>
                        <?php } ?>
                        <th class="text-right">
                            <a class="text-dark" href="?<?php echo $url_query_strings_sort; ?>&sort=income_amount&order=<?php echo $disp; ?>">
                                Amount <?php if ($sort == 'income_amount') { echo $order_icon; } ?>
                            </a>
                        </th>
                        <th>
                            <a class="text-dark" href="?<?php echo $url_query_strings_sort; ?>&sort=income_method&order=<?php echo $disp; ?>">
                                Method <?php if ($sort == 'income_method') { echo $order_icon; } ?>
                            </a>
                        </th>
                        <th>
                            <a class="text-dark" href="?<?php echo $url_query_strings_sort; ?>&sort=income_reference&order=<?php echo $disp; ?>">
                                Reference <?php if ($sort == 'income_reference') { echo $order_icon; } ?>
                            </a>
                        </th>
                        <th>
                            <a class="text-dark" href="?<?php echo $url_query_strings_sort; ?>&sort=income_account&order=<?php echo $disp; ?>">
                                Account <?php if ($sort == 'income_account') { echo $order_icon; } ?>
                            </a>
                        </th>
                        <th class="text-center">Action</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php

                    while ($row = mysqli_fetch_assoc($sql)) {
                        $income_type = escapeHtml($row['income_type']);
                        $income_id = intval($row['income_id']);
                        $income_date = escapeHtml($row['income_date']);
                        $income_invoice_id = intval($row['income_invoice_id']);
                        $income_source = escapeHtml($row['income_source']);
                        $income_client_id = intval($row['income_client_id']);
                        $income_client = escapeHtml($row['income_client']);
                        $income_amount = floatval($row['income_amount']);
                        $income_currency_code = escapeHtml($row['income_currency_code']);
                        $income_method = escapeHtml($row['income_method']);
                        $income_reference = escapeHtml($row['income_reference']);
                        if (empty($income_reference)) {
                            $income_reference_display = "-";
                        } else {
                            $income_reference_display = $income_reference;
                        }
                        $income_account = escapeHtml($row['income_account']);
                        $income_account_archived = escapeHtml($row['income_account_archived']);
                        if (empty($income_account_archived)) {
                            $income_account_archived_display = "";
                        } else {
                            $income_account_archived_display = "Archived - ";
                        }

                        // Each type keeps its own edit modal and delete handler
                        if ($income_type == 'Payment') {
                            $income_edit_modal = "modals/payment/payment_edit.php?id=$income_id";
                            $income_delete_action = "delete_payment=$income_id";
                            $income_type_badge = "badge-primary";
                        } else {
                            $income_edit_modal = "modals/revenue/revenue_edit.php?id=$income_id";
                            $income_delete_action = "delete_revenue=$income_id";
                            $income_type_badge = "badge-info";
                        }

                        ?>

                        <tr>
                            <td>
                                <a class="ajax-modal" href="#"
                                    data-modal-size = "lg"
                                    data-modal-url = "<?php echo $income_edit_modal; ?>">
                                    <?php echo $income_date; ?>
                                </a>
                            </td>
                            <td><span class="badge <?php echo $income_type_badge; ?>"><?php echo $income_type; ?></span></td>
                            <td>
                                <?php if ($income_type == 'Payment' && $income_invoice_id) { ?>
                                    <a href="invoice.php?<?php echo $client_url; ?>invoice_id=<?php echo $income_invoice_id; ?>">
                                        <?php echo $income_source; ?>
                                    </a>
                                <?php } else { ?>
                                    <?php echo $income_source; ?>
                                <?php } ?>
                            </td>
                            <?php if (!$client_url) { ?>
                            <td>
                                <?php if ($income_client_id) { ?>
                                    <a href="income.php?client_id=<?php echo $income_client_id; ?>"><?php echo $income_client; ?></a>
                                <?php } else { ?>
                                    -
                                <?php } ?>
                            </td>
                            <?php } ?>
                            <td class="text-right text-monospace"><?php echo numfmt_format_currency($currency_format, $income_amount, $income_currency_code); ?></td>
                            <td><?php echo $income_method; ?></td>
                            <td><?php echo $income_reference_display; ?></td>
                            <td><?php echo "$income_account_archived_display$income_account"; ?></td>
                            <td>
                                <?php if (lookupUserPermission("module_sales") >= 3) { ?>
                                    <div class="dropdown dropleft text-center">
                                        <button class="btn btn-secondary btn-sm" type="button" data-toggle="dropdown">
                                            <i class="fas fa-ellipsis-h"></i>
                                        </button>
                                        <div class="dropdown-menu">
                                            <a class="dropdown-item ajax-modal" href="#"
                                                data-modal-size = "lg"
                                                data-modal-url = "<?php echo $income_edit_modal; ?>">
                                                <i class="fas fa-fw fa-edit mr-2"></i>Edit
                                            </a>
                                            <div class="dropdown-divider"></div>
                                            <a class="dropdown-item text-danger text-bold confirm-link" href="post.php?<?php echo $income_delete_action; ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>">
                                                <i class="fas fa-fw fa-trash mr-2"></i>Delete
                                            </a>
                                        </div>
                                    </div>
                                <?php } ?>
                            </td>
                        </tr>

                    <?php

                    }

                    ?>

                    </tbody>
                </table>
            </div>
            <?php require_once "../includes/filter_footer.php"; ?>
        </div>
    </div>

<?php
require_once "../includes/footer.php";
