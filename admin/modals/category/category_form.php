<?php

/*
 * A category's fields - for category_add.php and category_edit.php, which set
 * $category (a categoryRow(), or null when new), $kind (its category_type,
 * or '' to pick one) and for one being edited $category_used (what uses it,
 * categoryUsageFor()). The preview, colour swatches and icon search are
 * js/category_form.js. Posts to post.php - admin/post/category.php, or
 * agent/post/category.php when opened from a form on an agent page.
 */

isset($mysqli) || die("Direct file access is not allowed");

$c = $category ?? null;
$c_config = $kind !== '' ? categoryKind($kind) : null;
$c_color = $c ? categoryColor($c['category_color']) : ($c_config ? categoryNextColor($kind) : CATEGORY_COLORS[0]);
$c_icon = $c ? categoryIcon($c['category_icon']) : '';
$c_icons = CATEGORY_ICONS;
if ($c_icon !== '' && !in_array($c_icon, $c_icons, true)) {
    // An icon picked by name before - offered first, so it stays chosen
    array_unshift($c_icons, $c_icon);
}
$c_used = $category_used ?? ['all' => 0, 'archived' => 0, 'uses' => []];
$c_preview = categoryChip([
    'category_name' => $c['category_name'] ?? 'New category',
    'category_type' => $kind,
    'category_color' => $c_color,
    'category_icon' => $c_icon,
]);
$c_max = $c_config['max'] ?? 200;

?>

<div data-category-form data-category-name-was="<?= escapeHtml($c['category_name'] ?? '') ?>">
    <div class="d-flex flex-wrap align-items-center gap-2 mb-3 p-2 rounded border bg-body-tertiary">
        <span class="small text-secondary">Looks like</span>
        <span data-category-preview data-category-badge="<?= $kind === 'asset_status' ? escapeHtml(assetStatusBadge((string) ($c['category_name'] ?? ''), '')['class'] ?: 'secondary') : '' ?>"><?= $c_preview ?></span>
        <?php if ($c) { ?>
            <span class="small text-secondary ms-auto text-end">
                <?= $c_used['all'] ? 'On ' . escapeHtml(categoryUsageText($c, $c_used)) : 'Nothing uses it yet' ?>
            </span>
        <?php } ?>
    </div>

    <?php if (!$c && !$c_config) { ?>
        <div class="mb-3">
            <label class="form-label">For <strong class="text-danger">*</strong></label>
            <div class="input-group">
                <span class="input-group-text"><i class="fa fa-fw fa-layer-group"></i></span>
                <select class="form-select" name="type" required>
                    <option value="">- What it sorts -</option>
                    <?php foreach (categoryKinds() as $kind_key => $kind_config) { ?>
                        <option value="<?= escapeHtml($kind_key) ?>"><?= escapeHtml(ucfirst($kind_config['many'])) ?></option>
                    <?php } ?>
                </select>
            </div>
        </div>
    <?php } elseif (!$c) { ?>
        <input type="hidden" name="type" value="<?= escapeHtml($kind) ?>">
    <?php } ?>

    <div class="mb-3">
        <label class="form-label">Name <strong class="text-danger">*</strong></label>
        <div class="input-group">
            <span class="input-group-text"><i class="fa fa-fw fa-list-ul"></i></span>
            <input type="text" class="form-control" name="name" maxlength="<?= $c_max ?>" placeholder="<?= escapeHtml(ucfirst($c_config['one'] ?? 'category')) ?> name" value="<?= escapeHtml($c['category_name'] ?? '') ?>" required<?= $c ? '' : ' autofocus' ?> data-category-name>
        </div>
        <?php if ($c && $c_config && $c_config['by'] === 'name' && $c_used['all']) { ?>
            <small class="text-secondary" data-category-rename-note hidden><i class="fas fa-fw fa-info-circle me-1"></i>Renaming it renames it on the <?= escapeHtml(categoryUsageText($c, $c_used)) ?> that <?= $c_used['all'] === 1 ? 'has' : 'have' ?> it too.</small>
        <?php } ?>
    </div>

    <div class="mb-3">
        <label class="form-label">Description</label>
        <div class="input-group">
            <span class="input-group-text"><i class="fa fa-fw fa-align-left"></i></span>
            <input type="text" class="form-control" name="description" maxlength="255" placeholder="What it's for, and when to use it" value="<?= escapeHtml($c['category_description'] ?? '') ?>">
        </div>
    </div>

    <div class="mb-3">
        <label class="form-label">Color</label>
        <input type="hidden" name="color" value="<?= $c_color ?>" data-category-color>
        <div class="d-flex flex-wrap align-items-center gap-2">
            <div class="d-flex flex-wrap gap-1" role="group" aria-label="Colors">
                <button type="button" class="itflow-tag-swatch itflow-category-swatch-none<?= $c_color === '' ? ' active' : '' ?>" data-category-swatch="" title="No color" aria-label="No color" aria-pressed="<?= $c_color === '' ? 'true' : 'false' ?>"><i class="fas fa-ban" aria-hidden="true"></i></button>
                <?php foreach (CATEGORY_COLORS as $swatch) { ?>
                    <button type="button" class="itflow-tag-swatch<?= $swatch === $c_color ? ' active' : '' ?>" style="--itflow-tag-color: <?= $swatch ?>; --itflow-tag-text: <?= tagTextColor($swatch) ?>" data-category-swatch="<?= $swatch ?>" title="<?= $swatch ?>" aria-label="Color <?= $swatch ?>" aria-pressed="<?= $swatch === $c_color ? 'true' : 'false' ?>"><i class="fas fa-check" aria-hidden="true"></i></button>
                <?php } ?>
            </div>
            <div class="input-group input-group-sm w-auto" title="Any other color">
                <span class="input-group-text"><i class="fa fa-fw fa-paint-brush"></i></span>
                <input type="color" class="form-control form-control-color" value="<?= $c_color ?: '#6c757d' ?>" aria-label="Custom color" data-category-color-pick>
                <input type="text" class="form-control font-monospace" style="width: 6rem" maxlength="7" value="<?= $c_color ?>" placeholder="None" pattern="#?[0-9a-fA-F]{6}" aria-label="Color as hex" data-category-color-hex>
            </div>
        </div>
    </div>

    <div class="mb-1">
        <label class="form-label">Icon</label>
        <input type="hidden" name="icon" value="<?= $c_icon ?>" data-category-icon>
        <div class="input-group mb-2">
            <span class="input-group-text"><i class="fa fa-fw fa-search"></i></span>
            <input type="search" class="form-control" placeholder="Search icons - or type any Font Awesome icon's name" aria-label="Search icons" data-category-icon-search>
        </div>
        <div class="itflow-tag-icons border rounded p-1" role="group" aria-label="Icons" data-category-icons>
            <button type="button" class="btn btn-sm itflow-tag-icon<?= $c_icon === '' ? ' active' : '' ?>" data-category-icon-choice="" title="No icon - a dot in its color" aria-label="No icon" aria-pressed="<?= $c_icon === '' ? 'true' : 'false' ?>"><i class="fas fa-fw fa-ban" aria-hidden="true"></i></button>
            <?php foreach ($c_icons as $icon) { ?>
                <button type="button" class="btn btn-sm itflow-tag-icon<?= $icon === $c_icon ? ' active' : '' ?>" data-category-icon-choice="<?= $icon ?>" title="<?= $icon ?>" aria-label="<?= $icon ?>" aria-pressed="<?= $icon === $c_icon ? 'true' : 'false' ?>"><i class="fas fa-fw fa-<?= $icon ?>" aria-hidden="true"></i></button>
            <?php } ?>
            <button type="button" class="btn btn-sm btn-outline-secondary itflow-tag-icon-custom" data-category-icon-custom hidden><i class="fas fa-fw" aria-hidden="true"></i><span></span></button>
            <div class="small text-secondary p-2" data-category-icon-none hidden>No icon by that name here - type its whole name to use it</div>
        </div>
        <?php if ($kind === 'asset_status') { ?>
            <small class="text-secondary">Asset statuses show as a badge in their color - the icon shows in the status picker.</small>
        <?php } ?>
    </div>
</div>

<script src="/js/category_form.js"></script>
