<?php

/*
 * Merge categories into one - from a category's Merge into..., or Bulk
 * Action on the ticked ones. Every record with one of them gets the one kept
 * instead, and the rest are deleted (categoryMerge()). Categories of one
 * kind only. Posts merge_categories.
 */

require_once '../../includes/modal_header.php';

$merge_ids = array_values(array_unique(array_filter(array_map('intval', (array) ($_GET['category_ids'] ?? [])))));
$merging = [];
foreach ($merge_ids as $merge_id) {
    if ($row = categoryRow($merge_id)) {
        $merging[intval($row['category_id'])] = $row;
    }
}
$kinds = array_unique(array_map(function ($row) {
    return (string) $row['category_type'];
}, $merging));
$config = count($kinds) === 1 ? categoryKind(reset($kinds)) : null;
$usage = $config ? categoryUsage($config['kind']) : [];
$used = function ($category_id) use ($usage) {
    return $usage[intval($category_id)]['all'] ?? 0;
};

// What they can go into: one of them (when there are several, the most used first), or another of the kind not archived
$into = [];
if ($config) {
    if (count($merging) > 1) {
        $into = $merging;
        uasort($into, function ($a, $b) use ($used) {
            return $used($b['category_id']) <=> $used($a['category_id']) ?: strnatcasecmp($a['category_name'], $b['category_name']);
        });
    }
    foreach (categoryRows($config['kind']) as $category_id => $row) {
        if (!isset($merging[$category_id]) && !$row['category_archived_at']) {
            $into[$category_id] = $row;
        }
    }
}
$keep_id = count($merging) > 1 ? intval(array_key_first($into)) : 0;

// What merging does, in words
$one = count($merging) === 1;
$records = 0;
foreach ($merging as $merge_id => $row) {
    $records += $used($merge_id);
}
$merge_text = '';
if ($config) {
    $merge_text = ($records ? 'The ' . number_format($records) . ' ' . ($records === 1 ? 'record' : 'records') . ' with ' . ($one ? 'it get' : 'these get') . ' the ' . $config['one'] . ' you keep instead'
            . ($config['by'] === 'name' ? ' - renamed to it' : '') . ($config['kind'] === 'Expense' ? ', and a month budgeted under more than one ends with one line of their total' : '') . '.'
            : 'Nothing uses ' . ($one ? 'it' : 'these') . ' yet.')
        . ($one ? ' It is then deleted.' : ' The others are then deleted.') . " This can't be undone.";
}

ob_start();

?>

<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fas fa-fw fa-compress-alt me-2"></i>Merge <?= $one ? 'Category' : count($merging) . ' Categories' ?></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>

<?php if (!$merging || !$config) { ?>
    <div class="modal-body text-center text-secondary py-5"><?= $merging ? 'Categories of one kind can be merged - pick expense categories, or asset statuses, and so on.' : 'Those categories are gone - they may have been merged or deleted already.' ?></div>
<?php } elseif (!$into) { ?>
    <div class="modal-body text-center text-secondary py-5">There's no other <?= escapeHtml($config['one']) ?> to merge it into yet.</div>
<?php } else { ?>
    <form action="/admin/post.php" method="post" autocomplete="off">
        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
        <?php foreach ($merging as $merge_id => $row) { ?>
            <input type="hidden" name="category_ids[]" value="<?= $merge_id ?>">
        <?php } ?>

        <div class="modal-body">
            <label class="form-label">Merging</label>
            <div class="d-flex flex-wrap gap-3 mb-3">
                <?php foreach ($merging as $merge_id => $row) { ?>
                    <span class="d-inline-flex align-items-center gap-2"><?= categoryChip($row) ?><span class="small text-secondary"><?= number_format($used($merge_id)) ?> <?= $used($merge_id) === 1 ? 'record' : 'records' ?></span></span>
                <?php } ?>
            </div>

            <div class="mb-3">
                <label class="form-label">Into <strong class="text-danger">*</strong></label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-list-ul"></i></span>
                    <select class="form-select select2" name="into_category_id" data-placeholder="- Keep which <?= escapeHtml($config['one']) ?> -" required>
                        <option value=""></option>
                        <?php foreach ($into as $into_id => $row) { ?>
                            <option value="<?= intval($into_id) ?>"<?= intval($into_id) === $keep_id ? ' selected' : '' ?><?= selectOptionColor(categoryColor($row['category_color']), categoryIcon($row['category_icon'])) ?>><?= escapeHtml($row['category_name']) ?><?= isset($merging[$into_id]) ? ' (one of these)' : '' ?><?= $row['category_archived_at'] ? ' (archived)' : '' ?></option>
                        <?php } ?>
                    </select>
                </div>
            </div>

            <p class="small text-secondary mb-0"><?= escapeHtml($merge_text) ?></p>
        </div>
        <div class="modal-footer">
            <button type="submit" name="merge_categories" class="btn btn-primary text-bold"><i class="fas fa-compress-alt me-2"></i>Merge</button>
            <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fas fa-times me-2"></i>Cancel</button>
        </div>
    </form>
<?php } ?>

<?php
require_once '../../../includes/modal_footer.php';
