<?php

/*
 * An expense's quick look - the receipt (shown right in the window when it
 * is a photo or a PDF, or attached here when there isn't one), who it was
 * paid to and for what, the account and how it was paid, the client, who
 * entered it, the refund it is or the refunds against it, billing it on to
 * the client (the invoice it is on, with the margin, or what it will bill
 * at), and the stock deliveries it paid for. Opened from the expenses list, the vendor's page,
 * Transactions and search (expenses.php?open=).
 */

require_once '../../../includes/modal_header.php';

enforceUserPermission('module_financial');

$row = expenseRow(intval($_GET['id'] ?? 0));
if (!$row) {
    echo json_encode(['error' => 'That expense no longer exists.']);
    exit;
}

$expense_id = intval($row['expense_id']);
$client_id = intval($row['expense_client_id']);
$can_edit = lookupUserPermission('module_financial') >= 2;
$can_delete = lookupUserPermission('module_financial') >= 3;
$is_transfer = !empty($row['transfer_id']);
$amount = floatval($row['expense_amount']);
$currency = (string) $row['expense_currency_code'];
$receipt_kind = $row['expense_receipt'] && expenseReceiptPath($row['expense_receipt']) !== '' ? expenseReceiptKind($row['expense_receipt']) : '';
$receipt_missing = $row['expense_receipt'] && $receipt_kind === '' && expenseReceiptPath($row['expense_receipt']) === '';
$receipt_url = "expense_receipt.php?expense_id=$expense_id";

// Billing it on to the client
$can_sales = lookupUserPermission('module_sales');
$billed_on = intval($row['expense_invoice_id']);
$cost = round($amount - floatval($row['refunded_amount']), 2);
$billable = !$billed_on && intval($row['expense_billable']) && $client_id && $amount > 0 && !$is_transfer;
$bill_price = expenseBillPrice($cost, $row['expense_markup']);
$can_unbill = $billed_on && $can_edit && expenseCanUnbill($row['invoice_status']);

// Refunds against it, and the stock it paid for
$refunds = [];
$sql = mysqli_query($mysqli, "SELECT expense_id, expense_date, expense_amount, expense_description FROM expenses WHERE expense_refund_of_id = $expense_id ORDER BY expense_date ASC, expense_id ASC");
while ($refund = mysqli_fetch_assoc($sql)) {
    $refunds[] = $refund;
}
$stock = [];
$sql = mysqli_query($mysqli, "SELECT stock_qty, stock_unit_cost, stock_created_at, product_id, product_name FROM product_stock
    JOIN products ON product_id = stock_product_id
    WHERE stock_expense_id = $expense_id ORDER BY stock_id ASC");
while ($move = mysqli_fetch_assoc($sql)) {
    $stock[] = $move;
}

ob_start();

?>

<div class="modal-header bg-dark" data-bs-theme="dark">
    <div class="d-flex align-items-center gap-3 min-w-0 flex-grow-1">
        <?= vendorTile($row['vendor_name'] ?: 'Transfer', 'lg') ?>
        <div class="min-w-0 flex-grow-1">
            <h5 class="modal-title d-flex flex-wrap align-items-center column-gap-2">
                <span class="fw-semibold text-break"><?= escapeHtml($is_transfer ? 'Transfer out' : ($row['vendor_name'] ?: 'No vendor')) ?></span>
                <?php if ($amount < 0) { ?><span class="badge rounded-pill text-bg-success fw-normal fs-6">Refund</span><?php } ?>
            </h5>
            <div class="small text-body-secondary mt-1"><?= escapeHtml(implode(' · ', array_filter([date('M j, Y', strtotime($row['expense_date'])), $row['category_name']]))) ?></div>
        </div>
        <div class="text-end">
            <div class="fs-4 fw-semibold itflow-money <?= $amount < 0 ? 'text-success' : 'text-white' ?>"><?= escapeHtml(billingMoney($amount, $currency)) ?></div>
        </div>
    </div>
    <button type="button" class="btn-close btn-close-white ms-3" data-bs-dismiss="modal" aria-label="Close"></button>
</div>

<div class="modal-body">

    <?php if ($is_transfer) { ?>
        <div class="alert alert-info small mb-3"><i class="fas fa-exchange-alt me-2"></i>This is the out side of a transfer between accounts - it is changed or deleted from <a href="transfers.php">Transfers</a>.</div>
    <?php } ?>

    <!-- The receipt -->
    <?php if ($receipt_kind) { ?>
        <div class="itflow-file-preview mb-3">
            <?php if ($receipt_kind === 'image') { ?>
                <a href="<?= $receipt_url ?>&amp;action=view" target="_blank" title="Open full size"><img src="<?= $receipt_url ?>&amp;action=view" alt="Receipt"></a>
            <?php } else { ?>
                <iframe src="<?= $receipt_url ?>&amp;action=view#view=FitH" title="Receipt"></iframe>
            <?php } ?>
        </div>
    <?php } elseif (!$is_transfer) { ?>
        <div class="itflow-expense-receipt-empty mb-3">
            <i class="fas fa-receipt fa-2x opacity-50 mb-2"></i>
            <div class="mb-2"><?= $receipt_missing ? "The receipt's file is missing from uploads/expenses/." : 'No receipt yet.' ?></div>
            <?php if ($can_edit) { ?>
                <form action="post.php" method="post" enctype="multipart/form-data" class="d-flex flex-wrap justify-content-center gap-2" autocomplete="off">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <input type="hidden" name="expense_id" value="<?= $expense_id ?>">
                    <div class="input-group" style="max-width: 26rem;">
                        <span class="input-group-text"><i class="fa fa-fw fa-paperclip"></i></span>
                        <input type="file" class="form-control" name="file" accept="image/*,application/pdf" required>
                        <button type="submit" name="expense_receipt_save" class="btn btn-primary"><i class="fas fa-upload me-1"></i>Attach</button>
                    </div>
                </form>
            <?php } ?>
        </div>
    <?php } ?>

    <!-- At a glance -->
    <div class="row g-2 itflow-asset-glance">
        <div class="col-sm-6">
            <?php if (!$is_transfer && intval($row['expense_vendor_id'])) { ?>
                <div class="itflow-glance-item" title="Vendor">
                    <i class="fas fa-fw fa-building"></i>
                    <a class="text-truncate" href="vendor.php?vendor_id=<?= intval($row['expense_vendor_id']) ?>"><?= escapeHtml($row['vendor_name']) ?></a>
                </div>
            <?php } ?>
            <div class="itflow-glance-item" title="Category">
                <i class="fas fa-fw fa-list"></i>
                <span><?= expenseCategoryDot($row['category_color']) ?><?= escapeHtml($row['category_name'] ?: 'No category') ?></span>
            </div>
            <div class="itflow-glance-item" title="Paid from">
                <i class="fas fa-fw fa-piggy-bank"></i>
                <span><?= escapeHtml($row['account_name'] ?: 'No account') ?><?= $row['expense_payment_method'] ? ' <span class="text-body-secondary">&middot; ' . escapeHtml($row['expense_payment_method']) . '</span>' : '' ?></span>
            </div>
            <?php if ($row['expense_reference']) { ?>
                <div class="itflow-glance-item" title="Reference">
                    <i class="fas fa-fw fa-hashtag"></i>
                    <span class="font-monospace text-break"><?= escapeHtml($row['expense_reference']) ?></span>
                </div>
            <?php } ?>
        </div>
        <div class="col-sm-6">
            <div class="itflow-glance-item" title="Client">
                <i class="fas fa-fw fa-user"></i>
                <?php if ($client_id) { ?>
                    <a class="text-truncate" href="client_overview.php?client_id=<?= $client_id ?>"><?= escapeHtml($row['client_name']) ?></a>
                <?php } else { ?>
                    <span class="text-body-secondary">Not for a client</span>
                <?php } ?>
            </div>
            <div class="itflow-glance-item" title="Entered">
                <i class="fas fa-fw fa-clock"></i>
                <span><span title="<?= escapeHtml($row['expense_created_at']) ?>"><?= timeAgo($row['expense_created_at']) ?></span><?php if ($row['created_by_name']) { ?> <span class="text-body-secondary">by <?= escapeHtml($row['created_by_name']) ?></span><?php } ?></span>
            </div>
            <?php if ($row['expense_recurring_expense_id']) { ?>
                <div class="itflow-glance-item" title="Recorded by">
                    <i class="fas fa-fw fa-redo-alt"></i>
                    <span>Recorded by <?= $row['recurring_expense_frequency'] !== null
                        ? '<a href="#" class="ajax-modal" data-modal-size="lg" data-modal-url="modals/recurring_expense/recurring_expense.php?id=' . intval($row['expense_recurring_expense_id']) . '">the ' . escapeHtml(strtolower(recurringInvoiceFrequencies()[$row['recurring_expense_frequency']] ?? 'monthly') . ' recurring expense') . '</a>'
                        : 'a recurring expense since deleted' ?></span>
                </div>
            <?php } ?>
            <?php if ($billed_on) { ?>
                <div class="itflow-glance-item" title="Billed">
                    <i class="fas fa-fw fa-file-invoice-dollar"></i>
                    <span>
                        <?php if ($row['invoice_status'] === null) { ?>
                            Billed on an invoice since deleted
                        <?php } else { ?>
                            Billed on <?= $can_sales ? '<a href="invoice.php?invoice_id=' . $billed_on . '">' . escapeHtml($row['invoice_prefix'] . $row['invoice_number']) . '</a>' : escapeHtml($row['invoice_prefix'] . $row['invoice_number']) ?><?= $row['invoice_status'] === 'Cancelled' ? ' <span class="text-danger-emphasis">(cancelled)</span>' : '' ?>
                            <?php if ($row['billed_subtotal'] !== null) { ?>
                                at <?= escapeHtml(billingMoney($row['billed_subtotal'], $currency)) ?>
                                <span class="text-body-secondary">&middot; <?= escapeHtml(expenseMarginText($row['billed_subtotal'], $cost)) ?></span>
                            <?php } ?>
                        <?php } ?>
                    </span>
                </div>
            <?php } elseif ($billable) { ?>
                <div class="itflow-glance-item" title="To bill">
                    <i class="fas fa-fw fa-file-invoice-dollar"></i>
                    <span>
                        <?php if ($cost <= 0) { ?>
                            Billable, but refunded in full
                        <?php } else { ?>
                            <span class="text-warning-emphasis fw-semibold">To bill</span> at <?= escapeHtml(billingMoney($bill_price, $currency)) ?>
                            <span class="text-body-secondary">&middot; <?= escapeHtml(rtrim(rtrim(number_format(floatval($row['expense_markup']), 2, '.', ''), '0'), '.')) ?>% markup</span>
                            <?php if ($can_sales) { ?>
                                <span class="text-body-secondary">&middot;</span> <a href="unbilled_time.php?client_id=<?= $client_id ?>">Unbilled Time</a>
                            <?php } ?>
                        <?php } ?>
                    </span>
                </div>
            <?php } ?>
            <?php if ($row['expense_refund_of_id']) { ?>
                <div class="itflow-glance-item" title="Refund of">
                    <i class="fas fa-fw fa-undo-alt"></i>
                    <span>Money back on <a href="#" class="ajax-modal" data-modal-size="lg" data-modal-url="modals/expense/expense.php?id=<?= intval($row['expense_refund_of_id']) ?>"><?= $row['refund_of_date'] !== null ? escapeHtml(billingMoney($row['refund_of_amount'], $currency) . ' from ' . $row['refund_of_date']) : 'an expense since deleted' ?></a></span>
                </div>
            <?php } ?>
            <?php if ($refunds) { ?>
                <div class="itflow-glance-item" title="Refunds">
                    <i class="fas fa-fw fa-undo-alt"></i>
                    <span>
                        <span class="text-success-emphasis"><?= escapeHtml(billingMoney($row['refunded_amount'], $currency)) ?> back</span>
                        <span class="text-body-secondary">&middot;
                            <?php foreach ($refunds as $i => $refund) { ?><?= $i ? ', ' : '' ?><a href="#" class="ajax-modal" data-modal-size="lg" data-modal-url="modals/expense/expense.php?id=<?= intval($refund['expense_id']) ?>"><?= escapeHtml($refund['expense_date']) ?></a><?php } ?>
                        </span>
                    </span>
                </div>
            <?php } ?>
        </div>
    </div>

    <?php if (trim((string) $row['expense_description']) !== '') { ?>
        <div class="mt-3">
            <div class="small fw-semibold text-uppercase text-body-secondary mb-1">Description</div>
            <div class="text-break" style="white-space: pre-line;"><?= escapeHtml($row['expense_description']) ?></div>
        </div>
    <?php } ?>

    <?php if ($stock) { ?>
        <div class="mt-3">
            <div class="small fw-semibold text-uppercase text-body-secondary mb-1">Stock it paid for</div>
            <?php foreach ($stock as $move) { ?>
                <div class="d-flex justify-content-between gap-2 small py-1 border-bottom">
                    <a class="text-truncate" href="product.php?product_id=<?= intval($move['product_id']) ?>"><i class="fas fa-fw fa-box-open text-secondary me-1"></i><?= escapeHtml(rtrim(rtrim(number_format(floatval($move['stock_qty']), 2, '.', ''), '0'), '.') . ' × ' . $move['product_name']) ?></a>
                    <span class="text-body-secondary text-nowrap"><?= $move['stock_unit_cost'] !== null ? escapeHtml(billingMoney($move['stock_unit_cost'], $currency)) . ' each' : '' ?></span>
                </div>
            <?php } ?>
        </div>
    <?php } ?>

</div>

<div class="modal-footer">
    <?php if ($receipt_kind) { ?>
        <a class="btn btn-outline-secondary" href="<?= $receipt_url ?>"><i class="fas fa-download me-2"></i>Receipt</a>
    <?php } ?>
    <?php if ($can_edit && !$is_transfer) { ?>
        <button type="button" class="btn btn-primary text-bold ajax-modal" data-modal-size="lg" data-modal-url="modals/expense/expense_edit.php?id=<?= $expense_id ?>"><i class="fas fa-edit me-2"></i>Edit</button>
        <div class="btn-group">
            <button type="button" class="btn btn-outline-secondary ajax-modal" data-modal-size="lg" data-modal-url="modals/expense/expense_copy.php?id=<?= $expense_id ?>"><i class="fas fa-copy me-2"></i>Copy</button>
            <?php if ($amount > 0) { ?>
                <button type="button" class="btn btn-outline-secondary ajax-modal" data-modal-size="lg" data-modal-url="modals/expense/expense_refund.php?id=<?= $expense_id ?>"><i class="fas fa-undo-alt me-2"></i>Refund</button>
            <?php } ?>
        </div>
    <?php } ?>
    <?php if ($can_unbill) { ?>
        <a class="btn btn-outline-secondary confirm-link" href="post.php?expense_unbill=<?= $expense_id ?>&amp;csrf_token=<?= $_SESSION['csrf_token'] ?>"
            data-confirm-text="<?= $row['invoice_status'] === 'Cancelled' ? escapeHtml($row['invoice_prefix'] . $row['invoice_number']) . ' is cancelled, so its line stays on it' : 'Its line comes off ' . escapeHtml($row['invoice_prefix'] . $row['invoice_number']) ?> and it waits to be billed again."><i class="fas fa-unlink"></i><span class="d-none d-sm-inline ms-2">Take off Invoice</span></a>
    <?php } ?>
    <?php if ($can_delete && !$is_transfer) { ?>
        <a class="btn btn-outline-danger confirm-link" href="post.php?delete_expense=<?= $expense_id ?>&amp;csrf_token=<?= $_SESSION['csrf_token'] ?>"
            data-confirm-text="<?= escapeHtml(expenseLabel($row)) ?> goes for good<?= $row['expense_receipt'] ? ', with its receipt' : '' ?>.<?= $billed_on && $row['invoice_status'] !== null ? ' Its line stays on ' . escapeHtml($row['invoice_prefix'] . $row['invoice_number']) . '.' : '' ?>"><i class="fas fa-trash"></i><span class="d-none d-sm-inline ms-2">Delete</span></a>
    <?php } ?>
    <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fa fa-times me-2"></i>Close</button>
</div>

<?php
require_once '../../../includes/modal_footer.php';
