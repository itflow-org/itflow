<?php

// Default Column Sortby Filter
$sort = "payment_method_name";
$order = "ASC";

require_once "includes/inc_all_admin.php";

$sql = mysqli_query($mysqli, "SELECT * FROM payment_methods ORDER BY $sort $order");

$num_rows = mysqli_num_rows($sql);

?>

<div class="card card-dark">
    <div class="card-header py-2">
        <h3 class="card-title mt-2"><i class="fas fa-fw fa-credit-card mr-2"></i>Payment Methods</h3>
        <div class="card-tools">
            <button type="button" class="btn btn-primary ajax-modal" data-modal-url="modals/payment_method/payment_method_add.php"><i class="fas fa-plus mr-2"></i>Add Payment Method</button>
        </div>
    </div>
    <div class="card-body">
        <div class="table-responsive-sm">
            <table class="table table-striped table-borderless table-hover">
                <thead class="text-dark <?php if ($num_rows == 0) { echo "d-none"; } ?>">
                <tr>
                    <th>
                        <a class="text-dark" href="?<?php echo $url_query_strings_sort; ?>&sort=payment_method_name&order=<?php echo $disp; ?>">
                            Method <?php if ($sort == 'payment_method_name') { echo $order_icon; } ?>
                        </a>
                    </th>
                    <th>
                        <a class="text-dark" href="?<?php echo $url_query_strings_sort; ?>&sort=payment_method_description&order=<?php echo $disp; ?>">
                            Description <?php if ($sort == 'payment_method_description') { echo $order_icon; } ?>
                        </a>
                    </th>
                    <th>
                        <a class="text-dark" href="?<?php echo $url_query_strings_sort; ?>&sort=payment_method_created_at&order=<?php echo $disp; ?>">
                            Created at <?php if ($sort == 'payment_method_created_at') { echo $order_icon; } ?>
                        </a>
                    </th>
                    <th class="text-center">Action</th>
                </tr>
                </thead>
                <tbody>
                <?php

                while ($row = mysqli_fetch_array($sql)) {
                    $payment_method_id = intval($row['payment_method_id']);
                    $payment_method_name = nullable_htmlentities($row['payment_method_name']);
                    $payment_method_description = nullable_htmlentities($row['payment_method_description']);
                    $payment_method_created_at = nullable_htmlentities($row['payment_method_created_at']);

                    ?>
                    <tr>
                        <td>
                            <a class="text-dark text-bold ajax-modal" href="#"
                                data-modal-url="modals/payment_method/payment_method_edit.php?id=<?= $payment_method_id ?>">
                                <?php echo $payment_method_name; ?>
                            </a>
                        </td>
                        <td><?php echo $payment_method_description; ?></td>
                        <td><?php echo $payment_method_created_at; ?></td>
                        <td>
                            <div class="dropdown dropleft text-center">
                                <button class="btn btn-secondary btn-sm" type="button" data-toggle="dropdown">
                                    <i class="fas fa-ellipsis-h"></i>
                                </button>
                                <div class="dropdown-menu">
                                    <a class="dropdown-item ajax-modal" href="#"
                                        data-modal-url="modals/payment_method/payment_method_edit.php?id=<?= $payment_method_id ?>">
                                        <i class="fas fa-fw fa-edit mr-2"></i>Edit
                                    </a>
                                    <div class="dropdown-divider"></div>
                                    <a class="dropdown-item text-danger confirm-link" href="post.php?delete_payment_method=<?php echo $payment_method_id; ?>&csrf_token=<?php echo $_SESSION['csrf_token'] ?>">
                                        <i class="fas fa-fw fa-trash mr-2"></i>Delete
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
require_once "../includes/footer.php";
