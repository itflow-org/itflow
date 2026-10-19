<?php

/*
 * Asset - one asset.
 *
 * Built around what a tech needs from the box: what it is and its status
 * (header), how to get on it (IP, MAC, links - each one copies or opens in a
 * click), what it physically is and whether it is still under warranty, where
 * it is and who has it. Everything linked to it - interfaces, credentials,
 * tickets, licenses, documents, files, services, notes - and its history
 * sit in tabs with counts, so nothing is hidden just because it is empty and
 * the page does not run to ten screens. The open tab follows the URL hash
 * (#tickets) and is remembered between visits.
 */

// If client_id is in URI then show client Side Bar and client header
if (isset($_GET['client_id'])) {
    require_once "includes/inc_all_client.php";
    $client_query = "AND asset_client_id = $client_id";
    $client_url = "client_id=$client_id&";
} else {
    require_once "includes/inc_client_overview_all.php";
    $client_query = '';
    $client_url = '';
}

// Perms
enforceUserPermission('module_support');

$asset_id = intval($_GET['asset_id'] ?? 0);

$sql = mysqli_query($mysqli, "SELECT asset_archived_at, asset_client_id, asset_contact_id, asset_created_at, asset_description, asset_favorite, asset_id,
    asset_install_date, asset_location_id, asset_make, asset_model, asset_name, asset_notes,
    asset_os, asset_photo, asset_physical_location, asset_purchase_date,
    asset_purchase_reference, asset_serial, asset_status, asset_type, asset_uri, asset_uri_2,
    asset_uri_client, asset_vendor_id, asset_warranty_expire, client_id, client_name,
    contact_archived_at, contact_email, contact_extension, contact_mobile,
    contact_mobile_country_code, contact_name, contact_phone, contact_phone_country_code, contact_photo,
    interface_ip, interface_ipv6, interface_mac, interface_nat_ip, interface_network_id,
    location_archived_at, location_name, network_name, network_type, network_description, vendor_name,
    asset_product_id, asset_invoice_id, invoice_prefix, invoice_number, invoice_date, product_name FROM assets
    LEFT JOIN clients ON client_id = asset_client_id
    LEFT JOIN contacts ON asset_contact_id = contact_id
    LEFT JOIN locations ON asset_location_id = location_id
    LEFT JOIN vendors ON asset_vendor_id = vendor_id
    LEFT JOIN asset_interfaces ON interface_asset_id = asset_id AND interface_primary = 1
    LEFT JOIN networks ON network_id = interface_network_id
    LEFT JOIN invoices ON invoice_id = asset_invoice_id AND asset_invoice_id > 0
    LEFT JOIN products ON product_id = asset_product_id AND asset_product_id > 0
    WHERE asset_id = $asset_id
    " . clientScopeSql('asset_client_id') . "
    $client_query
    LIMIT 1
");

if (mysqli_num_rows($sql) == 0) {

    echo "<center><h1 class='text-secondary mt-5'>Nothing to see here</h1><a class='btn btn-lg btn-secondary mt-3' href='javascript:history.back()'><i class='fa fa-fw fa-arrow-left'></i> Go Back</a></center>";

} else {

    $row = mysqli_fetch_assoc($sql);
    $asset_row = $row; // raw, for the quick pickers - $row gets reused below
    $client_id = intval($row['client_id']);
    $client_name = escapeHtml($row['client_name']);
    $asset_id = intval($row['asset_id']);
    $asset_type = escapeHtml($row['asset_type']);
    $asset_name = escapeHtml($row['asset_name']);
    $asset_description = escapeHtml($row['asset_description']);
    $asset_make_model = escapeHtml(trim($row['asset_make'] . ' ' . $row['asset_model']));
    $asset_serial = escapeHtml($row['asset_serial']);
    $asset_os = escapeHtml($row['asset_os']);
    $asset_os_icon = assetOsIcon($row['asset_os']);
    $asset_links = assetLinks($row['asset_uri'], $row['asset_uri_2'], $row['asset_uri_client']);
    $asset_status_pill = assetStatusPill($row['asset_status']);
    $asset_purchase_reference = escapeHtml($row['asset_purchase_reference']);
    $asset_purchase_date = escapeHtml($row['asset_purchase_date']);
    $asset_purchase_age = assetAge($row['asset_purchase_date']);
    $asset_install_date = escapeHtml($row['asset_install_date']);
    $asset_install_age = assetAge($row['asset_install_date']);
    $asset_warranty = assetWarranty($row['asset_warranty_expire']);
    $asset_photo = escapeHtml($row['asset_photo']);
    $asset_physical_location = escapeHtml($row['asset_physical_location']);
    $asset_notes = escapeHtml($row['asset_notes']);
    $asset_favorite = intval($row['asset_favorite']);
    $asset_created_at = escapeHtml($row['asset_created_at']);
    $asset_archived_at = escapeHtml($row['asset_archived_at']);
    $asset_vendor_id = intval($row['asset_vendor_id']);
    $asset_contact_id = intval($row['asset_contact_id']);
    $asset_tone = assetTypeTone($row['asset_type']);
    // Sold to the client on one of our invoices, from a catalog product
    $can_sales = lookupUserPermission('module_sales') >= 1;
    $asset_invoice_id = intval($row['asset_invoice_id']);
    $asset_sold_number = $row['invoice_number'] !== null ? escapeHtml($row['invoice_prefix'] . $row['invoice_number']) : '';
    $asset_sold_date = escapeHtml((string) $row['invoice_date']);
    $asset_product_id = intval($row['asset_product_id']);
    $asset_product_name = escapeHtml((string) $row['product_name']);
    $device_icon = getAssetIcon($row['asset_type']);

    $asset_ip = escapeHtml($row['interface_ip']);
    $asset_ipv6 = escapeHtml($row['interface_ipv6']);
    $asset_nat_ip = escapeHtml($row['interface_nat_ip']);
    $asset_mac = escapeHtml($row['interface_mac']);
    $asset_network_id = intval($row['interface_network_id']);
    $asset_network_type = networkType($row);
    $network_name = escapeHtml($row['network_name']);
    $vendor_name = escapeHtml($row['vendor_name']);

    $contact_name = escapeHtml($row['contact_name']);
    $contact_archived = !empty($row['contact_archived_at']);
    $location_name = escapeHtml($row['location_name']);
    $location_archived = !empty($row['location_archived_at']);

    $can_edit = lookupUserPermission('module_support') >= 2;
    $can_delete = lookupUserPermission('module_support') >= 3;
    $can_credentials = lookupUserPermission('module_credential') >= 1;
    $can_credentials_edit = lookupUserPermission('module_credential') >= 2;

    // The rack it sits in, if any
    $asset_rack = rackPositionForAsset($asset_id);
    if ($asset_rack) {
        $asset_rack_id = intval($asset_rack['rack_id']);
        $asset_rack_name = escapeHtml($asset_rack['rack_name']);
        $asset_rack_position = rackPositionLabel($asset_rack['unit_start_number'], $asset_rack['unit_end_number']) . ', ' . ($asset_rack['unit_face'] === 'rear' ? 'rear' : 'front');
    }

    // Override Tab Title // No Sanitizing needed as this var will opnly be used in the tab title
    $page_title = $row['asset_name'];

    // Tags - functions/tags.php
    $asset_tags_display = tagPills('asset', $asset_id, tagsForRecord('asset', $asset_id), "assets.php?client_id=$client_id&");

    // Tickets - linked as the ticket's asset or as one of its other assets (functions/ticket.php)
    $related_tickets = ticketRows(ticketAssetSql($asset_id));
    $ticket_count = count($related_tickets);
    $ticket_open_count = count(array_filter(array_column($related_tickets, 'is_open')));

    // Recurring tickets
    $sql_related_recurring_tickets = mysqli_query($mysqli, "SELECT recurring_tickets.recurring_ticket_id, recurring_ticket_subject,
            recurring_ticket_priority, recurring_ticket_frequency, recurring_ticket_next_run, recurring_ticket_paused_at
        FROM recurring_tickets
        LEFT JOIN recurring_ticket_assets ON recurring_tickets.recurring_ticket_id = recurring_ticket_assets.recurring_ticket_id
        WHERE recurring_ticket_asset_id = $asset_id OR recurring_ticket_assets.asset_id = $asset_id
        GROUP BY recurring_tickets.recurring_ticket_id
        ORDER BY recurring_ticket_next_run ASC"
    );
    $recurring_ticket_count = mysqli_num_rows($sql_related_recurring_tickets);

    // Network interfaces, in port order, and what each one is plugged into (functions/asset_interface.php)
    $interfaces = [];
    $sql_related_interfaces = mysqli_query($mysqli, "SELECT asset_interfaces.*, network_name, network, network_type, network_description, network_archived_at
        FROM asset_interfaces
        LEFT JOIN networks ON network_id = interface_network_id AND interface_network_id > 0
        WHERE interface_asset_id = $asset_id AND interface_archived_at IS NULL");
    while ($row = mysqli_fetch_assoc($sql_related_interfaces)) {
        $interfaces[intval($row['interface_id'])] = $row;
    }
    $interface_links = interfaceLinks(array_keys($interfaces));
    interfaceSort($interfaces);
    $interface_count = count($interfaces);
    // A switch, firewall or access point with a few ports is drawn as its ports too
    $interface_port_map = in_array(html_entity_decode($asset_type, ENT_QUOTES, 'UTF-8'), INTERFACE_PORT_MAP_TYPES, true) && $interface_count >= 4;

    // Files
    $sql_related_files = mysqli_query($mysqli, "SELECT file_created_at, file_description, file_ext, files.file_id, file_name FROM asset_files
        LEFT JOIN files ON asset_files.file_id = files.file_id
        WHERE asset_files.asset_id = $asset_id
        AND file_archived_at IS NULL
        ORDER BY file_name ASC"
    );
    $files_count = mysqli_num_rows($sql_related_files);

    // Documents
    $sql_related_documents = mysqli_query($mysqli, "SELECT document_created_at, document_description, documents.document_id, document_name,
        document_updated_at, user_name FROM asset_documents, documents
        LEFT JOIN users ON document_created_by = user_id
        WHERE asset_documents.asset_id = $asset_id
        AND asset_documents.document_id = documents.document_id
        AND document_archived_at IS NULL
        ORDER BY document_name ASC"
    );
    $document_count = mysqli_num_rows($sql_related_documents);

    // Credentials
    $related_credentials = $can_credentials ? credentialRows("credential_asset_id = $asset_id AND credential_client_id = $client_id") : [];
    $credential_count = count($related_credentials);

    // Licenses it holds a seat on, with each one's seats in use and the key it was given
    $held_software = softwareHeldBy('asset', $asset_id);
    $software_count = count($held_software);

    // The services it is part of - most important first
    $linked_services = servicesFor('asset', $asset_id);
    $service_count = count($linked_services);

    // Notes - 1 to many relationship
    $sql_related_notes = mysqli_query($mysqli, "SELECT asset_note, asset_note_created_at, asset_note_id, asset_note_type, user_name FROM asset_notes
        LEFT JOIN users ON asset_note_created_by = user_id
        WHERE asset_note_asset_id = $asset_id
        AND asset_note_archived_at IS NULL
        ORDER BY asset_note_created_at DESC"
    );
    $note_count = mysqli_num_rows($sql_related_notes);

    // Note type icons, read from the categories list so the seeded icons
    // stay the single source of truth
    $note_type_icons = array();
    $sql_note_type_icons = mysqli_query($mysqli, "SELECT category_name, category_icon FROM categories WHERE category_type = 'asset_note_type'");
    while ($row = mysqli_fetch_assoc($sql_note_type_icons)) {
        $note_type_icons[escapeHtml($row['category_name'])] = escapeHtml($row['category_icon']);
    }

    // The tabs, in the order a tech reaches for them
    // Its history, from the audit log
    $history = auditLogHistory('Asset', $asset_id);

    $asset_tabs = [
        'interfaces' => ['label' => 'Interfaces', 'icon' => 'fa-ethernet', 'count' => $interface_count],
        'credentials' => ['label' => 'Credentials', 'icon' => 'fa-key', 'count' => $credential_count],
        'tickets' => ['label' => 'Tickets', 'icon' => 'fa-life-ring', 'count' => $ticket_count, 'badge' => $ticket_open_count],
        'recurring' => ['label' => 'Recurring', 'icon' => 'fa-redo-alt', 'count' => $recurring_ticket_count],
        'licenses' => ['label' => 'Licenses', 'icon' => 'fa-cube', 'count' => $software_count],
        'documents' => ['label' => 'Documents', 'icon' => 'fa-file-alt', 'count' => $document_count],
        'files' => ['label' => 'Files', 'icon' => 'fa-paperclip', 'count' => $files_count],
        'services' => ['label' => 'Services', 'icon' => 'fa-stream', 'count' => $service_count],
        'notes' => ['label' => 'Notes', 'icon' => 'fa-sticky-note', 'count' => $note_count],
        'history' => ['label' => 'History', 'icon' => 'fa-history', 'count' => $history['count']],
    ];
    if (!$can_credentials) {
        unset($asset_tabs['credentials']);
    }

    ?>

    <ol class="breadcrumb d-print-none">
        <li class="breadcrumb-item">
            <a href="client_overview.php?client_id=<?= $client_id ?>"><?= $client_name ?></a>
        </li>
        <li class="breadcrumb-item">
            <a href="assets.php?client_id=<?= $client_id ?>">Assets</a>
        </li>
        <li class="breadcrumb-item active"><?= $asset_name ?></li>
    </ol>

    <!-- What it is -->
    <div class="card mb-3">
        <div class="card-body d-flex flex-wrap align-items-center gap-3">
            <?php if ($asset_photo) { ?>
                <a href="<?= "../uploads/clients/$client_id/$asset_photo" ?>" target="_blank" rel="noopener" title="Open photo">
                    <img class="itflow-asset-icon itflow-asset-icon-lg" src="<?= "../uploads/clients/$client_id/$asset_photo" ?>" alt="Photo of <?= $asset_name ?>">
                </a>
            <?php } else { ?>
                <span class="itflow-asset-icon itflow-asset-icon-lg itflow-asset-tone-<?= $asset_tone ?>" aria-hidden="true"><i class="fas fa-fw fa-<?= $device_icon ?>"></i></span>
            <?php } ?>

            <div class="flex-grow-1 min-w-0">
                <h1 class="h4 fw-semibold mb-1 d-flex flex-wrap align-items-center gap-2">
                    <span><?= $asset_name ?></span>
                    <?php if ($asset_favorite) { ?><i class="fas fa-star text-warning fs-6" title="Favorite"></i><?php } ?>
                    <?php if ($asset_archived_at) { ?><span class="badge rounded-pill text-bg-secondary fw-normal fs-6">Archived</span><?php } ?>
                    <?php if ($can_edit) { ?>
                        <span class="fs-6"><?= quickPick('asset_status', $asset_id, (string) $asset_row['asset_status'], assetQuickHtml('asset_status', $asset_row), 'list', 0, $client_id) ?></span>
                    <?php } else { ?>
                        <span class="fs-6"><?= $asset_status_pill ?></span>
                    <?php } ?>
                </h1>
                <div class="text-body-secondary small d-flex flex-wrap column-gap-3 row-gap-1">
                    <span><i class="fas fa-fw fa-<?= $device_icon ?> me-1"></i><?= $asset_type ?></span>
                    <?php if ($asset_make_model) { ?>
                        <span><i class="fas fa-fw fa-cube me-1"></i><?= $asset_make_model ?></span>
                    <?php } ?>
                    <?php if ($asset_os) { ?>
                        <span><i class="<?= $asset_os_icon ?> fa-fw me-1"></i><?= $asset_os ?></span>
                    <?php } ?>
                    <?php if ($asset_description) { ?>
                        <span><?= $asset_description ?></span>
                    <?php } ?>
                </div>
                <?php if ($asset_tags_display) { ?>
                    <div class="mt-2"><?= $asset_tags_display ?></div>
                <?php } ?>
            </div>

            <div class="d-flex flex-wrap gap-2 d-print-none">
                <?php foreach ($asset_links as [$link_label, $link_url]) { ?>
                    <a class="btn btn-outline-primary" href="<?= escapeUrl($link_url) ?>" target="_blank" rel="noopener" title="<?= $link_label ?>: <?= escapeHtml($link_url) ?>">
                        <i class="<?= assetLinkIcon($link_url) ?> me-2"></i><?= escapeHtml(assetLinkLabel($link_url)) ?><?= $link_label === 'Client link' ? ' (client)' : '' ?>
                    </a>
                <?php } ?>
                <?php if ($can_edit) { ?>
                    <div class="dropdown">
                        <button type="button" class="btn btn-primary dropdown-toggle" data-bs-toggle="dropdown"><i class="fas fa-plus me-2"></i>New</button>
                        <div class="dropdown-menu dropdown-menu-end">
                            <a class="dropdown-item ajax-modal" href="#" data-modal-url="modals/ticket/ticket_add.php?<?= $client_url ?>&asset_id=<?= $asset_id ?>" data-modal-size="lg">
                                <i class="fa fa-fw fa-life-ring text-secondary me-2"></i>Ticket
                            </a>
                            <a class="dropdown-item ajax-modal" href="#" data-modal-url="modals/recurring_ticket/recurring_ticket_add.php?<?= $client_url ?>&asset_id=<?= $asset_id ?>" data-modal-size="lg">
                                <i class="fa fa-fw fa-redo-alt text-secondary me-2"></i>Recurring Ticket
                            </a>
                            <?php if ($can_credentials_edit) { ?>
                                <a class="dropdown-item ajax-modal" href="#" data-modal-size="lg" data-modal-url="modals/credential/credential_add.php?<?= $client_url ?>asset_id=<?= $asset_id ?>">
                                    <i class="fa fa-fw fa-key text-secondary me-2"></i>Credential
                                </a>
                            <?php } ?>
                            <a class="dropdown-item ajax-modal" href="#" data-modal-size="lg" data-modal-url="modals/asset/asset_interface_add.php?asset_id=<?= $asset_id ?>">
                                <i class="fa fa-fw fa-ethernet text-secondary me-2"></i>Interface
                            </a>
                            <a class="dropdown-item ajax-modal" href="#" data-modal-size="lg" data-modal-url="modals/software/software_add.php?client_id=<?= $client_id ?>&asset_id=<?= $asset_id ?>">
                                <i class="fa fa-fw fa-cube text-secondary me-2"></i>License
                            </a>
                            <a class="dropdown-item ajax-modal" href="#" data-modal-url="modals/document/document_add.php?<?= $client_url ?>&asset_id=<?= $asset_id ?>" data-modal-size="xl">
                                <i class="fa fa-fw fa-file-alt text-secondary me-2"></i>Document
                            </a>
                            <a class="dropdown-item ajax-modal" href="#" data-modal-url="modals/file/file_upload.php?<?= $client_url ?>&asset_id=<?= $asset_id ?>">
                                <i class="fa fa-fw fa-upload text-secondary me-2"></i>Upload File(s)
                            </a>
                            <a class="dropdown-item ajax-modal" href="#" data-modal-url="modals/asset/asset_note_add.php?id=<?= $asset_id ?>">
                                <i class="fas fa-fw fa-sticky-note text-secondary me-2"></i>Note
                            </a>
                        </div>
                    </div>
                    <div class="dropdown">
                        <button type="button" class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown"><i class="fas fa-link me-2"></i>Link</button>
                        <div class="dropdown-menu dropdown-menu-end">
                            <a class="dropdown-item ajax-modal" href="#" data-modal-url="modals/link/link.php?from=asset&id=<?= $asset_id ?>&to=software">
                                <i class="fa fa-fw fa-cube text-secondary me-2"></i>License
                            </a>
                            <?php if ($can_credentials_edit) { ?>
                                <a class="dropdown-item ajax-modal" href="#" data-modal-url="modals/asset/asset_link_credential.php?id=<?= $asset_id ?>">
                                    <i class="fa fa-fw fa-key text-secondary me-2"></i>Credential
                                </a>
                            <?php } ?>
                            <a class="dropdown-item ajax-modal" href="#" data-modal-url="modals/asset/asset_link_service.php?id=<?= $asset_id ?>">
                                <i class="fa fa-fw fa-stream text-secondary me-2"></i>Service
                            </a>
                            <a class="dropdown-item ajax-modal" href="#" data-modal-url="modals/asset/asset_link_document.php?id=<?= $asset_id ?>">
                                <i class="fa fa-fw fa-file-alt text-secondary me-2"></i>Document
                            </a>
                            <a class="dropdown-item ajax-modal" href="#" data-modal-url="modals/asset/asset_link_file.php?id=<?= $asset_id ?>">
                                <i class="fa fa-fw fa-paperclip text-secondary me-2"></i>File
                            </a>
                        </div>
                    </div>
                    <button type="button" class="btn btn-outline-secondary ajax-modal" data-modal-size="lg" data-modal-url="modals/asset/asset_edit.php?id=<?= $asset_id ?>">
                        <i class="fas fa-edit me-2"></i>Edit
                    </button>
                    <div class="dropdown dropstart">
                        <button class="btn btn-outline-secondary" title="More actions" type="button" data-bs-toggle="dropdown">
                            <i class="fas fa-fw fa-ellipsis-v"></i>
                        </button>
                        <div class="dropdown-menu">
                            <a class="dropdown-item ajax-modal" href="#" data-modal-size="lg" data-modal-url="modals/asset/asset_copy.php?id=<?= $asset_id ?>">
                                <i class="fas fa-fw fa-copy text-secondary me-2"></i>Copy
                            </a>
                            <div class="dropdown-divider"></div>
                            <?php if ($asset_archived_at) { ?>
                                <a class="dropdown-item" href="post.php?restore_asset=<?= $asset_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>">
                                    <i class="fas fa-fw fa-redo text-secondary me-2"></i>Restore
                                </a>
                                <?php if ($can_delete) { ?>
                                    <a class="dropdown-item text-danger text-bold confirm-link" href="post.php?delete_asset=<?= $asset_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>">
                                        <i class="fas fa-fw fa-trash me-2"></i>Delete
                                    </a>
                                <?php } ?>
                            <?php } else { ?>
                                <a class="dropdown-item text-danger confirm-link" href="post.php?archive_asset=<?= $asset_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>">
                                    <i class="fas fa-fw fa-archive me-2"></i>Archive
                                </a>
                            <?php } ?>
                        </div>
                    </div>
                <?php } ?>
            </div>
        </div>
    </div>

    <div class="row g-3">

        <!-- The facts: how to reach it, what it is, where it is -->
        <div class="col-xl-4 col-lg-5">

            <?php if ($asset_ip || $asset_ipv6 || $asset_nat_ip || $asset_mac || $network_name) { ?>
                <div class="card mb-3">
                    <div class="card-header">
                        <h3 class="card-title mb-0"><i class="fas fa-fw fa-network-wired text-secondary me-2"></i>Network</h3>
                    </div>
                    <div class="card-body">
                        <dl class="itflow-asset-dl mb-0">
                            <?php if ($asset_ip) { ?>
                                <dt>IP</dt>
                                <dd><span class="font-monospace"><?= $asset_ip ?></span><?php if ($asset_ip !== 'DHCP') { ?><button type="button" class="itflow-copy clipboardjs" title="Copy IP" data-clipboard-text="<?= $asset_ip ?>"><i class="far fa-copy"></i></button><?php } ?></dd>
                            <?php } ?>
                            <?php if ($asset_nat_ip) { ?>
                                <dt>NAT</dt>
                                <dd><span class="font-monospace"><?= $asset_nat_ip ?></span><button type="button" class="itflow-copy clipboardjs" title="Copy NAT IP" data-clipboard-text="<?= $asset_nat_ip ?>"><i class="far fa-copy"></i></button></dd>
                            <?php } ?>
                            <?php if ($asset_ipv6) { ?>
                                <dt>IPv6</dt>
                                <dd><span class="font-monospace"><?= $asset_ipv6 ?></span><button type="button" class="itflow-copy clipboardjs" title="Copy IPv6" data-clipboard-text="<?= $asset_ipv6 ?>"><i class="far fa-copy"></i></button></dd>
                            <?php } ?>
                            <?php if ($asset_mac) { ?>
                                <dt>MAC</dt>
                                <dd><span class="font-monospace"><?= $asset_mac ?></span><button type="button" class="itflow-copy clipboardjs" title="Copy MAC" data-clipboard-text="<?= $asset_mac ?>"><i class="far fa-copy"></i></button></dd>
                            <?php } ?>
                            <?php if ($network_name) { ?>
                                <dt>Network</dt>
                                <dd><?= networkLink($asset_network_id, $network_name, $client_id, ['type' => $asset_network_type]) ?></dd>
                            <?php } ?>
                        </dl>
                    </div>
                </div>
            <?php } ?>

            <div class="card mb-3">
                <div class="card-header">
                    <h3 class="card-title mb-0"><i class="fas fa-fw fa-microchip text-secondary me-2"></i>Hardware</h3>
                </div>
                <div class="card-body">
                    <dl class="itflow-asset-dl mb-0">
                        <?php if ($asset_make_model) { ?>
                            <dt>Model</dt><dd><?= $asset_make_model ?></dd>
                        <?php } ?>
                        <?php if ($asset_serial) { ?>
                            <dt>Serial</dt>
                            <dd><span class="font-monospace"><?= $asset_serial ?></span><button type="button" class="itflow-copy clipboardjs" title="Copy serial" data-clipboard-text="<?= $asset_serial ?>"><i class="far fa-copy"></i></button></dd>
                        <?php } ?>
                        <?php if ($asset_os) { ?>
                            <dt>OS</dt><dd><i class="<?= $asset_os_icon ?> fa-fw text-secondary me-1"></i><?= $asset_os ?></dd>
                        <?php } ?>
                        <dt>Warranty</dt>
                        <dd>
                            <?php if ($asset_warranty) { ?>
                                <span class="badge rounded-pill bg-<?= $asset_warranty['tone'] ?>-subtle text-<?= $asset_warranty['tone'] ?>-emphasis border border-<?= $asset_warranty['tone'] ?>-subtle fw-normal"><?= escapeHtml($asset_warranty['short']) ?></span>
                                <div class="small text-body-secondary"><?= escapeHtml($asset_warranty['text']) ?><?= $asset_warranty['tone'] !== 'success' ? ' (' . escapeHtml($asset_warranty['date']) . ')' : '' ?></div>
                            <?php } else { ?>
                                <span class="text-body-secondary">Not recorded</span>
                            <?php } ?>
                        </dd>
                        <?php if ($asset_purchase_date) { ?>
                            <dt>Purchased</dt>
                            <dd><?= $asset_purchase_date ?><?php if ($asset_purchase_age) { ?><div class="small text-body-secondary"><?= $asset_purchase_age ?> old</div><?php } ?></dd>
                        <?php } ?>
                        <?php if ($asset_install_date) { ?>
                            <dt>Installed</dt>
                            <dd><?= $asset_install_date ?><?php if ($asset_install_age) { ?><div class="small text-body-secondary"><?= $asset_install_age ?> in service</div><?php } ?></dd>
                        <?php } ?>
                        <?php if ($vendor_name) { ?>
                            <dt>Vendor</dt>
                            <dd><?= vendorLink($asset_vendor_id, $vendor_name) ?></dd>
                        <?php } ?>
                        <?php if ($asset_sold_number) { ?>
                            <dt>Sold on</dt>
                            <dd>
                                <?= $can_sales ? "<a href=\"invoice.php?invoice_id=$asset_invoice_id\">$asset_sold_number</a>" : $asset_sold_number ?>
                                <div class="small text-body-secondary">Our invoice, <?= $asset_sold_date ?></div>
                            </dd>
                        <?php } ?>
                        <?php if ($asset_product_name) { ?>
                            <dt>Product</dt><dd><?= $can_sales ? "<a href=\"product.php?product_id=$asset_product_id\">$asset_product_name</a>" : $asset_product_name ?></dd>
                        <?php } ?>
                        <?php if ($asset_purchase_reference && $asset_purchase_reference !== $asset_sold_number) { ?>
                            <dt>Order ref</dt><dd><?= $asset_purchase_reference ?></dd>
                        <?php } ?>
                        <dt>Added</dt><dd title="<?= $asset_created_at ?>"><?= timeAgo($asset_created_at) ?></dd>
                    </dl>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header">
                    <h3 class="card-title mb-0"><i class="fas fa-fw fa-map-marker-alt text-secondary me-2"></i>Where &amp; who</h3>
                </div>
                <div class="card-body">
                    <dl class="itflow-asset-dl mb-0">
                        <dt>Location</dt>
                        <dd>
                            <?php if ($can_edit) { ?>
                                <?= quickPick('asset_location', $asset_id, (string) intval($asset_row['asset_location_id']), assetQuickHtml('asset_location', $asset_row, 'detail'), 'detail', 0, $client_id) ?>
                            <?php } elseif ($location_name) { ?>
                                <span class="<?= $location_archived ? 'text-danger text-decoration-line-through' : '' ?>" <?= $location_archived ? 'title="Archived location"' : '' ?>><?= $location_name ?></span>
                            <?php } else { ?>
                                <span class="text-body-secondary">-</span>
                            <?php } ?>
                            <?php if ($asset_physical_location) { ?>
                                <div class="small text-body-secondary"><?= $asset_physical_location ?></div>
                            <?php } ?>
                        </dd>
                        <?php if ($asset_rack) { ?>
                            <dt>Rack</dt>
                            <dd><a href="rack.php?client_id=<?= $client_id ?>&rack_id=<?= $asset_rack_id ?>"><?= $asset_rack_name ?></a><div class="small text-body-secondary"><?= $asset_rack_position ?></div></dd>
                        <?php } ?>
                        <dt>Assigned</dt>
                        <dd>
                            <?php if ($can_edit) { ?>
                                <?= quickPick('asset_contact', $asset_id, (string) $asset_contact_id, assetQuickHtml('asset_contact', $asset_row, 'detail'), 'detail', 0, $client_id) ?>
                            <?php } elseif ($contact_name) { ?>
                                <?= contactLink($client_id, $asset_contact_id, $asset_row['contact_name'], $asset_row['contact_photo'], ['page' => true, 'archived' => $contact_archived]) ?>
                            <?php } else { ?>
                                <span class="text-body-secondary">Nobody</span>
                            <?php } ?>
                            <!-- How to reach them - redrawn when the contact changes -->
                            <div data-quick-related="asset-contact-<?= $asset_id ?>"><?= assetContactDetails($asset_row) ?></div>
                        </dd>
                    </dl>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header">
                    <h3 class="card-title mb-0"><i class="fas fa-fw fa-pen text-secondary me-2"></i>Quick notes</h3>
                </div>
                <textarea class="form-control border-0 rounded-0 rounded-bottom" rows="5" id="assetNotes" placeholder="Jot something down - saves when you click away" onblur="updateAssetNotes(<?= $asset_id ?>)" <?= $can_edit ? '' : 'readonly' ?>><?= $asset_notes ?></textarea>
            </div>

        </div>

        <!-- Everything linked to it -->
        <div class="col-xl-8 col-lg-7">
            <div class="card mb-3 itflow-asset-related">
                <div class="card-header py-2">
                    <ul class="nav nav-pills itflow-asset-pills" role="tablist">
                        <?php foreach ($asset_tabs as $tab_key => $tab) { ?>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link text-nowrap <?= $tab['count'] ? '' : 'is-empty' ?>" id="asset-tab-<?= $tab_key ?>" data-bs-toggle="pill" data-bs-target="#asset-pane-<?= $tab_key ?>" data-asset-tab="<?= $tab_key ?>" type="button" role="tab">
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

                    <!-- Interfaces - a table, and for a switch, firewall or access point its ports drawn as a row -->
                    <div class="tab-pane fade" id="asset-pane-interfaces" role="tabpanel">
                        <div class="itflow-asset-pane-bar">
                            <span class="text-body-secondary small">Ports, addresses and what each is plugged into. The primary is the address shown on the left.</span>
                            <div class="d-flex flex-wrap gap-2">
                                <?php if ($interface_port_map) { ?>
                                    <div class="btn-group btn-group-sm" role="group" aria-label="Show as">
                                        <input type="radio" class="btn-check" name="interfaceView" id="interfaceViewPorts" value="ports" autocomplete="off" checked>
                                        <label class="btn btn-outline-secondary" for="interfaceViewPorts" title="Ports"><i class="fas fa-fw fa-th"></i></label>
                                        <input type="radio" class="btn-check" name="interfaceView" id="interfaceViewTable" value="table" autocomplete="off">
                                        <label class="btn btn-outline-secondary" for="interfaceViewTable" title="Table"><i class="fas fa-fw fa-list"></i></label>
                                    </div>
                                <?php } ?>
                                <?php if ($can_edit) { ?>
                                    <div class="dropdown" id="bulkActionButton" hidden>
                                        <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                            <i class="fas fa-fw fa-layer-group me-2"></i>Bulk Action (<span id="selectedCount">0</span>)
                                        </button>
                                        <div class="dropdown-menu dropdown-menu-end">
                                            <a class="dropdown-item ajax-modal" href="#" data-modal-url="modals/asset/asset_interface_bulk_edit_network.php?client_id=<?= $client_id ?>" data-bulk="true">
                                                <i class="fas fa-fw fa-network-wired me-2"></i>Network
                                            </a>
                                            <a class="dropdown-item ajax-modal" href="#" data-modal-url="modals/asset/asset_interface_bulk_edit_vlans.php" data-bulk="true">
                                                <i class="fas fa-fw fa-layer-group me-2"></i>VLANs
                                            </a>
                                            <a class="dropdown-item ajax-modal" href="#" data-modal-url="modals/asset/asset_interface_bulk_edit_type.php?client_id=<?= $client_id ?>" data-bulk="true">
                                                <i class="fas fa-fw fa-plug me-2"></i>Type
                                            </a>
                                            <button class="dropdown-item" type="submit" form="bulkActions" name="bulk_edit_asset_interface_ip_dhcp">
                                                <i class="fas fa-fw fa-random me-2"></i>Set to DHCP
                                            </button>
                                            <button class="dropdown-item" type="submit" form="bulkActions" name="bulk_disconnect_asset_interfaces">
                                                <i class="fas fa-fw fa-unlink me-2"></i>Disconnect
                                            </button>
                                            <?php if ($can_delete) { ?>
                                                <div class="dropdown-divider"></div>
                                                <button class="dropdown-item text-danger text-bold confirm-link" type="submit" form="bulkActions" name="bulk_delete_asset_interfaces"
                                                    data-confirm-text="The interfaces go for good, and their connections with them.">
                                                    <i class="fas fa-fw fa-trash me-2"></i>Delete
                                                </button>
                                            <?php } ?>
                                        </div>
                                    </div>
                                    <div class="btn-group">
                                        <button type="button" class="btn btn-sm btn-primary ajax-modal" data-modal-size="lg" data-modal-url="modals/asset/asset_interface_add.php?asset_id=<?= $asset_id ?>">
                                            <i class="fas fa-plus me-2"></i>New Interface
                                        </button>
                                        <button type="button" class="btn btn-sm btn-primary dropdown-toggle dropdown-toggle-split" data-bs-toggle="dropdown"></button>
                                        <div class="dropdown-menu dropdown-menu-end">
                                            <a class="dropdown-item ajax-modal" href="#" data-modal-url="modals/asset/asset_interface_multiple_add.php?asset_id=<?= $asset_id ?>">
                                                <i class="fa fa-fw fa-th me-2"></i>Add a run of ports
                                            </a>
                                            <a class="dropdown-item ajax-modal" href="#" data-modal-url="modals/asset/asset_interface_import.php?asset_id=<?= $asset_id ?>">
                                                <i class="fa fa-fw fa-upload me-2"></i>Import
                                            </a>
                                            <?php if ($interface_count) { ?>
                                                <a class="dropdown-item ajax-modal" href="#" data-modal-url="modals/asset/asset_interface_export.php?asset_id=<?= $asset_id ?>">
                                                    <i class="fa fa-fw fa-download me-2"></i>Export
                                                </a>
                                            <?php } ?>
                                        </div>
                                    </div>
                                <?php } ?>
                            </div>
                        </div>

                        <?php if ($interface_port_map) { ?>
                            <!-- The ports as they sit on the front of the box: green is plugged in, blue has an address -->
                            <div data-interface-view="ports">
                                <div class="itflow-port-map">
                                    <?php foreach ($interfaces as $interface_id => $row) {
                                        $link = $interface_links[$interface_id] ?? null;
                                        $states = array_filter([$link ? 'is-connected' : '', trim((string) $row['interface_ip']) !== '' ? 'is-addressed' : '', intval($row['interface_primary']) ? 'is-primary' : '']);
                                        $tip = implode("\n", array_filter([
                                            $row['interface_name'] . ($row['interface_description'] ? ' - ' . $row['interface_description'] : ''),
                                            $link ? 'To ' . $link['asset_name'] . ' ' . $link['interface_name'] : 'Not connected',
                                            $row['network_name'] ? $row['network_name'] . ($row['interface_vlans'] ? ' · VLAN ' . $row['interface_vlans'] : '') : ($row['interface_vlans'] ? 'VLAN ' . $row['interface_vlans'] : ''),
                                            $row['interface_ip'],
                                        ])); ?>
                                        <a href="#" class="itflow-port <?= implode(' ', $states) ?> <?= $can_edit ? 'ajax-modal' : '' ?>" title="<?= escapeHtml($tip) ?>" data-modal-size="lg" data-modal-url="modals/asset/asset_interface_edit.php?id=<?= $interface_id ?>">
                                            <span class="itflow-port-lamp"></span>
                                            <span class="itflow-port-name"><?= escapeHtml(preg_match('/(\d+)$/', $row['interface_name'], $m) && strlen($row['interface_name']) > 5 ? $m[1] : $row['interface_name']) ?></span>
                                        </a>
                                    <?php } ?>
                                </div>
                                <div class="itflow-port-legend">
                                    <span><span class="itflow-port-lamp" style="background: var(--bs-success); border-color: var(--bs-success)"></span>Connected</span>
                                    <span><span class="itflow-port-lamp" style="background: var(--bs-info); border-color: var(--bs-info)"></span>Has an address</span>
                                    <span><span class="itflow-port-lamp"></span>Free</span>
                                    <span><?= count($interface_links) ?> of <?= $interface_count ?> connected</span>
                                </div>
                            </div>
                        <?php } ?>

                        <form id="bulkActions" action="post.php" method="post" <?= $interface_port_map ? 'data-interface-view="table" hidden' : '' ?>>
                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead class="text-nowrap table-light <?php if ($interface_count == 0) { echo "d-none"; } ?>">
                                        <tr>
                                            <?php if ($can_edit) { ?>
                                                <th class="checkbox-column border-end">
                                                    <div class="form-check">
                                                        <input class="form-check-input" id="selectAllCheckbox" type="checkbox" onclick="checkAll(this)" title="Select all">
                                                    </div>
                                                </th>
                                            <?php } ?>
                                            <th>Interface</th>
                                            <th>Network</th>
                                            <th>Address</th>
                                            <th class="d-none d-xl-table-cell">MAC</th>
                                            <th>Connected to</th>
                                            <th></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ($interfaces as $interface_id => $row) {
                                        $link = $interface_links[$interface_id] ?? null;
                                        $interface_primary = intval($row['interface_primary']);
                                        $ip = normalizeIpAddress((string) $row['interface_ip']);
                                        $outside = $ip !== false && $row['network'] && networkSubnetInfo($row['network']) && !isIpInSubnet($ip, $row['network']);
                                        $details = implode(' · ', array_filter([$row['interface_type'], $row['interface_description']])); ?>
                                        <tr>
                                            <?php if ($can_edit) { ?>
                                                <td class="checkbox-column bg-light border-end">
                                                    <div class="form-check">
                                                        <input class="form-check-input bulk-select" type="checkbox" name="interface_ids[]" value="<?= $interface_id ?>" aria-label="Select <?= escapeHtml($row['interface_name']) ?>">
                                                    </div>
                                                </td>
                                            <?php } ?>
                                            <td class="itflow-iface-cell">
                                                <?php if ($can_edit) { ?>
                                                    <a class="text-body fw-semibold ajax-modal" href="#" data-modal-size="lg" data-modal-url="modals/asset/asset_interface_edit.php?id=<?= $interface_id ?>"><?= escapeHtml($row['interface_name']) ?></a>
                                                <?php } else { ?>
                                                    <span class="fw-semibold"><?= escapeHtml($row['interface_name']) ?></span>
                                                <?php } ?>
                                                <?php if ($interface_primary) { ?><span class="badge rounded-pill text-bg-primary fw-normal ms-1">Primary</span><?php } ?>
                                                <?php if ($details !== '') { ?><div class="small text-body-secondary"><?= escapeHtml($details) ?></div><?php } ?>
                                            </td>
                                            <td>
                                                <?php if ($row['network_name']) { ?>
                                                    <?= networkLink($row['interface_network_id'], $row['network_name'], $client_id, ['type' => networkType($row), 'archived' => !empty($row['network_archived_at'])]) ?>
                                                <?php } elseif (!$row['interface_vlans']) { ?>
                                                    <span class="text-body-secondary">-</span>
                                                <?php } ?>
                                                <?php if ($row['interface_vlans']) { ?><div><span class="itflow-net-vlan" title="VLANs">VLAN <?= escapeHtml($row['interface_vlans']) ?></span></div><?php } ?>
                                            </td>
                                            <td class="text-nowrap">
                                                <?= interfaceIpHtml($row['interface_ip']) ?><?php if ($ip !== false) { ?><button type="button" class="itflow-copy clipboardjs" title="Copy IP" data-clipboard-text="<?= escapeHtml($ip) ?>"><i class="far fa-copy"></i></button><?php } ?>
                                                <?php if ($outside) { ?><div class="small text-warning-emphasis"><i class="fas fa-exclamation-triangle me-1"></i>Outside <?= escapeHtml($row['network']) ?></div><?php } ?>
                                                <?php if ($row['interface_nat_ip']) { ?><div class="small text-body-secondary font-monospace">NAT <?= escapeHtml($row['interface_nat_ip']) ?></div><?php } ?>
                                                <?php if ($row['interface_ipv6']) { ?><div class="small text-body-secondary font-monospace"><?= escapeHtml($row['interface_ipv6']) ?></div><?php } ?>
                                            </td>
                                            <td class="text-nowrap d-none d-xl-table-cell">
                                                <?php if ($row['interface_mac']) { ?>
                                                    <span class="font-monospace small"><?= escapeHtml($row['interface_mac']) ?></span><button type="button" class="itflow-copy clipboardjs" title="Copy MAC" data-clipboard-text="<?= escapeHtml($row['interface_mac']) ?>"><i class="far fa-copy"></i></button>
                                                <?php } else { ?>
                                                    <span class="text-body-secondary">-</span>
                                                <?php } ?>
                                            </td>
                                            <td class="small">
                                                <?php if ($link) { ?>
                                                    <?= interfaceAssetRef($link['asset_id'], $link['asset_name'], $link['asset_type'], !empty($link['asset_archived_at'])) ?>
                                                    <span class="text-body-secondary text-nowrap"><i class="fas fa-ethernet fa-fw opacity-75"></i><?= escapeHtml($link['interface_name']) ?></span>
                                                <?php } else { ?>
                                                    <span class="text-body-secondary">-</span>
                                                <?php } ?>
                                            </td>
                                            <td class="text-end">
                                                <?php if ($can_edit) { ?>
                                                    <div class="dropdown dropstart">
                                                        <button class="btn btn-sm btn-light" title="More actions" type="button" data-bs-toggle="dropdown">
                                                            <i class="fas fa-fw fa-ellipsis-v"></i>
                                                        </button>
                                                        <div class="dropdown-menu">
                                                            <a class="dropdown-item ajax-modal" href="#" data-modal-size="lg" data-modal-url="modals/asset/asset_interface_edit.php?id=<?= $interface_id ?>">
                                                                <i class="fas fa-fw fa-edit text-secondary me-2"></i>Edit
                                                            </a>
                                                            <?php if (!$interface_primary) { ?>
                                                                <a class="dropdown-item" href="post.php?primary_asset_interface=<?= $interface_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>">
                                                                    <i class="fas fa-fw fa-star text-secondary me-2"></i>Make primary
                                                                </a>
                                                            <?php } ?>
                                                            <?php if ($link) { ?>
                                                                <a class="dropdown-item confirm-link" href="post.php?disconnect_asset_interface=<?= $interface_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>"
                                                                    data-confirm-title="Disconnect <?= escapeHtml($row['interface_name']) ?>?" data-confirm-button="Disconnect"
                                                                    data-confirm-text="It is no longer shown as plugged into <?= escapeHtml($link['asset_name'] . ' ' . $link['interface_name']) ?>.">
                                                                    <i class="fas fa-fw fa-unlink text-secondary me-2"></i>Disconnect
                                                                </a>
                                                            <?php } ?>
                                                            <?php if ($row['interface_network_id'] && $ip !== false) { ?>
                                                                <a class="dropdown-item" href="<?= escapeHtml(networkUrl($row['interface_network_id'], $client_id)) ?>#addresses">
                                                                    <i class="fas fa-fw fa-list-ol text-secondary me-2"></i>On the address plan
                                                                </a>
                                                            <?php } ?>
                                                            <?php if ($can_delete && !$interface_primary) { ?>
                                                                <div class="dropdown-divider"></div>
                                                                <a class="dropdown-item text-danger text-bold confirm-link" href="post.php?delete_asset_interface=<?= $interface_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>"
                                                                    data-confirm-text="<?= escapeHtml($row['interface_name']) ?> goes for good<?= $link ? ', and its connection with it' : '' ?>.">
                                                                    <i class="fas fa-fw fa-trash me-2"></i>Delete
                                                                </a>
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
                        <?php if (!$interface_count) { ?>
                            <div class="itflow-asset-empty">No interfaces yet<?= $can_edit ? ' - add its ports, or one for its network card' : '' ?>.</div>
                        <?php } ?>
                    </div>

                    <?php if ($can_credentials) { ?>
                    <!-- Credentials -->
                    <div class="tab-pane fade" id="asset-pane-credentials" role="tabpanel">
                        <div class="itflow-asset-pane-bar">
                            <span class="text-body-secondary small">Logins for this asset. Passwords and codes are fetched only when you ask for them.</span>
                            <?php if ($can_edit && $can_credentials_edit) { ?>
                                <div class="d-flex gap-2">
                                    <button type="button" class="btn btn-sm btn-outline-secondary ajax-modal" data-modal-url="modals/asset/asset_link_credential.php?id=<?= $asset_id ?>">
                                        <i class="fas fa-link me-2"></i>Link
                                    </button>
                                    <button type="button" class="btn btn-sm btn-primary ajax-modal" data-modal-size="lg" data-modal-url="modals/credential/credential_add.php?<?= $client_url ?>asset_id=<?= $asset_id ?>">
                                        <i class="fas fa-plus me-2"></i>New Credential
                                    </button>
                                </div>
                            <?php } ?>
                        </div>
                        <?php if ($related_credentials) { ?>
                            <?= credentialTableHtml($related_credentials, [
                                'for' => false,
                                'unlink' => function ($row) use ($asset_id, $can_edit) {
                                    return $can_edit && credentialCan(2) ? "post.php?unlink_credential_from_asset&asset_id=$asset_id&credential_id=" . intval($row['credential_id']) . "&csrf_token=" . $_SESSION['csrf_token'] : '';
                                },
                            ]) ?>
                        <?php } else { ?>
                            <div class="itflow-asset-empty">No credentials for this asset.</div>
                        <?php } ?>
                    </div>
                    <?php } ?>

                    <!-- Tickets -->
                    <div class="tab-pane fade" id="asset-pane-tickets" role="tabpanel">
                        <div class="itflow-asset-pane-bar">
                            <span class="text-body-secondary small">Open tickets first. Includes tickets where this is one of several assets.</span>
                            <?php if ($can_edit) { ?>
                                <button type="button" class="btn btn-sm btn-primary ajax-modal" data-modal-size="lg" data-modal-url="modals/ticket/ticket_add.php?<?= $client_url ?>&asset_id=<?= $asset_id ?>">
                                    <i class="fas fa-plus me-2"></i>New Ticket
                                </button>
                            <?php } ?>
                        </div>
                        <?php if ($related_tickets) { ?>
                            <?= ticketTableHtml($related_tickets) ?>
                        <?php } else { ?>
                            <div class="itflow-asset-empty">No tickets have mentioned this asset.</div>
                        <?php } ?>
                    </div>

                    <!-- Recurring tickets -->
                    <div class="tab-pane fade" id="asset-pane-recurring" role="tabpanel">
                        <div class="itflow-asset-pane-bar">
                            <span class="text-body-secondary small">Scheduled work - patching, checks, maintenance visits.</span>
                            <?php if ($can_edit) { ?>
                                <button type="button" class="btn btn-sm btn-primary ajax-modal" data-modal-size="lg" data-modal-url="modals/recurring_ticket/recurring_ticket_add.php?<?= $client_url ?>&asset_id=<?= $asset_id ?>">
                                    <i class="fas fa-plus me-2"></i>New Recurring Ticket
                                </button>
                            <?php } ?>
                        </div>
                        <?php if ($recurring_ticket_count) { ?>
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead class="text-nowrap table-light">
                                    <tr>
                                        <th>Subject</th>
                                        <th>Priority</th>
                                        <th>Frequency</th>
                                        <th>Next Run</th>
                                        <th></th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    <?php while ($row = mysqli_fetch_assoc($sql_related_recurring_tickets)) {
                                        $recurring_ticket_id = intval($row['recurring_ticket_id']);
                                        $recurring_ticket_subject = escapeHtml($row['recurring_ticket_subject']);
                                        $recurring_ticket_priority = escapeHtml($row['recurring_ticket_priority']);
                                        $recurring_ticket_frequency = escapeHtml($row['recurring_ticket_frequency']);
                                        $recurring_ticket_next_run = escapeHtml($row['recurring_ticket_next_run']);
                                        ?>
                                        <tr>
                                            <td>
                                                <?php if ($can_edit) { ?>
                                                    <a class="text-body fw-semibold ajax-modal" href="#" data-modal-size="lg" data-modal-url="modals/recurring_ticket/recurring_ticket_edit.php?id=<?= $recurring_ticket_id ?>"><?= $recurring_ticket_subject ?></a>
                                                <?php } else { ?>
                                                    <span class="fw-semibold"><?= $recurring_ticket_subject ?></span>
                                                <?php } ?>
                                            </td>
                                            <td><?= ticketPriorityHtml($row['recurring_ticket_priority'], 'detail') ?></td>
                                            <td><?= $recurring_ticket_frequency ?></td>
                                            <td class="text-nowrap"><?= recurringTicketNextRunPill($row) ?> <span class="small text-body-secondary ms-1"><?= $recurring_ticket_next_run ?></span></td>
                                            <td class="text-end">
                                                <?php if ($can_edit) { ?>
                                                    <div class="dropdown dropstart">
                                                        <button class="btn btn-sm btn-light" title="More actions" type="button" data-bs-toggle="dropdown">
                                                            <i class="fas fa-fw fa-ellipsis-v"></i>
                                                        </button>
                                                        <div class="dropdown-menu">
                                                            <a class="dropdown-item ajax-modal" href="#" data-modal-size="lg" data-modal-url="modals/recurring_ticket/recurring_ticket_edit.php?id=<?= $recurring_ticket_id ?>">
                                                                <i class="fas fa-fw fa-edit text-secondary me-2"></i>Edit
                                                            </a>
                                                            <a class="dropdown-item confirm-link" href="post.php?force_recurring_ticket=<?= $recurring_ticket_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>"
                                                                data-confirm-text="A ticket is raised now, and the next run moves on a step - this takes its place.">
                                                                <i class="fa fa-fw fa-paper-plane text-secondary me-2"></i>Raise now
                                                            </a>
                                                            <?php if ($can_delete) { ?>
                                                                <div class="dropdown-divider"></div>
                                                                <a class="dropdown-item text-danger text-bold confirm-link" href="post.php?delete_recurring_ticket=<?= $recurring_ticket_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>">
                                                                    <i class="fas fa-fw fa-trash me-2"></i>Delete
                                                                </a>
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
                        <?php } else { ?>
                            <div class="itflow-asset-empty">Nothing scheduled for this asset.</div>
                        <?php } ?>
                    </div>

                    <!-- Licenses -->
                    <div class="tab-pane fade" id="asset-pane-licenses" role="tabpanel">
                        <div class="itflow-asset-pane-bar">
                            <span class="text-body-secondary small">Licenses this asset holds a seat on, and the key to install.</span>
                            <?php if ($can_edit) { ?>
                                <div class="d-flex gap-2">
                                    <button type="button" class="btn btn-sm btn-outline-secondary ajax-modal" data-modal-url="modals/link/link.php?from=asset&id=<?= $asset_id ?>&to=software">
                                        <i class="fas fa-link me-2"></i>Link
                                    </button>
                                    <button type="button" class="btn btn-sm btn-primary ajax-modal" data-modal-size="lg" data-modal-url="modals/software/software_add.php?client_id=<?= $client_id ?>&asset_id=<?= $asset_id ?>">
                                        <i class="fas fa-plus me-2"></i>New License
                                    </button>
                                </div>
                            <?php } ?>
                        </div>
                        <?php if ($software_count) { ?>
                            <?= softwareHeldTable($held_software, 'asset', $asset_id, $can_edit) ?>
                        <?php } else { ?>
                            <div class="itflow-asset-empty">No licenses linked.</div>
                        <?php } ?>
                    </div>

                    <!-- Documents -->
                    <div class="tab-pane fade" id="asset-pane-documents" role="tabpanel">
                        <div class="itflow-asset-pane-bar">
                            <span class="text-body-secondary small">Runbooks, configs and how-tos for this asset.</span>
                            <?php if ($can_edit) { ?>
                                <div class="d-flex gap-2">
                                    <button type="button" class="btn btn-sm btn-outline-secondary ajax-modal" data-modal-url="modals/asset/asset_link_document.php?id=<?= $asset_id ?>">
                                        <i class="fas fa-link me-2"></i>Link
                                    </button>
                                    <button type="button" class="btn btn-sm btn-primary ajax-modal" data-modal-size="xl" data-modal-url="modals/document/document_add.php?<?= $client_url ?>&asset_id=<?= $asset_id ?>">
                                        <i class="fas fa-plus me-2"></i>New Document
                                    </button>
                                </div>
                            <?php } ?>
                        </div>
                        <?php if ($document_count) { ?>
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead class="text-nowrap table-light">
                                    <tr>
                                        <th>Document</th>
                                        <th>By</th>
                                        <th>Updated</th>
                                        <th></th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    <?php while ($row = mysqli_fetch_assoc($sql_related_documents)) {
                                        $document_id = intval($row['document_id']);
                                        $document_name = escapeHtml($row['document_name']);
                                        $document_description = escapeHtml($row['document_description']);
                                        $document_created_by = escapeHtml($row['user_name']);
                                        $document_updated_at = escapeHtml($row['document_updated_at'] ?: $row['document_created_at']);
                                        ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center gap-2">
                                                    <?= documentTile('sm') ?>
                                                    <div class="min-w-0">
                                                        <a class="fw-semibold" href="document.php?client_id=<?= $client_id ?>&document_id=<?= $document_id ?>"><?= $document_name ?></a>
                                                        <?php if ($document_description) { ?><div class="small text-body-secondary"><?= $document_description ?></div><?php } ?>
                                                    </div>
                                                </div>
                                            </td>
                                            <td><?= $document_created_by ?></td>
                                            <td class="text-nowrap small" title="<?= $document_updated_at ?>"><?= timeAgo($document_updated_at) ?></td>
                                            <td class="text-end text-nowrap">
                                                <a href="#" class="btn btn-sm btn-light ajax-modal" title="View document" data-modal-size="lg" data-modal-url="modals/document/document_view.php?id=<?= $document_id ?>"><i class="fas fa-fw fa-eye"></i></a>
                                                <?php if ($can_edit) { ?>
                                                    <a href="post.php?unlink_asset_from_document&asset_id=<?= $asset_id ?>&document_id=<?= $document_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>" class="btn btn-sm btn-light" title="Unlink"><i class="fas fa-fw fa-unlink"></i></a>
                                                <?php } ?>
                                            </td>
                                        </tr>
                                    <?php } ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php } else { ?>
                            <div class="itflow-asset-empty">No documents linked.</div>
                        <?php } ?>
                    </div>

                    <!-- Files -->
                    <div class="tab-pane fade" id="asset-pane-files" role="tabpanel">
                        <div class="itflow-asset-pane-bar">
                            <span class="text-body-secondary small">Configs, photos, invoices, exports.</span>
                            <?php if ($can_edit) { ?>
                                <div class="d-flex gap-2">
                                    <button type="button" class="btn btn-sm btn-outline-secondary ajax-modal" data-modal-url="modals/asset/asset_link_file.php?id=<?= $asset_id ?>">
                                        <i class="fas fa-link me-2"></i>Link
                                    </button>
                                    <button type="button" class="btn btn-sm btn-primary ajax-modal" data-modal-url="modals/file/file_upload.php?<?= $client_url ?>&asset_id=<?= $asset_id ?>">
                                        <i class="fas fa-upload me-2"></i>Upload
                                    </button>
                                </div>
                            <?php } ?>
                        </div>
                        <?php if ($files_count) {
                            ?>
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead class="text-nowrap table-light">
                                    <tr>
                                        <th>Name</th>
                                        <th>Uploaded</th>
                                        <th></th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    <?php while ($row = mysqli_fetch_assoc($sql_related_files)) {
                                        $file_id = intval($row['file_id']);
                                        $file_name = escapeHtml($row['file_name']);
                                        $file_description = escapeHtml($row['file_description']);
                                        $file_created_at = escapeHtml($row['file_created_at']);
                                        ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center gap-2">
                                                    <?= fileTypeTile($row['file_ext'], 'sm') ?>
                                                    <div class="min-w-0">
                                                        <a class="fw-semibold ajax-modal" href="#" data-modal-size="lg" data-modal-url="modals/file/file.php?id=<?= $file_id ?>"><?= $file_name ?></a>
                                                        <?php if ($file_description) { ?><div class="small text-body-secondary"><?= $file_description ?></div><?php } ?>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="text-nowrap small" title="<?= $file_created_at ?>"><?= timeAgo($file_created_at) ?></td>
                                            <td class="text-end">
                                                <?php if ($can_edit) { ?>
                                                    <a href="post.php?unlink_asset_from_file&asset_id=<?= $asset_id ?>&file_id=<?= $file_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>" class="btn btn-sm btn-light" title="Unlink"><i class="fas fa-fw fa-unlink"></i></a>
                                                <?php } ?>
                                            </td>
                                        </tr>
                                    <?php } ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php } else { ?>
                            <div class="itflow-asset-empty">No files linked.</div>
                        <?php } ?>
                    </div>

                    <!-- Services -->
                    <div class="tab-pane fade" id="asset-pane-services" role="tabpanel">
                        <div class="itflow-asset-pane-bar">
                            <span class="text-body-secondary small">Services that run on it - when it is down, so are they.</span>
                            <?php if ($can_edit) { ?>
                                <button type="button" class="btn btn-sm btn-outline-secondary ajax-modal" data-modal-url="modals/asset/asset_link_service.php?id=<?= $asset_id ?>">
                                    <i class="fas fa-link me-2"></i>Link Service
                                </button>
                            <?php } ?>
                        </div>
                        <?php if ($service_count) { ?>
                            <?= serviceRowsHtml($linked_services, $can_edit ? function ($row) use ($asset_id) {
                                return "post.php?unlink_item&from=asset&id=$asset_id&to=service&other=" . intval($row['service_id']) . "&csrf_token=" . $_SESSION['csrf_token'];
                            } : null) ?>
                        <?php } else { ?>
                            <div class="itflow-asset-empty">Not part of any service.</div>
                        <?php } ?>
                    </div>

                    <!-- History, from the audit log -->
                    <div class="tab-pane fade" id="asset-pane-history" role="tabpanel">
                        <div class="itflow-asset-pane-bar">
                            <span class="text-body-secondary small">Changes, moves and notes - newest first.</span>
                        </div>
                        <?= auditLogHistoryHtml($history, ['client_id' => $client_id]) ?>
                    </div>

                    <!-- Notes -->
                    <div class="tab-pane fade" id="asset-pane-notes" role="tabpanel">
                        <div class="itflow-asset-pane-bar">
                            <span class="text-body-secondary small">A dated log - visits, changes, findings.</span>
                            <?php if ($can_edit) { ?>
                                <button type="button" class="btn btn-sm btn-primary ajax-modal" data-modal-url="modals/asset/asset_note_add.php?id=<?= $asset_id ?>">
                                    <i class="fas fa-plus me-2"></i>New Note
                                </button>
                            <?php } ?>
                        </div>
                        <?php if ($note_count) { ?>
                            <div class="list-group list-group-flush">
                                <?php while ($row = mysqli_fetch_assoc($sql_related_notes)) {
                                    $asset_note_id = intval($row['asset_note_id']);
                                    $asset_note_type = escapeHtml($row['asset_note_type']);
                                    $asset_note = nl2br(escapeHtml($row['asset_note']));
                                    $note_by = escapeHtml($row['user_name']);
                                    $asset_note_created_at = escapeHtml($row['asset_note_created_at']);
                                    $note_type_icon = !empty($note_type_icons[$asset_note_type]) ? $note_type_icons[$asset_note_type] : 'fa-sticky-note';
                                    ?>
                                    <div class="list-group-item d-flex gap-3 py-3">
                                        <span class="itflow-asset-icon itflow-asset-icon-sm itflow-asset-tone-other" title="<?= $asset_note_type ?>"><i class="fas fa-fw <?= $note_type_icon ?>"></i></span>
                                        <div class="flex-grow-1 min-w-0">
                                            <div class="small text-body-secondary mb-1">
                                                <span class="fw-semibold text-body"><?= $asset_note_type ?></span>
                                                &middot; <?= $note_by ?> &middot; <span title="<?= $asset_note_created_at ?>"><?= timeAgo($asset_note_created_at) ?></span>
                                            </div>
                                            <div><?= $asset_note ?></div>
                                        </div>
                                        <?php if ($can_edit) { ?>
                                            <div class="dropdown dropstart">
                                                <button class="btn btn-sm btn-light" title="More actions" type="button" data-bs-toggle="dropdown">
                                                    <i class="fas fa-fw fa-ellipsis-v"></i>
                                                </button>
                                                <div class="dropdown-menu">
                                                    <a class="dropdown-item text-danger" href="post.php?archive_asset_note=<?= $asset_note_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>">
                                                        <i class="fas fa-fw fa-archive me-2"></i>Archive
                                                    </a>
                                                    <?php if ($can_delete) { ?>
                                                        <a class="dropdown-item text-danger text-bold confirm-link" href="post.php?delete_asset_note=<?= $asset_note_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>">
                                                            <i class="fas fa-fw fa-trash me-2"></i>Delete
                                                        </a>
                                                    <?php } ?>
                                                </div>
                                            </div>
                                        <?php } ?>
                                    </div>
                                <?php } ?>
                            </div>
                        <?php } else { ?>
                            <div class="itflow-asset-empty">No notes yet.</div>
                        <?php } ?>
                    </div>

                </div>
            </div>
        </div>

    </div>


    <script>
        // Interfaces as ports or as a table - remembered between visits
        (function () {
            var radios = document.querySelectorAll('input[name="interfaceView"]');
            if (!radios.length) {
                return;
            }
            function show(view) {
                document.querySelectorAll('[data-interface-view]').forEach(function (box) {
                    box.hidden = box.getAttribute('data-interface-view') !== view;
                });
                document.getElementById(view === 'table' ? 'interfaceViewTable' : 'interfaceViewPorts').checked = true;
            }
            var saved = '';
            try { saved = localStorage.getItem('itflow.interfaceView') || ''; } catch (e) {}
            show(saved === 'table' ? 'table' : 'ports');
            radios.forEach(function (radio) {
                radio.addEventListener('change', function () {
                    show(radio.value);
                    try { localStorage.setItem('itflow.interfaceView', radio.value); } catch (e) {}
                });
            });
        })();

        function updateAssetNotes(asset_id) {
            itflowPost("ajax.php", {
                asset_set_notes: 'TRUE',
                csrf_token: '<?= $_SESSION['csrf_token'] ?>',
                asset_id: asset_id,
                notes: document.getElementById("assetNotes").value
            });
        }

        /*
         * Which tab is open: the URL hash wins (#tickets - links from elsewhere
         * use it), then the tab last used on any asset, then Interfaces.
         * Choosing a tab updates the hash without adding a history entry, so a
         * refresh or the redirect back from a save lands on the same tab.
         * Bootstrap loads in the footer, so this waits for it.
         */
        document.addEventListener('DOMContentLoaded', function () {
            var key = 'itflow.assetTab';
            var tabs = document.querySelectorAll('[data-asset-tab]');
            if (!tabs.length) {
                return;
            }
            function byName(name) {
                return document.querySelector('[data-asset-tab="' + name + '"]');
            }
            var wanted = location.hash.replace('#', '');
            var remembered = '';
            try { remembered = localStorage.getItem(key) || ''; } catch (e) {}
            var start = byName(wanted) || byName(remembered) || tabs[0];
            bootstrap.Tab.getOrCreateInstance(start).show();

            tabs.forEach(function (tab) {
                tab.addEventListener('shown.bs.tab', function () {
                    var name = tab.getAttribute('data-asset-tab');
                    try { localStorage.setItem(key, name); } catch (e) {}
                    history.replaceState(null, '', location.pathname + location.search + '#' + name);
                });
            });
        });
    </script>

    <script src="../js/bulk_actions.js"></script>

<?php
}

require_once "../includes/footer.php";
