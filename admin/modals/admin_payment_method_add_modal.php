<div class="modal" id="addPaymentMethodModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content bg-dark">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fa fa-fw fa-credit-card mr-2"></i>Creating: <strong>Payment Method</strong></h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form action="post.php" method="post" autocomplete="off">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?>">

                <div class="modal-body bg-white">

                    <div class="form-group">
                        <label>Name <strong class="text-danger">*</strong></label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fa fa-fw fa-credit-card"></i></span>
                            </div>
                            <input type="text" class="form-control" name="name" placeholder="Payment method name" maxlength="200" required autofocus>
                        </div>
                    </div>

                    <div class="form-group">
                        <textarea class="form-control" rows="3" name="description" placeholder="Enter a description..."></textarea>
                    </div>

                </div>
                <div class="modal-footer bg-white">
                    <button type="submit" name="add_payment_method" class="btn btn-primary text-bold"><i class="fa fa-check mr-2"></i>Create</button>
                    <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fa fa-times mr-2"></i>Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>
