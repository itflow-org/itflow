<?php

/*
 * Delete a payment method - saying first what it has been used on. A method
 * nothing used just goes; one that was used has its records moved to another
 * method first - a merge, for two names for one thing ("Card" and "Credit
 * Card"). To keep them as they are, it is archived instead. The work is
 * paymentMethodDelete().
 */

require_once '../../includes/modal_header.php';

$payment_method = paymentMethodRow(intval($_GET['id'] ?? 0));

ob_start();

if (!$payment_method) { ?>
    <div class="modal-body text-center text-secondary py-5">That payment method is gone - it may have been deleted.</div>
<?php } else {
    $payment_method_id = intval($payment_method['payment_method_id']);
    $name = escapeHtml($payment_method['payment_method_name']);
    $usage = paymentMethodUsageFor($payment_method['payment_method_name']);
    $is_default = paymentMethodDefaultFor($payment_method['payment_method_name']);
    $others = array_filter(paymentMethodsAll(), function ($row) use ($payment_method_id) {
        return intval($row['payment_method_id']) !== $payment_method_id;
    }); ?>

<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fas fa-fw fa-trash me-2"></i>Delete <strong><?= $name ?></strong></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="payment_method_id" value="<?= $payment_method_id ?>">

    <div class="modal-body">
        <?php if ($usage['total']) { ?>
            <div class="alert alert-warning small py-2">
                <i class="fas fa-fw fa-exchange-alt me-1"></i>Used on <strong><?= escapeHtml(paymentMethodUsageText($usage)) ?></strong>.
                <a href="/agent/transactions.php?<?= escapeHtml(http_build_query(['method' => $payment_method['payment_method_name']])) ?>" target="_blank">See them</a>
            </div>
            <div class="mb-3">
                <label class="form-label">Change them to <strong class="text-danger">*</strong></label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-random"></i></span>
                    <select class="form-select" name="move_to" required>
                        <option value="">- Pick a method -</option>
                        <?php foreach ($others as $other_id => $other) { ?>
                            <option value="<?= intval($other_id) ?>"><?= escapeHtml($other['payment_method_name']) ?><?= empty($other['payment_method_archived_at']) ? '' : ' (archived)' ?></option>
                        <?php } ?>
                    </select>
                </div>
                <small class="text-secondary">Merges <?= $name ?> into the method you pick<?= $is_default ? ', which also takes its place as a default' : '' ?>. To leave its records as they are, archive it instead - it stops being offered, and they keep saying <?= $name ?>.</small>
            </div>
        <?php } else { ?>
            <p class="mb-2">Nothing has been paid this way, so nothing else changes.</p>
        <?php } ?>
        <?php if ($is_default) { ?>
            <p class="small text-secondary mb-0"><i class="fas fa-fw fa-star me-1"></i>It is the default for <?= implode(' and ', array_map(function ($way) { return $way === 'in' ? 'payments' : 'expenses'; }, $is_default)) ?>.</p>
        <?php } ?>
    </div>
    <div class="modal-footer">
        <button type="submit" name="delete_payment_method" class="btn btn-danger text-bold"><i class="fas fa-trash me-2"></i><?= $usage['total'] ? 'Merge and delete' : 'Delete' ?></button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fas fa-times me-2"></i>Cancel</button>
    </div>
</form>

<?php }
require_once '../../../includes/modal_footer.php';
