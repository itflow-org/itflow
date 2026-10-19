<?php

/*
 * Bill licenses made from a template on their clients' recurring invoices -
 * a line for the product the template is sold as, counting each license's
 * seats bought or in use, so it follows them from then on. ?id= the template;
 * &software_ids[]= the licenses ticked on its page, or - without any - every
 * one not billed yet. Each goes on one of its client's recurring invoices -
 * an active one billed as often as the product first. Saved by
 * bill_software_template (admin/post/software_template.php).
 */

require_once '../../includes/modal_header.php';

$template = softwareTemplateRow($_GET['id'] ?? 0);

ob_start();

if (!$template) { ?>
    <div class="modal-header bg-dark">
        <h5 class="modal-title"><i class="fa fa-fw fa-file-invoice-dollar me-2"></i>Bill seats</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
    </div>
    <div class="modal-body text-center text-secondary py-5">That license template no longer exists.</div>
<?php } else {
    $software_template_id = intval($template['software_template_id']);
    $product_id = intval($template['software_template_product_id']);
    $sellable = $template['product_name'] !== null && !$template['product_archived_at'];
    $all = softwareTemplateLicenses($software_template_id, $product_id);
    $ids = array_filter(array_map('intval', (array) ($_GET['software_ids'] ?? [])));
    $picked = $ids ? array_intersect_key($all, array_flip($ids)) : $all;
    $billed = array_filter($picked, function ($row) {
        return !empty($row['billing']);
    });
    $licenses = array_diff_key($picked, $billed);
    $recurring = softwareTemplateRecurringFor(array_column($licenses, 'software_client_id'));
    $count = ($_GET['count'] ?? '') === 'used' ? 'used' : 'total';
    ?>

<div class="modal-header bg-dark">
    <h5 class="modal-title text-truncate"><i class="fa fa-fw fa-file-invoice-dollar me-2"></i>Bill seats <small class="ms-1 opacity-75"><?= escapeHtml($template['software_template_name']) ?></small></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="software_template_id" value="<?= $software_template_id ?>">

    <div class="modal-body">
        <?php if (!$sellable) { ?>
            <div class="text-center text-secondary py-4">
                <?= $template['product_name'] !== null ? 'The product it is sold as, <strong>' . escapeHtml($template['product_name']) . '</strong>, is archived.' : 'It isn\'t sold as a product yet.' ?>
                Pick the product it is sold as on the template, then bill its licenses from here.
                <div class="mt-3"><button type="button" class="btn btn-sm btn-outline-primary ajax-modal" data-modal-size="lg" data-modal-url="modals/software_template/software_template_edit.php?id=<?= $software_template_id ?>"><i class="fas fa-edit me-1"></i>Edit the template</button></div>
            </div>
        <?php } elseif (!$licenses) { ?>
            <div class="text-center text-secondary py-4"><i class="fas fa-check text-success me-1"></i><?= $picked ? 'Every one of them is billed already.' : 'No licenses made from it yet.' ?></div>
        <?php } else { ?>
            <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
                <?= productTile($template['product_name'] !== null ? (string) getFieldById('products', $product_id, 'product_type') : 'service') ?>
                <div class="min-w-0 flex-grow-1">
                    <div class="fw-semibold"><?= escapeHtml($template['product_name']) ?></div>
                    <div class="small text-secondary"><?= escapeHtml(softwareTemplatePriceText($template)) ?></div>
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label">Count</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-users"></i></span>
                    <select class="form-select" name="count">
                        <option value="total" <?= $count === 'total' ? 'selected' : '' ?>>The seats bought</option>
                        <option value="used" <?= $count === 'used' ? 'selected' : '' ?>>The seats in use - devices and users on it</option>
                    </select>
                </div>
                <div class="form-text">The line's quantity follows the license each time an invoice is made. One with no seat limit counts the seats in use.</div>
            </div>
            <div class="table-responsive border rounded">
                <table class="table table-sm align-middle mb-0">
                    <thead class="table-light text-nowrap">
                        <tr><th class="ps-2" style="width: 2rem;"></th><th>Client</th><th class="text-end">Seats</th><th class="pe-2" style="min-width: 14rem;">Recurring invoice</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($licenses as $software_id => $row) {
                        $client_id = intval($row['software_client_id']);
                        $options = $recurring[$client_id] ?? [];
                        // An active one billed as often as the product, then any active one, then the first
                        $default = 0;
                        foreach ($options as $recurring_id => $option) {
                            if (intval($option['recurring_invoice_status']) === 1 && $option['recurring_invoice_frequency'] === (string) $template['product_frequency']) {
                                $default = $recurring_id;
                                break;
                            }
                        }
                        $default = $default ?: (int) array_key_first($options);
                        ?>
                        <tr data-bill-id="<?= $software_id ?>">
                            <td class="ps-2"><input class="form-check-input" type="checkbox" name="software_ids[]" value="<?= $software_id ?>" aria-label="Bill <?= escapeHtml($row['client_name']) ?>" <?= $options ? 'checked' : 'disabled' ?>></td>
                            <td class="min-w-0">
                                <div class="fw-semibold text-truncate"><?= escapeHtml($row['client_name']) ?></div>
                                <?php if ($row['software_name'] !== $template['software_template_name']) { ?><div class="small text-secondary text-truncate"><?= escapeHtml($row['software_name']) ?></div><?php } ?>
                            </td>
                            <td class="text-end text-nowrap small"><?= $row['seats']['total'] > 0 ? $row['seats']['total'] . ' bought' : '<span title="No seats bought to count - it counts the seats in use">No limit</span>' ?><div class="text-secondary"><?= $row['seats']['used'] ?> in use</div></td>
                            <td class="pe-2">
                                <?php if ($options) { ?>
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text"><i class="fa fa-fw fa-redo-alt"></i></span>
                                        <select class="form-select" name="recurring[<?= $software_id ?>]">
                                            <?php foreach ($options as $recurring_id => $option) { ?>
                                                <option value="<?= $recurring_id ?>" <?= $recurring_id === $default ? 'selected' : '' ?>><?= escapeHtml($option['number'] . ' · ' . (recurringInvoiceFrequencies()[$option['recurring_invoice_frequency']] ?? $option['recurring_invoice_frequency']) . (trim((string) $option['recurring_invoice_scope']) !== '' ? ' · ' . $option['recurring_invoice_scope'] : '') . (intval($option['recurring_invoice_status']) === 1 ? '' : ' (paused)')) ?></option>
                                            <?php } ?>
                                        </select>
                                    </div>
                                <?php } else { ?>
                                    <span class="small text-secondary">No recurring invoice - <a href="/agent/recurring_invoices.php?client_id=<?= $client_id ?>">make one</a> first</span>
                                <?php } ?>
                            </td>
                        </tr>
                    <?php } ?>
                    </tbody>
                </table>
            </div>
            <?php if ($billed) { ?>
                <div class="small text-secondary mt-2"><?= count($billed) === 1 ? '1 of the ones picked is' : count($billed) . ' of the ones picked are' ?> billed already, so not here.</div>
            <?php } ?>
        <?php } ?>
    </div>
    <div class="modal-footer">
        <?php if ($sellable && $licenses) { ?>
            <button type="submit" name="bill_software_template" class="btn btn-primary text-bold"><i class="fa fa-check me-2"></i>Add to recurring invoices</button>
        <?php } ?>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fa fa-times me-2"></i><?= $sellable && $licenses ? 'Cancel' : 'Close' ?></button>
    </div>
</form>

<?php }

require_once '../../../includes/modal_footer.php';
