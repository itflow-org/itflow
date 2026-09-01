<?php

// Default Column Sortby Filter
$sort = "payment_method_name";
$order = "ASC";

require_once "includes/inc_all_admin.php";

$sql = mysqli_query($mysqli, "SELECT SQL_CALC_FOUND_ROWS payment_method_created_at, payment_method_description, payment_method_id,
    payment_method_name FROM payment_methods
    WHERE payment_method_name LIKE '%$q%' OR payment_method_description LIKE '%$q%'
    ORDER BY $sort $order LIMIT $record_from, $record_to");

$num_rows = mysqli_fetch_row(mysqli_query($mysqli, "SELECT FOUND_ROWS()"));

?>

<div class="card">
    <div class="card-header bg-dark py-2">
        <h3 class="card-title mt-2"><i class="fas fa-fw fa-credit-card me-2"></i>Payment Methods</h3>
        <div class="card-tools">
            <button type="button" class="btn btn-primary ajax-modal" data-modal-url="modals/payment_method/payment_method_add.php"><i class="fas fa-plus me-2"></i>Add Payment Method</button>
        </div>
    </div>
    <div class="card-header py-3">
        <form autocomplete="off">
            <div class="row g-2 align-items-center">
                <div class="col-md-4">
                    <div class="input-group">
                        <input type="search" class="form-control" name="q" value="<?php if (isset($q)) { echo stripslashes(escapeHtml($q)); } ?>" placeholder="Search Payment Methods">
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
                    <a class="text-dark" href="?<?= $url_query_strings_sort ?>&sort=payment_method_name&order=<?= $disp ?>">
                        Method <?php if ($sort == 'payment_method_name') { echo $order_icon; } ?>
                    </a>
                </th>
                <th>
                    <a class="text-dark" href="?<?= $url_query_strings_sort ?>&sort=payment_method_description&order=<?= $disp ?>">
                        Description <?php if ($sort == 'payment_method_description') { echo $order_icon; } ?>
                    </a>
                </th>
                <th>
                    <a class="text-dark" href="?<?= $url_query_strings_sort ?>&sort=payment_method_created_at&order=<?= $disp ?>">
                        Created at <?php if ($sort == 'payment_method_created_at') { echo $order_icon; } ?>
                    </a>
                </th>
                <th class="text-center">Action</th>
            </tr>
            </thead>
            <tbody>
            <?php

            while ($row = mysqli_fetch_assoc($sql)) {
                $payment_method_id = intval($row['payment_method_id']);
                $payment_method_name = escapeHtml($row['payment_method_name']);
                $payment_method_description = escapeHtml($row['payment_method_description']);
                $payment_method_created_at = escapeHtml($row['payment_method_created_at']);

                ?>
                <tr>
                    <td class="ps-3">
                        <a class="text-dark text-bold ajax-modal" href="#"
                            data-modal-url="modals/payment_method/payment_method_edit.php?id=<?= $payment_method_id ?>">
                            <?= $payment_method_name ?>
                        </a>
                    </td>
                    <td><?= $payment_method_description ?></td>
                    <td><?= $payment_method_created_at ?></td>
                    <td>
                        <div class="dropdown dropstart text-center">
                            <button class="btn btn-secondary btn-sm" type="button" data-bs-toggle="dropdown">
                                <i class="fas fa-ellipsis-h"></i>
                            </button>
                            <div class="dropdown-menu">
                                <a class="dropdown-item ajax-modal" href="#"
                                    data-modal-url="modals/payment_method/payment_method_edit.php?id=<?= $payment_method_id ?>">
                                    <i class="fas fa-fw fa-edit me-2"></i>Edit
                                </a>
                                <div class="dropdown-divider"></div>
                                <a class="dropdown-item text-danger confirm-link" href="post.php?delete_payment_method=<?= $payment_method_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>">
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
    <?php require_once "../includes/filter_footer.php"; ?>
</div>

<?php
require_once "../includes/footer.php";
