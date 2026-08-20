<?php

require_once '../../../includes/modal_header.php';

ob_start();

?>
<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fa fa-fw fa-piggy-bank me-2"></i>New Account</h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

    <div class="modal-body">

        <div class="mb-3">
            <label>Account Name <strong class="text-danger">*</strong></label>
            <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-piggy-bank"></i></span>
                <input type="text" class="form-control" name="name" placeholder="Account name" maxlength="200" required autofocus>
            </div>
        </div>

        <div class="mb-3">
            <label>Opening Balance <strong class="text-danger">*</strong></label>
            <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-dollar-sign"></i></span>
                <input type="text" class="form-control" inputmode="decimal" pattern="-?[0-9]*\.?[0-9]{0,2}" name="opening_balance" placeholder="0.00" required>
            </div>
        </div>

        <div class="mb-3">
            <label>Currency <strong class="text-danger">*</strong></label>
            <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-money-bill"></i></span>
                <select class="form-select select2" name="currency_code" required>
                    <option value="">- Currency -</option>
                    <?php foreach ($currencies_array as $currency_code => $currency_name) { ?>
                        <option <?php if ($session_company_currency == $currency_code) { echo "selected"; } ?> value="<?= $currency_code ?>"><?= "$currency_code - $currency_name" ?></option>
                    <?php } ?>
                </select>
            </div>
        </div>

        <div class="mb-3">
            <label>Notes</label>
            <textarea class="form-control" rows="5" placeholder="Enter some notes" name="notes"></textarea>
        </div>

    </div>
    <div class="modal-footer">
        <button type="submit" name="add_account" class="btn btn-primary text-bold"><i class="fa fa-check me-2"></i>Create</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fa fa-times me-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
