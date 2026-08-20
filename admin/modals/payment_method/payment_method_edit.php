<?php

require_once '../../includes/modal_header.php';

$payment_method_id = intval($_GET['id']);

$sql = mysqli_query($mysqli, "SELECT payment_method_description, payment_method_id, payment_method_name FROM payment_methods WHERE payment_method_id = $payment_method_id LIMIT 1");

$row = mysqli_fetch_assoc($sql);
$payment_method_id = intval($row['payment_method_id']);
$payment_method_name = escapeHtml($row['payment_method_name']);
$payment_method_description = escapeHtml($row['payment_method_description']);

// Generate the HTML form content using output buffering.
ob_start();
?>
<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fa fa-fw fa-credit-card me-2"></i>Editing: <strong><?= $payment_method_name ?></strong></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="payment_method_id" value="<?= $payment_method_id ?>">
    <div class="modal-body">

        <div class="mb-3">
            <label>Name <strong class="text-danger">*</strong></label>
            <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-credit-card"></i></span>
                <input type="text" class="form-control" name="name" value="<?= $payment_method_name ?>" placeholder="Payment method name" maxlength="200" required autofocus>
            </div>
        </div>

        <div class="mb-3">
            <textarea class="form-control" rows="3" name="description" placeholder="Enter a description..." maxlength="250"><?= $payment_method_description ?></textarea>
        </div>

    </div>
    <div class="modal-footer">
        <button type="submit" name="edit_payment_method" class="btn btn-primary text-bold"><i class="fa fa-check me-2"></i>Save</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fa fa-times me-2"></i>Cancel</button>
    </div>
</form>

<?php

require_once '../../../includes/modal_footer.php';
