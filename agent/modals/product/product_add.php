<div class="modal" id="addProductModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-dark">
                <h5 class="modal-title"><i class="fas fa-fw <?= $type_icon ?> mr-2"></i>New <strong><?= ucwords($type_filter); ?></strong></h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form action="post.php" method="post" autocomplete="off">
                <input type="hidden" name="type" value="<?= $type_filter ?>">

                <div class="modal-body">

                    <div class="form-group">
                        <label>Name <strong class="text-danger">*</strong></label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fas fa-fw fa-box"></i></span>
                            </div>
                            <input type="text" class="form-control" name="name" placeholder="Product name" maxlength="200" required autofocus>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Category <strong class="text-danger">*</strong></label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fa fa-fw fa-tag"></i></span>
                            </div>
                            <select class="form-control select2" name="category" required>
                                <option value="">- Select Category -</option>
                                <?php

                                $sql = mysqli_query($mysqli, "SELECT * FROM categories WHERE category_type = 'Income' AND category_archived_at IS NULL");
                                while ($row = mysqli_fetch_array($sql)) {
                                    $category_id = intval($row['category_id']);
                                    $category_name = nullable_htmlentities($row['category_name']);
                                    ?>
                                    <option value="<?php echo $category_id; ?>"><?php echo $category_name; ?></option>

                                    <?php
                                }
                                ?>
                            </select>
                            <div class="input-group-append">
                                <button class="btn btn-secondary ajax-modal" type="button"
                                    data-modal-url="../admin/modals/category/category_add.php?category=Income">
                                    <i class="fas fa-fw fa-plus"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="col">
                            <div class="form-group">
                                <label>Price <strong class="text-danger">*</strong></label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fa fa-fw fa-dollar-sign"></i></span>
                                    </div>
                                    <input type="text" class="form-control" inputmode="numeric" pattern="[0-9]*\.?[0-9]{0,2}" name="price" placeholder="0.00" required>
                                </div>
                            </div>
                        </div>

                        <div class="col">
                            <div class="form-group">
                                <label>Tax</label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fa fa-fw fa-balance-scale"></i></span>
                                    </div>
                                    <select class="form-control select2" name="tax">
                                        <option value="0">None</option>
                                        <?php

                                        $taxes_sql = mysqli_query($mysqli, "SELECT * FROM taxes WHERE tax_archived_at IS NULL ORDER BY tax_name ASC");
                                        while ($row = mysqli_fetch_array($taxes_sql)) {
                                            $tax_id = intval($row['tax_id']);
                                            $tax_name = nullable_htmlentities($row['tax_name']);
                                            $tax_percent = floatval($row['tax_percent']);
                                            ?>
                                            <option value="<?php echo $tax_id; ?>"><?php echo "$tax_name $tax_percent%"; ?></option>

                                            <?php
                                        }
                                        ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <?php if ($type_filter == 'product') { ?>
                    <div class="form-group">
                        <label>Location</label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fa fa-fw fa-map-marker-alt"></i></span>
                            </div>
                            <input type="text" class="form-control" name="location" placeholder="Enter a location">
                        </div>
                    </div>
                    <?php } ?>

                    <div class="form-group">
                        <label>Description</label>
                        <textarea class="form-control" rows="4" name="description" placeholder="Product description"></textarea>
                    </div>

                    <div class="form-group">
                        <label>Code</label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fa fa-fw fa-barcode"></i></span>
                            </div>
                            <input type="text" class="form-control" name="code" placeholder="Enter product code eg. SKU #">
                        </div>
                    </div>

                </div>

                <div class="modal-footer">
                    <button type="submit" name="add_product" class="btn btn-primary text-bold"><i class="fas fa-check mr-2"></i>Create</button>
                    <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fas fa-times mr-2"></i>Cancel</button>
                </div>

            </form>
        </div>
    </div>
</div>
