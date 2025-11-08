<?php

// Default Column Sortby Filter
$sort = "tax_name";
$order = "ASC";

require_once "includes/inc_all_admin.php";

$sql = mysqli_query(
    $mysqli,
    "SELECT * FROM taxes
    WHERE tax_archived_at IS NULL
    ORDER BY $sort $order"
);

$num_rows = mysqli_num_rows($sql);

?>

<div class="card card-dark">
    <div class="card-header py-2">
        <h3 class="card-title mt-2"><i class="fas fa-fw fa-balance-scale mr-2"></i>Taxes</h3>
        <div class="card-tools">
            <button type="button" class="btn btn-primary ajax-modal" data-modal-url="modals/tax/tax_add.php"><i class="fas fa-plus mr-2"></i>New Tax</button>
        </div>
    </div>
    <div class="card-body">
        <div class="table-responsive-sm">
            <table class="table table-striped table-borderless table-hover">
                <thead class="text-dark <?php if ($num_rows == 0) { echo "d-none"; } ?>">
                <tr>
                    <th>
                        <a class="text-dark" href="?<?php echo $url_query_strings_sort; ?>&sort=tax_name&order=<?php echo $disp; ?>">
                            Name <?php if ($sort == 'tax_name') { echo $order_icon; } ?>
                        </a>
                    </th>
                    <th>
                        <a class="text-dark" href="?<?php echo $url_query_strings_sort; ?>&sort=tax_percent&order=<?php echo $disp; ?>">
                            Percent <?php if ($sort == 'tax_percent') { echo $order_icon; } ?>
                        </a>
                    </th>
                    <th class="text-center">Action</th>
                </tr>
                </thead>
                <tbody>
                <?php

                while ($row = mysqli_fetch_array($sql)) {
                    $tax_id = intval($row['tax_id']);
                    $tax_name = nullable_htmlentities($row['tax_name']);
                    $tax_percent = floatval($row['tax_percent']);

                    ?>
                    <tr>
                        <td>
                            <a class="text-dark text-bold ajax-modal" href="#"
                                data-modal-url="modals/tax/tax_edit.php?id=<?= $tax_id ?>">
                                <?php echo $tax_name; ?>
                            </a>
                        </td>
                        <td><?php echo "$tax_percent%"; ?></td>
                        <td>
                            <div class="dropdown dropleft text-center">
                                <button class="btn btn-secondary btn-sm" type="button" data-toggle="dropdown">
                                    <i class="fas fa-ellipsis-h"></i>
                                </button>
                                <div class="dropdown-menu">
                                    <a class="dropdown-item ajax-modal" href="#"
                                        data-modal-url="modals/tax/tax_edit.php?id=<?= $tax_id ?>">
                                        <i class="fas fa-fw fa-edit mr-2"></i>Edit
                                    </a>
                                    <div class="dropdown-divider"></div>
                                    <a class="dropdown-item text-danger confirm-link" href="post.php?archive_tax=<?php echo $tax_id; ?>&csrf_token=<?php echo $_SESSION['csrf_token'] ?>">
                                        <i class="fas fa-fw fa-archive mr-2"></i>Archive
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
