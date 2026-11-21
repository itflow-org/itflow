<?php

// Default Column Sortby Filter
$sort = "module_name";
$order = "DESC";

require_once "includes/inc_all_admin.php";

// Only a column the list has - another was an SQL error that stopped the page
if (!in_array($sort, ['module_name', 'module_description'], true)) {
    $sort = 'module_name';
}

$sql = mysqli_query(
    $mysqli,
    "SELECT SQL_CALC_FOUND_ROWS module_description, module_id, module_name FROM modules
    WHERE (module_name LIKE '%$q%' OR module_description LIKE '%$q%')
    ORDER BY $sort $order LIMIT $record_from, $record_to"
);

$num_rows = mysqli_fetch_row(mysqli_query($mysqli, "SELECT FOUND_ROWS()"));

?>

<div class="card">
    <div class="card-header bg-dark py-2">
        <h3 class="card-title mt-2"><i class="fas fa-fw fa-puzzle-piece me-2"></i>Access Modules</h3>
        <?php // No New Module, Edit or Delete: modules are built in - there is no form or handler to add or change one ?>
    </div>
    <div class="card-header py-3">
        <form autocomplete="off">
            <div class="row g-2 align-items-center">
                <div class="col-md-4">
                    <div class="input-group">
                        <input type="search" class="form-control" name="q" value="<?php if (isset($q)) {echo stripslashes(escapeHtml($q));} ?>" placeholder="Search modules...">
                            <button class="btn btn-primary" title="Search"><i class="fa fa-search"></i></button>
                    </div>
                </div>
            </div>
        </form>
    </div>
    <div class="table-responsive-sm">
        <table class="table table-striped table-borderless table-hover mb-0">
            <thead class="text-dark <?php if ($num_rows[0] == 0) { echo "d-none"; } ?> text-nowrap">
            <tr>
                <th class="ps-3">
                    <?= sortLink('module_name', 'Module') ?>
                </th>
                <th class="text-center">Action</th>
            </tr>
            </thead>
            <tbody>
            <?php

            while ($row = mysqli_fetch_assoc($sql)) {
                $module_id = intval($row['module_id']);
                $module_name = escapeHtml($row['module_name']);
                $module_description = escapeHtml($row['module_description']);

                ?>
                <tr>
                    <td class="ps-3">
                        <strong class="text-dark"><?= $module_name ?></strong>
                        <div class="text-secondary"><?= $module_description ?></div>
                    </td>
                    <td>
                        <p class='text-center'>N/A Predefined</p>
                    </td>
                </tr>

                <?php

            }

            ?>

            </tbody>
        </table>
    </div>
    <?php
    $empty_state = [
        'thing' => 'modules',
        'icon' => 'fa-puzzle-piece',
    ];
    ?>
    <?php require_once "../includes/filter_footer.php";
        ?>
</div>

<?php

require_once "../includes/footer.php";
