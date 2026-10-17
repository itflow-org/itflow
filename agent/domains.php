<?php

/*
 * Domains - one client's, or every domain the agent can see from All Client
 * Docs. Starred first. Each row has when it expires (and whether it renews
 * by itself), who registers it and who hosts DNS, the website and email, and
 * how its email is protected (SPF, DMARC, DKIM). The pills along the top
 * count and filter the ones to act on: expiring soon, expired, email gaps.
 */

// Default Column Sortby Filter
$sort = "domain_name";
$order = "ASC";

// If client_id is in URI then show client Side Bar and client header
if (isset($_GET['client_id'])) {
    require_once "includes/inc_all_client.php";
    $client_query = "AND domain_client_id = $client_id";
    $client_url = "client_id=$client_id&";
    $archived = isset($_GET['archived']) && $_GET['archived'] == 1 ? 1 : 0;
    $archive_query = $archived ? "domain_archived_at IS NOT NULL" : "domain_archived_at IS NULL";
} else {
    require_once "includes/inc_client_overview_all.php";
    $client_query = '';
    $client_url = '';
    $archived = isset($_GET['archived']) && $_GET['archived'] == 1 ? 1 : 0;
    $archive_query = $archived ? "(client_archived_at IS NOT NULL OR domain_archived_at IS NOT NULL)" : "(client_archived_at IS NULL AND domain_archived_at IS NULL)";
}

// Perms
enforceUserPermission('module_support');

$can_edit = domainCan(2);
$can_delete = domainCan(3) && !empty($config_destructive_deletes_enable);

// Client Filter (all clients)
$client = '';
if (!$client_url && !empty($_GET['client'])) {
    $client = intval($_GET['client']);
    $client_query = "AND domain_client_id = $client";
}

// Vendor Filter - the registrar or any of the hosts
$vendor_filter = intval($_GET['vendor'] ?? 0);
$vendor_query = $vendor_filter ? "AND $vendor_filter IN (domain_registrar, domain_dnshost, domain_webhost, domain_mailhost)" : '';

// Tags Filter - a domain with any of them
if (isset($_GET['tags']) && is_array($_GET['tags']) && !empty($_GET['tags'])) {
    $tag_filter = implode(",", array_map('intval', $_GET['tags']));
    $tag_query = "AND domains.domain_id IN (SELECT domain_id FROM domain_tags WHERE tag_id IN ($tag_filter))";
} else {
    $tag_filter = 0;
    $tag_query = '';
}

// Which ones - the pills along the top. The old "Expiring in" filter
// (?expire_days=30 from the dashboard and the expiry notifications) still works.
$soon_days = DOMAIN_SOON_DAYS;
$shows = [
    '' => ['label' => 'All', 'where' => ''],
    'starred' => ['label' => 'Starred', 'icon' => 'fa-star', 'where' => 'domains.domain_favorite = 1'],
    'soon' => ['label' => "Expiring in $soon_days days", 'icon' => 'fa-hourglass-half', 'where' => "domains.domain_expire BETWEEN CURDATE() AND CURDATE() + INTERVAL $soon_days DAY"],
    'expired' => ['label' => 'Expired', 'icon' => 'fa-calendar-times', 'where' => 'domains.domain_expire < CURDATE()'],
    'noauto' => ['label' => 'No auto-renew', 'icon' => 'fa-hand-paper', 'where' => 'domains.domain_auto_renew = 0'],
    'mail' => ['label' => 'Email gaps', 'icon' => 'fa-envelope-open-text', 'where' => domainMailGapSql()],
];
$show = isset($shows[$_GET['show'] ?? '']) ? ($_GET['show'] ?? '') : '';
$show_query = $shows[$show]['where'] ? 'AND ' . $shows[$show]['where'] : '';

$expire_days = $_GET['expire_days'] ?? '';
$expire_query = '';
if ($expire_days === 'expired') {
    $expire_query = 'AND domains.domain_expire < CURDATE()';
} elseif (intval($expire_days) > 0) {
    $expire_query = 'AND domains.domain_expire BETWEEN CURDATE() AND CURDATE() + INTERVAL ' . intval($expire_days) . ' DAY';
}

// Only these sort - filter_header.php will hand through any lowercase word, and
// an unknown column takes the page down with a mysqli exception
$sortable_columns = ['domain_name' => 'domains.domain_name', 'domain_expire' => 'domains.domain_expire', 'registrar_name' => 'registrar.vendor_name',
    'client_name' => 'client_name', 'domain_created_at' => 'domains.domain_created_at', 'domain_refreshed_at' => 'domains.domain_refreshed_at'];
if (!isset($sortable_columns[$sort])) {
    $sort = 'domain_name';
}
// Domains without an expiry go last whichever way the expiry is sorted
$sort_sql = ($sort === 'domain_expire' ? 'domains.domain_expire IS NULL, ' : '') . $sortable_columns[$sort] . " $order";

[$domain_columns, $domain_joins] = domainSelectSql();
$search_query = "AND (domains.domain_name LIKE '%$q%' OR domains.domain_description LIKE '%$q%' OR domains.domain_notes LIKE '%$q%'
    OR registrar.vendor_name LIKE '%$q%' OR dnshost.vendor_name LIKE '%$q%' OR mailhost.vendor_name LIKE '%$q%' OR webhost.vendor_name LIKE '%$q%' OR client_name LIKE '%$q%'
    OR domains.domain_id IN (SELECT domain_id FROM domain_tags JOIN tags USING (tag_id) WHERE tag_name LIKE '%$q%'))";
$scope_query = clientScopeSql('domain_client_id');
$filters = "$archive_query $tag_query $vendor_query $search_query $expire_query $scope_query $client_query";

$sql = mysqli_query($mysqli, "SELECT SQL_CALC_FOUND_ROWS $domain_columns
    FROM domains $domain_joins
    WHERE $filters $show_query
    ORDER BY domains.domain_favorite DESC, $sort_sql, domains.domain_name ASC LIMIT $record_from, $record_to");

$num_rows = mysqli_fetch_row(mysqli_query($mysqli, "SELECT FOUND_ROWS()"));

$domains = [];
while ($row = mysqli_fetch_assoc($sql)) {
    $domains[intval($row['domain_id'])] = $row;
}
$domains_tags = tagsForRecords('domain', array_keys($domains));

// How many of each, for the pills - everything in scope, before the pill itself
$count_sql = [];
foreach ($shows as $key => $s) {
    $count_sql[] = $key === '' ? 'COUNT(*)' : "COALESCE(SUM({$s['where']}), 0)";
}
$row = mysqli_fetch_row(mysqli_query($mysqli, "SELECT " . implode(', ', $count_sql) . " FROM domains $domain_joins WHERE $filters"));
$counts = [];
foreach (array_keys($shows) as $i => $key) {
    $counts[$key] = intval($row[$i]);
}

$filter_url = function ($changes) {
    $params = array_merge($_GET, $changes);
    unset($params['page']);
    return '?' . http_build_query(array_filter($params, function ($v) { return $v !== '' && $v !== null; }));
};

// Vendors for the filter - the ones registering or hosting a domain in this scope
$sql_vendors_filter = mysqli_query($mysqli, "SELECT DISTINCT vendor_id, vendor_name, vendor_description, vendor_client_id FROM vendors
    JOIN domains ON vendor_id IN (domain_registrar, domain_dnshost, domain_webhost, domain_mailhost)
    LEFT JOIN clients ON client_id = domain_client_id
    WHERE $archive_query $scope_query $client_query
    ORDER BY vendor_name ASC");

// Vendors the bulk "set registrar or host" offers - the client's and ours, or only ours across clients
$bulk_vendor_options = $can_edit ? domainVendorChoices($client_url ? $client_id : 0) : [];

?>

<div class="card">
    <div class="card-header bg-dark py-2">
        <h3 class="card-title mt-2"><i class="fa fa-fw fa-globe me-2"></i>Domains</h3>
        <div class="card-tools">
            <?php if ($can_edit || $num_rows[0]) { ?>
            <div class="btn-group">
                <?php if ($can_edit) { ?>
                    <button type="button" class="btn btn-primary ajax-modal" data-modal-size="lg" data-modal-url="modals/domain/domain_add.php?<?= $client_url ?>">
                        <i class="fas fa-plus me-2"></i>New Domain
                    </button>
                <?php } ?>
                <?php if ($num_rows[0] > 0) { ?>
                    <button type="button" class="btn btn-primary dropdown-toggle <?= $can_edit ? 'dropdown-toggle-split' : '' ?>" data-bs-toggle="dropdown"><?= $can_edit ? '' : '<i class="fas fa-ellipsis-h"></i>' ?></button>
                    <div class="dropdown-menu dropdown-menu-end">
                        <a class="dropdown-item text-dark ajax-modal" href="#"
                            data-modal-url="<?= buildExportModalUrl('modals/domain/domain_export.php', ['client_id', 'client', 'expire_days', 'archived', 'q', 'tags', 'vendor']) ?>">
                            <i class="fa fa-fw fa-download me-2"></i>Export
                        </a>
                    </div>
                <?php } ?>
            </div>
            <?php } ?>
        </div>
    </div>

    <div class="card-header py-3">
        <form autocomplete="off">
            <?php if ($client_url) { ?>
            <input type="hidden" name="client_id" value="<?= $client_id ?>">
            <?php } ?>
            <input type="hidden" name="archived" value="<?= $archived ?>">
            <input type="hidden" name="show" value="<?= escapeHtml($show) ?>">
            <div class="row g-2 align-items-center">
                <div class="col-lg-3 col-md-6">
                    <div class="input-group">
                        <input type="search" class="form-control" name="q" value="<?php if (isset($q)) { echo stripslashes(escapeHtml($q)); } ?>" placeholder="Search domain, registrar, host, notes...">
                        <button class="btn btn-dark" title="Search"><i class="fa fa-search"></i></button>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <select onchange="this.form.submit()" class="form-select select2" name="tags[]" data-placeholder="- Tags -" multiple>
                        <?php
                        // Tags in use on domains in this scope, plus any already picked
                        $sql_tags_filter = mysqli_query($mysqli, "
                            SELECT tag_id, tag_name, tag_color, tag_icon FROM tags
                            WHERE tag_type = 7
                            AND (
                                EXISTS (SELECT 1 FROM domain_tags JOIN domains ON domains.domain_id = domain_tags.domain_id
                                    WHERE domain_tags.tag_id = tags.tag_id " . clientScopeSql('domain_client_id') . " $client_query)
                                OR tag_id IN ($tag_filter)
                            )
                            ORDER BY tag_name ASC
                        ");
                        while ($row = mysqli_fetch_assoc($sql_tags_filter)) {
                            $tag_id = intval($row['tag_id']); ?>
                            <option value="<?= $tag_id ?>" <?php if (isset($_GET['tags']) && is_array($_GET['tags']) && in_array($tag_id, $_GET['tags'])) { echo 'selected'; } ?><?= selectOptionColor($row['tag_color'] ?? '', $row['tag_icon'] ?? '') ?>><?= escapeHtml($row['tag_name']) ?></option>
                        <?php } ?>
                    </select>
                </div>
                <div class="<?= $client_url ? 'col-lg-4' : 'col-lg-2' ?> col-md-6">
                        <select class="form-select select2" name="vendor" onchange="this.form.submit()">
                            <option value="">- Any registrar or host -</option>
                            <?php while ($row = mysqli_fetch_assoc($sql_vendors_filter)) {
                                $vendor_id = intval($row['vendor_id']); ?>
                                <option <?= $vendor_filter === $vendor_id ? 'selected' : '' ?> value="<?= $vendor_id ?>"<?= selectOptionVendor($row['vendor_name'], intval($row['vendor_client_id']) === 0 ? 'Ours' : $row['vendor_description']) ?>><?= escapeHtml($row['vendor_name']) ?></option>
                            <?php } ?>
                        </select>
                </div>
                <?php if (!$client_url) { ?>
                <div class="col-lg-2 col-md-6">
                            <select class="form-select select2" name="client" onchange="this.form.submit()">
                                <option value="" <?php if ($client == "") { echo "selected"; } ?>>- All Clients -</option>
                                <?php
                                $sql_clients_filter = mysqli_query($mysqli, "
                                    SELECT DISTINCT client_id, client_name, client_abbreviation
                                    FROM clients
                                    JOIN domains ON domain_client_id = client_id
                                    WHERE $archive_query
                                    " . clientScopeSql('clients.client_id') . "
                                    ORDER BY client_name ASC
                                ");
                                while ($row = mysqli_fetch_assoc($sql_clients_filter)) {
                                    $client_id_filter = intval($row['client_id']); ?>
                                    <option <?php if ($client == $client_id_filter) { echo "selected"; } ?> value="<?= $client_id_filter ?>"<?= selectOptionClient($row['client_id'], $row['client_name'], $row['client_abbreviation'] ?? '') ?>><?= escapeHtml($row['client_name']) ?></option>
                                <?php } ?>
                            </select>
                </div>
                <?php } ?>
                <div class="col-lg-2 col-md-6">
                    <div class="d-flex justify-content-end gap-2">
                        <a href="<?= escapeHtml($filter_url(['archived' => $archived ? '' : 1])) ?>"
                            class="btn <?= $archived ? 'btn-dark' : 'btn-outline-dark' ?>" title="<?= $archived ? 'Back to active domains' : 'Show archived domains' ?>">
                            <i class="fa fa-fw fa-archive"></i><span class="d-lg-none ms-2">Archived</span>
                        </a>
                        <?php if ($can_edit) { ?>
                        <div class="dropdown" id="bulkActionButton" hidden>
                            <button class="btn btn-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                <i class="fas fa-fw fa-layer-group me-2"></i>Bulk Action (<span id="selectedCount">0</span>)
                            </button>
                            <div class="dropdown-menu dropdown-menu-end">
                                <?php if (!$archived) { ?>
                                    <button class="dropdown-item" type="submit" form="bulkActions" name="bulk_refresh_domains">
                                        <i class="fas fa-fw fa-sync-alt me-2"></i>Look up now
                                    </button>
                                    <a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#domainBulkVendorModal">
                                        <i class="fas fa-fw fa-building me-2"></i>Set registrar or host
                                    </a>
                                <?php } ?>
                                <a class="dropdown-item" href="#" data-tag-picker data-tag-entity="domain" data-tag-bulk data-csrf="<?= $_SESSION['csrf_token'] ?>">
                                    <i class="fas fa-fw fa-tags me-2"></i>Tags
                                </a>
                                <button class="dropdown-item" type="submit" form="bulkActions" name="bulk_favorite_domains">
                                    <i class="fas fa-fw fa-star text-warning me-2"></i>Star
                                </button>
                                <button class="dropdown-item" type="submit" form="bulkActions" name="bulk_unfavorite_domains">
                                    <i class="far fa-fw fa-star me-2"></i>Unstar
                                </button>
                                <div class="dropdown-divider"></div>
                                <?php if ($archived) { ?>
                                    <button class="dropdown-item text-info" type="submit" form="bulkActions" name="bulk_restore_domains">
                                        <i class="fas fa-fw fa-redo me-2"></i>Restore
                                    </button>
                                    <?php if ($can_delete) { ?>
                                        <button class="dropdown-item text-danger text-bold confirm-link" type="submit" form="bulkActions" name="bulk_delete_domains"
                                            data-confirm-text="The domains go for good, with their history. Services and certificates stop pointing at them.">
                                            <i class="fas fa-fw fa-trash me-2"></i>Delete
                                        </button>
                                    <?php } ?>
                                <?php } else { ?>
                                    <button class="dropdown-item text-danger confirm-link" type="submit" form="bulkActions" name="bulk_archive_domains">
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
        <!-- Which ones - counted over what the filters above leave -->
        <nav class="nav nav-pills itflow-asset-tabs mt-2 flex-nowrap overflow-auto">
            <?php foreach ($shows as $key => $s) {
                $alarm = $counts[$key] && in_array($key, ['soon', 'expired', 'mail'], true); ?>
                <a class="nav-link text-nowrap <?= $show === $key ? 'active' : '' ?>" href="<?= escapeHtml($filter_url(['show' => $key, 'expire_days' => ''])) ?>">
                    <?php if (!empty($s['icon'])) { ?><i class="fas fa-fw <?= $s['icon'] ?> me-1"></i><?php } ?><?= $s['label'] ?>
                    <span class="badge rounded-pill ms-1 <?= $alarm ? ($key === 'expired' ? 'text-bg-danger' : 'text-bg-warning') : '' ?>"><?= $counts[$key] ?></span>
                </a>
            <?php } ?>
        </nav>
        <?php if ($expire_query) { ?>
            <div class="small text-body-secondary mt-2">
                <i class="fas fa-filter me-1"></i><?= $expire_days === 'expired' ? 'Expired' : 'Expiring in ' . intval($expire_days) . ' days' ?>
                <a class="ms-2" href="<?= escapeHtml($filter_url(['expire_days' => ''])) ?>">Clear</a>
            </div>
        <?php } ?>
    </div>

    <form id="bulkActions" action="post.php" method="post">
        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 itflow-asset-table">
                <thead class="<?php if (!$num_rows[0]) { echo "d-none"; } ?> text-nowrap table-light">
                <tr>
                    <?php if ($can_edit) { ?>
                    <th class="checkbox-column border-end">
                        <div class="form-check">
                            <input class="form-check-input" id="selectAllCheckbox" type="checkbox" onclick="checkAll(this)" title="Select all on this page">
                        </div>
                    </th>
                    <?php } ?>
                    <th>
                        <a class="text-reset text-decoration-none" href="?<?= $url_query_strings_sort ?>&sort=domain_name&order=<?= $disp ?>">
                            Domain <?php if ($sort == 'domain_name') { echo $order_icon; } ?>
                        </a>
                    </th>
                    <th>
                        <a class="text-reset text-decoration-none" href="?<?= $url_query_strings_sort ?>&sort=domain_expire&order=<?= $disp ?>">
                            Expires <?php if ($sort == 'domain_expire') { echo $order_icon; } ?>
                        </a>
                    </th>
                    <th>
                        <a class="text-reset text-decoration-none" href="?<?= $url_query_strings_sort ?>&sort=registrar_name&order=<?= $disp ?>">
                            Registrar and hosts <?php if ($sort == 'registrar_name') { echo $order_icon; } ?>
                        </a>
                    </th>
                    <th>Email</th>
                    <?php if (!$client_url) { ?>
                    <th>
                        <a class="text-reset text-decoration-none" href="?<?= $url_query_strings_sort ?>&sort=client_name&order=<?= $disp ?>">
                            Client <?php if ($sort == 'client_name') { echo $order_icon; } ?>
                        </a>
                    </th>
                    <?php } ?>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                <?php

                foreach ($domains as $domain_id => $row) {
                    $row_client_id = intval($row['domain_client_id']);
                    $domain_name = escapeHtml($row['domain_name']);
                    $domain_description = escapeHtml($row['domain_description']);
                    $domain_favorite = intval($row['domain_favorite']);
                    $domain_archived_at = escapeHtml($row['domain_archived_at']);
                    $domain_tags = $domains_tags[$domain_id] ?? [];
                    $expiry = domainExpiry($row['domain_expire'], $row['domain_auto_renew']);
                    $quick_url = "modals/domain/domain.php?id=$domain_id";

                    ?>
                    <tr>
                        <?php if ($can_edit) { ?>
                        <td class="checkbox-column bg-light border-end">
                            <div class="form-check">
                                <input class="form-check-input bulk-select" type="checkbox" name="domain_ids[]" value="<?= $domain_id ?>" aria-label="Select <?= $domain_name ?>">
                            </div>
                        </td>
                        <?php } ?>
                        <td class="itflow-asset-name-cell">
                            <div class="d-flex align-items-center gap-3">
                                <?= domainTile() ?>
                                <div class="min-w-0">
                                    <a class="fw-semibold text-body ajax-modal" href="#" data-modal-size="lg" data-modal-url="<?= $quick_url ?>"><?= $domain_name ?></a>
                                    <?php if ($domain_favorite) { ?><i class="fas fa-star text-warning small ms-1" title="Starred"></i><?php } ?>
                                    <?php if ($domain_archived_at) { ?><span class="badge rounded-pill text-bg-secondary fw-normal ms-1">Archived</span><?php } ?>
                                    <?php if ($domain_description) { ?>
                                        <div class="small text-body-secondary text-truncate itflow-asset-desc" title="<?= $domain_description ?>"><?= $domain_description ?></div>
                                    <?php } ?>
                                    <?php if ($domain_tags || $can_edit) { ?>
                                        <div class="mt-1"><?= tagPills('domain', $domain_id, $domain_tags, "domains.php?$client_url", $can_edit) ?></div>
                                    <?php } ?>
                                </div>
                            </div>
                        </td>
                        <td class="text-nowrap">
                            <?= domainExpiryPill($expiry) ?>
                            <?php if ($expiry) { ?>
                                <div class="small text-body-secondary mt-1"><?= escapeHtml($expiry['date']) ?></div>
                            <?php } ?>
                        </td>
                        <td class="small itflow-domain-who">
                            <?php $who = domainVendorsHtml($row); ?>
                            <?= $who ? '<div class="d-flex flex-column gap-1">' . $who . '</div>' : '<span class="text-body-secondary">-</span>' ?>
                        </td>
                        <td class="itflow-domain-mail"><?= domainMailPillsHtml(array_diff_key(domainMailChecks($row), ['mx' => 1])) ?></td>
                        <?php if (!$client_url) { ?>
                        <td><a href="domains.php?client_id=<?= $row_client_id ?>"><?= escapeHtml($row['client_name']) ?></a></td>
                        <?php } ?>
                        <td class="text-end">
                            <div class="dropdown dropstart">
                                <button class="btn btn-sm btn-light" title="More actions" type="button" data-bs-toggle="dropdown"><i class="fas fa-fw fa-ellipsis-v"></i></button>
                                <div class="dropdown-menu">
                                    <a class="dropdown-item ajax-modal" href="#" data-modal-size="lg" data-modal-url="<?= $quick_url ?>">
                                        <i class="fas fa-fw fa-eye text-secondary me-2"></i>Quick look
                                    </a>
                                    <a class="dropdown-item" href="https://<?= $domain_name ?>" target="_blank" rel="noopener noreferrer">
                                        <i class="fas fa-fw fa-external-link-alt text-secondary me-2"></i>Open the website
                                    </a>
                                    <?php if ($can_edit && !$domain_archived_at) { ?>
                                        <a class="dropdown-item ajax-modal" href="#" data-modal-size="lg" data-modal-url="modals/domain/domain_edit.php?id=<?= $domain_id ?>">
                                            <i class="fas fa-fw fa-edit text-secondary me-2"></i>Edit
                                        </a>
                                        <a class="dropdown-item" href="post.php?refresh_domain=<?= $domain_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>">
                                            <i class="fas fa-fw fa-sync-alt text-secondary me-2"></i>Look up now
                                        </a>
                                        <a class="dropdown-item" href="post.php?<?= $domain_favorite ? 'unfavorite_domain' : 'favorite_domain' ?>=<?= $domain_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>">
                                            <i class="<?= $domain_favorite ? 'far' : 'fas' ?> fa-fw fa-star text-secondary me-2"></i><?= $domain_favorite ? 'Unstar' : 'Star' ?>
                                        </a>
                                    <?php } ?>
                                    <?php if ($can_edit) { ?>
                                        <div class="dropdown-divider"></div>
                                        <?php if ($domain_archived_at) { ?>
                                            <a class="dropdown-item" href="post.php?restore_domain=<?= $domain_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>">
                                                <i class="fas fa-fw fa-redo text-secondary me-2"></i>Restore
                                            </a>
                                            <?php if ($can_delete) { ?>
                                                <a class="dropdown-item text-danger text-bold confirm-link" href="post.php?delete_domain=<?= $domain_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>"
                                                    data-confirm-text="<?= $domain_name ?> goes for good, with its history. Services and certificates stop pointing at it.">
                                                    <i class="fas fa-fw fa-trash me-2"></i>Delete
                                                </a>
                                            <?php } ?>
                                        <?php } else { ?>
                                            <a class="dropdown-item text-danger confirm-link" href="post.php?archive_domain=<?= $domain_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>">
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
    <?php
    $empty_state = [
        'thing' => 'domains',
        'icon' => 'fa-globe',
        'filtered' => $q || $tag_query || $vendor_query || $expire_query,
        'views' => ['show'],
        'view' => emptyStatePill($shows, $show, '', ['soon', 'expired', 'noauto', 'mail']),
        'text' => 'Their domain names, where each is registered and when it expires.',
        'actions' => $can_edit ? [['label' => 'New Domain', 'modal' => "modals/domain/domain_add.php?$client_url", 'size' => 'lg']] : [],
    ];
    require_once "../includes/filter_footer.php";
    ?>
</div>

<?php if ($can_edit && !$archived) { ?>
<!-- Bulk: who registers or hosts the domains picked -->
<div class="modal fade" id="domainBulkVendorModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-dark">
                <h5 class="modal-title"><i class="fas fa-fw fa-building me-2"></i>Set registrar or host</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <label class="form-label">What they do</label>
                <div class="btn-group w-100 mb-3" role="group">
                    <?php foreach (DOMAIN_ROLES as $role => $info) { ?>
                        <input type="radio" class="btn-check" form="bulkActions" name="role" value="<?= $role ?>" id="domainBulkRole<?= $role ?>" <?= $role === 'registrar' ? 'checked' : '' ?>>
                        <label class="btn btn-outline-secondary" for="domainBulkRole<?= $role ?>"><i class="fas <?= $info['icon'] ?> me-1"></i><?= $info['label'] ?></label>
                    <?php } ?>
                </div>
                <label class="form-label">Vendor</label>
                <select class="form-select select2" form="bulkActions" name="vendor_id">
                    <option value="0">- Nobody -</option>
                    <?php foreach ($bulk_vendor_options as $vendor) { ?>
                        <option value="<?= intval($vendor['vendor_id']) ?>"<?= selectOptionVendor($vendor['vendor_name'], intval($vendor['vendor_client_id']) === 0 ? 'Ours' : $vendor['vendor_description']) ?>><?= escapeHtml($vendor['vendor_name']) ?></option>
                    <?php } ?>
                </select>
                <div class="form-text"><?= $client_url ? "The client's vendors, and ours." : 'Across clients only our own vendors can be set - open a client to use theirs.' ?></div>
            </div>
            <div class="modal-footer">
                <button type="submit" form="bulkActions" name="bulk_set_domain_vendor" class="btn btn-primary text-bold"><i class="fa fa-check me-2"></i>Set it</button>
                <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fa fa-times me-2"></i>Cancel</button>
            </div>
        </div>
    </div>
</div>
<?php } ?>

<?php if (!empty($_GET['open'])) { ?>
    <script>
        // Opened from search or the overview - straight to that domain's quick look
        document.addEventListener('DOMContentLoaded', function () {
            var open = document.createElement('a');
            open.className = 'ajax-modal';
            open.href = '#';
            open.dataset.modalSize = 'lg';
            open.dataset.modalUrl = 'modals/domain/domain.php?id=<?= intval($_GET['open']) ?>';
            document.body.appendChild(open);
            open.click();
            open.remove();
        });
    </script>
<?php } ?>

<script src="../js/bulk_actions.js"></script>

<?php require_once "../includes/footer.php";
