<?php

require_once '../../../includes/modal_header.php';

// Income rows are a UNION of payments and revenues, so each checkbox carries a composite
// reference ("Payment-12" / "Revenue-7"). Whitelist the shape before echoing it back.
$income_ids = preg_grep('/^(Payment|Revenue)-[1-9][0-9]*$/', array_filter((array) ($_GET['income_ids'] ?? []), 'is_string'));

$count = count($income_ids);

// A revenue carries its own category, but a payment does not - it inherits the category from the
// invoice it was paid against, so categorising a payment really means categorising that invoice.
// Work out up front what that will drag along, and say so.
$selected_payment_ids = [];

foreach ($income_ids as $income_ref) {
    if (str_starts_with($income_ref, 'Payment-')) {
        $selected_payment_ids[] = intval(substr($income_ref, strlen('Payment-')));
    }
}

$affected_invoice_count = 0;
$orphan_payment_count = 0;
$sibling_payment_count = 0;

if ($selected_payment_ids) {

    $selected_payment_ids_sql = implode(',', $selected_payment_ids);

    $sql = mysqli_query($mysqli, "SELECT
            SUM(CASE WHEN payment_invoice_id = 0 THEN 1 ELSE 0 END) AS orphan_count,
            COUNT(DISTINCT CASE WHEN payment_invoice_id != 0 THEN payment_invoice_id END) AS invoice_count
        FROM payments
        WHERE payment_id IN ($selected_payment_ids_sql)");
    $row = mysqli_fetch_assoc($sql);
    $orphan_payment_count = intval($row['orphan_count']);
    $affected_invoice_count = intval($row['invoice_count']);

    // Other payments sitting on those same invoices - not selected, but they will show the new
    // category too, because they read it from the invoice as well
    $sql = mysqli_query($mysqli, "SELECT COUNT(*) AS sibling_count FROM payments
        WHERE payment_archived_at IS NULL
        AND payment_id NOT IN ($selected_payment_ids_sql)
        AND payment_invoice_id != 0
        AND payment_invoice_id IN (SELECT payment_invoice_id FROM payments WHERE payment_id IN ($selected_payment_ids_sql) AND payment_invoice_id != 0)");
    $row = mysqli_fetch_assoc($sql);
    $sibling_payment_count = intval($row['sibling_count']);

}

// Generate the HTML form content using output buffering.
ob_start();

?>
<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fa fa-fw fa-list mr-2"></i>Set Category for <strong><?= $count ?></strong> Income Record<?= $count == 1 ? '' : 's' ?></h5>
    <button type="button" class="close text-white" data-dismiss="modal">
        <span>&times;</span>
    </button>
</div>

<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <?php foreach ($income_ids as $income_id) { ?> <input type="hidden" name="income_ids[]" value="<?= $income_id ?>"><?php } ?>
    <div class="modal-body">

        <?php if ($affected_invoice_count || $orphan_payment_count) { ?>
            <div class="alert alert-warning">
                <?php if ($affected_invoice_count) { ?>
                    A payment has no category of its own - it takes the one on the invoice it was paid against.
                    This will set the category on <strong><?= $affected_invoice_count ?></strong> invoice<?= $affected_invoice_count == 1 ? '' : 's' ?>,
                    which changes how <?= $affected_invoice_count == 1 ? 'that invoice' : 'those invoices' ?> categorise<?= $affected_invoice_count == 1 ? 's' : '' ?> everywhere else too.
                    <?php if ($sibling_payment_count) { ?>
                        <br><br><strong><?= $sibling_payment_count ?></strong> further payment<?= $sibling_payment_count == 1 ? '' : 's' ?> against
                        <?= $affected_invoice_count == 1 ? 'that invoice' : 'those invoices' ?> <?= $sibling_payment_count == 1 ? 'is' : 'are' ?> not selected,
                        but will show the new category as well.
                    <?php } ?>
                <?php } ?>
                <?php if ($orphan_payment_count) { ?>
                    <?php if ($affected_invoice_count) { ?><br><br><?php } ?>
                    <strong><?= $orphan_payment_count ?></strong> selected payment<?= $orphan_payment_count == 1 ? '' : 's' ?>
                    <?= $orphan_payment_count == 1 ? 'has' : 'have' ?> no linked invoice and will be skipped.
                <?php } ?>
            </div>
        <?php } ?>

        <div class="form-group">
            <label>Category <strong class="text-danger">*</strong></label>
            <div class="input-group">
                <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-list"></i></span>
                </div>
                <select class="form-control select2" name="bulk_category_id" data-placeholder="- Select a Category -" required>
                    <option></option>
                    <?php

                    $sql = mysqli_query($mysqli, "SELECT category_id, category_name FROM categories WHERE category_type = 'Income' AND category_archived_at IS NULL ORDER BY category_name ASC");
                    while ($row = mysqli_fetch_assoc($sql)) {
                        $category_id = intval($row['category_id']);
                        $category_name = escapeHtml($row['category_name']);
                        ?>
                        <option value="<?= $category_id ?>"><?= $category_name ?></option>

                        <?php
                    }
                    ?>
                </select>
            </div>
        </div>

    </div>
    <div class="modal-footer">
        <button type="submit" name="bulk_edit_income_category" class="btn btn-primary text-bold"><i class="fa fa-fw fa-check mr-2"></i>Set</button>
        <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fa fa-times mr-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
