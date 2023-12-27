<?php 
        $sql_expenses = mysqli_query($mysqli, "SELECT * FROM expenses WHERE expense_ticket_id = $ticket_id");
?>

<div class="modal" id="editProductModal" tabindex="-1">
    <div class="modal-dialog modal-md">
        <div class="modal-content bg-dark">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fa fa-fw fa-boxes mr-2"></i>
                    <?php if($ticket_status != "Closed") { ?>Edit<?php } else { ?>View<?php } ?> products on Ticket:
                    <?php echo $ticket_prefix.$ticket_number; ?>
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form action="post.php" method="post" autocomplete="off">
                <input type="hidden" name="ticket_id" value="<?php echo $ticket_id; ?>">
                <div class="modal-body bg-white">
                    <div class="row">
                        <!-- Expense or Product Pills-->
                        <div class="col-md-12">
                            <ul class="nav nav-pills nav-fill">
                                <li class="nav-item">
                                    <a class="nav-link active" data-toggle="pill" href="#pills-expense">Expense</a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" data-toggle="pill" href="#pills-product">Product</a>
                                </li>
                            </ul>
                        </div>
                        <!-- End Expense or Product Pills-->
                        <!-- Expense or Product Pills Content-->
                        <!-- Expense Pills Content-->
                        <div class="tab-content">
                            <div class="tab-pane container show active" id="pills-expense">
                                <div class="col-md-12">
                                    <div class="row">
                                        <div class="md-6">
                                            <h6 class="text-center text-bold">Expenses</h6>
                                            <table class="table table-sm table-bordered table-striped">
                                                <thead>
                                                    <tr>
                                                        <th class="text-center">Description</th>
                                                        <th class="text-center">Amount</th>
                                                        <th class="text-center">Date</th>
                                                        <?php if ($ticket_status != "Closed") { ?>
                                                        <th class="text-center">Remove</th>
                                                        <?php } ?>
                                                    </tr>
                                                    <?php while($row = mysqli_fetch_array($sql_expenses)) {
                                                $expense_id = $row['expense_id'];
                                                $expense_description = $row['expense_description'];
                                                $expense_amount = $row['expense_amount'];
                                                $expense_date = $row['expense_date'];
                                                $expense_currency = $row['expense_currency_code'];

                                                $expense_date = strtotime($expense_date);
                                                $expense_date = date("m/d/Y", $expense_date);
                                                $expense_id = intval($expense_id);
                                                $expense_amount = floatval($expense_amount);
                                            ?>
                                                    <tr>
                                                        <td class="text-center">
                                                            <?php echo $expense_description; ?>
                                                        </td>
                                                        <td class="text-center">
                                                            <?php echo numfmt_format_currency($currency_format, $expense_amount, $expense_currency) ?>
                                                        </td>
                                                        <td class="text-center">
                                                            <?php echo $expense_date; ?>
                                                        </td>
                                                        <?php if ($ticket_status != "Closed") { ?>
                                                        <td class="text-center">
                                                            <a href="post.php?delete_ticket_expense_id=<?php echo $expense_id; ?>&ticket_id=<?php echo $ticket_id; ?>"
                                                                class="btn btn-danger btn-sm">
                                                                <i class="fa fa-trash-alt"></i>
                                                            </a>
                                                        </td>
                                                        <?php } ?>
                                                    </tr>
                                                    <?php } ?>
                                            </table>
                                        </div>
                                        <?php if ($ticket_status != "Closed") { ?>
                                        <div class="md-6">
                                            <label>Select Expense to Add</label>
                                            <div class="input-group mb-3">
                                                <div class="input-group-prepend">
                                                    <span class="input-group-text"><i class="fa fa-fw fa-dollar-sign
                                                "></i></span>
                                                </div>
                                                <select class="form-control select2" name="expense" id="expense"
                                                    class="form-control form-control-sm">
                                                    <?php if (mysqli_num_rows($sql_all_expenses) == 0) { ?>
                                                    <option value="0">No Expenses</option>
                                                    <?php } else { ?>
                                                    <option value="0">Select Expense</option>
                                                    <?php } ?>
                                                    <?php while($row = mysqli_fetch_array($sql_all_expenses)) {
                                                        $expense_id = $row['expense_id'];
                                                        $expense_description = $row['expense_description'];
                                                        ?>
                                                    <option value="<?php echo $expense_id; ?>">
                                                        <?php echo $expense_description; ?>
                                                    </option>
                                                    <?php } ?>
                                                </select>
                                                <div class="input-group-append">
                                                    <button type="button" class="btn btn-primary" data-toggle="modal"
                                                        data-target="#addExpenseModal"><i
                                                            class="fas fa-plus mr-2"></i>New</button>
                                                </div>
                                            </div>
                                        </div>
                                        <?php } ?>
                                    </div>
                                </div>
                            </div>
                            <!-- End Expense Pills Content-->
                            <!-- Product Pills Content-->
                            <div class="tab-pane fade container" id="pills-product">
                                <div class="col-md-12">
                                    <div class="row">
                                        <div class="mb-6">
                                            <h6 class="text-center text-bold">Products</h6>
                                            <table class="table table-sm table-bordered table-striped">
                                                <thead>
                                                    <tr>
                                                        <th class="text-center">Description</th>
                                                        <th class="text-center">Amount</th>
                                                        <th class="text-center">Quantity</th>
                                                        <?php if ($ticket_status != "Closed") { ?>
                                                        <th class="text-center">Remove</th>
                                                        <?php } ?>
                                                    </tr>
                                                </thead>
                                                <?php
                                            $sql_ticket_products = mysqli_query($mysqli, "SELECT * FROM ticket_products WHERE ticket_product_ticket_id = $ticket_id");
                                            while($row = mysqli_fetch_array($sql_ticket_products)) {
                                                $product_id = $row['ticket_product_product_id'];
                                                $product_quantity = $row['ticket_product_quantity'];
                                                $ticket_product_id = $row['ticket_product_association_id'];


                                                $sql_product = mysqli_query($mysqli, "SELECT * FROM products WHERE product_id = $product_id LIMIT 1");
                                                $row = mysqli_fetch_array($sql_product);

                                                $product_id = $row['product_id'];
                                                $product_description = $row['product_name'];
                                                $product_price = $row['product_price'];
                                                $product_currency_code = $row['product_currency_code'];
                                                

                                                $product_price = floatval($product_price);
                                                $product_price_fmt = numfmt_format_currency($currency_format, $product_price, $product_currency_code);

                                                $product_id = intval($product_id);
                                                $product_price = floatval($product_price);
                                                $product_description = nullable_htmlentities($product_description);
                                                $product_quantity = intval($product_quantity);
                                                $product_amount = $product_price * $product_quantity;
                                                $product_amount_fmt = numfmt_format_currency($currency_format, $product_amount, $product_currency_code);
                                                
                                                
                                            ?>
                                                <tr>
                                                    <td class="text-center">
                                                        <?php echo $product_description; ?>
                                                    </td>
                                                    <td class="text-center">
                                                        <?php echo $product_amount_fmt; ?>
                                                    </td>
                                                    <td class="text-center">
                                                        <?php echo $product_quantity ?>
                                                    </td>
                                                    <?php if ($ticket_status != "Closed") { ?>
                                                    <td class="text-center">
                                                        <a href="post.php?delete_ticket_product_id=<?php echo $ticket_product_id; ?>&ticket_id=<?php echo $ticket_id; ?>"
                                                            class="btn btn-danger btn-sm">
                                                            <i class="fa fa-trash-alt"></i>
                                                        </a>
                                                    </td>
                                                    <?php } ?>
                                                </tr>
                                                <?php } ?>

                                            </table>
                                        </div>

                                        <?php if ($ticket_status != "Closed") { ?>
                                        <div class="mb-6">
                                            <div class="form-group">
                                                <label>Select Product to Add</label>
                                                <input type="hidden" name="ticket_id" value="<?php echo $ticket_id; ?>">
                                                <div class="input-group">
                                                    <div class="input-group-prepend">
                                                        <span class="input-group-text"><label>Qty</label></span>
                                                    </div>
                                                    <input type="number" name="product_quantity" id="product_quantity"
                                                        class="form-control" value="1" min="1">
                                                    <select name="product" id="product"
                                                        class="form-control select2">
                                                        <option value="0">Select Product</option>
                                                        <?php while($row = mysqli_fetch_array($sql_all_products)) {
                                                            $product_id = $row['product_id'];
                                                            $product_description = $row['product_name'];
                                                            $product_price = $row['product_price'];
                                                            $product_currency_code = $row['product_currency_code'];

                                                            $product_price = floatval($product_price);
                                                            $product_price_fmt = numfmt_format_currency($currency_format, $product_price, $product_currency_code);

                                                            $product_id = intval($product_id);
                                                            $product_price = floatval($product_price);
                                                            $product_description = nullable_htmlentities($product_description);
                                                            ?>
                                                        <option value="<?php echo $product_id; ?>">
                                                            <?php echo $product_description." [".$product_price_fmt."]";
                                                            ?>
                                                        </option>
                                                        <?php } ?>
                                                    </select>
                                                </div>
                                            </div>

                                        </div>
                                        <?php } ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-white">
                    <?php // this is in post/ticket.php!
                    if ($ticket_status != "Closed") {
                    ?>
                    <button type="submit" name="change_product_ticket" class="btn btn-primary text-bold">
                        <i class="fa fa-plus mr-2"></i>Add
                    </button>
                    <?php } ?>
                    <button type="button" class="btn btn-light" data-dismiss="modal">
                        <i class="fa fa-times mr-2"></i><?php if ($ticket_status != "Closed") {
                                ?>Cancel
                        <?php } else {
                                ?>Close
                        <?php } ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>