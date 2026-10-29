<?php

require_once '../../includes/modal_header.php';

$tax = null;

ob_start();

?>

<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fas fa-fw fa-balance-scale me-2"></i>New Tax</h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

    <div class="modal-body">
        <?php require __DIR__ . '/tax_form.php'; ?>
        <?php if (!taxSettings()['default_id']) { ?>
            <div class="form-check form-switch mt-3">
                <input type="checkbox" class="form-check-input" name="make_default" value="1" id="taxMakeDefault">
                <label class="form-check-label" for="taxMakeDefault">Make it the default for new lines</label>
            </div>
        <?php } ?>
    </div>
    <div class="modal-footer">
        <button type="submit" name="add_tax" class="btn btn-primary text-bold"><i class="fa fa-check me-2"></i>Create</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fas fa-times me-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
