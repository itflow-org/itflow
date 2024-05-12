<?php

// Default Column Sortby Filter
$sort = "category_name";
$order = "ASC";

require_once "inc_all_admin.php";


if (isset($_GET['category'])) {
    $category = sanitizeInput($_GET['category']);
} else {
    $category = "Expense";
}

//Rebuild URL
$url_query_strings_sort = http_build_query($get_copy);


$sql = mysqli_query(
    $mysqli,
    "SELECT SQL_CALC_FOUND_ROWS * FROM categories
    WHERE category_name LIKE '%$q%'
    AND category_type = '$category'
    AND category_$archive_query
    ORDER BY $sort $order LIMIT $record_from, $record_to"
);
$num_rows = mysqli_fetch_row(mysqli_query($mysqli, "SELECT FOUND_ROWS()"));

if (isset($_GET['archived'])) {
    $category = "Archived";
}

?>


<div class="card card-dark">
    <div class="card-header py-2">
        <h3 class="card-title mt-2"><i class="fa fa-fw fa-list-ul mr-2"></i>
            <?php echo nullable_htmlentities($category); ?> Categories
        </h3>
        <?php
            if (!isset($_GET['archived'])) {
        ?>
        <div class="card-tools">
            <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#addCategoryModal"><i
                    class="fas fa-plus mr-2"></i>New <?php echo nullable_htmlentities($category); ?> Category</button>
        </div>
        <?php
            }
        ?>
    </div>
    <div class="card-body">
        <form autocomplete="off">
            <input type="hidden" name="category" value="<?php echo nullable_htmlentities($category); ?>">
            <div class="row">
                <div class="col-sm-4 mb-2">
                    <div class="input-group">
                        <input type="search" class="form-control" name="q"
                            value="<?php if (isset($q)) {
                                echo stripslashes(nullable_htmlentities($q));
                            } ?>"
                            placeholder="Search <?php echo nullable_htmlentities($category); ?> Categories ">
                        <div class="input-group-append">
                            <button class="btn btn-primary"><i class="fa fa-search"></i></button>
                        </div>
                    </div>
                </div>
                <div class="col-sm-8">
                    <div class="btn-group float-right">
                        <a href="?category=Expense"
                            class="btn <?php if ($category == 'Expense') {
                                echo 'btn-primary';
                            } else {
                                echo 'btn-default';
                            } ?>">Expense</a>
                        <a href="?category=Income"
                            class="btn <?php if ($category == 'Income') {
                                echo 'btn-primary';
                            } else {
                                echo 'btn-default';
                            } ?>">Income</a>
                        <a href="?category=Referral"
                            class="btn <?php if ($category == 'Referral') {
                                echo 'btn-primary';
                            } else {
                                echo 'btn-default';
                            } ?>">Referral</a>
                        <a href="?category=Payment Method"
                            class="btn <?php if ($category == 'Payment Method') {
                                echo 'btn-primary';
                            } else {
                                echo 'btn-default';
                            } ?>">Payment
                            Method</a>
                        <a href="?archived=1"
                            class="btn <?php if (isset($_GET['archived'])) {
                                echo 'btn-primary';
                            } else {
                                echo 'btn-default';
                            } ?>"><i
                                class="fas fa-fw fa-archive mr-2"></i>Archived</a>
                    </div>
                </div>
            </div>
        </form>
        <hr>
        <div class="table-responsive-sm">
            <table class="table table-striped table-borderless table-hover">
                <thead class="text-dark <?php if ($num_rows[0] == 0) {
                    echo "d-none";
                } ?>">
                    <tr>
                        <th><a class="text-dark"
                                href="?<?php echo $url_query_strings_sort; ?>&sort=category_name&order=<?php echo $disp; ?>">Name</a>
                        </th>
                        <th>Color</th>
                        <th class="text-center">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php

                    while ($row = mysqli_fetch_array($sql)) {
                        $category_id = intval($row['category_id']);
                        $category_name = nullable_htmlentities($row['category_name']);
                        $category_color = nullable_htmlentities($row['category_color']);
                    
                        ?>
                        <tr>
                            <td><a class="text-dark" href="#" data-toggle="modal"
                                    data-target="#editCategoryModal<?php echo $category_id; ?>">
                                    <?php echo $category_name; ?>
                                </a></td>
                            <td><i class="fa fa-3x fa-circle" style="color:<?php echo $category_color; ?>;"></i></td>
                            <td>
                                <div class="dropdown dropleft text-center">
                                    <button class="btn btn-secondary btn-sm" type="button" data-toggle="dropdown">
                                        <i class="fas fa-ellipsis-h"></i>
                                    </button>
                                    <div class="dropdown-menu">
                                        <?php
                                        if ($category == "Archived") {
                                            ?>
                                            <a class="dropdown-item text-success confirm-link"
                                                href="post.php?unarchive_category=<?php echo $category_id; ?>">
                                                <i class="fas fa-fw fa-archive mr-2"></i>Unarchive
                                            </a>
                                            <a class="dropdown-item text-danger confirm-link"
                                                href="post.php?delete_category=<?php echo $category_id; ?>">
                                                <i class="fas fa-fw fa-trash mr-2"></i>Delete
                                            </a>
                                            <?php
                                        } else {
                                            ?>
                                            <a class="dropdown-item" href="#" data-toggle="modal"
                                                data-target="#editCategoryModal<?php echo $category_id; ?>">
                                                <i class="fas fa-fw fa-edit mr-2"></i>Edit
                                            </a>
                                            <a class="dropdown-item text-danger confirm-link"
                                                href="post.php?archive_category=<?php echo $category_id; ?>">
                                                <i class="fas fa-fw fa-archive mr-2"></i>Archive
                                            </a>
                                            <?php
                                        }
                                        ?>
                                    </div>
                                </div>
                            </td>
                        </tr>

                        <?php
                    
                        include "admin_category_edit_modal.php";

                    }

                    ?>

                </tbody>
            </table>
        </div>
        <?php require_once "pagination.php";
 ?>
    </div>
</div>

<?php
require_once "admin_category_add_modal.php";

require_once "footer.php";
