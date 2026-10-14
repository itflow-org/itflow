<?php

/*
 * Racks - rack elevation layout, occupancy and the checks shared by the rack
 * handlers (agent/post/rack.php), the rack page (agent/rack.php) and the racks
 * listing (agent/racks.php).
 *
 * Positions: an item occupies unit_start_number..unit_end_number, start being
 * the LOWEST U number. That is storage only - whether U1 is drawn at the bottom
 * (the usual) or at the top is the rack's rack_units_descending flag.
 *
 * Faces: an item is mounted on the front or the rear. A full-depth item (most
 * servers, a UPS) fills both faces at its units; a half-depth one (a patch
 * panel, a small switch) leaves the other face free for something else.
 */

/** Unlinked rack items - things worth documenting that are not assets. */
function rackItemTypes() {
    return [
        'Device'           => ['icon' => 'fa-hdd',         'tone' => 'device'],
        'Patch Panel'      => ['icon' => 'fa-ethernet',    'tone' => 'patch'],
        'Cable Management' => ['icon' => 'fa-grip-lines',  'tone' => 'cable'],
        'Shelf'            => ['icon' => 'fa-layer-group', 'tone' => 'shelf'],
        'PDU'              => ['icon' => 'fa-plug',        'tone' => 'power'],
        'UPS'              => ['icon' => 'fa-car-battery', 'tone' => 'power'],
        'KVM / Console'    => ['icon' => 'fa-keyboard',    'tone' => 'device'],
        'Blanking Panel'   => ['icon' => 'fa-minus',       'tone' => 'blank'],
    ];
}

/**
 * How an item is drawn: its icon, colour tone and a type label. A linked asset
 * goes by its asset type, an unlinked item by its rack item type.
 */
function rackItemStyle($asset_type, $unit_type, $has_asset) {
    if ($has_asset) {
        $tones = [
            'Server' => 'server',
            'Virtual Machine' => 'server',
            'Switch' => 'network',
            'Access Point' => 'network',
            'Firewall/Router' => 'firewall',
        ];
        $asset_type = (string) $asset_type;
        return [
            'icon' => 'fa-' . getAssetIcon($asset_type),
            'tone' => $tones[$asset_type] ?? 'device',
            'label' => $asset_type !== '' ? $asset_type : 'Asset',
        ];
    }

    $types = rackItemTypes();
    $unit_type = isset($types[$unit_type]) ? $unit_type : 'Device';
    return [
        'icon' => $types[$unit_type]['icon'],
        'tone' => $types[$unit_type]['tone'],
        'label' => $unit_type,
    ];
}

/** "U7" or "U7-U8" (with an en dash) */
function rackPositionLabel($start, $end) {
    $start = intval($start);
    $end = intval($end);
    return $start === $end ? "U$start" : "U$start&ndash;U$end";
}

/** Does an item mounted like this fill the given face? */
function rackUnitOnFace($unit_face, $unit_full_depth, $face) {
    return $unit_full_depth || $unit_face === $face;
}

/**
 * Units in use per face, plus the rack as a whole. A U counts as used on a face
 * when anything fills that face there, and as used for the rack when it is used
 * on either face - a half-depth panel at the front still takes the U.
 *
 * $units: rows with unit_start_number, unit_end_number, unit_face, unit_full_depth
 * Returns front/rear/any (counts), free_any (count of U with nothing on either
 * face) and largest_gap ['size', 'start', 'end'] - the biggest run of U that is
 * free on BOTH faces, i.e. where a full-depth item could still go.
 */
function rackOccupancy(array $units, $rack_units) {
    $rack_units = max(0, intval($rack_units));
    $front = array_fill(1, max(1, $rack_units), false);
    $rear = $front;

    foreach ($units as $unit) {
        $start = max(1, intval($unit['unit_start_number']));
        $end = min($rack_units, intval($unit['unit_end_number']));
        $face = ($unit['unit_face'] ?? 'front') === 'rear' ? 'rear' : 'front';
        $full_depth = intval($unit['unit_full_depth'] ?? 1) === 1;
        for ($u = $start; $u <= $end; $u++) {
            if (rackUnitOnFace($face, $full_depth, 'front')) {
                $front[$u] = true;
            }
            if (rackUnitOnFace($face, $full_depth, 'rear')) {
                $rear[$u] = true;
            }
        }
    }

    $used_front = 0;
    $used_rear = 0;
    $used_any = 0;
    $gap = ['size' => 0, 'start' => 0, 'end' => 0];
    $run_start = 0;

    for ($u = 1; $u <= $rack_units; $u++) {
        $used_front += $front[$u] ? 1 : 0;
        $used_rear += $rear[$u] ? 1 : 0;
        $taken = $front[$u] || $rear[$u];
        $used_any += $taken ? 1 : 0;

        if (!$taken) {
            if (!$run_start) {
                $run_start = $u;
            }
            $size = $u - $run_start + 1;
            if ($size > $gap['size']) {
                $gap = ['size' => $size, 'start' => $run_start, 'end' => $u];
            }
        } else {
            $run_start = 0;
        }
    }

    return [
        'front' => $used_front,
        'rear' => $used_rear,
        'any' => $used_any,
        'free_any' => $rack_units - $used_any,
        'largest_gap' => $gap,
    ];
}

/**
 * The CSS grid row an item starts on, for a rack drawn one row per U. U1 sits
 * at the bottom unless the rack numbers top-down.
 */
function rackGridRow($start, $end, $rack_units, $descending) {
    return $descending ? intval($start) : intval($rack_units) - intval($end) + 1;
}

/**
 * The first item that would clash with an item at $start..$end on $face, or
 * null if the space is free. Two items clash when their units overlap and they
 * share a face - which a full-depth item always does. $exclude_unit_id skips
 * the item being moved.
 *
 * Returns the clashing item's display name and position, raw (escape it).
 */
function rackUnitConflict($rack_id, $start, $end, $face, $full_depth, $exclude_unit_id = 0) {
    global $mysqli;

    $rack_id = intval($rack_id);
    $start = intval($start);
    $end = intval($end);
    $face = $face === 'rear' ? 'rear' : 'front';
    $full_depth = $full_depth ? 1 : 0;
    $exclude_unit_id = intval($exclude_unit_id);

    $sql = mysqli_query($mysqli, "SELECT unit_device, unit_type, unit_start_number, unit_end_number, asset_name
        FROM rack_units
        LEFT JOIN assets ON asset_id = unit_asset_id
        WHERE unit_rack_id = $rack_id
        AND unit_id != $exclude_unit_id
        AND unit_start_number <= $end AND unit_end_number >= $start
        AND (unit_full_depth = 1 OR $full_depth = 1 OR unit_face = '$face')
        ORDER BY unit_start_number
        LIMIT 1");

    $row = mysqli_fetch_assoc($sql);
    if (!$row) {
        return null;
    }

    return [
        'name' => rackUnitName($row['asset_name'] ?? '', $row['unit_device'], $row['unit_type']),
        'start' => intval($row['unit_start_number']),
        'end' => intval($row['unit_end_number']),
    ];
}

/** What to call an item: its asset, else its label, else its type. Raw. */
function rackUnitName($asset_name, $unit_device, $unit_type) {
    $asset_name = trim((string) $asset_name);
    $unit_device = trim((string) $unit_device);
    if ($asset_name !== '') {
        return $asset_name;
    }
    if ($unit_device !== '') {
        return $unit_device;
    }
    return trim((string) $unit_type) !== '' ? (string) $unit_type : 'Unnamed item';
}

/**
 * Store an uploaded rack photo under the client's upload folder, removing the
 * one it replaces. Returns the stored file name, or '' when no usable file came.
 */
function rackStorePhoto($file, $client_id, $old_photo = '') {
    $client_id = intval($client_id);

    $new_file_name = checkFileUpload($file, ['jpg', 'jpeg', 'gif', 'png', 'webp']);
    if (!$new_file_name) {
        return '';
    }

    $upload_dir = __DIR__ . "/../uploads/clients/$client_id";
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    if (!move_uploaded_file($file['tmp_name'], "$upload_dir/$new_file_name")) {
        return '';
    }

    rackDeletePhoto($client_id, $old_photo);

    return $new_file_name;
}

/** Remove a rack photo file, if there is one. Never leaves the client's folder. */
function rackDeletePhoto($client_id, $photo) {
    $photo = basename((string) $photo);
    if ($photo === '' || $photo === '.' || $photo === '..') {
        return;
    }
    $path = __DIR__ . "/../uploads/clients/" . intval($client_id) . "/$photo";
    if (is_file($path)) {
        unlink($path);
    }
}

/**
 * Every item in the given racks, keyed by rack id, with what the rack page and
 * listing draw from its asset. Raw rows - escape them (rackUnitView() does).
 */
function rackLoadUnits(array $rack_ids) {
    global $mysqli;

    $rack_ids = array_filter(array_map('intval', $rack_ids));
    $units = array_fill_keys($rack_ids, []);
    if (empty($rack_ids)) {
        return $units;
    }

    $sql = mysqli_query($mysqli, "SELECT unit_id, unit_rack_id, unit_start_number, unit_end_number, unit_device,
        unit_face, unit_full_depth, unit_type, unit_power_watts, unit_notes,
        asset_id, asset_name, asset_type, asset_make, asset_model, asset_serial, asset_status, asset_archived_at,
        (SELECT interface_ip FROM asset_interfaces
            WHERE interface_asset_id = asset_id AND interface_primary = 1 AND interface_ip IS NOT NULL AND interface_ip != ''
            LIMIT 1) AS asset_primary_ip
        FROM rack_units
        LEFT JOIN assets ON asset_id = unit_asset_id
        WHERE unit_rack_id IN (" . implode(',', $rack_ids) . ")
        ORDER BY unit_start_number DESC");

    while ($row = mysqli_fetch_assoc($sql)) {
        $units[intval($row['unit_rack_id'])][] = $row;
    }

    return $units;
}

/**
 * One item, ready to draw. Every string in the result is HTML-escaped; numbers
 * are ints. 'name' is what to call it, 'meta' the quieter line under it.
 */
function rackUnitView(array $row) {
    $has_asset = intval($row['asset_id'] ?? 0) > 0;
    $style = rackItemStyle($row['asset_type'] ?? '', $row['unit_type'] ?? '', $has_asset);
    $start = intval($row['unit_start_number']);
    $end = max($start, intval($row['unit_end_number']));

    $make_model = trim(($row['asset_make'] ?? '') . ' ' . ($row['asset_model'] ?? ''));
    $label = trim((string) ($row['unit_device'] ?? ''));
    $name = rackUnitName($row['asset_name'] ?? '', $label, $row['unit_type'] ?? '');

    // A label that just repeats the asset name ("SRV-01 - Dell R650", how items
    // used to be labelled) says nothing the asset doesn't - drop it
    $asset_name = trim((string) ($row['asset_name'] ?? ''));
    if ($has_asset && $label !== '' && stripos($label, $asset_name) === 0) {
        $label = '';
    }

    // Under the name: the asset's label if it has one, else its make and model.
    // An unlinked item gets its type, unless its name already says it.
    if ($has_asset) {
        $meta = $label !== '' ? $label : $make_model;
    } elseif ($label !== '' && stripos($label, $style['label']) === false && $style['label'] !== 'Device') {
        $meta = $style['label'];
    } else {
        $meta = '';
    }

    return [
        'id' => intval($row['unit_id']),
        'start' => $start,
        'end' => $end,
        'height' => $end - $start + 1,
        'face' => ($row['unit_face'] ?? 'front') === 'rear' ? 'rear' : 'front',
        'full_depth' => intval($row['unit_full_depth'] ?? 1) === 1,
        'asset_id' => $has_asset ? intval($row['asset_id']) : 0,
        'asset_archived' => $has_asset && !empty($row['asset_archived_at']),
        'name' => escapeHtml($name),
        'meta' => escapeHtml($meta),
        'label' => escapeHtml($label),
        'make_model' => escapeHtml($make_model),
        'serial' => escapeHtml($row['asset_serial'] ?? ''),
        'status' => escapeHtml($row['asset_status'] ?? ''),
        'ip' => escapeHtml($row['asset_primary_ip'] ?? ''),
        'power' => intval($row['unit_power_watts'] ?? 0),
        'notes' => escapeHtml($row['unit_notes'] ?? ''),
        'icon' => $style['icon'],
        'tone' => $style['tone'],
        'type_label' => escapeHtml($style['label']),
        'position' => rackPositionLabel($start, $end),
    ];
}

/** Where an asset is racked: the rack and its position, or null. Raw values. */
function rackPositionForAsset($asset_id) {
    global $mysqli;

    $asset_id = intval($asset_id);
    $sql = mysqli_query($mysqli, "SELECT rack_id, rack_name, rack_archived_at, unit_start_number, unit_end_number, unit_face
        FROM rack_units
        JOIN racks ON rack_id = unit_rack_id
        WHERE unit_asset_id = $asset_id
        LIMIT 1");

    return mysqli_fetch_assoc($sql) ?: null;
}
