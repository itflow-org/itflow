<div class="modal" id="addTicketProductModal<?php echo $ticket_id; ?>" tabindex="-1">
    <div class="modal-dialog modal-md">
        <div class="modal-content bg-dark">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fa fa-fw fa-clone mr-2"></i>Add Products</h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form action="post.php" method="post" autocomplete="off">
                <input type="hidden" id="current_ticket_id" name="ticket_id" value="<?php echo $ticket_id; ?>">
                <div class="modal-body bg-white">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="product_id">Product</label>
                            <select class="form-control select2" id="product_id" name="product_id" required>
                                <option value="" selected disabled>Select a product</option>
                                <?php
                                $products = mysqli_query($mysqli, "SELECT * FROM products
                                    LEFT JOIN inventory ON products.product_id = inventory.inventory_product_id
                                    LEFT JOIN inventory_locations ON inventory.inventory_location_id = inventory_locations.inventory_location_id
                                    WHERE inventory_locations.inventory_location_user_id = $user_id
                                    AND (inventory.inventory_client_id = $client_id OR inventory.inventory_client_id = 0)
                                    GROUP BY products.product_id");
                                while ($product = mysqli_fetch_array($products)) {
                                    echo "<option value=\"$product[product_id]\">$product[product_name]</option>";
                                }
                                ?>
                            </select>
                            <label for="quantity">Quantity</label>
                            <input type="number" class="form-control" id="quantity" name="quantity" required>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-white">
                    <button type="submit" id="add_ticket_products_btn" name="add_ticket_products" class="btn btn-primary text-bold"><i class="fa fa-plus mr-2"></i>Add</button>
                    <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fa fa-times mr-2"></i>Cancel</button>
                    <!-- Merge button starts disabled. Is enabled by the merge_into_number_get_details Javascript function-->
                </div>
            </form>
        </div>
    </div>
</div>
