<?php

/*
 * Edit a payment method. Renaming it renames it on every record that used
 * it. The fields are payment_method_form.php.
 */

require_once '../../includes/modal_header.php';

$payment_method = paymentMethodRow(intval($_GET['id'] ?? 0));

ob_start();

if (!$payment_method) { ?>
    <div class="modal-body text-center text-secondary py-5">That payment method is gone - it may have been deleted.</div>
<?php } else { ?>

<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fa fa-fw fa-credit-card me-2"></i>Payment Method: <strong><?= escapeHtml($payment_method['payment_method_name']) ?></strong></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="payment_method_id" value="<?= intval($payment_method['payment_method_id']) ?>">

    <div class="modal-body">
        <?php if (!empty($payment_method['payment_method_archived_at'])) { ?>
            <div class="alert alert-secondary small py-2"><i class="fas fa-fw fa-archive me-1"></i>Archived <?= date('M j, Y', strtotime($payment_method['payment_method_archived_at'])) ?> - it isn't offered in the pickers. Restore it from the list.</div>
        <?php } ?>
        <?php require __DIR__ . '/payment_method_form.php'; ?>
    </div>
    <div class="modal-footer">
        <button type="submit" name="edit_payment_method" class="btn btn-primary text-bold"><i class="fa fa-check me-2"></i>Save changes</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fa fa-times me-2"></i>Cancel</button>
    </div>
</form>

<?php }
require_once '../../../includes/modal_footer.php';
