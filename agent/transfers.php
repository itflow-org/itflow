<?php

$sort = "transfer_date";
$order = "DESC";

require_once "includes/inc_all.php";

// Perms
enforceUserPermission('module_financial');

// Account Transfer From Filter
if (isset($_GET['account_from']) & !empty($_GET['account_from'])) {
    $account_from_query = 'AND (expense_account_id = ' . intval($_GET['account_from']) . ')';
    $account_from_filter = intval($_GET['account_from']);
} else {
    // Default - any
    $account_from_query = '';
    $account_from_filter = '';
}

// Account Transfer To Filter
if (isset($_GET['account_to']) & !empty($_GET['account_to'])) {
    $account_to_query = 'AND (revenue_account_id = ' . intval($_GET['account_to']) . ')';
    $account_to_filter = intval($_GET['account_to']);
} else {
    // Default - any
    $account_to_query = '';
    $account_to_filter = '';
}

$sql = mysqli_query(
    $mysqli,
    "SELECT SQL_CALC_FOUND_ROWS transfer_created_at, expense_date AS transfer_date, expense_amount AS transfer_amount, expense_account_id AS transfer_account_from, revenue_account_id AS transfer_account_to, transfer_expense_id, transfer_revenue_id , transfer_id, transfer_method, transfer_notes FROM transfers, expenses, revenues
    WHERE transfer_expense_id = expense_id
    AND transfer_revenue_id = revenue_id
    $account_from_query
    $account_to_query
    AND DATE(expense_date) BETWEEN '$dtf' AND '$dtt'
    AND (transfer_notes LIKE '%$q%' OR transfer_method LIKE '%$q%')
    ORDER BY $sort $order LIMIT $record_from, $record_to"
);

$num_rows = mysqli_fetch_row(mysqli_query($mysqli, "SELECT FOUND_ROWS()"));

?>

    <div class="card">
        <div class="card-header bg-dark py-2">
            <h3 class="card-title mt-2"><i class="fas fa-fw fa-exchange-alt me-2"></i>Transfers</h3>
            <div class="card-tools">
                <button type="button" class="btn btn-primary ajax-modal" data-modal-url="modals/transfer/transfer_add.php"><i class="fas fa-plus me-2"></i>New Transfer</button>
            </div>
        </div>

        <div class="card-header py-3">
            <form autocomplete="off">
                <div class="row g-2 align-items-end">
                    <div class="col-sm-4">
                        <div class="input-group">
                            <input type="search" class="form-control" name="q" value="<?php if (isset($q)) { echo stripslashes(escapeHtml($q)); } ?>" placeholder="Search Transfers">
                                <button class="btn btn-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#advancedFilter"><i class="fas fa-filter"></i></button>
                                <button class="btn btn-primary"><i class="fa fa-search"></i></button>
                        </div>
                    </div>
                </div>
                <div class="collapse mt-3 <?php if (isset($_GET['dtf']) && $_GET['dtf'] !== '1970-01-01' || $account_from_filter || $account_to_filter ) { echo"show"; } ?>" id="advancedFilter">
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
                        <div class="col-sm-2">
                            <div>
                                <label class="form-label">Account From</label>
                                <select class="form-select select2" name="account_from" onchange="this.form.submit()">
                                    <option value="">- All Accounts -</option>

                                    <?php
                                    $sql_accounts_from_filter = mysqli_query($mysqli, "SELECT account_id, account_name FROM accounts WHERE EXISTS (SELECT 1 FROM expenses WHERE expense_account_id = account_id) ORDER BY account_name ASC");
                                    while ($row = mysqli_fetch_assoc($sql_accounts_from_filter)) {
                                        $account_id = intval($row['account_id']);
                                        $account_name = escapeHtml($row['account_name']);
                                    ?>
                                        <option <?php if ($account_from_filter == $account_id) { echo "selected"; } ?> value="<?= $account_id ?>"><?= $account_name ?></option>
                                    <?php
                                    }
                                    ?>

                                </select>
                            </div>
                        </div>
                        <div class="col-sm-2">
                            <div>
                                <label class="form-label">Account To</label>
                                <select class="form-select select2" name="account_to" onchange="this.form.submit()">
                                    <option value="">- All Accounts -</option>

                                    <?php
                                    $sql_accounts_to_filter = mysqli_query($mysqli, "SELECT account_id, account_name FROM accounts WHERE EXISTS (SELECT 1 FROM revenues WHERE revenue_account_id = account_id) ORDER BY account_name ASC");
                                    while ($row = mysqli_fetch_assoc($sql_accounts_to_filter)) {
                                        $account_id = intval($row['account_id']);
                                        $account_name = escapeHtml($row['account_name']);
                                    ?>
                                        <option <?php if ($account_to_filter == $account_id) { echo "selected"; } ?> value="<?= $account_id ?>"><?= $account_name ?></option>
                                    <?php
                                    }
                                    ?>

                                </select>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
        <div class="table-responsive">
            <table class="table table-striped table-borderless table-hover mb-0">
                <thead class="text-dark <?php if ($num_rows[0] == 0) { echo "d-none"; } ?> text-nowrap">
                <tr>
                    <th class="ps-3">
                        <a class="text-dark" href="?<?= $url_query_strings_sort ?>&sort=transfer_date&order=<?= $disp ?>">
                            Date <?php if ($sort == 'transfer_date') { echo $order_icon; } ?>
                        </a>
                    </th>
                    <th>
                        <a class="text-dark" href="?<?= $url_query_strings_sort ?>&sort=transfer_account_from&order=<?= $disp ?>">
                            From Account <?php if ($sort == 'transfer_account_from') { echo $order_icon; } ?>
                        </a>
                    </th>
                    <th>
                        <a class="text-dark" href="?<?= $url_query_strings_sort ?>&sort=transfer_account_to&order=<?= $disp ?>">
                            To Account <?php if ($sort == 'transfer_account_to') { echo $order_icon; } ?>
                        </a>
                    </th>
                    <th>
                        <a class="text-dark" href="?<?= $url_query_strings_sort ?>&sort=transfer_method&order=<?= $disp ?>">
                            Method <?php if ($sort == 'transfer_method') { echo $order_icon; } ?>
                        </a>
                    </th>
                    <th>
                        <a class="text-dark" href="?<?= $url_query_strings_sort ?>&sort=transfer_notes&order=<?= $disp ?>">
                            Notes <?php if ($sort == 'transfer_notes') { echo $order_icon; } ?>
                        </a>
                    </th>
                    <th class="text-end">
                        <a class="text-dark" href="?<?= $url_query_strings_sort ?>&sort=transfer_amount&order=<?= $disp ?>">
                            Amount <?php if ($sort == 'transfer_amount') { echo $order_icon; } ?>
                        </a>
                    </th>
                    <th class="text-center">Action</th>
                </tr>
                </thead>
                <tbody>
                <?php

                while ($row = mysqli_fetch_assoc($sql)) {
                    $transfer_id = intval($row['transfer_id']);
                    $transfer_date = escapeHtml($row['transfer_date']);
                    $transfer_account_from = intval($row['transfer_account_from']);
                    $transfer_account_to = intval($row['transfer_account_to']);
                    $transfer_amount = floatval($row['transfer_amount']);
                    $transfer_method = escapeHtml($row['transfer_method']);
                    if($transfer_method) {
                        $transfer_method_display = $transfer_method;
                    } else {
                        $transfer_method_display = "-";
                    }
                    $transfer_notes = escapeHtml($row['transfer_notes']);
                    if(empty($transfer_notes)) {
                        $transfer_notes_display = "-";
                    } else {
                        $transfer_notes_display = nl2br($transfer_notes);
                    }
                    $transfer_created_at = escapeHtml($row['transfer_created_at']);
                    $expense_id = intval($row['transfer_expense_id']);
                    $revenue_id = intval($row['transfer_revenue_id']);

                    $sql_from = mysqli_query($mysqli, "SELECT account_archived_at, account_name FROM accounts WHERE account_id = $transfer_account_from");
                    $row = mysqli_fetch_assoc($sql_from);
                    $account_name_from = escapeHtml($row['account_name']);
                    $account_from_archived_at = escapeHtml($row['account_archived_at']);
                    if (empty($account_from_archived_at)) {
                        $account_from_archived_display = "";
                    } else {
                        $account_from_archived_display = "Archived - ";
                    }

                    $sql_to = mysqli_query($mysqli, "SELECT account_archived_at, account_name FROM accounts WHERE account_id = $transfer_account_to");
                    $row = mysqli_fetch_assoc($sql_to);
                    $account_name_to = escapeHtml($row['account_name']);
                    $account_to_archived_at = escapeHtml($row['account_archived_at']);
                    if (empty($account_to_archived_at)) {
                        $account_to_archived_display = "";
                    } else {
                        $account_to_archived_display = "Archived - ";
                    }

                    ?>
                    <tr>
                        <td class="ps-3">
                            <a class="text-dark ajax-modal" href="#"
                                data-modal-url = "modals/transfer/transfer_edit.php?id=<?= $transfer_id ?>">
                                <?= $transfer_date ?>
                            </a>
                        </td>
                        <td><?= "$account_from_archived_display$account_name_from" ?></td>
                        <td><?= "$account_to_archived_display$account_name_to" ?></td>
                        <td><?= $transfer_method_display ?></td>
                        <td><?= $transfer_notes_display ?></td>
                        <td class="text-end font-monospace"><?= numfmt_format_currency($currency_format, $transfer_amount, $session_company_currency) ?></td>
                        <td>
                            <div class="dropdown dropstart text-center">
                                <button class="btn btn-secondary btn-sm" type="button" data-bs-toggle="dropdown">
                                    <i class="fas fa-ellipsis-h"></i>
                                </button>
                                <div class="dropdown-menu">
                                    <a class="dropdown-item ajax-modal" href="#"
                                        data-modal-url = "modals/transfer/transfer_edit.php?id=<?= $transfer_id ?>">
                                        <i class="fas fa-fw fa-edit me-2"></i>Edit
                                    </a>
                                    <div class="dropdown-divider"></div>
                                    <a class="dropdown-item text-danger text-bold confirm-link" href="post.php?delete_transfer=<?= $transfer_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>">
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

<?php
require_once "../includes/footer.php";
