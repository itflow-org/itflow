<?php

/*
 * Vendors - one client's (their ISP, phone provider, software maker), ours
 * (our suppliers - what expenses are booked against), or every client's from
 * All Client Docs (?view=clients) - which is how an outage at one ISP is
 * checked against every client that uses it.
 *
 * Starred vendors come first. Each row is what a tech needs to make the call -
 * the support line and hours, the account number and PIN - and what the
 * vendor is to the client: open cases, licenses, assets, domains, services
 * and logins, each opening that tab on the vendor's page.
 */

// Default Column Sortby Filter
$sort = "vendor_name";
$order = "ASC";

if (isset($_GET['client_id'])) {
    // One client's vendors
    require_once "includes/inc_all_client.php";
    $view = 'client';
    $client_query = "AND vendor_client_id = $client_id";
    $client_url = "client_id=$client_id&";
} elseif (($_GET['view'] ?? '') === 'clients') {
    // Every client's vendors
    require_once "includes/inc_client_overview_all.php";
    $view = 'clients';
    $client_query = "AND vendor_client_id > 0";
    $client_url = "view=clients&";
} else {
    // Ours
    require_once "includes/inc_all.php";
    $view = 'ours';
    $client_query = "AND vendor_client_id = 0";
    $client_url = '';
}

// Override Filter Header Archived
$archived = isset($_GET['archived']) && $_GET['archived'] == 1 ? 1 : 0;
if ($view === 'clients') {
    $archive_query = $archived ? "(client_archived_at IS NOT NULL OR vendor_archived_at IS NOT NULL)" : "(client_archived_at IS NULL AND vendor_archived_at IS NULL)";
} else {
    $archive_query = $archived ? "vendor_archived_at IS NOT NULL" : "vendor_archived_at IS NULL";
}

// Perms - a client's vendors are the client module's; anyone may look up ours, the financial module changes them
if ($view !== 'ours') {
    enforceUserPermission('module_client');
}
$can_edit = $view === 'ours' ? lookupUserPermission('module_financial') >= 2 : lookupUserPermission('module_client') >= 2;
$can_delete = ($view === 'ours' ? lookupUserPermission('module_financial') >= 3 : lookupUserPermission('module_client') >= 3) && !empty($config_destructive_deletes_enable);
$can_support = lookupUserPermission('module_support') >= 1;
$can_credentials = lookupUserPermission('module_credential') >= 1;
$can_financial = lookupUserPermission('module_financial') >= 1;

$client = '';
if ($view === 'clients' && !empty($_GET['client'])) {
    // Client Filter
    $client = intval($_GET['client']);
    $client_query = "AND vendor_client_id = $client";
}

// Tags Filter - a vendor with any of them
if (isset($_GET['tags']) && is_array($_GET['tags']) && !empty($_GET['tags'])) {
    $tag_filter = implode(",", array_map('intval', $_GET['tags']));
    $tag_query = "AND vendors.vendor_id IN (SELECT vendor_id FROM vendor_tags WHERE tag_id IN ($tag_filter))";
} else {
    $tag_filter = 0;
    $tag_query = '';
}

// Only these sort - filter_header.php will hand through any lowercase word, and
// an unknown column takes the page down with a mysqli exception
$sortable_columns = ['vendor_name', 'vendor_description', 'vendor_account_number', 'vendor_phone', 'vendor_contact_name', 'vendor_hours', 'client_name', 'vendor_created_at'];
if (!in_array($sort, $sortable_columns, true)) {
    $sort = 'vendor_name';
}

$sql = mysqli_query(
    $mysqli,
    "SELECT SQL_CALC_FOUND_ROWS vendors.vendor_id, vendor_name, vendor_description, vendor_account_number, vendor_code, vendor_contact_name,
        vendor_contact_phone, vendor_contact_phone_country_code, vendor_contact_email, vendor_phone, vendor_phone_country_code, vendor_extension,
        vendor_email, vendor_website, vendor_hours, vendor_sla, vendor_favorite, vendor_archived_at, vendor_client_id, vendor_template_name,
        client_id, client_name
    FROM vendors
    LEFT JOIN clients ON client_id = vendor_client_id
    LEFT JOIN vendor_templates ON vendor_templates.vendor_template_id = vendors.vendor_template_id
    WHERE $archive_query
    $tag_query
    AND (vendor_name LIKE '%$q%' OR vendor_description LIKE '%$q%' OR vendor_account_number LIKE '%$q%' OR vendor_website LIKE '%$q%'
        OR vendor_contact_name LIKE '%$q%' OR vendor_email LIKE '%$q%' OR vendor_contact_email LIKE '%$q%' OR vendor_notes LIKE '%$q%'
        OR vendor_phone LIKE '%$phone_query%' OR vendor_contact_phone LIKE '%$phone_query%'" . ($view === 'clients' ? " OR client_name LIKE '%$q%'" : '') . "
        OR vendors.vendor_id IN (SELECT vendor_id FROM vendor_tags JOIN tags USING (tag_id) WHERE tag_name LIKE '%$q%'))
    $client_query
    " . clientScopeSql('vendor_client_id') . "
    ORDER BY vendor_favorite DESC, $sort $order, vendor_name ASC LIMIT $record_from, $record_to"
);

$num_rows = mysqli_fetch_row(mysqli_query($mysqli, "SELECT FOUND_ROWS()"));

$vendors = [];
while ($row = mysqli_fetch_assoc($sql)) {
    $vendors[intval($row['vendor_id'])] = $row;
}

// Tags and what each vendor is used for, for the whole page at once, not per row
$vendors_tags = tagsForRecords('vendor', array_keys($vendors));
$vendors_counts = vendorCounts($vendors);

$has_templates = (bool) mysqli_num_rows(mysqli_query($mysqli, "SELECT 1 FROM vendor_templates WHERE vendor_template_archived_at IS NULL LIMIT 1"));
$add_url = "modals/vendor/vendor_add.php?" . ($view === 'client' ? "client_id=$client_id" : ($view === 'clients' ? 'view=clients' : ''));

?>

<div class="card">
    <div class="card-header bg-dark py-2">
        <h3 class="card-title mt-2"><i class="fa fa-fw fa-building me-2"></i><?= $view === 'clients' ? 'Client Vendors' : 'Vendors' ?></h3>
        <div class="card-tools d-flex align-items-center gap-2">
            <?php if ($view !== 'client' && lookupUserPermission('module_client') >= 1) { ?>
                <!-- Ours, or every client's -->
                <nav class="nav nav-pills itflow-vendor-scope d-none d-md-flex">
                    <a class="nav-link py-1 <?= $view === 'ours' ? 'active' : 'text-light' ?>" href="vendors.php">Ours</a>
                    <a class="nav-link py-1 <?= $view === 'clients' ? 'active' : 'text-light' ?>" href="vendors.php?view=clients">Clients'</a>
                </nav>
            <?php } ?>
            <?php if ($can_edit || $num_rows[0]) { ?>
            <div class="btn-group">
                <?php if ($can_edit) { ?>
                    <button type="button" class="btn btn-primary ajax-modal" data-modal-size="lg" data-modal-url="<?= $add_url ?>">
                        <i class="fas fa-plus me-2"></i>New Vendor
                    </button>
                <?php } ?>
                <button type="button" class="btn btn-primary dropdown-toggle <?= $can_edit ? 'dropdown-toggle-split' : '' ?>" data-bs-toggle="dropdown"><?= $can_edit ? '' : '<i class="fas fa-ellipsis-h"></i>' ?></button>
                <div class="dropdown-menu dropdown-menu-end">
                    <?php if ($can_edit && $has_templates) { ?>
                        <a class="dropdown-item text-dark ajax-modal" href="#" data-modal-size="lg" data-modal-url="<?= $add_url ?>&from_template=1">
                            <i class="fa fa-fw fa-puzzle-piece me-2"></i>New from Template
                        </a>
                        <?php if ($view !== 'ours') { ?>
                            <a class="dropdown-item text-dark ajax-modal" href="#" data-modal-url="modals/vendor/vendor_template_clients.php">
                                <i class="fa fa-fw fa-users me-2"></i>Add a Template to Clients
                            </a>
                        <?php } ?>
                    <?php } ?>
                    <?php if ($num_rows[0] > 0) { ?>
                        <?php if ($can_edit && $has_templates) { ?><div class="dropdown-divider"></div><?php } ?>
                        <a class="dropdown-item text-dark ajax-modal" href="#"
                            data-modal-url="<?= buildExportModalUrl('modals/vendor/vendor_export.php', ['client_id', 'view', 'client', 'tags', 'archived', 'q']) ?>">
                            <i class="fa fa-fw fa-download me-2"></i>Export
                        </a>
                    <?php } ?>
                </div>
            </div>
            <?php } ?>
        </div>
    </div>
    <div class="card-header py-3">
        <form autocomplete="off">
            <?php if ($view === 'client') { ?>
                <input type="hidden" name="client_id" value="<?= $client_id ?>">
            <?php } elseif ($view === 'clients') { ?>
                <input type="hidden" name="view" value="clients">
            <?php } ?>
            <input type="hidden" name="archived" value="<?= $archived ?>">
            <div class="row g-2 align-items-center">
                <div class="col-lg-4 col-md-6">
                    <div class="input-group">
                        <input type="search" class="form-control" name="q" value="<?php if (isset($q)) { echo stripslashes(escapeHtml($q)); } ?>" placeholder="Search name, account, phone, contact...">
                        <button class="btn btn-dark" title="Search"><i class="fa fa-search"></i></button>
                    </div>
                </div>
                <?php if ($view === 'clients') { ?>
                <div class="col-lg-3 col-md-6">
                    <select class="form-select select2" name="client" onchange="this.form.submit()">
                        <option value="" <?php if ($client == "") { echo "selected"; } ?>>- All Clients -</option>
                        <?php
                        $sql_clients_filter = mysqli_query($mysqli, "
                            SELECT DISTINCT client_id, client_name, client_abbreviation
                            FROM clients
                            JOIN vendors ON vendor_client_id = client_id
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
                <div class="col-lg-3 col-md-6">
                    <select onchange="this.form.submit()" class="form-select select2" name="tags[]" data-placeholder="- Tags -" multiple>
                        <?php
                        // Tags in use on vendors in this scope, plus any already picked
                        $sql_tags_filter = mysqli_query($mysqli, "
                            SELECT tag_id, tag_name, tag_color, tag_icon FROM tags
                            WHERE tag_type = 6
                            AND (
                                EXISTS (SELECT 1 FROM vendor_tags JOIN vendors ON vendors.vendor_id = vendor_tags.vendor_id
                                    WHERE vendor_tags.tag_id = tags.tag_id " . clientScopeSql('vendor_client_id') . " $client_query)
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
                <div class="<?= $view === 'clients' ? 'col-lg-2' : 'col-lg-5' ?> col-md-6">
                    <div class="d-flex justify-content-end gap-2">
                        <?php if ($view !== 'client' && lookupUserPermission('module_client') >= 1) { ?>
                            <nav class="nav nav-pills d-md-none me-auto">
                                <a class="nav-link py-1 <?= $view === 'ours' ? 'active' : '' ?>" href="vendors.php">Ours</a>
                                <a class="nav-link py-1 <?= $view === 'clients' ? 'active' : '' ?>" href="vendors.php?view=clients">Clients'</a>
                            </nav>
                        <?php } ?>
                        <a href="?<?= $client_url ?>archived=<?= $archived ? 0 : 1 ?>"
                            class="btn <?= $archived ? 'btn-dark' : 'btn-outline-dark' ?>" title="<?= $archived ? 'Back to active vendors' : 'Show archived vendors' ?>">
                            <i class="fa fa-fw fa-archive"></i><span class="d-lg-none ms-2">Archived</span>
                        </a>
                        <?php if ($can_edit) { ?>
                        <div class="dropdown" id="bulkActionButton" hidden>
                            <button class="btn btn-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                <i class="fas fa-fw fa-layer-group me-2"></i>Bulk Action (<span id="selectedCount">0</span>)
                            </button>
                            <div class="dropdown-menu dropdown-menu-end">
                                <a class="dropdown-item" href="#" data-tag-picker data-tag-entity="vendor" data-tag-bulk data-csrf="<?= $_SESSION['csrf_token'] ?>">
                                    <i class="fas fa-fw fa-tags me-2"></i>Tags
                                </a>
                                <div class="dropdown-divider"></div>
                                <?php if ($archived) { ?>
                                    <button class="dropdown-item text-info" type="submit" form="bulkActions" name="bulk_restore_vendors">
                                        <i class="fas fa-fw fa-redo me-2"></i>Restore
                                    </button>
                                    <?php if ($can_delete) { ?>
                                        <button class="dropdown-item text-danger text-bold confirm-link" type="submit" form="bulkActions" name="bulk_delete_vendors"
                                            data-confirm-text="The vendors go for good. Their cases, licenses, assets and domains stay, with no vendor. Vendors with expenses are kept.">
                                            <i class="fas fa-fw fa-trash me-2"></i>Delete
                                        </button>
                                    <?php } ?>
                                <?php } else { ?>
                                    <button class="dropdown-item text-danger confirm-link" type="submit" form="bulkActions" name="bulk_archive_vendors">
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
                        <a class="text-reset text-decoration-none" href="?<?= $url_query_strings_sort ?>&sort=vendor_name&order=<?= $disp ?>">
                            Vendor <?php if ($sort == 'vendor_name') { echo $order_icon; } ?>
                        </a>
                    </th>
                    <th>
                        <a class="text-reset text-decoration-none" href="?<?= $url_query_strings_sort ?>&sort=vendor_phone&order=<?= $disp ?>">
                            Support <?php if ($sort == 'vendor_phone') { echo $order_icon; } ?>
                        </a>
                    </th>
                    <th>
                        <a class="text-reset text-decoration-none" href="?<?= $url_query_strings_sort ?>&sort=vendor_account_number&order=<?= $disp ?>">
                            Account <?php if ($sort == 'vendor_account_number') { echo $order_icon; } ?>
                        </a>
                    </th>
                    <th><?= $view === 'ours' ? 'Spend' : 'With them' ?></th>
                    <?php if ($view === 'clients') { ?>
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

                foreach ($vendors as $vendor_id => $row) {
                    $row_client_id = intval($row['vendor_client_id']);
                    $vendor_name = escapeHtml($row['vendor_name']);
                    $vendor_description = escapeHtml($row['vendor_description']);
                    $vendor_phone = contactPhoneLink($row['vendor_phone'], $row['vendor_phone_country_code'], $row['vendor_extension']);
                    $vendor_phone_text = $row['vendor_phone'] ? escapeHtml(formatPhoneNumber($row['vendor_phone'], $row['vendor_phone_country_code'])) : '';
                    $vendor_email = escapeHtml($row['vendor_email']);
                    $vendor_website_url = vendorWebsiteUrl($row['vendor_website']);
                    $vendor_support_when = escapeHtml(implode(' · ', array_filter([$row['vendor_hours'], $row['vendor_sla']])));
                    $vendor_account = escapeHtml($row['vendor_account_number']);
                    $vendor_pin = vendorSecretHtml($row['vendor_code']);
                    $vendor_favorite = intval($row['vendor_favorite']);
                    $vendor_archived_at = escapeHtml($row['vendor_archived_at']);
                    $vendor_tags = $vendors_tags[$vendor_id] ?? [];
                    $counts = $vendors_counts[$vendor_id];
                    $vendor_url = 'vendor.php?' . ($row_client_id ? "client_id=$row_client_id&" : '') . "vendor_id=$vendor_id";
                    $row_can_edit = $can_edit;
                    $tag_base = $view === 'client' ? "vendors.php?client_id=$row_client_id&" : ($view === 'clients' ? 'vendors.php?view=clients&' : 'vendors.php?');

                    ?>
                    <tr>
                        <?php if ($can_edit) { ?>
                        <td class="checkbox-column bg-light border-end">
                            <div class="form-check">
                                <input class="form-check-input bulk-select" type="checkbox" name="vendor_ids[]" value="<?= $vendor_id ?>" aria-label="Select <?= $vendor_name ?>">
                            </div>
                        </td>
                        <?php } ?>
                        <!-- The whole cell opens the vendor; the tag pills sit above it -->
                        <td class="position-relative itflow-asset-name-cell">
                            <div class="d-flex align-items-center gap-3">
                                <?= vendorTile($row['vendor_name']) ?>
                                <div class="min-w-0">
                                    <a class="fw-semibold text-body stretched-link" href="<?= $vendor_url ?>"><?= $vendor_name ?></a>
                                    <?php if ($vendor_favorite) { ?><i class="fas fa-star text-warning small ms-1" title="Starred"></i><?php } ?>
                                    <?php if ($row['vendor_template_name']) { ?><i class="fas fa-puzzle-piece text-body-secondary small ms-1" title="From template <?= escapeHtml($row['vendor_template_name']) ?>"></i><?php } ?>
                                    <?php if ($vendor_archived_at) { ?><span class="badge rounded-pill text-bg-secondary fw-normal ms-1">Archived</span><?php } ?>
                                    <?php if ($vendor_description) { ?>
                                        <div class="small text-body-secondary text-truncate itflow-asset-desc" title="<?= $vendor_description ?>"><?= $vendor_description ?></div>
                                    <?php } ?>
                                    <?php if ($vendor_tags || $row_can_edit) { ?>
                                        <div class="mt-1 itflow-above-stretch"><?= tagPills('vendor', $vendor_id, $vendor_tags, $tag_base, $row_can_edit) ?></div>
                                    <?php } ?>
                                </div>
                            </div>
                        </td>
                        <td class="itflow-vendor-support-cell">
                            <?php if ($vendor_phone) { ?>
                                <div class="text-nowrap"><?= $vendor_phone ?><button type="button" class="itflow-copy clipboardjs" title="Copy phone number" data-clipboard-text="<?= $vendor_phone_text ?>"><i class="far fa-copy"></i></button></div>
                            <?php } ?>
                            <?php if ($vendor_support_when) { ?><div class="small text-body-secondary"><i class="far fa-clock me-1"></i><?= $vendor_support_when ?></div><?php } ?>
                            <?php if ($vendor_email || $vendor_website_url) { ?>
                                <div class="small d-flex align-items-center gap-2 mt-1">
                                    <?php if ($vendor_email) { ?><span class="text-truncate itflow-vendor-email"><a href="mailto:<?= $vendor_email ?>"><?= $vendor_email ?></a><button type="button" class="itflow-copy clipboardjs" title="Copy email" data-clipboard-text="<?= $vendor_email ?>"><i class="far fa-copy"></i></button></span><?php } ?>
                                    <?php if ($vendor_website_url) { ?><a class="text-body-secondary" href="<?= escapeHtml($vendor_website_url) ?>" target="_blank" rel="noopener" title="<?= escapeHtml(vendorWebsiteLabel($row['vendor_website'])) ?>"><i class="fas fa-globe"></i></a><?php } ?>
                                </div>
                            <?php } ?>
                            <?php if (!$vendor_phone && !$vendor_support_when && !$vendor_email && !$vendor_website_url) { ?><span class="text-body-secondary">-</span><?php } ?>
                        </td>
                        <td>
                            <?php if ($vendor_account) { ?>
                                <div class="text-nowrap"><span class="font-monospace"><?= $vendor_account ?></span><button type="button" class="itflow-copy clipboardjs" title="Copy account number" data-clipboard-text="<?= $vendor_account ?>"><i class="far fa-copy"></i></button></div>
                            <?php } ?>
                            <?php if ($vendor_pin) { ?><div class="small text-nowrap"><span class="text-body-secondary me-1">PIN</span><?= $vendor_pin ?></div><?php } ?>
                            <?php if ($row['vendor_contact_name']) { ?><div class="small text-body-secondary text-truncate"><i class="fas fa-user-tie me-1"></i><?= escapeHtml($row['vendor_contact_name']) ?></div><?php } ?>
                            <?php if (!$vendor_account && !$vendor_pin && !$row['vendor_contact_name']) { ?><span class="text-body-secondary">-</span><?php } ?>
                        </td>
                        <td>
                            <div class="d-flex flex-wrap gap-1">
                                <?php if ($can_support && $counts['open']) { ?>
                                    <a class="itflow-contact-chip is-alert" href="<?= $vendor_url ?>#cases" title="<?= $counts['open'] ?> open case<?= $counts['open'] === 1 ? '' : 's' ?> with them"><i class="fas fa-life-ring"></i><?= $counts['open'] ?></a>
                                <?php } ?>
                                <?php if ($view === 'ours') { ?>
                                    <?php if ($can_financial && $counts['expense']) { ?>
                                        <span class="w-100 small text-nowrap"><strong><?= numfmt_format_currency($currency_format, $counts['spend_year'], $session_company_currency) ?></strong> <span class="text-body-secondary">this year</span></span>
                                    <?php } ?>
                                    <?php if ($can_financial && $counts['expense']) { ?>
                                        <a class="itflow-contact-chip" href="<?= $vendor_url ?>#expenses" title="<?= $counts['expense'] ?> expense<?= $counts['expense'] === 1 ? '' : 's' ?>"><i class="fas fa-receipt"></i><?= $counts['expense'] ?></a>
                                    <?php } ?>
                                    <?php if ($can_financial && $counts['recurring']) { ?>
                                        <a class="itflow-contact-chip" href="<?= $vendor_url ?>#recurring" title="<?= $counts['recurring'] ?> recurring expense<?= $counts['recurring'] === 1 ? '' : 's' ?>"><i class="fas fa-redo-alt"></i><?= $counts['recurring'] ?></a>
                                    <?php } ?>
                                <?php } else { ?>
                                    <?php foreach (['software' => ['licenses', 'fa-cube', 'license'], 'asset' => ['assets', 'fa-desktop', 'asset'], 'domain' => ['domains', 'fa-globe', 'domain'], 'service' => ['services', 'fa-stream', 'service']] as $kind => [$tab, $icon, $label]) {
                                        if ($can_support && $counts[$kind]) { ?>
                                            <a class="itflow-contact-chip" href="<?= $vendor_url ?>#<?= $tab ?>" title="<?= $counts[$kind] ?> <?= $label ?><?= $counts[$kind] === 1 ? '' : 's' ?>"><i class="fas <?= $icon ?>"></i><?= $counts[$kind] ?></a>
                                        <?php }
                                    } ?>
                                    <?php if ($can_credentials && $counts['credential']) { ?>
                                        <a class="itflow-contact-chip" href="<?= $vendor_url ?>#logins" title="<?= $counts['credential'] ?> login<?= $counts['credential'] === 1 ? '' : 's' ?>"><i class="fas fa-key"></i><?= $counts['credential'] ?></a>
                                    <?php } ?>
                                <?php } ?>
                                <?php
                                $shown = $view === 'ours'
                                    ? ($can_support && $counts['open']) || ($can_financial && ($counts['expense'] || $counts['recurring']))
                                    : ($can_support && ($counts['open'] || $counts['software'] || $counts['asset'] || $counts['domain'] || $counts['service'])) || ($can_credentials && $counts['credential']);
                                if (!$shown) { ?><span class="text-body-secondary small">Nothing yet</span><?php } ?>
                            </div>
                        </td>
                        <?php if ($view === 'clients') { ?>
                        <td><a href="vendors.php?client_id=<?= $row_client_id ?>"><?= escapeHtml($row['client_name']) ?></a></td>
                        <?php } ?>
                        <td class="text-end">
                            <div class="dropdown dropstart">
                                <button class="btn btn-sm btn-light" title="More actions" type="button" data-bs-toggle="dropdown"><i class="fas fa-fw fa-ellipsis-v"></i></button>
                                <div class="dropdown-menu">
                                    <a class="dropdown-item ajax-modal" href="#" data-modal-size="lg" data-modal-url="modals/vendor/vendor.php?id=<?= $vendor_id ?>">
                                        <i class="fas fa-fw fa-eye text-secondary me-2"></i>Quick look
                                    </a>
                                    <?php if ($row_can_edit) { ?>
                                        <a class="dropdown-item ajax-modal" href="#" data-modal-size="lg" data-modal-url="modals/vendor/vendor_edit.php?id=<?= $vendor_id ?>">
                                            <i class="fas fa-fw fa-edit text-secondary me-2"></i>Edit
                                        </a>
                                        <?php if (!$vendor_archived_at) { ?>
                                            <a class="dropdown-item" href="post.php?<?= $vendor_favorite ? 'unfavorite_vendor' : 'favorite_vendor' ?>=<?= $vendor_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>">
                                                <i class="<?= $vendor_favorite ? 'far' : 'fas' ?> fa-fw fa-star text-secondary me-2"></i><?= $vendor_favorite ? 'Unstar' : 'Star' ?>
                                            </a>
                                        <?php } ?>
                                        <div class="dropdown-divider"></div>
                                        <?php if ($vendor_archived_at) { ?>
                                            <a class="dropdown-item" href="post.php?restore_vendor=<?= $vendor_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>">
                                                <i class="fas fa-fw fa-redo text-secondary me-2"></i>Restore
                                            </a>
                                            <?php if ($can_delete) { ?>
                                                <a class="dropdown-item text-danger text-bold confirm-link" href="post.php?delete_vendor=<?= $vendor_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>"
                                                    data-confirm-text="<?= $vendor_name ?> goes for good. Its cases, licenses, assets and domains stay, with no vendor.">
                                                    <i class="fas fa-fw fa-trash me-2"></i>Delete
                                                </a>
                                            <?php } ?>
                                        <?php } else { ?>
                                            <a class="dropdown-item text-danger confirm-link" href="post.php?archive_vendor=<?= $vendor_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>">
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
        'thing' => 'vendors',
        'icon' => 'fa-building',
        'filtered' => $q || $tag_query,
        'views' => ['view'],
        'title' => $view === 'ours' ? 'None of our own vendors yet' : 'No vendors yet',
        'text' => $view === 'ours' ? 'The suppliers expenses are booked against.' : 'Their ISP, phone provider and software makers.',
        'actions' => $can_edit ? [['label' => 'New Vendor', 'modal' => $add_url, 'size' => 'lg']] : [],
    ];
    require_once "../includes/filter_footer.php";
    ?>
</div>

<script src="../js/bulk_actions.js"></script>

<?php
require_once "../includes/footer.php";
