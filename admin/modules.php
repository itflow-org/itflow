<?php

// Default Column Sortby Filter
$sort = "module_name";
$order = "DESC";

require_once "includes/inc_all_admin.php";

$sql = mysqli_query(
    $mysqli,
    "SELECT SQL_CALC_FOUND_ROWS * FROM modules
    WHERE (module_name LIKE '%$q%' OR module_description LIKE '%$q%')
    ORDER BY $sort $order LIMIT $record_from, $record_to"
);

$num_rows = mysqli_fetch_row(mysqli_query($mysqli, "SELECT FOUND_ROWS()"));

?>

<div class="card card-dark">
    <div class="card-header py-2">
        <h3 class="card-title mt-2"><i class="fas fa-fw fa-puzzle-piece mr-2"></i>Access Modules</h3>
        <div class="card-tools">
            <div class="btn-group">
                <button type="button" class="btn btn-primary ajax-modal" data-modal-url="modals/module/module_add.php">
                    <i class="fas fa-fw fa-plus mr-2"></i>New Module
                </button>
            </div>
        </div>
    </div>
    <div class="card-body">
        <form class="mb-4" autocomplete="off">
            <div class="row">
                <div class="col-md-4">
                    <div class="input-group">
                        <input type="search" class="form-control" name="q" value="<?php if (isset($q)) {echo stripslashes(nullable_htmlentities($q));} ?>" placeholder="Search Modules">
                        <div class="input-group-append">
                            <button class="btn btn-primary"><i class="fa fa-search"></i></button>
                        </div>
                    </div>
                </div>
            </div>
        </form>
        <hr>
        <div class="table-responsive-sm">
            <table class="table table-striped table-borderless table-hover">
                <thead class="text-dark <?php if ($num_rows[0] == 0) { echo "d-none"; } ?> text-nowrap">
                <tr>
                    <th>
                        <a class="text-dark" href="?<?php echo $url_query_strings_sort; ?>&sort=module_name&order=<?php echo $disp; ?>">
                            Module <?php if ($sort == 'module_name') { echo $order_icon; } ?>
                        </a>
                    </th>
                    <th class="text-center">Action</th>
                </tr>
                </thead>
                <tbody>
                <?php

                while ($row = mysqli_fetch_array($sql)) {
                    $module_id = intval($row['module_id']);
                    $module_name = nullable_htmlentities($row['module_name']);
                    $module_description = nullable_htmlentities($row['module_description']);

                    ?>
                    <tr>
                        <td>
                            <a href="#" <?php if ($module_id > 6) { ?> class="ajax-modal" data-modal-url="modals/modules/module_edit.php?id=<?= $module_id ?>" <?php } ?>>
                                <strong class="text-dark"><?= $module_name ?></strong>
                            </a>
                            <div class="text-secondary"><?= $module_description ?></div>
                        </td>
                        <td>
                            <?php if ($module_id > 6) { ?>
                                <div class="dropdown dropleft text-center">
                                    <button class="btn btn-secondary btn-sm" type="button" data-toggle="dropdown">
                                        <i class="fas fa-ellipsis-h"></i>
                                    </button>
                                    <div class="dropdown-menu">

                                        <a class="dropdown-item ajax-modal" href="#"
                                            data-modal-url="modals/module/module_edit.php?id=<?= $module_id ?>">
                                            <i class="fas fa-fw fa-user-edit mr-2"></i>Edit
                                        </a>

                                        <div class="dropdown-divider"></div>
                                        <a class="dropdown-item text-danger confirm-link" href="post.php?delete_module=<?= $module_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>">
                                            <i class="fas fa-fw fa-archive mr-2"></i>Delete
                                        </a>

                                    </div>
                                </div>
                            <?php } else { echo "<p class='text-center'>N/A Predefined</p>"; } ?>
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
