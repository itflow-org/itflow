<?php

// Default Column Sortby Filter
$sort = "account_name";
$order = "ASC";

require_once "includes/inc_all.php";

// Perms
enforceUserPermission('module_financial');

$sql = mysqli_query(
    $mysqli,
    "SELECT SQL_CALC_FOUND_ROWS account_currency_code, account_id, account_name, account_notes, opening_balance FROM accounts
    WHERE (account_name LIKE '%$q%')
    AND account_archived_at IS NULL
    ORDER BY $sort $order LIMIT $record_from, $record_to"
);

$num_rows = mysqli_fetch_row(mysqli_query($mysqli, "SELECT FOUND_ROWS()"));

?>

    <div class="card">
        <div class="card-header bg-dark py-2">
            <h3 class="card-title mt-2"><i class="fa fa-fw fa-piggy-bank me-2"></i>Accounts</h3>
            <div class="card-tools">
                <button type="button" class="btn btn-primary ajax-modal" data-modal-url="modals/account/account_add.php"><i class="fas fa-plus me-2"></i>New Account</button>
            </div>
        </div>
        <div class="card-header py-3">
            <form autocomplete="off">
                <div class="input-group">
                    <input type="search" class="form-control" name="q" value="<?php if (isset($q)) { echo stripslashes(escapeHtml($q)); } ?>" placeholder="Search Accounts">
                        <button class="btn btn-primary"><i class="fa fa-search"></i></button>
                </div>
            </form>
        </div>
        <div class="table-responsive">
            <table class="table table-striped table-borderless table-hover mb-0">
                <thead class="text-dark <?php if ($num_rows[0] == 0) { echo "d-none"; } ?>">
                <tr>
                    <th>
                        <a class="text-dark" href="?<?= $url_query_strings_sort ?>&sort=account_name&order=<?= $disp ?>">
                            Name <?php if ($sort == 'account_name') { echo $order_icon; } ?>
                        </a>
                    </th>
                    <th>
                        <a class="text-dark" href="?<?= $url_query_strings_sort ?>&sort=account_currency_code&order=<?= $disp ?>">
                            Currency <?php if ($sort == 'account_currency_code') { echo $order_icon; } ?>
                        </a>
                    </th>
                    <th class="text-end">Balance</th>
                    <th class="text-center">Action</th>
                </tr>
                </thead>
                <tbody>
                <?php

                while ($row = mysqli_fetch_assoc($sql)) {
                    $account_id = intval($row['account_id']);
                    $account_name = escapeHtml($row['account_name']);
                    $opening_balance = floatval($row['opening_balance']);
                    $account_currency_code = escapeHtml($row['account_currency_code']);
                    $account_notes = escapeHtml($row['account_notes']);

                    $sql_payments = mysqli_query($mysqli, "SELECT SUM(payment_amount) AS total_payments FROM payments WHERE payment_account_id = $account_id");
                    $row = mysqli_fetch_assoc($sql_payments);
                    $total_payments = floatval($row['total_payments']);

                    $sql_revenues = mysqli_query($mysqli, "SELECT SUM(revenue_amount) AS total_revenues FROM revenues WHERE revenue_account_id = $account_id");
                    $row = mysqli_fetch_assoc($sql_revenues);
                    $total_revenues = floatval($row['total_revenues']);

                    $sql_expenses = mysqli_query($mysqli, "SELECT SUM(expense_amount) AS total_expenses FROM expenses WHERE expense_account_id = $account_id");
                    $row = mysqli_fetch_assoc($sql_expenses);
                    $total_expenses = floatval($row['total_expenses']);

                    $balance = $opening_balance + $total_payments + $total_revenues - $total_expenses;
                    ?>

                    <tr>
                        <td>
                            <a class="text-dark ajax-modal" href="#"
                                data-modal-url="modals/account/account_edit.php?id=<?= $account_id ?>">
                                <?= $account_name ?>
                            </a>
                        </td>
                        <td><?= $account_currency_code ?></td>
                        <td class="text-end font-monospace"><?= numfmt_format_currency($currency_format, $balance, $account_currency_code) ?></td>
                        <td>
                            <div class="dropdown dropstart text-center">
                                <button class="btn btn-secondary btn-sm" type="button" data-bs-toggle="dropdown">
                                    <i class="fas fa-ellipsis-h"></i>
                                </button>
                                <div class="dropdown-menu">
                                    <a class="dropdown-item ajax-modal" href="#"
                                        data-modal-url="modals/account/account_edit.php?id=<?= $account_id ?>">
                                        <i class="fas fa-fw fa-edit me-2"></i>Edit
                                    </a>
                                    <?php if ($balance == 0 && $account_id != $config_stripe_account) { //Cannot Archive an Account until it reaches 0 Balance and cant be selected as an online account ?>
                                        <div class="dropdown-divider"></div>
                                        <a class="dropdown-item text-danger" href="post.php?archive_account=<?= $account_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>">
                                            <i class="fas fa-fw fa-archive me-2"></i>Archive
                                        </a>
                                    <?php } ?>
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
        <?php require_once "../includes/filter_footer.php"; ?>
    </div>

<?php
require_once "../includes/footer.php";
