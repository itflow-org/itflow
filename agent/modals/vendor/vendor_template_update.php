<?php

/*
 * Bring vendors in line with the template they were made from - ?vendor_id=
 * one vendor (its template), or ?template_id= with vendor_ids[] several made
 * from it. One vendor shows each field it differs in, its value beside the
 * template's; several show each field with how many of them differ. The
 * fields ticked are taken from the template (update_vendors_from_template,
 * agent/post/vendor.php) - never the account number, PIN or account manager,
 * which are each client's own. Opened from a vendor's page and from the
 * template's page in Admin, so it posts by its full path.
 */

require_once '../../../includes/modal_header.php';

$single = intval($_GET['vendor_id'] ?? 0);
$vendor_ids = $single ? [$single] : array_map('intval', array_filter((array) ($_GET['vendor_ids'] ?? []), 'is_scalar'));
$template_id = intval($_GET['template_id'] ?? 0);
if ($single) {
    $template_id = intval(getFieldById('vendors', $single, 'vendor_template_id'));
}
$template = vendorTemplateInfo($template_id);

// The vendors made from it this agent may change, with where each differs
$vendors = [];
if ($template) {
    foreach (array_unique(array_filter($vendor_ids)) as $vendor_id) {
        $row = vendorRow($vendor_id);
        if ($row && intval($row['vendor_template_id']) === $template_id && vendorCan($row['vendor_client_id'], 2)) {
            $row['differences'] = vendorTemplateDifferences($template, $row);
            $vendors[$vendor_id] = $row;
        }
    }
}
$differing = array_filter($vendors, function ($vendor) {
    return $vendor['differences'];
});
$field_counts = [];
foreach ($differing as $vendor) {
    foreach (array_keys($vendor['differences']) as $key) {
        $field_counts[$key] = ($field_counts[$key] ?? 0) + 1;
    }
}
$labels = vendorTemplateFieldLabels();

ob_start();

?>
<div class="modal-header bg-dark">
    <h5 class="modal-title text-truncate"><i class="fa fa-fw fa-sync-alt me-2"></i>Bring in line<?= $template ? ' with <strong>' . escapeHtml($template['vendor_template_name']) . '</strong>' : '' ?></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>

<?php if (!$template || !$vendors) { ?>
    <div class="modal-body text-center text-secondary py-5"><?= !$template ? 'That template no longer exists.' : 'None of those vendors are made from it, or yours to change.' ?></div>
<?php } elseif (!$differing) { ?>
    <div class="modal-body text-center text-secondary py-5" data-in-step><i class="fas fa-check-circle text-success fa-2x d-block mb-2"></i><?= count($vendors) === 1 ? 'It says' : 'They say' ?> what the template says already.</div>
<?php } else { ?>
<form action="/agent/post.php" method="post" autocomplete="off" data-vendor-template-update>
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="vendor_template_id" value="<?= intval($template['vendor_template_id']) ?>">
    <?php foreach (array_keys($differing) as $vendor_id) { ?>
        <input type="hidden" name="vendor_ids[]" value="<?= $vendor_id ?>">
    <?php } ?>

    <div class="modal-body">
        <?php if (count($vendors) === 1) {
            $vendor = reset($vendors); ?>
            <p class="small text-body-secondary mb-2">
                <strong><?= escapeHtml($vendor['vendor_name']) ?></strong><?= $vendor['client_name'] !== null ? ' for ' . escapeHtml($vendor['client_name']) : '' ?> differs from its template in <?= count($vendor['differences']) === 1 ? 'one field' : count($vendor['differences']) . ' fields' ?>. Tick the ones to take from the template.
            </p>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead class="table-light"><tr><th style="width: 2rem;"></th><th>Field</th><th>It says</th><th>The template says</th></tr></thead>
                    <tbody>
                    <?php foreach ($vendor['differences'] as $key => $label) { ?>
                        <tr data-difference="<?= $key ?>">
                            <td><input class="form-check-input" type="checkbox" name="fields[]" value="<?= $key ?>" id="vtField<?= $key ?>" checked></td>
                            <td class="text-nowrap"><label for="vtField<?= $key ?>"><i class="fas fa-fw <?= $labels[$key]['icon'] ?> text-secondary me-1"></i><?= $label ?></label></td>
                            <td class="small text-break"><?= vendorTemplateValueHtml($key, $vendor, 'vendor') ?: '<span class="text-body-secondary">Nothing</span>' ?></td>
                            <td class="small text-break"><?= vendorTemplateValueHtml($key, $template, 'template') ?></td>
                        </tr>
                    <?php } ?>
                    </tbody>
                </table>
            </div>
        <?php } else { ?>
            <p class="small text-body-secondary mb-2"><?= count($differing) === count($vendors) ? (count($vendors) === 2 ? 'Both vendors differ' : 'All ' . count($vendors) . ' vendors differ') : count($differing) . ' of the ' . count($vendors) . ' vendors differ' ?> from the template. Tick the fields to take from it - only the ones that differ change.</p>
            <div class="list-group mb-3">
                <?php foreach ($field_counts as $key => $count) { ?>
                    <label class="list-group-item d-flex align-items-center gap-2" data-difference="<?= $key ?>">
                        <input class="form-check-input mt-0" type="checkbox" name="fields[]" value="<?= $key ?>" checked>
                        <i class="fas fa-fw <?= $labels[$key]['icon'] ?> text-secondary"></i>
                        <span class="flex-grow-1"><?= $labels[$key]['label'] ?></span>
                        <span class="small text-body-secondary text-truncate" style="max-width: 50%;" title="What the template says"><?= vendorTemplateValueHtml($key, $template, 'template') ?></span>
                        <span class="badge rounded-pill bg-warning-subtle text-warning-emphasis border border-warning-subtle fw-normal"><?= $count ?> differ<?= $count === 1 ? 's' : '' ?></span>
                    </label>
                <?php } ?>
            </div>
            <div class="small text-body-secondary mb-1">The vendors</div>
            <ul class="list-unstyled small mb-0" style="max-height: 12rem; overflow-y: auto;">
                <?php foreach ($differing as $vendor) { ?>
                    <li class="py-1 border-bottom d-flex gap-2"><span class="fw-semibold"><?= escapeHtml($vendor['client_name'] ?? 'Ours') ?></span><span class="text-body-secondary text-truncate"><?= escapeHtml(implode(', ', $vendor['differences'])) ?></span></li>
                <?php } ?>
            </ul>
        <?php } ?>
        <div class="form-text mt-3">Their account number, PIN and account manager stay their own.</div>
    </div>
    <div class="modal-footer">
        <button type="submit" name="update_vendors_from_template" class="btn btn-primary text-bold"><i class="fa fa-check me-2"></i>Take them from the template</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fa fa-times me-2"></i>Cancel</button>
    </div>
</form>
<?php } ?>

<?php
require_once '../../../includes/modal_footer.php';
