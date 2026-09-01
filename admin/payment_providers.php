<?php

// Default Column Sortby Filter
$sort = "payment_provider_name";
$order = "ASC";

require_once "includes/inc_all_admin.php";

$sql = mysqli_query($mysqli, "SELECT SQL_CALC_FOUND_ROWS account_name, category_name, payment_provider_description, payment_provider_id,
    payment_provider_name, payment_provider_threshold, vendor_name FROM payment_providers
    LEFT JOIN accounts ON payment_provider_account = account_id
    LEFT JOIN vendors ON payment_provider_expense_vendor = vendor_id
    LEFT JOIN categories ON payment_provider_expense_category = category_id
    WHERE payment_provider_name LIKE '%$q%' OR payment_provider_description LIKE '%$q%'
    ORDER BY $sort $order LIMIT $record_from, $record_to"
);

$num_rows = mysqli_fetch_row(mysqli_query($mysqli, "SELECT FOUND_ROWS()"));

?>

<div class="card">
    <div class="card-header bg-dark py-2">
        <h3 class="card-title mt-2"><i class="fas fa-fw fa-credit-card me-2"></i>Payment Providers</h3>
        <div class="card-tools">
            <button type="button" class="btn btn-primary ajax-modal" data-modal-url="modals/payment_provider/payment_provider_add.php"><i class="fas fa-plus me-2"></i>Add Provider</button>
        </div>
    </div>
    <div class="card-header py-3">
        <form autocomplete="off">
            <div class="row g-2 align-items-center">
                <div class="col-md-4">
                    <div class="input-group">
                        <input type="search" class="form-control" name="q" value="<?php if (isset($q)) { echo stripslashes(escapeHtml($q)); } ?>" placeholder="Search Payment Providers">
                        <button class="btn btn-primary"><i class="fa fa-search"></i></button>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <div class="table-responsive-sm">
        <table class="table table-striped table-borderless table-hover mb-0">
            <thead class="text-dark <?php if ($num_rows[0] == 0) { echo "d-none"; } ?>">
            <tr>
                <th class="ps-3">
                    <a class="text-dark" href="?<?= $url_query_strings_sort ?>&sort=payment_provider_name&order=<?= $disp ?>">
                        Provider <?php if ($sort == 'payment_provider_name') { echo $order_icon; } ?>
                    </a>
                </th>
                <th>
                    <a class="text-dark" href="?<?= $url_query_strings_sort ?>&sort=account_name&order=<?= $disp ?>">
                        Expense / Income Account <?php if ($sort == 'account_name') { echo $order_icon; } ?>
                    </a>
                </th>
                <th>
                    <a class="text-dark" href="?<?= $url_query_strings_sort ?>&sort=payment_provider_threshold&order=<?= $disp ?>">
                        Threshold <?php if ($sort == 'payment_provider_threshold') { echo $order_icon; } ?>
                    </a>
                </th>
                <th>
                    <a class="text-dark" href="?<?= $url_query_strings_sort ?>&sort=vendor_name&order=<?= $disp ?>">
                        Expense Vendor <?php if ($sort == 'vendor_name') { echo $order_icon; } ?>
                    </a>
                </th>
                <th>
                    <a class="text-dark" href="?<?= $url_query_strings_sort ?>&sort=category_name&order=<?= $disp ?>">
                        Expense Category <?php if ($sort == 'category_name') { echo $order_icon; } ?>
                    </a>
                </th>
                <th class="text-center">
                    <a class="text-dark">Saved Payment Methods</a>
                </th>
                <th class="text-center">Action</th>
            </tr>
            </thead>
            <tbody>
            <?php

            while ($row = mysqli_fetch_assoc($sql)) {
                $provider_id = intval($row['payment_provider_id']);
                $provider_name = escapeHtml($row['payment_provider_name']);
                $provider_description = escapeHtml($row['payment_provider_description']);
                $account_name = escapeHtml($row['account_name']);
                $threshold = floatval($row['payment_provider_threshold']);
                if (!$threshold) {
                    $threshold = "Not Enforced";
                } else {
                    $threshold = numfmt_format_currency($currency_format, $threshold, $session_company_currency);
                }
                $vendor_name = escapeHtml($row['vendor_name'] ?? "Expense Disabled");
                $category = escapeHtml($row['category_name']);

                $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT('saved_payment_id') AS saved_payment_count FROM client_saved_payment_methods WHERE saved_payment_provider_id = $provider_id"));
                $saved_payment_count = intval($row['saved_payment_count']);

                ?>
                <tr>
                    <td class="ps-3">
                        <a class="text-dark text-bold ajax-modal" href="#"
                            data-modal-url="modals/payment_provider/payment_provider_edit.php?id=<?= $provider_id ?>">
                            <?= $provider_name ?>
                        </a>
                        <span class="text-secondary"><?= $provider_description ?></span>
                    </td>
                    <td><?= $account_name ?></td>
                    <td><?= $threshold ?></td>
                    <td><?= $vendor_name ?></td>
                    <td><?= $category ?></td>
                    <td class="text-center">
                        <a class="badge bg-dark rounded-pill p-2" href="saved_payment_methods.php"><?= $saved_payment_count ?></a>
                    </td>
                    <td>
                        <div class="dropdown dropstart text-center">
                            <button class="btn btn-secondary btn-sm" type="button" data-bs-toggle="dropdown">
                                <i class="fas fa-ellipsis-h"></i>
                            </button>
                            <div class="dropdown-menu">
                                <a class="dropdown-item ajax-modal" href="#"
                                    data-modal-url="modals/payment_provider/payment_provider_edit.php?id=<?= $provider_id ?>">
                                    <i class="fas fa-fw fa-edit me-2"></i>Edit
                                </a>
                                <div class="dropdown-divider"></div>
                                <a class="dropdown-item text-danger confirm-link" href="post.php?delete_payment_provider=<?= $provider_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>">
                                    <i class="fas fa-fw fa-trash me-2"></i><strong>Delete Provider and</strong>
                                    <ul class="text-xs">
                                        <li>Related Recurring Payments</li>
                                        <li>Related Saved cards</li>
                                        <li>Client Provider Relations</li>
                                    </ul>
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
    <?php require_once "../includes/filter_footer.php"; ?>
</div>

<?php
require_once "../includes/footer.php";
