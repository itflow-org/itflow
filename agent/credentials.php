<?php

/*
 * Credentials - one client's logins, or every login the agent can see from
 * All Client Docs. Starred first. Each row has what is needed to log in -
 * the username, the password and 2FA code on asking, where it opens - what
 * it is for (asset, contact, vendors, licenses), and how old the password is.
 * The pills along the top count and filter the ones to look at: no 2FA,
 * due for a change, shared out.
 *
 * Passwords and codes are never in the page; js/credentials.js fetches them
 * when asked for, and agent/ajax.php logs each view.
 */

// Default Column Sortby Filter
$sort = "credential_name";
$order = "ASC";

// If client_id is in URI then show client Side Bar and client header
if (isset($_GET['client_id'])) {
    require_once "includes/inc_all_client.php";
    $client_query = "AND credential_client_id = $client_id";
    $client_url = "client_id=$client_id&";
    $archived = isset($_GET['archived']) && $_GET['archived'] == 1 ? 1 : 0;
    $archive_query = $archived ? "credential_archived_at IS NOT NULL" : "credential_archived_at IS NULL";

    // Log when users load the Credentials page
    logAudit("Credential", "View", "$session_name viewed the Credentials page for client", $client_id);

} else {
    require_once "includes/inc_client_overview_all.php";
    $client_query = '';
    $client_url = '';
    $archived = isset($_GET['archived']) && $_GET['archived'] == 1 ? 1 : 0;
    $archive_query = $archived ? "(client_archived_at IS NOT NULL OR credential_archived_at IS NOT NULL)" : "(client_archived_at IS NULL AND credential_archived_at IS NULL)";

    // Log when users load the Credentials page
    logAudit("Credential", "View", "$session_name viewed the All Credentials page");
}

// Perms
enforceUserPermission('module_credential');

$can_edit = credentialCan(2);
$can_delete = credentialCan(3) && !empty($config_destructive_deletes_enable);
$can_share = lookupUserPermission('module_support') >= 2;

// Client Filter (all clients)
$client = '';
if (!$client_url && !empty($_GET['client'])) {
    $client = intval($_GET['client']);
    $client_query = "AND credential_client_id = $client";
}

// Location Filter (one client) - credentials for the assets at a site
$location_filter = $client_url ? intval($_GET['location'] ?? 0) : 0;
$location_query = $location_filter ? "AND c.credential_asset_id IN (SELECT asset_id FROM assets WHERE asset_location_id = $location_filter)" : '';

// Tags Filter - a credential with any of them
if (isset($_GET['tags']) && is_array($_GET['tags']) && !empty($_GET['tags'])) {
    $tag_filter = implode(",", array_map('intval', $_GET['tags']));
    $tag_query = "AND c.credential_id IN (SELECT credential_id FROM credential_tags WHERE tag_id IN ($tag_filter))";
} else {
    $tag_filter = 0;
    $tag_query = '';
}

// Which ones - the pills along the top
$rotate_days = CREDENTIAL_ROTATE_DAYS;
$shows = [
    '' => ['label' => 'All', 'where' => ''],
    'starred' => ['label' => 'Starred', 'icon' => 'fa-star', 'where' => 'c.credential_favorite = 1'],
    'otp' => ['label' => 'With 2FA', 'icon' => 'fa-clock', 'where' => "c.credential_otp_secret <> ''"],
    'nootp' => ['label' => 'No 2FA', 'icon' => 'fa-unlock', 'where' => "(c.credential_otp_secret IS NULL OR c.credential_otp_secret = '')"],
    'due' => ['label' => "Not changed in $rotate_days days", 'icon' => 'fa-history', 'where' => "(c.credential_password_changed_at IS NULL OR c.credential_password_changed_at < NOW() - INTERVAL $rotate_days DAY)"],
    'shared' => ['label' => 'Shared', 'icon' => 'fa-share-alt', 'where' => "c.credential_id IN (SELECT item_related_id FROM shared_items WHERE item_type = 'Credential' AND item_active = 1 AND item_expire_at > NOW() AND (COALESCE(item_view_limit, 0) = 0 OR item_views < item_view_limit))"],
];
$show = isset($shows[$_GET['show'] ?? '']) ? ($_GET['show'] ?? '') : '';
$show_query = $shows[$show]['where'] ? 'AND ' . $shows[$show]['where'] : '';

// Only these sort - filter_header.php will hand through any lowercase word, and
// an unknown column takes the page down with a mysqli exception
$sortable_columns = ['credential_name', 'credential_uri', 'credential_password_changed_at', 'credential_accessed_at', 'client_name', 'credential_created_at'];
if (!in_array($sort, $sortable_columns, true)) {
    $sort = 'credential_name';
}
$sort_column = $sort === 'client_name' ? 'client_name' : "c.$sort";

$search_query = "AND (c.credential_name LIKE '%$q%' OR c.credential_description LIKE '%$q%' OR c.credential_uri LIKE '%$q%' OR c.credential_uri_2 LIKE '%$q%'
    OR c.credential_note LIKE '%$q%' OR client_name LIKE '%$q%' OR asset_name LIKE '%$q%' OR contact_name LIKE '%$q%'
    OR c.credential_id IN (SELECT credential_id FROM credential_tags JOIN tags USING (tag_id) WHERE tag_name LIKE '%$q%'))";
$scope_query = clientScopeSql('credential_client_id');

$sql = mysqli_query(
    $mysqli,
    "SELECT SQL_CALC_FOUND_ROWS c.credential_id, c.credential_name, c.credential_description, c.credential_uri, c.credential_uri_2, c.credential_username,
        c.credential_otp_secret, c.credential_favorite, c.credential_archived_at, c.credential_password_changed_at, c.credential_accessed_at,
        c.credential_client_id, client_name, contact_id, contact_name, contact_photo, contact_archived_at, asset_id, asset_name, asset_type, asset_archived_at
    FROM credentials c
    LEFT JOIN clients ON client_id = credential_client_id
    LEFT JOIN contacts ON contact_id = credential_contact_id AND contact_client_id = credential_client_id
    LEFT JOIN assets ON asset_id = credential_asset_id AND asset_client_id = credential_client_id
    WHERE $archive_query
    $tag_query
    $search_query
    $location_query
    $show_query
    $scope_query
    $client_query
    ORDER BY c.credential_favorite DESC, $sort_column $order, c.credential_name ASC LIMIT $record_from, $record_to"
);

$num_rows = mysqli_fetch_row(mysqli_query($mysqli, "SELECT FOUND_ROWS()"));

$credentials = [];
while ($row = mysqli_fetch_assoc($sql)) {
    $credentials[intval($row['credential_id'])] = $row;
}

// Tags, links and share links for the whole page at once, not per row
$credentials_tags = tagsForRecords('credential', array_keys($credentials));
$credentials_links = credentialLinks(array_keys($credentials));
$credentials_shares = credentialShares(array_keys($credentials));

// How many of each, for the pills - everything in scope, before the pill itself
$counts = ['' => 0];
$count_sql = [];
foreach ($shows as $key => $s) {
    $count_sql[] = $key === '' ? 'COUNT(*)' : "COALESCE(SUM({$s['where']}), 0)";
}
$row = mysqli_fetch_row(mysqli_query($mysqli, "SELECT " . implode(', ', $count_sql) . "
    FROM credentials c
    LEFT JOIN clients ON client_id = credential_client_id
    LEFT JOIN contacts ON contact_id = credential_contact_id AND contact_client_id = credential_client_id
    LEFT JOIN assets ON asset_id = credential_asset_id AND asset_client_id = credential_client_id
    WHERE $archive_query $tag_query $search_query $location_query $scope_query $client_query"));
foreach (array_keys($shows) as $i => $key) {
    $counts[$key] = intval($row[$i]);
}

$filter_url = function ($changes) use ($client_url) {
    $params = array_merge($_GET, $changes);
    unset($params['page']);
    return '?' . http_build_query(array_filter($params, function ($v) { return $v !== '' && $v !== null; }));
};

?>

<div class="card">
    <div class="card-header bg-dark py-2">
        <h3 class="card-title mt-2"><i class="fa fa-fw fa-key me-2"></i>Credentials</h3>
        <div class="card-tools">
            <?php if ($can_edit || $num_rows[0]) { ?>
            <div class="btn-group">
                <?php if ($can_edit) { ?>
                    <button type="button" class="btn btn-primary ajax-modal" data-modal-size="lg" data-modal-url="modals/credential/credential_add.php?<?= $client_url ?>">
                        <i class="fas fa-plus me-2"></i>New Credential
                    </button>
                <?php } ?>
                <button type="button" class="btn btn-primary dropdown-toggle <?= $can_edit ? 'dropdown-toggle-split' : '' ?>" data-bs-toggle="dropdown"><?= $can_edit ? '' : '<i class="fas fa-ellipsis-h"></i>' ?></button>
                <div class="dropdown-menu dropdown-menu-end">
                    <?php if ($client_url && $can_edit) { ?>
                        <a class="dropdown-item text-dark ajax-modal" href="#" data-modal-url="modals/credential/credential_import.php?<?= $client_url ?>">
                            <i class="fa fa-fw fa-upload me-2"></i>Import
                        </a>
                    <?php } ?>
                    <?php if ($num_rows[0] > 0) { ?>
                        <a class="dropdown-item text-dark ajax-modal" href="#"
                            data-modal-url="<?= buildExportModalUrl('modals/credential/credential_export.php', ['client_id', 'client', 'tags', 'archived', 'q']) ?>">
                            <i class="fa fa-fw fa-download me-2"></i>Export
                        </a>
                    <?php } ?>
                    <a class="dropdown-item text-dark" href="reports/credential_rotation.php">
                        <i class="fa fa-fw fa-history me-2"></i>Rotation report
                    </a>
                </div>
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
                <div class="col-lg-4 col-md-6">
                    <div class="input-group">
                        <input type="search" class="form-control" name="q" value="<?php if (isset($q)) { echo stripslashes(escapeHtml($q)); } ?>" placeholder="Search name, link, notes, asset...">
                        <button class="btn btn-dark" title="Search"><i class="fa fa-search"></i></button>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <select onchange="this.form.submit()" class="form-select select2" name="tags[]" data-placeholder="- Tags -" multiple>
                        <?php
                        // Tags in use on credentials in this scope, plus any already picked
                        $sql_tags_filter = mysqli_query($mysqli, "
                            SELECT tag_id, tag_name, tag_color, tag_icon FROM tags
                            WHERE tag_type = 4
                            AND (
                                EXISTS (SELECT 1 FROM credential_tags JOIN credentials ON credentials.credential_id = credential_tags.credential_id
                                    WHERE credential_tags.tag_id = tags.tag_id " . clientScopeSql('credential_client_id') . " $client_query)
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
                <div class="col-lg-3 col-md-6">
                    <?php if ($client_url) { ?>
                        <select class="form-select select2" name="location" onchange="this.form.submit()">
                            <option value="">- All Asset Locations -</option>
                            <?php
                            $sql_locations_filter = mysqli_query($mysqli, "SELECT location_id, location_name FROM locations WHERE location_client_id = $client_id AND location_archived_at IS NULL ORDER BY location_name ASC");
                            while ($row = mysqli_fetch_assoc($sql_locations_filter)) {
                                $location_id = intval($row['location_id']); ?>
                                <option <?php if ($location_filter == $location_id) { echo "selected"; } ?> value="<?= $location_id ?>"><?= escapeHtml($row['location_name']) ?></option>
                            <?php } ?>
                        </select>
                    <?php } else { ?>
                        <select class="form-select select2" name="client" onchange="this.form.submit()">
                            <option value="" <?php if ($client == "") { echo "selected"; } ?>>- All Clients -</option>
                            <?php
                            $sql_clients_filter = mysqli_query($mysqli, "
                                SELECT DISTINCT client_id, client_name, client_abbreviation
                                FROM clients
                                JOIN credentials ON credential_client_id = client_id
                                WHERE $archive_query
                                " . clientScopeSql('clients.client_id') . "
                                ORDER BY client_name ASC
                            ");
                            while ($row = mysqli_fetch_assoc($sql_clients_filter)) {
                                $client_id_filter = intval($row['client_id']); ?>
                                <option <?php if ($client == $client_id_filter) { echo "selected"; } ?> value="<?= $client_id_filter ?>"<?= selectOptionClient($row['client_id'], $row['client_name'], $row['client_abbreviation'] ?? '') ?>><?= escapeHtml($row['client_name']) ?></option>
                            <?php } ?>
                        </select>
                    <?php } ?>
                </div>
                <div class="col-lg-2 col-md-6">
                    <div class="d-flex justify-content-end gap-2">
                        <a href="<?= escapeHtml($filter_url(['archived' => $archived ? '' : 1])) ?>"
                            class="btn <?= $archived ? 'btn-dark' : 'btn-outline-dark' ?>" title="<?= $archived ? 'Back to active credentials' : 'Show archived credentials' ?>">
                            <i class="fa fa-fw fa-archive"></i><span class="d-lg-none ms-2">Archived</span>
                        </a>
                        <?php if ($can_edit) { ?>
                        <div class="dropdown" id="bulkActionButton" hidden>
                            <button class="btn btn-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                <i class="fas fa-fw fa-layer-group me-2"></i>Bulk Action (<span id="selectedCount">0</span>)
                            </button>
                            <div class="dropdown-menu dropdown-menu-end">
                                <a class="dropdown-item" href="#" data-tag-picker data-tag-entity="credential" data-tag-bulk data-csrf="<?= $_SESSION['csrf_token'] ?>">
                                    <i class="fas fa-fw fa-tags me-2"></i>Tags
                                </a>
                                <button class="dropdown-item" type="submit" form="bulkActions" name="bulk_favorite_credentials">
                                    <i class="fas fa-fw fa-star text-warning me-2"></i>Star
                                </button>
                                <button class="dropdown-item" type="submit" form="bulkActions" name="bulk_unfavorite_credentials">
                                    <i class="far fa-fw fa-star me-2"></i>Unstar
                                </button>
                                <div class="dropdown-divider"></div>
                                <?php if ($archived) { ?>
                                    <button class="dropdown-item text-info" type="submit" form="bulkActions" name="bulk_restore_credentials">
                                        <i class="fas fa-fw fa-redo me-2"></i>Restore
                                    </button>
                                    <?php if ($can_delete) { ?>
                                        <button class="dropdown-item text-danger text-bold confirm-link" type="submit" form="bulkActions" name="bulk_delete_credentials"
                                            data-confirm-text="The credentials go for good, with any share links still open.">
                                            <i class="fas fa-fw fa-trash me-2"></i>Delete
                                        </button>
                                    <?php } ?>
                                <?php } else { ?>
                                    <button class="dropdown-item text-danger confirm-link" type="submit" form="bulkActions" name="bulk_archive_credentials">
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
            <?php foreach ($shows as $key => $s) { ?>
                <a class="nav-link text-nowrap <?= $show === $key ? 'active' : '' ?>" href="<?= escapeHtml($filter_url(['show' => $key])) ?>">
                    <?php if (!empty($s['icon'])) { ?><i class="fas fa-fw <?= $s['icon'] ?> me-1"></i><?php } ?><?= $s['label'] ?>
                    <span class="badge rounded-pill ms-1 <?= $key === 'due' && $counts[$key] ? 'text-bg-warning' : '' ?>"><?= $counts[$key] ?></span>
                </a>
            <?php } ?>
        </nav>
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
                        <a class="text-reset text-decoration-none" href="?<?= $url_query_strings_sort ?>&sort=credential_name&order=<?= $disp ?>">
                            Credential <?php if ($sort == 'credential_name') { echo $order_icon; } ?>
                        </a>
                    </th>
                    <th>Login</th>
                    <th>For</th>
                    <th>
                        <a class="text-reset text-decoration-none" href="?<?= $url_query_strings_sort ?>&sort=credential_password_changed_at&order=<?= $disp ?>">
                            Changed <?php if ($sort == 'credential_password_changed_at') { echo $order_icon; } ?>
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

                foreach ($credentials as $credential_id => $row) {
                    $row_client_id = intval($row['credential_client_id']);
                    $credential_name = escapeHtml($row['credential_name']);
                    $credential_description = escapeHtml($row['credential_description']);
                    $credential_favorite = intval($row['credential_favorite']);
                    $credential_archived_at = escapeHtml($row['credential_archived_at']);
                    $credential_tags = $credentials_tags[$credential_id] ?? [];
                    $credential_links = $credentials_links[$credential_id] ?? [];
                    $credential_shares = $credentials_shares[$credential_id] ?? [];
                    $has_otp = trim((string) $row['credential_otp_secret']) !== '';
                    $age = credentialAge($row['credential_password_changed_at']);
                    $quick_url = "modals/credential/credential.php?id=$credential_id";

                    ?>
                    <tr>
                        <?php if ($can_edit) { ?>
                        <td class="checkbox-column bg-light border-end">
                            <div class="form-check">
                                <input class="form-check-input bulk-select" type="checkbox" name="credential_ids[]" value="<?= $credential_id ?>" aria-label="Select <?= $credential_name ?>">
                            </div>
                        </td>
                        <?php } ?>
                        <td class="itflow-asset-name-cell">
                            <div class="d-flex align-items-center gap-3">
                                <?= credentialTile($row) ?>
                                <div class="min-w-0">
                                    <a class="fw-semibold text-body ajax-modal" href="#" data-modal-url="<?= $quick_url ?>"><?= $credential_name ?></a>
                                    <?php if ($credential_favorite) { ?><i class="fas fa-star text-warning small ms-1" title="Starred"></i><?php } ?>
                                    <?php if ($credential_shares) { ?><span class="badge rounded-pill bg-info-subtle text-info-emphasis fw-normal ms-1" title="Shared with <?= escapeHtml(implode(', ', array_filter(array_column($credential_shares, 'item_recipient'))) ?: 'a link') ?>"><i class="fas fa-share-alt me-1"></i>Shared</span><?php } ?>
                                    <?php if ($credential_archived_at) { ?><span class="badge rounded-pill text-bg-secondary fw-normal ms-1">Archived</span><?php } ?>
                                    <?php if ($credential_description) { ?>
                                        <div class="small text-body-secondary text-truncate itflow-asset-desc" title="<?= $credential_description ?>"><?= $credential_description ?></div>
                                    <?php } ?>
                                    <?php if ($credential_tags || $can_edit) { ?>
                                        <div class="mt-1"><?= tagPills('credential', $credential_id, $credential_tags, "credentials.php?$client_url") ?></div>
                                    <?php } ?>
                                </div>
                            </div>
                        </td>
                        <td>
                            <div class="d-flex align-items-center small"><i class="fas fa-fw fa-user text-body-secondary me-1"></i><?= credentialUsernameHtml(credentialUsername($row)) ?></div>
                            <div class="d-flex align-items-center flex-wrap gap-2 mt-1 small">
                                <?= credentialPasswordHtml($credential_id) ?>
                                <?= credentialOtpHtml($credential_id, $has_otp) ?>
                            </div>
                            <?php foreach (array_filter([$row['credential_uri'], $row['credential_uri_2']]) as $uri) { ?>
                                <div class="small text-nowrap mt-1"><?= credentialUriHtml($uri, 34) ?></div>
                            <?php } ?>
                        </td>
                        <td class="small itflow-credential-for">
                            <div class="d-flex flex-column gap-1">
                                <?= implode('', credentialOwnersHtml($row)) ?>
                                <?php foreach (array_slice($credential_links, 0, 2) as [$type, $id, $name]) { ?>
                                    <?php if ($type === 'vendor') { ?>
                                        <?= vendorLink($id, $name) ?>
                                    <?php } else { ?>
                                        <span class="itflow-contact-ref"><i class="fas fa-fw fa-<?= $type === 'software' ? 'cube' : 'stream' ?> text-body-secondary"></i><a class="ajax-modal text-truncate" href="#" data-modal-size="lg" data-modal-url="modals/<?= $type === 'software' ? 'software/software' : 'service/service' ?>.php?id=<?= $id ?>"><?= escapeHtml($name) ?></a></span>
                                    <?php } ?>
                                <?php } ?>
                                <?php if (count($credential_links) > 2) { ?>
                                    <a class="ajax-modal text-body-secondary" href="#" data-modal-url="<?= $quick_url ?>">+<?= count($credential_links) - 2 ?> more</a>
                                <?php } ?>
                                <?php if (!$row['asset_id'] && !$row['contact_id'] && !$credential_links) { ?><span class="text-body-secondary">-</span><?php } ?>
                            </div>
                        </td>
                        <td class="text-nowrap">
                            <?= credentialAgePill($age, true) ?: '<span class="text-body-secondary">-</span>' ?>
                            <?php if ($row['credential_accessed_at']) { ?><div class="small text-body-secondary mt-1" title="Password last shown or copied <?= escapeHtml($row['credential_accessed_at']) ?>">Used <?= timeAgo($row['credential_accessed_at']) ?></div><?php } ?>
                        </td>
                        <?php if (!$client_url) { ?>
                        <td><a href="credentials.php?client_id=<?= $row_client_id ?>"><?= escapeHtml($row['client_name']) ?></a></td>
                        <?php } ?>
                        <td class="text-end">
                            <div class="dropdown dropstart">
                                <button class="btn btn-sm btn-light" title="More actions" type="button" data-bs-toggle="dropdown"><i class="fas fa-fw fa-ellipsis-v"></i></button>
                                <div class="dropdown-menu">
                                    <a class="dropdown-item ajax-modal" href="#" data-modal-url="<?= $quick_url ?>">
                                        <i class="fas fa-fw fa-eye text-secondary me-2"></i>Quick look
                                    </a>
                                    <?php if ($can_edit && !$credential_archived_at) { ?>
                                        <a class="dropdown-item ajax-modal" href="#" data-modal-size="lg" data-modal-url="modals/credential/credential_edit.php?id=<?= $credential_id ?>">
                                            <i class="fas fa-fw fa-edit text-secondary me-2"></i>Edit
                                        </a>
                                        <a class="dropdown-item ajax-modal" href="#" data-modal-url="modals/credential/credential_rotate.php?id=<?= $credential_id ?>">
                                            <i class="fas fa-fw fa-sync-alt text-secondary me-2"></i>Change password
                                        </a>
                                    <?php } ?>
                                    <?php if ($can_share && !$credential_archived_at) { ?>
                                        <a class="dropdown-item ajax-modal" href="#" data-modal-url="modals/credential/credential_share.php?id=<?= $credential_id ?>">
                                            <i class="fas fa-fw fa-share-alt text-secondary me-2"></i>Share
                                        </a>
                                    <?php } ?>
                                    <?php if ($can_edit) { ?>
                                        <?php if (!$credential_archived_at) { ?>
                                            <a class="dropdown-item" href="post.php?<?= $credential_favorite ? 'unfavorite_credential' : 'favorite_credential' ?>=<?= $credential_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>">
                                                <i class="<?= $credential_favorite ? 'far' : 'fas' ?> fa-fw fa-star text-secondary me-2"></i><?= $credential_favorite ? 'Unstar' : 'Star' ?>
                                            </a>
                                        <?php } ?>
                                        <div class="dropdown-divider"></div>
                                        <?php if ($credential_archived_at) { ?>
                                            <a class="dropdown-item" href="post.php?restore_credential=<?= $credential_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>">
                                                <i class="fas fa-fw fa-redo text-secondary me-2"></i>Restore
                                            </a>
                                            <?php if ($can_delete) { ?>
                                                <a class="dropdown-item text-danger text-bold confirm-link" href="post.php?delete_credential=<?= $credential_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>"
                                                    data-confirm-text="<?= $credential_name ?> goes for good, with any share links still open.">
                                                    <i class="fas fa-fw fa-trash me-2"></i>Delete
                                                </a>
                                            <?php } ?>
                                        <?php } else { ?>
                                            <a class="dropdown-item text-danger confirm-link" href="post.php?archive_credential=<?= $credential_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>">
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
    <?php if (!$num_rows[0]) { ?>
        <div class="itflow-asset-empty">
            <i class="fas fa-key fa-2x mb-2 opacity-50"></i>
            <div><?= $q || $tag_query || $show ? 'No credentials match.' : 'No credentials yet.' ?></div>
        </div>
    <?php } ?>
    <?php require_once "../includes/filter_footer.php"; ?>
</div>

<?php if (!empty($_GET['open'])) { ?>
    <script>
        // Opened from search - straight to that credential's quick look
        document.addEventListener('DOMContentLoaded', function () {
            var open = document.createElement('a');
            open.className = 'ajax-modal';
            open.href = '#';
            open.dataset.modalUrl = 'modals/credential/credential.php?id=<?= intval($_GET['open']) ?>';
            document.body.appendChild(open);
            open.click();
            open.remove();
        });
    </script>
<?php } ?>

<script src="../js/bulk_actions.js"></script>

<?php
require_once "../includes/footer.php";
