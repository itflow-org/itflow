<?php
require_once("inc_all.php");

if (!empty($_GET['sb'])) {
    $sb = strip_tags(mysqli_real_escape_string($mysqli, $_GET['sb']));
} else {
    $sb = "product_name";
}


//Rebuild URL
$url_query_strings_sb = http_build_query(array_merge($_GET, array('sb' => $sb, 'o' => $o)));

$sql = mysqli_query(
    $mysqli,
    "SELECT SQL_CALC_FOUND_ROWS * FROM products LEFT JOIN categories ON product_category_id = category_id
    WHERE products.company_id = $session_company_id
    AND (product_name LIKE '%$q%' OR product_description LIKE '%$q%' OR category_name LIKE '%$q%' OR product_price LIKE '%$q%')
    ORDER BY $sb $o LIMIT $record_from, $record_to"
);

$num_rows = mysqli_fetch_row(mysqli_query($mysqli, "SELECT FOUND_ROWS()"));

?>

    <div class="card card-dark">
        <div class="card-header py-2">
            <h3 class="card-title mt-2"><i class="fa fa-fw fa-box"></i> Products</h3>
            <div class="card-tools">
                <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#addProductModal"><i class="fas fa-fw fa-plus"></i> New Product</button>
            </div>
        </div>

        <div class="card-body">
            <form class="mb-4" autocomplete="off">
                <div class="row">
                    <div class="col-sm-4">
                        <div class="input-group">
                            <input type="search" class="form-control" name="q" value="<?php if (isset($q)) {echo strip_tags(htmlentities($q));} ?>" placeholder="Search Products">
                            <div class="input-group-append">
                                <button class="btn btn-primary"><i class="fa fa-search"></i></button>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
            <hr>
            <div class="table-responsive">
                <table class="table table-striped table-borderless table-hover">
                    <thead class="text-dark <?php if ($num_rows[0] == 0) { echo "d-none"; } ?>">
                    <tr>
                        <th><a class="text-dark" href="?<?php echo $url_query_strings_sb; ?>&sb=product_name&o=<?php echo $disp; ?>">Name</a></th>
                        <th><a class="text-dark" href="?<?php echo $url_query_strings_sb; ?>&sb=category_name&o=<?php echo $disp; ?>">Category</a></th>
                        <th><a class="text-dark" href="?<?php echo $url_query_strings_sb; ?>&sb=product_description&o=<?php echo $disp; ?>">Description</a></th>
                        <th class="text-right"><a class="text-dark" href="?<?php echo $url_query_strings_sb; ?>&sb=product_price&o=<?php echo $disp; ?>">Price</a></th>
                        <th class="text-center">Action</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php

                    while ($row = mysqli_fetch_array($sql)) {
                        $product_id = $row['product_id'];
                        $product_name = htmlentities($row['product_name']);
                        $product_description = htmlentities($row['product_description']);
                        if (empty($product_description)) {
                            $product_description_display = "-";
                        } else {
                            $product_description_display = "<div style='white-space:pre-line'>$product_description</div>";
                        }
                        $product_price = floatval($row['product_price']);
                        $product_currency_code = htmlentities($row['product_currency_code']);
                        $product_created_at = $row['product_created_at'];
                        $category_id = $row['category_id'];
                        $category_name = htmlentities($row['category_name']);
                        $product_tax_id = $row['product_tax_id'];

                        ?>
                        <tr>
                            <th><a class="text-dark" href="#" data-toggle="modal" data-target="#editProductModal<?php echo $product_id; ?>"><?php echo $product_name; ?></a></th>
                            <td><?php echo $category_name; ?></td>
                            <td><?php echo $product_description_display; ?></td>
                            <td class="text-right"><?php echo numfmt_format_currency($currency_format, $product_price, $product_currency_code); ?></td>
                            <td>
                                <div class="dropdown dropleft text-center">
                                    <button class="btn btn-secondary btn-sm" type="button" data-toggle="dropdown">
                                        <i class="fas fa-ellipsis-h"></i>
                                    </button>
                                    <div class="dropdown-menu">
                                        <a class="dropdown-item" href="#" data-toggle="modal" data-target="#editProductModal<?php echo $product_id; ?>">Edit</a>
                                        <div class="dropdown-divider"></div>
                                        <a class="dropdown-item text-danger" href="post.php?delete_product=<?php echo $product_id; ?>">Delete</a>
                                    </div>
                                </div>
                            </td>
                        </tr>

                        <?php

                        require("product_edit_modal.php");

                    }

                    ?>

                    </tbody>
                </table>
            </div>
            <?php require_once("pagination.php"); ?>
        </div>
    </div>

<?php

require_once("product_add_modal.php");
require_once("category_quick_add_modal.php");
require_once("footer.php");
