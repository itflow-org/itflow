<?php
/*
 * One face of a rack, drawn to scale - included by agent/rack.php once for the
 * front and once for the rear. Expects:
 *
 *   $elevation_face        'front' | 'rear'
 *   $elevation_items       rackUnitView() items for the whole rack
 *   $elevation_rack_units  rack height in U
 *   $elevation_descending  1 when U1 is at the top
 *   $elevation_can_edit    whether empty U and items open the add/edit modals
 *   $rack_id, $client_id
 *
 * A CSS grid with one row per U. Items on this face are placed by grid-row;
 * a full-depth item mounted on the OTHER face shows here as a hatched ghost,
 * because it fills this face too. Every U nothing fills is an "add here" slot.
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

        <div class="itflow-rack-bay">
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
                }
                if ($block['asset_archived']) {
                    $block_classes .= ' is-archived';
                }
                $block_title = $block['name'] . ' - ' . html_entity_decode($block['position']) . ($block['meta'] ? " - {$block['meta']}" : '');
                ?>
                <div class="<?= $block_classes ?>" style="grid-row: <?= $block_row ?> / span <?= $block['height'] ?>">

                    <?php if ($block['ghost']) { ?>
                        <div class="itflow-rack-item-body" title="<?= $block_title ?> - mounted on the <?= $elevation_other_face ?>, full depth">
                            <span class="itflow-rack-item-text">
                                <span class="itflow-rack-item-name"><?= $block['name'] ?></span>
                                <span class="itflow-rack-item-meta">Full depth from the <?= $elevation_other_face ?></span>
                            </span>
                        </div>
                    <?php } else {
                        // An asset opens its asset page; anything else opens its editor
                        if ($block['asset_id']) {
                            $block_open = '<a class="itflow-rack-item-body" href="asset.php?client_id=' . $client_id . '&asset_id=' . $block['asset_id'] . '" title="' . $block_title . '">';
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
