<?php

require_once '../../includes/modal_header.php';

ob_start();

?>

<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fa fa-fw fa-credit-card me-2"></i>Creating: <strong>Payment Method</strong></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

    <div class="modal-body">

        <div class="mb-3">
            <label>Name <strong class="text-danger">*</strong></label>
            <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-credit-card"></i></span>
                <input type="text" class="form-control" name="name" placeholder="Payment method name" maxlength="200" required autofocus>
            </div>
        </div>

        <div class="mb-3">
            <textarea class="form-control" rows="3" name="description" placeholder="Enter a description..." maxlength="250"></textarea>
        </div>

    </div>
    <div class="modal-footer">
        <button type="submit" name="add_payment_method" class="btn btn-primary text-bold"><i class="fa fa-check me-2"></i>Create</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fa fa-times me-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
