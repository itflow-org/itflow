<?php

/*
 * Merge tags into one - from a tag's Merge into..., or Bulk Action on the
 * ticked tags. Every record with one of them gets the one kept instead,
 * recurring invoice lines counting by them count by it, and the rest are
 * deleted (tagMerge()). Tags of one kind only. Posts merge_tags.
 */

require_once '../../includes/modal_header.php';

$merge_ids = array_values(array_unique(array_filter(array_map('intval', (array) ($_GET['tag_ids'] ?? [])))));
$merging = [];
foreach ($merge_ids as $merge_id) {
    if ($row = tagRow($merge_id)) {
        $merging[intval($row['tag_id'])] = $row;
    }
}
$types = array_unique(array_map(function ($row) {
    return intval($row['tag_type']);
}, $merging));
$type = count($types) === 1 ? reset($types) : 0;
$kind = $type ? tagEntityByType($type) : null;
$usage = tagUsageCounts();
$seat_lines = tagSeatLineCounts();

// What they can go into: one of them (when there are several), or another of the kind not archived
$into = [];
if ($kind) {
    if (count($merging) > 1) {
        $into = $merging;
        uasort($into, function ($a, $b) use ($usage) {
            return ($usage[intval($b['tag_id'])] ?? 0) <=> ($usage[intval($a['tag_id'])] ?? 0) ?: strcasecmp($a['tag_name'], $b['tag_name']);
        });
    }
    foreach (tagOptionsFor($kind['entity']) as $row) {
        if (!isset($merging[intval($row['tag_id'])])) {
            $into[intval($row['tag_id'])] = $row;
        }
    }
}
$keep_id = count($merging) > 1 ? intval(array_key_first($into)) : 0;

// What merging does, in words
$records = $lines = 0;
if ($kind) {
    // Each record once, though it may have more than one of them
    $records = intval(mysqli_fetch_row(mysqli_query($mysqli, "SELECT COUNT(DISTINCT {$kind['link_record']}) FROM {$kind['link_table']}
        WHERE {$kind['link_tag']} IN (" . implode(',', array_keys($merging)) . ")"))[0]);
}
foreach ($merging as $merge_id => $row) {
    $lines += $seat_lines[$merge_id] ?? 0;
}
$one = count($merging) === 1;
$merge_text = '';
if ($kind) {
    $merge_text = ($records ? 'The ' . number_format($records) . ' ' . ($records === 1 ? strtolower($kind['title']) . ' with ' . ($one ? 'it gets' : 'one of these gets') : $kind['plural'] . ' with ' . ($one ? 'it get' : 'these get')) . ' the tag you keep instead.'
            : 'Nothing has ' . ($one ? 'it' : 'these') . ' yet.')
        . ($lines ? ' ' . ($lines === 1 ? 'The recurring invoice line counting by ' . ($one ? 'it counts' : 'them counts') : "The $lines recurring invoice lines counting by " . ($one ? 'it count' : 'them count')) . ' by the kept tag from now on.' : '')
        . ($one ? ' It is then deleted.' : ' The others are then deleted.') . " This can't be undone.";
}

ob_start();

?>

<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fas fa-fw fa-compress-alt me-2"></i>Merge <?= count($merging) === 1 ? 'Tag' : count($merging) . ' Tags' ?></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>

<?php if (!$merging || !$kind) { ?>
    <div class="modal-body text-center text-secondary py-5"><?= $merging ? 'Tags of one kind can be merged - pick client tags, or asset tags, and so on.' : 'Those tags are gone - they may have been merged or deleted already.' ?></div>
<?php } elseif (!$into) { ?>
    <div class="modal-body text-center text-secondary py-5">There's no other <?= strtolower($kind['title']) ?> tag to merge it into yet.</div>
<?php } else { ?>
    <form action="post.php" method="post" autocomplete="off">
        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
        <?php foreach ($merging as $merge_id => $row) { ?>
            <input type="hidden" name="tag_ids[]" value="<?= $merge_id ?>">
        <?php } ?>

        <div class="modal-body">
            <label class="form-label">Merging</label>
            <div class="d-flex flex-wrap gap-2 mb-3">
                <?php foreach ($merging as $merge_id => $row) { ?>
                    <span class="d-inline-flex align-items-center gap-1"><?= tagPill($row) ?><span class="small text-secondary"><?= number_format($usage[$merge_id] ?? 0) ?> <?= ($usage[$merge_id] ?? 0) === 1 ? strtolower($kind['title']) : $kind['plural'] ?></span></span>
                <?php } ?>
            </div>

            <div class="mb-3">
                <label class="form-label">Into <strong class="text-danger">*</strong></label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-tag"></i></span>
                    <select class="form-select select2" name="into_tag_id" data-placeholder="- Keep which tag -" required>
                        <option value=""></option>
                        <?php foreach ($into as $into_id => $row) { ?>
                            <option value="<?= intval($into_id) ?>"<?= intval($into_id) === $keep_id ? ' selected' : '' ?><?= selectOptionColor(tagColor($row['tag_color']), tagIcon($row['tag_icon'])) ?>><?= escapeHtml($row['tag_name']) ?><?= isset($merging[$into_id]) ? ' (one of these)' : '' ?><?= $row['tag_archived_at'] ? ' (archived)' : '' ?></option>
                        <?php } ?>
                    </select>
                </div>
            </div>

            <p class="small text-secondary mb-0"><?= $merge_text ?></p>
        </div>
        <div class="modal-footer">
            <button type="submit" name="merge_tags" class="btn btn-primary text-bold"><i class="fas fa-compress-alt me-2"></i>Merge</button>
            <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fas fa-times me-2"></i>Cancel</button>
        </div>
    </form>
<?php } ?>

<?php
require_once '../../../includes/modal_footer.php';
