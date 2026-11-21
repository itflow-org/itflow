<?php

/*
 * Your own asset types - making, changing, switching and deleting them, for
 * Admin > Asset Types (admin/asset_types.php; the handlers are
 * admin/post/asset_type.php). How every type is read and drawn is
 * functions/asset_type.php.
 *
 * One of yours has a name (what assets.asset_type holds for it), an icon, a
 * colour, a group (its tab), the standard fields it asks for, ITFlow's
 * details it has, its own (asset_type_details, kept in asset_specs as
 * custom_<id>) and whether it goes in a rack. Renaming one renames it on its
 * assets, products and the recurring invoice lines that count them; deleting
 * one moves those to another type first. One of ITFlow's can only be switched
 * off and on again - a row with asset_type_builtin set says which.
 *
 * Everything here takes raw values and does its own escaping.
 */

/** The standard fields (ASSET_FIELDS), as Admin > Asset Types asks which a type has. */
const ASSET_TYPE_FIELD_LABELS = [
    'contact' => ['label' => 'Assigned to', 'icon' => 'fa-user', 'about' => 'The contact who has it'],
    'os' => ['label' => 'Operating system or firmware', 'icon' => 'fa-compact-disc', 'about' => 'What it runs'],
    'network' => ['label' => 'Network', 'icon' => 'fa-network-wired', 'about' => 'Its network, IP and MAC addresses'],
    'nat' => ['label' => 'NAT address', 'icon' => 'fa-exchange-alt', 'about' => 'The address it is reached at from outside'],
    'links' => ['label' => 'Links', 'icon' => 'fa-link', 'about' => 'Where it is managed from - a web page, SSH'],
    'serial' => ['label' => 'Serial number', 'icon' => 'fa-barcode', 'about' => 'Checked against the other assets'],
    'purchase' => ['label' => 'Purchase', 'icon' => 'fa-receipt', 'about' => 'Vendor, order reference and date'],
    'warranty' => ['label' => 'Warranty', 'icon' => 'fa-shield-alt', 'about' => 'When it expires - flagged as it nears'],
];

/** How many details of its own a type can have. */
const ASSET_TYPE_DETAILS_MAX = 30;

/** One of your types by id - its asset_types row, with details (its asset_type_details rows, in order) - or null. Raw. */
function assetTypeRow($asset_type_id) {
    global $mysqli;
    $asset_type_id = intval($asset_type_id);
    if (!$asset_type_id || !assetTypesReady()) {
        return null;
    }
    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT asset_type_id, asset_type_name, asset_type_active, asset_type_icon, asset_type_tone, asset_type_group, asset_type_base,
            asset_type_os, asset_type_fields, asset_type_specs, asset_type_rack, asset_type_description
        FROM asset_types WHERE asset_type_id = $asset_type_id AND asset_type_builtin = 0 LIMIT 1"));
    if (!$row || assetTypeBuiltInName($row['asset_type_name']) !== '') {
        return null;
    }
    $row['details'] = [];
    $sql = mysqli_query($mysqli, "SELECT asset_type_detail_id, asset_type_detail_label, asset_type_detail_input, asset_type_detail_unit, asset_type_detail_options,
            asset_type_detail_flag, asset_type_detail_unit_only
        FROM asset_type_details WHERE asset_type_detail_asset_type_id = $asset_type_id ORDER BY asset_type_detail_order ASC, asset_type_detail_id ASC");
    while ($detail = mysqli_fetch_assoc($sql)) {
        $row['details'][] = $detail;
    }
    return $row;
}

/**
 * What the form gave, as a type of yours is kept - name, icon (fa-...), tone,
 * group, os, fields, specs (ITFlow's details), rack, description, base and
 * details: its own, in order - id (0 for a new one), label, input, unit,
 * options, flag, unit_only. A detail already there keeps its kind; a new row
 * left without a name is skipped. Checked by assetTypeProblem().
 */
function assetTypeFromInput(array $in, $row = null) {
    $text = function ($value, $max) {
        return mb_substr(trim(preg_replace('/[\x00-\x1F\x7F]+/u', ' ', strip_tags((string) $value))), 0, $max);
    };
    $existing = [];
    foreach ($row['details'] ?? [] as $detail) {
        $existing[intval($detail['asset_type_detail_id'])] = $detail;
    }
    $details = [];
    foreach ((array) ($in['detail_label'] ?? []) as $key => $label) {
        if (is_array($label)) {
            continue;
        }
        $id = intval($in['detail_id'][$key] ?? 0);
        $id = isset($existing[$id]) ? $id : 0;
        $label = $text($label, 100);
        if ($label === '' && !$id) {
            continue;
        }
        $input = $id ? $existing[$id]['asset_type_detail_input'] : (string) ($in['detail_input'][$key] ?? 'text');
        $input = isset(ASSET_TYPE_DETAIL_INPUTS[$input]) ? $input : 'text';
        $details[] = [
            'id' => $id,
            'label' => $label,
            'input' => $input,
            'unit' => $input === 'number' ? $text($in['detail_unit'][$key] ?? '', 20) : '',
            'options' => $input === 'select' ? assetTypeDetailOptions(is_array($in['detail_options'][$key] ?? '') ? '' : ($in['detail_options'][$key] ?? '')) : [],
            'flag' => $input === 'date' && !empty($in['detail_flag'][$key]),
            'unit_only' => !empty($in['detail_unit_only'][$key]),
        ];
    }
    $icon = categoryIcon($in['icon'] ?? '');
    return [
        'name' => $text($in['name'] ?? '', 200),
        'icon' => 'fa-' . ($icon !== '' ? $icon : 'tag'),
        'tone' => isset(ASSET_TYPE_TONES[$in['tone'] ?? '']) ? $in['tone'] : 'other',
        'group' => isset(ASSET_TYPE_GROUPS[$in['group'] ?? '']) ? $in['group'] : 'other',
        'os' => ($in['os'] ?? '') === 'firmware' ? 'firmware' : 'os',
        'fields' => array_values(array_intersect(ASSET_FIELDS, (array) ($in['fields'] ?? []))),
        'specs' => array_values(array_intersect(array_keys(ASSET_SPECS), (array) ($in['specs'] ?? []))),
        'rack' => !empty($in['rack']),
        'description' => $text($in['description'] ?? '', 255),
        'base' => assetTypeBuiltInName($in['base'] ?? ''),
        'details' => $details,
    ];
}

/** What is wrong with a type as given, in words - '' when it can be kept. $asset_type_id is the one being changed. */
function assetTypeProblem(array $f, $asset_type_id = 0) {
    if ($f['name'] === '') {
        return 'Give it a name.';
    }
    $clash = assetTypeName($f['name']);
    if ($clash !== '') {
        $info = assetTypes()[$clash];
        if ($info['builtin'] || $info['id'] !== intval($asset_type_id)) {
            return "There is already a type called $clash" . ($info['builtin'] ? ' - one of ITFlow\'s own' : '') . '.';
        }
    }
    if (count($f['details']) > ASSET_TYPE_DETAILS_MAX) {
        return 'A type can have up to ' . ASSET_TYPE_DETAILS_MAX . ' details of its own.';
    }
    $labels = [];
    foreach ($f['details'] as $detail) {
        if ($detail['label'] === '') {
            return 'Each of its own details needs a name - remove one it no longer needs.';
        }
        if (in_array(mb_strtolower($detail['label']), $labels, true)) {
            return "It has two details called {$detail['label']}.";
        }
        $labels[] = mb_strtolower($detail['label']);
        if ($detail['input'] === 'select' && !$detail['options']) {
            return "Give {$detail['label']} the choices on its list.";
        }
    }
    return '';
}

/** The columns a type of yours is kept in, as SQL. */
function assetTypeSetSql(array $f) {
    return "asset_type_name = '" . escapeSql($f['name']) . "', asset_type_icon = '" . escapeSql($f['icon']) . "', asset_type_tone = '" . escapeSql($f['tone']) . "',
        asset_type_group = '" . escapeSql($f['group']) . "', asset_type_os = '" . escapeSql($f['os']) . "', asset_type_fields = '" . escapeSql(implode(',', $f['fields'])) . "',
        asset_type_specs = '" . escapeSql(implode(',', $f['specs'])) . "', asset_type_rack = " . ($f['rack'] ? 1 : 0) . ",
        asset_type_description = " . ($f['description'] !== '' ? "'" . escapeSql($f['description']) . "'" : 'NULL') . ",
        asset_type_base = " . ($f['base'] !== '' ? "'" . escapeSql($f['base']) . "'" : 'NULL');
}

/**
 * Make a type of yours - its id. Assets, products and recurring lines that
 * already had the name, spelt any way (one set up from the list of types on
 * assets that aren't set up), take its spelling. [id, how many assets had it].
 */
function assetTypeCreate(array $f) {
    global $mysqli;
    mysqli_query($mysqli, "INSERT INTO asset_types SET " . assetTypeSetSql($f) . ", asset_type_builtin = 0, asset_type_active = 1");
    $asset_type_id = intval(mysqli_insert_id($mysqli));
    assetTypeDetailsSave($asset_type_id, $f['details'], []);
    $had = intval(mysqli_fetch_row(mysqli_query($mysqli, "SELECT COUNT(*) FROM assets WHERE asset_type = '" . escapeSql($f['name']) . "'"))[0]);
    assetTypeRenameEverywhere($f['name'], $f['name']);
    assetTypesReset();
    return [$asset_type_id, $had];
}

/** Change a type of yours - what changed, in words. A new name goes on its assets, products and recurring lines too. */
function assetTypeUpdate(array $row, array $f) {
    global $mysqli;
    $asset_type_id = intval($row['asset_type_id']);
    $changes = [];
    if ($row['asset_type_name'] !== $f['name']) {
        $changes[] = 'renamed from ' . $row['asset_type_name'];
    }
    $was = [
        'icon' => ['icon', $row['asset_type_icon']],
        'tone' => ['color', $row['asset_type_tone']],
        'group' => ['group', $row['asset_type_group']],
        'os' => ['OS label', $row['asset_type_os']],
        'description' => ['description', (string) $row['asset_type_description']],
    ];
    foreach ($was as $key => [$what, $before]) {
        if ((string) $before !== (string) $f[$key]) {
            $changes[] = $key === 'group' ? 'group ' . ASSET_TYPE_GROUPS[$f['group']]['label'] : "$what changed";
        }
    }
    if ($row['asset_type_fields'] !== implode(',', $f['fields'])) {
        $changes[] = 'fields changed';
    }
    if ($row['asset_type_specs'] !== implode(',', $f['specs'])) {
        $changes[] = "ITFlow's details changed";
    }
    if (intval($row['asset_type_rack']) !== ($f['rack'] ? 1 : 0)) {
        $changes[] = $f['rack'] ? 'goes in a rack' : 'no longer goes in a rack';
    }
    mysqli_query($mysqli, "UPDATE asset_types SET " . assetTypeSetSql($f) . " WHERE asset_type_id = $asset_type_id");
    $changes = array_merge($changes, assetTypeDetailsSave($asset_type_id, $f['details'], $row['details']));
    if ($row['asset_type_name'] !== $f['name']) {
        assetTypeRenameEverywhere($row['asset_type_name'], $f['name']);
    }
    assetTypesReset();
    return $changes;
}

/**
 * Keep a type's own details as given, in their order - changed, added, and
 * the ones gone deleted with their values on its assets. What changed, in words.
 */
function assetTypeDetailsSave($asset_type_id, array $details, array $before) {
    global $mysqli;
    $asset_type_id = intval($asset_type_id);
    $was = [];
    foreach ($before as $detail) {
        $was[intval($detail['asset_type_detail_id'])] = $detail;
    }
    $kept = [];
    $changes = [];
    foreach (array_values($details) as $order => $detail) {
        $set = "asset_type_detail_label = '" . escapeSql($detail['label']) . "',
            asset_type_detail_unit = " . ($detail['unit'] !== '' ? "'" . escapeSql($detail['unit']) . "'" : 'NULL') . ",
            asset_type_detail_options = " . ($detail['options'] ? "'" . escapeSql(implode("\n", $detail['options'])) . "'" : 'NULL') . ",
            asset_type_detail_flag = " . ($detail['flag'] ? 1 : 0) . ", asset_type_detail_unit_only = " . ($detail['unit_only'] ? 1 : 0) . ", asset_type_detail_order = $order";
        if ($detail['id'] && isset($was[$detail['id']])) {
            mysqli_query($mysqli, "UPDATE asset_type_details SET $set WHERE asset_type_detail_id = " . intval($detail['id']) . " AND asset_type_detail_asset_type_id = $asset_type_id");
            if ($was[$detail['id']]['asset_type_detail_label'] !== $detail['label']) {
                $changes[] = 'detail ' . $was[$detail['id']]['asset_type_detail_label'] . ' renamed ' . $detail['label'];
            }
            $kept[] = intval($detail['id']);
        } else {
            mysqli_query($mysqli, "INSERT INTO asset_type_details SET $set, asset_type_detail_input = '" . escapeSql($detail['input']) . "', asset_type_detail_asset_type_id = $asset_type_id");
            $changes[] = 'detail ' . $detail['label'] . ' added';
        }
    }
    foreach ($was as $detail_id => $detail) {
        if (in_array($detail_id, $kept, true)) {
            continue;
        }
        mysqli_query($mysqli, "DELETE FROM asset_specs WHERE asset_spec_key = 'custom_$detail_id'");
        $values = mysqli_affected_rows($mysqli);
        mysqli_query($mysqli, "DELETE FROM asset_type_details WHERE asset_type_detail_id = $detail_id");
        $changes[] = 'detail ' . $detail['asset_type_detail_label'] . ' removed' . ($values > 0 ? " with its value on $values " . ($values === 1 ? 'asset' : 'assets') : '');
    }
    return $changes;
}

/** The recurring invoice lines that count the assets of a type - [item_id => their seat filter], spelt any way. */
function assetTypeSeatLines($name) {
    global $mysqli;
    $lines = [];
    $sql = mysqli_query($mysqli, "SELECT item_id, item_seat_filter FROM recurring_invoice_items WHERE item_seat_filter LIKE '%\"types\"%'");
    while ($sql && $row = mysqli_fetch_assoc($sql)) {
        $filter = json_decode((string) $row['item_seat_filter'], true);
        foreach (is_array($filter['types'] ?? null) ? $filter['types'] : [] as $type) {
            if (strcasecmp(trim((string) $type), trim((string) $name)) === 0) {
                $lines[intval($row['item_id'])] = $filter;
                break;
            }
        }
    }
    return $lines;
}

/**
 * Put a new name on a type wherever it is held - its assets, the products
 * that become one and the recurring lines that count them. The old name is
 * matched without case, so this also gives them all the same spelling. Their
 * last-changed times stay. [assets, products, lines].
 */
function assetTypeRenameEverywhere($old, $new) {
    global $mysqli;
    $old_sql = escapeSql($old);
    $new_sql = escapeSql($new);
    mysqli_query($mysqli, "UPDATE assets SET asset_type = '$new_sql', asset_updated_at = asset_updated_at WHERE asset_type = '$old_sql' AND BINARY asset_type <> BINARY '$new_sql'");
    $assets = max(0, mysqli_affected_rows($mysqli));
    mysqli_query($mysqli, "UPDATE products SET product_asset_type = '$new_sql', product_updated_at = product_updated_at WHERE product_asset_type = '$old_sql' AND BINARY product_asset_type <> BINARY '$new_sql'");
    $products = max(0, mysqli_affected_rows($mysqli));
    $lines = 0;
    foreach (assetTypeSeatLines($old) as $item_id => $filter) {
        $types = [];
        foreach ($filter['types'] as $type) {
            $type = strcasecmp(trim((string) $type), trim((string) $old)) === 0 ? $new : (string) $type;
            if (!in_array(mb_strtolower($type), array_map('mb_strtolower', $types), true)) {
                $types[] = $type;
            }
        }
        if ($types !== $filter['types']) {
            $filter['types'] = $types;
            mysqli_query($mysqli, "UPDATE recurring_invoice_items SET item_seat_filter = '" . escapeSql(json_encode($filter)) . "', item_updated_at = item_updated_at WHERE item_id = $item_id");
            $lines++;
        }
    }
    return [$assets, $products, $lines];
}

/** What has a type - assets (not archived), archived (archived assets), products that become one and recurring lines that count them. */
function assetTypeUsage($name) {
    global $mysqli;
    $name_sql = escapeSql($name);
    $row = mysqli_fetch_row(mysqli_query($mysqli, "SELECT COALESCE(SUM(asset_archived_at IS NULL), 0), COALESCE(SUM(asset_archived_at IS NOT NULL), 0) FROM assets WHERE asset_type = '$name_sql'"));
    return [
        'assets' => intval($row[0]),
        'archived' => intval($row[1]),
        'products' => intval(mysqli_fetch_row(mysqli_query($mysqli, "SELECT COUNT(*) FROM products WHERE product_asset_type = '$name_sql'"))[0]),
        'lines' => count(assetTypeSeatLines($name)),
    ];
}

/**
 * Every type's assets - [name => [assets, archived]] by the type as the list
 * spells it, and the types on assets that aren't set up as one: [as it is on
 * them => [assets, archived]].
 */
function assetTypeAssetCounts() {
    global $mysqli;
    $known = $unknown = [];
    $sql = mysqli_query($mysqli, "SELECT asset_type, COUNT(*) AS n, COALESCE(SUM(asset_archived_at IS NULL), 0) AS live FROM assets GROUP BY asset_type");
    while ($row = mysqli_fetch_assoc($sql)) {
        $type = trim((string) $row['asset_type']);
        if ($type === '') {
            continue;
        }
        $name = assetTypeName($type);
        $counts = [intval($row['live']), intval($row['n']) - intval($row['live'])];
        if ($name !== '') {
            $known[$name] = [($known[$name][0] ?? 0) + $counts[0], ($known[$name][1] ?? 0) + $counts[1]];
        } else {
            $unknown[$type] = $counts;
        }
    }
    return [$known, $unknown];
}

/** Switch a type on or off - yours, or one of ITFlow's but Other. Whether it changed. */
function assetTypeSetActive($name, $active) {
    global $mysqli;
    $name = assetTypeName($name);
    if ($name === '' || $name === 'Other') {
        return false;
    }
    $info = assetTypes()[$name];
    if ($info['active'] === (bool) $active) {
        return false;
    }
    $on = $active ? 1 : 0;
    if ($info['builtin']) {
        mysqli_query($mysqli, "INSERT INTO asset_types SET asset_type_name = '" . escapeSql($name) . "', asset_type_builtin = 1, asset_type_active = $on
            ON DUPLICATE KEY UPDATE asset_type_active = $on, asset_type_builtin = 1");
    } else {
        mysqli_query($mysqli, "UPDATE asset_types SET asset_type_active = $on WHERE asset_type_id = " . intval($info['id']));
    }
    assetTypesReset();
    return true;
}

/**
 * Delete a type of yours. Its assets, products and recurring lines go to
 * $move_to first - needed when anything has it - taking the details that type
 * has too, the rest going as they would if the asset was changed to it by
 * hand. Its own details' values go with it. [ok, assets moved].
 */
function assetTypeDelete(array $row, $move_to = '') {
    global $mysqli;
    $name = $row['asset_type_name'];
    $usage = assetTypeUsage($name);
    $move_to = assetTypeName($move_to);
    if ($move_to === $name || ($move_to === '' && ($usage['assets'] || $usage['archived'] || $usage['products'] || $usage['lines']))) {
        return [false, 0];
    }
    $moved = 0;
    if ($move_to !== '') {
        $keep = array_keys(assetTypeSpecs($move_to));
        mysqli_query($mysqli, "DELETE asset_specs FROM asset_specs JOIN assets ON asset_id = asset_spec_asset_id
            WHERE asset_type = '" . escapeSql($name) . "'" . ($keep ? " AND asset_spec_key NOT IN ('" . implode("', '", array_map('escapeSql', $keep)) . "')" : ''));
        [$moved] = assetTypeRenameEverywhere($name, $move_to);
    }
    foreach ($row['details'] as $detail) {
        mysqli_query($mysqli, "DELETE FROM asset_specs WHERE asset_spec_key = 'custom_" . intval($detail['asset_type_detail_id']) . "'");
    }
    mysqli_query($mysqli, "DELETE FROM asset_types WHERE asset_type_id = " . intval($row['asset_type_id']));
    assetTypesReset();
    return [true, $moved];
}

/** The types a type's assets can be moved to - the ones switched on but this one: [name => icon class]. */
function assetTypeMoveTargets($name) {
    $targets = assetTypePicker();
    unset($targets[assetTypeName($name)]);
    return $targets;
}

/** How many assets have a value in each of these details of yours - [detail id => n]. */
function assetTypeDetailValueCounts(array $detail_ids) {
    global $mysqli;
    $keys = array_map(function ($id) {
        return "'custom_" . intval($id) . "'";
    }, array_filter(array_map('intval', $detail_ids)));
    $counts = [];
    if (!$keys) {
        return $counts;
    }
    $sql = mysqli_query($mysqli, "SELECT asset_spec_key, COUNT(*) FROM asset_specs WHERE asset_spec_key IN (" . implode(',', $keys) . ") GROUP BY asset_spec_key");
    while ($row = mysqli_fetch_row($sql)) {
        $counts[intval(substr($row[0], 7))] = intval($row[1]);
    }
    return $counts;
}

/**
 * A type as its form starts (admin/modals/asset_type/asset_type_form.php) -
 * one of yours from its row, or a new one from one of ITFlow's ($from) with
 * $name. $as_new for a copy of yours: no name, and its own details come as new
 * ones. Raw.
 */
function assetTypeFormValues($row = null, $as_new = false, $from = 'Other', $name = '') {
    if (!$row) {
        $from = assetTypeBuiltInName($from) ?: 'Other';
        $start = ASSET_TYPES[$from];
        return ['name' => mb_substr(trim(strip_tags((string) $name)), 0, 200), 'icon' => substr($start['icon'], 3), 'tone' => $start['tone'], 'group' => $start['group'],
            'os' => $start['os'], 'fields' => $start['fields'], 'specs' => $start['specs'], 'rack' => in_array($from, ASSET_RACK_TYPES, true),
            'description' => '', 'base' => $from, 'details' => []];
    }
    $info = assetTypeCustomInfo($row, []);
    $values = $as_new ? [] : assetTypeDetailValueCounts(array_column($row['details'], 'asset_type_detail_id'));
    $details = [];
    foreach ($row['details'] as $detail) {
        $detail_id = intval($detail['asset_type_detail_id']);
        $details[] = [
            'id' => $as_new ? 0 : $detail_id,
            'label' => $detail['asset_type_detail_label'],
            'input' => isset(ASSET_TYPE_DETAIL_INPUTS[$detail['asset_type_detail_input']]) ? $detail['asset_type_detail_input'] : 'text',
            'unit' => (string) $detail['asset_type_detail_unit'],
            'options' => assetTypeDetailOptions($detail['asset_type_detail_options']),
            'flag' => intval($detail['asset_type_detail_flag']) === 1,
            'unit_only' => intval($detail['asset_type_detail_unit_only']) === 1,
            'values' => $values[$detail_id] ?? 0,
        ];
    }
    return ['name' => $as_new ? '' : $row['asset_type_name'], 'icon' => substr($info['icon'], 3), 'tone' => $info['tone'], 'group' => $info['group'],
        'os' => $info['os'], 'fields' => $info['fields'], 'specs' => $info['specs'], 'rack' => $info['rack'], 'description' => $info['description'],
        'base' => $info['base'] !== '' ? $info['base'] : ($as_new ? 'Other' : ''), 'details' => $details];
}

/** A type's asset_types row, made for one of ITFlow's that has none yet (switched on, as it is) - its id, 0 when it isn't a type. */
function assetTypeEnsureRow($name) {
    global $mysqli;
    $name = assetTypeName($name);
    if ($name === '') {
        return 0;
    }
    $info = assetTypes()[$name];
    if (!$info['id'] && $info['builtin']) {
        mysqli_query($mysqli, "INSERT IGNORE INTO asset_types SET asset_type_name = '" . escapeSql($name) . "', asset_type_builtin = 1, asset_type_active = 1");
        assetTypesReset();
        $info = assetTypes()[$name];
    }
    return intval($info['id']);
}
