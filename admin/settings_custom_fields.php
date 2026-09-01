<?php

// Default Column Sortby Filter
$sort = "custom_field_label";
$order = "ASC";

require_once "includes/inc_all_admin.php";


if (isset($_GET['table'])) {
    $table = escapeSql($_GET['table']);
} else {
    $table = "client_assets";
}

$sql = mysqli_query(
    $mysqli,
    "SELECT SQL_CALC_FOUND_ROWS * FROM custom_fields
    WHERE custom_field_label LIKE '%$q%'
    AND custom_field_table = '$table'
    ORDER BY $sort $order LIMIT $record_from, $record_to"
);

$num_rows = mysqli_fetch_row(mysqli_query($mysqli, "SELECT FOUND_ROWS()"));

?>


    <div class="card">
        <div class="card-header bg-dark py-2">
            <h3 class="card-title mt-2"><i class="fa fa-fw fa-th-list me-2"></i><?= escapeHtml($table) ?> Fields</h3>
            <div class="card-tools">
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createCustomFieldModal"><i class="fas fa-plus me-2"></i>Create</button>
            </div>
        </div>
        <div class="card-header py-3">
            <form autocomplete="off">
                <input type="hidden" name="table" value="<?= escapeHtml($table) ?>">
                <div class="row g-2 align-items-center">
                    <div class="col-sm-4">
                        <div class="input-group">
                            <input type="search" class="form-control" name="q" value="<?php if (isset($q)) { echo stripslashes(escapeHtml($q)); } ?>" placeholder="Search">
                                <button class="btn btn-primary"><i class="fa fa-search"></i></button>
                        </div>
                    </div>
                    <div class="col-sm-8">
                        <div class="btn-group float-end">
                            <a href="?table=client_assets" class="btn <?php if ($table == 'client_assets') { echo 'btn-primary'; } else { echo 'btn-default'; } ?>">Assets</a>
                            <a href="?table=clients" class="btn <?php if ($table == 'clients') { echo 'btn-primary'; } else { echo 'btn-default'; } ?>">Clients</a>
                        </div>
                    </div>
                </div>
            </form>
        </div>
        <div class="table-responsive-sm">
            <table class="table table-striped table-borderless table-hover mb-0">
                <thead class="text-dark <?php if ($num_rows[0] == 0) { echo "d-none"; } ?>">
                <tr>
                    <th class="ps-3"><a class="text-dark" href="?<?= $url_query_strings_sort ?>&sort=custom_field_label&order=<?= $disp ?>">Label</a></th>
                    <th><a class="text-dark" href="?<?= $url_query_strings_sort ?>&sort=custom_field_type&order=<?= $disp ?>">Type</a></th>
                    <th class="text-center">Action</th>
                </tr>
                </thead>
                <tbody>
                <?php

                while ($row = mysqli_fetch_assoc($sql)) {
                    $custom_field_id = intval($row['custom_field_id']);
                    $custom_field_label = escapeHtml($row['custom_field_label']);
                    $custom_field_type = escapeHtml($row['custom_field_type']);
                    $custom_field_location = intval($row['custom_field_location']);
                    $custom_field_order = intval($row['custom_field_order']);

                    ?>
                    <tr>
                        <td class="ps-3"><a class="text-dark" href="#" data-bs-toggle="modal" data-bs-target="#editCustomFieldModal<?= $custom_field_id ?>"><?= $custom_field_label ?></a></td>
                        <td><?= $custom_field_type ?></td>
                        <td>
                            <div class="dropdown dropstart text-center">
                                <button class="btn btn-secondary btn-sm" type="button" data-bs-toggle="dropdown">
                                    <i class="fas fa-ellipsis-h"></i>
                                </button>
                                <div class="dropdown-menu">
                                    <a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#editCustomFieldModal<?= $custom_field_id ?>">
                                        <i class="fas fa-fw fa-edit me-2"></i>Edit
                                    </a>
                                    <div class="dropdown-divider"></div>
                                    <a class="dropdown-item text-danger text-bold confirm-link" href="post.php?delete_custom_field=<?= $custom_field_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>">
                                        <i class="fas fa-fw fa-trash me-2"></i>Delete
                                    </a>
                                </div>
                            </div>
                        </td>
                    </tr>

                    <?php

                    //$colors_diff = array_diff($colors_array,$colors_used_array);

                    include "custom_field_edit_modal.php";


                }

                ?>

                </tbody>
            </table>
        </div>
        <?php require_once "../includes/filter_footer.php";
 ?>
    </div>

<?php
require_once "custom_field_create_modal.php";

require_once "../includes/footer.php";
