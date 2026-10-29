<?php

/*
 * A tag's fields - for tag_add.php and tag_edit.php, which set $tag (a
 * tagRow(), or null when new), $type, and for a tag being edited $tag_used
 * and $tag_lines (how many records have it, and recurring invoice lines
 * count by it). Its kind can only change while neither is so. The preview,
 * colour swatches and icon search are js/tag_form.js. Posts to
 * admin/post.php (admin/post/tag.php).
 */

isset($mysqli) || die("Direct file access is not allowed");

$t = $tag ?? null;
$t_color = $t ? tagColor($t['tag_color']) : tagNextColor($type);
$t_icon = $t ? tagIcon($t['tag_icon']) : 'tag';
$t_kind_locked = $t && (($tag_used ?? 0) || ($tag_lines ?? 0));
$t_icons = TAG_ICONS;
if (!in_array($t_icon, $t_icons, true)) {
    // An icon picked by name before - offered first, so it stays chosen
    array_unshift($t_icons, $t_icon);
}

?>

<div data-tag-form>
    <div class="d-flex align-items-center gap-2 mb-3 p-2 rounded border bg-body-tertiary">
        <span class="small text-secondary">Looks like</span>
        <span class="itflow-tag" style="--itflow-tag-color: <?= $t_color ?>; --itflow-tag-text: <?= tagTextColor($t_color) ?>" data-tag-preview>
            <i class="fas fa-fw fa-<?= $t_icon ?>" aria-hidden="true" data-tag-preview-icon></i><span data-tag-preview-name><?= escapeHtml($t['tag_name'] ?? 'New tag') ?></span>
        </span>
        <?php if ($t) { ?>
            <span class="small text-secondary ms-auto text-end">
                <?= ($tag_used ?? 0) ? 'On ' . number_format($tag_used) . ' ' . ($tag_used === 1 ? strtolower(tagEntityByType($type)['title']) : tagEntityByType($type)['plural']) : 'Nothing has it yet' ?><?= ($tag_lines ?? 0) ? ' &middot; counted on ' . $tag_lines . ' recurring invoice ' . ($tag_lines === 1 ? 'line' : 'lines') : '' ?>
            </span>
        <?php } ?>
    </div>

    <div class="row">
        <div class="col-sm-7 mb-3">
            <label class="form-label">Name <strong class="text-danger">*</strong></label>
            <div class="input-group">
                <span class="input-group-text"><i class="fa fa-fw fa-tag"></i></span>
                <input type="text" class="form-control" name="name" maxlength="200" placeholder="VIP, Managed, Retiring soon..." value="<?= escapeHtml($t['tag_name'] ?? '') ?>" required<?= $t ? '' : ' autofocus' ?> data-tag-name>
            </div>
        </div>
        <div class="col-sm-5 mb-3">
            <label class="form-label">For <strong class="text-danger">*</strong></label>
            <div class="input-group">
                <span class="input-group-text"><i class="fa fa-fw fa-layer-group"></i></span>
                <select class="form-select" name="type"<?= $t_kind_locked ? ' disabled' : '' ?> required>
                    <?php foreach (tagKinds() as $kind_type => $kind_entity) {
                        $kind_config = tagEntity($kind_entity); ?>
                        <option value="<?= $kind_type ?>"<?= $kind_type === intval($type) ? ' selected' : '' ?>><?= $kind_config['title'] ?>s</option>
                    <?php } ?>
                </select>
            </div>
            <?php if ($t_kind_locked) { ?>
                <small class="text-secondary">In use, so it stays <?= tagKindPhrase($type) ?></small>
            <?php } ?>
        </div>
    </div>

    <div class="mb-3">
        <label class="form-label">Description</label>
        <div class="input-group">
            <span class="input-group-text"><i class="fa fa-fw fa-align-left"></i></span>
            <input type="text" class="form-control" name="description" maxlength="255" placeholder="What it means, and when to use it" value="<?= escapeHtml($t['tag_description'] ?? '') ?>">
        </div>
        <small class="text-secondary">Shown on the tag and in the tag picker</small>
    </div>

    <div class="mb-3">
        <label class="form-label">Color</label>
        <div class="d-flex flex-wrap align-items-center gap-2">
            <div class="d-flex flex-wrap gap-1" role="group" aria-label="Colors">
                <?php foreach (TAG_COLORS as $swatch) { ?>
                    <button type="button" class="itflow-tag-swatch<?= $swatch === $t_color ? ' active' : '' ?>" style="--itflow-tag-color: <?= $swatch ?>; --itflow-tag-text: <?= tagTextColor($swatch) ?>" data-tag-swatch="<?= $swatch ?>" title="<?= $swatch ?>" aria-label="Color <?= $swatch ?>" aria-pressed="<?= $swatch === $t_color ? 'true' : 'false' ?>"><i class="fas fa-check" aria-hidden="true"></i></button>
                <?php } ?>
            </div>
            <div class="input-group input-group-sm w-auto" title="Any other color">
                <span class="input-group-text"><i class="fa fa-fw fa-paint-brush"></i></span>
                <input type="color" class="form-control form-control-color" name="color" value="<?= $t_color ?>" aria-label="Custom color" data-tag-color>
                <input type="text" class="form-control font-monospace" style="width: 6rem" maxlength="7" value="<?= $t_color ?>" pattern="#?[0-9a-fA-F]{6}" aria-label="Color as hex" data-tag-color-hex>
            </div>
        </div>
    </div>

    <div class="mb-1">
        <label class="form-label">Icon</label>
        <input type="hidden" name="icon" value="<?= $t_icon ?>" data-tag-icon>
        <div class="input-group mb-2">
            <span class="input-group-text"><i class="fa fa-fw fa-search"></i></span>
            <input type="search" class="form-control" placeholder="Search icons - or type any Font Awesome icon's name" aria-label="Search icons" data-tag-icon-search>
        </div>
        <div class="itflow-tag-icons border rounded p-1" role="group" aria-label="Icons" data-tag-icons>
            <?php foreach ($t_icons as $icon) { ?>
                <button type="button" class="btn btn-sm itflow-tag-icon<?= $icon === $t_icon ? ' active' : '' ?>" data-tag-icon-choice="<?= $icon ?>" title="<?= $icon ?>" aria-label="<?= $icon ?>" aria-pressed="<?= $icon === $t_icon ? 'true' : 'false' ?>"><i class="fas fa-fw fa-<?= $icon ?>" aria-hidden="true"></i></button>
            <?php } ?>
            <button type="button" class="btn btn-sm btn-outline-secondary itflow-tag-icon-custom" data-tag-icon-custom hidden><i class="fas fa-fw" aria-hidden="true"></i><span></span></button>
            <div class="small text-secondary p-2" data-tag-icon-none hidden>No icon by that name here - type its whole name to use it</div>
        </div>
    </div>
</div>

<script src="/js/tag_form.js"></script>
