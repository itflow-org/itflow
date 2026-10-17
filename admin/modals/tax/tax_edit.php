<?php

require_once '../../includes/modal_header.php';

$tax_id = intval($_GET['id'] ?? 0);
$tax = taxRow($tax_id);

ob_start();

if (!$tax) { ?>
    <div class="modal-body text-center text-secondary py-5">That tax is gone.</div>
<?php } else {
    $usage = taxUsage($tax_id);
    $billed = taxBilled($tax_id);
    $drafts = count(taxDocIds('invoice', $tax_id)) + count(taxDocIds('quote', $tax_id));
    $replaced_by = intval($tax['tax_replaced_by']) ? taxRow($tax['tax_replaced_by']) : null;
    $on = [];
    foreach (['invoices' => ['invoice', 'invoices'], 'quotes' => ['quote', 'quotes']] as $key => [$one, $many]) {
        if ($usage[$key]) {
            $on[] = number_format($usage[$key]) . ' ' . ($usage[$key] === 1 ? $one : $many);
        }
    } ?>
    <div class="modal-header bg-dark">
        <h5 class="modal-title"><i class="fas fa-fw fa-balance-scale me-2"></i>Editing tax: <strong><?= escapeHtml($tax['tax_name']) ?></strong></h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
    </div>
    <form action="post.php" method="post" autocomplete="off">
        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
        <input type="hidden" name="tax_id" value="<?= $tax_id ?>">

        <div class="modal-body">
            <?php if ($tax['tax_archived_at']) { ?>
                <div class="alert alert-secondary small">
                    <i class="fas fa-fw fa-archive me-1"></i><?= $replaced_by ? 'Replaced by <strong>' . escapeHtml(taxLabel($replaced_by)) . '</strong> on ' : 'Archived on ' ?><?= date('M j, Y', strtotime($tax['tax_archived_at'])) ?> - it stays on what was billed with it, but isn't offered for new lines.
                </div>
            <?php } elseif ($billed) { ?>
                <div class="alert alert-info small">
                    <i class="fas fa-fw fa-history me-1"></i>On <?= escapeHtml(implode(' and ', $on)) ?>. A new rate or parts makes a new <strong><?= escapeHtml($tax['tax_name']) ?></strong> from now on: what was billed keeps this rate, and its products, clients, recurring invoices and the default move to the new one. The name, description and registration number change in place.
                    <?php if ($drafts) { ?>
                        <input type="hidden" name="drafts_asked" value="1">
                        <div class="form-check mt-2 mb-0">
                            <input type="checkbox" class="form-check-input" name="move_drafts" value="1" id="taxMoveDrafts" checked>
                            <label class="form-check-label" for="taxMoveDrafts">Move <?= $drafts === 1 ? 'the draft invoice or quote' : "the $drafts draft invoices and quotes" ?> with it to the new rate too</label>
                        </div>
                    <?php } ?>
                </div>
            <?php } ?>
            <?php require __DIR__ . '/tax_form.php'; ?>
        </div>
        <div class="modal-footer">
            <button type="submit" name="edit_tax" class="btn btn-primary text-bold"><i class="fas fa-check me-2"></i>Save</button>
            <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fas fa-times me-2"></i>Cancel</button>
        </div>
    </form>
<?php }

require_once '../../../includes/modal_footer.php';
