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
    $client_query = clientScopeSql('invoice_client_id');
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
$row = mysqli_fetch_assoc($sql_total_draft_amount);
$total_draft_amount = floatval($row['total_draft_amount']);

$sql_total_sent_amount = mysqli_query($mysqli, "SELECT SUM(invoice_amount) AS total_sent_amount FROM invoices WHERE invoice_status = 'Sent' $client_query");
$row = mysqli_fetch_assoc($sql_total_sent_amount);
$total_sent_amount = floatval($row['total_sent_amount']);

$sql_total_viewed_amount = mysqli_query($mysqli, "SELECT SUM(invoice_amount) AS total_viewed_amount FROM invoices WHERE invoice_status = 'Viewed' $client_query");
$row = mysqli_fetch_assoc($sql_total_viewed_amount);
$total_viewed_amount = floatval($row['total_viewed_amount']);

$sql_total_cancelled_amount = mysqli_query($mysqli, "SELECT SUM(invoice_amount) AS total_cancelled_amount FROM invoices WHERE invoice_status = 'Cancelled' $client_query");
$row = mysqli_fetch_assoc($sql_total_cancelled_amount);
$total_cancelled_amount = floatval($row['total_cancelled_amount']);

$sql_total_partial_amount = mysqli_query($mysqli, "SELECT SUM(invoice_amount) AS total_partial_amount FROM invoices WHERE invoice_status = 'Partial' $client_query");
$row = mysqli_fetch_assoc($sql_total_partial_amount);
$total_partial_amount = floatval($row['total_partial_amount']);

$sql_total_partial_paid_amount = mysqli_query($mysqli, "SELECT SUM(payment_amount) AS total_partial_paid_amount FROM payments, invoices WHERE payment_invoice_id = invoice_id AND invoice_status = 'Partial' $client_query");
$row = mysqli_fetch_assoc($sql_total_partial_paid_amount);
$total_partial_paid_amount = floatval($row['total_partial_paid_amount']);

$sql_total_overdue_partial_amount = mysqli_query($mysqli, "SELECT SUM(payment_amount) AS total_overdue_partial_amount FROM payments, invoices WHERE payment_invoice_id = invoice_id AND invoice_status = 'Partial' AND invoice_due < CURDATE() $client_query");
$row = mysqli_fetch_assoc($sql_total_overdue_partial_amount);
$total_overdue_partial_amount = floatval($row['total_overdue_partial_amount']);

$sql_total_overdue_amount = mysqli_query($mysqli, "SELECT SUM(invoice_amount) AS total_overdue_amount FROM invoices WHERE invoice_status != 'Draft' AND invoice_status != 'Paid' AND invoice_status != 'Cancelled' AND invoice_status != 'Non-Billable' AND invoice_due < CURDATE() $client_query");
$row = mysqli_fetch_assoc($sql_total_overdue_amount);
$total_overdue_amount = floatval($row['total_overdue_amount']);

$real_overdue_amount = $total_overdue_amount - $total_overdue_partial_amount;
$total_unpaid_amount = $total_sent_amount + $total_viewed_amount + $total_partial_amount - $total_partial_paid_amount;
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
    "SELECT SQL_CALC_FOUND_ROWS category_id, category_name, client_currency_code, client_id, client_name, client_net_terms,
        invoice_amount, invoice_created_at, invoice_currency_code, invoice_date,
        invoice_discount_amount, invoice_due, invoice_id, invoice_number, invoice_prefix,
        invoice_scope, invoice_status, recurring_invoice_id, recurring_invoice_number,
        recurring_invoice_prefix, IFNULL(invoice_payments.amount_paid, 0) AS amount_paid FROM invoices
    LEFT JOIN clients ON invoice_client_id = client_id
    LEFT JOIN categories ON invoice_category_id = category_id
    LEFT JOIN recurring_invoices ON invoice_recurring_invoice_id = recurring_invoice_id
    LEFT JOIN (SELECT payment_invoice_id, SUM(payment_amount) AS amount_paid
               FROM payments
               GROUP BY payment_invoice_id) AS invoice_payments ON payment_invoice_id = invoice_id
    WHERE ($status_query)
    $overdue_query
    $category_query
    AND DATE(invoice_date) BETWEEN '$dtf' AND '$dtt'
    AND (CONCAT(invoice_prefix,invoice_number) LIKE '%$q%' OR invoice_scope LIKE '%$q%' OR client_name LIKE '%$q%' OR invoice_status LIKE '%$q%' OR invoice_amount LIKE '%$q%' OR category_name LIKE '%$q%')
    " . clientScopeSql('invoice_client_id') . "
    $client_query
    ORDER BY $sort $order LIMIT $record_from, $record_to"
);

$num_rows = mysqli_fetch_row(mysqli_query($mysqli, "SELECT FOUND_ROWS()"));

?>

<div class="row">
    <div class="col-lg-4">
        <!-- small box -->
        <a href="?<?= $url_query_strings_sort ?>&status=Draft" class="small-box bg-secondary">
            <div class="inner">
                <h3><?= numfmt_format_currency($currency_format, $total_draft_amount, $session_company_currency) ?></h3>
                <p><?= $draft_count ?> Draft</p>
            </div>
            <div class="icon">
                <i class="fa fa-pencil-ruler"></i>
            </div>
        </a>
    </div>
    <!-- ./col -->

    <div class="col-lg-4">
        <!-- small box -->
        <a href="?<?= $url_query_strings_sort ?>&status=Unpaid" class="small-box bg-info">
            <div class="inner text-white">
                <h3><?= numfmt_format_currency($currency_format, $total_unpaid_amount, $session_company_currency) ?></h3>
                <p><?= $unpaid_count ?> Unpaid</p>
            </div>
            <div class="icon">
                <i class="fa fa-hand-holding-usd"></i>
            </div>
        </a>
    </div>
    <!-- ./col -->

    <div class="col-lg-4">
        <!-- small box -->
        <a href="?<?= $url_query_strings_sort ?>&status=Overdue" class="small-box bg-danger">
            <div class="inner">
                <h3><?= numfmt_format_currency($currency_format, $real_overdue_amount, $session_company_currency) ?></h3>
                <p><?= $overdue_count ?> Overdue</p>
            </div>
            <div class="icon">
                <i class="fa fa-exclamation-triangle"></i>
            </div>
        </a>
    </div>
    <!-- ./col -->

</div>

<div class="card">
    <div class="card-header bg-dark py-2">
        <h3 class="card-title mt-2"><i class="fa fa-fw fa-file-invoice me-2"></i>Invoices</h3>
        <div class="card-tools">
            <div class="btn-group">
                <?php if (lookupUserPermission("module_sales") >= 2) { ?>
                <button type="button" class="btn btn-primary ajax-modal"
                    data-modal-url="modals/invoice/invoice_add.php?<?= $client_url ?>">
                    <i class="fas fa-plus me-2"></i>New Invoice
                </button>
                <?php } ?>
                <button type="button" class="btn btn-primary dropdown-toggle dropdown-toggle-split" data-bs-toggle="dropdown"></button>
                <div class="dropdown-menu">
                    <a class="dropdown-item text-dark ajax-modal" href="#"
                         data-modal-url="<?= buildExportModalUrl('modals/invoice/invoice_export.php', ['client_id', 'status', 'category', 'q'], ['dtf' => $dtf, 'dtt' => $dtt]) ?>">
                        <i class="fa fa-fw fa-download me-2"></i>Export
                    </a>
                    <?php if ($client_url && lookupUserPermission("module_sales") >= 2 && !empty($config_smtp_provider)) { ?>
                        <div class="dropdown-divider"></div>
                        <a class="dropdown-item text-dark ajax-modal" href="#"
                            data-modal-url="modals/client/client_statement.php?client_id=<?= $client_id ?>">
                            <i class="fa fa-fw fa-file-alt me-2"></i>Send Account Statement
                        </a>
                    <?php } ?>
                </div>
            </div>
        </div>
    </div>

    <div class="card-header py-3">
        <form autocomplete="off">
            <input type="hidden" name="status" value="<?php if (isset($_GET['status'])) { echo escapeHtml($_GET['status']); } ?>">
            <?php if ($client_url) { ?>
                <input type="hidden" name="client_id" value="<?= $client_id ?>">
            <?php } ?>
            <div class="row g-2 align-items-end">
                <div class="col-sm-4">
                    <div>
                        <div class="input-group">
                            <input type="search" class="form-control" name="q" value="<?php if (isset($q)) {echo stripslashes(escapeHtml($q));} ?>" placeholder="Search Invoices">
                                <button class="btn btn-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#advancedFilter"><i class="fas fa-filter"></i></button>
                                <button class="btn btn-primary"><i class="fa fa-search"></i></button>
                        </div>
                    </div>
                </div>
                <div class="col-sm-3">
                    <div>
                        <select class="form-select select2" name="category" onchange="this.form.submit()">
                            <option value="">- All Categories -</option>

                            <?php
                            $sql_categories_filter = mysqli_query($mysqli, "SELECT category_id, category_name FROM categories WHERE category_type = 'Income' AND EXISTS (SELECT 1 FROM invoices WHERE invoice_category_id = category_id) ORDER BY category_name ASC");
                            while ($row = mysqli_fetch_assoc($sql_categories_filter)) {
                                $category_id = intval($row['category_id']);
                                $category_name = escapeHtml($row['category_name']);
                            ?>
                                <option <?php if ($category_filter == $category_id) { echo "selected"; } ?> value="<?= $category_id ?>"><?= $category_name ?></option>
                            <?php
                            }
                            ?>

                        </select>
                    </div>
                </div>
                <div class="col-md-5">
                    <div class="btn-group float-end">
                        <div class="dropdown ms-2" id="bulkActionButton" hidden>
                            <button class="btn btn-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                <i class="fas fa-fw fa-layer-group me-2"></i>Bulk Action (<span id="selectedCount">0</span>)
                            </button>
                            <div class="dropdown-menu">
                                <?php if ($client_url && $balance > 0) { ?>
                                    <a class="dropdown-item ajax-modal" href="#"
                                        data-modal-url="modals/payment/payment_bulk_add.php?<?= $client_url ?>">
                                        <i class="fa fa-credit-card me-2"></i>Batch Payment
                                    </a>
                                    <div class="dropdown-divider"></div>
                                <?php } ?>
                                <a class="dropdown-item ajax-modal" href="#"
                                    data-modal-url="modals/invoice/invoice_bulk_edit_category.php"
                                    data-bulk="true">
                                    <i class="fas fa-fw fa-list-ul me-2"></i>Set Category
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="collapse mt-3 <?php if (isset($_GET['dtf']) && $_GET['dtf'] !== '1970-01-01') { echo"show"; } ?>" id="advancedFilter">
                <div class="row g-3">
                    <div class="col-md-3">
                        <div>
                            <label class="form-label">Date range</label>
                            <input type="text" id="dateFilter" class="form-control" autocomplete="off">
                            <input type="hidden" name="canned_date" id="canned_date" value="<?= escapeHtml($_GET['canned_date']) ?? '' ?>">
                            <input type="hidden" name="dtf" id="dtf" value="<?= escapeHtml($dtf ?? '') ?>">
                            <input type="hidden" name="dtt" id="dtt" value="<?= escapeHtml($dtt ?? '') ?>">
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
    <form id="bulkActions" action="post.php" method="post">
        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
        <div class="table-responsive">
            <table class="table table-striped table-borderless table-hover mb-0">
                <thead class="text-dark <?php if ($num_rows[0] == 0) { echo "d-none"; } ?> text-nowrap">
                    <tr>
                        <td class="checkbox-column border-end">
                            <div class="form-check">
                                <input class="form-check-input" id="selectAllCheckbox" type="checkbox" onclick="checkAll(this)">
                            </div>
                        </td>
                        <th>
                            <a class="text-dark" href="?<?= $url_query_strings_sort ?>&sort=invoice_number&order=<?= $disp ?>">
                                Number <?php if ($sort == 'invoice_number') { echo $order_icon; } ?>
                            </a>
                        </th>
                        <th>
                            <a class="text-dark" href="?<?= $url_query_strings_sort ?>&sort=invoice_scope&order=<?= $disp ?>">
                                Scope <?php if ($sort == 'invoice_scope') { echo $order_icon; } ?>
                            </a>
                        </th>
                        <?php if (!$client_url) { ?>
                        <th>
                            <a class="text-dark" href="?<?= $url_query_strings_sort ?>&sort=client_name&order=<?= $disp ?>">
                                Client <?php if ($sort == 'client_name') { echo $order_icon; } ?>
                            </a>
                        </th>
                        <?php } ?>
                        <th class="text-end">
                            <a class="text-dark" href="?<?= $url_query_strings_sort ?>&sort=invoice_amount&order=<?= $disp ?>">
                                Amount <?php if ($sort == 'invoice_amount') { echo $order_icon; } ?>
                            </a>
                        </th>
                        <th>
                            <a class="text-dark" href="?<?= $url_query_strings_sort ?>&sort=invoice_date&order=<?= $disp ?>">
                                Date <?php if ($sort == 'invoice_date') { echo $order_icon; } ?>
                            </a>
                        </th>
                        <th>
                            <a class="text-dark" href="?<?= $url_query_strings_sort ?>&sort=invoice_due&order=<?= $disp ?>">
                                Due <?php if ($sort == 'invoice_due') { echo $order_icon; } ?>
                            </a>
                        </th>
                        <th>
                            <a class="text-dark" href="?<?= $url_query_strings_sort ?>&sort=category_name&order=<?= $disp ?>">
                                Category <?php if ($sort == 'category_name') { echo $order_icon; } ?>
                            </a>
                        </th>
                        <th>
                            <a class="text-dark" href="?<?= $url_query_strings_sort ?>&sort=invoice_status&order=<?= $disp ?>">
                                Status <?php if ($sort == 'invoice_status') { echo $order_icon; } ?>
                            </a>
                        </th>
                        <th>Recurring</th>
                        <th class="text-center">Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php

                while ($row = mysqli_fetch_assoc($sql)) {
                    $invoice_id = intval($row['invoice_id']);
                    $invoice_prefix = escapeHtml($row['invoice_prefix']);
                    $invoice_number = escapeHtml($row['invoice_number']);
                    $invoice_scope = escapeHtml($row['invoice_scope']);
                    if (empty($invoice_scope)) {
                        $invoice_scope_display = "-";
                    } else {
                        $invoice_scope_display = $invoice_scope;
                    }
                    $invoice_status = escapeHtml($row['invoice_status']);
                    $invoice_date = escapeHtml($row['invoice_date']);
                    $invoice_due = escapeHtml($row['invoice_due']);
                    $invoice_discount = floatval($row['invoice_discount_amount']);
                    $invoice_amount = floatval($row['invoice_amount']);
                    $amount_paid = floatval($row['amount_paid']);
                    $invoice_balance = $invoice_amount - $amount_paid;
                    $invoice_currency_code = escapeHtml($row['invoice_currency_code']);
                    $invoice_created_at = escapeHtml($row['invoice_created_at']);
                    $client_id = intval($row['client_id']);
                    $client_name = escapeHtml($row['client_name']);
                    $category_id = intval($row['category_id']);
                    $category_name = escapeHtml($row['category_name']);
                    $client_currency_code = escapeHtml($row['client_currency_code']);
                    $client_net_terms = intval($row['client_net_terms']);
                    if ($client_net_terms == 0) {
                        $client_net_terms = $config_default_net_terms;
                    }
                    $recurring_invoice_id = intval($row['recurring_invoice_id']);
                    $recurring_invoice_prefix = escapeHtml($row['recurring_invoice_prefix']);
                    $recurring_invoice_number = escapeHtml($row['recurring_invoice_number']);
                    if($recurring_invoice_id) {
                        $recurring_invoice_display = "<i class='fas fa-fw fa-redo-alt text-secondary me-1'></i><a href='recurring_invoice.php?recurring_invoice_id=$recurring_invoice_id'>$recurring_invoice_prefix$recurring_invoice_number</a>";
                    } else {
                        $recurring_invoice_display = "-";
                    }

                    $now = time();

                    if (($invoice_status == "Sent" || $invoice_status == "Partial" || $invoice_status == "Viewed") && strtotime($invoice_due) + 86400 < $now) {
                        $overdue_color = "text-danger fw-bold";
                    } else {
                        $overdue_color = "";
                    }

                    $invoice_badge_color = getInvoiceBadgeColor($invoice_status);

                    // Saved Payment Methods
                    $sql_saved_payment_methods = mysqli_query($mysqli, "
                        SELECT 1 FROM client_saved_payment_methods
                        LEFT JOIN payment_providers
                            ON client_saved_payment_methods.saved_payment_provider_id = payment_providers.payment_provider_id
                        WHERE saved_payment_client_id = $client_id
                        AND payment_provider_active = 1;
                    ");

                    ?>

                    <tr>
                        <td class="checkbox-column bg-light border-end">
                            <div class="form-check">
                                <input class="form-check-input bulk-select" type="checkbox" name="invoice_ids[]" value="<?= $invoice_id ?>">
                            </div>
                        </td>
                        <td class="text-bold">
                            <a href="invoice.php?client_id=<?= $client_id ?>&invoice_id=<?= $invoice_id ?>">
                            <?= "$invoice_prefix$invoice_number" ?>
                            </a>
                        </td>
                        <td><?= $invoice_scope_display ?></td>
                        <?php if (!$client_url) { ?>
                        <td class="text-bold"><a href="invoices.php?client_id=<?= $client_id ?>"><?= $client_name ?></a></td>
                        <?php } ?>
                        <td class="text-end font-monospace">
                            <?= numfmt_format_currency($currency_format, $invoice_amount, $invoice_currency_code) ?>
                            <?php if ($amount_paid > 0 && $invoice_balance > 0) { ?>
                                <br><small class="text-danger"><?= numfmt_format_currency($currency_format, $invoice_balance, $invoice_currency_code) ?> due</small>
                            <?php } ?>
                        </td>
                        <td><?= $invoice_date ?></td>
                        <td class="<?= $overdue_color ?>"><?= $invoice_due ?></td>
                        <td><?= $category_name ?></td>
                        <td>
                          <?php if ($invoice_status == 'Paid' || $invoice_status == 'Partial') { ?>
                            <a class="ajax-modal" href="#" title="View payments"
                                data-modal-url="modals/invoice/invoice_payments.php?invoice_id=<?= $invoice_id ?>">
                              <span class="p-2 badge text-bg-<?= $invoice_badge_color ?>">
                                  <?= $invoice_status ?>
                              </span>
                            </a>
                          <?php } else { ?>
                            <span class="p-2 badge text-bg-<?= $invoice_badge_color ?>">
                                <?= $invoice_status ?>
                            </span>
                          <?php } ?>
                        </td>
                        <td><?= $recurring_invoice_display ?></td>
                        <td>
                            <div class="dropdown dropstart text-center">
                                <button class="btn btn-secondary btn-sm" type="button" data-bs-toggle="dropdown">
                                    <i class="fas fa-ellipsis-h"></i>
                                </button>
                                <div class="dropdown-menu">
                                    <?php if ($invoice_status !== 'Paid' && $invoice_status !== 'Cancelled' && $invoice_status !== 'Draft' && $invoice_status !== 'Non-Billable' && $invoice_amount != 0) { ?>
                                        <a class="dropdown-item ajax-modal" href="#"
                                            data-modal-url="modals/payment/payment_add.php?id=<?= $invoice_id ?>">
                                            <i class="fa fa-fw fa-credit-card me-2"></i>Add Payment
                                        </a>
                                        <div class="dropdown-divider"></div>
                                        <?php if (mysqli_num_rows($sql_saved_payment_methods) > 0 && ($invoice_status === 'Sent' || $invoice_status === 'Viewed')) { ?>
                                            <a class="dropdown-item ajax-modal" href="#" data-modal-url="modals/payment/payment_saved_method_add.php?id=<?= $invoice_id ?>"><i class="fas fa-fw fa-wallet me-2"></i>Pay with Saved Card</a>
                                            <div class="dropdown-divider"></div>
                                        <?php } ?>
                                    <?php } ?>
                                    <a class="dropdown-item ajax-modal" href="#"
                                        data-modal-url="modals/invoice/invoice_edit.php?id=<?= $invoice_id ?>">
                                        <i class="fas fa-fw fa-edit me-2"></i>Edit
                                    </a>
                                    <a class="dropdown-item ajax-modal" href="#"
                                        data-modal-url="modals/invoice/invoice_copy.php?id=<?= $invoice_id ?>">
                                        <i class="fas fa-fw fa-copy me-2"></i>Copy
                                    </a>
                                    <div class="dropdown-divider"></div>
                                    <?php if (!empty($config_smtp_provider)) { ?>
                                        <button type="submit" class="dropdown-item confirm-link" form="quickSendInvoice"
                                            data-confirm-title="Send this invoice now?"
                                            data-confirm-text="It goes to the default contacts without opening the picker."
                                            data-confirm-button="Send"
                                            name="invoice_id" value="<?= $invoice_id ?>">
                                            <i class="fas fa-fw fa-bolt me-2"></i>Quick Send
                                        </button>
                                        <a class="dropdown-item ajax-modal" href="#"
                                            data-modal-url="modals/invoice/invoice_email.php?invoice_id=<?= $invoice_id ?>">
                                            <i class="fas fa-fw fa-paper-plane me-2"></i>Send Email<span class="text-muted">...</span>
                                        </a>
                                        <div class="dropdown-divider"></div>
                                    <?php } ?>
                                    <?php if ($invoice_status == 'Draft') { ?>
                                    <a class="dropdown-item ajax-modal" href="#"
                                        data-modal-url="modals/invoice/invoice_mark_sent.php?invoice_id=<?= $invoice_id ?>">
                                        <i class="fas fa-fw fa-check me-2"></i>Mark Sent
                                    </a>
                                    <div class="dropdown-divider"></div>
                                    <?php } ?>
                                    <a class="dropdown-item text-danger text-bold confirm-link" href="post.php?delete_invoice=<?= $invoice_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>">
                                        <i class="fas fa-fw fa-trash me-2"></i>Delete
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
    </form>
    <?php require_once "../includes/filter_footer.php"; ?>
</div>

<?php if (lookupUserPermission("module_sales") >= 2 && !empty($config_smtp_provider)) { ?>
    <?php
    /*
     * One hidden form for the whole page, targeted by the Quick Send buttons via
     * their form="" attribute. It cannot be a form per button: agent/invoices.php
     * wraps its table in a bulkActions form, and a nested form is invalid
     * HTML - the browser drops the inner one and the click silently submits the
     * bulk action instead. The button carries the id as its own name/value, which
     * a submit button contributes to the submission.
     */
    ?>
    <form id="quickSendInvoice" action="post.php" method="post" class="d-none">
        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
        <input type="hidden" name="email_invoice" value="1">
        <input type="hidden" name="quick_send" value="1">
    </form>
<?php } ?>

<script src="../js/bulk_actions.js"></script>

<?php
require_once "../includes/footer.php";
