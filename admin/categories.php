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
 * deleted. The kinds pickers list in an order set here are dragged into it.
 * The work is in functions/categories.php; the handlers are
 * admin/post/category.php.
 */

require_once "includes/inc_all_admin.php";

$kinds = categoryKinds();
$kind = isset($kinds[$_GET['category'] ?? '']) ? (string) $_GET['category'] : 'Expense';
$config = categoryKind($kind);
$show_archived = isset($_GET['archived']);
$search = trim((string) ($_GET['q'] ?? ''));

// How the list is sorted - the order pickers use first, for the kinds that keep one
$sorts = ($config['ordered'] ? ['order' => ['Order', 'fa-sort-numeric-down', 'The order they are offered in']] : [])
    + ['name' => ['Name', 'fa-sort-alpha-down', 'A to Z'], 'use' => ['Most used', 'fa-sort-amount-down', 'The ones on the most records first']];
$sort_by = isset($sorts[$_GET['sort'] ?? '']) ? (string) $_GET['sort'] : array_key_first($sorts);
$default_sort = array_key_first($sorts);

$usage = categoryUsage($kind);
$empty_use = ['all' => 0, 'archived' => 0, 'uses' => []];
$categories = [];
foreach (categoryRows($kind) as $category_id => $row) {
    if ((bool) $row['category_archived_at'] !== $show_archived) {
        continue;
    }
    if ($search !== '' && mb_stripos($row['category_name'], $search) === false && mb_stripos((string) $row['category_description'], $search) === false) {
        continue;
    }
    $row['used'] = $usage[$category_id] ?? $empty_use;
    $categories[$category_id] = $row;
}
if ($sort_by === 'name') {
    uasort($categories, function ($a, $b) {
        return strnatcasecmp($a['category_name'], $b['category_name']);
    });
} elseif ($sort_by === 'use') {
    uasort($categories, function ($a, $b) {
        return $b['used']['all'] <=> $a['used']['all'] ?: strnatcasecmp($a['category_name'], $b['category_name']);
    });
}

// Names records have that aren't categories - a kind held by name, in the plain current view
$loose = !$show_archived && $search === '' ? categoryLooseNames($kind) : [];

// Dragged into order - in the order view, with the whole current list showing
$can_reorder = $config['ordered'] && $sort_by === 'order' && !$show_archived && $search === '' && count($categories) > 1;

// Current categories of each kind for the nav, and this kind's archived ones for the tab
$kind_counts = [];
$archived_count = 0;
$sql = mysqli_query($mysqli, "SELECT category_type, SUM(category_archived_at IS NULL), SUM(category_archived_at IS NOT NULL) FROM categories GROUP BY category_type");
while ($row = mysqli_fetch_row($sql)) {
    $kind_counts[$row[0]] = intval($row[1]);
    if ($row[0] === $kind) {
        $archived_count = intval($row[2]);
    }
}

// This page with some of its query changed - null drops a key
$page_url = function (array $change = []) use ($kind, $show_archived, $sort_by, $default_sort, $search) {
    $query = array_merge(['category' => $kind, 'q' => $search !== '' ? $search : null, 'sort' => $sort_by !== $default_sort ? $sort_by : null, 'archived' => $show_archived ? 1 : null], $change);
    return 'categories.php?' . http_build_query(array_filter($query, function ($v) {
        return $v !== null;
    }));
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
                    <a class="nav-link d-flex align-items-center gap-2 text-nowrap<?= $kind_key === $kind ? ' active' : '' ?>" href="categories.php?category=<?= urlencode($kind_key) ?>"<?= $kind_key === $kind ? ' aria-current="page"' : '' ?>>
                        <i class="fas fa-fw <?= $kind_config['icon'] ?>"></i><span class="text-truncate"><?= escapeHtml($kind_config['label']) ?></span>
                        <span class="badge rounded-pill ms-auto <?= $kind_key === $kind ? 'text-bg-light' : 'text-bg-secondary' ?>"><?= $kind_counts[$kind_key] ?? 0 ?></span>
                    </a>
                <?php } ?>
            </nav>
        </div>

        <!-- This kind's categories -->
        <div class="col-md-8 col-lg-9">
            <div class="p-2 border-bottom">
                <p class="small text-secondary mb-2"><?= escapeHtml($config['about']) ?></p>
                <form autocomplete="off" class="d-flex flex-wrap align-items-center gap-2">
                    <input type="hidden" name="category" value="<?= escapeHtml($kind) ?>">
                    <?php if ($show_archived) { ?><input type="hidden" name="archived" value="1"><?php } ?>
                    <?php if ($sort_by !== $default_sort) { ?><input type="hidden" name="sort" value="<?= escapeHtml($sort_by) ?>"><?php } ?>
                    <div class="input-group input-group-sm w-auto">
                        <input type="search" class="form-control" name="q" value="<?= escapeHtml($search) ?>" placeholder="Search <?= escapeHtml($config['many']) ?>">
                        <button class="btn btn-primary" title="Search"><i class="fa fa-search"></i></button>
                    </div>
                    <div class="btn-group btn-group-sm" role="group" aria-label="Sort">
                        <?php foreach ($sorts as $sort_key => [$sort_label, $sort_icon, $sort_title]) { ?>
                            <a class="btn btn-outline-secondary<?= $sort_by === $sort_key ? ' active' : '' ?>" href="<?= escapeHtml($page_url(['sort' => $sort_key !== $default_sort ? $sort_key : null])) ?>" title="<?= $sort_title ?>"><i class="fas <?= $sort_icon ?> me-1"></i><?= $sort_label ?></a>
                        <?php } ?>
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
                            <a class="dropdown-item ajax-modal" href="#" data-modal-url="modals/category/category_merge.php" data-bulk="true">
                                <i class="fas fa-fw fa-compress-alt text-secondary me-2"></i>Merge into one...
                            </a>
                            <?php if ($show_archived) { ?>
                                <button class="dropdown-item" type="submit" form="bulkActions" name="bulk_restore_categories">
                                    <i class="fas fa-fw fa-redo text-secondary me-2"></i>Restore
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
                </form>
            </div>

            <form id="bulkActions" action="post.php" method="post">
                <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                <div class="table-responsive-sm">
                    <table class="table table-borderless table-hover align-middle mb-0">
                        <?php if ($categories) { ?>
                            <thead class="text-dark">
                            <tr>
                                <th class="ps-3" style="width: 1%">
                                    <div class="form-check mb-0">
                                        <input class="form-check-input" id="selectAllCheckbox" type="checkbox" title="Select all">
                                    </div>
                                </th>
                                <?php if ($can_reorder) { ?><th style="width: 1%"><span class="visually-hidden">Order</span></th><?php } ?>
                                <th><?= escapeHtml(ucfirst($config['one'])) ?></th>
                                <th class="d-none d-sm-table-cell">Used on</th>
                                <th class="text-center" style="width: 1%">Action</th>
                            </tr>
                            </thead>
                        <?php } ?>
                        <tbody<?= $can_reorder ? ' data-category-sortable data-category-kind="' . escapeHtml($kind) . '"' : '' ?>>
                        <?php
                        $positions = array_keys($categories);
                        foreach ($categories as $category_id => $row) {
                            $name = escapeHtml($row['category_name']);
                            $used = $row['used'];
                            $edit_url = "modals/category/category_edit.php?id=$category_id";
                            $used_html = $used['all'] ? categoryUsageHtml($row, $used) : '<span class="text-secondary">' . ($row['category_archived_at'] ? 'Nothing uses it' : 'Nothing yet') . '</span>';
                            $blocker = categoryDeleteBlocker($row, $used);
                            $position = array_search($category_id, $positions, true);
                            ?>
                            <tr data-category-id="<?= $category_id ?>">
                                <td class="ps-3">
                                    <div class="form-check mb-0">
                                        <input class="form-check-input bulk-select" type="checkbox" name="category_ids[]" value="<?= $category_id ?>" aria-label="Select <?= $name ?>">
                                    </div>
                                </td>
                                <?php if ($can_reorder) { ?>
                                    <td class="pe-0"><span class="itflow-category-grip text-secondary" title="Drag to move" data-category-grip><i class="fas fa-grip-vertical" aria-hidden="true"></i></span></td>
                                <?php } ?>
                                <td>
                                    <a class="ajax-modal text-decoration-none text-reset" href="#" data-modal-url="<?= $edit_url ?>" title="Edit <?= $name ?>"><?= categoryChip($row) ?></a>
                                    <?php if (trim((string) $row['category_description']) !== '') { ?>
                                        <div class="small text-secondary mt-1"><?= escapeHtml($row['category_description']) ?></div>
                                    <?php } ?>
                                    <div class="small d-sm-none mt-1"><?= $used_html ?></div>
                                    <?php if ($row['category_archived_at']) { ?>
                                        <div class="small text-secondary"><i class="fas fa-fw fa-archive me-1"></i>Archived <?= date('M j, Y', strtotime($row['category_archived_at'])) ?></div>
                                    <?php } ?>
                                </td>
                                <td class="d-none d-sm-table-cell small"><?= $used_html ?></td>
                                <td>
                                    <div class="dropdown dropstart text-center">
                                        <button class="btn btn-secondary btn-sm" title="More actions" type="button" data-bs-toggle="dropdown"><i class="fas fa-ellipsis-h"></i></button>
                                        <div class="dropdown-menu">
                                            <a class="dropdown-item ajax-modal" href="#" data-modal-url="<?= $edit_url ?>"><i class="fas fa-fw fa-edit me-2"></i>Edit</a>
                                            <a class="dropdown-item ajax-modal" href="#" data-modal-url="modals/category/category_merge.php?category_ids[]=<?= $category_id ?>"><i class="fas fa-fw fa-compress-alt me-2"></i>Merge into...</a>
                                            <?php if ($can_reorder) { ?>
                                                <?php if ($position > 0) { ?>
                                                    <a class="dropdown-item" href="post.php?move_category=<?= $category_id ?>&direction=up&csrf_token=<?= $csrf ?>"><i class="fas fa-fw fa-arrow-up me-2"></i>Move up</a>
                                                <?php } ?>
                                                <?php if ($position < count($positions) - 1) { ?>
                                                    <a class="dropdown-item" href="post.php?move_category=<?= $category_id ?>&direction=down&csrf_token=<?= $csrf ?>"><i class="fas fa-fw fa-arrow-down me-2"></i>Move down</a>
                                                <?php } ?>
                                            <?php } ?>
                                            <div class="dropdown-divider"></div>
                                            <?php if ($row['category_archived_at']) { ?>
                                                <a class="dropdown-item" href="post.php?restore_category=<?= $category_id ?>&csrf_token=<?= $csrf ?>"><i class="fas fa-fw fa-redo me-2"></i>Restore</a>
                                            <?php } else { ?>
                                                <a class="dropdown-item" href="post.php?archive_category=<?= $category_id ?>&csrf_token=<?= $csrf ?>" title="It stays on its records, but isn't offered for new ones"><i class="fas fa-fw fa-archive me-2"></i>Archive</a>
                                            <?php } ?>
                                            <?php if ($blocker !== '') { ?>
                                                <span class="dropdown-item disabled" title="<?= escapeHtml($blocker) ?>"><i class="fas fa-fw fa-trash me-2"></i>Delete</span>
                                            <?php } else { ?>
                                                <a class="dropdown-item text-danger text-bold confirm-link" href="post.php?delete_category=<?= $category_id ?>&csrf_token=<?= $csrf ?>"
                                                    data-confirm-title="Delete <?= $name ?>?" data-confirm-text="Nothing uses it, so no record changes." data-confirm-button="Delete"><i class="fas fa-fw fa-trash me-2"></i>Delete</a>
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
                <div class="small text-secondary px-3 py-2 border-top"><i class="fas fa-fw fa-grip-vertical me-1"></i>Drag them into the order they're offered in.</div>
            <?php } elseif ($config['ordered'] && $sort_by === 'order' && !$show_archived && $search !== '') { ?>
                <div class="small text-secondary px-3 py-2 border-top">Clear the search to drag them into order.</div>
            <?php } ?>

            <?php if (!$categories) { ?>
                <?= emptyStateHtml([
                    'thing' => $config['many'],
                    'title' => $show_archived ? 'No archived ' . $config['many'] : 'No ' . $config['many'] . ' yet',
                    'text' => $show_archived ? 'An archived category stays on its records, but isn\'t offered for new ones.' : $config['about'],
                    'icon' => $config['icon'],
                    'filtered' => $search !== '',
                    'clear_url' => $page_url(['q' => null]),
                    'actions' => $show_archived ? [] : [['label' => $new_label, 'modal' => $add_url]],
                ]) ?>
            <?php } ?>

            <?php if ($loose) { ?>
                <div class="border-top px-3 py-2 itflow-category-loose">
                    <div class="fw-semibold"><i class="fas fa-fw fa-question-circle text-secondary me-1"></i>In use, but not set up here</div>
                    <p class="small text-secondary mb-1">Records have these, but they aren't <?= escapeHtml($config['many']) ?> - so they can't be picked again, and have no color or icon. Add one to make it <?= escapeHtml(categoryKindPhrase($kind)) ?>; to fold it into another, add it, then merge it.</p>
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
            // Dragged into order - saved as soon as one is dropped; a failure reloads the page to show the order kept
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
                    itflowPost('post.php', { reorder_categories: 1, csrf_token: '<?= $csrf ?>', category: body.dataset.categoryKind, category_ids: ids })
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
