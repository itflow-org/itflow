<?php

require_once '../../../includes/modal_header.php';

enforceUserPermission('module_sales', 2);

$invoice_id = intval($_GET['invoice_id']);

$sql = mysqli_query($mysqli, "SELECT invoice_client_id, invoice_number, invoice_prefix
    FROM invoices WHERE invoice_id = $invoice_id LIMIT 1");
$row = mysqli_fetch_assoc($sql);
$invoice_prefix = escapeHtml($row['invoice_prefix']);
$invoice_number = intval($row['invoice_number']);
$client_id = intval($row['invoice_client_id']);

enforceClientAccess();

ob_start();

?>

<div class="modal-header bg-dark">
    <h5 class="modal-title text-white">
        <i class="fa fa-fw fa-check me-2"></i>Mark Invoice <?= "$invoice_prefix$invoice_number" ?> Sent
    </h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="invoice_id" value="<?= $invoice_id ?>">

    <div class="modal-body">

        <div class="mb-3">
            <label>How was it sent? <strong class="text-danger">*</strong></label>
            <div class="input-group">
                <span class="input-group-text"><i class="fa fa-fw fa-paper-plane"></i></span>
                <select class="form-select" name="sent_method" required>
                    <?php foreach (getSentMethods() as $sent_method) { ?>
                        <option value="<?= escapeHtml($sent_method) ?>"><?= escapeHtml($sent_method) ?></option>
                    <?php } ?>
                </select>
            </div>
        </div>

        <div class="mb-3">
            <label>Note</label>
            <textarea class="form-control" name="note" rows="3" maxlength="500"
                placeholder="Optional - tracking number, who it went to, anything worth keeping"></textarea>
        </div>

    </div>
    <div class="modal-footer">
        <button type="submit" name="mark_invoice_sent" class="btn btn-primary text-bold">
            <i class="fa fa-fw fa-check me-2"></i>Mark Sent
        </button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">
            <i class="fa fa-fw fa-times me-2"></i>Cancel
        </button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
