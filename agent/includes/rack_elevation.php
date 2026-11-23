<?php
/*
 * One face of a rack, drawn to scale - included by agent/rack.php once for the
 * front and once for the rear. Expects:
 *
 *   $elevation_face        'front' | 'rear'
 *   $elevation_items       rackUnitView() items for the whole rack, nested (rackNestItems())
 *   $elevation_rack_units  rack height in U
 *   $elevation_descending  1 when U1 is at the top
 *   $elevation_can_edit    whether empty U and items open the add/edit modals
 *   $rack_id, $client_id
 *
 * A CSS grid with one row per U. Items on this face are placed by grid-row;
 * a full-depth item mounted on the OTHER face shows here as a hatched ghost,
 * because it fills this face too. Every U nothing fills is an "add here" slot.
 * When it can be edited, the items on this face drag to another position and
 * the bay takes what is dropped on it (agent/js/rack_drag.js) - the data-rack-*
 * attributes are what that script reads.
 *
 * A holder draws what is in it side by side, in rows when it wraps
 * (rackHolderCells()): each device opens like any other item and drags to an
 * empty slot, and each empty slot is an "add here" of its own. The holder
 * itself is its tab at the left - that opens it, and drags it with everything
 * in it.
 *
 * Should not be accessed directly.
 */

isset($mysqli) || die("Direct file access is not allowed");

$elevation_taken = [];
$elevation_blocks = [];
foreach ($elevation_items as $elevation_item) {
    if (!rackUnitOnFace($elevation_item['face'], $elevation_item['full_depth'], $elevation_face)) {
        continue;
    }
    $elevation_blocks[] = $elevation_item + ['ghost' => $elevation_item['face'] !== $elevation_face];
    for ($u = $elevation_item['start']; $u <= $elevation_item['end']; $u++) {
        $elevation_taken[$u] = true;
    }
}

$elevation_other_face = $elevation_face === 'front' ? 'rear' : 'front';

// Rail numbers top to bottom, in the order the rack is drawn
$elevation_rail = $elevation_descending ? range(1, $elevation_rack_units) : range($elevation_rack_units, 1);

?>
<div class="itflow-rack" style="--rack-rows: <?= $elevation_rack_units ?>" data-rack-face="<?= $elevation_face ?>">
    <div class="itflow-rack-cap"><?= ucfirst($elevation_face) ?></div>
    <div class="itflow-rack-frame">

        <div class="itflow-rack-rail" aria-hidden="true">
            <?php foreach ($elevation_rail as $u) { ?>
                <span><?= $u ?></span>
            <?php } ?>
        </div>

        <div class="itflow-rack-bay"<?php if ($elevation_can_edit) { ?> data-rack-drop data-rack-face="<?= $elevation_face ?>" data-rack-units="<?= $elevation_rack_units ?>" data-rack-descending="<?= $elevation_descending ? 1 : 0 ?>"<?php } ?>>
            <?php
            // Empty U first so the items paint over the grid lines
            foreach ($elevation_rail as $u) {
                if (isset($elevation_taken[$u])) {
                    continue;
                }
                $slot_row = rackGridRow($u, $u, $elevation_rack_units, $elevation_descending);
                if ($elevation_can_edit) { ?>
                    <button type="button" class="itflow-rack-slot ajax-modal" style="grid-row: <?= $slot_row ?>"
                        data-modal-url="modals/rack/rack_device_add.php?id=<?= $rack_id ?>&u=<?= $u ?>&face=<?= $elevation_face ?>"
                        title="Add something at U<?= $u ?> (<?= $elevation_face ?>)">
                        <span><i class="fas fa-plus me-1"></i>U<?= $u ?></span>
                    </button>
                <?php } else { ?>
                    <span class="itflow-rack-slot" style="grid-row: <?= $slot_row ?>"></span>
                <?php }
            }

            foreach ($elevation_blocks as $block) {
                $block_row = rackGridRow($block['start'], $block['end'], $elevation_rack_units, $elevation_descending);
                $block_classes = "itflow-rack-item itflow-rack-tone-{$block['tone']} itflow-rack-h" . min(3, $block['height']);
                if ($block['ghost']) {
                    $block_classes .= ' is-ghost';
                } elseif ($block['slots']) {
                    $block_classes .= ' is-holder';
                }
                if ($block['asset_archived']) {
                    $block_classes .= ' is-archived';
                }
                $block_title = $block['name'] . ' - ' . html_entity_decode($block['position']) . ($block['meta'] ? " - {$block['meta']}" : '');
                $block_drags = $elevation_can_edit && !$block['ghost'];
                if ($block_drags) {
                    $block_title .= ' - drag to move it';
                }
                ?>
                <div class="<?= $block_classes ?>" style="grid-row: <?= $block_row ?> / span <?= $block['height'] ?>"
                    data-rack-unit="<?= $block['id'] ?>" data-rack-start="<?= $block['start'] ?>" data-rack-end="<?= $block['end'] ?>" data-rack-name="<?= $block['name'] ?>"
                    <?php if ($block_drags) { ?>draggable="true" data-rack-drag="unit" data-rack-id="<?= $block['id'] ?>" data-rack-height="<?= $block['height'] ?>" data-rack-full="<?= $block['full_depth'] ? 1 : 0 ?>" data-rack-from="<?= $block['face'] ?>"<?= $block['slots'] ? ' data-rack-holds="1"' : '' ?><?php } ?>>

                    <?php if ($block['slots'] && !$block['ghost']) {
                        $holder = rackHolderCells($block, $elevation_descending);
                        $holder_title = $block['name'] . ' - ' . html_entity_decode($block['position']) . ($block['meta'] ? " - {$block['meta']}" : '') . " - holds {$block['slots']} across" . ($block_drags ? ' - drag to move it with what is in it' : '');
                        // The tab opens the holder's editor - its asset, if it is one, is in the list below
                        if ($elevation_can_edit) {
                            $tab_open = '<button type="button" class="itflow-rack-holder-tab ajax-modal" data-modal-url="modals/rack/rack_device_edit.php?id=' . $block['id'] . '" title="' . $holder_title . '">';
                            $tab_close = '</button>';
                        } elseif ($block['asset_id']) {
                            $tab_open = '<a class="itflow-rack-holder-tab" href="asset.php?client_id=' . $client_id . '&asset_id=' . $block['asset_id'] . '" title="' . $holder_title . '" draggable="false">';
                            $tab_close = '</a>';
                        } else {
                            $tab_open = '<span class="itflow-rack-holder-tab" title="' . $holder_title . '">';
                            $tab_close = '</span>';
                        }
                        ?>
                        <?= $tab_open ?><i class="fas fa-fw <?= $block['icon'] ?>"></i><span class="visually-hidden"><?= $block['name'] ?></span><?= $tab_close ?>
                        <div class="itflow-rack-holder-slots" style="grid-template-columns: repeat(<?= $holder['columns'] ?>, minmax(0, 1fr)); grid-template-rows: repeat(<?= $holder['rows'] ?>, minmax(0, 1fr))">
                            <?php foreach ($holder['cells'] as $cell) {
                                $cell_place = "grid-column: {$cell['column']}; grid-row: {$cell['row']}";
                                $child = $cell['item'];
                                if (!$child) {
                                    if ($elevation_can_edit) { ?>
                                        <button type="button" class="itflow-rack-cell is-empty ajax-modal" style="<?= $cell_place ?>" data-rack-cell data-rack-holder="<?= $block['id'] ?>" data-rack-slot="<?= $cell['slot'] ?>"
                                            data-modal-url="modals/rack/rack_device_add.php?id=<?= $rack_id ?>&holder=<?= $block['id'] ?>&slot=<?= $cell['slot'] ?>"
                                            title="Add something to slot <?= $cell['slot'] ?> of <?= $block['name'] ?>">
                                            <i class="fas fa-plus"></i>
                                        </button>
                                    <?php } else { ?>
                                        <span class="itflow-rack-cell is-empty" style="<?= $cell_place ?>"></span>
                                    <?php }
                                    continue;
                                }
                                $child_title = $child['name'] . " - slot {$cell['slot']} of {$block['name']}" . ($child['meta'] ? " - {$child['meta']}" : '') . ($elevation_can_edit ? ' - drag to another slot' : '');
                                if ($child['asset_id']) {
                                    $child_open = '<a class="itflow-rack-cell-body" href="asset.php?client_id=' . $client_id . '&asset_id=' . $child['asset_id'] . '" title="' . $child_title . '" draggable="false">';
                                    $child_close = '</a>';
                                } elseif ($elevation_can_edit) {
                                    $child_open = '<button type="button" class="itflow-rack-cell-body ajax-modal" data-modal-url="modals/rack/rack_device_edit.php?id=' . $child['id'] . '" title="' . $child_title . '">';
                                    $child_close = '</button>';
                                } else {
                                    $child_open = '<div class="itflow-rack-cell-body" title="' . $child_title . '">';
                                    $child_close = '</div>';
                                }
                                ?>
                                <div class="itflow-rack-cell itflow-rack-tone-<?= $child['tone'] ?><?= $child['asset_archived'] ? ' is-archived' : '' ?>" style="<?= $cell_place ?>"
                                    <?php if ($elevation_can_edit) { ?>draggable="true" data-rack-drag="unit" data-rack-child="1" data-rack-id="<?= $child['id'] ?>" data-rack-holder="<?= $block['id'] ?>" data-rack-slot="<?= $cell['slot'] ?>" data-rack-height="<?= $block['height'] ?>" data-rack-full="<?= $block['full_depth'] ? 1 : 0 ?>" data-rack-from="<?= $block['face'] ?>" data-rack-start="<?= $block['start'] ?>"<?php } ?>>
                                    <?= $child_open ?>
                                        <span class="itflow-rack-cell-name"><?= $child['name'] ?></span>
                                        <?php if ($child['meta'] && $block['height'] / $holder['rows'] >= 2) { ?>
                                            <span class="itflow-rack-cell-meta"><?= $child['meta'] ?></span>
                                        <?php } ?>
                                    <?= $child_close ?>
                                    <?php if ($elevation_can_edit && $child['asset_id']) { ?>
                                        <button type="button" class="itflow-rack-cell-edit ajax-modal" title="Move or edit"
                                            data-modal-url="modals/rack/rack_device_edit.php?id=<?= $child['id'] ?>">
                                            <i class="fas fa-pen"></i>
                                        </button>
                                    <?php } ?>
                                </div>
                            <?php } ?>
                        </div>
                    <?php } elseif ($block['ghost']) { ?>
                        <div class="itflow-rack-item-body" title="<?= $block_title ?> - mounted on the <?= $elevation_other_face ?>, full depth">
                            <span class="itflow-rack-item-text">
                                <span class="itflow-rack-item-name"><?= $block['name'] ?></span>
                                <span class="itflow-rack-item-meta">Full depth from the <?= $elevation_other_face ?></span>
                            </span>
                        </div>
                    <?php } else {
                        // An asset opens its asset page; anything else opens its editor
                        if ($block['asset_id']) {
                            $block_open = '<a class="itflow-rack-item-body" href="asset.php?client_id=' . $client_id . '&asset_id=' . $block['asset_id'] . '" title="' . $block_title . '" draggable="false">';
                            $block_close = '</a>';
                        } elseif ($elevation_can_edit) {
                            $block_open = '<button type="button" class="itflow-rack-item-body ajax-modal" data-modal-url="modals/rack/rack_device_edit.php?id=' . $block['id'] . '" title="' . $block_title . '">';
                            $block_close = '</button>';
                        } else {
                            $block_open = '<div class="itflow-rack-item-body" title="' . $block_title . '">';
                            $block_close = '</div>';
                        }
                        ?>
                        <?= $block_open ?>
                            <span class="itflow-rack-item-icon"><i class="fas fa-fw <?= $block['icon'] ?>"></i></span>
                            <span class="itflow-rack-item-text">
                                <span class="itflow-rack-item-name"><?= $block['name'] ?></span>
                                <?php if ($block['meta'] || $block['ip']) { ?>
                                    <span class="itflow-rack-item-meta">
                                        <?= $block['meta'] ?>
                                        <?php if ($block['ip'] && $block['height'] > 1) { ?>
                                            <span class="font-monospace"><?= $block['meta'] ? '&middot; ' : '' ?><?= $block['ip'] ?></span>
                                        <?php } ?>
                                    </span>
                                <?php } ?>
                            </span>
                            <span class="itflow-rack-item-pos"><?= $block['position'] ?></span>
                        <?= $block_close ?>
                        <?php if ($elevation_can_edit && $block['asset_id']) { ?>
                            <button type="button" class="itflow-rack-item-edit ajax-modal" title="Move or edit"
                                data-modal-url="modals/rack/rack_device_edit.php?id=<?= $block['id'] ?>">
                                <i class="fas fa-pen"></i>
                            </button>
                        <?php } ?>
                    <?php } ?>

                </div>
            <?php } ?>
        </div>

        <div class="itflow-rack-rail" aria-hidden="true">
            <?php foreach ($elevation_rail as $u) { ?>
                <span><?= $u ?></span>
            <?php } ?>
        </div>

    </div>
</div>
