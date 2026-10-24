<?php

/*
 * A custom link's fields - for custom_link_add.php and custom_link_edit.php,
 * which set $custom_link (a customLinkRow(), or null when new) and
 * $default_location. The preview of it in its menu, the icon search and the
 * new-tab switch following the address are js/custom_link_form.js. The
 * server checks it all again (customLinkProblem()).
 */

isset($mysqli) || die("Direct file access is not allowed");

$l = $custom_link ?? null;
$l_locations = customLinkLocations();
// A menu ITFlow has - one saved with a menu it doesn't have is offered the agent menu
$l_stray = $l && !isset($l_locations[intval($l['custom_link_location'])]);
$l_location = $l ? ($l_stray ? 1 : intval($l['custom_link_location'])) : (isset($l_locations[$default_location ?? 0]) ? intval($default_location) : 1);
$l_icon = $l ? categoryIcon($l['custom_link_icon']) : '';
$l_icons = customLinkIconChoices($l_icon);
$l_new_tab = $l ? intval($l['custom_link_new_tab']) === 1 : true;
$l_name = (string) ($l['custom_link_name'] ?? '');
$l_description = (string) ($l['custom_link_description'] ?? '');

?>

<div data-custom-link-form>

    <div class="itflow-link-preview mb-3" data-custom-link-preview data-location="<?= $l_location ?>">
        <span class="small text-secondary">Looks like</span>
        <div class="itflow-link-preview-stage">
            <span class="itflow-link-preview-item" data-custom-link-preview-item title="<?= escapeHtml($l_description) ?>">
                <i class="fas fa-fw fa-<?= escapeHtml(customLinkIcon($l_icon)) ?>" data-custom-link-preview-icon aria-hidden="true"></i>
                <span data-custom-link-preview-name><?= escapeHtml($l_name !== '' ? $l_name : 'New link') ?></span>
                <i class="fas fa-external-link-alt itflow-link-preview-tab" data-custom-link-preview-tab aria-hidden="true"<?= $l_new_tab ? '' : ' hidden' ?>></i>
            </span>
        </div>
        <span class="small text-secondary" data-custom-link-preview-note></span>
    </div>

    <div class="mb-3">
        <label class="form-label">Name <strong class="text-danger">*</strong></label>
        <div class="input-group">
            <span class="input-group-text"><i class="fa fa-fw fa-tag"></i></span>
            <input type="text" class="form-control" name="name" maxlength="200" placeholder="What the menu says - RMM, Documentation, Book a call" value="<?= escapeHtml($l_name) ?>" required<?= $l ? '' : ' autofocus' ?> data-custom-link-name>
        </div>
    </div>

    <div class="mb-3">
        <label class="form-label">Goes to <strong class="text-danger">*</strong></label>
        <div class="input-group has-validation">
            <span class="input-group-text"><i class="fa fa-fw fa-link"></i></span>
            <input type="text" class="form-control" name="uri" maxlength="500" placeholder="https://..., /agent/page.php, mailto: or tel:" value="<?= escapeHtml($l['custom_link_uri'] ?? '') ?>" required data-custom-link-uri>
            <div class="invalid-feedback" data-custom-link-uri-problem></div>
        </div>
        <div class="form-check form-switch mt-2">
            <input class="form-check-input" type="checkbox" role="switch" name="new_tab" value="1" id="customLinkNewTab"<?= $l_new_tab ? ' checked' : '' ?> data-custom-link-new-tab<?= $l ? ' data-touched="1"' : '' ?>>
            <label class="form-check-label" for="customLinkNewTab">Open it in a new tab</label>
        </div>
    </div>

    <div class="mb-3">
        <label class="form-label">Shows in <strong class="text-danger">*</strong></label>
        <div class="input-group">
            <span class="input-group-text"><i class="fa fa-fw fa-bars"></i></span>
            <select class="form-select" name="location" required data-custom-link-location>
                <?php foreach ($l_locations as $location => $location_config) { ?>
                    <option value="<?= $location ?>"<?= $location === $l_location ? ' selected' : '' ?> data-about="<?= escapeHtml($location_config['about']) ?>"><?= escapeHtml($location_config['label']) ?></option>
                <?php } ?>
            </select>
        </div>
        <small class="text-secondary" data-custom-link-location-about><?= escapeHtml($l_locations[$l_location]['about']) ?></small>
        <?php if ($l_stray) { ?>
            <small class="text-warning d-block">It was in a menu ITFlow no longer has - pick where it should show.</small>
        <?php } ?>
    </div>

    <div class="mb-3">
        <label class="form-label">Description</label>
        <div class="input-group">
            <span class="input-group-text"><i class="fa fa-fw fa-align-left"></i></span>
            <input type="text" class="form-control" name="description" maxlength="500" placeholder="Shown when the link is hovered - what it's for" value="<?= escapeHtml($l_description) ?>" data-custom-link-description>
        </div>
    </div>

    <div class="mb-1">
        <label class="form-label">Icon</label>
        <input type="hidden" name="icon" value="<?= escapeHtml($l_icon) ?>" data-custom-link-icon>
        <div class="input-group mb-2">
            <span class="input-group-text"><i class="fa fa-fw fa-search"></i></span>
            <input type="search" class="form-control" placeholder="Search icons - or type any Font Awesome icon's name" aria-label="Search icons" data-custom-link-icon-search>
        </div>
        <div class="itflow-tag-icons border rounded p-1" role="group" aria-label="Icons">
            <button type="button" class="btn btn-sm itflow-tag-icon<?= $l_icon === '' ? ' active' : '' ?>" data-custom-link-icon-choice="" title="None - the link arrow" aria-label="No icon of its own" aria-pressed="<?= $l_icon === '' ? 'true' : 'false' ?>"><i class="fas fa-fw fa-ban" aria-hidden="true"></i></button>
            <?php foreach ($l_icons as $icon) { ?>
                <button type="button" class="btn btn-sm itflow-tag-icon<?= $icon === $l_icon ? ' active' : '' ?>" data-custom-link-icon-choice="<?= escapeHtml($icon) ?>" title="<?= escapeHtml($icon) ?>" aria-label="<?= escapeHtml($icon) ?>" aria-pressed="<?= $icon === $l_icon ? 'true' : 'false' ?>"><i class="fas fa-fw fa-<?= escapeHtml($icon) ?>" aria-hidden="true"></i></button>
            <?php } ?>
            <button type="button" class="btn btn-sm btn-outline-secondary itflow-tag-icon-custom" data-custom-link-icon-custom hidden><i class="fas fa-fw" aria-hidden="true"></i><span></span></button>
            <div class="small text-secondary p-2" data-custom-link-icon-none hidden>No icon by that name here - type its whole name to use it</div>
        </div>
    </div>
</div>

<script src="/js/custom_link_form.js"></script>
