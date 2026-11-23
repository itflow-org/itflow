<?php
/*
 * API - Racks - Read
 * GET /api/v1/racks/read.php
 *
 * A client's racks - archived ones too. One, by rack_id, also has what is mounted
 * in it (units): each item's position (unit_start_number to unit_end_number), face,
 * whether it is full depth, and the asset it is, or its own name and type. A holder
 * says how many it holds side by side (unit_slots); a device in one, which holder
 * (unit_parent_id) and its slot there (unit_position).
 *
 *   rack_id        optional - Just this rack, with its units
 *   client_id      optional - Just this client's
 *   limit, offset  optional - Paging
 */

require_once '../validate_api_key.php';
require_once '../require_get_method.php';

if (isset($_GET['rack_id'])) {
    $id = intval($_GET['rack_id']);
    $sql = mysqli_query($mysqli, "SELECT * FROM racks WHERE rack_id = $id " . apiClientScopeSql('rack_client_id'));
    if ($sql && ($row = mysqli_fetch_assoc($sql))) {
        // From U1 up - a holder, then what is in it by slot
        $units = rackLoadUnits([$id])[$id];
        usort($units, function ($a, $b) {
            return [intval($a['unit_start_number']), intval($a['unit_parent_id'] ?: $a['unit_id']), intval($a['unit_position'])]
                <=> [intval($b['unit_start_number']), intval($b['unit_parent_id'] ?: $b['unit_id']), intval($b['unit_position'])];
        });
        $row['units'] = array_map(function ($unit) {
            return [
                'unit_id' => intval($unit['unit_id']),
                'unit_start_number' => intval($unit['unit_start_number']),
                'unit_end_number' => intval($unit['unit_end_number']),
                'unit_face' => $unit['unit_face'],
                'unit_full_depth' => intval($unit['unit_full_depth']),
                'unit_device' => $unit['unit_device'],
                'unit_type' => $unit['unit_type'],
                'unit_power_watts' => $unit['unit_power_watts'] !== null ? intval($unit['unit_power_watts']) : null,
                'unit_notes' => $unit['unit_notes'],
                'unit_slots' => intval($unit['unit_slots']),
                'unit_parent_id' => $unit['unit_parent_id'] !== null ? intval($unit['unit_parent_id']) : null,
                'unit_position' => $unit['unit_parent_id'] !== null ? intval($unit['unit_position']) : null,
                'asset_id' => $unit['asset_id'] !== null ? intval($unit['asset_id']) : null,
                'asset_name' => $unit['asset_name'],
                'asset_type' => $unit['asset_type'],
            ];
        }, $units);
        echo json_encode(['success' => 'True', 'count' => 1, 'data' => [$row]]);
        exit();
    }
} else {
    $sql = mysqli_query($mysqli, "SELECT * FROM racks WHERE 1=1 " . apiClientScopeSql('rack_client_id') . " ORDER BY rack_id LIMIT $limit OFFSET $offset");
}

// Output
require_once "../read_output.php";
