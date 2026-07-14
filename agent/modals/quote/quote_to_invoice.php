<?php

require_once '../../../includes/modal_header.php';

$quote_id = intval($_GET['quote_id']);

$sql = mysqli_query($mysqli, "SELECT * FROM quotes WHERE quote_id = $quote_id LIMIT 1");

$row = mysqli_fetch_assoc($sql);
$quote_prefix = escapeHtml($row['quote_prefix']);
$quote_number = intval($row['quote_number']);
$client_id = intval($row['quote_client_id']);

enforceClientAccess();

ob_start();

?>

<div class="modal-header bg-dark">
    <h5 class="modal-title">
        <i class="fas fa-fw fa-file mr-2"></i>
        Quote <?= "$quote_prefix$quote_number" ?>
        <i class="fas fa-arrow-right mr-2"></i>Invoice
    </h5>
    <button type="button" class="close text-white" data-dismiss="modal">
        <span>&times;</span>
    </button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="quote_id" value="<?= $quote_id ?>">

    <div class="modal-body">

        <div class="form-group">
            <label>Invoice Date <strong class="text-danger">*</strong></label>
            <div class="input-group">
                <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-calendar"></i></span>
                </div>
                <input type="date" class="form-control" name="date" max="2999-12-31" value="<?= date("Y-m-d"); ?>" required>
            </div>
        </div>

    </div>
    <div class="modal-footer">
        <button type="submit" name="add_quote_to_invoice" class="btn btn-primary text-bold">
            <strong><i class="fas fa-check mr-2"></i>Create Invoice</strong>
        </button>
        <button type="button" class="btn btn-light" data-dismiss="modal">
            <i class="fas fa-times mr-2"></i>Cancel
        </button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
