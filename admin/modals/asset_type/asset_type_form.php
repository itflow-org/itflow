<?php

/*
 * An asset type's fields - for asset_type_add.php and asset_type_edit.php,
 * which set $t: the type as the form starts - name, icon (its bare Font
 * Awesome name), tone, group, os, fields, specs, rack, description, base and
 * details (its own: id, label, input, unit, options, flag, unit_only and
 * values - how many assets have one) - and $t_new (a new type). A new one
 * starts from one of ITFlow's: picking another fills in its group, colour,
 * fields, details and rack, all of which can then be changed. Tabs like the
 * asset form: Type, Fields, Details. The preview, colours, icon search, the
 * starting point and its own details are js/asset_type_form.js; the server
 * checks it all again (assetTypeFromInput(), assetTypeProblem()).
 */

isset($mysqli) || die("Direct file access is not allowed");

// What each of ITFlow's types starts a new one with, for the script
$t_starts = [];
foreach (ASSET_TYPES as $start_name => $start) {
    $start_info = assetTypes()[$start_name];
    $t_starts[$start_name] = ['icon' => substr($start['icon'], 3), 'tone' => $start['tone'], 'group' => $start['group'], 'os' => $start['os'],
        'fields' => $start['fields'], 'specs' => $start['specs'], 'rack' => $start_info['rack']];
}
$t_icons = ASSET_TYPE_ICONS;
if ($t['icon'] !== '' && !in_array($t['icon'], $t_icons, true)) {
    // An icon picked by name before - offered first, so it stays chosen
    array_unshift($t_icons, $t['icon']);
}

// One of its own details as a row - $key is its place in the form, __KEY__ in the template
$t_detail_row = function ($key, array $d) {
    $existing = intval($d['id'] ?? 0) > 0;
    $input = $d['input'] ?? 'text';
    ob_start(); ?>
    <div class="border rounded p-2 mb-2" data-asset-type-detail data-values="<?= intval($d['values'] ?? 0) ?>">
        <input type="hidden" name="detail_id[<?= $key ?>]" value="<?= intval($d['id'] ?? 0) ?>">
        <div class="row g-2 align-items-center">
            <div class="col-sm-5">
                <div class="input-group input-group-sm">
                    <span class="input-group-text"><i class="fas fa-fw fa-tag"></i></span>
                    <input type="text" class="form-control" name="detail_label[<?= $key ?>]" maxlength="100" placeholder="Doors, VIN, Inspected" value="<?= escapeHtml($d['label'] ?? '') ?>" aria-label="Its name" data-asset-type-detail-label>
                </div>
            </div>
            <div class="col-sm-3">
                <?php if ($existing) { ?>
                    <input type="hidden" name="detail_input[<?= $key ?>]" value="<?= escapeHtml($input) ?>" data-asset-type-detail-input>
                    <div class="small text-secondary" title="A detail keeps its kind - add another for a different one"><i class="fas fa-fw <?= ASSET_TYPE_DETAIL_INPUTS[$input]['icon'] ?> me-1"></i><?= ASSET_TYPE_DETAIL_INPUTS[$input]['label'] ?></div>
                <?php } else { ?>
                    <select class="form-select form-select-sm select2" name="detail_input[<?= $key ?>]" aria-label="What it holds" data-asset-type-detail-input>
                        <?php foreach (ASSET_TYPE_DETAIL_INPUTS as $input_key => $input_kind) { ?>
                            <option value="<?= $input_key ?>"<?= $input_key === $input ? ' selected' : '' ?><?= selectOptionData(['icon' => 'fas ' . $input_kind['icon']]) ?>><?= $input_kind['label'] ?></option>
                        <?php } ?>
                    </select>
                <?php } ?>
            </div>
            <div class="col-sm-3">
                <div class="input-group input-group-sm" data-asset-type-detail-for="number">
                    <span class="input-group-text">Unit</span>
                    <input type="text" class="form-control" name="detail_unit[<?= $key ?>]" maxlength="20" placeholder="kg, m, ft" value="<?= escapeHtml($d['unit'] ?? '') ?>" aria-label="Its unit">
                </div>
                <div class="form-check form-switch small mb-0" data-asset-type-detail-for="date" title="On its page as the date nears, like a warranty - and a notification 45, 7 and 1 days before">
                    <input class="form-check-input" type="checkbox" role="switch" name="detail_flag[<?= $key ?>]" value="1" id="assetTypeDetailFlag<?= $key ?>"<?= !empty($d['flag']) ? ' checked' : '' ?>>
                    <label class="form-check-label" for="assetTypeDetailFlag<?= $key ?>">Flag as it nears</label>
                </div>
            </div>
            <div class="col-sm-1 text-end">
                <button type="button" class="btn btn-sm btn-outline-secondary" title="Remove this detail" aria-label="Remove this detail" data-asset-type-detail-remove><i class="fas fa-times"></i></button>
            </div>
            <div class="col-12" data-asset-type-detail-for="select">
                <div class="input-group input-group-sm">
                    <span class="input-group-text"><i class="fas fa-fw fa-list-ul"></i></span>
                    <input type="text" class="form-control" name="detail_options[<?= $key ?>]" maxlength="2000" placeholder="The choices, with commas between - Card, Fob, Keypad" value="<?= escapeHtml(implode(', ', $d['options'] ?? [])) ?>" aria-label="Its choices">
                </div>
            </div>
            <div class="col-12">
                <div class="form-check small mb-0">
                    <input class="form-check-input" type="checkbox" name="detail_unit_only[<?= $key ?>]" value="1" id="assetTypeDetailUnit<?= $key ?>"<?= !empty($d['unit_only']) ? ' checked' : '' ?>>
                    <label class="form-check-label" for="assetTypeDetailUnit<?= $key ?>">Only this one has it - not copied when the asset is copied, like a serial or plate</label>
                </div>
                <?php if (intval($d['values'] ?? 0) > 0) { ?>
                    <div class="small text-secondary"><i class="fas fa-fw fa-info-circle me-1"></i>Filled in on <?= number_format(intval($d['values'])) ?> <?= intval($d['values']) === 1 ? 'asset' : 'assets' ?></div>
                <?php } ?>
            </div>
        </div>
    </div>
    <?php return ob_get_clean();
};

?>

<div data-asset-type-form data-asset-type-starts="<?= escapeHtml(json_encode($t_starts)) ?>">

    <ul class="nav nav-pills nav-fill flex-nowrap overflow-auto mb-3" role="tablist">
        <li class="nav-item">
            <a class="nav-link active" data-bs-toggle="pill" href="#assetTypeFormType"><i class="fas fa-fw fa-layer-group me-2 d-none d-lg-inline"></i>Type</a>
        </li>
        <li class="nav-item">
            <a class="nav-link" data-bs-toggle="pill" href="#assetTypeFormFields"><i class="fas fa-fw fa-list me-2 d-none d-lg-inline"></i>Fields</a>
        </li>
        <li class="nav-item">
            <a class="nav-link" data-bs-toggle="pill" href="#assetTypeFormDetails"><i class="fas fa-fw fa-microchip me-2 d-none d-lg-inline"></i>Details</a>
        </li>
    </ul>

    <div class="tab-content">

        <div class="tab-pane fade show active" id="assetTypeFormType">
            <div class="d-flex flex-wrap align-items-center gap-2 mb-3 p-2 border rounded bg-body-tertiary">
                <span class="small text-secondary">Looks like</span>
                <span class="itflow-asset-icon itflow-asset-icon-sm itflow-asset-tone-<?= escapeHtml($t['tone']) ?>" data-asset-type-preview-icon aria-hidden="true"><i class="fas fa-fw fa-<?= escapeHtml($t['icon'] !== '' ? $t['icon'] : 'tag') ?>"></i></span>
                <span class="fw-semibold" data-asset-type-preview-name><?= escapeHtml($t['name'] !== '' ? $t['name'] : 'New type') ?></span>
            </div>

            <?php if ($t_new) { ?>
                <div class="mb-3">
                    <label class="form-label">Start from</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-fw fa-clone"></i></span>
                        <select class="form-select select2" name="base" data-asset-type-start>
                            <?php foreach (ASSET_TYPES as $start_name => $start) { ?>
                                <option<?= $start_name === $t['base'] ? ' selected' : '' ?><?= selectOptionData(['icon' => 'fas ' . $start['icon'], 'description' => ASSET_TYPE_GROUPS[$start['group']]['label']]) ?>><?= escapeHtml($start_name) ?></option>
                            <?php } ?>
                        </select>
                    </div>
                    <div class="form-text">Fills in its group, color, icon, fields, details and rack from that type - change any of them after. Its examples on the asset form come from it too.</div>
                </div>
            <?php } else { ?>
                <input type="hidden" name="base" value="<?= escapeHtml($t['base']) ?>">
            <?php } ?>

            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label class="form-label">Name <strong class="text-danger">*</strong></label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-fw fa-tag"></i></span>
                        <input type="text" class="form-control" name="name" maxlength="200" placeholder="Badge Reader, Vehicle, Hypervisor" value="<?= escapeHtml($t['name']) ?>" required<?= $t_new ? ' autofocus' : '' ?> data-asset-type-name>
                    </div>
                    <?php if (!$t_new) { ?>
                        <div class="form-text" data-asset-type-rename-note hidden><i class="fas fa-fw fa-info-circle me-1"></i>Its assets, products and recurring invoice lines take the new name too.</div>
                    <?php } ?>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Group <strong class="text-danger">*</strong></label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-fw fa-folder"></i></span>
                        <select class="form-select select2" name="group" required data-asset-type-group>
                            <?php foreach (ASSET_TYPE_GROUPS as $group_key => $group) { ?>
                                <option value="<?= $group_key ?>"<?= $group_key === $t['group'] ? ' selected' : '' ?><?= selectOptionData(['icon' => 'fas ' . $group['icon']]) ?>><?= escapeHtml($group['label']) ?></option>
                            <?php } ?>
                        </select>
                    </div>
                    <div class="form-text">Its tab on the assets list, and how the client overview, the portal and client exports count it.</div>
                </div>
                <div class="col-12">
                    <label class="form-label">Description</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-fw fa-align-left"></i></span>
                        <input type="text" class="form-control" name="description" maxlength="255" placeholder="What it is, and when to use it" value="<?= escapeHtml($t['description']) ?>">
                    </div>
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label">Color</label>
                <input type="hidden" name="tone" value="<?= escapeHtml($t['tone']) ?>" data-asset-type-tone>
                <div class="d-flex flex-wrap gap-1" role="group" aria-label="Colors">
                    <?php foreach (ASSET_TYPE_TONES as $tone => $tone_label) { ?>
                        <button type="button" class="itflow-tag-swatch itflow-asset-tone-<?= $tone ?><?= $tone === $t['tone'] ? ' active' : '' ?>" style="--itflow-tag-color: var(--itflow-asset-tone)" data-asset-type-swatch="<?= $tone ?>" title="<?= $tone_label ?>" aria-label="<?= $tone_label ?>" aria-pressed="<?= $tone === $t['tone'] ? 'true' : 'false' ?>"><i class="fas fa-check" aria-hidden="true"></i></button>
                    <?php } ?>
                </div>
            </div>

            <div class="mb-1">
                <label class="form-label">Icon</label>
                <input type="hidden" name="icon" value="<?= escapeHtml($t['icon']) ?>" data-asset-type-icon>
                <div class="input-group mb-2">
                    <span class="input-group-text"><i class="fas fa-fw fa-search"></i></span>
                    <input type="search" class="form-control" placeholder="Search icons - or type any Font Awesome icon's name" aria-label="Search icons" data-asset-type-icon-search>
                </div>
                <div class="itflow-tag-icons border rounded p-1" role="group" aria-label="Icons">
                    <?php foreach ($t_icons as $icon) { ?>
                        <button type="button" class="btn btn-sm itflow-tag-icon<?= $icon === $t['icon'] ? ' active' : '' ?>" data-asset-type-icon-choice="<?= escapeHtml($icon) ?>" title="<?= escapeHtml($icon) ?>" aria-label="<?= escapeHtml($icon) ?>" aria-pressed="<?= $icon === $t['icon'] ? 'true' : 'false' ?>"><i class="fas fa-fw fa-<?= escapeHtml($icon) ?>" aria-hidden="true"></i></button>
                    <?php } ?>
                    <button type="button" class="btn btn-sm btn-outline-secondary itflow-tag-icon-custom" data-asset-type-icon-custom hidden><i class="fas fa-fw" aria-hidden="true"></i><span></span></button>
                    <div class="small text-secondary p-2" style="grid-column: 1 / -1" data-asset-type-icon-none hidden>No icon by that name here - type its whole name to use it</div>
                </div>
            </div>
        </div>

        <div class="tab-pane fade" id="assetTypeFormFields">
            <p class="small text-secondary">Name, type, status, description, tags, make, model, location, photo and notes are on every asset. Which of these does it ask for? One it doesn't still shows on an asset that has something in it.</p>
            <div class="row g-2 mb-3">
                <?php foreach (ASSET_TYPE_FIELD_LABELS as $field => $field_label) { ?>
                    <div class="col-md-6">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="fields[]" value="<?= $field ?>" id="assetTypeField_<?= $field ?>"<?= in_array($field, $t['fields'], true) ? ' checked' : '' ?> data-asset-type-field>
                            <label class="form-check-label" for="assetTypeField_<?= $field ?>">
                                <i class="fas fa-fw <?= $field_label['icon'] ?> text-secondary me-1"></i><?= $field_label['label'] ?>
                                <span class="d-block small text-secondary"><?= $field_label['about'] ?></span>
                            </label>
                        </div>
                    </div>
                <?php } ?>
            </div>

            <div class="mb-3" data-asset-type-os-choice>
                <label class="form-label">Its operating system field is called</label>
                <div class="d-flex flex-wrap gap-3">
                    <?php foreach (['os' => 'Operating system', 'firmware' => 'Firmware'] as $os_key => $os_label) { ?>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="os" value="<?= $os_key ?>" id="assetTypeOs_<?= $os_key ?>"<?= $t['os'] === $os_key ? ' checked' : '' ?> data-asset-type-os>
                            <label class="form-check-label" for="assetTypeOs_<?= $os_key ?>"><?= $os_label ?></label>
                        </div>
                    <?php } ?>
                </div>
            </div>

            <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" role="switch" name="rack" value="1" id="assetTypeRack"<?= $t['rack'] ? ' checked' : '' ?> data-asset-type-rack>
                <label class="form-check-label" for="assetTypeRack">Goes in a rack</label>
                <div class="form-text mt-0">Offered first when racking an asset, and on a rack page as kit not in one yet.</div>
            </div>
        </div>

        <div class="tab-pane fade" id="assetTypeFormDetails">
            <div class="itflow-form-section">ITFlow's details</div>
            <p class="small text-secondary">The facts only some kinds of thing have. A count of ports or SFP ports adds the interfaces when an asset is saved; Host picks the server a virtual one runs on.</p>
            <div class="row g-1 mb-3">
                <?php foreach (ASSET_SPECS as $spec_key => $spec) { ?>
                    <div class="col-md-4 col-sm-6">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="specs[]" value="<?= $spec_key ?>" id="assetTypeSpec_<?= $spec_key ?>"<?= in_array($spec_key, $t['specs'], true) ? ' checked' : '' ?> data-asset-type-spec>
                            <label class="form-check-label small" for="assetTypeSpec_<?= $spec_key ?>"><i class="fas fa-fw <?= $spec['icon'] ?> text-secondary me-1"></i><?= escapeHtml($spec['label']) ?><?= isset($spec['unit']) ? ' <span class="text-secondary">(' . escapeHtml($spec['unit']) . ')</span>' : '' ?></label>
                        </div>
                    </div>
                <?php } ?>
            </div>

            <div class="itflow-form-section">Its own details</div>
            <p class="small text-secondary">Anything else to keep on each one - a VIN, a door count, when it was last inspected. They come after ITFlow's on its form and page, and in exports as their own columns. Removing one deletes what was kept in it.</p>
            <div data-asset-type-details>
                <?php foreach ($t['details'] as $detail_key => $detail) {
                    echo $t_detail_row($detail_key, $detail);
                } ?>
            </div>
            <template data-asset-type-detail-template><?= $t_detail_row('__KEY__', []) ?></template>
            <button type="button" class="btn btn-outline-secondary btn-sm" data-asset-type-detail-add data-max="<?= ASSET_TYPE_DETAILS_MAX ?>"><i class="fas fa-plus me-1"></i>Add a Detail</button>
        </div>

    </div>
</div>

<script src="<?= staticFileUrl('/js/asset_type_form.js') ?>"></script>
