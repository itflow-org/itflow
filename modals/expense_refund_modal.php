<div class="modal" id="addExpenseRefundModal<?php echo $expense_id; ?>" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content bg-dark">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fa fa-fw fa-undo mr-2"></i>Refunding expense</h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form action="post.php" method="post" autocomplete="off">
                <div class="modal-body bg-white">
                    <input type="hidden" name="account" value="<?php echo $expense_account_id; ?>">
                    <input type="hidden" name="vendor" value="<?php echo $expense_vendor_id; ?>">
                    <input type="hidden" name="category" value="<?php echo $expense_category_id; ?>">

                    <div class="form-row">

                        <div class="form-group col-md">
                            <label>Refund Date</label>
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text"><i class="fa fa-fw fa-calendar"></i></span>
                                </div>
                                <input type="date" class="form-control" name="date" max="2999-12-31" required>
                            </div>
                        </div>

                        <div class="form-group col-md">
                            <label>Refund Amount</label>
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text"><i class="fa fa-fw fa-dollar-sign"></i></span>
                                </div>
                                <input type="text" class="form-control" inputmode="numeric" pattern="-?[0-9]*\.?[0-9]{0,2}" name="amount" value="-<?php echo number_format($expense_amount, 2, '.', ''); ?>" placeholder="-0.00" required>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Description</label>
                        <textarea class="form-control" rows="6" name="description" placeholder="Enter a description" required>Refund: <?php echo $expense_description; ?></textarea>
                    </div>

                    <div class="form-group">
                        <label>Reference</label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fa fa-fw fa-file-alt"></i></span>
                            </div>
                            <input type="text" class="form-control" name="reference" placeholder="Enter a reference" maxlength="200" value="<?php echo $expense_reference; ?>">
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Receipt</label>
                        <input type="file" class="form-control-file" name="file">
                    </div>

                </div>
                <div class="modal-footer bg-white">
                    <button type="submit" name="add_expense" class="btn btn-primary text-bold"><i class="fas fa-check mr-2"></i>Refund</button>
                    <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fas fa-times mr-2"></i>Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>
