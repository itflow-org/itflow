<?php

/*
 * Locations - one client's sites, or every location the agent can see from
 * All Client Docs. The primary location first. Each row is what a tech needs
 * before heading out - the address (opens in maps, copies in a click), the
 * phone and hours, who to ask for - and what is on site: people, assets,
 * networks and open tickets, each opening that tab on the location's page.
 */

// Default Column Sortby Filter
$sort = "location_name";
$order = "ASC";

// If client_id is in URI then show client Side Bar and client header
if (isset($_GET['client_id'])) {
    require_once "includes/inc_all_client.php";
    $client_query = "AND location_client_id = $client_id";
    $client_url = "client_id=$client_id&";
    // Override Filter Header Archived
    if (isset($_GET['archived']) && $_GET['archived'] == 1) {
        $archived = 1;
        $archive_query = "location_archived_at IS NOT NULL";
    } else {
        $archived = 0;
        $archive_query = "location_archived_at IS NULL";
    }
} else {
    require_once "includes/inc_client_overview_all.php";
    $client_query = '';
    $client_url = '';
    // Override Filter Header Archived
    if (isset($_GET['archived']) && $_GET['archived'] == 1) {
        $archived = 1;
        $archive_query = "(client_archived_at IS NOT NULL OR location_archived_at IS NOT NULL)";
    } else {
        $archived = 0;
        $archive_query = "(client_archived_at IS NULL AND location_archived_at IS NULL)";
    }
}

// Perms
enforceUserPermission('module_client');

$can_edit = lookupUserPermission('module_client') >= 2;
$can_delete = lookupUserPermission('module_client') >= 3 && !empty($config_destructive_deletes_enable);
$can_support = lookupUserPermission('module_support') >= 1;

$client = '';
if (!$client_url) {
    // Client Filter
    if (!empty($_GET['client'])) {
        $client = intval($_GET['client']);
        $client_query = "AND location_client_id = $client";
    }
}

// Tags Filter - a location with any of them
if (isset($_GET['tags']) && is_array($_GET['tags']) && !empty($_GET['tags'])) {
    $tag_filter = implode(",", array_map('intval', $_GET['tags']));
    $tag_query = "AND locations.location_id IN (SELECT location_id FROM location_tags WHERE tag_id IN ($tag_filter))";
} else {
    $tag_filter = 0;
    $tag_query = '';
}

// Only these sort - filter_header.php will hand through any lowercase word, and
// an unknown column takes the page down with a mysqli exception
$sortable_columns = ['location_name', 'location_address', 'location_city', 'location_phone', 'location_hours', 'contact_name', 'client_name', 'location_created_at'];
if (!in_array($sort, $sortable_columns, true)) {
    $sort = 'location_name';
}

$sql = mysqli_query(
    $mysqli,
    "SELECT SQL_CALC_FOUND_ROWS locations.location_id, location_name, location_description, location_address, location_city, location_state,
        location_zip, location_country, location_phone, location_phone_country_code, location_phone_extension, location_fax, location_fax_country_code,
        location_hours, location_photo, location_primary, location_archived_at, location_client_id, location_contact_id,
        client_id, client_name, contact_name, contact_photo, contact_title, contact_archived_at
    FROM locations
    LEFT JOIN clients ON client_id = location_client_id
    LEFT JOIN contacts ON contact_id = location_contact_id AND contact_client_id = location_client_id
    WHERE $archive_query
    $tag_query
    AND (location_name LIKE '%$q%' OR location_description LIKE '%$q%' OR location_address LIKE '%$q%' OR location_city LIKE '%$q%'
        OR location_state LIKE '%$q%' OR location_zip LIKE '%$q%' OR location_country LIKE '%$q%' OR location_phone LIKE '%$phone_query%'
        OR location_notes LIKE '%$q%' OR contact_name LIKE '%$q%' OR client_name LIKE '%$q%'
        OR locations.location_id IN (SELECT location_id FROM location_tags JOIN tags USING (tag_id) WHERE tag_name LIKE '%$q%'))
    " . clientScopeSql('location_client_id') . "
    $client_query
    ORDER BY location_primary DESC, $sort $order, location_name ASC LIMIT $record_from, $record_to"
);

$num_rows = mysqli_fetch_row(mysqli_query($mysqli, "SELECT FOUND_ROWS()"));

$locations = [];
while ($row = mysqli_fetch_assoc($sql)) {
    $locations[intval($row['location_id'])] = $row;
}

// Tags and what is on site for the whole page at once, not per row
$locations_tags = tagsForRecords('location', array_keys($locations));
$locations_counts = locationCounts(array_keys($locations));

?>

<div class="card">
    <div class="card-header bg-dark py-2">
        <h3 class="card-title mt-2"><i class="fa fa-fw fa-map-marker-alt me-2"></i>Locations</h3>
        <div class="card-tools">
            <?php if ($can_edit) { ?>
            <div class="btn-group">
                <button type="button" class="btn btn-primary ajax-modal" data-modal-size="lg" data-modal-url="modals/location/location_add.php?<?= $client_url ?>">
                    <i class="fas fa-plus me-2"></i>New Location
                </button>
                <button type="button" class="btn btn-primary dropdown-toggle dropdown-toggle-split" data-bs-toggle="dropdown"></button>
                <div class="dropdown-menu dropdown-menu-end">
                    <?php if ($client_url) { ?>
                    <a class="dropdown-item text-dark ajax-modal" href="#"
                        data-modal-url="modals/location/location_import.php?<?= $client_url ?>">
                        <i class="fa fa-fw fa-upload me-2"></i>Import
                    </a>
                    <?php } ?>
                    <?php if ($num_rows[0] > 0) { ?>
                        <?php if ($client_url) { ?><div class="dropdown-divider"></div><?php } ?>
                        <a class="dropdown-item text-dark ajax-modal" href="#"
                            data-modal-url="<?= buildExportModalUrl('modals/location/location_export.php', ['client_id', 'client', 'tags', 'archived', 'q']) ?>">
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
            <?php if ($client_url) { ?>
            <input type="hidden" name="client_id" value="<?= $client_id ?>">
            <?php } ?>
            <input type="hidden" name="archived" value="<?= $archived ?>">
            <div class="row g-2 align-items-center">
                <div class="col-lg-4 col-md-6">
                    <div class="input-group">
                        <input type="search" class="form-control" name="q" value="<?php if (isset($q)) { echo stripslashes(escapeHtml($q)); } ?>" placeholder="Search name, address, phone, notes...">
                        <button class="btn btn-dark" title="Search"><i class="fa fa-search"></i></button>
                    </div>
                </div>
                <?php if (!$client_url) { ?>
                <div class="col-lg-3 col-md-6">
                    <select class="form-select select2" name="client" onchange="this.form.submit()">
                        <option value="" <?php if ($client == "") { echo "selected"; } ?>>- All Clients -</option>
                        <?php
                        $sql_clients_filter = mysqli_query($mysqli, "
                            SELECT DISTINCT client_id, client_name, client_abbreviation
                            FROM clients
                            JOIN locations ON location_client_id = client_id
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
                        // Tags in use on locations in this scope, plus any already picked
                        $sql_tags_filter = mysqli_query($mysqli, "
                            SELECT tag_id, tag_name, tag_color, tag_icon FROM tags
                            WHERE tag_type = 2 AND (tag_archived_at IS NULL OR tag_id IN ($tag_filter))
                            AND (
                                EXISTS (SELECT 1 FROM location_tags JOIN locations ON locations.location_id = location_tags.location_id
                                    WHERE location_tags.tag_id = tags.tag_id " . clientScopeSql('location_client_id') . " $client_query)
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
                <div class="<?= $client_url ? 'col-lg-5' : 'col-lg-2' ?> col-md-6">
                    <div class="d-flex justify-content-end gap-2">
                        <a href="?<?= $client_url ?>archived=<?= $archived ? 0 : 1 ?>"
                            class="btn <?= $archived ? 'btn-dark' : 'btn-outline-dark' ?>" title="<?= $archived ? 'Back to active locations' : 'Show archived locations' ?>">
                            <i class="fa fa-fw fa-archive"></i><span class="d-lg-none ms-2">Archived</span>
                        </a>
                        <?php if ($can_edit) { ?>
                        <div class="dropdown" id="bulkActionButton" hidden>
                            <button class="btn btn-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                <i class="fas fa-fw fa-layer-group me-2"></i>Bulk Action (<span id="selectedCount">0</span>)
                            </button>
                            <div class="dropdown-menu dropdown-menu-end">
                                <a class="dropdown-item" href="#" data-tag-picker data-tag-entity="location" data-tag-bulk data-csrf="<?= $_SESSION['csrf_token'] ?>">
                                    <i class="fas fa-fw fa-tags me-2"></i>Tags
                                </a>
                                <div class="dropdown-divider"></div>
                                <?php if ($archived) { ?>
                                    <button class="dropdown-item text-info" type="submit" form="bulkActions" name="bulk_restore_locations">
                                        <i class="fas fa-fw fa-redo me-2"></i>Restore
                                    </button>
                                    <?php if ($can_delete) { ?>
                                        <button class="dropdown-item text-danger text-bold confirm-link" type="submit" form="bulkActions" name="bulk_delete_locations"
                                            data-confirm-text="The locations go for good. Their people, assets, networks, racks and tickets stay, with no location.">
                                            <i class="fas fa-fw fa-trash me-2"></i>Delete
                                        </button>
                                    <?php } ?>
                                <?php } else { ?>
                                    <button class="dropdown-item text-danger confirm-link" type="submit" form="bulkActions" name="bulk_archive_locations">
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
                        <a class="text-reset text-decoration-none" href="?<?= $url_query_strings_sort ?>&sort=location_name&order=<?= $disp ?>">
                            Location <?php if ($sort == 'location_name') { echo $order_icon; } ?>
                        </a>
                    </th>
                    <th>
                        <a class="text-reset text-decoration-none" href="?<?= $url_query_strings_sort ?>&sort=location_city&order=<?= $disp ?>">
                            Address <?php if ($sort == 'location_city') { echo $order_icon; } ?>
                        </a>
                    </th>
                    <th>
                        <a class="text-reset text-decoration-none" href="?<?= $url_query_strings_sort ?>&sort=location_phone&order=<?= $disp ?>">
                            Phone &amp; hours <?php if ($sort == 'location_phone') { echo $order_icon; } ?>
                        </a>
                    </th>
                    <th>
                        <a class="text-reset text-decoration-none" href="?<?= $url_query_strings_sort ?>&sort=contact_name&order=<?= $disp ?>">
                            Ask for <?php if ($sort == 'contact_name') { echo $order_icon; } ?>
                        </a>
                    </th>
                    <th>On site</th>
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

                foreach ($locations as $location_id => $row) {
                    $client_id = intval($row['client_id']);
                    $client_name = escapeHtml($row['client_name']);
                    $location_name = escapeHtml($row['location_name']);
                    $location_description = escapeHtml($row['location_description']);
                    $location_street = escapeHtml($row['location_address']);
                    $location_place = escapeHtml(formatAddress('', $row['location_city'], $row['location_state'], $row['location_zip'], $row['location_country'], ', '));
                    $location_address_text = locationAddressText($row, true);
                    $location_map_url = locationMapUrl($row);
                    $location_directions_url = locationDirectionsUrl($row);
                    $location_phone = contactPhoneLink($row['location_phone'], $row['location_phone_country_code'], $row['location_phone_extension']);
                    $location_hours = escapeHtml($row['location_hours']);
                    $location_primary = intval($row['location_primary']);
                    $location_archived_at = escapeHtml($row['location_archived_at']);
                    $location_tags = $locations_tags[$location_id] ?? [];
                    $counts = $locations_counts[$location_id];
                    $location_url = "location.php?client_id=$client_id&location_id=$location_id";

                    ?>
                    <tr>
                        <?php if ($can_edit) { ?>
                        <td class="checkbox-column bg-light border-end">
                            <div class="form-check">
                                <input class="form-check-input bulk-select" type="checkbox" name="location_ids[]" value="<?= $location_id ?>" aria-label="Select <?= $location_name ?>">
                            </div>
                        </td>
                        <?php } ?>
                        <!-- The whole cell opens the location; the tag pills sit above it -->
                        <td class="position-relative itflow-asset-name-cell">
                            <div class="d-flex align-items-center gap-3">
                                <?= locationTile($row) ?>
                                <div class="min-w-0">
                                    <a class="fw-semibold text-body stretched-link" href="<?= $location_url ?>"><?= $location_name ?></a>
                                    <?php if ($location_primary) { ?><span class="badge rounded-pill text-bg-primary fw-normal ms-1">Primary</span><?php } ?>
                                    <?php if ($location_archived_at) { ?><span class="badge rounded-pill text-bg-secondary fw-normal ms-1">Archived</span><?php } ?>
                                    <?php if ($location_description) { ?>
                                        <div class="small text-body-secondary text-truncate itflow-asset-desc" title="<?= $location_description ?>"><?= $location_description ?></div>
                                    <?php } ?>
                                    <?php if ($location_tags || $can_edit) { ?>
                                        <div class="mt-1 itflow-above-stretch"><?= tagPills('location', $location_id, $location_tags, "locations.php?$client_url") ?></div>
                                    <?php } ?>
                                </div>
                            </div>
                        </td>
                        <td class="itflow-location-address-cell">
                            <?php if ($location_address_text !== '') { ?>
                                <a class="text-reset" href="<?= escapeHtml($location_map_url) ?>" target="_blank" rel="noopener" title="Open in maps">
                                    <?php if ($location_street) { ?><div><?= $location_street ?></div><?php } ?>
                                    <div class="<?= $location_street ? 'small text-body-secondary' : '' ?>"><?= $location_place ?></div>
                                </a>
                                <div class="mt-1 d-flex align-items-center gap-1">
                                    <a class="btn btn-xs btn-outline-secondary" href="<?= escapeHtml($location_directions_url) ?>" target="_blank" rel="noopener" title="Directions"><i class="fas fa-directions me-1"></i>Directions</a>
                                    <button type="button" class="itflow-copy clipboardjs" title="Copy address" data-clipboard-text="<?= escapeHtml($location_address_text) ?>"><i class="far fa-copy"></i></button>
                                </div>
                            <?php } else { ?>
                                <span class="text-body-secondary">-</span>
                            <?php } ?>
                        </td>
                        <td>
                            <?php if ($location_phone) { ?><div class="text-nowrap"><?= $location_phone ?></div><?php } ?>
                            <?php if ($location_hours) { ?><div class="small text-body-secondary"><i class="far fa-clock me-1"></i><?= $location_hours ?></div><?php } ?>
                            <?php if (!$location_phone && !$location_hours) { ?><span class="text-body-secondary">-</span><?php } ?>
                        </td>
                        <td>
                            <?php if ($row['contact_name']) { ?>
                                <?= contactLink($client_id, $row['location_contact_id'], $row['contact_name'], $row['contact_photo'], ['archived' => !empty($row['contact_archived_at'])]) ?>
                            <?php } else { ?>
                                <span class="text-body-secondary">-</span>
                            <?php } ?>
                        </td>
                        <td>
                            <div class="d-flex flex-wrap gap-1">
                                <?php if ($counts['contact']) { ?>
                                    <a class="itflow-contact-chip" href="<?= $location_url ?>#people" title="<?= $counts['contact'] ?> people based here"><i class="fas fa-users"></i><?= $counts['contact'] ?></a>
                                <?php } ?>
                                <?php if ($can_support && $counts['asset']) { ?>
                                    <a class="itflow-contact-chip" href="<?= $location_url ?>#assets" title="<?= $counts['asset'] ?> asset<?= $counts['asset'] === 1 ? '' : 's' ?>"><i class="fas fa-desktop"></i><?= $counts['asset'] ?></a>
                                <?php } ?>
                                <?php if ($can_support && $counts['network']) { ?>
                                    <a class="itflow-contact-chip" href="<?= $location_url ?>#networks" title="<?= $counts['network'] ?> network<?= $counts['network'] === 1 ? '' : 's' ?>"><i class="fas fa-network-wired"></i><?= $counts['network'] ?></a>
                                <?php } ?>
                                <?php if ($can_support && $counts['rack']) { ?>
                                    <a class="itflow-contact-chip" href="<?= $location_url ?>#racks" title="<?= $counts['rack'] ?> rack<?= $counts['rack'] === 1 ? '' : 's' ?>"><i class="fas fa-server"></i><?= $counts['rack'] ?></a>
                                <?php } ?>
                                <?php if ($can_support && $counts['ticket']) { ?>
                                    <a class="itflow-contact-chip is-alert" href="<?= $location_url ?>#tickets" title="<?= $counts['ticket'] ?> open ticket<?= $counts['ticket'] === 1 ? '' : 's' ?>"><i class="fas fa-life-ring"></i><?= $counts['ticket'] ?></a>
                                <?php } ?>
                                <?php if (!array_filter($counts)) { ?><span class="text-body-secondary small">Nothing yet</span><?php } ?>
                            </div>
                        </td>
                        <?php if (!$client_url) { ?>
                        <td><a href="locations.php?client_id=<?= $client_id ?>"><?= $client_name ?></a></td>
                        <?php } ?>
                        <td class="text-end">
                            <div class="dropdown dropstart">
                                <button class="btn btn-sm btn-light" title="More actions" type="button" data-bs-toggle="dropdown"><i class="fas fa-fw fa-ellipsis-v"></i></button>
                                <div class="dropdown-menu">
                                    <a class="dropdown-item ajax-modal" href="#" data-modal-size="lg" data-modal-url="modals/location/location.php?id=<?= $location_id ?>">
                                        <i class="fas fa-fw fa-eye text-secondary me-2"></i>Quick look
                                    </a>
                                    <?php if ($can_edit) { ?>
                                        <a class="dropdown-item ajax-modal" href="#" data-modal-size="lg" data-modal-url="modals/location/location_edit.php?id=<?= $location_id ?>">
                                            <i class="fas fa-fw fa-edit text-secondary me-2"></i>Edit
                                        </a>
                                        <?php if (!$location_primary && !$location_archived_at) { ?>
                                            <a class="dropdown-item" href="post.php?make_location_primary=<?= $location_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>">
                                                <i class="fas fa-fw fa-building text-secondary me-2"></i>Make primary
                                            </a>
                                        <?php } ?>
                                        <?php if (!$location_primary) { ?>
                                            <div class="dropdown-divider"></div>
                                            <?php if ($location_archived_at) { ?>
                                                <a class="dropdown-item" href="post.php?restore_location=<?= $location_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>">
                                                    <i class="fas fa-fw fa-redo text-secondary me-2"></i>Restore
                                                </a>
                                                <?php if ($can_delete) { ?>
                                                    <a class="dropdown-item text-danger text-bold confirm-link" href="post.php?delete_location=<?= $location_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>"
                                                        data-confirm-text="<?= $location_name ?> goes for good. Its people, assets, networks, racks and tickets stay, with no location.">
                                                        <i class="fas fa-fw fa-trash me-2"></i>Delete
                                                    </a>
                                                <?php } ?>
                                            <?php } else { ?>
                                                <a class="dropdown-item text-danger confirm-link" href="post.php?archive_location=<?= $location_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>">
                                                    <i class="fas fa-fw fa-archive me-2"></i>Archive
                                                </a>
                                            <?php } ?>
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
        'thing' => 'locations',
        'icon' => 'fa-map-marker-alt',
        'filtered' => $q || $tag_query,
        'text' => 'Their offices and sites, with the address and phone of each.',
        'actions' => $can_edit ? array_merge(
            [['label' => 'New Location', 'modal' => "modals/location/location_add.php?$client_url", 'size' => 'lg']],
            $client_url ? [['label' => 'Import', 'modal' => "modals/location/location_import.php?$client_url", 'icon' => 'fa-upload']] : []
        ) : [],
    ];
    require_once "../includes/filter_footer.php";
    ?>
</div>

<script src="../js/bulk_actions.js"></script>

<?php
require_once "../includes/footer.php";
