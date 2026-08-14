<?php

// Default Column Sortby Filter
$sort = "tax_name";
$order = "ASC";

require_once "includes/inc_all_admin.php";

$sql = mysqli_query(
    $mysqli,
    "SELECT tax_id, tax_name, tax_percent FROM taxes
    WHERE tax_archived_at IS NULL
    ORDER BY $sort $order"
);

$num_rows = mysqli_num_rows($sql);

?>

<div class="card card-dark">
    <div class="card-header py-2">
        <h3 class="card-title mt-2"><i class="fas fa-fw fa-balance-scale me-2"></i>Tax Rates</h3>
        <div class="card-tools">
            <button type="button" class="btn btn-primary ajax-modal" data-modal-url="modals/tax/tax_add.php"><i class="fas fa-plus me-2"></i>New Tax Rate</button>
        </div>
    </div>
    <div class="card-body">
        <div class="table-responsive-sm">
            <table class="table table-striped table-borderless table-hover">
                <thead class="text-dark <?php if ($num_rows == 0) { echo "d-none"; } ?>">
                <tr>
                    <th>
                        <a class="text-dark" href="?<?= $url_query_strings_sort ?>&sort=tax_name&order=<?= $disp ?>">
                            Name <?php if ($sort == 'tax_name') { echo $order_icon; } ?>
                        </a>
                    </th>
                    <th>
                        <a class="text-dark" href="?<?= $url_query_strings_sort ?>&sort=tax_percent&order=<?= $disp ?>">
                            Percent <?php if ($sort == 'tax_percent') { echo $order_icon; } ?>
                        </a>
                    </th>
                    <th class="text-center">Action</th>
                </tr>
                </thead>
                <tbody>
                <?php

                while ($row = mysqli_fetch_assoc($sql)) {
                    $tax_id = intval($row['tax_id']);
                    $tax_name = escapeHtml($row['tax_name']);
                    $tax_percent = floatval($row['tax_percent']);

                    ?>
                    <tr>
                        <td>
                            <a class="text-dark text-bold ajax-modal" href="#"
                                data-modal-url="modals/tax/tax_edit.php?id=<?= $tax_id ?>">
                                <?= $tax_name ?>
                            </a>
                        </td>
                        <td><?= "$tax_percent%" ?></td>
                        <td>
                            <div class="dropdown dropstart text-center">
                                <button class="btn btn-secondary btn-sm" type="button" data-bs-toggle="dropdown">
                                    <i class="fas fa-ellipsis-h"></i>
                                </button>
                                <div class="dropdown-menu">
                                    <a class="dropdown-item ajax-modal" href="#"
                                        data-modal-url="modals/tax/tax_edit.php?id=<?= $tax_id ?>">
                                        <i class="fas fa-fw fa-edit me-2"></i>Edit
                                    </a>
                                    <div class="dropdown-divider"></div>
                                    <a class="dropdown-item text-danger confirm-link" href="post.php?archive_tax=<?= $tax_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>">
                                        <i class="fas fa-fw fa-archive me-2"></i>Archive
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
