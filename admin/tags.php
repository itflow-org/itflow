<?php

/*
 * Admin > Tags - the tags for each kind of record: clients, locations,
 * contacts, credentials, assets, vendors and domains.
 *
 * Each tag shows as it looks on a record, with its description and how many
 * records have it (opening that list filtered by it). A tag can be changed,
 * merged into another of its kind, archived - it stays on its records but
 * isn't offered for new tagging - or deleted. The work is in
 * functions/tags.php; the handlers are admin/post/tag.php.
 */

require_once "includes/inc_all_admin.php";

$kinds = [];
foreach (tagKinds() as $kind_type => $kind_entity) {
    $kinds[$kind_type] = tagEntity($kind_entity);
}
$type = intval($_GET['type'] ?? 1);
$type = isset($kinds[$type]) ? $type : 1;
$kind = $kinds[$type];
$show_archived = isset($_GET['archived']);
$by_use = ($_GET['sort'] ?? '') === 'use';

$usage = tagUsageBreakdown();
$seat_lines = tagSeatLineCounts();

$search_sql = $q !== '' ? "AND (tag_name LIKE '%$q%' OR tag_description LIKE '%$q%')" : '';
$tags = [];
$sql = mysqli_query($mysqli, "SELECT tag_id, tag_name, tag_description, tag_type, tag_color, tag_icon, tag_created_at, tag_archived_at FROM tags
    WHERE tag_type = $type AND tag_archived_at IS " . ($show_archived ? 'NOT NULL' : 'NULL') . " $search_sql ORDER BY tag_name ASC");
while ($row = mysqli_fetch_assoc($sql)) {
    $row['used'] = $usage[intval($row['tag_id'])] ?? ['all' => 0, 'archived' => 0];
    $row['lines'] = $seat_lines[intval($row['tag_id'])] ?? 0;
    $tags[] = $row;
}
if ($by_use) {
    // Most used first; the query put them in name order, and usort keeps that order for equal counts
    usort($tags, function ($a, $b) {
        return $b['used']['all'] <=> $a['used']['all'];
    });
}

// Current tags of each kind for the nav, and this kind's archived ones for the tab
$kind_counts = [];
$archived_count = 0;
$sql = mysqli_query($mysqli, "SELECT tag_type, SUM(tag_archived_at IS NULL), SUM(tag_archived_at IS NOT NULL) FROM tags GROUP BY tag_type");
while ($row = mysqli_fetch_row($sql)) {
    $kind_counts[intval($row[0])] = intval($row[1]);
    if (intval($row[0]) === $type) {
        $archived_count = intval($row[2]);
    }
}

// This page with some of its query changed - null drops a key
$page_url = function (array $change = []) use ($type, $show_archived, $by_use, $q) {
    $query = array_merge(['type' => $type, 'q' => $q !== '' ? stripslashes($q) : null, 'sort' => $by_use ? 'use' : null, 'archived' => $show_archived ? 1 : null], $change);
    return 'tags.php?' . http_build_query(array_filter($query, function ($v) {
        return $v !== null;
    }));
};

// "12 assets" / "1 asset"
$records_text = function ($n) use ($kind) {
    return number_format($n) . ' ' . ($n === 1 ? strtolower($kind['title']) : $kind['plural']);
};
$kind_word = strtolower($kind['title']);
$csrf = $_SESSION['csrf_token'];

?>

<div class="card">
    <div class="card-header bg-dark py-2">
        <h3 class="card-title mt-2"><i class="fas fa-fw fa-tags me-2"></i>Tags</h3>
        <div class="card-tools">
            <button type="button" class="btn btn-primary ajax-modal" data-modal-url="modals/tag/tag_add.php?type=<?= $type ?>"><i class="fas fa-plus me-2"></i>New <?= $kind['title'] ?> Tag</button>
        </div>
    </div>

    <div class="row g-0">
        <!-- Kinds -->
        <div class="col-md-3 col-xl-2 border-end">
            <nav class="nav nav-pills flex-nowrap flex-md-column overflow-auto p-2 gap-1 itflow-tag-kinds" aria-label="Kinds of tag">
                <?php foreach ($kinds as $kind_type => $kind_config) { ?>
                    <a class="nav-link d-flex align-items-center gap-2 text-nowrap<?= $kind_type === $type ? ' active' : '' ?>" href="tags.php?type=<?= $kind_type ?>"<?= $kind_type === $type ? ' aria-current="page"' : '' ?>>
                        <i class="fas fa-fw <?= $kind_config['icon'] ?>"></i><?= ucfirst($kind_config['plural']) ?>
                        <span class="badge rounded-pill ms-auto <?= $kind_type === $type ? 'text-bg-light' : 'text-bg-secondary' ?>"><?= $kind_counts[$kind_type] ?? 0 ?></span>
                    </a>
                <?php } ?>
            </nav>
        </div>

        <!-- This kind's tags -->
        <div class="col-md-9 col-xl-10">
            <div class="p-2 border-bottom">
                <form autocomplete="off" class="d-flex flex-wrap align-items-center gap-2">
                    <input type="hidden" name="type" value="<?= $type ?>">
                    <?php if ($show_archived) { ?><input type="hidden" name="archived" value="1"><?php } ?>
                    <?php if ($by_use) { ?><input type="hidden" name="sort" value="use"><?php } ?>
                    <div class="input-group input-group-sm w-auto">
                        <input type="search" class="form-control" name="q" value="<?= escapeHtml(stripslashes($q)) ?>" placeholder="Search <?= $kind_word ?> tags">
                        <button class="btn btn-primary" title="Search"><i class="fa fa-search"></i></button>
                    </div>
                    <div class="btn-group btn-group-sm" role="group" aria-label="Sort">
                        <a class="btn btn-outline-secondary<?= $by_use ? '' : ' active' ?>" href="<?= escapeHtml($page_url(['sort' => null])) ?>" title="A to Z"><i class="fas fa-sort-alpha-down me-1"></i>Name</a>
                        <a class="btn btn-outline-secondary<?= $by_use ? ' active' : '' ?>" href="<?= escapeHtml($page_url(['sort' => 'use'])) ?>" title="The tags on the most records first"><i class="fas fa-sort-amount-down me-1"></i>Most used</a>
                    </div>
                    <div class="btn-group btn-group-sm me-auto" role="group" aria-label="Current or archived">
                        <a class="btn btn-outline-secondary<?= $show_archived ? '' : ' active' ?>" href="<?= escapeHtml($page_url(['archived' => null])) ?>">Current</a>
                        <a class="btn btn-outline-secondary<?= $show_archived ? ' active' : '' ?>" href="<?= escapeHtml($page_url(['archived' => 1])) ?>">Archived <span class="badge text-bg-secondary ms-1"><?= $archived_count ?></span></a>
                    </div>
                    <div class="dropdown" id="bulkActionButton" hidden>
                        <button class="btn btn-secondary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown">
                            <i class="fas fa-fw fa-layer-group me-1"></i>Bulk Action (<span id="selectedCount">0</span>)
                        </button>
                        <div class="dropdown-menu dropdown-menu-end">
                            <a class="dropdown-item ajax-modal" href="#" data-modal-url="modals/tag/tag_merge.php" data-bulk="true">
                                <i class="fas fa-fw fa-compress-alt text-secondary me-2"></i>Merge into one...
                            </a>
                            <?php if ($show_archived) { ?>
                                <button class="dropdown-item" type="submit" form="bulkActions" name="bulk_restore_tags">
                                    <i class="fas fa-fw fa-redo text-secondary me-2"></i>Restore
                                </button>
                            <?php } else { ?>
                                <button class="dropdown-item" type="submit" form="bulkActions" name="bulk_archive_tags">
                                    <i class="fas fa-fw fa-archive text-secondary me-2"></i>Archive
                                </button>
                            <?php } ?>
                            <div class="dropdown-divider"></div>
                            <button class="dropdown-item text-danger confirm-link" type="submit" form="bulkActions" name="bulk_delete_tags"
                                data-confirm-title="Delete these tags?"
                                data-confirm-text="They come off every <?= $kind_word ?> that has them. Any that a recurring invoice line counts by are kept."
                                data-confirm-button="Delete">
                                <i class="fas fa-fw fa-trash me-2"></i>Delete
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            <form id="bulkActions" action="post.php" method="post">
                <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                <div class="table-responsive-sm">
                    <table class="table table-borderless table-hover align-middle mb-0">
                        <?php if ($tags) { ?>
                            <thead class="text-dark">
                            <tr>
                                <th class="ps-3" style="width: 1%">
                                    <div class="form-check mb-0">
                                        <input class="form-check-input" id="selectAllCheckbox" type="checkbox" title="Select all">
                                    </div>
                                </th>
                                <th>Tag</th>
                                <th class="d-none d-sm-table-cell">On</th>
                                <th class="text-center" style="width: 1%">Action</th>
                            </tr>
                            </thead>
                        <?php } ?>
                        <tbody>
                        <?php foreach ($tags as $tag) {
                            $tag_id = intval($tag['tag_id']);
                            $name = escapeHtml($tag['tag_name']);
                            $used = $tag['used']['all'];
                            $used_archived = $tag['used']['archived'];
                            $lines = $tag['lines'];
                            $edit_url = "modals/tag/tag_edit.php?id=$tag_id";
                            $list_url = tagListUrl(tagKinds()[$type], $tag);
                            $used_html = $used
                                ? '<a href="' . escapeHtml($list_url) . '" title="Show the ' . $kind['plural'] . ' tagged ' . $name . '">' . $records_text($used) . '</a>'
                                    . ($used_archived ? ' <span class="text-secondary">(' . number_format($used_archived) . ' archived)</span>' : '')
                                : '<span class="text-secondary">' . ($tag['tag_archived_at'] ? 'Nothing has it' : 'Nothing yet') . '</span>';
                            $lines_html = $lines
                                ? '<div class="small text-secondary" title="Recurring invoice lines that count the ' . ($type === 3 ? 'users' : 'devices') . ' with this tag"><i class="fas fa-fw fa-redo-alt me-1"></i>Counted on ' . $lines . ' recurring invoice ' . ($lines === 1 ? 'line' : 'lines') . '</div>'
                                : '';
                            $delete_text = $used
                                ? 'It comes off the ' . $records_text($used) . ' that ' . ($used === 1 ? 'has' : 'have') . ' it. To keep it on them but stop offering it, archive it instead.'
                                : 'Nothing has it, so no record changes.';
                            ?>
                            <tr>
                                <td class="ps-3">
                                    <div class="form-check mb-0">
                                        <input class="form-check-input bulk-select" type="checkbox" name="tag_ids[]" value="<?= $tag_id ?>" aria-label="Select <?= $name ?>">
                                    </div>
                                </td>
                                <td>
                                    <a class="ajax-modal text-decoration-none" href="#" data-modal-url="<?= $edit_url ?>" title="Edit <?= $name ?>"><?= tagPill($tag) ?></a>
                                    <?php if (trim((string) $tag['tag_description']) !== '') { ?>
                                        <div class="small text-secondary mt-1"><?= escapeHtml($tag['tag_description']) ?></div>
                                    <?php } ?>
                                    <div class="small d-sm-none mt-1"><?= $used_html ?><?= $lines_html ?></div>
                                    <?php if ($tag['tag_archived_at']) { ?>
                                        <div class="small text-secondary"><i class="fas fa-fw fa-archive me-1"></i>Archived <?= date('M j, Y', strtotime($tag['tag_archived_at'])) ?></div>
                                    <?php } ?>
                                </td>
                                <td class="d-none d-sm-table-cell small">
                                    <?= $used_html ?>
                                    <?= $lines_html ?>
                                </td>
                                <td>
                                    <div class="dropdown dropstart text-center">
                                        <button class="btn btn-secondary btn-sm" title="More actions" type="button" data-bs-toggle="dropdown"><i class="fas fa-ellipsis-h"></i></button>
                                        <div class="dropdown-menu">
                                            <a class="dropdown-item ajax-modal" href="#" data-modal-url="<?= $edit_url ?>"><i class="fas fa-fw fa-edit me-2"></i>Edit</a>
                                            <a class="dropdown-item ajax-modal" href="#" data-modal-url="modals/tag/tag_merge.php?tag_ids[]=<?= $tag_id ?>"><i class="fas fa-fw fa-compress-alt me-2"></i>Merge into...</a>
                                            <?php if ($used) { ?>
                                                <a class="dropdown-item" href="<?= escapeHtml($list_url) ?>"><i class="fas fa-fw <?= $kind['icon'] ?> me-2"></i>Show the <?= $kind['plural'] ?></a>
                                            <?php } ?>
                                            <div class="dropdown-divider"></div>
                                            <?php if ($tag['tag_archived_at']) { ?>
                                                <a class="dropdown-item" href="post.php?restore_tag=<?= $tag_id ?>&csrf_token=<?= $csrf ?>"><i class="fas fa-fw fa-redo me-2"></i>Restore</a>
                                            <?php } else { ?>
                                                <a class="dropdown-item" href="post.php?archive_tag=<?= $tag_id ?>&csrf_token=<?= $csrf ?>" title="It stays on its <?= $kind['plural'] ?>, but isn't offered for tagging or in the filters"><i class="fas fa-fw fa-archive me-2"></i>Archive</a>
                                            <?php } ?>
                                            <?php if ($lines) { ?>
                                                <span class="dropdown-item disabled" title="Recurring invoice lines count by it - archive it, or merge it into another tag"><i class="fas fa-fw fa-trash me-2"></i>Delete</span>
                                            <?php } else { ?>
                                                <a class="dropdown-item text-danger text-bold confirm-link" href="post.php?delete_tag=<?= $tag_id ?>&csrf_token=<?= $csrf ?>"
                                                    data-confirm-title="Delete <?= $name ?>?" data-confirm-text="<?= escapeHtml($delete_text) ?>" data-confirm-button="Delete"><i class="fas fa-fw fa-trash me-2"></i>Delete</a>
                                            <?php } ?>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        <?php } ?>
                        </tbody>
                    </table>
                </div>
            </form>

            <?php if (!$tags) { ?>
                <?= emptyStateHtml([
                    'thing' => "$kind_word tags",
                    'title' => $show_archived ? "No archived $kind_word tags" : "No $kind_word tags yet",
                    'text' => $show_archived ? 'An archived tag stays on its records, but isn\'t offered for tagging or in the filters.' : 'Tags sort and filter ' . $kind['plural'] . ' - "VIP", "Managed", "Retiring soon".',
                    'icon' => 'fa-tags',
                    'filtered' => $q !== '',
                    'clear_url' => $page_url(['q' => null]),
                    'actions' => $show_archived ? [] : [['label' => "New {$kind['title']} Tag", 'modal' => "modals/tag/tag_add.php?type=$type"]],
                ]) ?>
            <?php } ?>
        </div>
    </div>
</div>

<script src="../js/bulk_actions.js"></script>

<?php
require_once "../includes/footer.php";
