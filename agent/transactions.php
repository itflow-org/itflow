<?php

// Default Column Sortby/Order Filter
$sort = "transaction_date";
$order = "DESC";

require_once "includes/inc_all.php";

// Perms
enforceUserPermission('module_financial');

// Account Filter
if (isset($_GET['account']) & !empty($_GET['account'])) {
    $account_filter = intval($_GET['account']);
} else {
    // Default - none selected
    $account_filter = '';
}

// Type Filter
$transaction_types_array = ['Revenue', 'Payment', 'Expense', 'Transfer In', 'Transfer Out'];
if (isset($_GET['type']) & !empty($_GET['type']) && in_array($_GET['type'], $transaction_types_array)) {
    $type_query = "AND (transaction_type = '" . escapeSql($_GET['type']) . "')";
    $type_filter = escapeSql($_GET['type']);
} else {
    // Default - any
    $type_query = '';
    $type_filter = '';
}

// Category Filter
if (isset($_GET['category']) & !empty($_GET['category'])) {
    $category_query = 'AND (transaction_category_id = ' . intval($_GET['category']) . ')';
    $category_filter = intval($_GET['category']);
} else {
    // Default - any
    $category_query = '';
    $category_filter = '';
}

// Client Filter
if (isset($_GET['client']) & !empty($_GET['client'])) {
    $client_query = 'AND (transaction_client_id = ' . intval($_GET['client']) . ')';
    $client_filter = intval($_GET['client']);
} else {
    // Default - any
    $client_query = '';
    $client_filter = '';
}

// Payment Method Filter
if (isset($_GET['payment_method']) & !empty($_GET['payment_method'])) {
    $payment_method_query = "AND (transaction_payment_method = '" . escapeSql($_GET['payment_method']) . "')";
    $payment_method_filter = escapeSql($_GET['payment_method']);
} else {
    // Default - any
    $payment_method_query = '';
    $payment_method_filter = '';
}

// Amount Range Filter - matched on absolute value so direction doesn't matter (type filter handles direction)
if (isset($_GET['amount_min']) && $_GET['amount_min'] != '') {
    $amount_min_query = 'AND (ABS(transaction_amount) >= ' . floatval($_GET['amount_min']) . ')';
    $amount_min_filter = floatval($_GET['amount_min']);
} else {
    // Default - any
    $amount_min_query = '';
    $amount_min_filter = '';
}
if (isset($_GET['amount_max']) && $_GET['amount_max'] != '') {
    $amount_max_query = 'AND (ABS(transaction_amount) <= ' . floatval($_GET['amount_max']) . ')';
    $amount_max_filter = floatval($_GET['amount_max']);
} else {
    // Default - any
    $amount_max_query = '';
    $amount_max_filter = '';
}

// Balance only makes sense in chronological order - hide the column under any other sort
if ($sort == 'transaction_date') {
    $balance_visible = true;
} else {
    $balance_visible = false;
}

if ($account_filter) {

    // Account details - opening balance feeds the running balance, currency feeds the summary
    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT * FROM accounts WHERE account_id = $account_filter LIMIT 1"));
    $account_currency_code = escapeHtml($row['account_currency_code']);
    $account_opening_balance = floatval($row['opening_balance']);

    // Transfers are stored as a linked expense (from account) + revenue (to account) pair,
    //  so we detect them via the transfers link table and relabel the row accordingly
    // The running balance is calculated over the account's full ledger BEFORE any filters,
    //  so the balance per transaction stays accurate no matter what is filtered out

    $ledger_query =
        "SELECT *,
            $account_opening_balance + SUM(transaction_amount) OVER (ORDER BY transaction_date ASC, transaction_created_at ASC, transaction_type ASC, transaction_id ASC) AS transaction_balance
        FROM (
            SELECT
                CASE WHEN transfer_id IS NOT NULL THEN 'Transfer In' ELSE 'Revenue' END AS transaction_type,
                revenue_id AS transaction_id,
                transfer_id AS transaction_transfer_id,
                0 AS transaction_invoice_id,
                revenue_date AS transaction_date,
                revenue_created_at AS transaction_created_at,
                CASE WHEN transfer_id IS NOT NULL THEN transfer_notes ELSE revenue_description END AS transaction_description,
                from_account.account_name AS transaction_other_account,
                revenue_reference AS transaction_reference,
                revenue_client_id AS transaction_client_id,
                CASE WHEN transfer_id IS NOT NULL THEN transfer_method ELSE revenue_payment_method END AS transaction_payment_method,
                revenue_category_id AS transaction_category_id,
                category_name AS transaction_category,
                revenue_amount AS transaction_amount,
                revenue_currency_code AS transaction_currency_code
            FROM revenues
            LEFT JOIN categories ON revenue_category_id = category_id
            LEFT JOIN transfers ON transfer_revenue_id = revenue_id
            LEFT JOIN expenses AS transfer_expense ON transfer_expense_id = transfer_expense.expense_id
            LEFT JOIN accounts AS from_account ON transfer_expense.expense_account_id = from_account.account_id
            WHERE revenue_account_id = $account_filter
            AND revenue_archived_at IS NULL

            UNION ALL

            SELECT
                CASE WHEN transfer_id IS NOT NULL THEN 'Transfer Out' ELSE 'Expense' END,
                expense_id,
                transfer_id,
                0,
                expense_date,
                expense_created_at,
                CASE WHEN transfer_id IS NOT NULL THEN transfer_notes ELSE expense_description END,
                to_account.account_name,
                expense_reference,
                expense_client_id,
                CASE WHEN transfer_id IS NOT NULL THEN transfer_method ELSE expense_payment_method END,
                expense_category_id,
                category_name,
                -expense_amount,
                expense_currency_code
            FROM expenses
            LEFT JOIN categories ON expense_category_id = category_id
            LEFT JOIN transfers ON transfer_expense_id = expense_id
            LEFT JOIN revenues AS transfer_revenue ON transfer_revenue_id = transfer_revenue.revenue_id
            LEFT JOIN accounts AS to_account ON transfer_revenue.revenue_account_id = to_account.account_id
            WHERE expense_account_id = $account_filter
            AND expense_archived_at IS NULL

            UNION ALL

            SELECT
                'Payment',
                payment_id,
                NULL,
                payment_invoice_id,
                payment_date,
                payment_created_at,
                CONCAT('Payment for Invoice ', invoice_prefix, invoice_number),
                NULL,
                payment_reference,
                invoice_client_id,
                payment_method,
                0,
                'Invoice Payment',
                payment_amount,
                payment_currency_code
            FROM payments
            LEFT JOIN invoices ON payment_invoice_id = invoice_id
            WHERE payment_account_id = $account_filter
            AND payment_archived_at IS NULL
        ) AS ledger";

    $transaction_filter_query =
        "WHERE DATE(transaction_date) BETWEEN '$dtf' AND '$dtt'
        AND (transaction_description LIKE '%$q%' OR transaction_category LIKE '%$q%' OR transaction_reference LIKE '%$q%' OR transaction_other_account LIKE '%$q%' OR transaction_amount LIKE '%$q%')
        $type_query
        $category_query
        $client_query
        $payment_method_query
        $amount_min_query
        $amount_max_query";

    $sql = mysqli_query(
        $mysqli,
        "SELECT SQL_CALC_FOUND_ROWS * FROM (
            $ledger_query
        ) AS transactions
        $transaction_filter_query
        ORDER BY $sort $order LIMIT $record_from, $record_to"
    );

    $num_rows = mysqli_fetch_row(mysqli_query($mysqli, "SELECT FOUND_ROWS()"));

    // Summary - money in / money out / net for the current filtered view, plus current account balance
    $sql_summary = mysqli_query(
        $mysqli,
        "SELECT
            SUM(CASE WHEN transaction_amount > 0 THEN transaction_amount ELSE 0 END) AS total_in,
            SUM(CASE WHEN transaction_amount < 0 THEN transaction_amount ELSE 0 END) AS total_out,
            SUM(transaction_amount) AS total_net
        FROM (
            $ledger_query
        ) AS transactions
        $transaction_filter_query"
    );
    $row = mysqli_fetch_assoc($sql_summary);
    $summary_total_in = floatval($row['total_in']);
    $summary_total_out = floatval($row['total_out']);
    $summary_total_net = floatval($row['total_net']);

} else {
    $num_rows = [0];
}

?>

    <div class="card card-dark">
        <div class="card-header py-2">
            <h3 class="card-title mt-2"><i class="fas fa-fw fa-list-alt mr-2"></i>Transactions</h3>
            <?php if ($account_filter) { ?>
            <div class="card-tools">
                <button type="button" class="btn btn-primary ajax-modal" data-modal-url="modals/transaction/transaction_export.php?account=<?php echo $account_filter; ?>&type=<?php echo urlencode($type_filter); ?>&category=<?php echo $category_filter; ?>&client=<?php echo $client_filter; ?>&payment_method=<?php echo urlencode($payment_method_filter); ?>&amount_min=<?php echo $amount_min_filter; ?>&amount_max=<?php echo $amount_max_filter; ?>&dtf=<?php echo $dtf; ?>&dtt=<?php echo $dtt; ?>&q=<?php echo urlencode($q ?? ''); ?>"><i class="fa fa-download mr-2"></i>Export</button>
            </div>
            <?php } ?>
        </div>

        <div class="card-body">
            <form class="mb-4" autocomplete="off">
                <div class="row">
                    <div class="col-sm-3">
                        <div class="form-group">
                            <label>Search</label>
                            <div class="input-group">
                                <input type="search" class="form-control" name="q" value="<?php if (isset($q)) { echo stripslashes(escapeHtml($q)); } ?>" placeholder="Search Transactions">
                                <div class="input-group-append">
                                    <button class="btn btn-primary"><i class="fa fa-search"></i></button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-3">
                        <div class="form-group">
                            <label>Account</label>
                            <select class="form-control select2" name="account" onchange="this.form.submit()">
                                <option value="">- Select an Account -</option>

                                <?php
                                $sql_accounts_filter = mysqli_query(
                                    $mysqli,
                                    "SELECT account_id, account_name, account_currency_code,
                                        opening_balance
                                        + COALESCE((SELECT SUM(revenue_amount) FROM revenues WHERE revenue_account_id = account_id AND revenue_archived_at IS NULL), 0)
                                        + COALESCE((SELECT SUM(payment_amount) FROM payments WHERE payment_account_id = account_id AND payment_archived_at IS NULL), 0)
                                        - COALESCE((SELECT SUM(expense_amount) FROM expenses WHERE expense_account_id = account_id AND expense_archived_at IS NULL), 0)
                                        AS account_balance
                                    FROM accounts WHERE account_archived_at IS NULL ORDER BY account_name ASC"
                                );
                                while ($row = mysqli_fetch_assoc($sql_accounts_filter)) {
                                    $account_id = intval($row['account_id']);
                                    $account_name = escapeHtml($row['account_name']);
                                    $account_balance_display = numfmt_format_currency($currency_format, floatval($row['account_balance']), $row['account_currency_code']);
                                ?>
                                    <option <?php if ($account_filter == $account_id) { echo "selected"; } ?> value="<?php echo $account_id; ?>"><?php echo "$account_name ($account_balance_display)"; ?></option>
                                <?php
                                }
                                ?>

                            </select>
                        </div>
                    </div>
                    <div class="col-sm-3">
                        <div class="form-group">
                            <label>Type</label>
                            <select class="form-control select2" name="type" onchange="this.form.submit()">
                                <option value="">- All Types -</option>

                                <?php foreach ($transaction_types_array as $transaction_type_option) { ?>
                                    <option <?php if ($type_filter == $transaction_type_option) { echo "selected"; } ?> value="<?php echo $transaction_type_option; ?>"><?php echo $transaction_type_option; ?></option>
                                <?php } ?>

                            </select>
                        </div>
                    </div>
                    <div class="col-sm-3">
                        <div class="form-group">
                            <label>Category</label>
                            <select class="form-control select2" name="category" onchange="this.form.submit()">
                                <option value="">- All Categories -</option>

                                <?php
                                $sql_categories_filter = mysqli_query($mysqli, "SELECT category_id, category_name FROM categories WHERE category_type IN ('Income', 'Expense') AND (EXISTS (SELECT 1 FROM revenues WHERE revenue_category_id = category_id) OR EXISTS (SELECT 1 FROM expenses WHERE expense_category_id = category_id)) ORDER BY category_name ASC");
                                while ($row = mysqli_fetch_assoc($sql_categories_filter)) {
                                    $category_id = intval($row['category_id']);
                                    $category_name = escapeHtml($row['category_name']);
                                ?>
                                    <option <?php if ($category_filter == $category_id) { echo "selected"; } ?> value="<?php echo $category_id; ?>"><?php echo $category_name; ?></option>
                                <?php
                                }
                                ?>

                            </select>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-sm-3">
                        <div class="form-group mb-md-0">
                            <label>Client</label>
                            <select class="form-control select2" name="client" onchange="this.form.submit()">
                                <option value="">- All Clients -</option>

                                <?php
                                $sql_clients_filter = mysqli_query($mysqli, "SELECT client_id, client_name FROM clients WHERE client_archived_at IS NULL AND (EXISTS (SELECT 1 FROM revenues WHERE revenue_client_id = client_id) OR EXISTS (SELECT 1 FROM expenses WHERE expense_client_id = client_id) OR EXISTS (SELECT 1 FROM invoices WHERE invoice_client_id = client_id)) ORDER BY client_name ASC");
                                while ($row = mysqli_fetch_assoc($sql_clients_filter)) {
                                    $client_id = intval($row['client_id']);
                                    $client_name = escapeHtml($row['client_name']);
                                ?>
                                    <option <?php if ($client_filter == $client_id) { echo "selected"; } ?> value="<?php echo $client_id; ?>"><?php echo $client_name; ?></option>
                                <?php
                                }
                                ?>

                            </select>
                        </div>
                    </div>
                    <div class="col-sm-3">
                        <div class="form-group mb-md-0">
                            <label>Payment Method</label>
                            <select class="form-control select2" name="payment_method" onchange="this.form.submit()">
                                <option value="">- All Methods -</option>

                                <?php
                                $sql_payment_methods_filter = mysqli_query($mysqli, "SELECT payment_method_name FROM payment_methods ORDER BY payment_method_name ASC");
                                while ($row = mysqli_fetch_assoc($sql_payment_methods_filter)) {
                                    $payment_method_name = escapeHtml($row['payment_method_name']);
                                ?>
                                    <option <?php if ($payment_method_filter == $payment_method_name) { echo "selected"; } ?> value="<?php echo $payment_method_name; ?>"><?php echo $payment_method_name; ?></option>
                                <?php
                                }
                                ?>

                            </select>
                        </div>
                    </div>
                    <div class="col-sm-3">
                        <div class="form-group mb-md-0">
                            <label>Amount Range</label>
                            <div class="input-group">
                                <input type="number" step="0.01" min="0" class="form-control" name="amount_min" value="<?php echo $amount_min_filter; ?>" placeholder="Min" onchange="this.form.submit()">
                                <input type="number" step="0.01" min="0" class="form-control" name="amount_max" value="<?php echo $amount_max_filter; ?>" placeholder="Max" onchange="this.form.submit()">
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-3">
                        <div class="form-group mb-md-0">
                            <label>Date Range</label>
                            <input type="text" id="dateFilter" class="form-control" autocomplete="off">
                            <input type="hidden" name="canned_date" id="canned_date" value="<?php echo escapeHtml($_GET['canned_date']) ?? ''; ?>">
                            <input type="hidden" name="dtf" id="dtf" value="<?php echo escapeHtml($dtf ?? ''); ?>">
                            <input type="hidden" name="dtt" id="dtt" value="<?php echo escapeHtml($dtt ?? ''); ?>">
                        </div>
                    </div>
                </div>
            </form>
            <hr>

            <?php if ($account_filter) { ?>

            <div class="row">
                <div class="col-lg-4 col-md-6 col-sm-12">
                    <!-- small box -->
                    <div class="small-box bg-success">
                        <div class="inner">
                            <h3><?php echo numfmt_format_currency($currency_format, $summary_total_in, $account_currency_code); ?></h3>
                            <p>Money In</p>
                        </div>
                        <div class="icon">
                            <i class="fa fa-hand-holding-usd"></i>
                        </div>
                    </div>
                </div>
                <!-- ./col -->

                <div class="col-lg-4 col-md-6 col-sm-12">
                    <!-- small box -->
                    <div class="small-box bg-danger">
                        <div class="inner">
                            <h3><?php echo numfmt_format_currency($currency_format, $summary_total_out, $account_currency_code); ?></h3>
                            <p>Money Out</p>
                        </div>
                        <div class="icon">
                            <i class="fa fa-shopping-cart"></i>
                        </div>
                    </div>
                </div>
                <!-- ./col -->

                <div class="col-lg-4 col-md-6 col-sm-12">
                    <!-- small box -->
                    <div class="small-box <?php if ($summary_total_net < 0) { echo "bg-danger"; } else { echo "bg-primary"; } ?>">
                        <div class="inner">
                            <h3><?php echo numfmt_format_currency($currency_format, $summary_total_net, $account_currency_code); ?></h3>
                            <p>Net</p>
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
                    <thead class="text-dark <?php if ($num_rows[0] == 0) { echo "d-none"; } ?>">
                    <tr>
                        <th>
                            <a class="text-dark" href="?<?php echo $url_query_strings_sort; ?>&sort=transaction_date&order=<?php echo $disp; ?>">
                                Date <?php if ($sort == 'transaction_date') { echo $order_icon; } ?>
                            </a>
                        </th>
                        <th>
                            <a class="text-dark" href="?<?php echo $url_query_strings_sort; ?>&sort=transaction_type&order=<?php echo $disp; ?>">
                                Type <?php if ($sort == 'transaction_type') { echo $order_icon; } ?>
                            </a>
                        </th>
                        <th>
                            <a class="text-dark" href="?<?php echo $url_query_strings_sort; ?>&sort=transaction_category&order=<?php echo $disp; ?>">
                                Category <?php if ($sort == 'transaction_category') { echo $order_icon; } ?>
                            </a>
                            /
                            <a class="text-secondary" href="?<?php echo $url_query_strings_sort; ?>&sort=transaction_description&order=<?php echo $disp; ?>">
                                Description <?php if ($sort == 'transaction_description') { echo $order_icon; } ?>
                            </a>
                        </th>
                        <th class="text-right">
                            <a class="text-dark" href="?<?php echo $url_query_strings_sort; ?>&sort=transaction_amount&order=<?php echo $disp; ?>">
                                Amount <?php if ($sort == 'transaction_amount') { echo $order_icon; } ?>
                            </a>
                        </th>
                        <?php if ($balance_visible) { ?>
                        <th class="text-right">Balance</th>
                        <?php } ?>
                    </tr>
                    </thead>
                    <tbody>
                    <?php

                    while ($row = mysqli_fetch_assoc($sql)) {
                        $transaction_id = intval($row['transaction_id']);
                        $transaction_transfer_id = intval($row['transaction_transfer_id']);
                        $transaction_invoice_id = intval($row['transaction_invoice_id']);
                        $transaction_type = escapeHtml($row['transaction_type']);
                        $transaction_date = escapeHtml($row['transaction_date']);
                        $transaction_description = escapeHtml($row['transaction_description']);
                        $transaction_category = escapeHtml($row['transaction_category']);
                        $transaction_other_account = escapeHtml($row['transaction_other_account']);
                        $transaction_amount = floatval($row['transaction_amount']);
                        $transaction_balance = floatval($row['transaction_balance']);
                        $transaction_currency_code = escapeHtml($row['transaction_currency_code']);

                        // Category cell display - transfers show the other account instead of a category
                        if ($transaction_type == 'Transfer In') {
                            $transaction_category_display = "<i class='fas fa-fw fa-arrow-left text-secondary mr-1'></i>From $transaction_other_account";
                        } elseif ($transaction_type == 'Transfer Out') {
                            $transaction_category_display = "<i class='fas fa-fw fa-arrow-right text-secondary mr-1'></i>To $transaction_other_account";
                        } else {
                            $transaction_category_display = $transaction_category;
                        }

                        // Badge color based on type
                        if ($transaction_type == 'Revenue' || $transaction_type == 'Payment') {
                            $transaction_badge_color = "success";
                        } elseif ($transaction_type == 'Expense') {
                            $transaction_badge_color = "danger";
                        } else {
                            $transaction_badge_color = "secondary";
                        }

                        // Amount text color
                        if ($transaction_amount < 0) {
                            $transaction_amount_color = "text-danger";
                        } else {
                            $transaction_amount_color = "text-success";
                        }

                        // Balance text color
                        if ($transaction_balance < 0) {
                            $transaction_balance_color = "text-danger";
                        } else {
                            $transaction_balance_color = "";
                        }

                        // Route the date link to the right edit modal based on type
                        if ($transaction_type == 'Transfer In' || $transaction_type == 'Transfer Out') {
                            $transaction_modal_url = "modals/transfer/transfer_edit.php?id=$transaction_transfer_id";
                        } elseif ($transaction_type == 'Expense') {
                            $transaction_modal_url = "modals/expense/expense_edit.php?id=$transaction_id";
                        } elseif ($transaction_type == 'Revenue') {
                            $transaction_modal_url = "modals/revenue/revenue_edit.php?id=$transaction_id";
                        } else {
                            $transaction_modal_url = "";
                        }

                        ?>

                        <tr>
                            <td>
                                <?php if ($transaction_type == 'Payment') { ?>
                                    <a class="text-bold" href="invoice.php?invoice_id=<?php echo $transaction_invoice_id; ?>">
                                        <?php echo $transaction_date; ?>
                                    </a>
                                <?php } else { ?>
                                    <a class="text-bold ajax-modal" href="#"
                                        data-modal-size="lg"
                                        data-modal-url="<?php echo $transaction_modal_url; ?>">
                                        <?php echo $transaction_date; ?>
                                    </a>
                                <?php } ?>
                            </td>
                            <td><span class="badge badge-<?php echo $transaction_badge_color; ?>"><?php echo $transaction_type; ?></span></td>
                            <td>
                                <?php echo $transaction_category_display; ?>
                                <div class="text-secondary"><small><?php echo truncate($transaction_description, 60); ?></small></div>
                            </td>
                            <td class="text-right text-monospace <?php echo $transaction_amount_color; ?>"><?php echo numfmt_format_currency($currency_format, $transaction_amount, $transaction_currency_code); ?></td>
                            <?php if ($balance_visible) { ?>
                            <td class="text-right text-monospace <?php echo $transaction_balance_color; ?>"><?php echo numfmt_format_currency($currency_format, $transaction_balance, $transaction_currency_code); ?></td>
                            <?php } ?>
                        </tr>

                        <?php
                    }
                    ?>

                    </tbody>
                </table>
            </div>
            <?php require_once "../includes/filter_footer.php"; ?>

            <?php } else { ?>

            <p class="text-secondary mb-0">Select an account above to view its transactions.</p>

            <?php } ?>

        </div>
    </div>

<?php
require_once "../includes/footer.php";