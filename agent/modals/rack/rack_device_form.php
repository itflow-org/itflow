<?php
/*
 * Rack item form - shared by rack_device_add.php and rack_device_edit.php,
 * which load the rack ($rack_id, $rack_units, $client_id) and the item's
 * current values ($unit_*, empty for a new one) before including this.
 * Should not be accessed directly.
 *
 * The live position check below is advisory - agent/post/rack.php runs the
 * same bounds, overlap and asset checks again on submit.
 */

isset($mysqli) || die("Direct file access is not allowed");

// Everything else in the rack, for the live position check
$rack_other_units = [];
$sql = mysqli_query($mysqli, "SELECT unit_id, unit_device, unit_type, unit_start_number, unit_end_number, unit_face, unit_full_depth, asset_name
    FROM rack_units
    LEFT JOIN assets ON asset_id = unit_asset_id
    WHERE unit_rack_id = $rack_id AND unit_id != $unit_id
    ORDER BY unit_start_number");
while ($row = mysqli_fetch_assoc($sql)) {
    $rack_other_units[] = [
        'start' => intval($row['unit_start_number']),
        'end' => intval($row['unit_end_number']),
        'face' => $row['unit_face'] === 'rear' ? 'rear' : 'front',
        'full' => intval($row['unit_full_depth']) === 1,
        'name' => rackUnitName($row['asset_name'], $row['unit_device'], $row['unit_type']),
    ];
}

// This client's assets that aren't racked anywhere yet (plus the one already
// on this item). An archived asset only stays listed if it's this item's.
$asset_options = [];
$sql = mysqli_query($mysqli, "SELECT asset_id, asset_name, asset_type, asset_make, asset_model
    FROM assets
    WHERE asset_client_id = $client_id
    AND (asset_id = $unit_asset_id OR (
        asset_archived_at IS NULL
        AND NOT EXISTS (SELECT 1 FROM rack_units WHERE unit_asset_id = asset_id)
    ))
    ORDER BY FIELD(asset_type, 'Server', 'Switch', 'Firewall/Router', 'Access Point') = 0, asset_type, asset_name");
while ($row = mysqli_fetch_assoc($sql)) {
    $asset_options[] = $row;
}

$unit_is_asset = $unit_asset_id > 0 || ($unit_id === 0 && $unit_device === '');
$item_types = rackItemTypes();

?>

<div class="mb-3">
    <div class="btn-group w-100" role="group" aria-label="What is going in the rack">
        <input type="radio" class="btn-check" name="item_kind" id="rackItemKindAsset" value="asset" <?= $unit_is_asset ? 'checked' : '' ?>>
        <label class="btn btn-outline-secondary" for="rackItemKindAsset"><i class="fas fa-fw fa-desktop me-2"></i>An asset</label>
        <input type="radio" class="btn-check" name="item_kind" id="rackItemKindOther" value="other" <?= $unit_is_asset ? '' : 'checked' ?>>
        <label class="btn btn-outline-secondary" for="rackItemKindOther"><i class="fas fa-fw fa-ethernet me-2"></i>Something else</label>
    </div>
</div>

<div class="mb-3" data-rack-kind="asset">
    <label class="form-label">Asset <strong class="text-danger">*</strong></label>
    <select class="form-select select2" name="asset" id="rackItemAsset">
        <option value="">- Select an asset -</option>
        <?php foreach ($asset_options as $asset_option) {
            $asset_option_id = intval($asset_option['asset_id']);
            ?>
            <option value="<?= $asset_option_id ?>" data-asset-type="<?= escapeHtml($asset_option['asset_type']) ?>" <?php if ($asset_option_id === $unit_asset_id) { echo "selected"; } ?><?= selectOptionAsset($asset_option['asset_type'], trim($asset_option['asset_make'] . ' ' . $asset_option['asset_model'])) ?>><?= escapeHtml($asset_option['asset_name']) ?></option>
        <?php } ?>
    </select>
    <?php if (empty($asset_options)) { ?>
        <div class="form-text">Every asset this client has is already racked.</div>
    <?php } ?>
</div>

<div class="mb-3" data-rack-kind="other">
    <label class="form-label">What is it</label>
    <select class="form-select select2" name="type" id="rackItemType">
        <?php foreach ($item_types as $item_type => $item_type_style) { ?>
            <option value="<?= escapeHtml($item_type) ?>" <?php if ($unit_type === $item_type) { echo "selected"; } ?><?= selectOptionData(['icon' => 'fas ' . $item_type_style['icon']]) ?>><?= escapeHtml($item_type) ?></option>
        <?php } ?>
    </select>
</div>

<div class="mb-3">
    <label class="form-label" for="rackItemName">
        <span data-rack-kind="asset">Label <span class="text-secondary fw-normal">(optional, shown under the asset name)</span></span>
        <span data-rack-kind="other">Name <strong class="text-danger">*</strong></span>
    </label>
    <input type="text" class="form-control" name="name" id="rackItemName" maxlength="200" value="<?= $unit_device ?>" placeholder="24-port Cat6 patch panel">
</div>

<div class="row g-3 mb-1">
    <div class="col-sm-4">
        <label class="form-label" for="rackItemStart">Lowest U <strong class="text-danger">*</strong></label>
        <div class="input-group">
            <span class="input-group-text">U</span>
            <input type="number" class="form-control" name="unit_start" id="rackItemStart" min="1" max="<?= $rack_units ?>" value="<?= $unit_start ?: '' ?>" required>
        </div>
    </div>
    <div class="col-sm-4">
        <label class="form-label" for="rackItemHeight">Height <strong class="text-danger">*</strong></label>
        <div class="input-group">
            <input type="number" class="form-control" name="unit_height" id="rackItemHeight" min="1" max="<?= $rack_units ?>" value="<?= $unit_height ?>" required>
            <span class="input-group-text">U</span>
        </div>
    </div>
    <div class="col-sm-4">
        <label class="form-label">Mounted on</label>
        <div class="btn-group w-100" role="group">
            <input type="radio" class="btn-check" name="face" id="rackItemFaceFront" value="front" <?= $unit_face === 'rear' ? '' : 'checked' ?>>
            <label class="btn btn-outline-secondary" for="rackItemFaceFront">Front</label>
            <input type="radio" class="btn-check" name="face" id="rackItemFaceRear" value="rear" <?= $unit_face === 'rear' ? 'checked' : '' ?>>
            <label class="btn btn-outline-secondary" for="rackItemFaceRear">Rear</label>
        </div>
    </div>
</div>

<div class="form-check form-switch mt-2">
    <input class="form-check-input" type="checkbox" name="full_depth" value="1" id="rackItemFullDepth" <?= $unit_full_depth ? 'checked' : '' ?>>
    <label class="form-check-label" for="rackItemFullDepth">Full depth <span class="text-secondary">- fills the front and the rear</span></label>
</div>

<div class="itflow-rack-fit small mt-2 mb-3" id="rackItemFit" aria-live="polite"></div>

<div class="row g-3">
    <div class="col-sm-5">
        <label class="form-label">Power draw</label>
        <div class="input-group">
            <span class="input-group-text"><i class="fa fa-fw fa-bolt"></i></span>
            <input type="number" class="form-control" name="power_watts" min="0" max="100000" placeholder="350" value="<?= $unit_power_watts ?: '' ?>">
            <span class="input-group-text">W</span>
        </div>
    </div>
    <div class="col-sm-7">
        <label class="form-label">Notes</label>
        <input type="text" class="form-control" name="notes" maxlength="255" placeholder="Rails in the box, PSU A on PDU-A port 3" value="<?= $unit_notes ?>">
    </div>
</div>

<script>
(function () {
    var others = <?= json_encode($rack_other_units, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    var rackUnits = <?= intval($rack_units) ?>;

    var form = document.getElementById('rackItemFit').closest('form');
    var start = form.querySelector('#rackItemStart');
    var height = form.querySelector('#rackItemHeight');
    var fullDepth = form.querySelector('#rackItemFullDepth');
    var fit = form.querySelector('#rackItemFit');
    var nameInput = form.querySelector('#rackItemName');
    var assetSelect = form.querySelector('#rackItemAsset');
    var typeSelect = form.querySelector('#rackItemType');

    // Things that sit at the front and leave the back free, as a starting point
    var halfDepthTypes = ['Patch Panel', 'Cable Management', 'Blanking Panel'];
    var halfDepthAssetTypes = ['Access Point', 'Firewall/Router'];

    function kind() {
        return form.querySelector('input[name="item_kind"]:checked').value;
    }

    function face() {
        return form.querySelector('input[name="face"]:checked').value;
    }

    function position(s, e) {
        return s === e ? 'U' + s : 'U' + s + '–U' + e;
    }

    function setText(el, icon, cls, text) {
        el.className = 'itflow-rack-fit small mt-2 mb-3 ' + cls;
        el.textContent = '';
        var i = document.createElement('i');
        i.className = 'fas fa-fw me-1 ' + icon;
        el.appendChild(i);
        el.appendChild(document.createTextNode(text));
    }

    function check() {
        var s = parseInt(start.value, 10);
        var h = parseInt(height.value, 10);
        if (!s || !h) {
            fit.textContent = '';
            fit.className = 'itflow-rack-fit small mt-2 mb-3';
            return;
        }
        var e = s + h - 1;
        if (s < 1 || e > rackUnits) {
            setText(fit, 'fa-times-circle', 'text-danger', position(s, e) + " doesn't fit - this rack runs from U1 to U" + rackUnits);
            return;
        }
        var f = face();
        var full = fullDepth.checked;
        var clash = others.find(function (o) {
            return o.start <= e && o.end >= s && (o.full || full || o.face === f);
        });
        if (clash) {
            setText(fit, 'fa-times-circle', 'text-danger', position(s, e) + ' clashes with ' + clash.name + ' (' + position(clash.start, clash.end) + ', ' + (clash.full ? 'full depth' : clash.face) + ')');
        } else {
            setText(fit, 'fa-check-circle', 'text-success', position(s, e) + ' is free on the ' + (full ? 'front and rear' : f));
        }
    }

    function applyKind() {
        var k = kind();
        form.querySelectorAll('[data-rack-kind]').forEach(function (el) {
            el.classList.toggle('d-none', el.getAttribute('data-rack-kind') !== k);
        });
        assetSelect.required = k === 'asset';
        nameInput.required = k === 'other';
        nameInput.placeholder = k === 'asset' ? 'Optional' : '24-port Cat6 patch panel';
    }

    form.querySelectorAll('input[name="item_kind"]').forEach(function (r) {
        r.addEventListener('change', applyKind);
    });

    [start, height, fullDepth].forEach(function (el) {
        el.addEventListener('input', check);
        el.addEventListener('change', check);
    });
    form.querySelectorAll('input[name="face"]').forEach(function (r) {
        r.addEventListener('change', check);
    });

    <?php if ($unit_id === 0) { ?>
    // A new item: suggest the depth from what it is. Editing keeps what was saved.
    typeSelect.addEventListener('change', function () {
        fullDepth.checked = halfDepthTypes.indexOf(typeSelect.value) === -1;
        check();
    });
    assetSelect.addEventListener('change', function () {
        var option = assetSelect.options[assetSelect.selectedIndex];
        var assetType = option ? option.getAttribute('data-asset-type') : '';
        if (assetType) {
            fullDepth.checked = halfDepthAssetTypes.indexOf(assetType) === -1;
            height.value = assetType === 'Server' && !height.dataset.touched ? 2 : height.value;
            check();
        }
    });
    height.addEventListener('input', function () {
        height.dataset.touched = '1';
    });
    <?php } ?>

    // On submit, the field for the kind not chosen must not count
    form.addEventListener('submit', function () {
        if (kind() === 'other') {
            assetSelect.value = '';
        }
    });

    applyKind();
    check();
})();
</script>
