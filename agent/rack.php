<?php

/*
 * Rack - one rack, drawn to scale front and rear, with what is in it.
 *
 * The elevation (includes/rack_elevation.php) is the page: every item at its
 * position on the face it is mounted on, every empty U a click away from
 * adding something there. Around it, how full the rack is (units, the largest
 * gap a full-depth item could still go in, power against the budget), the
 * rack's details and photos, the item list for print and search, and the
 * client's network gear that isn't racked anywhere yet.
 */

require_once "includes/inc_all_client.php";

// Perms
enforceUserPermission('module_support');

$rack_id = intval($_GET['rack_id'] ?? 0);

$sql = mysqli_query(
    $mysqli,
    "SELECT rack_archived_at, rack_client_id, rack_created_at, rack_depth, rack_description, rack_id, rack_model,
        rack_name, rack_notes, rack_photo, rack_photo_rear, rack_physical_location, rack_power_capacity,
        rack_type, rack_units, rack_units_descending, rack_updated_at, location_id, location_name
    FROM racks
    LEFT JOIN locations ON location_id = rack_location_id
    WHERE rack_id = $rack_id
    AND rack_client_id = $client_id
    LIMIT 1"
);

if (mysqli_num_rows($sql) == 0) {

    echo "<center><h1 class='text-secondary mt-5'>Nothing to see here</h1><a class='btn btn-lg btn-secondary mt-3' href='racks.php?client_id=$client_id'><i class='fa fa-fw fa-arrow-left'></i> Back to racks</a></center>";

} else {

    $row = mysqli_fetch_assoc($sql);
    $rack_name = escapeHtml($row['rack_name']);
    $rack_description = escapeHtml($row['rack_description']);
    $rack_model = escapeHtml($row['rack_model']);
    $rack_depth = escapeHtml($row['rack_depth']);
    $rack_type = escapeHtml($row['rack_type']);
    $rack_units = max(1, intval($row['rack_units']));
    $rack_units_descending = intval($row['rack_units_descending']);
    $rack_power_capacity = intval($row['rack_power_capacity']);
    $rack_photo = escapeHtml($row['rack_photo']);
    $rack_photo_rear = escapeHtml($row['rack_photo_rear']);
    $rack_physical_location = escapeHtml($row['rack_physical_location']);
    $rack_notes = escapeHtml($row['rack_notes']);
    $rack_archived_at = escapeHtml($row['rack_archived_at']);
    $rack_updated_at = escapeHtml($row['rack_updated_at'] ?: $row['rack_created_at']);
    $location_id = intval($row['location_id']);
    $location_name = escapeHtml($row['location_name']);

    $can_edit = lookupUserPermission('module_support') >= 2;
    $can_delete = lookupUserPermission('module_support') >= 3;

    $rack_rows = rackLoadUnits([$rack_id])[$rack_id];
    $rack_items = array_map('rackUnitView', $rack_rows);
    $occupancy = rackOccupancy($rack_rows, $rack_units);
    $used_percent = (int) round($occupancy['any'] / $rack_units * 100);
    $power_used = array_sum(array_column($rack_items, 'power'));
    $power_percent = $rack_power_capacity ? (int) round($power_used / $rack_power_capacity * 100) : 0;
    $item_count = count($rack_items);
    $gap = $occupancy['largest_gap'];

    // The client's network kit that isn't in any rack - one click to put it here
    $unracked_assets = [];
    if ($can_edit && !$rack_archived_at) {
        $sql_unracked = mysqli_query($mysqli, "SELECT asset_id, asset_name, asset_type, asset_make, asset_model FROM assets
            WHERE asset_client_id = $client_id
            AND asset_archived_at IS NULL
            AND asset_type IN ('Server', 'Switch', 'Firewall/Router')
            AND NOT EXISTS (SELECT 1 FROM rack_units WHERE unit_asset_id = asset_id)
            ORDER BY FIELD(asset_type, 'Firewall/Router', 'Switch', 'Server'), asset_name
            LIMIT 25");
        while ($unracked_row = mysqli_fetch_assoc($sql_unracked)) {
            $unracked_assets[] = $unracked_row;
        }
    }

    $usage_tone = $used_percent >= 90 ? 'danger' : ($used_percent >= 75 ? 'warning' : 'primary');
    $power_tone = $power_percent >= 90 ? 'danger' : ($power_percent >= 75 ? 'warning' : 'success');

    ?>

    <ol class="breadcrumb d-print-none">
        <li class="breadcrumb-item">
            <a href="client_overview.php?client_id=<?= $client_id ?>"><?= $client_name ?></a>
        </li>
        <li class="breadcrumb-item">
            <a href="racks.php?client_id=<?= $client_id ?>">Racks</a>
        </li>
        <li class="breadcrumb-item active"><?= $rack_name ?></li>
    </ol>

    <!-- Rack header -->
    <div class="card mb-3 itflow-rack-head">
        <div class="card-body d-flex flex-wrap align-items-center gap-3">
            <span class="itflow-rack-head-icon" aria-hidden="true"><i class="fas fa-server"></i></span>
            <div class="flex-grow-1 min-w-0">
                <h1 class="h4 mb-1 fw-semibold">
                    <?= $rack_name ?>
                    <?php if ($rack_archived_at) { ?>
                        <span class="badge rounded-pill text-bg-secondary fw-normal align-middle fs-6 ms-1">Archived</span>
                    <?php } ?>
                </h1>
                <div class="text-body-secondary small d-flex flex-wrap column-gap-3 row-gap-1">
                    <span><i class="fas fa-fw fa-arrows-alt-v me-1"></i><?= $rack_units ?>U</span>
                    <?php if ($rack_type) { ?>
                        <span><i class="fas fa-fw fa-th-large me-1"></i><?= $rack_type ?></span>
                    <?php } ?>
                    <?php if ($location_name || $rack_physical_location) { ?>
                        <span>
                            <i class="fas fa-fw fa-map-marker-alt me-1"></i><?= $location_name ?><?= $location_name && $rack_physical_location ? ' &rsaquo; ' : '' ?><?= $rack_physical_location ?>
                        </span>
                    <?php } ?>
                    <?php if ($rack_description) { ?>
                        <span><?= $rack_description ?></span>
                    <?php } ?>
                </div>
            </div>
            <div class="d-flex flex-wrap gap-2 d-print-none">
                <?php if ($can_edit && !$rack_archived_at) { ?>
                    <button type="button" class="btn btn-primary ajax-modal" data-modal-url="modals/rack/rack_device_add.php?id=<?= $rack_id ?>">
                        <i class="fas fa-plus me-2"></i>Add Item
                    </button>
                <?php } ?>
                <?php if ($can_edit) { ?>
                    <button type="button" class="btn btn-outline-secondary ajax-modal" data-modal-size="lg" data-modal-url="modals/rack/rack_edit.php?id=<?= $rack_id ?>">
                        <i class="fas fa-edit me-2"></i>Edit
                    </button>
                <?php } ?>
                <button type="button" class="btn btn-outline-secondary" onclick="window.print()" title="Print the elevation and item list">
                    <i class="fas fa-print"></i><span class="visually-hidden">Print</span>
                </button>
                <?php if ($can_edit) { ?>
                    <div class="dropdown dropstart">
                        <button class="btn btn-outline-secondary" title="More actions" type="button" data-bs-toggle="dropdown">
                            <i class="fas fa-fw fa-ellipsis-v"></i>
                        </button>
                        <div class="dropdown-menu">
                            <?php if ($rack_archived_at) { ?>
                                <a class="dropdown-item confirm-link" href="post.php?restore_rack=<?= $rack_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>">
                                    <i class="fas fa-fw fa-redo me-2"></i>Restore
                                </a>
                            <?php } else { ?>
                                <a class="dropdown-item text-danger confirm-link" href="post.php?archive_rack=<?= $rack_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>">
                                    <i class="fas fa-fw fa-archive me-2"></i>Archive
                                </a>
                            <?php } ?>
                            <?php if ($can_delete) { ?>
                                <div class="dropdown-divider"></div>
                                <a class="dropdown-item text-danger text-bold confirm-link" href="post.php?delete_rack=<?= $rack_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>">
                                    <i class="fas fa-fw fa-trash me-2"></i>Delete
                                </a>
                            <?php } ?>
                        </div>
                    </div>
                <?php } ?>
            </div>
        </div>
    </div>

    <div class="row g-3">

        <!-- Elevation and items -->
        <div class="col-xl-8 col-xxl-9 order-xl-2">

            <div class="card mb-3">
                <div class="card-header d-flex flex-wrap align-items-center gap-2">
                    <h3 class="card-title mb-0 me-auto"><i class="fas fa-fw fa-columns me-2 text-secondary"></i>Elevation</h3>
                    <span class="small text-body-secondary d-print-none">
                        <?php if ($can_edit && !$rack_archived_at) { ?>
                            Click an empty U to add something there
                        <?php } ?>
                    </span>
                </div>
                <div class="card-body">
                    <div class="itflow-rack-pair">
                        <?php
                        $elevation_items = $rack_items;
                        $elevation_rack_units = $rack_units;
                        $elevation_descending = $rack_units_descending;
                        $elevation_can_edit = $can_edit && !$rack_archived_at;
                        foreach (['front', 'rear'] as $elevation_face) {
                            require "includes/rack_elevation.php";
                        }
                        ?>
                    </div>
                    <div class="itflow-rack-legend small text-body-secondary mt-3">
                        <span><i class="itflow-rack-swatch itflow-rack-tone-server"></i>Servers</span>
                        <span><i class="itflow-rack-swatch itflow-rack-tone-network"></i>Network</span>
                        <span><i class="itflow-rack-swatch itflow-rack-tone-firewall"></i>Firewalls</span>
                        <span><i class="itflow-rack-swatch itflow-rack-tone-power"></i>Power</span>
                        <span><i class="itflow-rack-swatch itflow-rack-tone-patch"></i>Patching</span>
                        <span><i class="itflow-rack-swatch itflow-rack-tone-device"></i>Other</span>
                        <span><i class="itflow-rack-swatch itflow-rack-swatch-ghost"></i>Full depth from the other side</span>
                    </div>
                </div>
            </div>

            <div class="card mb-3 itflow-rack-items">
                <div class="card-header d-flex align-items-center">
                    <h3 class="card-title mb-0 me-auto"><i class="fas fa-fw fa-list me-2 text-secondary"></i>In this rack <span class="text-body-secondary fw-normal">(<?= $item_count ?>)</span></h3>
                </div>
                <?php if ($item_count) { ?>
                    <div class="table-responsive">
                        <table class="table table-sm table-hover align-middle mb-0">
                            <thead class="text-nowrap">
                                <tr>
                                    <th class="ps-3">Position</th>
                                    <th style="min-width: 15rem">Item</th>
                                    <th>Make / Model</th>
                                    <th>Serial</th>
                                    <th>IP</th>
                                    <th>Mounted</th>
                                    <th class="text-end">Power</th>
                                    <?php if ($can_edit) { ?>
                                        <th class="d-print-none"></th>
                                    <?php } ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                // Top of the rack first, the way you read it standing in front of it
                                $table_items = $rack_items;
                                usort($table_items, function ($a, $b) use ($rack_units_descending) {
                                    return $rack_units_descending ? $a['start'] <=> $b['start'] : $b['end'] <=> $a['end'];
                                });
                                foreach ($table_items as $item) { ?>
                                    <tr>
                                        <td class="ps-3 text-nowrap font-monospace small"><?= $item['position'] ?></td>
                                        <td>
                                            <div class="d-flex align-items-center gap-2">
                                                <span class="itflow-rack-chip itflow-rack-tone-<?= $item['tone'] ?>"><i class="fas fa-fw <?= $item['icon'] ?>"></i></span>
                                                <div class="min-w-0">
                                                    <?php if ($item['asset_id']) { ?>
                                                        <a class="fw-semibold<?= $item['asset_archived'] ? ' text-decoration-line-through' : '' ?>" href="asset.php?client_id=<?= $client_id ?>&asset_id=<?= $item['asset_id'] ?>"><?= $item['name'] ?></a>
                                                    <?php } else { ?>
                                                        <span class="fw-semibold"><?= $item['name'] ?></span>
                                                    <?php } ?>
                                                    <div class="small text-body-secondary">
                                                        <?= $item['type_label'] ?>
                                                        <?php if ($item['asset_id'] && $item['label']) { ?>&middot; <?= $item['label'] ?><?php } ?>
                                                        <?php if ($item['notes']) { ?>&middot; <?= $item['notes'] ?><?php } ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="small"><?= $item['make_model'] ?: '<span class="text-body-secondary">-</span>' ?></td>
                                        <td class="small font-monospace"><?= $item['serial'] ?: '<span class="text-body-secondary">-</span>' ?></td>
                                        <td class="small font-monospace"><?= $item['ip'] ?: '<span class="text-body-secondary">-</span>' ?></td>
                                        <td class="small text-nowrap">
                                            <?= ucfirst($item['face']) ?>
                                            <?php if ($item['full_depth']) { ?>
                                                <span class="badge rounded-pill text-bg-light border fw-normal ms-1">Full depth</span>
                                            <?php } ?>
                                        </td>
                                        <td class="small text-end text-nowrap"><?= $item['power'] ? number_format($item['power']) . ' W' : '<span class="text-body-secondary">-</span>' ?></td>
                                        <?php if ($can_edit) { ?>
                                            <td class="text-end pe-3 d-print-none">
                                                <button type="button" class="btn btn-sm btn-light ajax-modal" title="Move or edit"
                                                    data-modal-url="modals/rack/rack_device_edit.php?id=<?= $item['id'] ?>">
                                                    <i class="fas fa-fw fa-pen"></i>
                                                </button>
                                            </td>
                                        <?php } ?>
                                    </tr>
                                <?php } ?>
                            </tbody>
                            <?php if ($power_used) { ?>
                                <tfoot>
                                    <tr>
                                        <td colspan="6" class="ps-3 text-end small text-body-secondary">Total draw</td>
                                        <td class="text-end small fw-semibold text-nowrap"><?= number_format($power_used) ?> W</td>
                                        <?php if ($can_edit) { ?><td class="d-print-none"></td><?php } ?>
                                    </tr>
                                </tfoot>
                            <?php } ?>
                        </table>
                    </div>
                <?php } else { ?>
                    <div class="card-body text-center text-body-secondary py-5">
                        <i class="fas fa-server fa-2x mb-3 opacity-50"></i>
                        <div>Nothing racked yet.</div>
                        <?php if ($can_edit && !$rack_archived_at) { ?>
                            <div class="small">Click an empty U on the elevation, or use <strong>Add Item</strong>.</div>
                        <?php } ?>
                    </div>
                <?php } ?>
            </div>

        </div>

        <!-- Capacity, details, photos, notes -->
        <div class="col-xl-4 col-xxl-3 order-xl-1">

            <div class="card mb-3">
                <div class="card-body">
                    <div class="d-flex align-items-baseline justify-content-between mb-2">
                        <div>
                            <span class="fs-3 fw-semibold"><?= $occupancy['any'] ?></span>
                            <span class="text-body-secondary">of <?= $rack_units ?>U used</span>
                        </div>
                        <span class="fw-semibold text-<?= $usage_tone ?>-emphasis"><?= $used_percent ?>%</span>
                    </div>
                    <div class="progress mb-3" role="progressbar" aria-label="Units used" aria-valuenow="<?= $used_percent ?>" aria-valuemin="0" aria-valuemax="100" style="height: .5rem">
                        <div class="progress-bar bg-<?= $usage_tone ?>" style="width: <?= $used_percent ?>%"></div>
                    </div>
                    <div class="row g-2 text-center itflow-rack-stats">
                        <div class="col-4">
                            <div class="fw-semibold"><?= $occupancy['free_any'] ?>U</div>
                            <div class="small text-body-secondary">Free</div>
                        </div>
                        <div class="col-4">
                            <div class="fw-semibold"><?= $gap['size'] ?>U</div>
                            <div class="small text-body-secondary" title="The tallest full-depth item that still fits">Largest gap</div>
                        </div>
                        <div class="col-4">
                            <div class="fw-semibold"><?= $item_count ?></div>
                            <div class="small text-body-secondary">Items</div>
                        </div>
                    </div>
                    <?php if ($gap['size']) { ?>
                        <div class="small text-body-secondary mt-2 text-center">
                            Room for up to <?= $gap['size'] ?>U at <?= rackPositionLabel($gap['start'], $gap['end']) ?>
                        </div>
                    <?php } ?>

                    <?php if ($rack_power_capacity || $power_used) { ?>
                        <hr>
                        <div class="d-flex align-items-baseline justify-content-between mb-2">
                            <div>
                                <i class="fas fa-fw fa-bolt text-warning me-1"></i>
                                <span class="fw-semibold"><?= number_format($power_used) ?> W</span>
                                <?php if ($rack_power_capacity) { ?>
                                    <span class="text-body-secondary">of <?= number_format($rack_power_capacity) ?> W</span>
                                <?php } ?>
                            </div>
                            <?php if ($rack_power_capacity) { ?>
                                <span class="fw-semibold text-<?= $power_tone ?>-emphasis"><?= $power_percent ?>%</span>
                            <?php } ?>
                        </div>
                        <?php if ($rack_power_capacity) { ?>
                            <div class="progress" role="progressbar" aria-label="Power used" aria-valuenow="<?= min(100, $power_percent) ?>" aria-valuemin="0" aria-valuemax="100" style="height: .5rem">
                                <div class="progress-bar bg-<?= $power_tone ?>" style="width: <?= min(100, $power_percent) ?>%"></div>
                            </div>
                            <?php if ($power_used > $rack_power_capacity) { ?>
                                <div class="small text-danger mt-2"><i class="fas fa-exclamation-triangle me-1"></i>Over budget by <?= number_format($power_used - $rack_power_capacity) ?> W</div>
                            <?php } ?>
                        <?php } else { ?>
                            <div class="small text-body-secondary">Set a power budget on the rack to track headroom.</div>
                        <?php } ?>
                    <?php } ?>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header">
                    <h3 class="card-title mb-0"><i class="fas fa-fw fa-info-circle me-2 text-secondary"></i>Details</h3>
                </div>
                <div class="card-body">
                    <dl class="itflow-rack-dl mb-0">
                        <?php if ($rack_model) { ?>
                            <dt>Model</dt><dd><?= $rack_model ?></dd>
                        <?php } ?>
                        <?php if ($rack_depth) { ?>
                            <dt>Depth</dt><dd><?= $rack_depth ?></dd>
                        <?php } ?>
                        <dt>Numbering</dt><dd>U1 at the <?= $rack_units_descending ? 'top' : 'bottom' ?></dd>
                        <?php if ($location_name) { ?>
                            <dt>Location</dt><dd><a href="locations.php?client_id=<?= $client_id ?>&q=<?= urlencode(html_entity_decode($location_name)) ?>"><?= $location_name ?></a></dd>
                        <?php } ?>
                        <?php if ($rack_physical_location) { ?>
                            <dt>In the building</dt><dd><?= $rack_physical_location ?></dd>
                        <?php } ?>
                        <dt>Front / rear</dt><dd><?= $occupancy['front'] ?>U / <?= $occupancy['rear'] ?>U in use</dd>
                        <dt>Updated</dt><dd title="<?= $rack_updated_at ?>"><?= timeAgo($rack_updated_at) ?></dd>
                    </dl>
                </div>
            </div>

            <?php if ($rack_photo || $rack_photo_rear) { ?>
                <div class="card mb-3">
                    <div class="card-header">
                        <h3 class="card-title mb-0"><i class="fas fa-fw fa-camera me-2 text-secondary"></i>Photos</h3>
                    </div>
                    <div class="card-body">
                        <div class="row g-2">
                            <?php foreach (['Front' => $rack_photo, 'Rear' => $rack_photo_rear] as $photo_side => $photo_file) {
                                if (!$photo_file) {
                                    continue;
                                } ?>
                                <div class="col-6">
                                    <a class="itflow-rack-photo" href="<?= "../uploads/clients/$client_id/$photo_file" ?>" target="_blank" rel="noopener" title="Open full size">
                                        <img src="<?= "../uploads/clients/$client_id/$photo_file" ?>" alt="<?= $photo_side ?> of <?= $rack_name ?>" loading="lazy">
                                        <span><?= $photo_side ?></span>
                                    </a>
                                </div>
                            <?php } ?>
                        </div>
                    </div>
                </div>
            <?php } ?>

            <?php if ($rack_notes) { ?>
                <div class="card mb-3">
                    <div class="card-header">
                        <h3 class="card-title mb-0"><i class="fas fa-fw fa-sticky-note me-2 text-secondary"></i>Notes</h3>
                    </div>
                    <div class="card-body small" style="white-space: pre-line"><?= $rack_notes ?></div>
                </div>
            <?php } ?>

            <?php if ($unracked_assets) { ?>
                <div class="card mb-3 d-print-none">
                    <div class="card-header">
                        <h3 class="card-title mb-0"><i class="fas fa-fw fa-dolly me-2 text-secondary"></i>Not racked yet</h3>
                    </div>
                    <div class="list-group list-group-flush">
                        <?php foreach ($unracked_assets as $unracked) {
                            $unracked_id = intval($unracked['asset_id']);
                            $unracked_style = rackItemStyle($unracked['asset_type'], '', true);
                            ?>
                            <div class="list-group-item d-flex align-items-center gap-2">
                                <span class="itflow-rack-chip itflow-rack-tone-<?= $unracked_style['tone'] ?>"><i class="fas fa-fw <?= $unracked_style['icon'] ?>"></i></span>
                                <div class="min-w-0 flex-grow-1">
                                    <div class="text-truncate fw-semibold small"><?= escapeHtml($unracked['asset_name']) ?></div>
                                    <div class="text-truncate small text-body-secondary"><?= escapeHtml(trim($unracked['asset_make'] . ' ' . $unracked['asset_model'])) ?: escapeHtml($unracked['asset_type']) ?></div>
                                </div>
                                <button type="button" class="btn btn-sm btn-outline-primary ajax-modal flex-shrink-0"
                                    data-modal-url="modals/rack/rack_device_add.php?id=<?= $rack_id ?>&asset_id=<?= $unracked_id ?>">
                                    Rack it
                                </button>
                            </div>
                        <?php } ?>
                    </div>
                </div>
            <?php } ?>

        </div>

    </div>

<?php
}

require_once "../includes/footer.php";
