<?php

/*
 * Admin > Categories - the categories of each kind: expense, income,
 * referral and ticket, and the interface, asset status, software, rack and
 * note types.
 *
 * Each shows as it looks on records, with its description and what uses it
 * (opening the list of them, where there is one). A category can be
 * changed, merged into another of its kind, archived - it stays on its
 * records but isn't offered for new ones - or, while nothing uses it,
 * deleted. The kinds pickers list in an order set here are listed in it, and
 * dragged into it. The work is in functions/categories.php; the handlers are
 * admin/post/category.php.
 */

// Default Column Sortby Filter - the column is the kind's, set below
$order = "ASC";

require_once "includes/inc_all_admin.php";

$kinds = categoryKinds();
$kind = isset($kinds[$_GET['category'] ?? '']) ? (string) $_GET['category'] : 'Expense';
$config = categoryKind($kind);
// ?archived=1 is how older links asked for the archived ones
$show_archived = ($_GET['show'] ?? '') === 'archived' || !empty($_GET['archived']);
$search = trim((string) ($_GET['q'] ?? ''));

// Only these sort - by name, by use, and in the order pickers use for the kinds that keep one (and are listed in it)
$sort_first = ['category_name' => 'ASC', 'used' => 'DESC'] + ($config['ordered'] ? ['category_order' => 'ASC'] : []);
$default_sort = $config['ordered'] ? 'category_order' : 'category_name';
if (!isset($sort) || !isset($sort_first[$sort])) {
    $sort = $default_sort;
}

// The kind's categories, what the search leaves - counted for the pills, then this pill's
$usage = categoryUsage($kind);
$empty_use = ['all' => 0, 'archived' => 0, 'uses' => []];
$categories = [];
$counts = [0, 0];
foreach (categoryRows($kind) as $category_id => $row) {
    if ($search !== '' && mb_stripos($row['category_name'], $search) === false && mb_stripos((string) $row['category_description'], $search) === false) {
        continue;
    }
    $counts[$row['category_archived_at'] ? 1 : 0]++;
    if ((bool) $row['category_archived_at'] !== $show_archived) {
        continue;
    }
    $row['used'] = $usage[$category_id] ?? $empty_use;
    $categories[$category_id] = $row;
}

// categoryRows() has them in the order pickers use
$direction = $order === 'DESC' ? -1 : 1;
if ($sort === 'category_name') {
    uasort($categories, function ($a, $b) use ($direction) {
        return $direction * strnatcasecmp($a['category_name'], $b['category_name']);
    });
} elseif ($sort === 'used') {
    uasort($categories, function ($a, $b) use ($direction) {
        return $direction * ($a['used']['all'] <=> $b['used']['all']) ?: strnatcasecmp($a['category_name'], $b['category_name']);
    });
} elseif ($direction === -1) {
    $categories = array_reverse($categories, true);
}

// A page of them
$num_rows = [count($categories)];
$categories = array_slice($categories, $record_from, $record_to, true);

// Names records have that aren't categories - a kind held by name, in the plain current view
$loose = !$show_archived && $search === '' ? categoryLooseNames($kind) : [];

// Dragged into order - in order, first to last, with every current one in it
$can_reorder = $config['ordered'] && $sort === 'category_order' && $order === 'ASC' && !$show_archived && $search === '' && $num_rows[0] > 1;

// Current categories of each kind for the nav
$kind_counts = [];
$sql = mysqli_query($mysqli, "SELECT category_type, SUM(category_archived_at IS NULL) FROM categories GROUP BY category_type");
while ($row = mysqli_fetch_row($sql)) {
    $kind_counts[$row[0]] = intval($row[1]);
}

// A column first sorts the way it reads best - A to Z, most used, first to last - then flips
$sort_link = function ($column, $label) use ($url_query_strings_sort, $sort, $disp, $order_icon, $sort_first) {
    return '<a class="text-reset text-decoration-none" href="?' . escapeHtml($url_query_strings_sort) . '&amp;sort=' . $column . '&amp;order=' . ($sort === $column ? $disp : $sort_first[$column]) . '">'
        . $label . ($sort === $column ? " $order_icon" : '') . '</a>';
};
$pill_url = function ($archived) use ($kind) {
    return '?' . http_build_query(array_filter(['category' => $kind, 'q' => $_GET['q'] ?? '', 'show' => $archived ? 'archived' : '']));
};

$new_label = 'New ' . ucwords($config['one']);
$add_url = 'modals/category/category_add.php?category=' . urlencode($kind);
$csrf = $_SESSION['csrf_token'];

?>

<div class="card">
    <div class="card-header bg-dark py-2">
        <h3 class="card-title mt-2"><i class="fas fa-fw fa-list-ul me-2"></i>Categories</h3>
        <div class="card-tools">
            <button type="button" class="btn btn-primary ajax-modal" data-modal-url="<?= escapeHtml($add_url) ?>"><i class="fas fa-plus me-2"></i><?= escapeHtml($new_label) ?></button>
        </div>
    </div>

    <div class="row g-0">
        <!-- Kinds -->
        <div class="col-md-4 col-lg-3 border-end">
            <nav class="nav nav-pills flex-nowrap flex-md-column overflow-auto p-2 gap-1 itflow-category-kinds" aria-label="Kinds of category">
                <?php foreach ($kinds as $kind_key => $kind_config) { ?>
                    <a class="nav-link d-flex align-items-center gap-2 text-nowrap<?= $kind_key === $kind ? ' active' : '' ?>" href="categories.php?category=<?= urlencode($kind_key) ?>" title="<?= escapeHtml($kind_config['about']) ?>"<?= $kind_key === $kind ? ' aria-current="page"' : '' ?>>
                        <i class="fas fa-fw <?= $kind_config['icon'] ?>"></i><span class="text-truncate"><?= escapeHtml($kind_config['label']) ?></span>
                        <span class="badge rounded-pill ms-auto <?= $kind_key === $kind ? 'text-bg-light' : 'text-bg-secondary' ?>"><?= $kind_counts[$kind_key] ?? 0 ?></span>
                    </a>
                <?php } ?>
            </nav>
        </div>

        <!-- This kind's categories -->
        <div class="col-md-8 col-lg-9">
            <div class="card-header py-3">
                <form autocomplete="off">
                    <input type="hidden" name="category" value="<?= escapeHtml($kind) ?>">
                    <?php if ($show_archived) { ?><input type="hidden" name="show" value="archived"><?php } ?>
                    <div class="row g-2 align-items-center">
                        <div class="col-lg-6 col-md-8">
                            <div class="input-group">
                                <input type="search" class="form-control" name="q" value="<?= escapeHtml($search) ?>" placeholder="Search <?= escapeHtml($config['many']) ?>..." title="Name or description">
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
                                        <a class="dropdown-item ajax-modal" href="#" data-modal-url="modals/category/category_merge.php" data-bulk="true">
                                            <i class="fas fa-fw fa-compress-alt text-secondary me-2"></i>Merge into one...
                                        </a>
                                        <?php if ($show_archived) { ?>
                                            <button class="dropdown-item" type="submit" form="bulkActions" name="bulk_restore_categories">
                                                <i class="fas fa-fw fa-box-open text-secondary me-2"></i>Restore
                                            </button>
                                        <?php } else { ?>
                                            <button class="dropdown-item" type="submit" form="bulkActions" name="bulk_archive_categories">
                                                <i class="fas fa-fw fa-archive text-secondary me-2"></i>Archive
                                            </button>
                                        <?php } ?>
                                        <div class="dropdown-divider"></div>
                                        <button class="dropdown-item text-danger confirm-link" type="submit" form="bulkActions" name="bulk_delete_categories"
                                            data-confirm-title="Delete these <?= escapeHtml($config['many']) ?>?"
                                            data-confirm-text="Only the ones nothing uses are deleted - the rest are kept, to merge into another or archive."
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
                    <a class="nav-link <?= $show_archived ? '' : 'active' ?>" href="<?= escapeHtml($pill_url(false)) ?>"><i class="fas fa-fw <?= $config['icon'] ?> me-1"></i>Active <span class="badge rounded-pill ms-1"><?= $counts[0] ?></span></a>
                    <a class="nav-link <?= $show_archived ? 'active' : '' ?>" href="<?= escapeHtml($pill_url(true)) ?>" title="They stay on their records, but aren't offered for new ones"><i class="fas fa-fw fa-archive me-1"></i>Archived <span class="badge rounded-pill ms-1"><?= $counts[1] ?></span></a>
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
                            <?php if ($can_reorder) { ?><th style="width: 1%"><span class="visually-hidden">Order</span></th><?php } ?>
                            <th>
                                <?= $sort_link('category_name', escapeHtml(ucfirst($config['one']))) ?>
                                <?php if ($config['ordered']) { ?><span class="fw-normal text-body-secondary small ms-2" title="The order they are offered in"><?= $sort_link('category_order', 'in order') ?></span><?php } ?>
                            </th>
                            <th class="d-none d-sm-table-cell"><?= $sort_link('used', 'Used on') ?></th>
                            <th></th>
                        </tr>
                        </thead>
                        <tbody<?= $can_reorder ? ' data-category-sortable data-category-kind="' . escapeHtml($kind) . '" data-category-offset="' . intval($record_from) . '"' : '' ?>>
                        <?php
                        $position = $record_from - 1;
                        foreach ($categories as $category_id => $row) {
                            $position++;
                            $name = escapeHtml($row['category_name']);
                            $used = $row['used'];
                            $edit_url = "modals/category/category_edit.php?id=$category_id";
                            $used_html = $used['all'] ? categoryUsageHtml($row, $used) : '<span class="text-body-secondary">' . ($row['category_archived_at'] ? 'Nothing uses it' : 'Nothing yet') . '</span>';
                            $blocker = categoryDeleteBlocker($row, $used);
                            ?>
                            <tr data-category-id="<?= $category_id ?>">
                                <td class="checkbox-column bg-light border-end">
                                    <div class="form-check">
                                        <input class="form-check-input bulk-select" type="checkbox" name="category_ids[]" value="<?= $category_id ?>" aria-label="Select <?= $name ?>">
                                    </div>
                                </td>
                                <?php if ($can_reorder) { ?>
                                    <td class="pe-0"><span class="itflow-category-grip text-body-secondary" title="Drag to move" data-category-grip><i class="fas fa-grip-vertical" aria-hidden="true"></i></span></td>
                                <?php } ?>
                                <td>
                                    <a class="ajax-modal text-decoration-none text-reset" href="#" data-modal-url="<?= $edit_url ?>" title="Edit <?= $name ?>"><?= categoryChip($row) ?></a>
                                    <?php if (trim((string) $row['category_description']) !== '') { ?>
                                        <div class="small text-body-secondary mt-1"><?= escapeHtml($row['category_description']) ?></div>
                                    <?php } ?>
                                    <div class="small d-sm-none mt-1"><?= $used_html ?></div>
                                    <?php if ($row['category_archived_at']) { ?>
                                        <div class="small text-body-secondary"><i class="fas fa-fw fa-archive me-1"></i>Archived <?= date('M j, Y', strtotime($row['category_archived_at'])) ?></div>
                                    <?php } ?>
                                </td>
                                <td class="d-none d-sm-table-cell small"><?= $used_html ?></td>
                                <td class="text-end">
                                    <div class="dropdown dropstart">
                                        <button class="btn btn-sm btn-light" title="More actions" type="button" data-bs-toggle="dropdown"><i class="fas fa-fw fa-ellipsis-v"></i></button>
                                        <div class="dropdown-menu">
                                            <a class="dropdown-item ajax-modal" href="#" data-modal-url="<?= $edit_url ?>">
                                                <i class="fas fa-fw fa-edit text-secondary me-2"></i>Edit
                                            </a>
                                            <a class="dropdown-item ajax-modal" href="#" data-modal-url="modals/category/category_merge.php?category_ids[]=<?= $category_id ?>">
                                                <i class="fas fa-fw fa-compress-alt text-secondary me-2"></i>Merge into...
                                            </a>
                                            <?php if ($can_reorder) { ?>
                                                <?php if ($position > 0) { ?>
                                                    <a class="dropdown-item" href="post.php?move_category=<?= $category_id ?>&direction=up&csrf_token=<?= $csrf ?>">
                                                        <i class="fas fa-fw fa-arrow-up text-secondary me-2"></i>Move up
                                                    </a>
                                                <?php } ?>
                                                <?php if ($position < $num_rows[0] - 1) { ?>
                                                    <a class="dropdown-item" href="post.php?move_category=<?= $category_id ?>&direction=down&csrf_token=<?= $csrf ?>">
                                                        <i class="fas fa-fw fa-arrow-down text-secondary me-2"></i>Move down
                                                    </a>
                                                <?php } ?>
                                            <?php } ?>
                                            <div class="dropdown-divider"></div>
                                            <?php if ($row['category_archived_at']) { ?>
                                                <a class="dropdown-item" href="post.php?restore_category=<?= $category_id ?>&csrf_token=<?= $csrf ?>">
                                                    <i class="fas fa-fw fa-box-open text-secondary me-2"></i>Restore
                                                </a>
                                            <?php } else { ?>
                                                <a class="dropdown-item text-danger" href="post.php?archive_category=<?= $category_id ?>&csrf_token=<?= $csrf ?>" title="It stays on its records, but isn't offered for new ones">
                                                    <i class="fas fa-fw fa-archive me-2"></i>Archive
                                                </a>
                                            <?php } ?>
                                            <?php if ($blocker !== '') { ?>
                                                <span class="dropdown-item disabled" title="<?= escapeHtml($blocker) ?>"><i class="fas fa-fw fa-trash me-2"></i>Delete</span>
                                            <?php } else { ?>
                                                <a class="dropdown-item text-danger text-bold confirm-link" href="post.php?delete_category=<?= $category_id ?>&csrf_token=<?= $csrf ?>"
                                                    data-confirm-title="Delete <?= $name ?>?" data-confirm-text="Nothing uses it, so no record changes." data-confirm-button="Delete">
                                                    <i class="fas fa-fw fa-trash me-2"></i>Delete
                                                </a>
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

            <?php if ($can_reorder) { ?>
                <div class="small text-body-secondary px-3 py-2 border-top"><i class="fas fa-fw fa-grip-vertical me-1"></i>Drag them into the order they're offered in.</div>
            <?php } elseif ($config['ordered'] && $sort === 'category_order' && !$show_archived && $search !== '') { ?>
                <div class="small text-body-secondary px-3 py-2 border-top">Clear the search to drag them into order.</div>
            <?php } ?>

            <?php
            $empty_state = [
                'thing' => $config['many'],
                'icon' => $config['icon'],
                'filtered' => $search !== '',
                'archived' => $show_archived,
                'views' => ['category', 'show'],
                'text' => escapeHtml($config['about']),
                'actions' => [['label' => $new_label, 'modal' => $add_url]],
            ];
            require_once "../includes/filter_footer.php";
            ?>

            <?php if ($loose) { ?>
                <div class="border-top px-3 py-2 itflow-category-loose">
                    <div class="fw-semibold"><i class="fas fa-fw fa-question-circle text-secondary me-1"></i>In use, but not set up here</div>
                    <p class="small text-body-secondary mb-1">Records have these, but they aren't <?= escapeHtml($config['many']) ?> - so they can't be picked again, and have no color or icon. Add one to make it <?= escapeHtml(categoryKindPhrase($kind)) ?>; to fold it into another, add it, then merge it.</p>
                    <ul class="list-unstyled mb-0">
                        <?php foreach ($loose as $loose_name => $loose_used) { ?>
                            <li class="d-flex flex-wrap align-items-center gap-2 py-1">
                                <span><?= escapeHtml($loose_name) ?></span>
                                <span class="small"><?= categoryUsageHtml(['category_id' => 0, 'category_name' => $loose_name, 'category_type' => $kind], $loose_used) ?></span>
                                <form action="post.php" method="post" class="ms-auto">
                                    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                                    <input type="hidden" name="type" value="<?= escapeHtml($kind) ?>">
                                    <input type="hidden" name="name" value="<?= escapeHtml($loose_name) ?>">
                                    <button type="submit" name="add_category" class="btn btn-sm btn-outline-secondary" title="Make it <?= escapeHtml(categoryKindPhrase($kind)) ?>"><i class="fas fa-plus me-1"></i>Add</button>
                                </form>
                            </li>
                        <?php } ?>
                    </ul>
                </div>
            <?php } ?>
        </div>
    </div>
</div>

<script src="../js/bulk_actions.js"></script>
<?php if ($can_reorder) { ?>
    <script src="/libs/SortableJS/Sortable.min.js"></script>
    <script>
        (function () {
            // Dragged into order - saved as soon as one is dropped, in their place among the rest (offset - the ones on the pages before);
            // a failure reloads the page to show the order kept
            const body = document.querySelector('[data-category-sortable]');
            new Sortable(body, {
                handle: '[data-category-grip]',
                animation: 150,
                onEnd: function (event) {
                    if (event.oldIndex === event.newIndex) {
                        return;
                    }
                    const ids = Array.from(body.querySelectorAll('tr[data-category-id]')).map(function (row) {
                        return row.dataset.categoryId;
                    });
                    itflowPost('post.php', { reorder_categories: 1, csrf_token: '<?= $csrf ?>', category: body.dataset.categoryKind, offset: body.dataset.categoryOffset, category_ids: ids })
                        .then(function (text) {
                            const result = JSON.parse(text);
                            if (!result.ok) {
                                throw new Error(result.error || 'Not saved');
                            }
                            itflowToast('Order saved', 'success');
                        })
                        .catch(function () {
                            window.location.reload();
                        });
                }
            });
        })();
    </script>
<?php } ?>

<?php
require_once "../includes/footer.php";
