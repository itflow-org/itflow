<?php

/*
 * Certificates - one client's, or every certificate the agent can see from
 * All Client Docs. Starred first. Each row has the host it is served on and
 * the domain it belongs to, who issued it, when it expires (and whether it
 * renews itself), and what the last check of the host found. The pills along
 * the top count and filter the ones to act on: expiring soon, expired,
 * problems the check found, and the ones nobody is checking.
 */

// Default Column Sortby Filter
$sort = "certificate_name";
$order = "ASC";

// If client_id is in URI then show client Side Bar and client header
if (isset($_GET['client_id'])) {
    require_once "includes/inc_all_client.php";
    $client_query = "AND certificate_client_id = $client_id";
    $client_url = "client_id=$client_id&";
    $archived = isset($_GET['archived']) && $_GET['archived'] == 1 ? 1 : 0;
    $archive_query = $archived ? "certificate_archived_at IS NOT NULL" : "certificate_archived_at IS NULL";
} else {
    require_once "includes/inc_client_overview_all.php";
    $client_query = '';
    $client_url = '';
    $archived = isset($_GET['archived']) && $_GET['archived'] == 1 ? 1 : 0;
    $archive_query = $archived ? "(client_archived_at IS NOT NULL OR certificate_archived_at IS NOT NULL)" : "(client_archived_at IS NULL AND certificate_archived_at IS NULL)";
}

// Perms
enforceUserPermission('module_support');

$can_edit = certificateCan(2);
$can_delete = certificateCan(3) && !empty($config_destructive_deletes_enable);

// Client Filter (all clients)
$client = '';
if (!$client_url && !empty($_GET['client'])) {
    $client = intval($_GET['client']);
    $client_query = "AND certificate_client_id = $client";
}

// Issuer Filter
$issuer_filter = trim((string) ($_GET['issuer'] ?? ''));
$issuer_query = $issuer_filter !== '' ? "AND certificates.certificate_issued_by = '" . escapeSql($issuer_filter) . "'" : '';

// Which ones - the pills along the top. The old "Expiring in" filter
// (?expire_days=30 from links and bookmarks) still works.
$shows = [
    '' => ['label' => 'All', 'where' => ''],
    'starred' => ['label' => 'Starred', 'icon' => 'fa-star', 'where' => 'certificates.certificate_favorite = 1'],
    'soon' => ['label' => 'Expiring soon', 'icon' => 'fa-hourglass-half', 'where' => certificateSoonSql(),
        'title' => 'In the next ' . CERTIFICATE_SOON_DAYS . " days - or 20 for a short-lived one, which should have renewed itself by then"],
    'expired' => ['label' => 'Expired', 'icon' => 'fa-calendar-times', 'where' => 'certificates.certificate_expire < CURDATE()'],
    'problems' => ['label' => 'Problems', 'icon' => 'fa-exclamation-triangle', 'where' => certificateProblemSql(),
        'title' => "The last check found it serving one for another name, an untrusted or expired one - or nothing answered"],
    'unchecked' => ['label' => 'Not checked', 'icon' => 'fa-eye-slash', 'where' => 'certificates.certificate_check_status IS NULL',
        'title' => 'No host to check, or not checked yet'],
];
$show = isset($shows[$_GET['show'] ?? '']) ? ($_GET['show'] ?? '') : '';
$show_query = $shows[$show]['where'] ? 'AND ' . $shows[$show]['where'] : '';

$expire_days = $_GET['expire_days'] ?? '';
$expire_query = '';
if ($expire_days === 'expired') {
    $expire_query = 'AND certificates.certificate_expire < CURDATE()';
} elseif (intval($expire_days) > 0) {
    $expire_query = 'AND certificates.certificate_expire BETWEEN CURDATE() AND CURDATE() + INTERVAL ' . intval($expire_days) . ' DAY';
}

// Only these sort - filter_header.php will hand through any lowercase word, and
// an unknown column takes the page down with a mysqli exception
$sortable_columns = ['certificate_name' => 'certificates.certificate_name', 'certificate_domain' => 'certificates.certificate_domain',
    'certificate_issued_by' => 'certificates.certificate_issued_by', 'certificate_expire' => 'certificates.certificate_expire',
    'certificate_checked_at' => 'certificates.certificate_checked_at', 'client_name' => 'client_name', 'certificate_created_at' => 'certificates.certificate_created_at'];
if (!isset($sortable_columns[$sort])) {
    $sort = 'certificate_name';
}
// Certificates without an expiry (or never checked) go last whichever way it is sorted
$sort_sql = (in_array($sort, ['certificate_expire', 'certificate_checked_at'], true) ? "{$sortable_columns[$sort]} IS NULL, " : '') . $sortable_columns[$sort] . " $order";

[$certificate_columns, $certificate_joins] = certificateSelectSql();
$search_query = "AND (certificates.certificate_name LIKE '%$q%' OR certificates.certificate_domain LIKE '%$q%' OR certificates.certificate_description LIKE '%$q%'
    OR certificates.certificate_issued_by LIKE '%$q%' OR certificates.certificate_names LIKE '%$q%' OR certificates.certificate_fingerprint LIKE '%$q%'
    OR certificates.certificate_notes LIKE '%$q%' OR domains.domain_name LIKE '%$q%' OR client_name LIKE '%$q%')";
$scope_query = clientScopeSql('certificate_client_id');
$filters = "$archive_query $issuer_query $search_query $expire_query $scope_query $client_query";

$sql = mysqli_query($mysqli, "SELECT SQL_CALC_FOUND_ROWS $certificate_columns
    FROM certificates $certificate_joins
    WHERE $filters $show_query
    ORDER BY certificates.certificate_favorite DESC, $sort_sql, certificates.certificate_name ASC LIMIT $record_from, $record_to");

$num_rows = mysqli_fetch_row(mysqli_query($mysqli, "SELECT FOUND_ROWS()"));

$certificates = [];
while ($row = mysqli_fetch_assoc($sql)) {
    $certificates[intval($row['certificate_id'])] = $row;
}

// How many of each, for the pills - everything in scope, before the pill itself
$count_sql = [];
foreach ($shows as $key => $s) {
    $count_sql[] = $key === '' ? 'COUNT(*)' : "COALESCE(SUM({$s['where']}), 0)";
}
$row = mysqli_fetch_row(mysqli_query($mysqli, "SELECT " . implode(', ', $count_sql) . " FROM certificates $certificate_joins WHERE $filters"));
$counts = [];
foreach (array_keys($shows) as $i => $key) {
    $counts[$key] = intval($row[$i]);
}

$filter_url = function ($changes) {
    $params = array_merge($_GET, $changes);
    unset($params['page']);
    return '?' . http_build_query(array_filter($params, function ($v) { return $v !== '' && $v !== null; }));
};

// Issuers for the filter - the ones on a certificate in this scope
$sql_issuers_filter = mysqli_query($mysqli, "SELECT certificates.certificate_issued_by, COUNT(*) AS issued FROM certificates $certificate_joins
    WHERE $archive_query $scope_query $client_query AND certificates.certificate_issued_by <> ''
    GROUP BY certificates.certificate_issued_by ORDER BY certificates.certificate_issued_by ASC");

// What the export starts from - the pill, as the export's own filters
$export_extra = [
    'expire_days' => $show === 'expired' ? 'expired' : ($show === 'soon' ? CERTIFICATE_SOON_DAYS : ''),
    'problems' => $show === 'problems' ? 1 : '',
];

?>

<div class="card">
    <div class="card-header bg-dark py-2">
        <h3 class="card-title mt-2"><i class="fa fa-fw fa-lock me-2"></i>Certificates</h3>
        <div class="card-tools">
            <?php if ($can_edit || $num_rows[0]) { ?>
            <div class="btn-group">
                <?php if ($can_edit) { ?>
                    <button type="button" class="btn btn-primary ajax-modal" data-modal-size="lg" data-modal-url="modals/certificate/certificate_add.php?<?= $client_url ?>">
                        <i class="fas fa-plus me-2"></i>New Certificate
                    </button>
                <?php } ?>
                <?php if ($num_rows[0] > 0) { ?>
                    <button type="button" class="btn btn-primary dropdown-toggle <?= $can_edit ? 'dropdown-toggle-split' : '' ?>" data-bs-toggle="dropdown"><?= $can_edit ? '' : '<i class="fas fa-ellipsis-h"></i>' ?></button>
                    <div class="dropdown-menu dropdown-menu-end">
                        <a class="dropdown-item text-dark ajax-modal" href="#"
                            data-modal-url="<?= buildExportModalUrl('modals/certificate/certificate_export.php', ['client_id', 'client', 'expire_days', 'archived', 'q'], $export_extra) ?>">
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
                <div class="<?= $client_url ? 'col-lg-5' : 'col-lg-4' ?> col-md-6">
                    <div class="input-group">
                        <input type="search" class="form-control" name="q" value="<?php if (isset($q)) { echo stripslashes(escapeHtml($q)); } ?>" placeholder="Search name, host, names covered, issuer...">
                        <button class="btn btn-dark" title="Search"><i class="fa fa-search"></i></button>
                    </div>
                </div>
                <div class="<?= $client_url ? 'col-lg-4' : 'col-lg-3' ?> col-md-6">
                    <select class="form-select select2" name="issuer" onchange="this.form.submit()">
                        <option value="">- Any issuer -</option>
                        <?php while ($row = mysqli_fetch_assoc($sql_issuers_filter)) { ?>
                            <option <?= $issuer_filter === $row['certificate_issued_by'] ? 'selected' : '' ?> value="<?= escapeHtml($row['certificate_issued_by']) ?>" data-icon="fas fa-building" data-description="<?= intval($row['issued']) ?> certificate<?= intval($row['issued']) === 1 ? '' : 's' ?>"><?= escapeHtml($row['certificate_issued_by']) ?></option>
                        <?php } ?>
                    </select>
                </div>
                <?php if (!$client_url) { ?>
                <div class="col-lg-3 col-md-6">
                    <select class="form-select select2" name="client" onchange="this.form.submit()">
                        <option value="" <?php if ($client == "") { echo "selected"; } ?>>- All Clients -</option>
                        <?php
                        $sql_clients_filter = mysqli_query($mysqli, "
                            SELECT DISTINCT client_id, client_name, client_abbreviation
                            FROM clients
                            JOIN certificates ON certificate_client_id = client_id
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
                <div class="<?= $client_url ? 'col-lg-3' : 'col-lg-2' ?> col-md-6">
                    <div class="d-flex justify-content-end gap-2">
                        <a href="<?= escapeHtml($filter_url(['archived' => $archived ? '' : 1])) ?>"
                            class="btn <?= $archived ? 'btn-dark' : 'btn-outline-dark' ?>" title="<?= $archived ? 'Back to active certificates' : 'Show archived certificates' ?>">
                            <i class="fa fa-fw fa-archive"></i><span class="d-lg-none ms-2">Archived</span>
                        </a>
                        <?php if ($can_edit) { ?>
                        <div class="dropdown" id="bulkActionButton" hidden>
                            <button class="btn btn-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                <i class="fas fa-fw fa-layer-group me-2"></i>Bulk Action (<span id="selectedCount">0</span>)
                            </button>
                            <div class="dropdown-menu dropdown-menu-end">
                                <?php if (!$archived) { ?>
                                    <button class="dropdown-item" type="submit" form="bulkActions" name="bulk_check_certificates">
                                        <i class="fas fa-fw fa-sync-alt me-2"></i>Check now
                                    </button>
                                <?php } ?>
                                <button class="dropdown-item" type="submit" form="bulkActions" name="bulk_favorite_certificates">
                                    <i class="fas fa-fw fa-star text-warning me-2"></i>Star
                                </button>
                                <button class="dropdown-item" type="submit" form="bulkActions" name="bulk_unfavorite_certificates">
                                    <i class="far fa-fw fa-star me-2"></i>Unstar
                                </button>
                                <div class="dropdown-divider"></div>
                                <?php if ($archived) { ?>
                                    <button class="dropdown-item text-info" type="submit" form="bulkActions" name="bulk_restore_certificates">
                                        <i class="fas fa-fw fa-redo me-2"></i>Restore
                                    </button>
                                    <?php if ($can_delete) { ?>
                                        <button class="dropdown-item text-danger text-bold confirm-link" type="submit" form="bulkActions" name="bulk_delete_certificates"
                                            data-confirm-text="The certificates go for good, with their history. Services stop pointing at them.">
                                            <i class="fas fa-fw fa-trash me-2"></i>Delete
                                        </button>
                                    <?php } ?>
                                <?php } else { ?>
                                    <button class="dropdown-item text-danger confirm-link" type="submit" form="bulkActions" name="bulk_archive_certificates">
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
                $alarm = $counts[$key] && in_array($key, ['soon', 'expired', 'problems'], true); ?>
                <a class="nav-link text-nowrap <?= $show === $key ? 'active' : '' ?>" href="<?= escapeHtml($filter_url(['show' => $key, 'expire_days' => ''])) ?>"<?= !empty($s['title']) ? ' title="' . escapeHtml($s['title']) . '"' : '' ?>>
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
            <table class="table table-hover align-middle mb-0 itflow-asset-table itflow-certificate-table">
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
                        <a class="text-reset text-decoration-none" href="?<?= $url_query_strings_sort ?>&sort=certificate_name&order=<?= $disp ?>">
                            Certificate <?php if ($sort == 'certificate_name') { echo $order_icon; } ?>
                        </a>
                    </th>
                    <th>
                        <a class="text-reset text-decoration-none" href="?<?= $url_query_strings_sort ?>&sort=certificate_domain&order=<?= $disp ?>">
                            Host <?php if ($sort == 'certificate_domain') { echo $order_icon; } ?>
                        </a>
                    </th>
                    <th>
                        <a class="text-reset text-decoration-none" href="?<?= $url_query_strings_sort ?>&sort=certificate_issued_by&order=<?= $disp ?>">
                            Issuer <?php if ($sort == 'certificate_issued_by') { echo $order_icon; } ?>
                        </a>
                    </th>
                    <th>
                        <a class="text-reset text-decoration-none" href="?<?= $url_query_strings_sort ?>&sort=certificate_expire&order=<?= $disp ?>">
                            Expires <?php if ($sort == 'certificate_expire') { echo $order_icon; } ?>
                        </a>
                    </th>
                    <th>
                        <a class="text-reset text-decoration-none" href="?<?= $url_query_strings_sort ?>&sort=certificate_checked_at&order=<?= $disp ?>">
                            Last check <?php if ($sort == 'certificate_checked_at') { echo $order_icon; } ?>
                        </a>
                    </th>
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

                foreach ($certificates as $certificate_id => $row) {
                    $row_client_id = intval($row['certificate_client_id']);
                    $certificate_name = escapeHtml($row['certificate_name']);
                    $certificate_description = escapeHtml($row['certificate_description']);
                    $certificate_favorite = intval($row['certificate_favorite']);
                    $certificate_archived_at = escapeHtml($row['certificate_archived_at']);
                    $host = certificateHost($row['certificate_domain']);
                    $names = certificateNames($row);
                    $other_names = $host ? array_values(array_filter($names, function ($covered) use ($host) { return strtolower($covered) !== $host['host']; })) : $names;
                    $expiry = certificateExpiry($row);
                    $quick_url = "modals/certificate/certificate.php?id=$certificate_id";

                    ?>
                    <tr>
                        <?php if ($can_edit) { ?>
                        <td class="checkbox-column bg-light border-end">
                            <div class="form-check">
                                <input class="form-check-input bulk-select" type="checkbox" name="certificate_ids[]" value="<?= $certificate_id ?>" aria-label="Select <?= $certificate_name ?>">
                            </div>
                        </td>
                        <?php } ?>
                        <td class="itflow-asset-name-cell">
                            <div class="d-flex align-items-center gap-3">
                                <?= certificateTile() ?>
                                <div class="min-w-0">
                                    <a class="fw-semibold text-body ajax-modal" href="#" data-modal-size="lg" data-modal-url="<?= $quick_url ?>"><?= $certificate_name ?></a>
                                    <?php if ($certificate_favorite) { ?><i class="fas fa-star text-warning small ms-1" title="Starred"></i><?php } ?>
                                    <?php if ($certificate_archived_at) { ?><span class="badge rounded-pill text-bg-secondary fw-normal ms-1">Archived</span><?php } ?>
                                    <?php if ($certificate_description) { ?>
                                        <div class="small text-body-secondary text-truncate itflow-asset-desc" title="<?= $certificate_description ?>"><?= $certificate_description ?></div>
                                    <?php } ?>
                                </div>
                            </div>
                        </td>
                        <td class="itflow-certificate-host">
                            <?php if ($host) { ?>
                                <div class="d-flex align-items-center min-w-0">
                                    <span class="font-monospace small text-truncate"><?= escapeHtml($host['display']) ?></span>
                                    <button type="button" class="itflow-copy clipboardjs" title="Copy" data-clipboard-text="<?= escapeHtml($host['display']) ?>"><i class="far fa-copy"></i></button>
                                </div>
                            <?php } else { ?>
                                <span class="small text-body-secondary">No host</span>
                            <?php } ?>
                            <?php if ($host && $names && !certificateCovers($host['host'], $names)) { ?>
                                <div class="small text-warning-emphasis text-truncate" title="<?= escapeHtml(implode(', ', $names)) ?>">
                                    <i class="fas fa-not-equal me-1"></i><?= count($names) === 1 ? 'Covers ' . escapeHtml($names[0]) : 'Covers ' . count($names) . ' other names' ?>
                                </div>
                            <?php } elseif ($other_names) { ?>
                                <div class="small text-body-secondary text-truncate" title="<?= escapeHtml(implode(', ', $names)) ?>">
                                    <?= count($other_names) === 1 ? 'Also ' . escapeHtml($other_names[0]) : 'Also covers ' . count($other_names) . ' names' ?>
                                </div>
                            <?php } ?>
                            <?php if (intval($row['certificate_domain_id']) && $row['domain_name'] !== null) { ?>
                                <div class="small mt-1"><?= domainLink($row['certificate_domain_id'], $row['domain_name'], ['archived' => !empty($row['domain_archived_at'])]) ?></div>
                            <?php } ?>
                        </td>
                        <td class="small">
                            <?php if ($row['certificate_issued_by'] !== '') { ?>
                                <div class="text-truncate itflow-certificate-issuer" title="<?= escapeHtml($row['certificate_issued_by']) ?>"><?= escapeHtml($row['certificate_issued_by']) ?></div>
                            <?php } else { ?>
                                <span class="text-body-secondary">-</span>
                            <?php } ?>
                        </td>
                        <td class="text-nowrap">
                            <?= certificateExpiryPill($expiry) ?>
                            <?php if ($expiry) { ?>
                                <div class="small text-body-secondary mt-1"><?= escapeHtml($expiry['date']) ?></div>
                            <?php } ?>
                        </td>
                        <td class="text-nowrap">
                            <?= certificateStatusPill($row) ?>
                            <?php if (!empty($row['certificate_checked_at'])) { ?>
                                <div class="small text-body-secondary mt-1" title="<?= escapeHtml($row['certificate_checked_at']) ?>"><?= timeAgo($row['certificate_checked_at']) ?></div>
                            <?php } ?>
                        </td>
                        <?php if (!$client_url) { ?>
                        <td><a href="certificates.php?client_id=<?= $row_client_id ?>"><?= escapeHtml($row['client_name']) ?></a></td>
                        <?php } ?>
                        <td class="text-end">
                            <div class="dropdown dropstart">
                                <button class="btn btn-sm btn-light" title="More actions" type="button" data-bs-toggle="dropdown"><i class="fas fa-fw fa-ellipsis-v"></i></button>
                                <div class="dropdown-menu">
                                    <a class="dropdown-item ajax-modal" href="#" data-modal-size="lg" data-modal-url="<?= $quick_url ?>">
                                        <i class="fas fa-fw fa-eye text-secondary me-2"></i>Quick look
                                    </a>
                                    <?php if ($host) { ?>
                                        <a class="dropdown-item" href="https://<?= escapeHtml($host['display']) ?>" target="_blank" rel="noopener noreferrer">
                                            <i class="fas fa-fw fa-external-link-alt text-secondary me-2"></i>Open the site
                                        </a>
                                    <?php } ?>
                                    <?php if ($can_edit && !$certificate_archived_at) { ?>
                                        <a class="dropdown-item ajax-modal" href="#" data-modal-size="lg" data-modal-url="modals/certificate/certificate_edit.php?id=<?= $certificate_id ?>">
                                            <i class="fas fa-fw fa-edit text-secondary me-2"></i>Edit
                                        </a>
                                        <?php if ($host) { ?>
                                            <a class="dropdown-item" href="post.php?check_certificate=<?= $certificate_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>">
                                                <i class="fas fa-fw fa-sync-alt text-secondary me-2"></i>Check now
                                            </a>
                                        <?php } ?>
                                        <a class="dropdown-item" href="post.php?<?= $certificate_favorite ? 'unfavorite_certificate' : 'favorite_certificate' ?>=<?= $certificate_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>">
                                            <i class="<?= $certificate_favorite ? 'far' : 'fas' ?> fa-fw fa-star text-secondary me-2"></i><?= $certificate_favorite ? 'Unstar' : 'Star' ?>
                                        </a>
                                    <?php } ?>
                                    <?php if ($can_edit) { ?>
                                        <div class="dropdown-divider"></div>
                                        <?php if ($certificate_archived_at) { ?>
                                            <a class="dropdown-item" href="post.php?restore_certificate=<?= $certificate_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>">
                                                <i class="fas fa-fw fa-redo text-secondary me-2"></i>Restore
                                            </a>
                                            <?php if ($can_delete) { ?>
                                                <a class="dropdown-item text-danger text-bold confirm-link" href="post.php?delete_certificate=<?= $certificate_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>"
                                                    data-confirm-text="<?= $certificate_name ?> goes for good, with its history. Services stop pointing at it.">
                                                    <i class="fas fa-fw fa-trash me-2"></i>Delete
                                                </a>
                                            <?php } ?>
                                        <?php } else { ?>
                                            <a class="dropdown-item text-danger confirm-link" href="post.php?archive_certificate=<?= $certificate_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>">
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
        'thing' => 'certificates',
        'icon' => 'fa-lock',
        'filtered' => $q || $issuer_query || $expire_query,
        'views' => ['show'],
        'view' => emptyStatePill($shows, $show, '', ['soon', 'expired', 'problems', 'unchecked']),
        'text' => 'The certificates on their websites and services, and when each expires.',
        'actions' => $can_edit ? [['label' => 'New Certificate', 'modal' => "modals/certificate/certificate_add.php?$client_url", 'size' => 'lg']] : [],
    ];
    require_once "../includes/filter_footer.php";
    ?>
</div>

<?php if (!empty($_GET['open'])) { ?>
    <script>
        // Opened from search, a notification or the overview - straight to that certificate's quick look
        document.addEventListener('DOMContentLoaded', function () {
            var open = document.createElement('a');
            open.className = 'ajax-modal';
            open.href = '#';
            open.dataset.modalSize = 'lg';
            open.dataset.modalUrl = 'modals/certificate/certificate.php?id=<?= intval($_GET['open']) ?>';
            document.body.appendChild(open);
            open.click();
            open.remove();
        });
    </script>
<?php } ?>

<script src="../js/bulk_actions.js"></script>

<?php require_once "../includes/footer.php";
