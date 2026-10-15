<?php

/*
 * Services - one client's, or every service the agent can see from All
 * Client Docs. What a client runs on, most important first: each row says
 * what it depends on (assets, logins, domains, certificates, people,
 * vendors, documents), whether any of that needs attention, any open
 * tickets about it, and when it is next due for review - with a red or
 * amber edge when something it depends on is expired, out of action or
 * archived. The pills along the top are the ones to look at: needs
 * attention, review due, high importance.
 */

// Default Column Sortby Filter - most important first
$sort = "service_importance";
$order = "ASC";

// If client_id is in URI then show client Side Bar and client header
if (isset($_GET['client_id'])) {
    require_once "includes/inc_all_client.php";
    $client_query = "AND service_client_id = $client_id";
    $client_url = "client_id=$client_id&";
} else {
    require_once "includes/inc_client_overview_all.php";
    $client_query = 'AND clients.client_archived_at IS NULL';
    $client_url = '';
}

// Perms
enforceUserPermission('module_support');

// filter_header.php fills in canned_date=custom when no range was asked for - not a filter here, so not carried into links
if (($_GET['canned_date'] ?? '') === 'custom' && empty($_GET['dtf'])) {
    unset($_GET['canned_date']);
}

$can_edit = serviceCan(2);
$can_delete = serviceCan(3) && !empty($config_destructive_deletes_enable);

// Client (all clients)
$client_filter = 0;
if (!$client_url && !empty($_GET['client'])) {
    $client_filter = intval($_GET['client']);
    $client_query .= " AND service_client_id = $client_filter";
}

// Importance and category
$importance_filter = in_array($_GET['importance'] ?? '', SERVICE_IMPORTANCE, true) ? $_GET['importance'] : '';
$importance_query = $importance_filter ? "AND service_importance = '$importance_filter'" : '';
$category_filter = trim((string) ($_GET['category'] ?? ''));
$category_query = $category_filter !== '' ? "AND service_category = '" . escapeSql($category_filter) . "'" : '';

$filters = "(service_name LIKE '%$q%' OR service_description LIKE '%$q%' OR service_category LIKE '%$q%' OR service_notes LIKE '%$q%' OR client_name LIKE '%$q%')
    " . clientScopeSql('service_client_id') . " $client_query $importance_query $category_query";

$joins = "LEFT JOIN clients ON client_id = service_client_id";

// The views - counted over what the filters leave
$shows = serviceViews();
$show = isset($shows[$_GET['show'] ?? '']) ? ($_GET['show'] ?? '') : '';
$show_query = $shows[$show]['where'] ? 'AND ' . $shows[$show]['where'] : '';

// Only these sort - an unknown column would take the page down
$sortable_columns = [
    'service_importance' => "FIELD(service_importance, 'High', 'Medium', 'Low') $order, service_name ASC",
    'service_name' => "service_name $order",
    'service_category' => "service_category = '' $order, service_category $order, service_name ASC",
    'client_name' => "client_name $order, service_name ASC",
    'service_review_due' => "service_review_due IS NULL, service_review_due $order, service_name ASC",
    'service_updated_at' => "COALESCE(service_updated_at, service_created_at) $order",
];
if (!isset($sortable_columns[$sort])) {
    $sort = 'service_importance';
}

$sql = mysqli_query($mysqli, "SELECT SQL_CALC_FOUND_ROWS services.*, client_name FROM services $joins
    WHERE $filters $show_query
    ORDER BY services.service_favorite DESC, {$sortable_columns[$sort]}, service_id DESC
    LIMIT $record_from, $record_to");

$num_rows = mysqli_fetch_row(mysqli_query($mysqli, "SELECT FOUND_ROWS()"));

$services = [];
while ($row = mysqli_fetch_assoc($sql)) {
    $services[intval($row['service_id'])] = $row;
}
$counts = serviceCounts(array_keys($services));
$issues = serviceIssues(array_keys($services));

// How many of each, for the pills
$count_sql = [];
foreach ($shows as $key => $s) {
    $count_sql[] = $s['where'] === '' ? 'COUNT(*)' : "COALESCE(SUM({$s['where']}), 0)";
}
$row = mysqli_fetch_row(mysqli_query($mysqli, "SELECT " . implode(', ', $count_sql) . " FROM services $joins WHERE $filters"));
$view_counts = [];
foreach (array_keys($shows) as $i => $key) {
    $view_counts[$key] = intval($row[$i]);
}

// Categories in use in this scope, for the filter
$categories = [];
$sql_categories = mysqli_query($mysqli, "SELECT DISTINCT service_category FROM services $joins
    WHERE service_category <> '' " . clientScopeSql('service_client_id') . ($client_url ? " AND service_client_id = $client_id" : '') . " ORDER BY service_category");
while ($row = mysqli_fetch_row($sql_categories)) {
    $categories[] = $row[0];
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

$link_icons = ['asset' => ['fa-desktop', 'asset', 'assets'], 'credential' => ['fa-key', 'login', 'logins'], 'domain' => ['fa-globe', 'domain', 'domains'],
    'certificate' => ['fa-lock', 'certificate', 'certificates'], 'contact' => ['fa-user', 'person', 'people'], 'vendor' => ['fa-building', 'vendor', 'vendors'],
    'document' => ['fa-file-alt', 'document', 'documents']];

?>

<div class="card">
    <div class="card-header bg-dark py-2">
        <h3 class="card-title mt-2"><i class="fas fa-fw fa-stream me-2"></i>Services</h3>
        <div class="card-tools">
            <div class="btn-group">
                <?php if ($can_edit) { ?>
                    <button type="button" class="btn btn-primary ajax-modal" data-modal-size="lg" data-modal-url="modals/service/service_add.php?<?= $client_url ?>">
                        <i class="fas fa-plus"></i><span class="d-none d-lg-inline ms-2">New Service</span>
                    </button>
                <?php } ?>
                <button type="button" class="btn btn-primary dropdown-toggle <?= $can_edit ? 'dropdown-toggle-split' : '' ?>" data-bs-toggle="dropdown"><?= $can_edit ? '' : '<i class="fas fa-ellipsis-h"></i>' ?></button>
                <div class="dropdown-menu dropdown-menu-end">
                    <a class="dropdown-item text-dark ajax-modal" href="#" data-modal-url="<?= buildExportModalUrl('modals/service/service_export.php', ['client_id', 'client', 'show', 'importance', 'category', 'q']) ?>">
                        <i class="fa fa-fw fa-download me-2"></i>Export
                    </a>
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
                <div class="<?= $client_url ? 'col-lg-4' : 'col-lg-3' ?> col-md-6">
                    <div class="input-group">
                        <input type="search" class="form-control" name="q" value="<?php if (isset($q)) { echo stripslashes(escapeHtml($q)); } ?>" placeholder="Search services..." title="Name, description, category, notes, client">
                        <button class="btn btn-dark" title="Search"><i class="fa fa-search"></i></button>
                    </div>
                </div>
                <div class="col-lg-2 col-md-6">
                    <select class="form-select select2" name="category" onchange="this.form.submit()">
                        <option value="">- Any category -</option>
                        <?php foreach ($categories as $category) { ?>
                            <option value="<?= escapeHtml($category) ?>" <?= $category_filter === $category ? 'selected' : '' ?> data-icon="fas <?= serviceStyle($category)['icon'] ?>"><?= escapeHtml($category) ?></option>
                        <?php } ?>
                    </select>
                </div>
                <div class="col-lg-2 col-md-6">
                    <select class="form-select select2" name="importance" onchange="this.form.submit()">
                        <option value="">- Any importance -</option>
                        <?php foreach (SERVICE_IMPORTANCE as $importance) { ?>
                            <option value="<?= $importance ?>" <?= $importance_filter === $importance ? 'selected' : '' ?>><?= $importance ?></option>
                        <?php } ?>
                    </select>
                </div>
                <?php if (!$client_url) { ?>
                    <div class="col-lg-2 col-md-6">
                        <select class="form-select select2" name="client" onchange="this.form.submit()">
                            <option value="">- All Clients -</option>
                            <?php
                            $sql_clients_filter = mysqli_query($mysqli, "SELECT client_id, client_name, client_abbreviation FROM clients
                                WHERE client_archived_at IS NULL AND (EXISTS (SELECT 1 FROM services WHERE service_client_id = client_id) OR client_id = $client_filter)
                                " . clientScopeSql('clients.client_id') . " ORDER BY client_name ASC");
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
                                    <a class="dropdown-item ajax-modal" href="#" data-modal-url="modals/service/service_bulk_edit.php" data-bulk="true">
                                        <i class="fas fa-fw fa-edit me-2"></i>Importance, category, review
                                    </a>
                                    <button class="dropdown-item confirm-link" type="submit" form="bulkActions" name="bulk_review_services"
                                        data-confirm-text="Each one is marked reviewed today, and its next review set from its schedule.">
                                        <i class="fas fa-fw fa-clipboard-check me-2"></i>Mark reviewed
                                    </button>
                                    <button class="dropdown-item" type="submit" form="bulkActions" name="bulk_favorite_services">
                                        <i class="fas fa-fw fa-star text-warning me-2"></i>Star
                                    </button>
                                    <button class="dropdown-item" type="submit" form="bulkActions" name="bulk_unfavorite_services">
                                        <i class="far fa-fw fa-star me-2"></i>Unstar
                                    </button>
                                    <div class="dropdown-divider"></div>
                                    <?php if ($show === 'archived') { ?>
                                        <button class="dropdown-item" type="submit" form="bulkActions" name="bulk_restore_services">
                                            <i class="fas fa-fw fa-redo me-2"></i>Restore
                                        </button>
                                        <?php if ($can_delete) { ?>
                                            <button class="dropdown-item text-danger text-bold confirm-link" type="submit" form="bulkActions" name="bulk_delete_services"
                                                data-confirm-text="The services go for good. What they linked to - assets, logins, documents - stays.">
                                                <i class="fas fa-fw fa-trash me-2"></i>Delete
                                            </button>
                                        <?php } ?>
                                    <?php } else { ?>
                                        <button class="dropdown-item text-danger confirm-link" type="submit" form="bulkActions" name="bulk_archive_services"
                                            data-confirm-text="They move to Archived, off the lists and counts, until restored.">
                                            <i class="fas fa-fw fa-archive me-2"></i>Archive
                                        </button>
                                    <?php } ?>
                                </div>
                            </div>
                        <?php } ?>
                    </div>
                </div>
            </div>
        </form>

        <!-- The views - counted over what the filters above leave -->
        <nav class="nav nav-pills itflow-asset-tabs mt-2 flex-nowrap overflow-auto">
            <?php foreach ($shows as $key => $s) {
                $alarm = $view_counts[$key] && !empty($s['alarm']) && $show !== $key ? $s['alarm'] : ''; ?>
                <?php if ($key === 'archived') { ?><span class="vr mx-1 my-1"></span><?php } ?>
                <a class="nav-link text-nowrap <?= $show === $key ? 'active' : '' ?>" href="<?= escapeHtml($filter_url(['show' => $key, 'sort' => null, 'order' => null])) ?>"<?= !empty($s['title']) ? ' title="' . escapeHtml($s['title']) . '"' : '' ?>>
                    <?php if (!empty($s['icon'])) { ?><i class="fas fa-fw <?= $s['icon'] ?> me-1"></i><?php } ?><?= $s['label'] ?>
                    <span class="badge rounded-pill ms-1 <?= $alarm ? "text-bg-$alarm" : '' ?>"><?= $view_counts[$key] ?></span>
                </a>
            <?php } ?>
        </nav>
    </div>

    <form id="bulkActions" action="post.php" method="post">
        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 itflow-asset-table itflow-ticket-list itflow-service-list">
                <thead class="<?php if (!$services) { echo "d-none"; } ?> text-nowrap table-light">
                <tr>
                    <?php if ($can_edit) { ?>
                        <th class="checkbox-column border-end">
                            <div class="form-check">
                                <input class="form-check-input" id="selectAllCheckbox" type="checkbox" onclick="checkAll(this)" title="Select all on this page">
                            </div>
                        </th>
                    <?php } ?>
                    <th><?= $sort_link('service_name', 'Service') ?></th>
                    <?php if (!$client_url) { ?><th><?= $sort_link('client_name', 'Client') ?></th><?php } ?>
                    <th><?= $sort_link('service_importance', 'Importance') ?></th>
                    <th class="d-none d-xxl-table-cell">Depends on</th>
                    <th>Attention</th>
                    <th><?= $sort_link('service_review_due', 'Review') ?></th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($services as $service_id => $row) {
                    $row_client_id = intval($row['service_client_id']);
                    $c = $counts[$service_id];
                    $row_issues = $issues[$service_id];
                    $review = serviceReview($row);
                    $is_archived = !empty($row['service_archived_at']);
                    $is_favorite = intval($row['service_favorite']) === 1;
                    $has_danger = in_array('danger', array_column($row_issues, 'tone'), true);
                    $edge = $is_archived ? '' : ($has_danger ? 'danger' : ($row_issues || $review['state'] === 'overdue' ? 'warning' : ''));
                    $url = escapeHtml(serviceUrl($service_id, $row_client_id));
                    $quick_url = "modals/service/service.php?id=$service_id";
                    $name = escapeHtml($row['service_name']);
                    $detail = implode(' · ', array_filter([$row['service_category'], $row['service_description']]));
                    $chips = '';
                    foreach ($link_icons as $type => [$icon, $one, $many]) {
                        if ($c[$type]) {
                            $chips .= '<span class="itflow-contact-chip" title="' . $c[$type] . ' ' . ($c[$type] === 1 ? $one : $many) . '"><i class="fas ' . $icon . '"></i>' . $c[$type] . '</span>';
                        }
                    }
                    ?>
                    <tr class="itflow-ticket-row <?= $edge ? "is-$edge" : '' ?> <?= $is_archived ? 'is-done' : '' ?>">
                        <?php if ($can_edit) { ?>
                            <td class="checkbox-column bg-light border-end">
                                <div class="form-check">
                                    <input class="form-check-input bulk-select" type="checkbox" name="service_ids[]" value="<?= $service_id ?>" aria-label="Select <?= $name ?>">
                                </div>
                            </td>
                        <?php } ?>

                        <!-- What it is -->
                        <td class="itflow-ticket-main itflow-service-main">
                            <div class="d-flex align-items-center gap-3">
                                <?= serviceTile($row['service_category']) ?>
                                <div class="min-w-0">
                                    <a class="<?= $is_archived ? 'text-body-secondary' : 'fw-semibold text-body' ?>" href="<?= $url ?>"><?= $name ?></a>
                                    <?php if ($is_favorite) { ?><i class="fas fa-star text-warning small ms-1" title="Starred"></i><?php } ?>
                                    <?php if ($is_archived) { ?><span class="badge rounded-pill text-bg-secondary fw-normal ms-1">Archived</span><?php } ?>
                                    <?php if ($detail !== '') { ?>
                                        <div class="small text-body-secondary text-truncate itflow-service-desc" title="<?= escapeHtml($detail) ?>"><?= escapeHtml($detail) ?></div>
                                    <?php } ?>
                                    <!-- Narrower screens drop the Depends on column - the chips come along -->
                                    <?php if ($chips) { ?><div class="d-none d-md-flex d-xxl-none flex-wrap gap-1 mt-1"><?= $chips ?></div><?php } ?>
                                    <!-- On a phone the other columns are off to the side - the essentials come along -->
                                    <div class="d-md-none d-flex flex-wrap align-items-center gap-2 mt-1 small">
                                        <?= serviceImportancePill($row['service_importance']) ?>
                                        <?php if ($row_issues) { ?><span class="text-<?= $has_danger ? 'danger' : 'warning' ?>-emphasis"><i class="fas fa-exclamation-triangle me-1"></i><?= count($row_issues) ?> to look at</span><?php } ?>
                                        <?php if (!$client_url) { ?><span class="text-body-secondary text-truncate"><?= escapeHtml($row['client_name']) ?></span><?php } ?>
                                    </div>
                                </div>
                            </div>
                        </td>

                        <?php if (!$client_url) { ?>
                            <td class="itflow-ticket-who-cell"><a class="d-block text-truncate text-body fw-semibold small" href="services.php?client_id=<?= $row_client_id ?>"><?= escapeHtml($row['client_name']) ?></a></td>
                        <?php } ?>

                        <td class="text-nowrap">
                            <?= $can_edit && !$is_archived ? quickPick('service_importance', $service_id, $row['service_importance'], serviceImportancePill($row['service_importance'])) : serviceImportancePill($row['service_importance']) ?>
                        </td>

                        <td class="itflow-service-chips-cell d-none d-xxl-table-cell">
                            <?php if ($chips) { ?>
                                <a class="d-flex flex-wrap gap-1 text-decoration-none itflow-service-chips" href="<?= $url ?>"><?= $chips ?></a>
                            <?php } else { ?>
                                <span class="small text-body-secondary" title="Link the assets, logins and vendors it depends on from its page">Nothing linked</span>
                            <?php } ?>
                        </td>

                        <td class="small itflow-service-attention">
                            <?php if ($row_issues) {
                                $first = $row_issues[0]; ?>
                                <div class="text-<?= $first['tone'] ?>-emphasis text-truncate" title="<?= escapeHtml(implode("\n", array_column($row_issues, 'text'))) ?>">
                                    <i class="fas fa-fw fa-exclamation-<?= $first['tone'] === 'danger' ? 'circle' : 'triangle' ?> me-1"></i><?= escapeHtml($first['text']) ?>
                                </div>
                                <?php if (count($row_issues) > 1) { ?><div class="text-body-secondary">and <?= count($row_issues) - 1 ?> more</div><?php } ?>
                            <?php } ?>
                            <?php if ($c['open_tickets']) { ?>
                                <a class="text-reset text-nowrap" href="<?= $url ?>#tickets"><i class="fas fa-fw fa-life-ring text-body-secondary me-1"></i><?= $c['open_tickets'] ?> open ticket<?= $c['open_tickets'] === 1 ? '' : 's' ?></a>
                            <?php } ?>
                            <?php if (!$row_issues && !$c['open_tickets']) { ?>
                                <span class="text-body-secondary">-</span>
                            <?php } ?>
                        </td>

                        <td class="text-nowrap"><?= serviceReviewPill($review) ?></td>

                        <td class="text-end">
                            <div class="dropdown dropstart">
                                <button class="btn btn-sm btn-light" title="More actions" type="button" data-bs-toggle="dropdown"><i class="fas fa-fw fa-ellipsis-v"></i></button>
                                <div class="dropdown-menu">
                                    <a class="dropdown-item ajax-modal" href="#" data-modal-size="lg" data-modal-url="<?= $quick_url ?>">
                                        <i class="fas fa-fw fa-eye text-secondary me-2"></i>Quick look
                                    </a>
                                    <a class="dropdown-item" href="<?= $url ?>" target="_blank">
                                        <i class="fas fa-fw fa-external-link-alt text-secondary me-2"></i>Open in a new tab
                                    </a>
                                    <?php if ($can_edit && !$is_archived) { ?>
                                        <a class="dropdown-item ajax-modal" href="#" data-modal-size="lg" data-modal-url="<?= escapeHtml('modals/ticket/ticket_add.php?' . http_build_query(['client_id' => $row_client_id, 'service_id' => $service_id])) ?>">
                                            <i class="fas fa-fw fa-life-ring text-secondary me-2"></i>Report a problem
                                        </a>
                                        <a class="dropdown-item ajax-modal" href="#" data-modal-size="lg" data-modal-url="modals/service/service_edit.php?id=<?= $service_id ?>">
                                            <i class="fas fa-fw fa-edit text-secondary me-2"></i>Edit
                                        </a>
                                        <a class="dropdown-item ajax-modal" href="#" data-modal-url="modals/service/service_review.php?id=<?= $service_id ?>">
                                            <i class="fas fa-fw fa-clipboard-check text-secondary me-2"></i>Mark reviewed
                                        </a>
                                        <a class="dropdown-item" href="post.php?<?= $is_favorite ? 'unfavorite_service' : 'favorite_service' ?>=<?= $service_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>">
                                            <i class="<?= $is_favorite ? 'far' : 'fas' ?> fa-fw fa-star text-secondary me-2"></i><?= $is_favorite ? 'Unstar' : 'Star' ?>
                                        </a>
                                    <?php } ?>
                                    <?php if ($can_edit) { ?>
                                        <div class="dropdown-divider"></div>
                                        <?php if ($is_archived) { ?>
                                            <a class="dropdown-item" href="post.php?restore_service=<?= $service_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>">
                                                <i class="fas fa-fw fa-redo text-secondary me-2"></i>Restore
                                            </a>
                                            <?php if ($can_delete) { ?>
                                                <a class="dropdown-item text-danger text-bold confirm-link" href="post.php?delete_service=<?= $service_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>"
                                                    data-confirm-text="<?= $name ?> goes for good. What it linked to - assets, logins, documents - stays.">
                                                    <i class="fas fa-fw fa-trash me-2"></i>Delete
                                                </a>
                                            <?php } ?>
                                        <?php } else { ?>
                                            <a class="dropdown-item text-danger confirm-link" href="post.php?archive_service=<?= $service_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>"
                                                data-confirm-text="<?= $name ?> moves to Archived, off the lists and counts, until restored.">
                                                <i class="fas fa-fw fa-archive me-2"></i>Archive
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
    <?php if (!$services) { ?>
        <div class="itflow-asset-empty">
            <i class="fas fa-stream fa-2x mb-2 opacity-50"></i>
            <div><?= $q !== '' || $show || $importance_filter || $category_filter !== '' || $client_filter ? 'No services match.' : 'No services yet - start with what the client cannot work without: email, backup, their line of business app.' ?></div>
        </div>
    <?php } ?>
    <?php require_once "../includes/filter_footer.php"; ?>
</div>

<script src="../js/bulk_actions.js"></script>

<?php
require_once "../includes/footer.php";
