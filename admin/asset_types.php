<?php

/*
 * Admin > Asset Types - every kind of asset: ITFlow's own, and yours - a
 * Badge Reader, a Vehicle, a Hypervisor - each with its icon, the group it is
 * counted in (its tab on the assets list), its details and how many assets
 * are of it. In use are the ones the type pickers offer, in their order;
 * switched off ones stay on their assets, but can't be picked. Types that are
 * on assets but aren't set up - from an import, the API or an older ITFlow -
 * can be set up from here. The work is functions/asset_type_custom.php; the
 * handlers are admin/post/asset_type.php.
 */

// Default Column Sortby Filter - the order the pickers show them in
$sort = "order";
$order = "ASC";

require_once "includes/inc_all_admin.php";

$views = [
    '' => ['label' => 'In use', 'icon' => 'fa-check-circle', 'title' => 'Offered by the type pickers'],
    'off' => ['label' => 'Switched off', 'icon' => 'fa-power-off', 'title' => 'Kept on their assets, but can\'t be picked'],
];
$show = (string) ($_GET['show'] ?? '');
$show = isset($views[$show]) ? $show : '';

[$asset_counts, $not_set_up] = assetTypeAssetCounts();
$search = mb_strtolower(trim((string) ($_GET['q'] ?? '')));

// Every type as a row, in the pickers' order - searched by name, description, group and details
$rows = [];
$view_counts = array_fill_keys(array_keys($views), 0);
$position = 0;
foreach (assetTypes() as $name => $info) {
    $specs = assetTypeSpecs($name);
    $row = $info + [
        'name' => $name,
        'position' => $position++,
        'assets' => $asset_counts[$name][0] ?? 0,
        'archived' => $asset_counts[$name][1] ?? 0,
        'spec_labels' => array_column($specs, 'label'),
        'own' => count($info['details']),
    ];
    $haystack = mb_strtolower(implode(' ', array_merge([$name, $info['description'], ASSET_TYPE_GROUPS[$info['group']]['label']], $row['spec_labels'])));
    if ($search !== '' && mb_strpos($haystack, $search) === false) {
        continue;
    }
    $view_counts[$info['active'] ? '' : 'off']++;
    if ($info['active'] === ($show === '')) {
        $rows[] = $row;
    }
}

// Only these sort
$sortable_columns = ['order', 'name', 'group', 'assets'];
if (!in_array($sort, $sortable_columns, true)) {
    $sort = 'order';
}
$group_order = array_flip(array_keys(ASSET_TYPE_GROUPS));
usort($rows, function ($a, $b) use ($sort, $order, $group_order) {
    switch ($sort) {
        case 'name':
            $by = strnatcasecmp($a['name'], $b['name']);
            break;
        case 'group':
            $by = $group_order[$a['group']] <=> $group_order[$b['group']];
            break;
        case 'assets':
            $by = $a['assets'] <=> $b['assets'];
            break;
        default:
            $by = $a['position'] <=> $b['position'];
    }
    $by = $order === 'DESC' ? -$by : $by;
    return $by !== 0 ? $by : $a['position'] <=> $b['position'];
});

$num_rows = [count($rows)];
$rows = array_slice($rows, $record_from, $record_to);

$pill_url = function ($view) {
    return '?' . http_build_query(array_filter(['q' => $_GET['q'] ?? '', 'show' => $view]));
};

$csrf = $_SESSION['csrf_token'];

?>

<div class="card">
    <div class="card-header bg-dark py-2">
        <h3 class="card-title mt-2"><i class="fas fa-fw fa-layer-group me-2"></i>Asset Types</h3>
        <div class="card-tools">
            <button type="button" class="btn btn-primary ajax-modal" data-modal-size="lg" data-modal-url="modals/asset_type/asset_type_add.php">
                <i class="fas fa-plus"></i><span class="d-none d-lg-inline ms-2">New Type</span>
            </button>
        </div>
    </div>
    <div class="card-header py-3">
        <form autocomplete="off">
            <?php if ($show !== '') { ?><input type="hidden" name="show" value="<?= escapeHtml($show) ?>"><?php } ?>
            <div class="row g-2 align-items-center">
                <div class="col-lg-5 col-md-6">
                    <div class="input-group">
                        <input type="search" class="form-control" name="q" value="<?php if (isset($q)) { echo stripslashes(escapeHtml($q)); } ?>" placeholder="Search types..." title="Name, description, group or a detail">
                        <button class="btn btn-dark" title="Search"><i class="fas fa-search"></i></button>
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
    <?php if ($not_set_up && $show === '' && $search === '') { ?>
        <!-- On assets, but not a type - an import, the API or an older ITFlow made them; until set up they show as Other -->
        <div class="card-body border-bottom py-2 small d-flex flex-wrap align-items-center gap-2">
            <span class="text-secondary"><i class="fas fa-fw fa-question-circle text-warning-emphasis me-1"></i>On assets, but not set up - they show as Other:</span>
            <?php foreach ($not_set_up as $type_name => [$live, $archived]) { ?>
                <a class="btn btn-sm btn-outline-secondary ajax-modal" href="#" data-modal-size="lg" data-modal-url="modals/asset_type/asset_type_add.php?name=<?= urlencode($type_name) ?>" title="Set it up as a type of its own<?= $archived ? ' - ' . number_format($archived) . ' archived too' : '' ?>">
                    <i class="fas fa-fw fa-plus me-1"></i><?= escapeHtml($type_name) ?> <span class="badge rounded-pill text-bg-light border ms-1"><?= number_format($live + $archived) ?></span>
                </a>
            <?php } ?>
        </div>
    <?php } ?>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light text-nowrap <?php if ($num_rows[0] == 0) { echo "d-none"; } ?>">
            <tr>
                <th class="ps-3"><?= sortLink('name', 'Type') ?></th>
                <th class="d-none d-md-table-cell"><?= sortLink('group', 'Group') ?></th>
                <th class="d-none d-lg-table-cell">Details</th>
                <th class="text-end"><?= sortLink('assets', 'Assets') ?></th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $row) {
                $name = $row['name'];
                $name_html = escapeHtml($name);
                $name_url = urlencode($name);
                $group = ASSET_TYPE_GROUPS[$row['group']];
                $edit_url = $row['builtin'] ? '' : "modals/asset_type/asset_type_edit.php?id=" . intval($row['id']);
                $details = array_slice($row['spec_labels'], 0, 4);
                ?>
                <tr>
                    <td class="ps-3">
                        <div class="d-flex align-items-start gap-3">
                            <span class="itflow-asset-icon itflow-asset-tone-<?= escapeHtml($row['tone']) ?> d-none d-sm-inline-flex" aria-hidden="true"><i class="fas fa-fw <?= escapeHtml($row['icon']) ?>"></i></span>
                            <div class="min-w-0">
                                <div class="d-flex flex-wrap align-items-center gap-2">
                                    <?php if ($edit_url) { ?>
                                        <a class="ajax-modal fw-semibold text-decoration-none" href="#" data-modal-size="lg" data-modal-url="<?= $edit_url ?>" title="Edit <?= $name_html ?>"><?= $name_html ?></a>
                                    <?php } else { ?>
                                        <span class="fw-semibold"><?= $name_html ?></span>
                                        <span class="badge rounded-pill text-bg-light border fw-normal" title="One of ITFlow's own - it can be switched off, but not changed"><i class="fas fa-fw fa-lock text-secondary me-1"></i>Built in</span>
                                    <?php } ?>
                                    <?php if ($row['rack']) { ?>
                                        <span class="small text-secondary" title="Goes in a rack"><i class="fas fa-fw fa-server"></i></span>
                                    <?php } ?>
                                </div>
                                <?php if ($row['description'] !== '') { ?>
                                    <div class="small text-body-secondary mt-1"><?= escapeHtml($row['description']) ?></div>
                                <?php } ?>
                                <div class="d-md-none small text-body-secondary mt-1"><i class="fas fa-fw <?= $group['icon'] ?> me-1"></i><?= escapeHtml($group['label']) ?></div>
                            </div>
                        </div>
                    </td>
                    <td class="d-none d-md-table-cell text-nowrap"><i class="fas fa-fw <?= $group['icon'] ?> text-secondary me-1"></i><?= escapeHtml($group['label']) ?></td>
                    <td class="d-none d-lg-table-cell small">
                        <?php if ($row['spec_labels']) { ?>
                            <span class="text-body-secondary" title="<?= escapeHtml(implode(', ', $row['spec_labels'])) ?>"><?= escapeHtml(implode(', ', $details)) ?><?= count($row['spec_labels']) > count($details) ? ' and ' . (count($row['spec_labels']) - count($details)) . ' more' : '' ?></span>
                            <?php if ($row['own']) { ?>
                                <div class="text-secondary"><i class="fas fa-fw fa-pen-fancy me-1"></i><?= $row['own'] ?> of its own</div>
                            <?php } ?>
                        <?php } else { ?>
                            <span class="text-body-tertiary">None</span>
                        <?php } ?>
                    </td>
                    <td class="text-end text-nowrap">
                        <?php if ($row['assets']) { ?>
                            <span<?= $row['archived'] ? ' title="And ' . number_format($row['archived']) . ' archived"' : '' ?>><?= number_format($row['assets']) ?></span>
                        <?php } else { ?>
                            <span class="text-body-tertiary"<?= $row['archived'] ? ' title="' . number_format($row['archived']) . ' archived"' : '' ?>>0</span>
                        <?php } ?>
                    </td>
                    <td class="text-end">
                        <div class="dropdown dropstart">
                            <button class="btn btn-sm btn-light" title="More actions" type="button" data-bs-toggle="dropdown"><i class="fas fa-fw fa-ellipsis-v"></i></button>
                            <div class="dropdown-menu">
                                <?php if ($edit_url) { ?>
                                    <a class="dropdown-item ajax-modal" href="#" data-modal-size="lg" data-modal-url="<?= $edit_url ?>">
                                        <i class="fas fa-fw fa-edit text-secondary me-2"></i>Edit
                                    </a>
                                    <a class="dropdown-item ajax-modal" href="#" data-modal-size="lg" data-modal-url="modals/asset_type/asset_type_add.php?copy=<?= intval($row['id']) ?>">
                                        <i class="fas fa-fw fa-copy text-secondary me-2"></i>Copy
                                    </a>
                                <?php } else { ?>
                                    <a class="dropdown-item ajax-modal" href="#" data-modal-size="lg" data-modal-url="modals/asset_type/asset_type_add.php?from=<?= $name_url ?>">
                                        <i class="fas fa-fw fa-clone text-secondary me-2"></i>New Type From It
                                    </a>
                                <?php } ?>
                                <?php if ($name !== 'Other') { ?>
                                    <div class="dropdown-divider"></div>
                                    <?php if ($row['active']) { ?>
                                        <a class="dropdown-item" href="post.php?switch_off_asset_type=<?= $name_url ?>&csrf_token=<?= $csrf ?>" title="Kept on its assets, but it can't be picked">
                                            <i class="fas fa-fw fa-power-off text-secondary me-2"></i>Switch Off
                                        </a>
                                    <?php } else { ?>
                                        <a class="dropdown-item" href="post.php?switch_on_asset_type=<?= $name_url ?>&csrf_token=<?= $csrf ?>" title="Back in the type pickers">
                                            <i class="fas fa-fw fa-power-off text-secondary me-2"></i>Switch On
                                        </a>
                                    <?php } ?>
                                <?php } ?>
                                <?php if (!$row['builtin']) { ?>
                                    <a class="dropdown-item text-danger ajax-modal" href="#" data-modal-url="modals/asset_type/asset_type_delete.php?id=<?= intval($row['id']) ?>">
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
    <?php
    $empty_state = [
        'thing' => 'asset types',
        'icon' => 'fa-layer-group',
        'filtered' => $search !== '',
        'views' => ['show'],
        'view' => $show === 'off' ? [
            'title' => 'None switched off',
            'icon' => 'fa-power-off',
            'text' => 'A type you switch off is kept here - its assets keep it, but it can\'t be picked.',
        ] : [],
        'text' => 'Every kind of asset - add your own, like a Badge Reader or a Vehicle.',
        'actions' => [['label' => 'New Type', 'modal' => 'modals/asset_type/asset_type_add.php', 'size' => 'lg']],
    ];
    require_once "../includes/filter_footer.php";
    ?>
</div>

<?php
require_once "../includes/footer.php";
