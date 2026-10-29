<?php

/*
 * Admin > Custom Links - your own links in ITFlow's menus: your RMM, your
 * documentation, a booking page for clients. Tabs for each menu - the agent
 * menu, the top bar, the client portal, the admin menu and the reports menu -
 * with how many each holds, and the archived ones, which are kept but not
 * shown. Each row shows the link as its menu draws it, what it is for, where
 * it goes and whether it opens in a new tab. On a menu's tab they are in that
 * menu's order and can be dragged into another (or moved up and down from
 * the row's menu). The work is in functions/custom_link.php; the handlers
 * are admin/post/custom_link.php.
 */

// Default Column Sortby Filter - the order the menus show them in
$sort = "custom_link_order";
$order = "ASC";

require_once "includes/inc_all_admin.php";

$locations = customLinkLocations();

// The tabs: every menu, each menu, and the archived ones
$views = ['' => ['label' => 'All menus', 'icon' => 'fa-th-list', 'where' => 'custom_link_archived_at IS NULL', 'title' => 'Every link that shows in a menu']];
foreach ($locations as $location => $location_config) {
    $views[(string) $location] = ['label' => $location_config['label'], 'icon' => $location_config['icon'], 'where' => "custom_link_archived_at IS NULL AND custom_link_location = $location", 'title' => $location_config['about']];
}
$views['archived'] = ['label' => 'Archived', 'icon' => 'fa-archive', 'where' => 'custom_link_archived_at IS NOT NULL', 'title' => 'Kept, but not shown in any menu'];
$show = (string) ($_GET['show'] ?? '');
$show = isset($views[$show]) ? $show : '';
$show_location = $show !== '' && $show !== 'archived' ? intval($show) : 0;

// Only these sort - an unknown column would take the page down
$sortable_columns = [
    'custom_link_order' => "custom_link_location ASC, custom_link_order $order, custom_link_name ASC",
    'custom_link_name' => "custom_link_name $order",
    'custom_link_location' => "custom_link_location $order, custom_link_order ASC, custom_link_name ASC",
    'custom_link_uri' => "custom_link_uri $order",
];
if (!isset($sortable_columns[$sort])) {
    $sort = 'custom_link_order';
}

$filters = "(custom_link_name LIKE '%$q%' OR custom_link_uri LIKE '%$q%' OR custom_link_description LIKE '%$q%')";

$sql = mysqli_query($mysqli, "SELECT SQL_CALC_FOUND_ROWS custom_link_id, custom_link_name, custom_link_description, custom_link_uri, custom_link_new_tab, custom_link_icon,
        custom_link_location, custom_link_order, custom_link_archived_at
    FROM custom_links
    WHERE $filters AND {$views[$show]['where']}
    ORDER BY {$sortable_columns[$sort]}, custom_link_id ASC
    LIMIT $record_from, $record_to");

$num_rows = mysqli_fetch_row(mysqli_query($mysqli, "SELECT FOUND_ROWS()"));

$counts = mysqli_fetch_row(mysqli_query($mysqli, "SELECT " . implode(', ', array_map(function ($view) {
    return "COALESCE(SUM({$view['where']}), 0)";
}, $views)) . " FROM custom_links WHERE $filters"));
$view_counts = array_combine(array_keys($views), $counts);

// Dragging into order: one menu's links, in its order, with nothing searched
$can_reorder = $show_location && $sort === 'custom_link_order' && $order === 'ASC' && $q === '' && $num_rows[0] > 1;

$sort_link = function ($column, $label) use ($url_query_strings_sort, $sort, $disp, $order_icon) {
    return '<a class="text-reset text-decoration-none" href="?' . escapeHtml($url_query_strings_sort) . '&amp;sort=' . $column . '&amp;order=' . $disp . '">'
        . $label . ($sort === $column ? " $order_icon" : '') . '</a>';
};
$pill_url = function ($view) {
    return '?' . http_build_query(array_filter(['q' => $_GET['q'] ?? '', 'show' => $view]));
};

$add_url = 'modals/custom_link/custom_link_add.php' . ($show_location ? "?location=$show_location" : '');
$csrf = $_SESSION['csrf_token'];

?>

<div class="card">
    <div class="card-header bg-dark py-2">
        <h3 class="card-title mt-2"><i class="fas fa-fw fa-external-link-alt me-2"></i>Custom Links</h3>
        <div class="card-tools">
            <button type="button" class="btn btn-primary ajax-modal" data-modal-url="<?= escapeHtml($add_url) ?>">
                <i class="fas fa-plus"></i><span class="d-none d-lg-inline ms-2">New Link</span>
            </button>
        </div>
    </div>
    <div class="card-header py-3">
        <form autocomplete="off">
            <?php if ($show !== '') { ?><input type="hidden" name="show" value="<?= escapeHtml($show) ?>"><?php } ?>
            <div class="row g-2 align-items-center">
                <div class="col-lg-5 col-md-6">
                    <div class="input-group">
                        <input type="search" class="form-control" name="q" value="<?php if (isset($q)) { echo stripslashes(escapeHtml($q)); } ?>" placeholder="Search links..." title="Name, address or description">
                        <button class="btn btn-dark" title="Search"><i class="fa fa-search"></i></button>
                    </div>
                </div>
            </div>
        </form>
        <nav class="nav nav-pills itflow-asset-tabs mt-2">
            <?php foreach ($views as $view_key => $view) { ?>
                <a class="nav-link <?= $show === (string) $view_key ? 'active' : '' ?>" href="<?= escapeHtml($pill_url($view_key)) ?>" title="<?= escapeHtml($view['title']) ?>"><i class="fas fa-fw <?= $view['icon'] ?> me-1"></i><?= escapeHtml($view['label']) ?> <span class="badge rounded-pill ms-1"><?= intval($view_counts[$view_key]) ?></span></a>
            <?php } ?>
        </nav>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light text-nowrap <?php if ($num_rows[0] == 0) { echo "d-none"; } ?>">
            <tr>
                <?php if ($can_reorder) { ?><th style="width: 1%"><span class="visually-hidden">Order</span></th><?php } ?>
                <th class="<?= $can_reorder ? 'ps-0' : 'ps-3' ?>"><?= $sort_link('custom_link_name', 'Link') ?></th>
                <?php if (!$show_location) { ?>
                    <th class="d-none d-md-table-cell"><?= $sort_link('custom_link_location', 'Shows in') ?></th>
                <?php } ?>
                <th class="d-none d-lg-table-cell"><?= $sort_link('custom_link_uri', 'Goes to') ?></th>
                <th></th>
            </tr>
            </thead>
            <tbody<?= $can_reorder ? ' data-custom-link-sortable data-custom-link-location="' . $show_location . '" data-custom-link-offset="' . intval($record_from) . '"' : '' ?>>
            <?php
            $position = $record_from - 1;
            while ($row = mysqli_fetch_assoc($sql)) {
                $position++;
                $custom_link_id = intval($row['custom_link_id']);
                $name = escapeHtml($row['custom_link_name']);
                $description = trim((string) $row['custom_link_description']);
                $edit_url = "modals/custom_link/custom_link_edit.php?id=$custom_link_id";
                $location = intval($row['custom_link_location']);
                $location_config = $locations[$location] ?? ['label' => "Menu $location", 'icon' => 'fa-question-circle', 'about' => 'Not a menu ITFlow has - it shows nowhere'];
                $new_tab = intval($row['custom_link_new_tab']) === 1;
                $problem = customLinkUriProblem($row['custom_link_uri']);
                $where_html = '<span class="badge rounded-pill text-bg-light border fw-normal" title="' . escapeHtml($location_config['about']) . '"><i class="fas fa-fw ' . $location_config['icon'] . ' text-secondary me-1"></i>' . escapeHtml($location_config['label']) . '</span>';
                $goes_html = '<span class="font-monospace small text-break">' . escapeHtml(customLinkUriDisplay($row['custom_link_uri'])) . '</span>'
                    . ($new_tab ? ' <i class="fas fa-fw fa-external-link-alt small text-body-secondary" title="Opens in a new tab"></i>' : '');
                ?>
                <tr data-custom-link-id="<?= $custom_link_id ?>">
                    <?php if ($can_reorder) { ?>
                        <td class="pe-0"><span class="itflow-category-grip text-body-secondary" title="Drag to move" data-custom-link-grip><i class="fas fa-grip-vertical" aria-hidden="true"></i></span></td>
                    <?php } ?>
                    <td class="<?= $can_reorder ? 'ps-0' : 'ps-3' ?>">
                        <div class="d-flex align-items-center gap-3">
                            <span class="itflow-asset-icon itflow-asset-tone-other d-none d-sm-inline-flex" aria-hidden="true"><i class="fas fa-fw fa-<?= escapeHtml(customLinkIcon($row['custom_link_icon'])) ?>"></i></span>
                            <div class="min-w-0">
                                <a class="ajax-modal fw-semibold text-body" href="#" data-modal-url="<?= $edit_url ?>" title="Edit <?= $name ?>"><?= $name ?></a>
                                <?php if ($description !== '') { ?>
                                    <div class="small text-body-secondary"><?= escapeHtml($description) ?></div>
                                <?php } ?>
                                <?php if ($problem !== '') { ?>
                                    <div class="small text-danger" title="<?= escapeHtml($problem) ?>"><i class="fas fa-fw fa-exclamation-triangle me-1"></i>Goes nowhere in its menu - edit its address</div>
                                <?php } ?>
                                <?php if ($row['custom_link_archived_at']) { ?>
                                    <div class="small text-body-secondary"><i class="fas fa-fw fa-archive me-1"></i>Archived <?= date('M j, Y', strtotime($row['custom_link_archived_at'])) ?></div>
                                <?php } ?>
                                <!-- On smaller screens where it shows and where it goes come along under the name -->
                                <div class="d-lg-none mt-1 d-flex flex-wrap align-items-center gap-2">
                                    <?php if (!$show_location) { ?><span class="d-md-none"><?= $where_html ?></span><?php } ?>
                                    <span class="text-body-secondary"><?= $goes_html ?></span>
                                </div>
                            </div>
                        </div>
                    </td>
                    <?php if (!$show_location) { ?>
                        <td class="d-none d-md-table-cell"><?= $where_html ?></td>
                    <?php } ?>
                    <td class="d-none d-lg-table-cell text-body-secondary"><?= $goes_html ?></td>
                    <td class="text-end">
                        <div class="dropdown dropstart">
                            <button class="btn btn-sm btn-light" title="More actions" type="button" data-bs-toggle="dropdown"><i class="fas fa-fw fa-ellipsis-v"></i></button>
                            <div class="dropdown-menu">
                                <a class="dropdown-item ajax-modal" href="#" data-modal-url="<?= $edit_url ?>">
                                    <i class="fas fa-fw fa-edit text-secondary me-2"></i>Edit
                                </a>
                                <?php if ($problem === '') { ?>
                                    <a class="dropdown-item" href="<?= escapeHtml(customLinkHref($row['custom_link_uri'])) ?>" target="_blank" rel="noopener noreferrer" title="Try it, in a new tab">
                                        <i class="fas fa-fw fa-external-link-alt text-secondary me-2"></i>Open
                                    </a>
                                <?php } ?>
                                <a class="dropdown-item" href="post.php?copy_custom_link=<?= $custom_link_id ?>&csrf_token=<?= $csrf ?>" title="The same link again - to put it in another menu too">
                                    <i class="fas fa-fw fa-copy text-secondary me-2"></i>Copy
                                </a>
                                <?php if ($can_reorder) { ?>
                                    <?php if ($position > 0) { ?>
                                        <a class="dropdown-item" href="post.php?move_custom_link=<?= $custom_link_id ?>&direction=up&csrf_token=<?= $csrf ?>">
                                            <i class="fas fa-fw fa-arrow-up text-secondary me-2"></i>Move up
                                        </a>
                                    <?php } ?>
                                    <?php if ($position < $num_rows[0] - 1) { ?>
                                        <a class="dropdown-item" href="post.php?move_custom_link=<?= $custom_link_id ?>&direction=down&csrf_token=<?= $csrf ?>">
                                            <i class="fas fa-fw fa-arrow-down text-secondary me-2"></i>Move down
                                        </a>
                                    <?php } ?>
                                <?php } ?>
                                <div class="dropdown-divider"></div>
                                <?php if ($row['custom_link_archived_at']) { ?>
                                    <a class="dropdown-item" href="post.php?restore_custom_link=<?= $custom_link_id ?>&csrf_token=<?= $csrf ?>" title="Back at the end of the <?= escapeHtml($location_config['label']) ?>">
                                        <i class="fas fa-fw fa-box-open text-secondary me-2"></i>Restore
                                    </a>
                                <?php } else { ?>
                                    <a class="dropdown-item text-danger" href="post.php?archive_custom_link=<?= $custom_link_id ?>&csrf_token=<?= $csrf ?>" title="Kept, but no longer shown">
                                        <i class="fas fa-fw fa-archive me-2"></i>Archive
                                    </a>
                                <?php } ?>
                                <a class="dropdown-item text-danger text-bold confirm-link" href="post.php?delete_custom_link=<?= $custom_link_id ?>&csrf_token=<?= $csrf ?>"
                                    data-confirm-title="Delete <?= $name ?>?" data-confirm-text="It goes from the <?= escapeHtml($location_config['label']) ?> for good. Archive it instead to keep it." data-confirm-button="Delete">
                                    <i class="fas fa-fw fa-trash me-2"></i>Delete
                                </a>
                            </div>
                        </div>
                    </td>
                </tr>
            <?php } ?>
            </tbody>
        </table>
    </div>
    <?php if ($can_reorder) { ?>
        <div class="small text-body-secondary px-3 py-2 border-top"><i class="fas fa-fw fa-grip-vertical me-1"></i>Drag them into the order the <?= escapeHtml($locations[$show_location]['label']) ?> shows them in.</div>
    <?php } elseif ($show_location && $q !== '') { ?>
        <div class="small text-body-secondary px-3 py-2 border-top">Clear the search to drag them into order.</div>
    <?php } elseif ($show === '' && $num_rows[0] > 1 && $sort === 'custom_link_order') { ?>
        <div class="small text-body-secondary px-3 py-2 border-top">In the order each menu shows them - open a menu's tab to drag them into another.</div>
    <?php } ?>
    <?php
    $empty_state = [
        'thing' => 'custom links',
        'icon' => 'fa-external-link-alt',
        'filtered' => $q !== '',
        'archived' => $show === 'archived',
        'views' => ['show'],
        'view' => $show_location ? [
            'title' => 'Nothing in the ' . escapeHtml($locations[$show_location]['label']) . ' yet',
            'icon' => $locations[$show_location]['icon'],
            'text' => escapeHtml($locations[$show_location]['about']) . '.',
        ] : [],
        'text' => 'Your own links in ITFlow\'s menus - your RMM, your documentation, a page for booking time with you in the client portal.',
        'actions' => [['label' => 'New Link', 'modal' => $add_url]],
    ];
    require_once "../includes/filter_footer.php";
    ?>
</div>

<?php if ($can_reorder) { ?>
    <script src="/libs/SortableJS/Sortable.min.js"></script>
    <script>
        (function () {
            // Dragged into order - saved as soon as one is dropped, in their place among the rest (offset - the ones on the pages before);
            // a failure reloads the page to show the order kept
            const body = document.querySelector('[data-custom-link-sortable]');
            new Sortable(body, {
                handle: '[data-custom-link-grip]',
                animation: 150,
                onEnd: function (event) {
                    if (event.oldIndex === event.newIndex) {
                        return;
                    }
                    const ids = Array.from(body.querySelectorAll('tr[data-custom-link-id]')).map(function (row) {
                        return row.dataset.customLinkId;
                    });
                    itflowPost('post.php', { reorder_custom_links: 1, csrf_token: '<?= $csrf ?>', location: body.dataset.customLinkLocation, offset: body.dataset.customLinkOffset, custom_link_ids: ids })
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
