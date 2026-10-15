<?php

/*
 * Projects - every project the agent can reach, or one client's. Each row
 * says how far along it is (tickets done, tasks done), the time logged
 * against the hours budgeted, when it is due, who manages it and when its
 * tickets last moved - with a red or amber edge when it is late, over
 * budget or has stalled. The pills along the top are the ones to look at:
 * mine, overdue, due soon, stalled, over budget and ready to close.
 *
 * Old links still work: ?status=1 (completed) and ?archived=1.
 */

// Default Column Sortby/Order Filter
$sort = "project_due";
$order = "ASC";

// If client_id is in URI then show client Side Bar and client header
if (isset($_GET['client_id'])) {
    require_once "includes/inc_all_client.php";
    $client_query = "AND project_client_id = $client_id";
    $client_url = "client_id=$client_id&";
} else {
    require_once "includes/inc_all.php";
    $client_query = '';
    $client_url = '';
}

// Perms
enforceUserPermission('module_support');

$can_edit = projectCan(2);
$can_delete = projectCan(3);

// Old query strings, into the ones this page reads
if (!isset($_GET['show'])) {
    if (($_GET['archived'] ?? '') === '1') {
        $_GET['show'] = 'archived';
    } elseif (($_GET['status'] ?? '') === '1') {
        $_GET['show'] = 'completed';
    }
}
unset($_GET['status'], $_GET['archived']);

// filter_header.php fills in canned_date=custom when no range was asked for - not a filter, so not carried into links
if (($_GET['canned_date'] ?? '') === 'custom' && empty($_GET['dtf'])) {
    unset($_GET['canned_date']);
}
$get_copy = $_GET;
unset($get_copy['sort'], $get_copy['order']);
$url_query_strings_sort = http_build_query($get_copy);

// Client (all clients) - 'internal' for ours
$client_filter = '';
if (!$client_url && isset($_GET['client']) && $_GET['client'] !== '') {
    $client_filter = $_GET['client'] === 'internal' ? 'internal' : intval($_GET['client']);
    $client_query = "AND project_client_id = " . ($client_filter === 'internal' ? 0 : $client_filter);
}

// Manager - an agent, or 'none'
$manager_filter = '';
if (isset($_GET['manager']) && $_GET['manager'] !== '') {
    $manager_filter = $_GET['manager'] === 'none' ? 'none' : intval($_GET['manager']);
}
$manager_query = $manager_filter === '' ? '' : 'AND project_manager = ' . ($manager_filter === 'none' ? 0 : $manager_filter);

// Created - filter_header.php resolves the canned ranges into $dtf / $dtt
$date_filter_active = (isset($_GET['canned_date']) && !in_array($_GET['canned_date'], ['custom', 'alltime'], true))
    || (!empty($_GET['dtf']) && $_GET['dtf'] !== '1970-01-01');
$date_query = $date_filter_active ? "AND DATE(project_created_at) BETWEEN '$dtf' AND '$dtt'" : '';

$filters = "(CONCAT(project_prefix, project_number) LIKE '%$q%' OR project_name LIKE '%$q%' OR project_description LIKE '%$q%'
    OR client_name LIKE '%$q%' OR user_name LIKE '%$q%' OR contact_name LIKE '%$q%')
    " . clientScopeSql('project_client_id') . " $client_query $manager_query $date_query";

$joins = "LEFT JOIN clients ON client_id = project_client_id
    LEFT JOIN users ON user_id = project_manager
    LEFT JOIN contacts ON contact_id = project_contact_id AND project_contact_id > 0";

// The views - Over budget only once someone uses budgets
$budgets_in_use = (bool) mysqli_num_rows(mysqli_query($mysqli, "SELECT 1 FROM projects WHERE project_budget_hours > 0 LIMIT 1"));
$shows = projectViews($budgets_in_use);
$show = isset($shows[$_GET['show'] ?? '']) ? ($_GET['show'] ?? '') : '';
$show_query = $shows[$show]['where'] ? 'AND ' . $shows[$show]['where'] : '';

// Only these sort - an unknown column would take the page down. Completed ones, newest done first.
if (!isset($_GET['sort']) && in_array($show, ['completed', 'archived'], true)) {
    $sort = 'project_completed_at';
    $order = 'DESC';
    $disp = 'ASC';
    $order_icon = "<i class='fas fa-sort-up'></i>";
}
$sortable_columns = [
    'project_due' => "projects.project_due IS NULL, projects.project_due $order, project_name ASC",
    'project_name' => "project_name $order",
    'project_number' => "project_number $order",
    'client_name' => "project_client_id = 0 $order, client_name $order, project_name ASC",
    'user_name' => "project_manager = 0 $order, user_name $order, project_name ASC",
    'project_created_at' => "project_created_at $order",
    'project_completed_at' => "project_completed_at IS NULL, project_completed_at $order",
    'activity' => "COALESCE(project_last_activity, project_created_at) $order",
];
if (!isset($sortable_columns[$sort])) {
    $sort = 'project_due';
}

$sql = mysqli_query($mysqli, "SELECT SQL_CALC_FOUND_ROWS projects.*, client_name, user_name, user_avatar, contact_name, contact_photo,
        " . projectLastActivitySql() . " AS project_last_activity
    FROM projects $joins
    WHERE $filters $show_query
    ORDER BY {$sortable_columns[$sort]}, project_id DESC
    LIMIT $record_from, $record_to");

$num_rows = mysqli_fetch_row(mysqli_query($mysqli, "SELECT FOUND_ROWS()"));

$projects = [];
while ($row = mysqli_fetch_assoc($sql)) {
    $projects[intval($row['project_id'])] = $row;
}
$stats = projectStats(array_keys($projects));

// How many of each, for the pills - over what the filters leave
$count_sql = [];
foreach ($shows as $key => $s) {
    $count_sql[] = $s['where'] === '' ? 'COUNT(*)' : "COALESCE(SUM({$s['where']}), 0)";
}
$row = mysqli_fetch_row(mysqli_query($mysqli, "SELECT " . implode(', ', $count_sql) . " FROM projects $joins WHERE $filters"));
$counts = [];
foreach (array_keys($shows) as $i => $key) {
    $counts[$key] = intval($row[$i]);
}

$filter_url = function ($changes) {
    $params = array_merge($_GET, $changes);
    unset($params['page']);
    return '?' . http_build_query(array_filter($params, function ($v) { return $v !== '' && $v !== null; }));
};

$sort_link = function ($column, $label) use ($url_query_strings_sort, $sort, $disp, $order_icon) {
    return '<a class="text-reset text-decoration-none" href="?' . escapeHtml($url_query_strings_sort) . '&amp;sort=' . $column . '&amp;order=' . $disp . '">'
        . $label . ($sort === $column ? " $order_icon" : '') . '</a>';
};

$hidden_filter_count = $date_filter_active ? 1 : 0;

?>

<div class="card">
    <div class="card-header bg-dark py-2">
        <h3 class="card-title mt-2"><i class="fas fa-fw fa-project-diagram me-2"></i>Projects</h3>
        <div class="card-tools">
            <div class="btn-group">
                <?php if ($can_edit) { ?>
                    <button type="button" class="btn btn-primary ajax-modal" data-modal-size="lg" data-modal-url="modals/project/project_add.php?<?= $client_url ?>">
                        <i class="fas fa-plus"></i><span class="d-none d-lg-inline ms-2">New Project</span>
                    </button>
                <?php } ?>
                <button type="button" class="btn btn-primary dropdown-toggle <?= $can_edit ? 'dropdown-toggle-split' : '' ?>" data-bs-toggle="dropdown"><?= $can_edit ? '' : '<i class="fas fa-ellipsis-h"></i>' ?></button>
                <div class="dropdown-menu dropdown-menu-end">
                    <a class="dropdown-item text-dark ajax-modal" href="#" data-modal-url="<?= buildExportModalUrl('modals/project/project_export.php', ['client_id', 'client', 'manager', 'show', 'q']) ?>">
                        <i class="fa fa-fw fa-download me-2"></i>Export
                    </a>
                    <?php if ($session_is_admin) { ?>
                        <a class="dropdown-item" href="/admin/project_templates.php">
                            <i class="fa fa-fw fa-cubes me-2"></i>Project templates
                        </a>
                    <?php } ?>
                </div>
            </div>
        </div>
    </div>

    <div class="card-header py-3">
        <form autocomplete="off">
            <?php if ($client_url) { ?>
                <input type="hidden" name="client_id" value="<?= $client_id ?>">
            <?php } ?>
            <input type="hidden" name="show" value="<?= escapeHtml($show) ?>">
            <div class="row g-2 align-items-center">
                <div class="<?= $client_url ? 'col-lg-5' : 'col-lg-4' ?> col-md-6">
                    <div class="input-group">
                        <input type="search" class="form-control" name="q" value="<?php if (isset($q)) { echo stripslashes(escapeHtml($q)); } ?>" placeholder="Search projects..." title="Number, name, description, client, contact, manager">
                        <button class="btn <?= $hidden_filter_count ? 'btn-warning' : 'btn-outline-secondary' ?>" type="button" data-bs-toggle="collapse" data-bs-target="#advancedFilter" title="More filters" aria-expanded="<?= $hidden_filter_count ? 'true' : 'false' ?>">
                            <i class="fas fa-sliders-h"></i><?php if ($hidden_filter_count) { ?><span class="ms-1"><?= $hidden_filter_count ?></span><?php } ?>
                        </button>
                        <button class="btn btn-dark" title="Search"><i class="fa fa-search"></i></button>
                    </div>
                </div>
                <div class="<?= $client_url ? 'col-lg-3' : 'col-lg-2' ?> col-md-6">
                    <select class="form-select select2" name="manager" onchange="this.form.submit()">
                        <option value="">- Any manager -</option>
                        <option value="none" <?= $manager_filter === 'none' ? 'selected' : '' ?> data-icon="fas fa-user-slash">No manager</option>
                        <?php
                        $manager_filter_id = is_int($manager_filter) ? $manager_filter : 0;
                        $sql_managers = mysqli_query($mysqli, "SELECT user_id, user_name, user_avatar FROM users WHERE user_type = 1
                            AND (EXISTS (SELECT 1 FROM projects WHERE project_manager = user_id) OR user_id = $manager_filter_id OR (user_archived_at IS NULL AND user_status = 1))
                            ORDER BY user_id = " . intval($session_user_id) . " DESC, user_name ASC");
                        while ($row = mysqli_fetch_assoc($sql_managers)) {
                            $user_id = intval($row['user_id']); ?>
                            <option value="<?= $user_id ?>" <?= $manager_filter === $user_id ? 'selected' : '' ?><?= selectOptionUser($row['user_id'], $row['user_name'], $row['user_avatar'] ?? '') ?>><?= escapeHtml($row['user_name']) ?><?= $user_id === intval($session_user_id) ? ' (me)' : '' ?></option>
                        <?php } ?>
                    </select>
                </div>
                <?php if (!$client_url) { ?>
                    <div class="col-lg-3 col-md-6">
                        <select class="form-select select2" name="client" onchange="this.form.submit()">
                            <option value="">- All Clients -</option>
                            <option value="internal" <?= $client_filter === 'internal' ? 'selected' : '' ?> data-icon="fas fa-building">Internal (no client)</option>
                            <?php
                            $client_filter_id = is_int($client_filter) ? $client_filter : 0;
                            $sql_clients_filter = mysqli_query($mysqli, "SELECT client_id, client_name, client_abbreviation FROM clients
                                WHERE (EXISTS (SELECT 1 FROM projects WHERE project_client_id = client_id) OR client_id = $client_filter_id) " . clientScopeSql('clients.client_id') . "
                                ORDER BY client_name ASC");
                            while ($row = mysqli_fetch_assoc($sql_clients_filter)) {
                                $client_id_filter = intval($row['client_id']); ?>
                                <option <?= $client_filter === $client_id_filter ? 'selected' : '' ?> value="<?= $client_id_filter ?>"<?= selectOptionClient($row['client_id'], $row['client_name'], $row['client_abbreviation'] ?? '') ?>><?= escapeHtml($row['client_name']) ?></option>
                            <?php } ?>
                        </select>
                    </div>
                <?php } ?>
                <div class="<?= $client_url ? 'col-lg-4' : 'col-lg-3' ?> col-md-6">
                    <div class="d-flex justify-content-end gap-2">
                        <?php if ($can_edit) { ?>
                            <div class="dropdown" id="bulkActionButton" hidden>
                                <button class="btn btn-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                    <i class="fas fa-fw fa-layer-group me-2"></i>Bulk Action (<span id="selectedCount">0</span>)
                                </button>
                                <div class="dropdown-menu dropdown-menu-end">
                                    <a class="dropdown-item ajax-modal" href="#" data-modal-url="modals/project/project_bulk_manager.php" data-bulk="true">
                                        <i class="fas fa-fw fa-user-tie me-2"></i>Set manager
                                    </a>
                                    <a class="dropdown-item ajax-modal" href="#" data-modal-url="modals/project/project_bulk_due.php" data-bulk="true">
                                        <i class="fas fa-fw fa-calendar-day me-2"></i>Set due date
                                    </a>
                                    <div class="dropdown-divider"></div>
                                    <button class="dropdown-item confirm-link" type="submit" form="bulkActions" name="bulk_complete_projects"
                                        data-confirm-text="Each one with no open tickets is marked complete. Any with open tickets stay as they are.">
                                        <i class="fas fa-fw fa-check me-2"></i>Mark complete
                                    </button>
                                    <button class="dropdown-item" type="submit" form="bulkActions" name="bulk_reopen_projects">
                                        <i class="fas fa-fw fa-redo me-2"></i>Reopen
                                    </button>
                                    <button class="dropdown-item" type="submit" form="bulkActions" name="bulk_archive_projects">
                                        <i class="fas fa-fw fa-archive me-2"></i>Archive
                                    </button>
                                    <button class="dropdown-item" type="submit" form="bulkActions" name="bulk_restore_projects">
                                        <i class="fas fa-fw fa-box-open me-2"></i>Restore
                                    </button>
                                    <?php if ($can_delete) { ?>
                                        <div class="dropdown-divider"></div>
                                        <button class="dropdown-item text-danger text-bold confirm-link" type="submit" form="bulkActions" name="bulk_delete_projects"
                                            data-confirm-text="The projects go for good. Their tickets stay, no longer part of a project.">
                                            <i class="fas fa-fw fa-trash me-2"></i>Delete
                                        </button>
                                    <?php } ?>
                                </div>
                            </div>
                        <?php } ?>
                    </div>
                </div>
            </div>

            <div class="collapse <?= $hidden_filter_count ? 'show' : '' ?>" id="advancedFilter">
                <div class="row g-2 mt-1">
                    <div class="col-lg-3 col-md-6">
                        <label class="form-label small text-body-secondary mb-1">Created</label>
                        <input type="text" id="dateFilter" class="form-control" autocomplete="off" placeholder="Any time">
                        <input type="hidden" name="canned_date" id="canned_date" value="<?= escapeHtml($_GET['canned_date'] ?? '') ?>">
                        <input type="hidden" name="dtf" id="dtf" value="<?= escapeHtml($date_filter_active ? $dtf : '') ?>">
                        <input type="hidden" name="dtt" id="dtt" value="<?= escapeHtml($date_filter_active ? $dtt : '') ?>">
                    </div>
                </div>
            </div>
        </form>

        <!-- The views - counted over what the filters above leave -->
        <nav class="nav nav-pills itflow-asset-tabs mt-2 flex-nowrap overflow-auto">
            <?php foreach ($shows as $key => $s) {
                $alarm = $counts[$key] && !empty($s['alarm']) && $show !== $key ? $s['alarm'] : ''; ?>
                <?php if ($key === 'completed') { ?><span class="vr mx-1 my-1"></span><?php } ?>
                <a class="nav-link text-nowrap <?= $show === $key ? 'active' : '' ?>" href="<?= escapeHtml($filter_url(['show' => $key, 'sort' => null, 'order' => null])) ?>"<?= !empty($s['title']) ? ' title="' . escapeHtml($s['title']) . '"' : '' ?>>
                    <?php if (!empty($s['icon'])) { ?><i class="fas fa-fw <?= $s['icon'] ?> me-1"></i><?php } ?><?= $s['label'] ?>
                    <span class="badge rounded-pill ms-1 <?= $alarm ? "text-bg-$alarm" : '' ?>"><?= $counts[$key] ?></span>
                </a>
            <?php } ?>
        </nav>

        <?php if ($date_filter_active) { ?>
            <div class="d-flex flex-wrap align-items-center gap-1 mt-2">
                <span class="badge rounded-pill bg-body-secondary text-body fw-normal border py-2 px-2">
                    <span class="text-body-secondary">Created:</span> <?= escapeHtml("$dtf to $dtt") ?>
                    <a href="<?= escapeHtml($filter_url(['canned_date' => null, 'dtf' => null, 'dtt' => null])) ?>" class="text-danger ms-1" title="Remove this filter"><i class="fas fa-times"></i></a>
                </span>
            </div>
        <?php } ?>
    </div>

    <form id="bulkActions" action="post.php" method="post">
        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 itflow-asset-table itflow-ticket-list itflow-project-list">
                <thead class="<?php if (!$projects) { echo "d-none"; } ?> text-nowrap table-light">
                <tr>
                    <?php if ($can_edit) { ?>
                        <th class="checkbox-column border-end">
                            <div class="form-check">
                                <input class="form-check-input" id="selectAllCheckbox" type="checkbox" onclick="checkAll(this)" title="Select all on this page">
                            </div>
                        </th>
                    <?php } ?>
                    <th><?= $sort_link('project_name', 'Project') ?></th>
                    <th><?= $client_url ? 'Contact' : $sort_link('client_name', 'Client') ?></th>
                    <th>Progress</th>
                    <th><?= $budgets_in_use ? 'Time / budget' : 'Time' ?></th>
                    <th><?= in_array($show, ['completed', 'archived'], true) ? $sort_link('project_completed_at', 'Done') : $sort_link('project_due', 'Due') ?></th>
                    <th><?= $sort_link('user_name', 'Manager') ?></th>
                    <th class="d-none d-xxl-table-cell"><?= $sort_link('activity', 'Activity') ?></th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($projects as $project_id => $row) {
                    $row_client_id = intval($row['project_client_id']);
                    $s = $stats[$project_id];
                    $is_open = projectIsOpen($row);
                    $state = projectState($row);
                    $flags = projectFlags($row, $s);
                    $flag_keys = array_column($flags, 'key');
                    $edge = array_intersect($flag_keys, ['overdue', 'over_budget']) ? 'danger' : (array_intersect($flag_keys, ['stalled', 'soon']) ? 'warning' : '');
                    $url = escapeHtml(projectUrl($project_id, $client_url ? $client_id : $row_client_id));
                    $reference = escapeHtml(projectReference($row));
                    $manager = formatAssignee($row['project_manager'], $row['user_name'], $row['user_avatar'], false, 'No manager');
                    $description = trim(strtok((string) $row['project_description'], "\n"));
                    $last = $row['project_last_activity'];
                    ?>
                    <tr class="itflow-ticket-row <?= $edge ? "is-$edge" : '' ?> <?= $is_open ? '' : 'is-done' ?>"<?= $flags ? ' title="' . escapeHtml(implode('. ', array_column($flags, 'text'))) . '"' : '' ?>>
                        <?php if ($can_edit) { ?>
                            <td class="checkbox-column bg-light border-end">
                                <div class="form-check">
                                    <input class="form-check-input bulk-select" type="checkbox" name="project_ids[]" value="<?= $project_id ?>" aria-label="Select <?= $reference ?>">
                                </div>
                            </td>
                        <?php } ?>

                        <!-- What it is -->
                        <td class="itflow-ticket-main itflow-project-main">
                            <a class="<?= $is_open ? 'fw-semibold text-body' : 'text-body-secondary' ?> itflow-ticket-subject" href="<?= $url ?>"><?= escapeHtml($row['project_name']) ?></a>
                            <div class="itflow-ticket-meta small text-body-secondary">
                                <a class="font-monospace text-body-secondary" href="<?= $url ?>"><?= $reference ?></a>
                                <?php if ($state !== 'active') { ?><?= projectStatePill($row) ?><?php } ?>
                                <?php if (in_array('ready', $flag_keys, true)) { ?>
                                    <span class="text-success-emphasis text-nowrap" title="Every ticket is resolved or closed"><i class="fas fa-flag-checkered me-1"></i>Ready to close</span>
                                <?php } ?>
                                <?php if (in_array('stalled', $flag_keys, true)) { ?>
                                    <span class="text-warning-emphasis text-nowrap"><i class="fas fa-hourglass-half me-1"></i>Stalled</span>
                                <?php } ?>
                            </div>
                            <?php if ($description !== '') { ?>
                                <div class="small text-body-secondary text-truncate itflow-project-desc" title="<?= escapeHtml($description) ?>"><?= escapeHtml($description) ?></div>
                            <?php } ?>
                            <!-- On a phone the other columns are off to the side - the essentials come along -->
                            <div class="d-md-none d-flex flex-wrap align-items-center gap-2 mt-1 small">
                                <?= projectDuePill($row) ?>
                                <?php if ($s['tickets']) { ?><span class="text-body-secondary"><?= $s['done'] ?>/<?= $s['tickets'] ?> tickets done</span><?php } ?>
                                <?php if (!$client_url) { ?><span class="text-body-secondary text-truncate"><?= $row_client_id ? escapeHtml($row['client_name']) : 'Internal' ?></span><?php } ?>
                            </div>
                        </td>

                        <!-- Who it is for -->
                        <td class="itflow-ticket-who-cell">
                            <?php if (!$client_url) { ?>
                                <?php if ($row_client_id) { ?>
                                    <a class="d-block text-truncate text-body fw-semibold small" href="projects.php?client_id=<?= $row_client_id ?>"><?= escapeHtml($row['client_name']) ?></a>
                                <?php } else { ?>
                                    <div class="small text-body-secondary"><i class="fas fa-building me-1"></i>Internal</div>
                                <?php } ?>
                            <?php } ?>
                            <?php if (intval($row['project_contact_id']) && $row['contact_name'] !== null) { ?>
                                <div class="small text-truncate"><?= contactLink($row_client_id, $row['project_contact_id'], $row['contact_name'], $row['contact_photo'] ?? '') ?></div>
                            <?php } elseif ($client_url) { ?>
                                <span class="small text-body-secondary">-</span>
                            <?php } ?>
                        </td>

                        <td class="itflow-project-progress-cell"><?= projectProgressHtml($s) ?></td>

                        <td class="itflow-project-progress-cell">
                            <?= projectTimeHtml($row, $s) ?>
                            <?php if ($s['unbilled_seconds']) { ?>
                                <div class="small text-body-secondary text-nowrap mt-1" title="Billable time on tickets not invoiced yet"><i class="fas fa-receipt me-1"></i><?= projectHoursText($s['unbilled_seconds']) ?> unbilled</div>
                            <?php } ?>
                        </td>

                        <td class="text-nowrap">
                            <?= projectDuePill($row) ?: '<span class="text-body-secondary">-</span>' ?>
                            <?php if ($state === 'upcoming') { ?>
                                <div class="small text-body-secondary mt-1"><i class="far fa-calendar me-1"></i>Starts <?= escapeHtml(date('M j', strtotime($row['project_start']))) ?></div>
                            <?php } elseif ($is_open && $row['project_due'] && $row['project_due'] < date('Y-m-d')) { ?>
                                <div class="small text-body-secondary mt-1">Was due <?= escapeHtml(date('M j', strtotime($row['project_due']))) ?></div>
                            <?php } ?>
                        </td>

                        <td class="text-nowrap">
                            <?= $can_edit && $is_open ? quickPick('project_manager', $project_id, (string) intval($row['project_manager']), $manager) : $manager ?>
                        </td>

                        <td class="small text-nowrap d-none d-xxl-table-cell">
                            <?php if ($last) { ?>
                                <span title="A ticket last moved <?= escapeHtml($last) ?>"><?= timeAgo($last) ?></span>
                                <?php if ($s['open']) { ?><div class="text-body-secondary"><?= $s['open'] ?> open</div><?php } ?>
                            <?php } else { ?>
                                <span class="text-body-secondary">No tickets yet</span>
                            <?php } ?>
                        </td>

                        <td class="text-end">
                            <div class="dropdown dropstart">
                                <button class="btn btn-sm btn-light" title="More actions" type="button" data-bs-toggle="dropdown"><i class="fas fa-fw fa-ellipsis-v"></i></button>
                                <div class="dropdown-menu">
                                    <a class="dropdown-item ajax-modal" href="#" data-modal-size="lg" data-modal-url="modals/project/project.php?id=<?= $project_id ?>">
                                        <i class="fas fa-fw fa-eye text-secondary me-2"></i>Quick look
                                    </a>
                                    <a class="dropdown-item" href="<?= $url ?>" target="_blank">
                                        <i class="fas fa-fw fa-external-link-alt text-secondary me-2"></i>Open in a new tab
                                    </a>
                                    <?php if ($can_edit) { ?>
                                        <?php if ($is_open) { ?>
                                            <a class="dropdown-item ajax-modal" href="#" data-modal-size="lg" data-modal-url="modals/ticket/ticket_add.php?<?= $row_client_id ? "client_id=$row_client_id&" : '' ?>project_id=<?= $project_id ?>">
                                                <i class="fas fa-fw fa-life-ring text-secondary me-2"></i>New ticket
                                            </a>
                                        <?php } ?>
                                        <?php if (!$row['project_archived_at']) { ?>
                                            <a class="dropdown-item ajax-modal" href="#" data-modal-size="lg" data-modal-url="modals/project/project_edit.php?id=<?= $project_id ?>">
                                                <i class="fas fa-fw fa-edit text-secondary me-2"></i>Edit
                                            </a>
                                        <?php } ?>
                                        <div class="dropdown-divider"></div>
                                        <?php if ($is_open) { ?>
                                            <?php if ($s['open']) { ?>
                                                <span class="dropdown-item disabled" title="Resolve or close its tickets first"><i class="fas fa-fw fa-check me-2"></i>Mark complete <small>(<?= $s['open'] ?> open)</small></span>
                                            <?php } else { ?>
                                                <a class="dropdown-item confirm-link" href="post.php?complete_project=<?= $project_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>" data-confirm-text="<?= $reference ?> is marked complete.">
                                                    <i class="fas fa-fw fa-check text-success me-2"></i>Mark complete
                                                </a>
                                            <?php } ?>
                                        <?php } elseif ($state === 'completed') { ?>
                                            <a class="dropdown-item" href="post.php?reopen_project=<?= $project_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>">
                                                <i class="fas fa-fw fa-redo text-secondary me-2"></i>Reopen
                                            </a>
                                        <?php } ?>
                                        <?php if ($row['project_archived_at']) { ?>
                                            <a class="dropdown-item" href="post.php?restore_project=<?= $project_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>">
                                                <i class="fas fa-fw fa-box-open text-secondary me-2"></i>Restore
                                            </a>
                                        <?php } else { ?>
                                            <a class="dropdown-item text-danger confirm-link" href="post.php?archive_project=<?= $project_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>" data-confirm-text="<?= $reference ?> moves to Archived. Its tickets stay as they are.">
                                                <i class="fas fa-fw fa-archive me-2"></i>Archive
                                            </a>
                                        <?php } ?>
                                        <?php if ($can_delete) { ?>
                                            <a class="dropdown-item text-danger text-bold confirm-link" href="post.php?delete_project=<?= $project_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>"
                                                data-confirm-text="<?= $reference ?> goes for good. Its tickets stay, no longer part of a project.">
                                                <i class="fas fa-fw fa-trash me-2"></i>Delete
                                            </a>
                                        <?php } ?>
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
    <?php if (!$projects) { ?>
        <div class="itflow-asset-empty">
            <i class="fas fa-project-diagram fa-2x mb-2 opacity-50"></i>
            <div><?= $q !== '' || $show || $manager_filter !== '' || $client_filter !== '' || $date_filter_active ? 'No projects match.' : 'No projects yet - onboarding a new client or a server refresh is a good first one.' ?></div>
        </div>
    <?php } ?>
    <?php require_once "../includes/filter_footer.php"; ?>
</div>

<script src="../js/bulk_actions.js"></script>

<?php
require_once "../includes/footer.php";
