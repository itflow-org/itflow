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

// Default Column Sortby Filter
$sort = "tag_name";
$order = "ASC";

require_once "includes/inc_all_admin.php";

$kinds = [];
foreach (tagKinds() as $kind_type => $kind_entity) {
    $kinds[$kind_type] = tagEntity($kind_entity);
}
$type = intval($_GET['type'] ?? 1);
$type = isset($kinds[$type]) ? $type : 1;
$kind = $kinds[$type];
// ?archived=1 is how older links asked for the archived ones
$show_archived = ($_GET['show'] ?? '') === 'archived' || !empty($_GET['archived']);

// Only these sort - by name, and by how many records have each, which is worked out here rather than in the query
$sortable_columns = ['tag_name' => true, 'used' => true];
if (!isset($sortable_columns[$sort])) {
    $sort = 'tag_name';
}

$usage = tagUsageBreakdown();
$seat_lines = tagSeatLineCounts();

$search = "tag_type = $type AND (tag_name LIKE '%$q%' OR tag_description LIKE '%$q%')";
$tags = [];
$sql = mysqli_query($mysqli, "SELECT tag_id, tag_name, tag_description, tag_type, tag_color, tag_icon, tag_created_at, tag_archived_at FROM tags
    WHERE $search AND tag_archived_at IS " . ($show_archived ? 'NOT NULL' : 'NULL') . " ORDER BY tag_name $order, tag_id ASC");
while ($row = mysqli_fetch_assoc($sql)) {
    $row['used'] = $usage[intval($row['tag_id'])] ?? ['all' => 0, 'archived' => 0];
    $row['lines'] = $seat_lines[intval($row['tag_id'])] ?? 0;
    $tags[] = $row;
}
if ($sort === 'used') {
    // By use, ties A to Z - usort keeps the order it is given for equal counts, so the query's name order goes back to A to Z first
    if ($order === 'DESC') {
        $tags = array_reverse($tags);
    }
    $direction = $order === 'DESC' ? -1 : 1;
    usort($tags, function ($a, $b) use ($direction) {
        return $direction * ($a['used']['all'] <=> $b['used']['all']);
    });
}

// A page of them
$num_rows = [count($tags)];
$tags = array_slice($tags, $record_from, $record_to);

$counts = mysqli_fetch_row(mysqli_query($mysqli, "SELECT COALESCE(SUM(tag_archived_at IS NULL), 0), COALESCE(SUM(tag_archived_at IS NOT NULL), 0) FROM tags WHERE $search"));

// Current tags of each kind for the nav
$kind_counts = [];
$sql = mysqli_query($mysqli, "SELECT tag_type, SUM(tag_archived_at IS NULL) FROM tags GROUP BY tag_type");
while ($row = mysqli_fetch_row($sql)) {
    $kind_counts[intval($row[0])] = intval($row[1]);
}

$sort_link = function ($column, $label) use ($url_query_strings_sort, $sort, $disp, $order_icon) {
    return '<a class="text-reset text-decoration-none" href="?' . escapeHtml($url_query_strings_sort) . '&amp;sort=' . $column . '&amp;order=' . $disp . '">'
        . $label . ($sort === $column ? " $order_icon" : '') . '</a>';
};
$pill_url = function ($archived) use ($type) {
    return '?' . http_build_query(array_filter(['type' => $type, 'q' => $_GET['q'] ?? '', 'show' => $archived ? 'archived' : '']));
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
            <div class="card-header py-3">
                <form autocomplete="off">
                    <input type="hidden" name="type" value="<?= $type ?>">
                    <?php if ($show_archived) { ?><input type="hidden" name="show" value="archived"><?php } ?>
                    <div class="row g-2 align-items-center">
                        <div class="col-lg-6 col-md-8">
                            <div class="input-group">
                                <input type="search" class="form-control" name="q" value="<?php if (isset($q)) { echo stripslashes(escapeHtml($q)); } ?>" placeholder="Search <?= $kind_word ?> tags..." title="Name or description">
                                <button class="btn btn-dark" title="Search"><i class="fa fa-search"></i></button>
                            </div>
                        </div>
                        <div class="col-lg-6 col-md-4">
                            <div class="d-flex justify-content-end gap-2">
                                <div class="dropdown" id="bulkActionButton" hidden>
                                    <button class="btn btn-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                        <i class="fas fa-fw fa-layer-group me-2"></i>Bulk Action (<span id="selectedCount">0</span>)
                                    </button>
                                    <div class="dropdown-menu dropdown-menu-end">
                                        <a class="dropdown-item ajax-modal" href="#" data-modal-url="modals/tag/tag_merge.php" data-bulk="true">
                                            <i class="fas fa-fw fa-compress-alt text-secondary me-2"></i>Merge into one...
                                        </a>
                                        <?php if ($show_archived) { ?>
                                            <button class="dropdown-item" type="submit" form="bulkActions" name="bulk_restore_tags">
                                                <i class="fas fa-fw fa-box-open text-secondary me-2"></i>Restore
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
                            </div>
                        </div>
                    </div>
                </form>
                <nav class="nav nav-pills itflow-asset-tabs mt-2">
                    <a class="nav-link <?= $show_archived ? '' : 'active' ?>" href="<?= escapeHtml($pill_url(false)) ?>"><i class="fas fa-fw fa-tags me-1"></i>Active <span class="badge rounded-pill ms-1"><?= intval($counts[0]) ?></span></a>
                    <a class="nav-link <?= $show_archived ? 'active' : '' ?>" href="<?= escapeHtml($pill_url(true)) ?>" title="They stay on their records, but aren't offered for tagging or in the filters"><i class="fas fa-fw fa-archive me-1"></i>Archived <span class="badge rounded-pill ms-1"><?= intval($counts[1]) ?></span></a>
                </nav>
            </div>

            <form id="bulkActions" action="post.php" method="post">
                <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light text-nowrap <?php if ($num_rows[0] == 0) { echo "d-none"; } ?>">
                        <tr>
                            <th class="checkbox-column border-end">
                                <div class="form-check">
                                    <input class="form-check-input" id="selectAllCheckbox" type="checkbox" title="Select all on this page">
                                </div>
                            </th>
                            <th><?= $sort_link('tag_name', 'Tag') ?></th>
                            <th class="d-none d-sm-table-cell"><?= $sort_link('used', 'On') ?></th>
                            <th></th>
                        </tr>
                        </thead>
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
                                    . ($used_archived ? ' <span class="text-body-secondary">(' . number_format($used_archived) . ' archived)</span>' : '')
                                : '<span class="text-body-secondary">' . ($tag['tag_archived_at'] ? 'Nothing has it' : 'Nothing yet') . '</span>';
                            $lines_html = $lines
                                ? '<div class="small text-body-secondary" title="Recurring invoice lines that count the ' . ($type === 3 ? 'users' : 'devices') . ' with this tag"><i class="fas fa-fw fa-redo-alt me-1"></i>Counted on ' . $lines . ' recurring invoice ' . ($lines === 1 ? 'line' : 'lines') . '</div>'
                                : '';
                            $delete_text = $used
                                ? 'It comes off the ' . $records_text($used) . ' that ' . ($used === 1 ? 'has' : 'have') . ' it. To keep it on them but stop offering it, archive it instead.'
                                : 'Nothing has it, so no record changes.';
                            ?>
                            <tr>
                                <td class="checkbox-column bg-light border-end">
                                    <div class="form-check">
                                        <input class="form-check-input bulk-select" type="checkbox" name="tag_ids[]" value="<?= $tag_id ?>" aria-label="Select <?= $name ?>">
                                    </div>
                                </td>
                                <td>
                                    <a class="ajax-modal text-decoration-none" href="#" data-modal-url="<?= $edit_url ?>" title="Edit <?= $name ?>"><?= tagPill($tag) ?></a>
                                    <?php if (trim((string) $tag['tag_description']) !== '') { ?>
                                        <div class="small text-body-secondary mt-1"><?= escapeHtml($tag['tag_description']) ?></div>
                                    <?php } ?>
                                    <div class="small d-sm-none mt-1"><?= $used_html ?><?= $lines_html ?></div>
                                    <?php if ($tag['tag_archived_at']) { ?>
                                        <div class="small text-body-secondary"><i class="fas fa-fw fa-archive me-1"></i>Archived <?= date('M j, Y', strtotime($tag['tag_archived_at'])) ?></div>
                                    <?php } ?>
                                </td>
                                <td class="d-none d-sm-table-cell small">
                                    <?= $used_html ?>
                                    <?= $lines_html ?>
                                </td>
                                <td class="text-end">
                                    <div class="dropdown dropstart">
                                        <button class="btn btn-sm btn-light" title="More actions" type="button" data-bs-toggle="dropdown"><i class="fas fa-fw fa-ellipsis-v"></i></button>
                                        <div class="dropdown-menu">
                                            <a class="dropdown-item ajax-modal" href="#" data-modal-url="<?= $edit_url ?>">
                                                <i class="fas fa-fw fa-edit text-secondary me-2"></i>Edit
                                            </a>
                                            <a class="dropdown-item ajax-modal" href="#" data-modal-url="modals/tag/tag_merge.php?tag_ids[]=<?= $tag_id ?>">
                                                <i class="fas fa-fw fa-compress-alt text-secondary me-2"></i>Merge into...
                                            </a>
                                            <?php if ($used) { ?>
                                                <a class="dropdown-item" href="<?= escapeHtml($list_url) ?>">
                                                    <i class="fas fa-fw <?= $kind['icon'] ?> text-secondary me-2"></i>Show the <?= $kind['plural'] ?>
                                                </a>
                                            <?php } ?>
                                            <div class="dropdown-divider"></div>
                                            <?php if ($tag['tag_archived_at']) { ?>
                                                <a class="dropdown-item" href="post.php?restore_tag=<?= $tag_id ?>&csrf_token=<?= $csrf ?>">
                                                    <i class="fas fa-fw fa-box-open text-secondary me-2"></i>Restore
                                                </a>
                                            <?php } else { ?>
                                                <a class="dropdown-item text-danger" href="post.php?archive_tag=<?= $tag_id ?>&csrf_token=<?= $csrf ?>" title="It stays on its <?= $kind['plural'] ?>, but isn't offered for tagging or in the filters">
                                                    <i class="fas fa-fw fa-archive me-2"></i>Archive
                                                </a>
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

            <?php
            $empty_state = [
                'thing' => "$kind_word tags",
                'icon' => 'fa-tags',
                'filtered' => $q !== '',
                'archived' => $show_archived,
                'views' => ['type', 'show'],
                'text' => 'Tags sort and filter ' . $kind['plural'] . ' - "VIP", "Managed", "Retiring soon".',
                'actions' => [['label' => "New {$kind['title']} Tag", 'modal' => "modals/tag/tag_add.php?type=$type"]],
            ];
            require_once "../includes/filter_footer.php";
            ?>
        </div>
    </div>
</div>

<script src="../js/bulk_actions.js"></script>

<?php
require_once "../includes/footer.php";
