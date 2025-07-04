<?php

// Default Column Sortby Filter
$sort = "payment_provider_name";
$order = "ASC";

require_once "includes/inc_all_admin.php";

$sql = mysqli_query($mysqli, "SELECT * FROM payment_providers 
    LEFT JOIN accounts ON payment_provider_account = account_id
    LEFT JOIN vendors ON payment_provider_expense_vendor = vendor_id
    LEFT JOIN categories ON payment_provider_expense_category = category_id 
    ORDER BY $sort $order"
);

$num_rows = mysqli_num_rows($sql);

?>

<div class="card card-dark">
    <div class="card-header py-2">
        <h3 class="card-title mt-2"><i class="fas fa-fw fa-credit-card mr-2"></i>Payment Providers</h3>
        <div class="card-tools">
            <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#addPaymentProviderModal"><i class="fas fa-plus mr-2"></i>Add Provider</button>
        </div>
    </div>
    <div class="card-body">
        <div class="table-responsive-sm">
            <table class="table table-striped table-borderless table-hover">
                <thead class="text-dark <?php if ($num_rows == 0) { echo "d-none"; } ?>">
                <tr>
                    <th>
                        <a class="text-dark" href="?<?php echo $url_query_strings_sort; ?>&sort=payment_provider_name&order=<?php echo $disp; ?>">
                            Provider <?php if ($sort == 'payment_provider_name') { echo $order_icon; } ?>
                        </a>
                    </th>
                    <th>
                        <a class="text-dark" href="?<?php echo $url_query_strings_sort; ?>&sort=account_name&order=<?php echo $disp; ?>">
                            Account <?php if ($sort == 'account_name') { echo $order_icon; } ?>
                        </a>
                    </th>
                    <th>
                        <a class="text-dark" href="?<?php echo $url_query_strings_sort; ?>&sort=payment_provider_treshold&order=<?php echo $disp; ?>">
                            Threshold <?php if ($sort == 'payment_provider_treshold') { echo $order_icon; } ?>
                        </a>
                    </th>
                    <th>
                        <a class="text-dark" href="?<?php echo $url_query_strings_sort; ?>&sort=vendor_name&order=<?php echo $disp; ?>">
                            Expense Vendor <?php if ($sort == 'vendor_name') { echo $order_icon; } ?>
                        </a>
                    </th>
                    <th>
                        <a class="text-dark" href="?<?php echo $url_query_strings_sort; ?>&sort=category_name&order=<?php echo $disp; ?>">
                            Expense Category <?php if ($sort == 'category_name') { echo $order_icon; } ?>
                        </a>
                    </th>
                    <th>
                        <a class="text-dark">Fee</a>
                    </th>
                    <th class="text-center">Action</th>
                </tr>
                </thead>
                <tbody>
                <?php

                while ($row = mysqli_fetch_array($sql)) {
                    $provider_id = intval($row['payment_provider_id']);
                    $provider_name = nullable_htmlentities($row['payment_provider_name']);
                    $account_name = nullable_htmlentities($row['account_name']);
                    $threshold = floatval($row['payment_provider_treshold']);
                    $vendor_name = nullable_htmlentities($row['vendor_name']);
                    $category = nullable_htmlentities($row['category_name']);
                    $percent_fee = floatval($row['payment_provider_percentage_fee']);
                    $flat_fee = floatval($row['payment_provider_flat_fee']);

                    ?>
                    <tr>
                        <td>
                            <a class="text-dark text-bold" href="#"
                                data-toggle="ajax-modal"
                                data-ajax-url="ajax/ajax_payment_provider_edit.php"
                                data-ajax-id="<?php echo $provider_id; ?>"
                                >
                                <?php echo $provider_name; ?>
                            </a>
                            <span class="text-secondary"><?php echo $provider_description; ?></span>
                        </td>
                        <td><?php echo $account_name; ?></td>
                        <td><?php echo numfmt_format_currency($currency_format, $threshold, $session_company_currency_code); ?></td>
                        <td><?php echo $vendor_name; ?></td>
                        <td><?php echo $category; ?></td>
                        <td><?php echo $percent_fee; ?> + <?php echo numfmt_format_currency($currency_format, $flat_fee, $session_company_currency_code); ?></td>
                        <td>
                            <div class="dropdown dropleft text-center">
                                <button class="btn btn-secondary btn-sm" type="button" data-toggle="dropdown">
                                    <i class="fas fa-ellipsis-h"></i>
                                </button>
                                <div class="dropdown-menu">
                                    <a class="dropdown-item" href="#"
                                        data-toggle="ajax-modal"
                                        data-ajax-url="ajax/ajax_payment_provider_edit.php"
                                        data-ajax-id="<?php echo $provider_id; ?>"
                                        >
                                        <i class="fas fa-fw fa-edit mr-2"></i>Edit
                                    </a>
                                    <div class="dropdown-divider"></div>
                                    <a class="dropdown-item text-danger confirm-link" href="post.php?disable_payment_provicer=<?php echo $provider_id; ?>&csrf_token=<?php echo $_SESSION['csrf_token'] ?>">
                                        <i class="fas fa-fw fa-thumbs-down mr-2"></i>Disable
                                    </a>
                                </div>
                            </div>
                        </td>
                    </tr>

                    <?php

                }

                if ($num_rows == 0) {
                    echo "<h3 class='text-secondary mt-3' style='text-align: center'>No Records Here</h3>";
                }

                ?>

                </tbody>
            </table>

        </div>
    </div>
</div>

<?php
require_once "modals/admin_payment_provider_add_modal.php";
require_once "includes/footer.php";
