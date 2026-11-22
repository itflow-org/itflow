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
 *
 * Holders: an item with unit_slots holds devices side by side - two Tiny PCs in
 * a 1U bracket, eight PLCs on a shelf. Each device is an item of its own with
 * unit_parent_id set to the holder and unit_position its slot: 1 is the first,
 * counting across and then a row on, so devices past unit_slots wrap onto
 * another row (stacks). Rows count the way the rack's units do - the first at
 * the bottom, or at the top when U1 is. A device keeps its holder's units, face
 * and depth in its own columns (rackSyncChildren() after the holder moves), so
 * whatever reads where an asset is racked reads it the same way - but only the
 * holder takes space in the rack: occupancy and clashes skip what is in one.
 * Holders don't go in holders. Removing a holder removes what is in it (the
 * foreign key cascades); the assets themselves stay.
 */

/** The most devices a holder takes side by side */
const RACK_SLOTS_MAX = 24;

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
            'NAS' => 'server',
            'Switch' => 'network',
            'Access Point' => 'network',
            'Modem/ONT' => 'network',
            'Firewall/Router' => 'firewall',
            'UPS' => 'power',
            'PDU' => 'power',
        ];
        $asset_type = (string) $asset_type;
        // One of your types by its group (functions/asset_type.php)
        $group_tones = ['server' => 'server', 'virtual' => 'server', 'network' => 'network', 'power' => 'power'];
        return [
            'icon' => 'fa-' . getAssetIcon($asset_type),
            'tone' => $tones[$asset_type] ?? $group_tones[assetTypeInfo($asset_type)['group']] ?? 'device',
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
        // A device in a holder is in the holder's units already
        if (intval($unit['unit_parent_id'] ?? 0)) {
            continue;
        }
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
 * the item being moved. Devices in a holder never clash - their holder does.
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
        AND unit_parent_id IS NULL
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
        unit_face, unit_full_depth, unit_type, unit_power_watts, unit_notes, unit_slots, unit_parent_id, unit_position,
        asset_id, asset_name, asset_type, asset_make, asset_model, asset_serial, asset_status, asset_archived_at,
        (SELECT interface_ip FROM asset_interfaces
            WHERE interface_asset_id = asset_id AND interface_primary = 1 AND interface_ip IS NOT NULL AND interface_ip != ''
            LIMIT 1) AS asset_primary_ip
        FROM rack_units
        LEFT JOIN assets ON asset_id = unit_asset_id
        WHERE unit_rack_id IN (" . implode(',', $rack_ids) . ")
        ORDER BY unit_start_number DESC, unit_position");

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
        'slots' => intval($row['unit_parent_id'] ?? 0) ? 0 : max(0, intval($row['unit_slots'] ?? 0)),
        'parent_id' => intval($row['unit_parent_id'] ?? 0),
        'slot' => intval($row['unit_parent_id'] ?? 0) ? max(1, intval($row['unit_position'] ?? 0)) : 0,
    ];
}

/**
 * rackUnitView() items as they are drawn: the items in the rack itself, each
 * holder with what is in it as 'children', in slot order. A device whose holder
 * isn't among them (it can't be - the key cascades) is left out rather than
 * drawn loose.
 */
function rackNestItems(array $items) {
    $children = [];
    foreach ($items as $item) {
        if ($item['parent_id']) {
            $children[$item['parent_id']][] = $item;
        }
    }
    $nested = [];
    foreach ($items as $item) {
        if ($item['parent_id']) {
            continue;
        }
        $kids = $children[$item['id']] ?? [];
        usort($kids, function ($a, $b) {
            return $a['slot'] <=> $b['slot'];
        });
        $nested[] = $item + ['children' => $kids];
    }
    return $nested;
}

/** How many rows a holder with $slots across draws, its highest slot in use being $highest */
function rackHolderRows($slots, $highest) {
    $slots = max(1, intval($slots));
    return max(1, intval(ceil(max(0, intval($highest)) / $slots)));
}

/** The highest slot a device can go in: the holder's rows so far, and one row more */
function rackSlotLimit($slots, $highest) {
    $slots = max(1, intval($slots));
    return (rackHolderRows($slots, $highest) + 1) * $slots;
}

/**
 * A holder's slots as they are drawn: each ['slot', 'column', 'row' (the CSS
 * grid row), 'item' (the device there, or null)], in slot order. Rows count
 * the way the rack's units do - slot 1 is at the bottom left unless U1 is at
 * the top.
 */
function rackHolderCells(array $holder, $descending) {
    $slots = max(1, intval($holder['slots']));
    $by_slot = [];
    foreach ($holder['children'] ?? [] as $child) {
        $by_slot[$child['slot']] = $child;
    }
    $rows = rackHolderRows($slots, $by_slot ? max(array_keys($by_slot)) : 0);
    $cells = [];
    for ($slot = 1; $slot <= $rows * $slots; $slot++) {
        $row = intdiv($slot - 1, $slots);
        $cells[] = [
            'slot' => $slot,
            'column' => ($slot - 1) % $slots + 1,
            'row' => $descending ? $row + 1 : $rows - $row,
            'item' => $by_slot[$slot] ?? null,
        ];
    }
    return ['rows' => $rows, 'columns' => $slots, 'cells' => $cells];
}

/** How many devices are in a holder */
function rackChildCount($unit_id) {
    global $mysqli;
    return intval(mysqli_fetch_row(mysqli_query($mysqli, "SELECT COUNT(*) FROM rack_units WHERE unit_parent_id = " . intval($unit_id)))[0]);
}

/** What a holder is called (rackUnitName()), raw - '' when it is gone */
function rackHolderName($unit_id) {
    global $mysqli;
    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT unit_device, unit_type, asset_name FROM rack_units
        LEFT JOIN assets ON asset_id = unit_asset_id WHERE unit_id = " . intval($unit_id)));
    return $row ? rackUnitName($row['asset_name'], $row['unit_device'], $row['unit_type']) : '';
}

/** "1 device" / "3 devices" */
function rackDeviceCount($count) {
    $count = intval($count);
    return $count === 1 ? '1 device' : "$count devices";
}

/**
 * Where an item would go, checked the way Add and Edit check it - the rack item
 * form, a drag, Undo and the API all come through here, so they can't drift
 * apart. $want:
 *
 *   holder      the holder (unit id) to put it in, 0 for the rack itself
 *   position    its slot in the holder - 0 for the first free one
 *   start, end, face, full_depth   where in the rack, when it isn't in a holder
 *   slots       how many it holds side by side, 0 when it holds nothing
 *
 * Returns ['error' => why] (HTML - names are in <strong>, tags stripped) or
 * where it goes: holder, position, start, end, face, full_depth, slots and
 * holder_name (raw). rackPlacementSet() turns that into the SET.
 */
function rackPlacement($rack_id, $unit_id, array $want) {
    global $mysqli;

    $rack_id = intval($rack_id);
    $unit_id = intval($unit_id);
    $holder_id = intval($want['holder'] ?? 0);
    $slots = max(0, min(RACK_SLOTS_MAX, intval($want['slots'] ?? 0)));
    $children = $unit_id ? rackChildCount($unit_id) : 0;
    $strong = function ($name) {
        return '<strong>' . strip_tags((string) $name) . '</strong>';
    };

    if ($holder_id) {
        if ($holder_id === $unit_id) {
            return ['error' => "An item can't go in itself"];
        }
        $holder = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT unit_id, unit_rack_id, unit_slots, unit_parent_id, unit_start_number, unit_end_number,
                unit_face, unit_full_depth, unit_device, unit_type, asset_name
            FROM rack_units
            LEFT JOIN assets ON asset_id = unit_asset_id
            WHERE unit_id = $holder_id AND unit_rack_id = $rack_id"));
        if (!$holder) {
            return ['error' => "That holder isn't in this rack any more"];
        }
        $holder_name = rackUnitName($holder['asset_name'], $holder['unit_device'], $holder['unit_type']);
        $holder_slots = intval($holder['unit_slots']);
        if ($holder_slots < 1 || intval($holder['unit_parent_id'])) {
            return ['error' => $strong($holder_name) . " doesn't hold devices"];
        }
        if ($children) {
            return ['error' => 'It holds ' . rackDeviceCount($children) . " of its own - a holder can't go in another holder"];
        }

        $taken = [];
        $sql = mysqli_query($mysqli, "SELECT unit_position, unit_device, unit_type, asset_name FROM rack_units
            LEFT JOIN assets ON asset_id = unit_asset_id
            WHERE unit_parent_id = $holder_id AND unit_id != $unit_id");
        while ($row = mysqli_fetch_assoc($sql)) {
            $taken[intval($row['unit_position'])] = rackUnitName($row['asset_name'], $row['unit_device'], $row['unit_type']);
        }
        $limit = rackSlotLimit($holder_slots, $taken ? max(array_keys($taken)) : 0);

        $position = intval($want['position'] ?? 0);
        if ($position < 1) {
            $position = 1;
            while (isset($taken[$position])) {
                $position++;
            }
        }
        if ($position > $limit) {
            return ['error' => $strong($holder_name) . " has slots 1 to $limit - pick one of those"];
        }
        if (isset($taken[$position])) {
            return ['error' => "Slot $position in " . $strong($holder_name) . ' is taken by ' . $strong($taken[$position])];
        }

        return [
            'holder' => $holder_id,
            'position' => $position,
            'start' => intval($holder['unit_start_number']),
            'end' => intval($holder['unit_end_number']),
            'face' => $holder['unit_face'] === 'rear' ? 'rear' : 'front',
            'full_depth' => intval($holder['unit_full_depth']) === 1 ? 1 : 0,
            'slots' => 0,
            'holder_name' => $holder_name,
        ];
    }

    $rack_units = intval(mysqli_fetch_row(mysqli_query($mysqli, "SELECT rack_units FROM racks WHERE rack_id = $rack_id"))[0] ?? 0);
    $start = intval($want['start'] ?? 0);
    $end = max($start, intval($want['end'] ?? $start));
    $face = ($want['face'] ?? 'front') === 'rear' ? 'rear' : 'front';
    $full_depth = intval($want['full_depth'] ?? 0) === 1 ? 1 : 0;

    if ($start < 1 || $end > $rack_units) {
        return ['error' => "That doesn't fit - this rack runs from U1 to U$rack_units"];
    }

    $conflict = rackUnitConflict($rack_id, $start, $end, $face, $full_depth, $unit_id);
    if ($conflict) {
        return ['error' => html_entity_decode(rackPositionLabel($start, $end)) . " on the $face is taken by " . $strong($conflict['name']) . ' (' . html_entity_decode(rackPositionLabel($conflict['start'], $conflict['end'])) . ')'];
    }

    if ($children && $slots < 1) {
        return ['error' => 'It holds ' . rackDeviceCount($children) . ' - take ' . ($children === 1 ? 'it' : 'them') . ' out before it stops holding devices'];
    }

    return [
        'holder' => 0,
        'position' => 0,
        'start' => $start,
        'end' => $end,
        'face' => $face,
        'full_depth' => $full_depth,
        'slots' => $slots,
        'holder_name' => '',
    ];
}

/** The SET for where rackPlacement() says an item goes - not its slots, which only Add and Edit change */
function rackPlacementSet(array $place) {
    return "unit_parent_id = " . (intval($place['holder']) ?: 'NULL') . ", unit_position = " . intval($place['position']) . ",
        unit_start_number = " . intval($place['start']) . ", unit_end_number = " . intval($place['end']) . ",
        unit_face = '" . ($place['face'] === 'rear' ? 'rear' : 'front') . "', unit_full_depth = " . (intval($place['full_depth']) ? 1 : 0);
}

/** What is in a holder takes its units, face and depth again - after the holder moves or changes */
function rackSyncChildren($holder_id) {
    global $mysqli;
    $holder_id = intval($holder_id);
    mysqli_query($mysqli, "UPDATE rack_units child
        JOIN rack_units holder ON holder.unit_id = child.unit_parent_id
        SET child.unit_start_number = holder.unit_start_number, child.unit_end_number = holder.unit_end_number,
            child.unit_face = holder.unit_face, child.unit_full_depth = holder.unit_full_depth, child.unit_rack_id = holder.unit_rack_id
        WHERE holder.unit_id = $holder_id");
}

/** " - slot 2 in Tiny bracket", raw, for where an asset is racked - '' for one in the rack itself */
function rackSlotText($holder_name, $position) {
    $holder_name = trim((string) $holder_name);
    return $holder_name !== '' ? ' - slot ' . intval($position) . " in $holder_name" : '';
}

/**
 * How an asset of this type usually goes in a rack, to start the Add window
 * from: ['height' => U, 'full_depth' => 0|1]. A server, NAS or UPS takes 2U;
 * an access point, firewall, modem or PDU is usually half depth.
 */
function rackAssetDefaults($asset_type) {
    $asset_type = (string) $asset_type;
    return [
        'height' => in_array($asset_type, ['Server', 'NAS', 'UPS'], true) ? 2 : 1,
        'full_depth' => in_array($asset_type, ['Access Point', 'Firewall/Router', 'Modem/ONT', 'PDU'], true) ? 0 : 1,
    ];
}

/**
 * Where an asset is racked: the rack and its position, or null. Raw values. In
 * a holder, holder_name is the holder and unit_position its slot there.
 */
function rackPositionForAsset($asset_id) {
    global $mysqli;

    $asset_id = intval($asset_id);
    $sql = mysqli_query($mysqli, "SELECT rack_id, rack_name, rack_archived_at, rack_units.unit_start_number, rack_units.unit_end_number,
            rack_units.unit_face, rack_units.unit_position, holder.unit_id AS holder_id, holder.unit_device AS holder_device,
            holder.unit_type AS holder_type, holder_asset.asset_name AS holder_asset_name
        FROM rack_units
        JOIN racks ON rack_id = rack_units.unit_rack_id
        LEFT JOIN rack_units holder ON holder.unit_id = rack_units.unit_parent_id
        LEFT JOIN assets holder_asset ON holder_asset.asset_id = holder.unit_asset_id
        WHERE rack_units.unit_asset_id = $asset_id
        LIMIT 1");

    $row = mysqli_fetch_assoc($sql);
    if (!$row) {
        return null;
    }
    $row['holder_name'] = $row['holder_id'] ? rackUnitName($row['holder_asset_name'], $row['holder_device'], $row['holder_type']) : '';
    return $row;
}
