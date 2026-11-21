<?php

/*
 * Delete one of your asset types - saying first what has it: its assets
 * (archived ones too), the products that become one and the recurring invoice
 * lines that count them. They move to another type, picked here, taking the
 * details that one has. Its own details go, with what was kept in them. The
 * work is assetTypeDelete().
 */

require_once '../../includes/modal_header.php';

$asset_type = assetTypeRow(intval($_GET['id'] ?? 0));

ob_start();

if (!$asset_type) { ?>
    <div class="modal-body text-center text-secondary py-5">That type is gone - it may have been deleted.</div>
<?php } else {
    $name = $asset_type['asset_type_name'];
    $info = assetTypeInfo($name);
    $usage = assetTypeUsage($name);
    $targets = assetTypeMoveTargets($name);
    $default_target = isset($targets[$info['base']]) ? $info['base'] : 'Other';
    $has = $usage['assets'] + $usage['archived'] + $usage['products'] + $usage['lines'];
    $values = array_sum(assetTypeDetailValueCounts(array_column($asset_type['details'], 'asset_type_detail_id')));
    $plural = function ($n, $one, $many) {
        return number_format($n) . ' ' . ($n === 1 ? $one : $many);
    }; ?>

<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fas fa-fw fa-trash me-2"></i>Delete <strong><?= escapeHtml($name) ?></strong></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="asset_type_id" value="<?= intval($asset_type['asset_type_id']) ?>">

    <div class="modal-body">
        <p class="mb-3 d-flex align-items-center gap-2">
            <span class="itflow-asset-icon itflow-asset-icon-sm itflow-asset-tone-<?= escapeHtml($info['tone']) ?>" aria-hidden="true"><i class="fas fa-fw <?= escapeHtml($info['icon']) ?>"></i></span>
            <span><strong><?= escapeHtml($name) ?></strong> goes for good. To keep it on its assets but stop it being picked, switch it off instead.</span>
        </p>

        <?php if ($has) { ?>
            <?php $has_list = array_values(array_filter([
                $usage['assets'] ? '<strong>' . $plural($usage['assets'], 'asset', 'assets') . '</strong>' : '',
                $usage['archived'] ? $plural($usage['archived'], 'archived asset', 'archived assets') : '',
                $usage['products'] ? $plural($usage['products'], 'product', 'products') : '',
                $usage['lines'] ? $plural($usage['lines'], 'recurring invoice line', 'recurring invoice lines') : '',
            ])); ?>
            <div class="alert alert-warning small py-2">
                <i class="fas fa-fw fa-layer-group me-1"></i><?= count($has_list) > 1 ? implode(', ', array_slice($has_list, 0, -1)) . ' and ' . end($has_list) : $has_list[0] ?> <?= $has === 1 ? 'has' : 'have' ?> this type.
            </div>
            <div class="mb-3">
                <label class="form-label">Move <?= $has === 1 ? 'it' : 'them' ?> to <strong class="text-danger">*</strong></label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-fw fa-exchange-alt"></i></span>
                    <select class="form-select select2" name="move_to" required>
                        <?php foreach ($targets as $target => $target_icon) { ?>
                            <option<?= $target === $default_target ? ' selected' : '' ?><?= selectOptionData(['icon' => 'fas ' . $target_icon]) ?>><?= escapeHtml($target) ?></option>
                        <?php } ?>
                    </select>
                </div>
                <div class="form-text">An asset keeps the details that type has too. A recurring line that counts this type's assets counts that type's instead.</div>
            </div>
        <?php } else { ?>
            <p class="small text-secondary"><i class="fas fa-fw fa-check me-1"></i>No assets, products or recurring invoice lines have it.</p>
        <?php } ?>

        <?php if ($asset_type['details']) { ?>
            <p class="small text-secondary mb-0">
                <i class="fas fa-fw fa-microchip me-1"></i>Its <?= $plural(count($asset_type['details']), 'detail', 'details') ?> of its own go too<?= $values ? ', with ' . $plural($values, 'value', 'values') . ' kept in them' : '' ?>.
            </p>
        <?php } ?>
    </div>
    <div class="modal-footer">
        <button type="submit" name="delete_asset_type" class="btn btn-danger fw-bold"><i class="fas fa-trash me-2"></i>Delete</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fas fa-times me-2"></i>Cancel</button>
    </div>
</form>

<?php }
require_once '../../../includes/modal_footer.php';
