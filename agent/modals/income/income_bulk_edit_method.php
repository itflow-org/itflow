<?php

require_once '../../../includes/modal_header.php';

// Income rows are a UNION of payments and revenues, so each checkbox carries a composite
// reference ("Payment-12" / "Revenue-7"). Whitelist the shape before echoing it back.
$income_ids = preg_grep('/^(Payment|Revenue)-[1-9][0-9]*$/', array_filter((array) ($_GET['income_ids'] ?? []), 'is_string'));

$count = count($income_ids);

// Generate the HTML form content using output buffering.
ob_start();

?>
<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fa fa-fw fa-money-check-alt me-2"></i>Set Payment Method for <strong><?= $count ?></strong> Income Record<?= $count == 1 ? '' : 's' ?></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>

<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <?php foreach ($income_ids as $income_id) { ?> <input type="hidden" name="income_ids[]" value="<?= $income_id ?>"><?php } ?>
    <div class="modal-body">

        <div class="mb-3">
            <label>Payment Method <strong class="text-danger">*</strong></label>
            <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-money-check-alt"></i></span>
                <select class="form-select select2" name="bulk_payment_method" data-placeholder="- Select a Method of Payment -" required>
                    <option></option>
                    <?php

                    $sql = mysqli_query($mysqli, "SELECT payment_method_name FROM payment_methods ORDER BY payment_method_name ASC");
                    while ($row = mysqli_fetch_assoc($sql)) {
                        $payment_method_name = escapeHtml($row['payment_method_name']);
                        ?>
                        <option><?= $payment_method_name ?></option>

                        <?php
                    }
                    ?>
                </select>
            </div>
        </div>

    </div>
    <div class="modal-footer">
        <button type="submit" name="bulk_edit_income_method" class="btn btn-primary text-bold"><i class="fa fa-fw fa-check me-2"></i>Set</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fa fa-times me-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
