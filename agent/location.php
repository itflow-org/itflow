<?php

/*
 * Location - one of a client's sites.
 *
 * Built for the tech heading out: how to get there (directions, phone,
 * hours), who to ask for, how to get in (the access notes, always in view),
 * and then what is on site in tabs with counts - the people based there, the
 * assets, networks and racks, the tickets for it (open first), the documents
 * and files about it, and its history. Things are moved here in bulk with
 * Move here, after an office move or a new site goes in. The open tab follows
 * the URL hash (#assets) and is remembered between visits.
 */

// If client_id is in URI then show client Side Bar and client header
if (isset($_GET['client_id'])) {
    require_once "includes/inc_all_client.php";
    $client_url = "client_id=$client_id&";
} else {
    require_once "includes/inc_client_overview_all.php";
    $client_url = '';
}

// Perms
enforceUserPermission('module_client');

$location_id = intval($_GET['location_id'] ?? 0);
$location = locationRow($location_id);

if (!$location || ($client_url && intval($location['location_client_id']) !== intval($client_id))) {

    echo "<center><h1 class='text-secondary mt-5'>Nothing to see here</h1><a class='btn btn-lg btn-secondary mt-3' href='javascript:history.back()'><i class='fa fa-fw fa-arrow-left'></i> Go Back</a></center>";

} else {

    $client_id = intval($location['location_client_id']);
    enforceClientAccess();

    $client_name = escapeHtml($location['client_name']);
    $location_name = escapeHtml($location['location_name']);
    $location_description = escapeHtml($location['location_description']);
    $location_address_lines = escapeHtml(formatAddress($location['location_address'], $location['location_city'], $location['location_state'], $location['location_zip'], $location['location_country'], "\n"));
    $location_address_text = locationAddressText($location, true);
    $location_map_url = locationMapUrl($location);
    $location_directions_url = locationDirectionsUrl($location);
    $location_phone = contactPhoneLink($location['location_phone'], $location['location_phone_country_code'], $location['location_phone_extension']);
    $location_phone_dial = preg_replace('/[^0-9]/', '', (string) $location['location_phone']);
    $location_fax = $location['location_fax'] ? escapeHtml(formatPhoneNumber($location['location_fax'], $location['location_fax_country_code'])) : '';
    $location_hours = escapeHtml($location['location_hours']);
    $location_notes = escapeHtml($location['location_notes']);
    $location_primary = intval($location['location_primary']);
    $location_archived_at = escapeHtml($location['location_archived_at']);
    $location_created_at = escapeHtml($location['location_created_at']);

    $can_edit = lookupUserPermission('module_client') >= 2;
    $can_delete = lookupUserPermission('module_client') >= 3 && !empty($config_destructive_deletes_enable);
    $can_support = lookupUserPermission('module_support') >= 1;
    $can_support_edit = lookupUserPermission('module_support') >= 2;
    $can_move = $can_edit || $can_support_edit;

    // Override Tab Title // No Sanitizing needed as this var will only be used in the tab title
    $page_title = $location['location_name'];

    $location_tags_display = tagPills('location', $location_id, tagsForRecord('location', $location_id), "locations.php?client_id=$client_id&");

    // People based here
    $people = [];
    $sql = mysqli_query($mysqli, "SELECT contact_id, contact_name, contact_title, contact_email, contact_phone, contact_phone_country_code, contact_extension,
            contact_mobile, contact_mobile_country_code, contact_photo, contact_client_id, contact_primary, contact_important, contact_billing, contact_technical
        FROM contacts WHERE contact_location_id = $location_id AND contact_archived_at IS NULL
        ORDER BY contact_primary DESC, contact_important DESC, contact_name ASC");
    while ($row = mysqli_fetch_assoc($sql)) {
        $people[] = $row;
    }

    // What is on site
    $assets = $networks = $racks = $tickets = [];
    $ticket_count = $ticket_open_count = 0;
    if ($can_support) {
        $sql = mysqli_query($mysqli, "SELECT asset_id, asset_name, asset_type, asset_make, asset_model, asset_status, asset_physical_location,
                interface_ip, contact_id, contact_name, contact_photo, contact_archived_at
            FROM assets
            LEFT JOIN asset_interfaces ON interface_asset_id = asset_id AND interface_primary = 1
            LEFT JOIN contacts ON contact_id = asset_contact_id
            WHERE asset_location_id = $location_id AND asset_archived_at IS NULL
            ORDER BY asset_type ASC, asset_name ASC");
        while ($row = mysqli_fetch_assoc($sql)) {
            $assets[] = $row;
        }
        $sql = mysqli_query($mysqli, "SELECT network_id, network_name, network_description, network_type, network, network_vlan, network_gateway, network_dhcp_range
            FROM networks WHERE network_location_id = $location_id AND network_archived_at IS NULL ORDER BY network_name ASC");
        while ($row = mysqli_fetch_assoc($sql)) {
            $networks[intval($row['network_id'])] = $row;
        }
        $network_usage = networkUsage($networks);
        $sql = mysqli_query($mysqli, "SELECT rack_id, rack_name, rack_type, rack_model, rack_units, rack_physical_location
            FROM racks WHERE rack_location_id = $location_id AND rack_archived_at IS NULL ORDER BY rack_name ASC");
        while ($row = mysqli_fetch_assoc($sql)) {
            $racks[] = $row;
        }

        // Tickets for this site - every open one, then the most recent closed ones
        [$ticket_count, $ticket_open_count] = ticketCounts("tickets.ticket_location_id = $location_id");
        $tickets = ticketRows("tickets.ticket_location_id = $location_id", max(25, $ticket_open_count));
    }

    $documents = $can_support ? linkedRecords('location', $location_id, 'document') : [];
    $files = $can_support ? linkedRecords('location', $location_id, 'file') : [];
    $history = locationHistory($location_id, 100);

    $location_tabs = [
        'people' => ['label' => 'People', 'icon' => 'fa-users', 'count' => count($people)],
        'assets' => ['label' => 'Assets', 'icon' => 'fa-desktop', 'count' => count($assets)],
        'networks' => ['label' => 'Networks', 'icon' => 'fa-network-wired', 'count' => count($networks)],
        'racks' => ['label' => 'Racks', 'icon' => 'fa-server', 'count' => count($racks)],
        'tickets' => ['label' => 'Tickets', 'icon' => 'fa-life-ring', 'count' => $ticket_count, 'badge' => $ticket_open_count],
        'documents' => ['label' => 'Documents', 'icon' => 'fa-file-alt', 'count' => count($documents)],
        'files' => ['label' => 'Files', 'icon' => 'fa-paperclip', 'count' => count($files)],
        'history' => ['label' => 'History', 'icon' => 'fa-history', 'count' => count($history)],
    ];
    if (!$can_support) {
        unset($location_tabs['assets'], $location_tabs['networks'], $location_tabs['racks'], $location_tabs['tickets'], $location_tabs['documents'], $location_tabs['files']);
    }

    $move_url = "modals/location/location_assign.php?id=$location_id&kind=";
    $unset_url = function ($kind, $id) use ($location_id) {
        return "post.php?unset_location_record=" . intval($id) . "&location_id=$location_id&kind=$kind&csrf_token=" . $_SESSION['csrf_token'];
    };
    $link_url = "modals/link/link.php?from=location&id=$location_id&to=";

    ?>

    <ol class="breadcrumb d-print-none">
        <li class="breadcrumb-item">
            <a href="client_overview.php?client_id=<?= $client_id ?>"><?= $client_name ?></a>
        </li>
        <li class="breadcrumb-item">
            <a href="locations.php?client_id=<?= $client_id ?>">Locations</a>
        </li>
        <li class="breadcrumb-item active"><?= $location_name ?></li>
    </ol>

    <!-- Where it is and how to get there -->
    <div class="card mb-3">
        <div class="card-body d-flex flex-wrap align-items-center gap-3">
            <?php if ($location['location_photo']) { ?>
                <a href="<?= "../uploads/clients/$client_id/" . escapeHtml($location['location_photo']) ?>" target="_blank" rel="noopener" title="Open photo"><?= locationTile($location, 'lg') ?></a>
            <?php } else { ?>
                <?= locationTile($location, 'lg') ?>
            <?php } ?>

            <div class="flex-grow-1 min-w-0">
                <h1 class="h4 fw-semibold mb-1 d-flex flex-wrap align-items-center gap-2">
                    <span><?= $location_name ?></span>
                    <?php if ($location_primary) { ?><span class="badge rounded-pill text-bg-primary fw-normal fs-6">Primary</span><?php } ?>
                    <?php if ($location_archived_at) { ?><span class="badge rounded-pill text-bg-secondary fw-normal fs-6">Archived</span><?php } ?>
                </h1>
                <div class="text-body-secondary small d-flex flex-wrap column-gap-3 row-gap-1">
                    <?php if ($location_address_text !== '') { ?>
                        <span class="d-inline-flex align-items-center"><i class="fas fa-fw fa-map-marker-alt me-1"></i><?= escapeHtml($location_address_text) ?><button type="button" class="itflow-copy clipboardjs" title="Copy address" data-clipboard-text="<?= escapeHtml($location_address_text) ?>"><i class="far fa-copy"></i></button></span>
                    <?php } ?>
                    <?php if ($location_hours) { ?><span><i class="fas fa-fw fa-clock me-1"></i><?= $location_hours ?></span><?php } ?>
                    <?php if ($location_description) { ?><span><?= $location_description ?></span><?php } ?>
                </div>
                <?php if ($location_tags_display) { ?>
                    <div class="mt-2"><?= $location_tags_display ?></div>
                <?php } ?>
            </div>

            <div class="d-flex flex-wrap gap-2 d-print-none">
                <?php if ($location_directions_url) { ?>
                    <a class="btn btn-outline-primary" href="<?= escapeHtml($location_directions_url) ?>" target="_blank" rel="noopener"><i class="fas fa-directions me-2"></i>Directions</a>
                <?php } ?>
                <?php if ($location_phone_dial !== '') { ?>
                    <a class="btn btn-outline-primary" href="tel:<?= escapeHtml(($location['location_phone_country_code'] ? '+' . preg_replace('/[^0-9]/', '', $location['location_phone_country_code']) : '') . $location_phone_dial) ?>"><i class="fas fa-phone me-2"></i>Call</a>
                <?php } ?>
                <?php if ($can_edit || $can_support_edit) { ?>
                    <div class="dropdown">
                        <button type="button" class="btn btn-primary dropdown-toggle" data-bs-toggle="dropdown"><i class="fas fa-plus me-2"></i>New</button>
                        <div class="dropdown-menu dropdown-menu-end">
                            <?php if ($can_support_edit) { ?>
                                <a class="dropdown-item ajax-modal" href="#" data-modal-size="lg" data-modal-url="modals/ticket/ticket_add.php?client_id=<?= $client_id ?>&location_id=<?= $location_id ?>">
                                    <i class="fa fa-fw fa-life-ring text-secondary me-2"></i>Ticket here
                                </a>
                            <?php } ?>
                            <a class="dropdown-item ajax-modal" href="#" data-modal-size="lg" data-modal-url="modals/calendar/calendar_event_add.php?client_id=<?= $client_id ?>&location_id=<?= $location_id ?>&lock_client=1&title=Onsite+visit">
                                <i class="far fa-fw fa-calendar-plus text-secondary me-2"></i>Visit here
                            </a>
                            <?php if ($can_edit) { ?>
                                <a class="dropdown-item ajax-modal" href="#" data-modal-size="lg" data-modal-url="modals/contact/contact_add.php?client_id=<?= $client_id ?>&location_id=<?= $location_id ?>">
                                    <i class="fa fa-fw fa-user text-secondary me-2"></i>Contact
                                </a>
                            <?php } ?>
                            <?php if ($can_support_edit) { ?>
                                <a class="dropdown-item ajax-modal" href="#" data-modal-size="lg" data-modal-url="modals/asset/asset_add.php?client_id=<?= $client_id ?>&location_id=<?= $location_id ?>">
                                    <i class="fa fa-fw fa-desktop text-secondary me-2"></i>Asset
                                </a>
                                <a class="dropdown-item ajax-modal" href="#" data-modal-url="modals/network/network_add.php?client_id=<?= $client_id ?>&location_id=<?= $location_id ?>">
                                    <i class="fa fa-fw fa-network-wired text-secondary me-2"></i>Network
                                </a>
                                <a class="dropdown-item ajax-modal" href="#" data-modal-size="lg" data-modal-url="modals/rack/rack_add.php?client_id=<?= $client_id ?>&location_id=<?= $location_id ?>">
                                    <i class="fa fa-fw fa-server text-secondary me-2"></i>Rack
                                </a>
                                <div class="dropdown-divider"></div>
                                <a class="dropdown-item ajax-modal" href="#" data-modal-size="xl" data-modal-url="modals/document/document_add.php?client_id=<?= $client_id ?>&location_id=<?= $location_id ?>">
                                    <i class="fa fa-fw fa-file-alt text-secondary me-2"></i>Document
                                </a>
                                <a class="dropdown-item ajax-modal" href="#" data-modal-url="modals/file/file_upload.php?client_id=<?= $client_id ?>&location_id=<?= $location_id ?>">
                                    <i class="fa fa-fw fa-upload text-secondary me-2"></i>Upload File(s)
                                </a>
                            <?php } ?>
                            <?php if (!empty($config_module_enable_accounting)) { ?>
                                <div class="dropdown-divider"></div>
                                <a class="dropdown-item ajax-modal" href="#" data-modal-url="modals/trip/trip_add.php?client_id=<?= $client_id ?>&location_id=<?= $location_id ?>">
                                    <i class="fa fa-fw fa-route text-secondary me-2"></i>Trip here
                                </a>
                            <?php } ?>
                        </div>
                    </div>
                <?php } ?>
                <?php if ($can_move && !$location_archived_at) { ?>
                    <div class="dropdown">
                        <button type="button" class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown"><i class="fas fa-dolly me-2"></i>Move here</button>
                        <div class="dropdown-menu dropdown-menu-end">
                            <?php foreach (LOCATION_KINDS as $kind => $k) {
                                if (lookupUserPermission($k['module']) < 2) {
                                    continue;
                                } ?>
                                <a class="dropdown-item ajax-modal" href="#" data-modal-url="<?= $move_url . $kind ?>"><i class="fa fa-fw <?= $k['icon'] ?> text-secondary me-2"></i><?= $k['plural'] ?></a>
                            <?php } ?>
                            <?php if ($can_support_edit) { ?>
                                <div class="dropdown-divider"></div>
                                <a class="dropdown-item ajax-modal" href="#" data-modal-url="<?= $link_url ?>document"><i class="fa fa-fw fa-link text-secondary me-2"></i>Link a document or file</a>
                            <?php } ?>
                        </div>
                    </div>
                <?php } ?>
                <?php if ($can_edit) { ?>
                    <button type="button" class="btn btn-outline-secondary ajax-modal" data-modal-size="lg" data-modal-url="modals/location/location_edit.php?id=<?= $location_id ?>">
                        <i class="fas fa-edit me-2"></i>Edit
                    </button>
                    <div class="dropdown dropstart">
                        <button class="btn btn-outline-secondary" title="More actions" type="button" data-bs-toggle="dropdown">
                            <i class="fas fa-fw fa-ellipsis-v"></i>
                        </button>
                        <div class="dropdown-menu">
                            <?php if ($location_map_url) { ?>
                                <a class="dropdown-item" href="<?= escapeHtml($location_map_url) ?>" target="_blank" rel="noopener"><i class="fas fa-fw fa-map text-secondary me-2"></i>Open in maps</a>
                            <?php } ?>
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
                        </div>
                    </div>
                <?php } ?>
            </div>
        </div>
    </div>

    <div class="row g-3">

        <!-- Getting there, who to ask for, getting in -->
        <div class="col-xl-4 col-lg-5">

            <div class="card mb-3">
                <div class="card-header">
                    <h3 class="card-title mb-0"><i class="fas fa-fw fa-map-marker-alt text-secondary me-2"></i>Getting there</h3>
                </div>
                <div class="card-body">
                    <dl class="itflow-asset-dl mb-0">
                        <dt>Address</dt>
                        <dd>
                            <?php if ($location_address_lines !== '') { ?>
                                <?php if ($location_map_url) { ?><a class="text-reset" href="<?= escapeHtml($location_map_url) ?>" target="_blank" rel="noopener" title="Open in maps"><?php } ?>
                                <?= nl2br($location_address_lines) ?>
                                <?php if ($location_map_url) { ?></a><?php } ?>
                                <button type="button" class="itflow-copy clipboardjs" title="Copy address" data-clipboard-text="<?= escapeHtml($location_address_text) ?>"><i class="far fa-copy"></i></button>
                                <?php if ($location_directions_url) { ?>
                                    <div class="mt-1"><a class="small" href="<?= escapeHtml($location_directions_url) ?>" target="_blank" rel="noopener"><i class="fas fa-directions me-1"></i>Directions</a></div>
                                <?php } ?>
                            <?php } else { ?>
                                <span class="text-body-secondary">Not recorded</span>
                            <?php } ?>
                        </dd>
                        <?php if ($location_phone) { ?>
                            <dt>Phone</dt><dd><?= $location_phone ?></dd>
                        <?php } ?>
                        <?php if ($location_fax) { ?>
                            <dt>Fax</dt><dd><?= $location_fax ?></dd>
                        <?php } ?>
                        <dt>Hours</dt>
                        <dd><?= $location_hours ?: '<span class="text-body-secondary">Not recorded</span>' ?></dd>
                        <dt>Added</dt><dd title="<?= $location_created_at ?>"><?= timeAgo($location_created_at) ?></dd>
                    </dl>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header">
                    <h3 class="card-title mb-0"><i class="fas fa-fw fa-user text-secondary me-2"></i>Ask for</h3>
                </div>
                <div class="card-body">
                    <?php if ($location['contact_id']) {
                        $onsite_email = escapeHtml($location['contact_email']);
                        $onsite_phone = contactPhoneLink($location['contact_phone'], $location['contact_phone_country_code'], $location['contact_extension']);
                        $onsite_mobile = contactPhoneLink($location['contact_mobile'], $location['contact_mobile_country_code']); ?>
                        <div class="d-flex align-items-center gap-2">
                            <?= contactAvatar($client_id, $location['contact_id'], $location['contact_name'], $location['contact_photo'], 'md') ?>
                            <div class="min-w-0">
                                <?= contactLink($client_id, $location['contact_id'], $location['contact_name'], '', ['class' => 'fw-semibold', 'archived' => !empty($location['contact_archived_at'])]) ?>
                                <?php if ($location['contact_title']) { ?><div class="small text-body-secondary"><?= escapeHtml($location['contact_title']) ?></div><?php } ?>
                            </div>
                        </div>
                        <div class="itflow-contact-reach small mt-2">
                            <?php if ($onsite_phone) { ?><div><i class="fas fa-fw fa-phone text-body-secondary me-1"></i><?= $onsite_phone ?></div><?php } ?>
                            <?php if ($onsite_mobile) { ?><div><i class="fas fa-fw fa-mobile-alt text-body-secondary me-1"></i><?= $onsite_mobile ?></div><?php } ?>
                            <?php if ($onsite_email) { ?><div><i class="fas fa-fw fa-envelope text-body-secondary me-1"></i><a href="mailto:<?= $onsite_email ?>"><?= $onsite_email ?></a><button type="button" class="itflow-copy clipboardjs" title="Copy email" data-clipboard-text="<?= $onsite_email ?>"><i class="far fa-copy"></i></button></div><?php } ?>
                        </div>
                    <?php } else { ?>
                        <div class="text-body-secondary small">Nobody set.
                            <?php if ($can_edit) { ?><a class="ajax-modal" href="#" data-modal-size="lg" data-modal-url="modals/location/location_edit.php?id=<?= $location_id ?>">Pick who to ask for on site</a>.<?php } ?>
                        </div>
                    <?php } ?>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header">
                    <h3 class="card-title mb-0"><i class="fas fa-fw fa-door-open text-secondary me-2"></i>Access notes</h3>
                </div>
                <textarea class="form-control border-0 rounded-0 rounded-bottom itflow-location-notes" rows="7" placeholder="Parking, which door, who has the keys, alarm and door codes, where the comms cupboard is - saves when you click away"
                    data-location-id="<?= $location_id ?>" data-csrf="<?= escapeHtml($_SESSION['csrf_token']) ?>" <?= $can_edit ? '' : 'readonly' ?>><?= $location_notes ?></textarea>
            </div>

        </div>

        <!-- What is on site -->
        <div class="col-xl-8 col-lg-7">
            <div class="card mb-3 itflow-asset-related">
                <div class="card-header py-2">
                    <ul class="nav nav-pills itflow-asset-pills" role="tablist">
                        <?php foreach ($location_tabs as $tab_key => $tab) { ?>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link text-nowrap <?= $tab['count'] ? '' : 'is-empty' ?>" id="location-tab-<?= $tab_key ?>" data-bs-toggle="pill" data-bs-target="#location-pane-<?= $tab_key ?>" data-location-tab="<?= $tab_key ?>" type="button" role="tab">
                                    <i class="fas fa-fw <?= $tab['icon'] ?> me-1 d-none d-xxl-inline"></i><?= $tab['label'] ?>
                                    <span class="badge rounded-pill ms-1"><?= $tab['count'] ?></span>
                                    <?php if (!empty($tab['badge'])) { ?>
                                        <span class="badge rounded-pill text-bg-danger" title="Open"><?= intval($tab['badge']) ?> open</span>
                                    <?php } ?>
                                </button>
                            </li>
                        <?php } ?>
                    </ul>
                </div>
                <div class="tab-content">

                    <!-- People based here -->
                    <div class="tab-pane fade" id="location-pane-people" role="tabpanel">
                        <div class="itflow-asset-pane-bar">
                            <span class="text-body-secondary small">People based at this site.</span>
                            <?php if ($can_edit && !$location_archived_at) { ?>
                                <div class="d-flex gap-2">
                                    <button type="button" class="btn btn-sm btn-outline-secondary ajax-modal" data-modal-url="<?= $move_url ?>contact"><i class="fas fa-dolly me-2"></i>Move here</button>
                                    <button type="button" class="btn btn-sm btn-primary ajax-modal" data-modal-size="lg" data-modal-url="modals/contact/contact_add.php?client_id=<?= $client_id ?>&location_id=<?= $location_id ?>"><i class="fas fa-plus me-2"></i>New Contact</button>
                                </div>
                            <?php } ?>
                        </div>
                        <?php if ($people) { ?>
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <tbody>
                                    <?php foreach ($people as $person) {
                                        $person_id = intval($person['contact_id']);
                                        $person_email = escapeHtml($person['contact_email']);
                                        $person_phone = contactPhoneLink($person['contact_phone'], $person['contact_phone_country_code'], $person['contact_extension']);
                                        $person_mobile = contactPhoneLink($person['contact_mobile'], $person['contact_mobile_country_code']); ?>
                                        <tr>
                                            <td>
                                                <?= contactLink($person['contact_client_id'], $person_id, $person['contact_name'], $person['contact_photo'], ['size' => 'sm', 'class' => 'fw-semibold']) ?>
                                                <?= contactFlagPills($person, false) ?>
                                                <?php if ($person['contact_title']) { ?><div class="small text-body-secondary itflow-license-holder-sub"><?= escapeHtml($person['contact_title']) ?></div><?php } ?>
                                            </td>
                                            <td class="small">
                                                <?php if ($person_phone) { ?><div class="text-nowrap"><i class="fas fa-fw fa-phone text-body-secondary me-1"></i><?= $person_phone ?></div><?php } ?>
                                                <?php if ($person_mobile) { ?><div class="text-nowrap"><i class="fas fa-fw fa-mobile-alt text-body-secondary me-1"></i><?= $person_mobile ?></div><?php } ?>
                                                <?php if ($person_email) { ?><div class="text-truncate itflow-location-email"><i class="fas fa-fw fa-envelope text-body-secondary me-1"></i><a href="mailto:<?= $person_email ?>"><?= $person_email ?></a><button type="button" class="itflow-copy clipboardjs" title="Copy email" data-clipboard-text="<?= $person_email ?>"><i class="far fa-copy"></i></button></div><?php } ?>
                                            </td>
                                            <td class="text-end">
                                                <?php if ($can_edit) { ?>
                                                    <a href="<?= $unset_url('contact', $person_id) ?>" class="btn btn-sm btn-light" title="Not based here"><i class="fas fa-fw fa-unlink"></i></a>
                                                <?php } ?>
                                            </td>
                                        </tr>
                                    <?php } ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php } else { ?>
                            <div class="itflow-asset-empty">Nobody is based here.</div>
                        <?php } ?>
                    </div>

                    <?php if ($can_support) { ?>
                    <!-- Assets on site -->
                    <div class="tab-pane fade" id="location-pane-assets" role="tabpanel">
                        <div class="itflow-asset-pane-bar">
                            <span class="text-body-secondary small">Everything on site, by kind.</span>
                            <?php if ($can_support_edit && !$location_archived_at) { ?>
                                <div class="d-flex gap-2">
                                    <button type="button" class="btn btn-sm btn-outline-secondary ajax-modal" data-modal-url="<?= $move_url ?>asset"><i class="fas fa-dolly me-2"></i>Move here</button>
                                    <button type="button" class="btn btn-sm btn-primary ajax-modal" data-modal-size="lg" data-modal-url="modals/asset/asset_add.php?client_id=<?= $client_id ?>&location_id=<?= $location_id ?>"><i class="fas fa-plus me-2"></i>New Asset</button>
                                </div>
                            <?php } ?>
                        </div>
                        <?php if ($assets) { ?>
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead class="text-nowrap table-light"><tr><th>Asset</th><th>IP</th><th>Assigned</th><th>Status</th><th></th></tr></thead>
                                    <tbody>
                                    <?php foreach ($assets as $asset) {
                                        $asset_id = intval($asset['asset_id']);
                                        $asset_ip = escapeHtml($asset['interface_ip']); ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center gap-2">
                                                    <span class="itflow-asset-icon itflow-asset-icon-sm itflow-asset-tone-<?= assetTypeTone($asset['asset_type']) ?>" aria-hidden="true"><i class="fas fa-fw fa-<?= getAssetIcon($asset['asset_type']) ?>"></i></span>
                                                    <div class="min-w-0">
                                                        <a class="fw-semibold ajax-modal" href="#" data-modal-size="lg" data-modal-url="modals/asset/asset.php?id=<?= $asset_id ?>"><?= escapeHtml($asset['asset_name']) ?></a>
                                                        <div class="small text-body-secondary"><?= escapeHtml(implode(' · ', array_filter([$asset['asset_type'], trim($asset['asset_make'] . ' ' . $asset['asset_model']), $asset['asset_physical_location']]))) ?></div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="text-nowrap"><?php if ($asset_ip) { ?><span class="font-monospace"><?= $asset_ip ?></span><?php if ($asset_ip !== 'DHCP') { ?><button type="button" class="itflow-copy clipboardjs" title="Copy IP" data-clipboard-text="<?= $asset_ip ?>"><i class="far fa-copy"></i></button><?php } ?><?php } else { ?><span class="text-body-secondary">-</span><?php } ?></td>
                                            <td><?= $asset['contact_name'] ? contactLink($client_id, $asset['contact_id'], $asset['contact_name'], $asset['contact_photo'], ['archived' => !empty($asset['contact_archived_at'])]) : '<span class="text-body-secondary">-</span>' ?></td>
                                            <td><?= assetStatusPill($asset['asset_status']) ?: '<span class="text-body-secondary">-</span>' ?></td>
                                            <td class="text-end">
                                                <?php if ($can_support_edit) { ?>
                                                    <a href="<?= $unset_url('asset', $asset_id) ?>" class="btn btn-sm btn-light" title="Not at this location"><i class="fas fa-fw fa-unlink"></i></a>
                                                <?php } ?>
                                            </td>
                                        </tr>
                                    <?php } ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php } else { ?>
                            <div class="itflow-asset-empty">No assets here.</div>
                        <?php } ?>
                    </div>

                    <!-- Networks -->
                    <div class="tab-pane fade" id="location-pane-networks" role="tabpanel">
                        <div class="itflow-asset-pane-bar">
                            <span class="text-body-secondary small">Subnets and VLANs at this site.</span>
                            <?php if ($can_support_edit && !$location_archived_at) { ?>
                                <div class="d-flex gap-2">
                                    <button type="button" class="btn btn-sm btn-outline-secondary ajax-modal" data-modal-url="<?= $move_url ?>network"><i class="fas fa-dolly me-2"></i>Move here</button>
                                    <button type="button" class="btn btn-sm btn-primary ajax-modal" data-modal-url="modals/network/network_add.php?client_id=<?= $client_id ?>&location_id=<?= $location_id ?>"><i class="fas fa-plus me-2"></i>New Network</button>
                                </div>
                            <?php } ?>
                        </div>
                        <?php if ($networks) { ?>
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead class="text-nowrap table-light"><tr><th>Network</th><th>Subnet</th><th class="d-none d-md-table-cell">Gateway</th><th>In use</th><th></th></tr></thead>
                                    <tbody>
                                    <?php foreach ($networks as $network_id => $network) {
                                        $network_subnet = escapeHtml($network['network']);
                                        $network_gateway = escapeHtml($network['network_gateway']); ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center gap-2">
                                                    <?= networkTile(networkType($network), 'sm') ?>
                                                    <div class="min-w-0">
                                                        <a class="fw-semibold" href="<?= escapeHtml(networkUrl($network_id, $client_id)) ?>"><?= escapeHtml($network['network_name']) ?></a>
                                                        <?php if (intval($network['network_vlan'])) { ?><span class="itflow-net-vlan ms-1">VLAN <?= intval($network['network_vlan']) ?></span><?php } ?>
                                                        <?php if ($network['network_description']) { ?><div class="small text-body-secondary"><?= escapeHtml($network['network_description']) ?></div><?php } ?>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="text-nowrap"><span class="font-monospace"><?= $network_subnet ?></span><?php if ($network_subnet) { ?><button type="button" class="itflow-copy clipboardjs" title="Copy subnet" data-clipboard-text="<?= $network_subnet ?>"><i class="far fa-copy"></i></button><?php } ?></td>
                                            <td class="text-nowrap d-none d-md-table-cell"><?php if ($network_gateway) { ?><span class="font-monospace"><?= $network_gateway ?></span><button type="button" class="itflow-copy clipboardjs" title="Copy gateway" data-clipboard-text="<?= $network_gateway ?>"><i class="far fa-copy"></i></button><?php } else { ?><span class="text-body-secondary">-</span><?php } ?></td>
                                            <td><?= networkUsageBar($network_usage[$network_id]) ?></td>
                                            <td class="text-end">
                                                <?php if ($can_support_edit) { ?>
                                                    <a href="<?= $unset_url('network', $network_id) ?>" class="btn btn-sm btn-light" title="Not at this location"><i class="fas fa-fw fa-unlink"></i></a>
                                                <?php } ?>
                                            </td>
                                        </tr>
                                    <?php } ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php } else { ?>
                            <div class="itflow-asset-empty">No networks here.</div>
                        <?php } ?>
                    </div>

                    <!-- Racks -->
                    <div class="tab-pane fade" id="location-pane-racks" role="tabpanel">
                        <div class="itflow-asset-pane-bar">
                            <span class="text-body-secondary small">Racks and cabinets at this site.</span>
                            <?php if ($can_support_edit && !$location_archived_at) { ?>
                                <div class="d-flex gap-2">
                                    <button type="button" class="btn btn-sm btn-outline-secondary ajax-modal" data-modal-url="<?= $move_url ?>rack"><i class="fas fa-dolly me-2"></i>Move here</button>
                                    <button type="button" class="btn btn-sm btn-primary ajax-modal" data-modal-size="lg" data-modal-url="modals/rack/rack_add.php?client_id=<?= $client_id ?>&location_id=<?= $location_id ?>"><i class="fas fa-plus me-2"></i>New Rack</button>
                                </div>
                            <?php } ?>
                        </div>
                        <?php if ($racks) { ?>
                            <ul class="list-unstyled mb-0 itflow-links itflow-license-links">
                                <?php foreach ($racks as $rack) {
                                    $rack_id = intval($rack['rack_id']); ?>
                                    <li class="itflow-link-row">
                                        <span class="itflow-link-icon" aria-hidden="true"><i class="fas fa-fw fa-server"></i></span>
                                        <div class="min-w-0 flex-grow-1">
                                            <div class="d-flex"><a class="text-truncate" href="rack.php?client_id=<?= $client_id ?>&rack_id=<?= $rack_id ?>"><?= escapeHtml($rack['rack_name']) ?></a></div>
                                            <div class="small text-body-secondary text-truncate"><?= escapeHtml(implode(' · ', array_filter([intval($rack['rack_units']) . 'U', $rack['rack_type'], $rack['rack_model'], $rack['rack_physical_location']]))) ?></div>
                                        </div>
                                        <?php if ($can_support_edit) { ?>
                                            <a class="itflow-link-remove" href="<?= $unset_url('rack', $rack_id) ?>" title="Not at this location"><i class="fas fa-unlink"></i></a>
                                        <?php } ?>
                                    </li>
                                <?php } ?>
                            </ul>
                        <?php } else { ?>
                            <div class="itflow-asset-empty">No racks here.</div>
                        <?php } ?>
                    </div>

                    <!-- Tickets for this site -->
                    <div class="tab-pane fade" id="location-pane-tickets" role="tabpanel">
                        <div class="itflow-asset-pane-bar">
                            <span class="text-body-secondary small">Tickets logged against this site, open first.</span>
                            <?php if ($can_support_edit) { ?>
                                <button type="button" class="btn btn-sm btn-primary ajax-modal" data-modal-size="lg" data-modal-url="modals/ticket/ticket_add.php?client_id=<?= $client_id ?>&location_id=<?= $location_id ?>"><i class="fas fa-plus me-2"></i>New Ticket</button>
                            <?php } ?>
                        </div>
                        <?php if ($tickets) { ?>
                            <?= ticketTableHtml($tickets) ?>
                            <?php if ($ticket_count > count($tickets)) { ?>
                                <div class="small text-body-secondary px-3 py-2 border-top">Showing every open ticket and the <?= count($tickets) - $ticket_open_count ?> most recent closed of <?= $ticket_count ?>.</div>
                            <?php } ?>
                        <?php } else { ?>
                            <div class="itflow-asset-empty">No tickets for this site.</div>
                        <?php } ?>
                    </div>

                    <!-- Documents and files -->
                    <?php foreach (['documents' => ['document', $documents, 'Floor plans, site runbooks, cabling and access guides.', 'No documents linked.'],
                                    'files' => ['file', $files, 'Photos of the cupboard and the patch panel, plans, leases.', 'No files linked.']] as $pane => [$to, $linked, $hint, $empty]) { ?>
                        <div class="tab-pane fade" id="location-pane-<?= $pane ?>" role="tabpanel">
                            <div class="itflow-asset-pane-bar">
                                <span class="text-body-secondary small"><?= $hint ?></span>
                                <?php if ($can_support_edit && !$location_archived_at) { ?>
                                    <div class="d-flex gap-2">
                                        <button type="button" class="btn btn-sm btn-outline-secondary ajax-modal" data-modal-url="<?= $link_url . $to ?>">
                                            <i class="fas fa-link me-2"></i>Link
                                        </button>
                                        <?php if ($to === 'document') { ?>
                                            <button type="button" class="btn btn-sm btn-primary ajax-modal" data-modal-size="xl" data-modal-url="modals/document/document_add.php?client_id=<?= $client_id ?>&location_id=<?= $location_id ?>">
                                                <i class="fas fa-plus me-2"></i>New Document
                                            </button>
                                        <?php } else { ?>
                                            <button type="button" class="btn btn-sm btn-primary ajax-modal" data-modal-url="modals/file/file_upload.php?client_id=<?= $client_id ?>&location_id=<?= $location_id ?>">
                                                <i class="fas fa-upload me-2"></i>Upload
                                            </button>
                                        <?php } ?>
                                    </div>
                                <?php } ?>
                            </div>
                            <?php if ($linked) { ?>
                                <ul class="list-unstyled mb-0 itflow-links itflow-license-links">
                                    <?php foreach ($linked as $linked_row) { ?>
                                        <li class="itflow-link-row">
                                            <?= linkRowHtml($to, $linked_row, $client_id) ?>
                                            <?php if ($can_support_edit) { ?>
                                                <a class="itflow-link-remove" href="post.php?unlink_item&from=location&id=<?= $location_id ?>&to=<?= $to ?>&other=<?= intval($linked_row['id']) ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>" title="Unlink <?= $to ?>"><i class="fas fa-unlink"></i></a>
                                            <?php } ?>
                                        </li>
                                    <?php } ?>
                                </ul>
                            <?php } else { ?>
                                <div class="itflow-asset-empty"><?= $empty ?></div>
                            <?php } ?>
                        </div>
                    <?php } ?>
                    <?php } ?>

                    <!-- History, from the audit log -->
                    <div class="tab-pane fade" id="location-pane-history" role="tabpanel">
                        <div class="itflow-asset-pane-bar">
                            <span class="text-body-secondary small">Edits, moves and notes - newest first.</span>
                        </div>
                        <?php if ($history) {
                            $history_icons = ['Create' => 'fa-plus', 'Edit' => 'fa-edit', 'Archive' => 'fa-archive', 'Restore' => 'fa-redo', 'Link' => 'fa-link', 'Unlink' => 'fa-unlink']; ?>
                            <ul class="list-group list-group-flush">
                                <?php foreach ($history as $entry) { ?>
                                    <li class="list-group-item d-flex gap-3 py-2">
                                        <span class="itflow-asset-icon itflow-asset-icon-sm itflow-asset-tone-other" title="<?= escapeHtml($entry['log_action']) ?>"><i class="fas fa-fw <?= $history_icons[$entry['log_action']] ?? 'fa-circle' ?>"></i></span>
                                        <div class="flex-grow-1 min-w-0">
                                            <div><?= escapeHtml($entry['log_description']) ?></div>
                                            <div class="small text-body-secondary"><?= escapeHtml($entry['user_name'] ?: 'System') ?> &middot; <span title="<?= escapeHtml($entry['log_created_at']) ?>"><?= timeAgo($entry['log_created_at']) ?></span></div>
                                        </div>
                                    </li>
                                <?php } ?>
                            </ul>
                        <?php } else { ?>
                            <div class="itflow-asset-empty">Nothing logged yet.</div>
                        <?php } ?>
                    </div>

                </div>
            </div>
        </div>

    </div>

    <script>
        // Access notes save when you click away
        document.querySelectorAll('.itflow-location-notes').forEach(function (notes) {
            var saved = notes.value;
            notes.addEventListener('blur', function () {
                if (notes.readOnly || notes.value === saved) {
                    return;
                }
                saved = notes.value;
                itflowPost('ajax.php', {
                    location_set_notes: 'TRUE',
                    csrf_token: notes.dataset.csrf,
                    location_id: notes.dataset.locationId,
                    notes: notes.value
                });
            });
        });

        /*
         * Which tab is open: the URL hash wins (#assets), then the tab last
         * used on any location, then People. Choosing a tab updates the hash
         * without a history entry, so the redirect back from a save lands on
         * the same tab. Bootstrap loads in the footer, so this waits for it.
         */
        document.addEventListener('DOMContentLoaded', function () {
            var key = 'itflow.locationTab';
            var tabs = document.querySelectorAll('[data-location-tab]');
            if (!tabs.length) {
                return;
            }
            function byName(name) {
                return document.querySelector('[data-location-tab="' + name + '"]');
            }
            var remembered = '';
            try { remembered = localStorage.getItem(key) || ''; } catch (e) {}
            var start = byName(location.hash.replace('#', '')) || byName(remembered) || tabs[0];
            bootstrap.Tab.getOrCreateInstance(start).show();

            tabs.forEach(function (tab) {
                tab.addEventListener('shown.bs.tab', function () {
                    var name = tab.getAttribute('data-location-tab');
                    try { localStorage.setItem(key, name); } catch (e) {}
                    history.replaceState(null, '', location.pathname + location.search + '#' + name);
                });
            });
        });
    </script>

<?php
}

require_once "../includes/footer.php";
