<script src="js/quote_edit_modal.js"></script>
<div class="modal" id="editQuoteModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content bg-dark">
            <div class="modal-header">
                <h5 class="modal-title text-white"><i class="fas fa-fw fa-comment-dollar mr-2"></i>Editing quote: <span class="text-bold" id="editQuoteHeaderID"></span> - <span class="text" id="editQuoteHeaderClient"></span></h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form action="post.php" method="post" autocomplete="off">
                <input type="hidden" name="quote_id" id="editQuoteID" value="">

                <div class="modal-body bg-white" <?php if (lookupUserPermission('module_sales') <= 1) { echo 'inert'; } ?>>

                    <div class="form-group">
                        <label>Quote Date</label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fa fa-fw fa-calendar"></i></span>
                            </div>
                            <input type="date" class="form-control" name="date" id="editQuoteDate" max="2999-12-31" value="" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Expire <strong class="text-danger">*</strong></label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fa fa-fw fa-calendar"></i></span>
                            </div>
                            <input type="date" class="form-control" name="expire" id="editQuoteExpire" max="2999-12-31" value="" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Income Category</label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fa fa-fw fa-tag"></i></span>
                            </div>
                            <select class="form-control select2" name="category" id="editQuoteCategory" required>
                            </select>
                            <div class="input-group-append">
                                <a class="btn btn-secondary" href="admin_category.php?category=Income" target="_blank"><i class="fas fa-fw fa-plus"></i></a>
                            </div>
                        </div>
                    </div>


                    <div class='form-group'>
                        <label>Discount Amount</label>
                        <div class='input-group'>
                            <div class='input-group-prepend'>
                                <span class='input-group-text'><i class='fa fa-fw fa-dollar-sign'></i></span>
                            </div>
                            <input type='text' class='form-control' inputmode="numeric" pattern="-?[0-9]*\.?[0-9]{0,2}" name='quote_discount' placeholder='0.00' value="<?php echo number_format($quote_discount, 2, '.', ''); ?>">
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Scope</label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fa fa-fw fa-comment"></i></span>
                            </div>
                            <input type="text" class="form-control" name="scope" id="editQuoteScope" placeholder="Quick description" value="">
                        </div>
                    </div>

                </div>
                <div class="modal-footer bg-white">
                    <button type="submit" name="edit_quote" class="btn btn-primary text-bold"><i class="fas fa-check mr-2"></i>Save</button>
                    <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fas fa-times mr-2"></i>Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>
