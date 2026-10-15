<?php

/*
 * Licenses - one client's, or every license the agent can see from All
 * Client Docs. Built around the two things an MSP watches: renewals (what is
 * coming up, what has lapsed) and seats (bought against in use - over means a
 * true-up, none in use means paying for nothing). Tabs along the top pull
 * those out; each row shows its seats as a bar and its renewal in colour.
 * Names open the license page.
 */

// Default Column Sortby Filter
$sort = "software_name";
$order = "ASC";

// If client_id is in URI then show client Side Bar and client header
if (isset($_GET['client_id'])) {
    require_once "includes/inc_all_client.php";
    $client_query = "AND software_client_id = $client_id";
    $client_url = "client_id=$client_id&";
    // Overide Filter Header Archived
    if (isset($_GET['archived']) && $_GET['archived'] == 1) {
        $archived = 1;
        $archive_query = "software_archived_at IS NOT NULL";
    } else {
        $archived = 0;
        $archive_query = "software_archived_at IS NULL";
    }
} else {
    require_once "includes/inc_client_overview_all.php";
    $client_query = '';
    $client_url = '';
    // Overide Filter Header Archived
    if (isset($_GET['archived']) && $_GET['archived'] == 1) {
        $archived = 1;
        $archive_query = "(client_archived_at IS NOT NULL OR software_archived_at IS NOT NULL)";
    } else {
        $archived = 0;
        $archive_query = "(client_archived_at IS NULL AND software_archived_at IS NULL)";
    }
}

// Perms
enforceUserPermission('module_support');

$can_edit = lookupUserPermission('module_support') >= 2;
// Deleting for good also needs Admin > Settings to allow destructive deletes
$can_delete = lookupUserPermission('module_support') >= 3 && !empty($config_destructive_deletes_enable);

$client = '';
if (!$client_url) {
    // Client Filter
    if (!empty($_GET['client'])) {
        $client = intval($_GET['client']);
        $client_query = "AND software_client_id = $client";
    }
}

// Attention tabs - renewing, expired, over seats, nobody on it
$status_filters = softwareStatusFilters();
$status = isset($status_filters[$_GET['status'] ?? '']) ? $_GET['status'] : '';
$_GET['status'] = $status;
$status_query = $status ? "AND ({$status_filters[$status]['sql']})" : '';

// License type filter
$license_type_filter = (string) ($_GET['license_type'] ?? '');
$license_type_query = $license_type_filter !== '' ? "AND software_license_type = '" . escapeSql($license_type_filter) . "'" : '';

// Expiring In Filter - the dashboard links here with expire_days=30
if (!empty($_GET['expire_days'])) {
    if ($_GET['expire_days'] == "expired") {
        $expire_days = "expired";
        $expire_query = "AND (software_expire IS NOT NULL AND software_expire != '0000-00-00' AND software_expire < CURDATE())";
    } else {
        $expire_days = intval($_GET['expire_days']);
        $expire_query = "AND (software_expire IS NOT NULL AND software_expire != '0000-00-00' AND software_expire BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL $expire_days DAY))";
    }
} else {
    // Default - any
    $expire_days = '';
    $expire_query = '';
}

// Extra columns, picked from the filter bar
$extra_column_options = [
    'Key' => 'License key',
    'Purchased' => 'Purchased',
    'Order_Ref' => 'Order reference',
];
$extra_columns = array_values(array_intersect(array_keys($extra_column_options), (array) ($_GET['show_column'] ?? [])));
$show_key = in_array('Key', $extra_columns, true);
$show_purchased = in_array('Purchased', $extra_columns, true);
$show_order_ref = in_array('Order_Ref', $extra_columns, true);

// Only these sort - filter_header.php will hand through any lowercase word, and
// an unknown column takes the page down with a mysqli exception
$sortable_columns = [
    'software_name', 'software_type', 'software_license_type', 'seats_used', 'software_seats', 'software_expire',
    'software_purchase', 'software_purchase_reference', 'vendor_name', 'client_name', 'software_created_at'
];
if (!in_array($sort, $sortable_columns, true)) {
    $sort = 'software_name';
}
// No renewal date sorts last whichever way, so "soonest first" starts with a date
$order_by = $sort === 'software_expire' ? "software_expire IS NULL, software_expire $order, software_name ASC" : "$sort $order, software_name ASC";

$seats_used_sql = softwareSeatsUsedSql('software.software_id');

// Tab counts - within the client and archive scope, before the search narrows it
$count_columns = ["COUNT(*) AS all_count"];
foreach ($status_filters as $status_key => $status_filter) {
    $count_columns[] = "COALESCE(SUM({$status_filter['sql']}), 0) AS {$status_key}_count";
}
$row = mysqli_fetch_assoc(mysqli_query($mysqli, "
    SELECT " . implode(', ', $count_columns) . "
    FROM software
    LEFT JOIN clients ON client_id = software_client_id
    WHERE $archive_query
    " . clientScopeSql('software_client_id') . "
    $client_query
"));
$tab_counts = ['' => intval($row['all_count'])];
foreach (array_keys($status_filters) as $status_key) {
    $tab_counts[$status_key] = intval($row["{$status_key}_count"]);
}

$sql = mysqli_query(
    $mysqli,
    "SELECT SQL_CALC_FOUND_ROWS client_id, client_name, software_created_at, software_description, software_expire,
        software_id, software_license_type, software_name, software_seats, software_type, software_key, software_favorite,
        software_purchase, software_purchase_reference, software_version, software_archived_at, vendor_id, vendor_name,
        $seats_used_sql AS seats_used,
        (SELECT COUNT(*) FROM software_keys WHERE software_key_software_id = software.software_id) AS key_count
    FROM software
    LEFT JOIN clients ON client_id = software_client_id
    LEFT JOIN vendors ON vendor_id = software_vendor_id
    WHERE (software_name LIKE '%$q%' OR software_description LIKE '%$q%' OR software_type LIKE '%$q%' OR software_license_type LIKE '%$q%'
        OR software_key LIKE '%$q%' OR software_purchase_reference LIKE '%$q%' OR vendor_name LIKE '%$q%' OR client_name LIKE '%$q%'
        OR software.software_id IN (SELECT software_key_software_id FROM software_keys WHERE software_key LIKE '%$q%'))
    AND $archive_query
    " . clientScopeSql('software_client_id') . "
    $client_query
    $status_query
    $license_type_query
    $expire_query
    ORDER BY $order_by LIMIT $record_from, $record_to");

$num_rows = mysqli_fetch_row(mysqli_query($mysqli, "SELECT FOUND_ROWS()"));

// License types in use here, for the filter
$license_type_options = [];
$sql_license_types = mysqli_query($mysqli, "SELECT DISTINCT software_license_type FROM software
    LEFT JOIN clients ON client_id = software_client_id
    WHERE software_license_type IS NOT NULL AND software_license_type != '' AND $archive_query
    " . clientScopeSql('software_client_id') . " $client_query
    ORDER BY software_license_type ASC");
while ($license_type_row = mysqli_fetch_row($sql_license_types)) {
    $license_type_options[] = $license_type_row[0];
}

?>

<div class="card">
    <div class="card-header bg-dark py-2">
        <h3 class="card-title mt-2"><i class="fas fa-fw fa-cube me-2"></i>Licenses</h3>
        <div class="card-tools">
            <?php if ($can_edit) { ?>
            <div class="btn-group">
                <button type="button" class="btn btn-primary ajax-modal" data-modal-size="lg" data-modal-url="modals/software/software_add.php?<?= $client_url ?>">
                    <i class="fas fa-plus me-2"></i>New License
                </button>
                <button type="button" class="btn btn-primary dropdown-toggle dropdown-toggle-split" data-bs-toggle="dropdown"></button>
                <div class="dropdown-menu dropdown-menu-end">
                    <a class="dropdown-item text-dark ajax-modal" href="#" data-modal-size="lg"
                        data-modal-url="modals/software/software_add_from_template.php?<?= $client_url ?>">
                        <i class="fas fa-fw fa-puzzle-piece me-2"></i>New from Template
                    </a>
                    <?php if ($num_rows[0] > 0) { ?>
                        <div class="dropdown-divider"></div>
                        <a class="dropdown-item text-dark ajax-modal" href="#"
                            data-modal-url="<?= buildExportModalUrl('modals/software/software_export.php', ['client_id', 'client', 'status', 'license_type', 'expire_days', 'archived', 'q']) ?>">
                            <i class="fa fa-fw fa-download me-2"></i>Export
                        </a>
                    <?php } ?>
                </div>
            </div>
            <?php } ?>
        </div>
    </div>

    <?php if ($tab_counts['']) { ?>
        <!-- What needs a look -->
        <div class="card-header py-2 itflow-asset-tabs">
            <nav class="nav nav-pills flex-nowrap overflow-auto">
                <a class="nav-link text-nowrap <?= $status === '' ? 'active' : '' ?>" href="?<?= $url_query_strings_sort ?>&status=">
                    All <span class="badge rounded-pill ms-1"><?= $tab_counts[''] ?></span>
                </a>
                <?php foreach ($status_filters as $status_key => $status_filter) {
                    if (!$tab_counts[$status_key] && $status !== $status_key) {
                        continue;
                    }
                    $tab_tone = ['expired' => 'danger', 'over' => 'danger', 'renewing' => 'warning'][$status_key] ?? ''; ?>
                    <a class="nav-link text-nowrap <?= $status === $status_key ? 'active' : '' ?>" href="?<?= $url_query_strings_sort ?>&status=<?= $status_key ?>" title="<?= $status_filter['title'] ?>">
                        <i class="fas fa-fw <?= $status_filter['icon'] ?> me-1 <?= $tab_tone && $status !== $status_key ? "text-$tab_tone" : '' ?>"></i><?= $status_filter['label'] ?> <span class="badge rounded-pill ms-1"><?= $tab_counts[$status_key] ?></span>
                    </a>
                <?php } ?>
            </nav>
        </div>
    <?php } ?>

    <div class="card-header py-3">
        <form autocomplete="off">
            <?php if ($client_url) { ?>
            <input type="hidden" name="client_id" value="<?= $client_id ?>">
            <?php } ?>
            <input type="hidden" name="status" value="<?= $status ?>">
            <input type="hidden" name="archived" value="<?= $archived ?>">
            <div class="row g-2 align-items-center">
                <div class="col-lg-3 col-md-6">
                    <div class="input-group">
                        <input type="search" class="form-control" name="q" value="<?php if (isset($q)) { echo stripslashes(escapeHtml($q)); } ?>" placeholder="Search licenses, keys, vendors...">
                        <button class="btn btn-dark" title="Search"><i class="fa fa-search"></i></button>
                    </div>
                </div>
                <?php if (!$client_url) { ?>
                <div class="col-lg-2 col-md-6">
                    <select class="form-select select2" name="client" onchange="this.form.submit()">
                        <option value="" <?php if ($client == "") { echo "selected"; } ?>>- All Clients -</option>
                        <?php
                        $sql_clients_filter = mysqli_query($mysqli, "
                            SELECT DISTINCT client_id, client_name, client_abbreviation
                            FROM clients
                            JOIN software ON software_client_id = client_id
                            WHERE $archive_query
                            " . clientScopeSql('clients.client_id') . "
                            ORDER BY client_name ASC
                        ");
                        while ($row = mysqli_fetch_assoc($sql_clients_filter)) {
                            $client_id_filter = intval($row['client_id']);
                            $client_name_filter = escapeHtml($row['client_name']);
                        ?>
                            <option <?php if ($client == $client_id_filter) { echo "selected"; } ?> value="<?= $client_id_filter ?>"<?= selectOptionClient($row['client_id'], $row['client_name'], $row['client_abbreviation'] ?? '') ?>><?= $client_name_filter ?></option>
                        <?php } ?>
                    </select>
                </div>
                <?php } ?>
                <div class="col-lg-2 col-md-4">
                    <select class="form-select select2" name="license_type" onchange="this.form.submit()">
                        <option value="">- License Type -</option>
                        <?php foreach ($license_type_options as $license_type_option) { ?>
                            <option <?= $license_type_option === $license_type_filter ? 'selected' : '' ?><?= selectOptionData(['icon' => 'fas ' . softwareLicenseTypeIcon($license_type_option)]) ?>><?= escapeHtml($license_type_option) ?></option>
                        <?php } ?>
                    </select>
                </div>
                <div class="col-lg-2 col-md-4">
                    <select class="form-select select2" name="expire_days" onchange="this.form.submit()">
                        <option value="" <?php if ($expire_days == "") { echo "selected"; } ?>>- Renewal -</option>
                        <option value="expired" <?php if ($expire_days === "expired") { echo "selected"; } ?>>Expired</option>
                        <?php foreach ([7, 30, 45, 60, 90] as $expire_option) { ?>
                            <option value="<?= $expire_option ?>" <?php if ($expire_days === $expire_option) { echo "selected"; } ?>>Renews in <?= $expire_option ?> days</option>
                        <?php } ?>
                    </select>
                </div>
                <div class="col-lg-2 col-md-4">
                    <select onchange="this.form.submit()" class="form-select select2" name="show_column[]" data-placeholder="- More columns -" multiple>
                        <?php foreach ($extra_column_options as $column_key => $column_label) { ?>
                            <option value="<?= $column_key ?>" <?php if (in_array($column_key, $extra_columns, true)) { echo 'selected'; } ?>><?= $column_label ?></option>
                        <?php } ?>
                    </select>
                </div>
                <div class="col-lg-1 col-md-12 <?= $client_url ? 'col-lg-3' : '' ?>">
                    <div class="d-flex justify-content-end gap-2">
                        <a href="?<?= $client_url ?>archived=<?= $archived ? 0 : 1 ?>"
                            class="btn <?= $archived ? 'btn-dark' : 'btn-outline-dark' ?>" title="<?= $archived ? 'Back to active licenses' : 'Show archived licenses' ?>">
                            <i class="fa fa-fw fa-archive"></i><span class="d-lg-none ms-2">Archived</span>
                        </a>
                        <?php if ($can_edit) { ?>
                        <div class="dropdown" id="bulkActionButton" hidden>
                            <button class="btn btn-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                <i class="fas fa-fw fa-layer-group me-2"></i>Bulk Action (<span id="selectedCount"></span>)
                            </button>
                            <div class="dropdown-menu dropdown-menu-end">
                                <?php if (!$archived) { ?>
                                    <a class="dropdown-item ajax-modal" href="#" data-modal-url="modals/software/software_renew.php" data-modal-size="lg" data-bulk="true">
                                        <i class="fas fa-fw fa-redo-alt me-2"></i>Renew
                                    </a>
                                    <div class="dropdown-divider"></div>
                                    <button class="dropdown-item text-danger confirm-link" type="submit" form="bulkActions" name="bulk_archive_software">
                                        <i class="fas fa-fw fa-archive me-2"></i>Archive
                                    </button>
                                <?php } else { ?>
                                    <button class="dropdown-item text-info" type="submit" form="bulkActions" name="bulk_restore_software">
                                        <i class="fas fa-fw fa-redo me-2"></i>Restore
                                    </button>
                                    <?php if ($can_delete) { ?>
                                        <button class="dropdown-item text-danger text-bold confirm-link" type="submit" form="bulkActions" name="bulk_delete_software"
                                            data-confirm-text="The licenses go for good, with their seats and keys.">
                                            <i class="fas fa-fw fa-trash me-2"></i>Delete
                                        </button>
                                    <?php } ?>
                                <?php } ?>
                            </div>
                        </div>
                        <?php } ?>
                    </div>
                </div>
            </div>
        </form>
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
                        <a class="text-reset text-decoration-none" href="?<?= $url_query_strings_sort ?>&sort=software_name&order=<?= $disp ?>">
                            License <?php if ($sort == 'software_name') { echo $order_icon; } ?>
                        </a>
                    </th>
                    <th>
                        <a class="text-reset text-decoration-none" href="?<?= $url_query_strings_sort ?>&sort=software_license_type&order=<?= $disp ?>">
                            Type <?php if ($sort == 'software_license_type') { echo $order_icon; } ?>
                        </a>
                    </th>
                    <th>
                        <a class="text-reset text-decoration-none" href="?<?= $url_query_strings_sort ?>&sort=seats_used&order=<?= $disp ?>">
                            Seats <?php if ($sort == 'seats_used') { echo $order_icon; } ?>
                        </a>
                    </th>
                    <th>
                        <a class="text-reset text-decoration-none" href="?<?= $url_query_strings_sort ?>&sort=software_expire&order=<?= $disp ?>">
                            Renews <?php if ($sort == 'software_expire') { echo $order_icon; } ?>
                        </a>
                    </th>
                    <?php if ($show_key) { ?>
                        <th>Key</th>
                    <?php } ?>
                    <?php if ($show_purchased) { ?>
                        <th>
                            <a class="text-reset text-decoration-none" href="?<?= $url_query_strings_sort ?>&sort=software_purchase&order=<?= $disp ?>">
                                Purchased <?php if ($sort == 'software_purchase') { echo $order_icon; } ?>
                            </a>
                        </th>
                    <?php } ?>
                    <?php if ($show_order_ref) { ?>
                        <th>
                            <a class="text-reset text-decoration-none" href="?<?= $url_query_strings_sort ?>&sort=software_purchase_reference&order=<?= $disp ?>">
                                Order ref <?php if ($sort == 'software_purchase_reference') { echo $order_icon; } ?>
                            </a>
                        </th>
                    <?php } ?>
                    <th>
                        <a class="text-reset text-decoration-none" href="?<?= $url_query_strings_sort ?>&sort=vendor_name&order=<?= $disp ?>">
                            Vendor <?php if ($sort == 'vendor_name') { echo $order_icon; } ?>
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

                while ($row = mysqli_fetch_assoc($sql)) {
                    $client_id = intval($row['client_id']);
                    $client_name = escapeHtml($row['client_name']);
                    $software_id = intval($row['software_id']);
                    $software_name = escapeHtml($row['software_name']);
                    $software_description = escapeHtml($row['software_description']);
                    $software_version = escapeHtml($row['software_version']);
                    $software_type = escapeHtml($row['software_type']);
                    $software_license_type = escapeHtml($row['software_license_type']);
                    $software_purchase = escapeHtml($row['software_purchase']);
                    $software_purchase_reference = escapeHtml($row['software_purchase_reference']);
                    $software_favorite = intval($row['software_favorite']);
                    $software_archived_at = escapeHtml($row['software_archived_at']);
                    $vendor_name = escapeHtml($row['vendor_name']);
                    $vendor_id = intval($row['vendor_id']);
                    $key_count = intval($row['key_count']);
                    $seats = softwareSeats($row['seats_used'], $row['software_seats']);
                    $renewal = softwareRenewal($row['software_expire']);
                    $license_url = "license.php?client_id=$client_id&software_id=$software_id";

                    ?>
                    <tr>
                        <?php if ($can_edit) { ?>
                        <td class="checkbox-column bg-light border-end">
                            <div class="form-check">
                                <input class="form-check-input bulk-select" type="checkbox" name="software_ids[]" value="<?= $software_id ?>" aria-label="Select <?= $software_name ?>">
                            </div>
                        </td>
                        <?php } ?>
                        <!-- The whole cell opens the license -->
                        <td class="position-relative itflow-asset-name-cell">
                            <div class="d-flex align-items-center gap-3">
                                <?= softwareTile($row['software_type']) ?>
                                <div class="min-w-0">
                                    <a class="fw-semibold text-body stretched-link" href="<?= $license_url ?>"><?= $software_name ?></a>
                                    <?php if ($software_version) { ?><span class="text-body-secondary ms-1"><?= $software_version ?></span><?php } ?>
                                    <?php if ($software_favorite) { ?><i class="fas fa-star text-warning ms-1" title="Favorite"></i><?php } ?>
                                    <?php if ($software_archived_at) { ?><span class="badge rounded-pill text-bg-secondary fw-normal ms-1">Archived</span><?php } ?>
                                    <div class="small text-body-secondary text-truncate itflow-asset-desc" title="<?= $software_description ?: $software_type ?>"><?= $software_description ?: $software_type ?></div>
                                </div>
                            </div>
                        </td>
                        <td class="text-nowrap">
                            <?php if ($software_license_type) { ?>
                                <div><i class="fas fa-fw <?= softwareLicenseTypeIcon($row['software_license_type']) ?> text-secondary me-1"></i><?= $software_license_type ?></div>
                            <?php } else { ?>
                                <div class="text-body-secondary">-</div>
                            <?php } ?>
                            <?php if ($software_description) { ?><div class="small text-body-secondary"><?= $software_type ?></div><?php } ?>
                        </td>
                        <td class="itflow-seats-cell"><?= softwareSeatBar($seats) ?></td>
                        <td class="text-nowrap">
                            <?php if ($renewal) { ?>
                                <div><?= escapeHtml($renewal['date']) ?></div>
                                <?php if ($renewal['state'] === 'active') { ?>
                                    <div class="small text-body-secondary"><?= escapeHtml($renewal['text']) ?></div>
                                <?php } else { ?>
                                    <?= softwareRenewalPill($renewal) ?>
                                <?php } ?>
                            <?php } else { ?>
                                <span class="text-body-secondary">-</span>
                            <?php } ?>
                        </td>
                        <?php if ($show_key) { ?>
                            <td class="itflow-license-key-cell">
                                <?= $row['software_key'] ? softwareKeyHtml($row['software_key']) : '' ?>
                                <?php if ($key_count) { ?>
                                    <div class="small text-body-secondary"><?= $key_count ?> individual key<?= $key_count === 1 ? '' : 's' ?></div>
                                <?php } elseif (!$row['software_key']) { ?>
                                    <span class="text-body-secondary">-</span>
                                <?php } ?>
                            </td>
                        <?php } ?>
                        <?php if ($show_purchased) { ?>
                            <td class="text-nowrap"><?= $software_purchase ?: '<span class="text-body-secondary">-</span>' ?></td>
                        <?php } ?>
                        <?php if ($show_order_ref) { ?>
                            <td class="text-nowrap">
                                <?php if ($software_purchase_reference) { ?>
                                    <?= $software_purchase_reference ?><button type="button" class="itflow-copy clipboardjs" title="Copy reference" data-clipboard-text="<?= $software_purchase_reference ?>"><i class="far fa-copy"></i></button>
                                <?php } else { ?>
                                    <span class="text-body-secondary">-</span>
                                <?php } ?>
                            </td>
                        <?php } ?>
                        <td>
                            <?php if ($vendor_name) { ?>
                                <a class="ajax-modal" href="#" data-modal-url="modals/vendor/vendor.php?id=<?= $vendor_id ?>"><?= $vendor_name ?></a>
                            <?php } else { ?>
                                <span class="text-body-secondary">-</span>
                            <?php } ?>
                        </td>
                        <?php if (!$client_url) { ?>
                        <td><a href="software.php?client_id=<?= $client_id ?>"><?= $client_name ?></a></td>
                        <?php } ?>
                        <td class="text-end">
                            <div class="dropdown dropstart">
                                <button class="btn btn-sm btn-light" title="More actions" type="button" data-bs-toggle="dropdown"><i class="fas fa-fw fa-ellipsis-v"></i></button>
                                <div class="dropdown-menu">
                                    <a class="dropdown-item ajax-modal" href="#" data-modal-size="lg" data-modal-url="modals/software/software.php?id=<?= $software_id ?>">
                                        <i class="fas fa-fw fa-eye text-secondary me-2"></i>Quick look
                                    </a>
                                    <?php if ($can_edit && !$software_archived_at) { ?>
                                        <a class="dropdown-item ajax-modal" href="#" data-modal-size="lg" data-modal-url="modals/software/software_edit.php?id=<?= $software_id ?>">
                                            <i class="fas fa-fw fa-edit text-secondary me-2"></i>Edit
                                        </a>
                                        <a class="dropdown-item ajax-modal" href="#" data-modal-url="modals/software/software_seats.php?id=<?= $software_id ?>">
                                            <i class="fas fa-fw fa-users text-secondary me-2"></i>Seats
                                        </a>
                                        <a class="dropdown-item ajax-modal" href="#" data-modal-size="lg" data-modal-url="modals/software/software_renew.php?id=<?= $software_id ?>">
                                            <i class="fas fa-fw fa-redo-alt text-secondary me-2"></i>Renew
                                        </a>
                                        <div class="dropdown-divider"></div>
                                        <a class="dropdown-item text-danger confirm-link" href="post.php?archive_software=<?= $software_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>">
                                            <i class="fas fa-fw fa-archive me-2"></i>Archive
                                        </a>
                                    <?php } elseif ($can_edit) { ?>
                                        <div class="dropdown-divider"></div>
                                        <a class="dropdown-item" href="post.php?restore_software=<?= $software_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>">
                                            <i class="fas fa-fw fa-redo text-secondary me-2"></i>Restore
                                        </a>
                                        <?php if ($can_delete) { ?>
                                            <a class="dropdown-item text-danger text-bold confirm-link" href="post.php?delete_software=<?= $software_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>"
                                                data-confirm-text="<?= $software_name ?> goes for good, with its seats and keys.">
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
    <?php if (!$num_rows[0]) { ?>
        <div class="itflow-asset-empty">
            <i class="fas fa-cube fa-2x mb-2 opacity-50"></i>
            <div><?= $tab_counts[''] ? 'No licenses match.' : 'No licenses yet.' ?></div>
        </div>
    <?php } ?>
    <?php require_once "../includes/filter_footer.php"; ?>
</div>

<script src="../js/bulk_actions.js"></script>

<?php
require_once "../includes/footer.php";
