<?php

// Default Column Sortby/Order Filter
$sort = "product_name";
$order = "ASC";

require_once "includes/inc_all.php";

// Perms
enforceUserPermission('module_sales');

// Category Filter
if (isset($_GET['category']) & !empty($_GET['category'])) {
    $category_query = 'AND (category_id = ' . intval($_GET['category']) . ')';
    $category_filter = intval($_GET['category']);
} else {
    // Default - any
    $category_query = '';
    $category_filter = '';
}

//Rebuild URL
$url_query_strings_sort = http_build_query($get_copy);

$sql = mysqli_query(
    $mysqli,
    "SELECT SQL_CALC_FOUND_ROWS * FROM products
    LEFT JOIN categories ON product_category_id = category_id
    LEFT JOIN taxes ON product_tax_id = tax_id
    WHERE (product_name LIKE '%$q%' OR product_description LIKE '%$q%' OR category_name LIKE '%$q%' OR product_price LIKE '%$q%' OR tax_name LIKE '%$q%' OR tax_percent LIKE '%$q%')
    AND product_$archive_query
    $category_query
    
    
    ORDER BY $sort $order LIMIT $record_from, $record_to"
);

$num_rows = mysqli_fetch_row(mysqli_query($mysqli, "SELECT FOUND_ROWS()"));

?>

    <div class="card card-dark">
        <div class="card-header py-2">
            <h3 class="card-title mt-2"><i class="fas fa-fw fa-box-open mr-2"></i>Products</h3>
            <div class="card-tools">
                <div class="btn-group">
                    <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#addProductModal"><i class="fas fa-plus mr-2"></i>New Product</button>
                    <button type="button" class="btn btn-primary dropdown-toggle dropdown-toggle-split" data-toggle="dropdown"></button>
                    <div class="dropdown-menu">
                        <a class="dropdown-item text-dark" href="#" data-toggle="modal" data-target="#exportProductsModal">
                            <i class="fa fa-fw fa-download mr-2"></i>Export
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <div class="card-body">
            <form class="mb-4" autocomplete="off">
                <input type="hidden" name="archived" value="<?php echo $archived; ?>">
                <div class="row">
                    <div class="col-sm-4">
                        <div class="input-group mb-3 mb-sm-0">
                            <input type="search" class="form-control" name="q" value="<?php if (isset($q)) {echo stripslashes(nullable_htmlentities($q));} ?>" placeholder="Search Products">
                            <div class="input-group-append">
                                <button class="btn btn-primary"><i class="fa fa-search"></i></button>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-3">
                        <div class="input-group mb-3 mb-sm-0">
                            <select class="form-control select2" name="category" onchange="this.form.submit()">
                                <option value="">- All Categories -</option>

                                <?php
                                $sql_categories_filter = mysqli_query($mysqli, "SELECT * FROM categories WHERE category_type = 'Income' ORDER BY category_name ASC");
                                while ($row = mysqli_fetch_array($sql_categories_filter)) {
                                    $category_id = intval($row['category_id']);
                                    $category_name = nullable_htmlentities($row['category_name']);
                                ?>
                                    <option <?php if ($category_filter == $category_id) { echo "selected"; } ?> value="<?php echo $category_id; ?>"><?php echo $category_name; ?></option>
                                <?php
                                }
                                ?>

                            </select>
                            <div class="input-group-append">
                                <button class="btn btn-secondary" type="button"
                                    data-toggle="ajax-modal"
                                    data-modal-size="sm"
                                    data-ajax-url="ajax/ajax_category_add.php?category=Income">
                                    <i class="fas fa-plus"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-5">
                        <div class="btn-group float-right">
                            <a href="?<?php echo $url_query_strings_sort ?>&archived=<?php if($archived == 1){ echo 0; } else { echo 1; } ?>" 
                                class="btn btn-<?php if($archived == 1){ echo "primary"; } else { echo "default"; } ?>">
                                <i class="fa fa-fw fa-archive mr-2"></i>Archived
                            </a>
                            <div class="dropdown ml-2" id="bulkActionButton" hidden>
                                <button class="btn btn-secondary dropdown-toggle" type="button" data-toggle="dropdown">
                                    <i class="fas fa-fw fa-layer-group mr-2"></i>Bulk Action (<span id="selectedCount">0</span>)
                                </button>
                                <div class="dropdown-menu">
                                    <a class="dropdown-item" href="#" data-toggle="modal" data-target="#bulkEditCategoryModal">
                                        <i class="fas fa-fw fa-list mr-2"></i>Set Category
                                    </a>
                                    <?php if ($archived) { ?>
                                    <div class="dropdown-divider"></div>
                                    <button class="dropdown-item text-info"
                                        type="submit" form="bulkActions" name="bulk_unarchive_products">
                                        <i class="fas fa-fw fa-redo mr-2"></i>Unarchive
                                    </button>
                                    <div class="dropdown-divider"></div>
                                    <button class="dropdown-item text-danger text-bold"
                                        type="submit" form="bulkActions" name="bulk_delete_products">
                                        <i class="fas fa-fw fa-trash mr-2"></i>Delete
                                    </button>
                                    <?php } else { ?>
                                    <div class="dropdown-divider"></div>
                                    <button class="dropdown-item text-danger confirm-link"
                                        type="submit" form="bulkActions" name="bulk_archive_products">
                                        <i class="fas fa-fw fa-archive mr-2"></i>Archive
                                    </button>
                                    <?php } ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
            <hr>
            <form id="bulkActions" action="post.php" method="post">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?>">

                <div class="table-responsive-sm">
                    <table class="table table-striped table-borderless table-hover">
                        <thead class="text-dark <?php if ($num_rows[0] == 0) { echo "d-none"; } ?> text-nowrap">
                        <tr>
                            <td class="bg-light pr-0">
                                <div class="form-check">
                                    <input class="form-check-input" id="selectAllCheckbox" type="checkbox" onclick="checkAll(this)">
                                </div>
                            </td>
                            <th>
                                <a class="text-dark" href="?<?php echo $url_query_strings_sort; ?>&sort=product_name&order=<?php echo $disp; ?>">
                                    Name <?php if ($sort == 'product_name') { echo $order_icon; } ?>
                                </a>
                            </th>
                            <th>
                                <a class="text-dark" href="?<?php echo $url_query_strings_sort; ?>&sort=category_name&order=<?php echo $disp; ?>">
                                    Category <?php if ($sort == 'category_name') { echo $order_icon; } ?>
                                </a>
                            </th>
                            <th>
                                <a class="text-dark" href="?<?php echo $url_query_strings_sort; ?>&sort=product_description&order=<?php echo $disp; ?>">
                                    Description <?php if ($sort == 'product_description') { echo $order_icon; } ?>
                                </a>
                            </th>
                            <th>
                                <a class="text-dark" href="?<?php echo $url_query_strings_sort; ?>&sort=tax_name&order=<?php echo $disp; ?>">
                                    Tax Name <?php if ($sort == 'tax_name') { echo $order_icon; } ?>
                                </a>
                            </th>
                            <th>
                                <a class="text-dark" href="?<?php echo $url_query_strings_sort; ?>&sort=tax_percent&order=<?php echo $disp; ?>">
                                    Tax Rate <?php if ($sort == 'tax_percent') { echo $order_icon; } ?>
                                </a>
                            </th>
                            <th class="text-right">
                                <a class="text-dark" href="?<?php echo $url_query_strings_sort; ?>&sort=product_price&order=<?php echo $disp; ?>">
                                    Price <?php if ($sort == 'product_price') { echo $order_icon; } ?>
                                </a>
                            </th>
                            <th class="text-center">Action</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php

                        while ($row = mysqli_fetch_array($sql)) {
                            $product_id = intval($row['product_id']);
                            $product_name = nullable_htmlentities($row['product_name']);
                            $product_description = nullable_htmlentities($row['product_description']);
                            if (empty($product_description)) {
                                $product_description_display = "-";
                            } else {
                                $product_description_display = "<div style='white-space:pre-line'>$product_description</div>";
                            }
                            $product_price = floatval($row['product_price']);
                            $product_currency_code = nullable_htmlentities($row['product_currency_code']);
                            $product_created_at = nullable_htmlentities($row['product_created_at']);
                            $product_archived_at = nullable_htmlentities($row['product_archived_at']);
                            $category_id = intval($row['category_id']);
                            $category_name = nullable_htmlentities($row['category_name']);
                            $product_tax_id = intval($row['product_tax_id']);
                            $tax_name = nullable_htmlentities($row['tax_name']);
                            if (empty($tax_name)) {
                                $tax_name_display = "-";
                            } else {
                                $tax_name_display = $tax_name;
                            }
                            $tax_percent = floatval($row['tax_percent']);


                            ?>
                            <tr>
                                <td class="pr-0 bg-light">
                                    <div class="form-check">
                                        <input class="form-check-input bulk-select" type="checkbox" name="product_ids[]" value="<?php echo $product_id ?>">
                                    </div>
                                </td>
                                <td>
                                    <a class="text-dark text-bold" href="#"
                                        data-toggle="ajax-modal"
                                        data-ajax-url="ajax/ajax_product_edit.php"
                                        data-ajax-id="<?php echo $product_id; ?>"
                                        >
                                        <?php echo $product_name; ?>   
                                    </a>
                                </td>
                                <td><?php echo $category_name; ?></td>
                                <td><?php echo $product_description_display; ?></td>
                                <td><?php echo $tax_name_display; ?></td>
                                <td><?php echo $tax_percent; ?>%</td>
                                <td class="text-right"><?php echo numfmt_format_currency($currency_format, $product_price, $product_currency_code); ?></td>
                                
                                <td>
                                    <div class="dropdown dropleft text-center">
                                        <button class="btn btn-secondary btn-sm" type="button" data-toggle="dropdown">
                                            <i class="fas fa-ellipsis-h"></i>
                                        </button>
                                        <div class="dropdown-menu">
                                            <a class="dropdown-item" href="#"
                                                data-toggle="ajax-modal"
                                                data-ajax-url="ajax/ajax_product_edit.php"
                                                data-ajax-id="<?php echo $product_id; ?>"
                                                >
                                                <i class="fas fa-fw fa-edit mr-2"></i>Edit
                                            </a>
                                            <?php if ($session_user_role == 3) { ?>
                                                <?php if ($product_archived_at) { ?>
                                                <div class="dropdown-divider"></div>
                                                <a class="dropdown-item text-info" href="post.php?unarchive_product=<?php echo $product_id; ?>">
                                                    <i class="fas fa-fw fa-redo mr-2"></i>Unarchive
                                                </a>
                                                <?php if ($config_destructive_deletes_enable) { ?>
                                                <div class="dropdown-divider"></div>
                                                <a class="dropdown-item text-danger text-bold confirm-link" href="post.php?delete_product=<?php echo $product_id; ?>">
                                                    <i class="fas fa-fw fa-trash mr-2"></i>Delete
                                                </a>
                                                <?php } ?>
                                                <?php } else { ?>
                                                <div class="dropdown-divider"></div>
                                                <a class="dropdown-item text-danger confirm-link" href="post.php?archive_product=<?php echo $product_id; ?>">
                                                    <i class="fas fa-fw fa-archive mr-2"></i>Archive
                                                </a>
                                                <?php } ?>
                                            <?php } ?>
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
                <?php require_once "modals/product_bulk_edit_category_modal.php"; ?>
            </form>
            <?php require_once "includes/filter_footer.php";
 ?>
        </div>
    </div>

<script src="js/bulk_actions.js"></script>

<?php

require_once "modals/product_add_modal.php";
require_once "modals/product_export_modal.php";

require_once "includes/footer.php";
