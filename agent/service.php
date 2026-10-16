<?php

/*
 * Service - one service a client relies on: email, backup, the line of
 * business app, the phones.
 *
 * What an agent wants when it breaks, in one place: how important it is,
 * anything it depends on that needs attention now (header), who to call
 * and how it comes back, the notes, and then everything it depends on in
 * tabs with counts - the assets it runs on (with their addresses, networks
 * and warranties), the logins, the domains and certificates, the documents,
 * the tickets raised about it or its assets, and its history from the audit
 * log. Report a problem starts a ticket that already says all that. The
 * open tab follows the URL hash (#logins) and is remembered between visits.
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
enforceUserPermission('module_support');

$service_id = intval($_GET['service_id'] ?? 0);
$service = serviceRow($service_id);

if (!$service || ($client_url && intval($service['service_client_id']) !== intval($client_id))) {

    echo "<center><h1 class='text-secondary mt-5'>Nothing to see here</h1><a class='btn btn-lg btn-secondary mt-3' href='javascript:history.back()'><i class='fa fa-fw fa-arrow-left'></i> Go Back</a></center>";

} else {

    $client_id = intval($service['service_client_id']);
    enforceClientAccess();
    $client_url = "client_id=$client_id&";

    $service_name = escapeHtml($service['service_name']);
    $service_archived = !empty($service['service_archived_at']);
    $is_favorite = intval($service['service_favorite']) === 1;

    $can_edit = serviceCan(2) && !$service_archived;
    $can_restore = serviceCan(2);
    $can_delete = serviceCan(3) && !empty($config_destructive_deletes_enable);
    $can_credentials = lookupUserPermission('module_credential') >= 1;
    $can_link_credentials = $can_edit && linkCanEdit('service', 'credential');

    // Override Tab Title // No Sanitizing needed as this var will only be used in the tab title
    $page_title = $service['service_name'];

    $counts = serviceCounts([$service_id])[$service_id];
    $issues = serviceIssues([$service_id])[$service_id];
    $review = serviceReview($service);
    $urls = serviceUrls($service_id, $can_credentials);

    // What it runs on - each with its address, network, site and warranty
    $assets = [];
    $sql = mysqli_query($mysqli, "SELECT assets.asset_id, asset_name, asset_type, asset_make, asset_model, asset_status, asset_warranty_expire, asset_archived_at,
            asset_contact_id, asset_location_id, interface_ip, interface_network_id, network_name, network_type, network_vlan, location_name, contact_name, contact_photo
        FROM service_assets
        JOIN assets ON assets.asset_id = service_assets.asset_id
        LEFT JOIN asset_interfaces ON interface_asset_id = assets.asset_id AND interface_primary = 1
        LEFT JOIN networks ON network_id = interface_network_id AND interface_network_id > 0
        LEFT JOIN locations ON location_id = asset_location_id
        LEFT JOIN contacts ON contact_id = asset_contact_id
        WHERE service_assets.service_id = $service_id
        ORDER BY asset_archived_at IS NOT NULL, asset_name ASC");
    while ($row = mysqli_fetch_assoc($sql)) {
        $assets[] = $row;
    }
    // The networks it is on, each once - linked to their pages
    $networks = [];
    foreach ($assets as $asset_row) {
        if (!$asset_row['asset_archived_at'] && $asset_row['network_name']) {
            $networks[intval($asset_row['interface_network_id'])] = networkLink($asset_row['interface_network_id'], $asset_row['network_name'], $client_id, ['type' => networkType($asset_row)]);
        }
    }
    $sites = array_unique(array_filter(array_column(array_filter($assets, function ($a) { return !$a['asset_archived_at']; }), 'location_name')));

    // Its logins, and the ones on the assets it runs on
    $credentials = $can_credentials ? credentialRows("credential_id IN (SELECT credential_id FROM service_credentials WHERE service_id = $service_id)") : [];
    $asset_credentials = $can_credentials ? credentialRows("credential_client_id = $client_id AND credential_asset_id IN (SELECT asset_id FROM service_assets WHERE service_id = $service_id)
        AND credential_id NOT IN (SELECT credential_id FROM service_credentials WHERE service_id = $service_id)") : [];

    $domains = serviceLinked($service_id, 'domain');
    $certificates = serviceLinked($service_id, 'certificate');
    $documents = serviceLinked($service_id, 'document');
    $contacts = serviceLinked($service_id, 'contact');
    $vendors = serviceLinked($service_id, 'vendor');
    $archived_credentials = $can_credentials ? array_values(array_filter(serviceLinked($service_id, 'credential'), function ($row) { return $row['archived_at']; })) : [];

    // Who to call - the vendors' and people's details for the call
    $vendor_details = [];
    if ($vendors) {
        $sql = mysqli_query($mysqli, "SELECT vendor_id, vendor_account_number, vendor_code, vendor_email, vendor_website, vendor_hours, vendor_sla FROM vendors
            WHERE vendor_id IN (" . implode(',', array_map('intval', array_column($vendors, 'id'))) . ")");
        while ($row = mysqli_fetch_assoc($sql)) {
            $vendor_details[intval($row['vendor_id'])] = $row;
        }
    }
    $contact_details = [];
    if ($contacts) {
        $sql = mysqli_query($mysqli, "SELECT contact_id, contact_email, contact_phone, contact_phone_country_code, contact_extension, contact_mobile, contact_mobile_country_code FROM contacts
            WHERE contact_id IN (" . implode(',', array_map('intval', array_column($contacts, 'id'))) . ")");
        while ($row = mysqli_fetch_assoc($sql)) {
            $contact_details[intval($row['contact_id'])] = $row;
        }
    }

    $tickets = ticketRows(serviceTicketsSql($service_id), 100);
    $linked_ticket_ids = [];
    $sql = mysqli_query($mysqli, "SELECT ticket_id FROM service_tickets WHERE service_id = $service_id");
    while ($row = mysqli_fetch_row($sql)) {
        $linked_ticket_ids[intval($row[0])] = true;
    }
    $open_tickets = count(array_filter($tickets, 'ticketIsOpen'));
    $history = serviceHistory($service_id, 100);

    $service_tabs = [
        'assets' => ['label' => 'Runs on', 'icon' => 'fa-desktop', 'count' => count($assets)],
        'logins' => ['label' => 'Logins', 'icon' => 'fa-key', 'count' => count($credentials) + count($asset_credentials) + count($archived_credentials)],
        'web' => ['label' => 'Domains &amp; SSL', 'icon' => 'fa-globe', 'count' => count($domains) + count($certificates)],
        'documents' => ['label' => 'Documents', 'icon' => 'fa-file-alt', 'count' => count($documents)],
        'tickets' => ['label' => 'Tickets', 'icon' => 'fa-life-ring', 'count' => count($tickets), 'alarm' => $open_tickets],
        'history' => ['label' => 'History', 'icon' => 'fa-history', 'count' => count($history)],
    ];
    if (!$can_credentials) {
        unset($service_tabs['logins']);
    }

    $link_url = "modals/link/link.php?from=service&id=$service_id&to=";
    $unlink_url = function ($to, $other_id) use ($service_id) {
        return "post.php?unlink_item&from=service&id=$service_id&to=$to&other=" . intval($other_id) . "&csrf_token=" . $_SESSION['csrf_token'];
    };
    $report_url = 'modals/ticket/ticket_add.php?' . http_build_query(['client_id' => $client_id, 'service_id' => $service_id]);
    $has_danger = in_array('danger', array_column($issues, 'tone'), true);

    /** An archived record still linked - struck through, with a way to unlink it. */
    $archived_row = function ($to, $row) use ($can_edit, $unlink_url) {
        $entity = LINK_ENTITIES[$to];
        return '<li class="itflow-link-row"><span class="itflow-link-icon" aria-hidden="true"><i class="fas fa-fw ' . $entity['icon'] . '"></i></span>'
            . '<div class="min-w-0 flex-grow-1"><div class="text-truncate text-decoration-line-through text-body-secondary">' . escapeHtml($row['name']) . '</div>'
            . '<div class="small text-warning-emphasis">Archived - unlink it, or link what replaced it</div></div>'
            . ($can_edit ? '<a class="btn btn-sm btn-light" href="' . escapeHtml($unlink_url($to, $row['id'])) . '" title="Unlink"><i class="fas fa-fw fa-unlink"></i></a>' : '')
            . '</li>';
    };

    ?>

    <ol class="breadcrumb d-print-none">
        <li class="breadcrumb-item">
            <a href="client_overview.php?client_id=<?= $client_id ?>"><?= escapeHtml($service['client_name']) ?></a>
        </li>
        <li class="breadcrumb-item">
            <a href="services.php?client_id=<?= $client_id ?>">Services</a>
        </li>
        <li class="breadcrumb-item active"><?= $service_name ?></li>
    </ol>

    <!-- What it is, how much it matters, when it is next checked -->
    <div class="card mb-3">
        <div class="card-body d-flex flex-wrap align-items-center gap-3">
            <?= serviceTile($service['service_category'], 'lg') ?>

            <div class="flex-grow-1 min-w-0">
                <h1 class="h4 fw-semibold mb-1 d-flex flex-wrap align-items-center gap-2">
                    <span><?= $service_name ?></span>
                    <?php if ($is_favorite) { ?><i class="fas fa-star text-warning fs-6" title="Starred"></i><?php } ?>
                    <?php if ($service_archived) { ?><span class="badge rounded-pill text-bg-secondary fw-normal fs-6">Archived</span><?php } ?>
                    <span class="fs-6"><?= serviceImportancePill($service['service_importance'], true) ?></span>
                    <span class="fs-6"><?= serviceReviewPill($review, true) ?></span>
                </h1>
                <div class="text-body-secondary small d-flex flex-wrap column-gap-3 row-gap-1">
                    <?php if ($service['service_category']) { ?><span><i class="fas fa-fw <?= serviceStyle($service['service_category'])['icon'] ?> me-1"></i><?= escapeHtml($service['service_category']) ?></span><?php } ?>
                    <?php if ($service['service_description']) { ?><span><?= escapeHtml($service['service_description']) ?></span><?php } ?>
                </div>
            </div>

            <div class="d-flex flex-wrap gap-2 d-print-none">
                <?php if ($can_edit) { ?>
                    <button type="button" class="btn btn-outline-danger ajax-modal" data-modal-size="lg" data-modal-url="<?= escapeHtml($report_url) ?>">
                        <i class="fas fa-life-ring me-2"></i>Report a problem
                    </button>
                    <div class="dropdown">
                        <button type="button" class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown"><i class="fas fa-link me-2"></i>Link</button>
                        <div class="dropdown-menu dropdown-menu-end">
                            <a class="dropdown-item ajax-modal" href="#" data-modal-url="<?= $link_url ?>asset"><i class="fa fa-fw fa-desktop text-secondary me-2"></i>Asset it runs on</a>
                            <?php if ($can_link_credentials) { ?>
                                <a class="dropdown-item ajax-modal" href="#" data-modal-url="<?= $link_url ?>credential"><i class="fa fa-fw fa-key text-secondary me-2"></i>Login</a>
                            <?php } ?>
                            <a class="dropdown-item ajax-modal" href="#" data-modal-url="<?= $link_url ?>domain"><i class="fa fa-fw fa-globe text-secondary me-2"></i>Domain</a>
                            <a class="dropdown-item ajax-modal" href="#" data-modal-url="<?= $link_url ?>certificate"><i class="fa fa-fw fa-lock text-secondary me-2"></i>Certificate</a>
                            <a class="dropdown-item ajax-modal" href="#" data-modal-url="<?= $link_url ?>contact"><i class="fa fa-fw fa-user text-secondary me-2"></i>Person</a>
                            <a class="dropdown-item ajax-modal" href="#" data-modal-url="<?= $link_url ?>vendor"><i class="fa fa-fw fa-building text-secondary me-2"></i>Vendor</a>
                            <a class="dropdown-item ajax-modal" href="#" data-modal-url="<?= $link_url ?>document"><i class="fa fa-fw fa-file-alt text-secondary me-2"></i>Document</a>
                            <a class="dropdown-item ajax-modal" href="#" data-modal-url="modals/service/service_link_ticket.php?id=<?= $service_id ?>"><i class="fa fa-fw fa-life-ring text-secondary me-2"></i>Ticket</a>
                        </div>
                    </div>
                    <button type="button" class="btn btn-outline-secondary ajax-modal" data-modal-url="modals/service/service_review.php?id=<?= $service_id ?>">
                        <i class="fas fa-clipboard-check me-2"></i>Mark reviewed
                    </button>
                    <button type="button" class="btn btn-outline-secondary ajax-modal" data-modal-size="lg" data-modal-url="modals/service/service_edit.php?id=<?= $service_id ?>">
                        <i class="fas fa-edit me-2"></i>Edit
                    </button>
                <?php } ?>
                <?php if ($can_restore) { ?>
                    <div class="dropdown dropstart">
                        <button class="btn btn-outline-secondary" title="More actions" type="button" data-bs-toggle="dropdown">
                            <i class="fas fa-fw fa-ellipsis-v"></i>
                        </button>
                        <div class="dropdown-menu">
                            <?php if ($service_archived) { ?>
                                <a class="dropdown-item" href="post.php?restore_service=<?= $service_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>">
                                    <i class="fas fa-fw fa-redo text-secondary me-2"></i>Restore
                                </a>
                                <?php if ($can_delete) { ?>
                                    <a class="dropdown-item text-danger text-bold confirm-link" href="post.php?delete_service=<?= $service_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>"
                                        data-confirm-text="<?= $service_name ?> goes for good. What it linked to - assets, logins, documents - stays.">
                                        <i class="fas fa-fw fa-trash me-2"></i>Delete
                                    </a>
                                <?php } ?>
                            <?php } else { ?>
                                <a class="dropdown-item ajax-modal" href="#" data-modal-size="xl" data-modal-url="modals/document/document_add.php?client_id=<?= $client_id ?>&service_id=<?= $service_id ?>">
                                    <i class="fas fa-fw fa-file-alt text-secondary me-2"></i>New runbook document
                                </a>
                                <a class="dropdown-item" href="post.php?<?= $is_favorite ? 'unfavorite_service' : 'favorite_service' ?>=<?= $service_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>">
                                    <i class="<?= $is_favorite ? 'far' : 'fas' ?> fa-fw fa-star text-secondary me-2"></i><?= $is_favorite ? 'Unstar' : 'Star' ?>
                                </a>
                                <div class="dropdown-divider"></div>
                                <a class="dropdown-item text-danger confirm-link" href="post.php?archive_service=<?= $service_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>"
                                    data-confirm-text="<?= $service_name ?> moves to Archived, off the lists and counts, until restored.">
                                    <i class="fas fa-fw fa-archive me-2"></i>Archive
                                </a>
                            <?php } ?>
                        </div>
                    </div>
                <?php } ?>
            </div>
        </div>
    </div>

    <?php if ($issues && !$service_archived) { ?>
        <!-- What it depends on that needs attention now -->
        <div class="alert alert-<?= $has_danger ? 'danger' : 'warning' ?> d-flex gap-3 mb-3 itflow-service-alert">
            <i class="fas fa-exclamation-triangle mt-1"></i>
            <div class="min-w-0">
                <div class="fw-semibold mb-1"><?= count($issues) ?> thing<?= count($issues) === 1 ? '' : 's' ?> it depends on need<?= count($issues) === 1 ? 's' : '' ?> attention</div>
                <?= serviceIssuesHtml($issues) ?>
            </div>
        </div>
    <?php } ?>

    <div class="row g-3">

        <!-- The facts, who to call, how it comes back, the notes -->
        <div class="col-xl-4 col-lg-5">

            <div class="card mb-3">
                <div class="card-header">
                    <h3 class="card-title mb-0"><i class="fas fa-fw fa-stream text-secondary me-2"></i>Service</h3>
                </div>
                <div class="card-body">
                    <dl class="itflow-asset-dl mb-0">
                        <dt>Importance</dt>
                        <dd><?= $can_edit ? quickPick('service_importance', $service_id, $service['service_importance'], serviceImportancePill($service['service_importance'])) : serviceImportancePill($service['service_importance']) ?></dd>
                        <dt>Review</dt>
                        <dd>
                            <?php if ($review['date']) { ?>
                                <?= escapeHtml(date('M j, Y', strtotime($review['date']))) ?>
                                <div class="small text-<?= $review['tone'] === 'success' ? 'body-secondary' : $review['tone'] . '-emphasis' ?>"><?= escapeHtml($review['state'] === 'scheduled' ? ucfirst(timeAgo($review['date'])) : $review['text']) ?></div>
                            <?php } else { ?>
                                <span class="text-body-secondary">Not scheduled</span>
                            <?php } ?>
                            <div class="small text-body-secondary">
                                <?= escapeHtml(SERVICE_REVIEW_INTERVALS[intval($service['service_review_interval'])] ?? '') ?><?= $service['service_reviewed_at'] ? ' · last ' . escapeHtml(date('M j, Y', strtotime($service['service_reviewed_at']))) : ' · never reviewed' ?>
                            </div>
                        </dd>
                        <?php if ($urls) { ?>
                            <dt>Opens at</dt>
                            <dd class="min-w-0">
                                <?php foreach ($urls as $url) { ?>
                                    <div class="text-truncate"><a href="<?= escapeHtml($url['url']) ?>" target="_blank" rel="noopener noreferrer" title="<?= escapeHtml($url['url']) ?>"><i class="fas fa-fw <?= $url['icon'] ?> text-secondary me-1"></i><?= escapeHtml($url['label']) ?></a></div>
                                <?php } ?>
                            </dd>
                        <?php } ?>
                        <?php if ($networks || $sites) { ?>
                            <dt>Where</dt>
                            <dd>
                                <?php if ($sites) { ?><div><i class="fas fa-fw fa-map-marker-alt text-secondary me-1"></i><?= escapeHtml(implode(', ', $sites)) ?></div><?php } ?>
                                <?php if ($networks) { ?><div class="d-flex flex-wrap column-gap-3"><?= implode('', $networks) ?></div><?php } ?>
                            </dd>
                        <?php } ?>
                        <dt>Added</dt><dd title="<?= escapeHtml($service['service_created_at']) ?>"><?= timeAgo($service['service_created_at']) ?></dd>
                        <?php if ($service['service_updated_at']) { ?>
                            <dt>Updated</dt><dd title="<?= escapeHtml($service['service_updated_at']) ?>"><?= timeAgo($service['service_updated_at']) ?></dd>
                        <?php } ?>
                    </dl>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header">
                    <h3 class="card-title mb-0"><i class="fas fa-fw fa-history text-secondary me-2"></i>Backup and recovery</h3>
                </div>
                <div class="card-body">
                    <?php if (trim((string) $service['service_backup']) !== '') { ?>
                        <div class="itflow-service-text"><?= escapeHtml($service['service_backup']) ?></div>
                    <?php } else { ?>
                        <div class="small text-body-secondary">
                            How it is backed up and how it comes back is not written down.
                            <?php if ($can_edit) { ?><a class="ajax-modal" href="#" data-modal-size="lg" data-modal-url="modals/service/service_edit.php?id=<?= $service_id ?>">Add it</a><?php } ?>
                        </div>
                    <?php } ?>
                </div>
            </div>

            <!-- Who to call - the vendors and the people at the client -->
            <div class="card mb-3">
                <div class="card-header d-flex align-items-center">
                    <h3 class="card-title mb-0 me-auto"><i class="fas fa-fw fa-phone text-secondary me-2"></i>Who to call</h3>
                    <?php if ($can_edit) { ?>
                        <div class="dropdown">
                            <button type="button" class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown"><i class="fas fa-link me-1"></i>Link</button>
                            <div class="dropdown-menu dropdown-menu-end">
                                <a class="dropdown-item ajax-modal" href="#" data-modal-url="<?= $link_url ?>vendor"><i class="fa fa-fw fa-building text-secondary me-2"></i>Vendor</a>
                                <a class="dropdown-item ajax-modal" href="#" data-modal-url="<?= $link_url ?>contact"><i class="fa fa-fw fa-user text-secondary me-2"></i>Person at the client</a>
                            </div>
                        </div>
                    <?php } ?>
                </div>
                <?php if ($vendors || $contacts) { ?>
                    <ul class="list-group list-group-flush itflow-service-callees">
                        <?php foreach ($vendors as $vendor) {
                            if ($vendor['archived_at']) {
                                echo '<li class="list-group-item p-0"><ul class="list-unstyled mb-0 itflow-links">' . $archived_row('vendor', $vendor) . '</ul></li>';
                                continue;
                            }
                            $vendor_id = intval($vendor['id']);
                            $details = $vendor_details[$vendor_id] ?? [];
                            $phone = contactPhoneLink($vendor['vendor_phone'], $vendor['vendor_phone_country_code'], $vendor['vendor_extension']);
                            $account = escapeHtml($details['vendor_account_number'] ?? '');
                            $pin = vendorSecretHtml($details['vendor_code'] ?? '');
                            $when = escapeHtml(implode(' · ', array_filter([$details['vendor_hours'] ?? '', $details['vendor_sla'] ?? '']))); ?>
                            <li class="list-group-item itflow-service-callee">
                                <div class="d-flex align-items-center gap-2 min-w-0">
                                    <?= vendorLink($vendor_id, $vendor['name'], ['page' => true, 'client_id' => $client_id, 'class' => 'fw-semibold']) ?>
                                    <span class="small text-body-secondary ms-1">Vendor</span>
                                    <?php if ($can_edit) { ?>
                                        <a class="itflow-link-remove ms-auto confirm-link" href="<?= escapeHtml($unlink_url('vendor', $vendor_id)) ?>" title="Unlink vendor" data-confirm-text="<?= escapeHtml($vendor['name']) ?> is no longer listed on this service."><i class="fas fa-unlink"></i></a>
                                    <?php } ?>
                                </div>
                                <?php if ($vendor['vendor_description']) { ?><div class="small text-body-secondary text-truncate"><?= escapeHtml($vendor['vendor_description']) ?></div><?php } ?>
                                <div class="small d-flex flex-wrap column-gap-3 row-gap-1 mt-1">
                                    <?php if ($phone) { ?><span><i class="fas fa-fw fa-phone text-secondary me-1"></i><?= $phone ?></span><?php } ?>
                                    <?php if (!empty($details['vendor_email'])) { ?><span class="text-truncate"><i class="fas fa-fw fa-envelope text-secondary me-1"></i><a href="mailto:<?= escapeHtml($details['vendor_email']) ?>"><?= escapeHtml($details['vendor_email']) ?></a></span><?php } ?>
                                    <?php if ($account) { ?><span title="Account number"><i class="fas fa-fw fa-fingerprint text-secondary me-1"></i><span class="font-monospace"><?= $account ?></span><button type="button" class="itflow-copy clipboardjs" title="Copy account number" data-clipboard-text="<?= $account ?>"><i class="far fa-copy"></i></button></span><?php } ?>
                                    <?php if ($pin) { ?><span><span class="text-body-secondary">PIN</span> <?= $pin ?></span><?php } ?>
                                    <?php if ($when) { ?><span class="text-body-secondary"><i class="fas fa-fw fa-clock me-1"></i><?= $when ?></span><?php } ?>
                                </div>
                            </li>
                        <?php } ?>
                        <?php foreach ($contacts as $contact) {
                            if ($contact['archived_at']) {
                                echo '<li class="list-group-item p-0"><ul class="list-unstyled mb-0 itflow-links">' . $archived_row('contact', $contact) . '</ul></li>';
                                continue;
                            }
                            $contact_id = intval($contact['id']);
                            $details = $contact_details[$contact_id] ?? [];
                            $phone = contactPhoneLink($details['contact_phone'] ?? '', $details['contact_phone_country_code'] ?? '', $details['contact_extension'] ?? '');
                            $mobile = contactPhoneLink($details['contact_mobile'] ?? '', $details['contact_mobile_country_code'] ?? ''); ?>
                            <li class="list-group-item itflow-service-callee">
                                <div class="d-flex align-items-center gap-2 min-w-0">
                                    <?= contactLink($client_id, $contact_id, $contact['name'], $contact['contact_photo'], ['class' => 'fw-semibold']) ?>
                                    <span class="small text-body-secondary ms-1 text-truncate"><?= escapeHtml($contact['contact_title'] ?: 'At the client') ?></span>
                                    <?php if ($can_edit) { ?>
                                        <a class="itflow-link-remove ms-auto confirm-link" href="<?= escapeHtml($unlink_url('contact', $contact_id)) ?>" title="Unlink person" data-confirm-text="<?= escapeHtml($contact['name']) ?> is no longer listed on this service."><i class="fas fa-unlink"></i></a>
                                    <?php } ?>
                                </div>
                                <div class="small d-flex flex-wrap column-gap-3 row-gap-1 mt-1">
                                    <?php if ($phone) { ?><span><i class="fas fa-fw fa-phone text-secondary me-1"></i><?= $phone ?></span><?php } ?>
                                    <?php if ($mobile) { ?><span><i class="fas fa-fw fa-mobile-alt text-secondary me-1"></i><?= $mobile ?></span><?php } ?>
                                    <?php if (!empty($details['contact_email'])) { ?><span class="text-truncate"><i class="fas fa-fw fa-envelope text-secondary me-1"></i><a href="mailto:<?= escapeHtml($details['contact_email']) ?>"><?= escapeHtml($details['contact_email']) ?></a></span><?php } ?>
                                </div>
                            </li>
                        <?php } ?>
                    </ul>
                <?php } else { ?>
                    <div class="card-body small text-body-secondary">No vendor or person linked - who gets the call when it breaks?</div>
                <?php } ?>
            </div>

            <div class="card mb-3">
                <div class="card-header">
                    <h3 class="card-title mb-0"><i class="fas fa-fw fa-pen text-secondary me-2"></i>Notes</h3>
                </div>
                <textarea class="form-control border-0 rounded-0 rounded-bottom itflow-service-notes" rows="7" placeholder="How it fits together, the quirks, what to check first when it breaks - saves when you click away"
                    data-service-id="<?= $service_id ?>" data-csrf="<?= escapeHtml($_SESSION['csrf_token']) ?>" <?= $can_edit ? '' : 'readonly' ?>><?= escapeHtml($service['service_notes']) ?></textarea>
            </div>

        </div>

        <!-- Everything it depends on -->
        <div class="col-xl-8 col-lg-7">
            <div class="card mb-3 itflow-asset-related">
                <div class="card-header py-2">
                    <ul class="nav nav-pills itflow-asset-pills" role="tablist">
                        <?php foreach ($service_tabs as $tab_key => $tab) { ?>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link text-nowrap <?= $tab['count'] ? '' : 'is-empty' ?>" id="service-tab-<?= $tab_key ?>" data-bs-toggle="pill" data-bs-target="#service-pane-<?= $tab_key ?>" data-service-tab="<?= $tab_key ?>" type="button" role="tab">
                                    <?= $tab['label'] ?>
                                    <span class="badge rounded-pill ms-1 <?= !empty($tab['alarm']) ? 'text-bg-primary' : '' ?>" <?= !empty($tab['alarm']) ? 'title="' . $tab['alarm'] . ' open"' : '' ?>><?= !empty($tab['alarm']) ? $tab['alarm'] . ' open' : $tab['count'] ?></span>
                                </button>
                            </li>
                        <?php } ?>
                    </ul>
                </div>
                <div class="tab-content">

                    <!-- What it runs on -->
                    <div class="tab-pane fade" id="service-pane-assets" role="tabpanel">
                        <div class="itflow-asset-pane-bar">
                            <span class="text-body-secondary small">The servers, firewalls, NAS and other kit it runs on - when one is down, so is the service.</span>
                            <?php if ($can_edit) { ?>
                                <button type="button" class="btn btn-sm btn-outline-secondary ajax-modal" data-modal-url="<?= $link_url ?>asset">
                                    <i class="fas fa-link me-2"></i>Link
                                </button>
                            <?php } ?>
                        </div>
                        <?php if ($assets) { ?>
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead class="text-nowrap table-light">
                                    <tr><th>Asset</th><th>Address</th><th>Site</th><th>Status</th><th></th></tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ($assets as $asset) {
                                        $asset_id = intval($asset['asset_id']);
                                        $asset_archived = !empty($asset['asset_archived_at']);
                                        $warranty = assetWarranty($asset['asset_warranty_expire']); ?>
                                        <tr class="<?= $asset_archived ? 'text-body-secondary' : '' ?>">
                                            <td>
                                                <div class="d-flex align-items-center gap-2">
                                                    <span class="itflow-asset-icon itflow-asset-icon-sm itflow-asset-tone-<?= assetTypeTone($asset['asset_type']) ?>" aria-hidden="true"><i class="fas fa-fw fa-<?= getAssetIcon($asset['asset_type']) ?>"></i></span>
                                                    <div class="min-w-0">
                                                        <a class="fw-semibold ajax-modal <?= $asset_archived ? 'text-decoration-line-through text-body-secondary' : '' ?>" href="#" data-modal-size="lg" data-modal-url="modals/asset/asset.php?id=<?= $asset_id ?>"><?= escapeHtml($asset['asset_name']) ?></a>
                                                        <div class="small text-body-secondary text-truncate"><?= escapeHtml(implode(' · ', array_filter([$asset['asset_type'], trim($asset['asset_make'] . ' ' . $asset['asset_model'])]))) ?></div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="small text-nowrap">
                                                <?php if ($asset['interface_ip']) { ?>
                                                    <span class="font-monospace"><?= escapeHtml($asset['interface_ip']) ?></span><button type="button" class="itflow-copy clipboardjs" title="Copy address" data-clipboard-text="<?= escapeHtml($asset['interface_ip']) ?>"><i class="far fa-copy"></i></button>
                                                <?php } ?>
                                                <?php if ($asset['network_name']) { ?><div class="text-body-secondary"><?= networkLink($asset['interface_network_id'], $asset['network_name'], $client_id, ['type' => networkType($asset), 'class' => 'text-reset']) ?><?= $asset['network_vlan'] ? ' · VLAN ' . escapeHtml($asset['network_vlan']) : '' ?></div><?php } ?>
                                                <?php if (!$asset['interface_ip'] && !$asset['network_name']) { ?><span class="text-body-secondary">-</span><?php } ?>
                                            </td>
                                            <td class="small"><?= $asset['location_name'] ? escapeHtml($asset['location_name']) : '<span class="text-body-secondary">-</span>' ?></td>
                                            <td class="small">
                                                <?php if ($asset_archived) { ?>
                                                    <span class="badge rounded-pill text-bg-secondary fw-normal">Archived</span>
                                                <?php } else { ?>
                                                    <?= assetStatusPill($asset['asset_status']) ?>
                                                    <?php if ($warranty && $warranty['tone'] !== 'success') { ?>
                                                        <div class="text-<?= $warranty['tone'] ?>-emphasis mt-1 text-nowrap" title="Warranty"><i class="fas fa-shield-alt me-1"></i><?= escapeHtml($warranty['short']) ?></div>
                                                    <?php } ?>
                                                <?php } ?>
                                            </td>
                                            <td class="text-end text-nowrap">
                                                <?php if ($can_edit) { ?>
                                                    <a href="<?= escapeHtml($unlink_url('asset', $asset_id)) ?>" class="btn btn-sm btn-light confirm-link" title="Unlink" data-confirm-text="<?= escapeHtml($asset['asset_name']) ?> is no longer listed as something it runs on."><i class="fas fa-fw fa-unlink"></i></a>
                                                <?php } ?>
                                            </td>
                                        </tr>
                                    <?php } ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php } else { ?>
                            <div class="itflow-asset-empty">
                                <div>Nothing linked as what it runs on.</div>
                                <?php if ($can_edit) { ?>
                                    <button type="button" class="btn btn-sm btn-outline-primary mt-3 ajax-modal" data-modal-url="<?= $link_url ?>asset"><i class="fas fa-link me-2"></i>Link assets</button>
                                <?php } ?>
                            </div>
                        <?php } ?>
                    </div>

                    <?php if ($can_credentials) { ?>
                    <!-- Logins: its own, then the ones on its assets -->
                    <div class="tab-pane fade" id="service-pane-logins" role="tabpanel">
                        <div class="itflow-asset-pane-bar">
                            <span class="text-body-secondary small">Admin consoles, portals and service accounts. Passwords are fetched only when you ask.</span>
                            <?php if ($can_link_credentials) { ?>
                                <button type="button" class="btn btn-sm btn-outline-secondary ajax-modal" data-modal-url="<?= $link_url ?>credential">
                                    <i class="fas fa-link me-2"></i>Link
                                </button>
                            <?php } ?>
                        </div>
                        <?php if ($credentials) { ?>
                            <?= credentialTableHtml($credentials, [
                                'for' => true,
                                'unlink' => function ($row) use ($unlink_url, $can_link_credentials) {
                                    return $can_link_credentials ? $unlink_url('credential', $row['credential_id']) : '';
                                },
                            ]) ?>
                        <?php } ?>
                        <?php if ($archived_credentials) { ?>
                            <ul class="list-unstyled mb-0 itflow-links border-top">
                                <?php foreach ($archived_credentials as $row) { echo $archived_row('credential', $row); } ?>
                            </ul>
                        <?php } ?>
                        <?php if ($asset_credentials) { ?>
                            <div class="itflow-license-section"><i class="fas fa-fw fa-desktop me-1"></i>On the assets it runs on <span class="badge rounded-pill ms-1"><?= count($asset_credentials) ?></span></div>
                            <?= credentialTableHtml($asset_credentials, ['for' => true]) ?>
                        <?php } ?>
                        <?php if (!$credentials && !$asset_credentials && !$archived_credentials) { ?>
                            <div class="itflow-asset-empty">No logins linked, and none on the assets it runs on.</div>
                        <?php } ?>
                    </div>
                    <?php } ?>

                    <!-- Domains and certificates -->
                    <div class="tab-pane fade" id="service-pane-web" role="tabpanel">
                        <div class="itflow-asset-pane-bar">
                            <span class="text-body-secondary small">The domains its mail or web run on and the certificates that keep it on https - expiring ones show at the top of the page.</span>
                            <?php if ($can_edit) { ?>
                                <div class="dropdown">
                                    <button type="button" class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown"><i class="fas fa-link me-2"></i>Link</button>
                                    <div class="dropdown-menu dropdown-menu-end">
                                        <a class="dropdown-item ajax-modal" href="#" data-modal-url="<?= $link_url ?>domain"><i class="fa fa-fw fa-globe text-secondary me-2"></i>Domain</a>
                                        <a class="dropdown-item ajax-modal" href="#" data-modal-url="<?= $link_url ?>certificate"><i class="fa fa-fw fa-lock text-secondary me-2"></i>Certificate</a>
                                    </div>
                                </div>
                            <?php } ?>
                        </div>
                        <?php foreach (['domain' => [$domains, 'Domains', 'fa-globe'], 'certificate' => [$certificates, 'Certificates', 'fa-lock']] as $to => [$linked, $label, $icon]) {
                            if (!$linked) {
                                continue;
                            } ?>
                            <div class="itflow-license-section"><i class="fas fa-fw <?= $icon ?> me-1"></i><?= $label ?> <span class="badge rounded-pill ms-1"><?= count($linked) ?></span></div>
                            <ul class="list-unstyled mb-0 itflow-links itflow-license-links">
                                <?php foreach ($linked as $linked_row) {
                                    if ($linked_row['archived_at']) {
                                        echo $archived_row($to, $linked_row);
                                        continue;
                                    } ?>
                                    <li class="itflow-link-row">
                                        <?= linkRowHtml($to, $linked_row, $client_id) ?>
                                        <?php if ($can_edit) { ?>
                                            <a class="itflow-link-remove" href="<?= escapeHtml($unlink_url($to, $linked_row['id'])) ?>" title="Unlink <?= $to ?>"><i class="fas fa-unlink"></i></a>
                                        <?php } ?>
                                    </li>
                                <?php } ?>
                            </ul>
                        <?php } ?>
                        <?php if (!$domains && !$certificates) { ?>
                            <div class="itflow-asset-empty">No domains or certificates linked.</div>
                        <?php } ?>
                    </div>

                    <!-- Documents -->
                    <div class="tab-pane fade" id="service-pane-documents" role="tabpanel">
                        <div class="itflow-asset-pane-bar">
                            <span class="text-body-secondary small">Runbooks, diagrams, the restore procedure, the vendor contract.</span>
                            <?php if ($can_edit) { ?>
                                <div class="d-flex gap-2">
                                    <button type="button" class="btn btn-sm btn-outline-secondary ajax-modal" data-modal-url="<?= $link_url ?>document">
                                        <i class="fas fa-link me-2"></i>Link
                                    </button>
                                    <button type="button" class="btn btn-sm btn-primary ajax-modal" data-modal-size="xl" data-modal-url="modals/document/document_add.php?client_id=<?= $client_id ?>&service_id=<?= $service_id ?>">
                                        <i class="fas fa-plus me-2"></i>New Document
                                    </button>
                                </div>
                            <?php } ?>
                        </div>
                        <?php if ($documents) { ?>
                            <ul class="list-unstyled mb-0 itflow-links itflow-license-links">
                                <?php foreach ($documents as $linked_row) {
                                    if ($linked_row['archived_at']) {
                                        echo $archived_row('document', $linked_row);
                                        continue;
                                    } ?>
                                    <li class="itflow-link-row">
                                        <?= linkRowHtml('document', $linked_row, $client_id) ?>
                                        <?php if ($can_edit) { ?>
                                            <a class="itflow-link-remove" href="<?= escapeHtml($unlink_url('document', $linked_row['id'])) ?>" title="Unlink document"><i class="fas fa-unlink"></i></a>
                                        <?php } ?>
                                    </li>
                                <?php } ?>
                            </ul>
                        <?php } else { ?>
                            <div class="itflow-asset-empty">No documents linked.</div>
                        <?php } ?>
                    </div>

                    <!-- Tickets about it, or about what it runs on -->
                    <div class="tab-pane fade" id="service-pane-tickets" role="tabpanel">
                        <div class="itflow-asset-pane-bar">
                            <span class="text-body-secondary small">Raised about it, or about what it runs on - open ones first.</span>
                            <?php if ($can_edit) { ?>
                                <div class="d-flex gap-2">
                                    <button type="button" class="btn btn-sm btn-outline-secondary ajax-modal" data-modal-url="modals/service/service_link_ticket.php?id=<?= $service_id ?>">
                                        <i class="fas fa-link me-2"></i>Link
                                    </button>
                                    <button type="button" class="btn btn-sm btn-primary ajax-modal" data-modal-size="lg" data-modal-url="<?= escapeHtml($report_url) ?>">
                                        <i class="fas fa-plus me-2"></i>Report a problem
                                    </button>
                                </div>
                            <?php } ?>
                        </div>
                        <?php if ($tickets) { ?>
                            <?= ticketTableHtml($tickets) ?>
                            <?php
                            $linked_tickets = array_values(array_filter($tickets, function ($row) use ($linked_ticket_ids) { return isset($linked_ticket_ids[intval($row['ticket_id'])]); }));
                            if ($linked_tickets && $can_edit) { ?>
                                <div class="small text-body-secondary px-3 py-2 border-top d-flex flex-wrap align-items-center gap-2">
                                    <span>Raised about the service:</span>
                                    <?php foreach ($linked_tickets as $row) { ?>
                                        <span class="itflow-contact-chip"><?= escapeHtml(ticketReference($row)) ?>
                                            <a class="text-body-secondary ms-1" href="post.php?unlink_service_ticket=<?= intval($row['ticket_id']) ?>&service_id=<?= $service_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>" title="Not about this service"><i class="fas fa-times"></i></a>
                                        </span>
                                    <?php } ?>
                                </div>
                            <?php } ?>
                        <?php } else { ?>
                            <div class="itflow-asset-empty">No tickets about it or what it runs on.</div>
                        <?php } ?>
                    </div>

                    <!-- History, from the audit log -->
                    <div class="tab-pane fade" id="service-pane-history" role="tabpanel">
                        <div class="itflow-asset-pane-bar">
                            <span class="text-body-secondary small">Reviews, links, edits - newest first.</span>
                        </div>
                        <?php if ($history) {
                            $history_icons = ['Create' => 'fa-plus', 'Edit' => 'fa-edit', 'Review' => 'fa-clipboard-check', 'Link' => 'fa-link', 'Unlink' => 'fa-unlink',
                                'Archive' => 'fa-archive', 'Restore' => 'fa-redo', 'Export' => 'fa-download']; ?>
                            <ul class="list-group list-group-flush">
                                <?php foreach ($history as $entry) { ?>
                                    <li class="list-group-item d-flex gap-3 py-2">
                                        <span class="itflow-asset-icon itflow-asset-icon-sm itflow-asset-tone-<?= $entry['log_action'] === 'Review' ? 'network' : 'other' ?>" title="<?= escapeHtml($entry['log_action']) ?>"><i class="fas fa-fw <?= $history_icons[$entry['log_action']] ?? 'fa-circle' ?>"></i></span>
                                        <div class="flex-grow-1 min-w-0">
                                            <div class="text-break"><?= escapeHtml($entry['log_description']) ?></div>
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
        // Notes save when you click away
        document.querySelectorAll('.itflow-service-notes').forEach(function (notes) {
            var saved = notes.value;
            notes.addEventListener('blur', function () {
                if (notes.readOnly || notes.value === saved) {
                    return;
                }
                saved = notes.value;
                itflowPost('post.php', {
                    service_set_notes: 'TRUE',
                    ajax: 1,
                    csrf_token: notes.dataset.csrf,
                    service_id: notes.dataset.serviceId,
                    notes: notes.value
                });
            });
        });

        /*
         * Which tab is open: the URL hash wins (#tickets), then the tab last
         * used on any service, then the first with something in it (history
         * aside - a new service has that, and wants linking first).
         * Choosing a tab updates the hash without a history entry, so the
         * redirect back from a save lands on the same tab. Bootstrap loads
         * in the footer, so this waits for it.
         */
        document.addEventListener('DOMContentLoaded', function () {
            var key = 'itflow.serviceTab';
            var tabs = document.querySelectorAll('[data-service-tab]');
            if (!tabs.length) {
                return;
            }
            function byName(name) {
                return document.querySelector('[data-service-tab="' + name + '"]');
            }
            // A remembered tab that is empty here gives way to the first one with something in it
            var remembered = '';
            try { remembered = localStorage.getItem(key) || ''; } catch (e) {}
            var kept = byName(remembered);
            var start = byName(location.hash.replace('#', ''))
                || (kept && !kept.classList.contains('is-empty') ? kept : null)
                || document.querySelector('[data-service-tab]:not(.is-empty):not([data-service-tab="history"])') || tabs[0];
            bootstrap.Tab.getOrCreateInstance(start).show();

            tabs.forEach(function (tab) {
                tab.addEventListener('shown.bs.tab', function () {
                    var name = tab.getAttribute('data-service-tab');
                    try { localStorage.setItem(key, name); } catch (e) {}
                    history.replaceState(null, '', location.pathname + location.search + '#' + name);
                });
            });
        });
    </script>

<?php
}

require_once "../includes/footer.php";
