<?php

/*
 * Contacts - everyone at a client (or across all clients).
 *
 * Each row is the person (picture or initials, their flags, title and
 * department), how to reach them (phone, mobile, email - each one a click to
 * call, mail or copy), where they are (changed in place, js/quick_pick.js)
 * and what is linked to them. Tabs across the top narrow it to the roles an
 * MSP reaches for: important people, billing, technical, portal users.
 */

// Default Column Sortby Filter
$sort = "contact_name";
$order = "ASC";

// If client_id is in URI then show client Side Bar and client header
if (isset($_GET['client_id'])) {
    require_once "includes/inc_all_client.php";
    $client_query = "AND contact_client_id = $client_id";
    $client_url = "client_id=$client_id&";
    // Override Filter Header Archived
    if (isset($_GET['archived']) && $_GET['archived'] == 1) {
        $archived = 1;
        $archive_query = "contact_archived_at IS NOT NULL";
    } else {
        $archived = 0;
        $archive_query = "contact_archived_at IS NULL";
    }
} else {
    require_once "includes/inc_client_overview_all.php";
    $client_query = '';
    $client_url = '';
    // Override Filter Header Archived
    if (isset($_GET['archived']) && $_GET['archived'] == 1) {
        $archived = 1;
        $archive_query = "(client_archived_at IS NOT NULL OR contact_archived_at IS NOT NULL)";
    } else {
        $archived = 0;
        $archive_query = "(client_archived_at IS NULL AND contact_archived_at IS NULL)";
    }
}

// Perms
enforceUserPermission('module_client');

$can_edit = lookupUserPermission('module_client') >= 2;
$can_delete = lookupUserPermission('module_client') >= 3;
$can_credentials = lookupUserPermission('module_credential') >= 1;
$can_support = lookupUserPermission('module_support') >= 1;

// The roles an MSP reaches for
$contact_role_tabs = [
    'important' => ['label' => 'Important', 'icon' => 'fa-star', 'query' => "contact_important = 1"],
    'billing' => ['label' => 'Billing', 'icon' => 'fa-file-invoice-dollar', 'query' => "contact_billing = 1"],
    'technical' => ['label' => 'Technical', 'icon' => 'fa-tools', 'query' => "contact_technical = 1"],
    'portal' => ['label' => 'Portal', 'icon' => 'fa-user-circle', 'query' => "contact_user_id > 0 AND COALESCE(user_auth_method, '') != ''"],
];
$role_filter = (isset($_GET['role']) && isset($contact_role_tabs[$_GET['role']])) ? $_GET['role'] : '';
$_GET['role'] = $role_filter;
$role_query = $role_filter ? $contact_role_tabs[$role_filter]['query'] : "1 = 1";

// Tags Filter
if (isset($_GET['tags']) && is_array($_GET['tags']) && !empty($_GET['tags'])) {
    $tag_filter = implode(",", array_map('intval', $_GET['tags']));
    $tag_query = "AND contacts.contact_id IN (SELECT contact_tags.contact_id FROM contact_tags WHERE contact_tags.tag_id IN ($tag_filter))";
} else {
    $tag_filter = 0;
    $tag_query = '';
}

$client = '';
if (!$client_url) {
    // Client Filter
    if (!empty($_GET['client'])) {
        $client = intval($_GET['client']);
        $client_query = "AND contact_client_id = $client";
    }
}

if ($client_url && !empty($_GET['location'])) {
    // Location Filter
    $location_filter = intval($_GET['location']);
    $location_query = "AND contact_location_id = $location_filter";
} else {
    // Default - any
    $location_query = '';
    $location_filter = 0;
}

// Only these sort - filter_header.php will hand through any lowercase word, and
// an unknown column takes the page down with a mysqli exception
$sortable_columns = ['contact_name', 'contact_title', 'contact_department', 'contact_email', 'location_name', 'client_name', 'contact_created_at'];
if (!in_array($sort, $sortable_columns, true)) {
    $sort = 'contact_name';
}

// Tab counts - within the client, location, tag and archive scope, before the search narrows it
$row = mysqli_fetch_assoc(mysqli_query($mysqli, "
    SELECT
        COUNT(*) AS all_count,
        SUM(contact_important = 1) AS important_count,
        SUM(contact_billing = 1) AS billing_count,
        SUM(contact_technical = 1) AS technical_count,
        SUM(contact_user_id > 0 AND COALESCE(user_auth_method, '') != '') AS portal_count
    FROM contacts
    LEFT JOIN clients ON client_id = contact_client_id
    LEFT JOIN users ON user_id = contact_user_id
    WHERE $archive_query
    $tag_query
    " . clientScopeSql('contact_client_id') . "
    $client_query
    $location_query
"));
$tab_counts = ['' => intval($row['all_count'])];
foreach (array_keys($contact_role_tabs) as $tab_key) {
    $tab_counts[$tab_key] = intval($row[$tab_key . '_count']);
}

$sql = mysqli_query($mysqli, "SELECT SQL_CALC_FOUND_ROWS contacts.contact_id, contact_archived_at, contact_billing, contact_client_id,
        contact_department, contact_email, contact_extension, contact_important, contact_location_id, contact_mobile,
        contact_mobile_country_code, contact_name, contact_phone, contact_phone_country_code, contact_photo, contact_primary,
        contact_technical, contact_title, contact_user_id, client_id, client_name, location_archived_at, location_name, user_auth_method
    FROM contacts
    LEFT JOIN clients ON client_id = contact_client_id
    LEFT JOIN locations ON location_id = contact_location_id
    LEFT JOIN users ON user_id = contact_user_id
    WHERE $archive_query
    $tag_query
    AND ($role_query)
    AND (contact_name LIKE '%$q%' OR contact_title LIKE '%$q%' OR location_name LIKE '%$q%' OR contact_email LIKE '%$q%' OR contact_department LIKE '%$q%'
        OR contact_phone LIKE '%$phone_query%' OR contact_extension LIKE '%$q%' OR contact_mobile LIKE '%$phone_query%' OR client_name LIKE '%$q%'
        OR contacts.contact_id IN (SELECT contact_tags.contact_id FROM contact_tags JOIN tags ON tags.tag_id = contact_tags.tag_id WHERE tag_name LIKE '%$q%'))
    " . clientScopeSql('contact_client_id') . "
    $client_query
    $location_query
    ORDER BY contact_primary DESC, contact_important DESC, $sort $order LIMIT $record_from, $record_to"
);

$num_rows = mysqli_fetch_row(mysqli_query($mysqli, "SELECT FOUND_ROWS()"));

$contacts = [];
while ($row = mysqli_fetch_assoc($sql)) {
    $contacts[intval($row['contact_id'])] = $row;
}

// Tags and what is linked to each contact, for the whole page in a handful of
// queries rather than five per row
$contacts_tags = tagsForRecords('contact', array_keys($contacts));
$linked = ['assets' => [], 'credentials' => [], 'licenses' => [], 'tickets' => [], 'open_tickets' => []];
if ($contacts) {
    $ids = implode(',', array_keys($contacts));
    $sql_linked = mysqli_query($mysqli, "SELECT asset_contact_id AS id, COUNT(*) AS n FROM assets WHERE asset_contact_id IN ($ids) AND asset_archived_at IS NULL GROUP BY asset_contact_id");
    while ($row = mysqli_fetch_assoc($sql_linked)) {
        $linked['assets'][intval($row['id'])] = intval($row['n']);
    }
    if ($can_credentials) {
        $sql_linked = mysqli_query($mysqli, "SELECT credential_contact_id AS id, COUNT(*) AS n FROM credentials WHERE credential_contact_id IN ($ids) AND credential_archived_at IS NULL GROUP BY credential_contact_id");
        while ($row = mysqli_fetch_assoc($sql_linked)) {
            $linked['credentials'][intval($row['id'])] = intval($row['n']);
        }
    }
    $sql_linked = mysqli_query($mysqli, "SELECT software_contacts.contact_id AS id, COUNT(*) AS n FROM software_contacts
        JOIN software ON software.software_id = software_contacts.software_id
        WHERE software_contacts.contact_id IN ($ids) AND software_archived_at IS NULL GROUP BY software_contacts.contact_id");
    while ($row = mysqli_fetch_assoc($sql_linked)) {
        $linked['licenses'][intval($row['id'])] = intval($row['n']);
    }
    if ($can_support) {
        $sql_linked = mysqli_query($mysqli, "SELECT ticket_contact_id AS id, COUNT(*) AS n, SUM(ticket_resolved_at IS NULL AND ticket_closed_at IS NULL) AS open_n
            FROM tickets WHERE ticket_contact_id IN ($ids) AND ticket_archived_at IS NULL GROUP BY ticket_contact_id");
        while ($row = mysqli_fetch_assoc($sql_linked)) {
            $linked['tickets'][intval($row['id'])] = intval($row['n']);
            $linked['open_tickets'][intval($row['id'])] = intval($row['open_n']);
        }
    }
}

$search_noun = $role_filter ? strtolower($contact_role_tabs[$role_filter]['label']) . ' contacts' : 'contacts';

?>

<div class="card">
    <div class="card-header bg-dark py-2">
        <h3 class="card-title mt-2"><i class="fa fa-fw fa-address-book me-2"></i>Contacts</h3>
        <div class="card-tools">
            <?php if ($can_edit) { ?>
            <div class="btn-group">
                <button type="button" class="btn btn-primary ajax-modal" data-modal-size="lg" data-modal-url="modals/contact/contact_add.php?<?= $client_url ?>">
                    <i class="fas fa-plus me-2"></i>New Contact
                </button>
                <button type="button" class="btn btn-primary dropdown-toggle dropdown-toggle-split" data-bs-toggle="dropdown"></button>
                <div class="dropdown-menu dropdown-menu-end">
                    <?php if ($client_url) { ?>
                    <a class="dropdown-item text-dark ajax-modal" href="#"
                        data-modal-url="modals/contact/contact_import.php?<?= $client_url ?>">
                        <i class="fa fa-fw fa-upload me-2"></i>Import
                    </a>
                    <div class="dropdown-divider"></div>
                    <?php } ?>
                    <a class="dropdown-item text-dark ajax-modal" href="#"
                        data-modal-url="<?= buildExportModalUrl('modals/contact/contact_export.php', ['client_id', 'client', 'location', 'tags', 'archived', 'q']) ?>">
                        <i class="fa fa-fw fa-download me-2"></i>Export
                    </a>
                </div>
            </div>
            <?php } ?>
        </div>
    </div>

    <?php if ($tab_counts['']) { ?>
        <!-- Roles -->
        <div class="card-header py-2 itflow-asset-tabs">
            <nav class="nav nav-pills flex-nowrap overflow-auto">
                <a class="nav-link text-nowrap <?= $role_filter === '' ? 'active' : '' ?>" href="?<?= $url_query_strings_sort ?>&role=">
                    All <span class="badge rounded-pill ms-1"><?= $tab_counts[''] ?></span>
                </a>
                <?php foreach ($contact_role_tabs as $tab_key => $tab) {
                    if (!$tab_counts[$tab_key] && $role_filter !== $tab_key) {
                        continue;
                    } ?>
                    <a class="nav-link text-nowrap <?= $role_filter === $tab_key ? 'active' : '' ?>" href="?<?= $url_query_strings_sort ?>&role=<?= $tab_key ?>">
                        <i class="fas fa-fw <?= $tab['icon'] ?> me-1"></i><?= $tab['label'] ?> <span class="badge rounded-pill ms-1"><?= $tab_counts[$tab_key] ?></span>
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
            <input type="hidden" name="role" value="<?= $role_filter ?>">
            <input type="hidden" name="archived" value="<?= $archived ?>">
            <div class="row g-2 align-items-center">

                <div class="col-lg-4 col-md-6">
                    <div class="input-group">
                        <input type="search" class="form-control" name="q" value="<?php if (isset($q)) { echo stripslashes(escapeHtml($q)); } ?>" placeholder="Search <?= $search_noun ?>, phones, emails...">
                        <button class="btn btn-dark" title="Search"><i class="fa fa-search"></i></button>
                    </div>
                </div>

                <?php if ($client_url) { ?>
                <div class="col-lg-3 col-md-6">
                    <select class="form-select select2" name="location" onchange="this.form.submit()">
                        <option value="">- All Locations -</option>
                        <?php
                        $sql_locations_filter = mysqli_query($mysqli, "
                            SELECT location_id, location_name
                            FROM locations
                            WHERE location_client_id = $client_id
                            AND (EXISTS (SELECT 1 FROM contacts WHERE contact_location_id = location_id AND $archive_query) OR location_id = $location_filter)
                            ORDER BY location_name ASC
                        ");
                        while ($row = mysqli_fetch_assoc($sql_locations_filter)) {
                            $location_id = intval($row['location_id']); ?>
                            <option <?php if ($location_filter == $location_id) { echo "selected"; } ?> value="<?= $location_id ?>"><?= escapeHtml($row['location_name']) ?></option>
                        <?php } ?>
                    </select>
                </div>
                <?php } else { ?>
                <div class="col-lg-3 col-md-6">
                    <select class="form-select select2" name="client" onchange="this.form.submit()">
                        <option value="" <?php if ($client == "") { echo "selected"; } ?>>- All Clients -</option>
                        <?php
                        $sql_clients_filter = mysqli_query($mysqli, "
                            SELECT DISTINCT client_id, client_name, client_abbreviation
                            FROM clients
                            JOIN contacts ON contact_client_id = client_id
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
                        // Tags in use on contacts in this scope, plus any already picked
                        $sql_tags_filter = mysqli_query($mysqli, "
                            SELECT tag_id, tag_name, tag_color, tag_icon
                            FROM tags
                            WHERE tag_type = 3
                            AND (
                                EXISTS (SELECT 1 FROM contact_tags JOIN contacts ON contacts.contact_id = contact_tags.contact_id
                                    WHERE contact_tags.tag_id = tags.tag_id " . clientScopeSql('contact_client_id') . " $client_query)
                                OR tag_id IN ($tag_filter)
                            )
                            ORDER BY tag_name ASC
                        ");
                        while ($row = mysqli_fetch_assoc($sql_tags_filter)) {
                            $tag_id = intval($row['tag_id']); ?>
                            <option value="<?= $tag_id ?>" <?php if (isset($_GET['tags']) && in_array($tag_id, $_GET['tags'])) { echo 'selected'; } ?><?= selectOptionColor($row['tag_color'] ?? '', $row['tag_icon'] ?? '') ?>><?= escapeHtml($row['tag_name']) ?></option>
                        <?php } ?>
                    </select>
                </div>

                <div class="col-lg-2 col-md-6">
                    <div class="d-flex justify-content-end gap-2">
                        <a href="?<?= $client_url ?>archived=<?= $archived ? 0 : 1 ?>"
                            class="btn <?= $archived ? 'btn-dark' : 'btn-outline-dark' ?>" title="<?= $archived ? 'Back to active contacts' : 'Show archived contacts' ?>">
                            <i class="fa fa-fw fa-archive"></i><span class="d-xl-none ms-2">Archived</span>
                        </a>
                        <?php if ($can_edit) { ?>
                        <div class="dropdown" id="bulkActionButton" hidden>
                            <button class="btn btn-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                <i class="fas fa-fw fa-layer-group me-2"></i>Bulk Action (<span id="selectedCount">0</span>)
                            </button>
                            <div class="dropdown-menu dropdown-menu-end">
                                <?php if ($client_url) { ?>
                                <a class="dropdown-item ajax-modal" href="#"
                                    data-modal-url="modals/contact/contact_bulk_assign_location.php?<?= $client_url ?>"
                                    data-bulk="true">
                                    <i class="fas fa-fw fa-map-marker-alt me-2"></i>Assign Location
                                </a>
                                <?php } ?>
                                <a class="dropdown-item ajax-modal" href="#"
                                    data-modal-url="modals/contact/contact_bulk_edit_phone.php"
                                    data-bulk="true">
                                    <i class="fas fa-fw fa-phone-alt me-2"></i>Set Phone Number
                                </a>
                                <a class="dropdown-item ajax-modal" href="#"
                                    data-modal-url="modals/contact/contact_bulk_edit_department.php"
                                    data-bulk="true">
                                    <i class="fas fa-fw fa-users me-2"></i>Set Department
                                </a>
                                <a class="dropdown-item ajax-modal" href="#"
                                    data-modal-url="modals/contact/contact_bulk_edit_role.php"
                                    data-bulk="true">
                                    <i class="fas fa-fw fa-user-shield me-2"></i>Set Roles
                                </a>
                                <a class="dropdown-item" href="#" data-tag-picker data-tag-entity="contact" data-tag-bulk data-csrf="<?= $_SESSION['csrf_token'] ?>">
                                    <i class="fas fa-fw fa-tags me-2"></i>Tags
                                </a>
                                <div class="dropdown-divider"></div>
                                <a class="dropdown-item ajax-modal" href="#"
                                    data-modal-url="modals/contact/contact_bulk_email.php"
                                    data-modal-size="lg"
                                    data-bulk="true">
                                    <i class="fas fa-fw fa-paper-plane me-2"></i>Send Email
                                </a>
                                <div class="dropdown-divider"></div>
                                <?php if ($archived) { ?>
                                    <button class="dropdown-item text-info"
                                        type="submit" form="bulkActions" name="bulk_restore_contacts">
                                        <i class="fas fa-fw fa-redo me-2"></i>Restore
                                    </button>
                                    <?php if ($can_delete) { ?>
                                        <button class="dropdown-item text-danger text-bold confirm-link"
                                            type="submit" form="bulkActions" name="bulk_delete_contacts">
                                            <i class="fas fa-fw fa-trash me-2"></i>Delete
                                        </button>
                                    <?php } ?>
                                <?php } else { ?>
                                    <button class="dropdown-item text-danger confirm-link"
                                        type="submit" form="bulkActions" name="bulk_archive_contacts">
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
    <form id="bulkActions" action="post.php" method="post" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 itflow-contact-table">
                <thead class="<?php if (!$num_rows[0]) { echo "d-none"; } ?> text-nowrap table-light">
                <tr>
                    <th class="checkbox-column border-end">
                        <div class="form-check">
                            <input class="form-check-input" id="selectAllCheckbox" type="checkbox" onclick="checkAll(this)" title="Select all on this page">
                        </div>
                    </th>
                    <th>
                        <a class="text-reset text-decoration-none" href="?<?= $url_query_strings_sort ?>&sort=contact_name&order=<?= $disp ?>">
                            Name <?php if ($sort == 'contact_name') { echo $order_icon; } ?>
                        </a>
                    </th>
                    <th>
                        <a class="text-reset text-decoration-none" href="?<?= $url_query_strings_sort ?>&sort=contact_email&order=<?= $disp ?>">
                            Reach <?php if ($sort == 'contact_email') { echo $order_icon; } ?>
                        </a>
                    </th>
                    <th>
                        <a class="text-reset text-decoration-none" href="?<?= $url_query_strings_sort ?>&sort=location_name&order=<?= $disp ?>">
                            Location <?php if ($sort == 'location_name') { echo $order_icon; } ?>
                        </a>
                    </th>
                    <th class="d-none d-lg-table-cell">Linked</th>
                    <?php if (!$client_url) { ?>
                    <th>
                        <a class="text-reset text-decoration-none" href="?<?= $url_query_strings_sort ?>&sort=client_name&order=<?= $disp ?>">
                            Client <?php if ($sort == 'client_name') { echo $order_icon; } ?>
                        </a>
                    </th>
                    <?php } ?>
                    <th class="position-relative"><span class="visually-hidden">Actions</span></th>
                </tr>
                </thead>
                <tbody>
                <?php

                foreach ($contacts as $contact_id => $row) {
                    $client_id = intval($row['client_id']);
                    $client_name = escapeHtml($row['client_name']);
                    $contact_name = escapeHtml($row['contact_name']);
                    $contact_title = escapeHtml($row['contact_title']);
                    $contact_department = escapeHtml($row['contact_department']);
                    $contact_email = escapeHtml($row['contact_email']);
                    $contact_phone_link = contactPhoneLink($row['contact_phone'], $row['contact_phone_country_code'], $row['contact_extension'], 'text-reset');
                    $contact_mobile_link = contactPhoneLink($row['contact_mobile'], $row['contact_mobile_country_code'], '', 'text-reset');
                    $contact_primary = intval($row['contact_primary']);
                    $contact_archived_at = $row['contact_archived_at'];
                    $contact_role_line = implode(' &middot; ', array_filter([$contact_title, $contact_department]));
                    $contact_tags = $contacts_tags[$contact_id] ?? [];
                    $contact_url = "contact.php?client_id=$client_id&contact_id=$contact_id";

                    $assets_n = $linked['assets'][$contact_id] ?? 0;
                    $credentials_n = $linked['credentials'][$contact_id] ?? 0;
                    $licenses_n = $linked['licenses'][$contact_id] ?? 0;
                    $tickets_n = $linked['tickets'][$contact_id] ?? 0;
                    $open_tickets_n = $linked['open_tickets'][$contact_id] ?? 0;

                    ?>
                    <tr>
                        <td class="checkbox-column bg-light border-end">
                            <div class="form-check">
                                <input class="form-check-input bulk-select" type="checkbox" name="contact_ids[]" value="<?= $contact_id ?>" aria-label="Select <?= $contact_name ?>">
                            </div>
                        </td>
                        <!-- The whole cell opens the contact (stretched-link); the tag pills sit above it -->
                        <td class="position-relative itflow-contact-name-cell">
                            <div class="d-flex align-items-center gap-3">
                                <?= contactAvatar($client_id, $contact_id, $row['contact_name'], $row['contact_photo']) ?>
                                <div class="min-w-0">
                                    <div class="d-flex flex-wrap align-items-center column-gap-2">
                                        <a class="fw-semibold text-body stretched-link" href="<?= $contact_url ?>"><?= $contact_name ?></a>
                                        <?= contactFlagPills($row) ?>
                                        <?php if ($contact_archived_at) { ?><span class="badge rounded-pill text-bg-secondary fw-normal">Archived</span><?php } ?>
                                    </div>
                                    <?php if ($contact_role_line) { ?>
                                        <div class="small text-body-secondary text-truncate itflow-contact-role"><?= $contact_role_line ?></div>
                                    <?php } ?>
                                    <?php if ($contact_tags || $can_edit) { ?>
                                        <div class="mt-1 itflow-above-stretch"><?= tagPills('contact', $contact_id, $contact_tags, "contacts.php?$client_url") ?></div>
                                    <?php } ?>
                                </div>
                            </div>
                        </td>
                        <td class="small itflow-contact-reach">
                            <?php if ($contact_phone_link) { ?>
                                <div class="text-nowrap"><i class="fas fa-fw fa-phone text-body-secondary me-1" title="Phone"></i><?= $contact_phone_link ?></div>
                            <?php } ?>
                            <?php if ($contact_mobile_link) { ?>
                                <div class="text-nowrap"><i class="fas fa-fw fa-mobile-alt text-body-secondary me-1" title="Mobile"></i><?= $contact_mobile_link ?></div>
                            <?php } ?>
                            <?php if ($contact_email) { ?>
                                <div class="text-nowrap"><i class="far fa-fw fa-envelope text-body-secondary me-1" title="Email"></i><a class="text-reset" href="mailto:<?= $contact_email ?>"><?= $contact_email ?></a><button type="button" class="itflow-copy clipboardjs" title="Copy email" data-clipboard-text="<?= $contact_email ?>"><i class="far fa-copy"></i></button></div>
                            <?php } ?>
                            <?php if (!$contact_phone_link && !$contact_mobile_link && !$contact_email) { ?>
                                <span class="text-body-secondary">-</span>
                            <?php } ?>
                        </td>
                        <td>
                            <?php if ($can_edit && !$contact_archived_at) { ?>
                                <?= quickPick('contact_location', $contact_id, (string) intval($row['contact_location_id']), contactLocationHtml($row), 'list', 0, $client_id) ?>
                            <?php } elseif ($row['location_name']) { ?>
                                <span class="<?= $row['location_archived_at'] ? 'text-danger text-decoration-line-through' : '' ?>"><?= escapeHtml($row['location_name']) ?></span>
                            <?php } else { ?>
                                <span class="text-body-secondary">-</span>
                            <?php } ?>
                        </td>
                        <td class="d-none d-lg-table-cell">
                            <div class="d-flex flex-wrap gap-1">
                                <?php if ($assets_n) { ?>
                                    <a class="itflow-contact-chip" href="<?= $contact_url ?>#assets" title="<?= $assets_n ?> asset<?= $assets_n === 1 ? '' : 's' ?>"><i class="fas fa-desktop"></i><?= $assets_n ?></a>
                                <?php } ?>
                                <?php if ($credentials_n) { ?>
                                    <a class="itflow-contact-chip" href="<?= $contact_url ?>#credentials" title="<?= $credentials_n ?> credential<?= $credentials_n === 1 ? '' : 's' ?>"><i class="fas fa-key"></i><?= $credentials_n ?></a>
                                <?php } ?>
                                <?php if ($licenses_n) { ?>
                                    <a class="itflow-contact-chip" href="<?= $contact_url ?>#licenses" title="<?= $licenses_n ?> license<?= $licenses_n === 1 ? '' : 's' ?>"><i class="fas fa-cube"></i><?= $licenses_n ?></a>
                                <?php } ?>
                                <?php if ($tickets_n) { ?>
                                    <a class="itflow-contact-chip <?= $open_tickets_n ? 'is-alert' : '' ?>" href="<?= $contact_url ?>#tickets" title="<?= $tickets_n ?> ticket<?= $tickets_n === 1 ? '' : 's' ?><?= $open_tickets_n ? ", $open_tickets_n open" : '' ?>"><i class="fas fa-life-ring"></i><?= $open_tickets_n ? "$open_tickets_n open" : $tickets_n ?></a>
                                <?php } ?>
                                <?php if (!$assets_n && !$credentials_n && !$licenses_n && !$tickets_n) { ?>
                                    <span class="text-body-secondary">-</span>
                                <?php } ?>
                            </div>
                        </td>
                        <?php if (!$client_url) { ?>
                        <td><a href="contacts.php?client_id=<?= $client_id ?>"><?= $client_name ?></a></td>
                        <?php } ?>
                        <td class="text-end">
                            <?php if ($can_edit) { ?>
                                <div class="dropdown dropstart">
                                    <button class="btn btn-sm btn-light" title="More actions" type="button" data-bs-toggle="dropdown"><i class="fas fa-fw fa-ellipsis-v"></i></button>
                                    <div class="dropdown-menu">
                                        <a class="dropdown-item ajax-modal" href="#" data-modal-size="lg" data-modal-url="modals/contact/contact_edit.php?id=<?= $contact_id ?>">
                                            <i class="fas fa-fw fa-edit text-secondary me-2"></i>Edit
                                        </a>
                                        <a class="dropdown-item ajax-modal" href="#" data-modal-url="modals/contact/contact_note_add.php?id=<?= $contact_id ?>">
                                            <i class="fas fa-fw fa-sticky-note text-secondary me-2"></i>Add Note
                                        </a>
                                        <?php if (!$contact_primary) { ?>
                                            <div class="dropdown-divider"></div>
                                            <?php if ($contact_archived_at) { ?>
                                                <a class="dropdown-item confirm-link" href="post.php?restore_contact=<?= $contact_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>">
                                                    <i class="fas fa-fw fa-redo text-secondary me-2"></i>Restore
                                                </a>
                                            <?php } else { ?>
                                                <a class="dropdown-item text-danger confirm-link" href="post.php?archive_contact=<?= $contact_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>">
                                                    <i class="fas fa-fw fa-archive me-2"></i>Archive
                                                </a>
                                                <?php if ($can_delete) { ?>
                                                    <a class="dropdown-item text-danger confirm-link" href="post.php?anonymize_contact=<?= $contact_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>">
                                                        <i class="fas fa-fw fa-user-secret me-2"></i>Anonymize &amp; Archive
                                                    </a>
                                                <?php } ?>
                                            <?php } ?>
                                            <?php if ($can_delete && $config_destructive_deletes_enable) { ?>
                                                <a class="dropdown-item text-danger text-bold confirm-link" href="post.php?delete_contact=<?= $contact_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>">
                                                    <i class="fas fa-fw fa-trash me-2"></i>Delete
                                                </a>
                                            <?php } ?>
                                        <?php } ?>
                                    </div>
                                </div>
                            <?php } ?>
                        </td>
                    </tr>

                <?php } ?>

                </tbody>
            </table>
        </div>
    </form>
    <?php
    $empty_state = [
        'thing' => 'contacts',
        'icon' => 'fa-address-book',
        'views' => ['role'],
        'view' => emptyStatePill($contact_role_tabs, $role_filter),
        'text' => 'The people at the client - who to call, and who gets the invoices.',
        'actions' => $can_edit ? array_merge(
            [['label' => 'New Contact', 'modal' => "modals/contact/contact_add.php?$client_url", 'size' => 'lg']],
            $client_url ? [['label' => 'Import', 'modal' => "modals/contact/contact_import.php?$client_url", 'icon' => 'fa-upload']] : []
        ) : [],
    ];
    ?>
    <?php require_once "../includes/filter_footer.php"; ?>
</div>

<script src="../js/bulk_actions.js"></script>

<?php
require_once "../includes/footer.php";
