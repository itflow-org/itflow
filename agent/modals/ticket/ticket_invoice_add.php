<?php
$sql_invoices = mysqli_query($mysqli, "SELECT * FROM invoices WHERE invoice_status LIKE 'Draft' AND invoice_client_id = $client_id ORDER BY invoice_number ASC");

?>

<div class="modal" id="addInvoiceFromTicketModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-dark">
                <h5 class="modal-title"><i class="fa fa-fw fa-file-invoice-dollar mr-2"></i>Invoice ticket</h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form action="post.php" method="post" autocomplete="off">
                <input type="hidden" name="ticket_id" value="<?php echo $ticket_id; ?>">
                <div class="modal-body">
                    <?php if (mysqli_num_rows($sql_invoices) > 0) { ?>

                    <ul class="nav nav-pills nav-justified mb-3">
                        <li class="nav-item">
                            <a class="nav-link active" data-toggle="pill" href="#pills-create-invoice"><i class="fa fa-fw fa-check mr-2"></i>Create New Invoice</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" data-toggle="pill" href="#pills-add-to-invoice"><i class="fa fa-fw fa-plus mr-2"></i>Add to Existing Invoice</a>
                        </li>
                        <?php } ?>
                    </ul>

                    <hr>

                    <div class="tab-content">

                            <div class="tab-pane fade show active" id="pills-create-invoice">

                            <div class="row">
                                <div class="col-sm-6">
                            
                                    <div class="form-group">
                                        <label>Invoice Date <strong class="text-danger">*</strong></label>
                                        <div class="input-group">
                                            <div class="input-group-prepend">
                                                <span class="input-group-text"><i class="fa fa-fw fa-calendar"></i></span>
                                            </div>
                                            <input type="date" class="form-control" name="date" max="2999-12-31" value="<?php echo date("Y-m-d"); ?>">
                                        </div>
                                    </div>

                                </div>
                                <div class="col-sm-6">
                                    <div class="form-group">
                                        <label>Invoice Category <strong class="text-danger">*</strong></label>
                                        <div class="input-group">
                                            <div class="input-group-prepend">
                                                <span class="input-group-text"><i class="fa fa-fw fa-list"></i></span>
                                            </div>
                                            <select class="form-control select2" name="category">
                                                <option value="">- Category -</option>
                                                <?php

                                                $sql = mysqli_query($mysqli, "SELECT * FROM categories WHERE category_type = 'Income' AND category_archived_at IS NULL ORDER BY category_name ASC");
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
                                                <button type="button" class="btn btn-secondary" data-toggle="modal" data-target="#addQuickCategoryIncomeModal"><i class="fas fa-fw fa-plus"></i></button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="form-group">
                                <label>Scope</label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fa fa-fw fa-comment"></i></span>
                                    </div>
                                    <input type="text" class="form-control" name="scope" placeholder="Quick description" value="Ticket <?php echo "$ticket_prefix$ticket_number - $ticket_subject"; ?>">
                                </div>
                            </div>


                        </div>

                        <?php
                        
                        if (mysqli_num_rows($sql_invoices) > 0) { ?>

                        <div class="tab-pane fade" id="pills-add-to-invoice">
                            <div class="form-group">
                                <label>Invoice</label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fa fa-fw fa-file-invoice-dollar"></i></span>
                                    </div>
                                    <select class="form-control" name="invoice_id">
                                        <option value="0">- Invoice -</option>
                                        <?php

                                        while ($row = mysqli_fetch_array($sql_invoices)) {
                                            $invoice_id = intval($row['invoice_id']);
                                            $invoice_prefix = nullable_htmlentities($row['invoice_prefix']);
                                            $invoice_number = intval($row['invoice_number']);
                                            $invoice_scope = nullable_htmlentities($row['invoice_scope']);
                                            $invoice_status = nullable_htmlentities($row['invoice_status']);
                                            $invoice_date = nullable_htmlentities($row['invoice_date']);
                                            $invoice_due = nullable_htmlentities($row['invoice_due']);
                                            $invoice_amount = floatval($row['invoice_amount']);
                                            ?>
                                            <option value="<?php echo $invoice_id; ?>"><?php echo "$invoice_prefix$invoice_number | $invoice_scope"; ?></option>
                                        <?php } ?>

                                    </select>
                                </div>
                            </div>
                        </div>

                        <?php } ?>
                    </div>

                    <hr>

                    <div class="form-group">
                        <label>Item <strong class="text-danger">*</strong></label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fa fa-fw fa-box"></i></span>
                            </div>
                            <input type="text" class="form-control" name="item_name" placeholder="Item" value="Support [Hourly]" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Item Description</label>
                        <div class="input-group">
                            <textarea class="form-control" rows="5" name="item_description"><?php echo "# $contact_name - $asset_name - $ticket_date\nTicket $ticket_prefix$ticket_number\n$ticket_subject\nTT: $ticket_total_reply_time"; ?></textarea>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="col">

                            <div class="form-group">
                                <label>QTY <strong class="text-danger">*</strong></label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fa fa-fw fa-balance-scale"></i></span>
                                    </div>
                                    <input type="text" class="form-control" inputmode="numeric" pattern="-?[0-9]*\.?[0-9]{0,2}" name="qty" value="<?php echo roundToNearest15($ticket_total_reply_time); ?>" required>
                                </div>
                            </div>

                        </div>

                        <div class="col">

                            <div class="form-group">
                                <label>Price <strong class="text-danger">*</strong></label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fa fa-fw fa-dollar-sign"></i></span>
                                    </div>
                                    <input type="text" class="form-control" inputmode="numeric" pattern="-?[0-9]*\.?[0-9]{0,2}" name="price" value="<?php echo number_format($client_rate, 2, '.', ''); ?>" required>
                                </div>
                            </div>

                        </div>

                    </div>

                    <div class="form-group">
                        <label>Tax <strong class="text-danger">*</strong></label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fa fa-fw fa-piggy-bank"></i></span>
                            </div>
                            <select class="form-control select2" name="tax_id" required>
                                <option value="0">None</option>
                                <?php

                                $taxes_sql = mysqli_query($mysqli, "SELECT * FROM taxes WHERE tax_archived_at IS NULL ORDER BY tax_name ASC");
                                while ($row = mysqli_fetch_array($taxes_sql)) {
                                    $tax_id_select = intval($row['tax_id']);
                                    $tax_name = nullable_htmlentities($row['tax_name']);
                                    $tax_percent = floatval($row['tax_percent']);
                                    ?>
                                    <option value="<?php echo $tax_id_select; ?>"><?php echo "$tax_name $tax_percent%"; ?></option>
                                <?php } ?>
                            </select>

                        </div>
                    </div>

                </div>
                <div class="modal-footer">
                    <button type="submit" name="add_invoice_from_ticket" class="btn btn-primary text-bold"><i class="fa fa-check mr-2"></i>Invoice</button>
                    <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fa fa-times mr-2"></i>Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>
