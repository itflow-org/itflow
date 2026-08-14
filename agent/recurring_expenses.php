<?php

// Default Column Sortby/Order Filter
$sort = "recurring_expense_next_date";
$order = "ASC";

require_once "includes/inc_all.php";

// Perms
enforceUserPermission('module_financial');

$sql = mysqli_query(
    $mysqli,
    "SELECT SQL_CALC_FOUND_ROWS account_name, category_name, client_name, recurring_expense_account_id,
        recurring_expense_amount, recurring_expense_category_id, recurring_expense_client_id,
        recurring_expense_created_at, recurring_expense_currency_code, recurring_expense_day,
        recurring_expense_description, recurring_expense_frequency, recurring_expense_id,
        recurring_expense_last_sent, recurring_expense_month, recurring_expense_next_date,
        recurring_expense_payment_method, recurring_expense_reference, recurring_expense_status,
        recurring_expense_vendor_id, vendor_name FROM recurring_expenses
    LEFT JOIN categories ON recurring_expense_category_id = category_id
    LEFT JOIN vendors ON recurring_expense_vendor_id = vendor_id
    LEFT JOIN accounts ON recurring_expense_account_id = account_id
    LEFT JOIN clients ON recurring_expense_client_id = client_id
    WHERE DATE(recurring_expense_created_at) BETWEEN '$dtf' AND '$dtt'
    AND (vendor_name LIKE '%$q%' OR client_name LIKE '%$q%' OR category_name LIKE '%$q%' OR account_name LIKE '%$q%' OR recurring_expense_description LIKE '%$q%' OR recurring_expense_amount LIKE '%$q%')
    " . clientScopeSql('recurring_expense_client_id') . "
    ORDER BY $sort $order LIMIT $record_from, $record_to"
);

$num_rows = mysqli_fetch_row(mysqli_query($mysqli, "SELECT FOUND_ROWS()"));

?>

    <div class="card card-dark">
        <div class="card-header py-2">
            <h3 class="card-title mt-2"><i class="fas fa-fw fa-redo-alt me-2"></i>Recurring Expenses</h3>
            <div class="card-tools">
                <button type="button" class="btn btn-primary ajax-modal" data-modal-url="modals/recurring_expense/recurring_expense_add.php" data-modal-size="lg"><i class="fas fa-plus"></i><span class="d-none d-lg-inline ms-2">New Recurring Expense</span></button>
            </div>
        </div>

        <div class="card-body">
            <form class="mb-4" autocomplete="off">
                <div class="row">
                    <div class="col-sm-4">
                        <div class="input-group">
                            <input type="search" class="form-control" name="q" value="<?php if (isset($q)) { echo stripslashes(escapeHtml($q)); } ?>" placeholder="Search Recurring Expenses">
                                <button class="btn btn-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#advancedFilter"><i class="fas fa-filter"></i></button>
                                <button class="btn btn-primary"><i class="fa fa-search"></i></button>
                        </div>
                    </div>
                    <div class="col-sm-8">
                    </div>
                </div>
                <div class="collapse mt-3 <?php if (isset($_GET['dtf']) && $_GET['dtf'] !== '1970-01-01') { echo "show"; } ?>" id="advancedFilter">
                    <div class="row">
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label>Date range</label>
                                <input type="text" id="dateFilter" class="form-control" autocomplete="off">
                                <input type="hidden" name="canned_date" id="canned_date" value="<?= escapeHtml($_GET['canned_date']) ?? '' ?>">
                                <input type="hidden" name="dtf" id="dtf" value="<?= escapeHtml($dtf ?? '') ?>">
                                <input type="hidden" name="dtt" id="dtt" value="<?= escapeHtml($dtt ?? '') ?>">
                            </div>
                        </div>
                    </div>
                </div>
            </form>
            <hr>
            <div class="table-responsive">
                <table class="table table-striped table-borderless table-hover">
                    <thead class="text-dark <?php if ($num_rows[0] == 0) { echo "d-none"; } ?>">
                    <tr>
                        <th>
                            <a class="text-dark" href="?<?= $url_query_strings_sort ?>&sort=recurring_expense_next_date&order=<?= $disp ?>">
                                Next Date <?php if ($sort == 'recurring_expense_next_date') { echo $order_icon; } ?>
                            </a>
                        </th>
                        <th>
                            <a class="text-dark" href="?<?= $url_query_strings_sort ?>&sort=category_name&order=<?= $disp ?>">
                                Category <?php if ($sort == 'category_name') { echo $order_icon; } ?>
                            </a>
                            /
                            <a class="text-secondary" href="?<?= $url_query_strings_sort ?>&sort=recurring_expense_description&order=<?= $disp ?>">
                                Description <?php if ($sort == 'recurring_expense_description') { echo $order_icon; } ?>
                            </a>
                        </th>
                        <th>
                            <a class="text-dark" href="?<?= $url_query_strings_sort ?>&sort=vendor_name&order=<?= $disp ?>">
                                Vendor <?php if ($sort == 'vendor_name') { echo $order_icon; } ?>
                            </a>
                        </th>
                        <th class="text-end">
                            <a class="text-dark" href="?<?= $url_query_strings_sort ?>&sort=recurring_expense_amount&order=<?= $disp ?>">
                                Amount <?php if ($sort == 'recurring_expense_amount') { echo $order_icon; } ?>
                            </a>
                        </th>
                        <th>
                            <a class="text-dark" href="?<?= $url_query_strings_sort ?>&sort=recurring_expense_frequency&order=<?= $disp ?>">
                                Frequency <?php if ($sort == 'recurring_expense_frequency') { echo $order_icon; } ?>
                            </a>
                        </th>
                        <th>
                            <a class="text-dark" href="?<?= $url_query_strings_sort ?>&sort=recurring_expense_last_sent&order=<?= $disp ?>">
                                Last Billed <?php if ($sort == 'recurring_expense_last_sent') { echo $order_icon; } ?>
                            </a>
                        </th>
                        <th>
                            <a class="text-dark" href="?<?= $url_query_strings_sort ?>&sort=account_name&order=<?= $disp ?>">
                                Account  <?php if ($sort == 'account_name') { echo $order_icon; } ?>
                            </a>
                        </th>
                        <th>
                            <a class="text-dark" href="?<?= $url_query_strings_sort ?>&sort=client_name&order=<?= $disp ?>">
                                Client  <?php if ($sort == 'client_name') { echo $order_icon; } ?>
                            </a>
                        </th>
                        <th class="text-center">Action</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php

                    while ($row = mysqli_fetch_assoc($sql)) {
                        $recurring_expense_id = intval($row['recurring_expense_id']);
                        $recurring_expense_frequency = intval($row['recurring_expense_frequency']);
                        if($recurring_expense_frequency == 1) {
                            $recurring_expense_frequency_display = "Monthly";
                        } else {
                            $recurring_expense_frequency_display = "Annually";
                        }
                        $recurring_expense_day = intval($row['recurring_expense_day']);
                        $recurring_expense_month = intval($row['recurring_expense_month']);
                        $recurring_expense_last_sent = escapeHtml($row['recurring_expense_last_sent']);
                        if(empty($recurring_expense_last_sent)) {
                            $recurring_expense_last_sent_display = "-";
                        } else {
                            $recurring_expense_last_sent_display = $recurring_expense_last_sent;
                        }
                        $recurring_expense_next_date = escapeHtml($row['recurring_expense_next_date']);
                        $recurring_expense_next_month = date('n', strtotime($row['recurring_expense_next_date']));
                        $recurring_expense_status = intval($row['recurring_expense_status']);
                        $recurring_expense_description = escapeHtml($row['recurring_expense_description']);
                        $recurring_expense_amount = floatval($row['recurring_expense_amount']);
                        $recurring_expense_payment_method = escapeHtml($row['recurring_expense_payment_method']);
                        $recurring_expense_reference = escapeHtml($row['recurring_expense_reference']);
                        $recurring_expense_currency_code = escapeHtml($row['recurring_expense_currency_code']);
                        $recurring_expense_created_at = escapeHtml($row['recurring_expense_created_at']);
                        $recurring_expense_vendor_id = intval($row['recurring_expense_vendor_id']);
                        $vendor_name = escapeHtml($row['vendor_name']);
                        $recurring_expense_category_id = intval($row['recurring_expense_category_id']);
                        $category_name = escapeHtml($row['category_name']);
                        $account_name = escapeHtml($row['account_name']);
                        $recurring_expense_account_id = intval($row['recurring_expense_account_id']);
                        $client_name = escapeHtml($row['client_name']);
                        if(empty($client_name)) {
                            $client_name_display = "-";
                        } else {
                            $client_name_display = $client_name;
                        }
                        $recurring_expense_client_id = intval($row['recurring_expense_client_id']);

                        ?>

                        <tr>
                            <td>
                                <a class="text-dark ajax-modal" href="#"
                                    data-modal-size = "lg"
                                    data-modal-url = "modals/recurring_expense/recurring_expense_edit.php?id=<?= $recurring_expense_id ?>">
                                    <?= $recurring_expense_next_date ?>
                                </a>
                            </td>
                            <td>
                                <?= $category_name ?>
                                <div class="text-secondary"><small><?= truncate($recurring_expense_description, 60) ?></small></div>
                            </td>
                            <td><?= $vendor_name ?></td>
                            <td class="text-end font-monospace"><?= numfmt_format_currency($currency_format, $recurring_expense_amount, $recurring_expense_currency_code) ?></td>
                            <td><?= $recurring_expense_frequency_display ?></td>
                            <td><?= $recurring_expense_last_sent_display ?></td>
                            <td><?= $account_name ?></td>
                            <td><?= $client_name_display ?></td>
                            <td>
                                <div class="dropdown dropstart text-center">
                                    <button class="btn btn-secondary btn-sm" type="button" data-bs-toggle="dropdown">
                                        <i class="fas fa-ellipsis-h"></i>
                                    </button>
                                    <div class="dropdown-menu">
                                        <a class="dropdown-item ajax-modal" href="#"
                                            data-modal-size="lg"
                                            data-modal-url="modals/recurring_expense/recurring_expense_edit.php?id=<?= $recurring_expense_id ?>">
                                            <i class="fas fa-fw fa-edit me-2"></i>Edit
                                        </a>
                                        <div class="dropdown-divider"></div>
                                        <a class="dropdown-item text-danger text-bold confirm-link" href="post.php?delete_recurring_expense=<?= $recurring_expense_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>">
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
            <?php require_once "../includes/filter_footer.php";
 ?>
        </div>
    </div>

<?php
require_once "../includes/footer.php";
