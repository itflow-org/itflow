<?php

/*
 * Archive a tax - no longer offered for new lines - and, when products,
 * clients, recurring invoices, drafts or the default use it, move them to
 * another rate (functions/tax.php: taxArchive()).
 */

require_once '../../includes/modal_header.php';

$tax_id = intval($_GET['id'] ?? 0);
$tax = taxRow($tax_id);

ob_start();

if (!$tax || $tax['tax_archived_at']) { ?>
    <div class="modal-body text-center text-secondary py-5">That tax is already archived.</div>
<?php } else {
    $usage = taxUsage($tax_id);
    $drafts = count(taxDocIds('invoice', $tax_id)) + count(taxDocIds('quote', $tax_id));
    $uses = [];
    foreach (['products' => ['product', 'products'], 'clients' => ['client', 'clients'], 'recurring' => ['recurring invoice', 'recurring invoices']] as $key => [$one, $many]) {
        if ($usage[$key]) {
            $uses[] = number_format($usage[$key]) . ' ' . ($usage[$key] === 1 ? $one : $many);
        }
    }
    if ($drafts) {
        $uses[] = $drafts . ' draft ' . ($drafts === 1 ? 'invoice or quote' : 'invoices and quotes');
    }
    $others = billingTaxes();
    unset($others[$tax_id]); ?>
    <div class="modal-header bg-dark">
        <h5 class="modal-title"><i class="fas fa-fw fa-archive me-2"></i>Archive tax: <strong><?= escapeHtml(taxLabel($tax)) ?></strong></h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
    </div>
    <form action="post.php" method="post" autocomplete="off">
        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
        <input type="hidden" name="tax_id" value="<?= $tax_id ?>">

        <div class="modal-body">
            <p>It won't be offered for new lines. Invoices and quotes already billed with it keep it.</p>
            <?php if ($uses || $usage['default']) { ?>
                <div class="alert alert-warning small">
                    <i class="fas fa-fw fa-exclamation-triangle me-1"></i>Still used by <?= escapeHtml($uses ? implode(', ', $uses) : 'nothing but the default') ?><?= $uses && $usage['default'] ? ', and it is the default' : '' ?>.
                </div>
                <div class="mb-2">
                    <label class="form-label">Move them to</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fa fa-fw fa-exchange-alt"></i></span>
                        <select class="form-select" name="move_to">
                            <option value="0">Leave them where they are</option>
                            <?php foreach ($others as $id => $other) { ?>
                                <option value="<?= intval($id) ?>"><?= escapeHtml(taxLabel($other)) ?></option>
                            <?php } ?>
                        </select>
                    </div>
                    <small class="text-secondary">Left where they are, recurring invoices and drafts keep charging it, products and clients fall back to the next rate along for new lines<?= $usage['default'] ? ', and there is no default until you pick one' : '' ?>.</small>
                </div>
            <?php } ?>
        </div>
        <div class="modal-footer">
            <button type="submit" name="archive_tax" class="btn btn-danger text-bold"><i class="fas fa-archive me-2"></i>Archive</button>
            <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fas fa-times me-2"></i>Cancel</button>
        </div>
    </form>
<?php }

require_once '../../../includes/modal_footer.php';
