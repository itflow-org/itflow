<?php

/*
 * Asset - one asset.
 *
 * Built around what a tech needs from the box: what it is and its status
 * (header), how to get on it (IP, MAC, links - each one copies or opens in a
 * click), what it physically is and whether it is still under warranty, where
 * it is and who has it. Everything linked to it - interfaces, credentials,
 * tickets, licenses, documents, files, services, notes - sits in tabs with
 * counts, so nothing is hidden just because it is empty and the page does not
 * run to ten screens. The open tab follows the URL hash (#tickets) and is
 * remembered between visits.
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
    location_archived_at, location_name, network_name, vendor_name FROM assets
    LEFT JOIN clients ON client_id = asset_client_id
    LEFT JOIN contacts ON asset_contact_id = contact_id
    LEFT JOIN locations ON asset_location_id = location_id
    LEFT JOIN vendors ON asset_vendor_id = vendor_id
    LEFT JOIN asset_interfaces ON interface_asset_id = asset_id AND interface_primary = 1
    LEFT JOIN networks ON network_id = interface_network_id
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
    $device_icon = getAssetIcon($row['asset_type']);

    $asset_ip = escapeHtml($row['interface_ip']);
    $asset_ipv6 = escapeHtml($row['interface_ipv6']);
    $asset_nat_ip = escapeHtml($row['interface_nat_ip']);
    $asset_mac = escapeHtml($row['interface_mac']);
    $asset_network_id = intval($row['interface_network_id']);
    $network_name = escapeHtml($row['network_name']);
    $vendor_name = escapeHtml($row['vendor_name']);

    $contact_name = escapeHtml($row['contact_name']);
    $contact_archived = !empty($row['contact_archived_at']);
    $location_name = escapeHtml($row['location_name']);
    $location_archived = !empty($row['location_archived_at']);

    $can_edit = lookupUserPermission('module_support') >= 2;
    $can_delete = lookupUserPermission('module_support') >= 3;
    $can_credentials = lookupUserPermission('module_credential') >= 1;

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

    // Tickets - linked as the ticket's asset or as one of its additional assets
    $sql_related_tickets = mysqli_query($mysqli, "
        SELECT tickets.ticket_id, ticket_prefix, ticket_number, ticket_subject, ticket_priority, ticket_created_at,
            ticket_updated_at, ticket_resolved_at, ticket_closed_at, ticket_assigned_to,
            user_name, user_avatar, ticket_status_id, ticket_status_name, ticket_status_color
        FROM tickets
        LEFT JOIN users ON ticket_assigned_to = user_id
        LEFT JOIN ticket_statuses ON ticket_status_id = ticket_status
        LEFT JOIN ticket_assets ON tickets.ticket_id = ticket_assets.ticket_id
        WHERE ticket_asset_id = $asset_id OR ticket_assets.asset_id = $asset_id
        GROUP BY tickets.ticket_id
        ORDER BY (ticket_resolved_at IS NULL AND ticket_closed_at IS NULL) DESC, ticket_number DESC
    ");
    $ticket_count = mysqli_num_rows($sql_related_tickets);
    $ticket_open_count = 0;
    $related_tickets = [];
    while ($row = mysqli_fetch_assoc($sql_related_tickets)) {
        $related_tickets[] = $row;
        if (empty($row['ticket_resolved_at']) && empty($row['ticket_closed_at'])) {
            $ticket_open_count++;
        }
    }

    // Recurring tickets
    $sql_related_recurring_tickets = mysqli_query($mysqli, "SELECT recurring_tickets.recurring_ticket_id, recurring_ticket_subject,
            recurring_ticket_priority, recurring_ticket_frequency, recurring_ticket_next_run
        FROM recurring_tickets
        LEFT JOIN recurring_ticket_assets ON recurring_tickets.recurring_ticket_id = recurring_ticket_assets.recurring_ticket_id
        WHERE recurring_ticket_asset_id = $asset_id OR recurring_ticket_assets.asset_id = $asset_id
        GROUP BY recurring_tickets.recurring_ticket_id
        ORDER BY recurring_ticket_next_run ASC"
    );
    $recurring_ticket_count = mysqli_num_rows($sql_related_recurring_tickets);

    // Network interfaces, and what each one is plugged into
    $sql_related_interfaces = mysqli_query($mysqli, "
        SELECT
            ai.interface_id,
            ai.interface_name,
            ai.interface_description,
            ai.interface_type,
            ai.interface_mac,
            ai.interface_ip,
            ai.interface_nat_ip,
            ai.interface_ipv6,
            ai.interface_primary,
            n.network_name,
            n.network_id,
            connected_interfaces.interface_name AS connected_interface_name,
            connected_assets.asset_name AS connected_asset_name,
            connected_assets.asset_id AS connected_asset_id,
            connected_assets.asset_type AS connected_asset_type
        FROM asset_interfaces AS ai
        LEFT JOIN networks AS n
          ON n.network_id = ai.interface_network_id
        LEFT JOIN asset_interface_links AS ail
          ON (ail.interface_a_id = ai.interface_id OR ail.interface_b_id = ai.interface_id)
        LEFT JOIN asset_interfaces AS connected_interfaces
          ON (
              (ail.interface_a_id = ai.interface_id AND ail.interface_b_id = connected_interfaces.interface_id)
              OR
              (ail.interface_b_id = ai.interface_id AND ail.interface_a_id = connected_interfaces.interface_id)
          )
        LEFT JOIN assets AS connected_assets
          ON connected_assets.asset_id = connected_interfaces.interface_asset_id
        WHERE
            ai.interface_asset_id = $asset_id
            AND ai.interface_archived_at IS NULL
        ORDER BY ai.interface_primary DESC, ai.interface_name ASC
    ");
    $interface_count = mysqli_num_rows($sql_related_interfaces);

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
    $credential_count = 0;
    if ($can_credentials) {
        $sql_related_credentials = mysqli_query($mysqli, "
            SELECT credential_id, credential_name, credential_description, credential_uri,
                credential_username, credential_otp_secret
            FROM credentials
            WHERE credential_asset_id = $asset_id
              AND credential_archived_at IS NULL
            ORDER BY credential_name ASC
        ");
        $credential_count = mysqli_num_rows($sql_related_credentials);
    }

    // Licenses, with seats in use counted in the same query instead of two queries per license
    $sql_related_software = mysqli_query(
        $mysqli,
        "SELECT software_expire, software_assets.software_id, software_license_type,
            software_name, software_seats, software_type, software_version,
            (SELECT COUNT(*) FROM software_assets AS sa WHERE sa.software_id = software.software_id)
            + (SELECT COUNT(*) FROM software_contacts AS sc WHERE sc.software_id = software.software_id) AS seats_used
        FROM software_assets
        LEFT JOIN software ON software_assets.software_id = software.software_id
        WHERE software_assets.asset_id = $asset_id
        AND software_archived_at IS NULL
        ORDER BY software_name ASC"
    );
    $software_count = mysqli_num_rows($sql_related_software);

    // Linked services
    $sql_linked_services = mysqli_query($mysqli, "SELECT service_category, service_description, service_assets.service_id, service_importance,
        service_name FROM service_assets, services
        WHERE service_assets.asset_id = $asset_id
        AND service_assets.service_id = services.service_id
        ORDER BY service_name ASC"
    );
    $service_count = mysqli_num_rows($sql_linked_services);

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
                            <?php if ($can_credentials) { ?>
                                <a class="dropdown-item ajax-modal" href="#" data-modal-url="modals/credential/credential_add.php?<?= $client_url ?>asset_id=<?= $asset_id ?>">
                                    <i class="fa fa-fw fa-key text-secondary me-2"></i>Credential
                                </a>
                            <?php } ?>
                            <a class="dropdown-item ajax-modal" href="#" data-modal-url="modals/asset/asset_interface_add.php?&asset_id=<?= $asset_id ?>">
                                <i class="fa fa-fw fa-ethernet text-secondary me-2"></i>Interface
                            </a>
                            <a class="dropdown-item ajax-modal" href="#" data-modal-url="modals/document/document_add.php?<?= $client_url ?>&asset_id=<?= $asset_id ?>" data-modal-size="lg">
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
                            <a class="dropdown-item ajax-modal" href="#" data-modal-url="modals/asset/asset_link_software.php?id=<?= $asset_id ?>">
                                <i class="fa fa-fw fa-cube text-secondary me-2"></i>License
                            </a>
                            <?php if ($can_credentials) { ?>
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
                                <dd><a href="network.php?client_id=<?= $client_id ?>&network_id=<?= $asset_network_id ?>"><?= $network_name ?></a></dd>
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
                            <dd><a class="ajax-modal" href="#" data-modal-url="modals/vendor/vendor.php?id=<?= $asset_vendor_id ?>"><?= $vendor_name ?></a></dd>
                        <?php } ?>
                        <?php if ($asset_purchase_reference) { ?>
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

                    <!-- Interfaces -->
                    <div class="tab-pane fade" id="asset-pane-interfaces" role="tabpanel">
                        <div class="itflow-asset-pane-bar">
                            <span class="text-body-secondary small">Ports and addresses. The primary interface is the one shown on the left.</span>
                            <?php if ($can_edit) { ?>
                                <div class="d-flex gap-2">
                                    <div class="dropdown" id="bulkActionButton" hidden>
                                        <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                            <i class="fas fa-fw fa-layer-group me-2"></i>Bulk Action (<span id="selectedCount">0</span>)
                                        </button>
                                        <div class="dropdown-menu dropdown-menu-end">
                                            <a class="dropdown-item ajax-modal" href="#"
                                                data-modal-url="modals/asset/asset_interface_bulk_edit_network.php?client_id=<?= $client_id ?>"
                                                data-bulk="true">
                                                <i class="fas fa-fw fa-network-wired me-2"></i>Assign Network
                                            </a>
                                            <a class="dropdown-item ajax-modal" href="#"
                                                data-modal-url="modals/asset/asset_interface_bulk_edit_type.php?client_id=<?= $client_id ?>"
                                                data-bulk="true">
                                                <i class="fas fa-fw fa-ethernet me-2"></i>Set Type
                                            </a>
                                            <button class="dropdown-item" type="submit" form="bulkActions" name="bulk_edit_asset_interface_ip_dhcp">
                                                <i class="fas fa-fw fa-list-ul me-2"></i>Set to DHCP
                                            </button>
                                            <?php if ($can_delete) { ?>
                                                <div class="dropdown-divider"></div>
                                                <button class="dropdown-item text-danger text-bold confirm-link" type="submit" form="bulkActions" name="bulk_delete_asset_interfaces">
                                                    <i class="fas fa-fw fa-trash me-2"></i>Delete
                                                </button>
                                            <?php } ?>
                                        </div>
                                    </div>
                                    <div class="btn-group">
                                        <button type="button" class="btn btn-sm btn-primary ajax-modal" data-modal-url="modals/asset/asset_interface_add.php?&asset_id=<?= $asset_id ?>">
                                            <i class="fas fa-plus me-2"></i>New Interface
                                        </button>
                                        <button type="button" class="btn btn-sm btn-primary dropdown-toggle dropdown-toggle-split" data-bs-toggle="dropdown"></button>
                                        <div class="dropdown-menu dropdown-menu-end">
                                            <a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#addMultipleAssetInterfacesModal">
                                                <i class="fa fa-fw fa-check-double me-2"></i>Add Multiple
                                            </a>
                                            <a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#importAssetInterfaceModal">
                                                <i class="fa fa-fw fa-upload me-2"></i>Import
                                            </a>
                                            <a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#exportAssetInterfaceModal">
                                                <i class="fa fa-fw fa-download me-2"></i>Export
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            <?php } ?>
                        </div>
                        <form id="bulkActions" action="post.php" method="post">
                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead class="text-nowrap table-light <?php if ($interface_count == 0) { echo "d-none"; } ?>">
                                        <tr>
                                            <th class="checkbox-column border-end">
                                                <div class="form-check">
                                                    <input class="form-check-input" id="selectAllCheckbox" type="checkbox" onclick="checkAll(this)" onkeydown="checkAll(this)" title="Select all">
                                                </div>
                                            </th>
                                            <th>Name / Port</th>
                                            <th>Type</th>
                                            <th>Network</th>
                                            <th>IP</th>
                                            <th>MAC</th>
                                            <th>Connected To</th>
                                            <th></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    <?php while ($row = mysqli_fetch_assoc($sql_related_interfaces)) {
                                        $interface_id = intval($row['interface_id']);
                                        $interface_name = escapeHtml($row['interface_name']);
                                        $interface_description = escapeHtml($row['interface_description']);
                                        $interface_type = escapeHtml($row['interface_type']);
                                        $interface_mac = escapeHtml($row['interface_mac']);
                                        $interface_ip = escapeHtml($row['interface_ip']);
                                        $interface_nat_ip = escapeHtml($row['interface_nat_ip']);
                                        $interface_ipv6 = escapeHtml($row['interface_ipv6']);
                                        $interface_primary = intval($row['interface_primary']);
                                        $interface_network_id = intval($row['network_id']);
                                        $interface_network_name = escapeHtml($row['network_name']);
                                        $connected_asset_id = intval($row['connected_asset_id']);
                                        $connected_asset_name = escapeHtml($row['connected_asset_name']);
                                        $connected_asset_icon = getAssetIcon($row['connected_asset_type']);
                                        $connected_interface_name = escapeHtml($row['connected_interface_name']);
                                        ?>
                                        <tr>
                                            <td class="checkbox-column bg-light border-end">
                                                <div class="form-check">
                                                    <input class="form-check-input bulk-select" type="checkbox" name="interface_ids[]" value="<?= $interface_id ?>" aria-label="Select <?= $interface_name ?>">
                                                </div>
                                            </td>
                                            <td>
                                                <a class="text-body fw-semibold ajax-modal" href="#" data-modal-url="modals/asset/asset_interface_edit.php?id=<?= $interface_id ?>"><?= $interface_name ?></a>
                                                <?php if ($interface_primary) { ?><span class="badge rounded-pill text-bg-primary fw-normal ms-1">Primary</span><?php } ?>
                                                <?php if ($interface_description) { ?><div class="small text-body-secondary"><?= $interface_description ?></div><?php } ?>
                                            </td>
                                            <td><?= $interface_type ?: '<span class="text-body-secondary">-</span>' ?></td>
                                            <td>
                                                <?php if ($interface_network_name) { ?>
                                                    <a href="network.php?client_id=<?= $client_id ?>&network_id=<?= $interface_network_id ?>"><?= $interface_network_name ?></a>
                                                <?php } else { ?>
                                                    <span class="text-body-secondary">-</span>
                                                <?php } ?>
                                            </td>
                                            <td class="text-nowrap">
                                                <?php if ($interface_ip) { ?>
                                                    <span class="font-monospace"><?= $interface_ip ?></span><?php if ($interface_ip !== 'DHCP') { ?><button type="button" class="itflow-copy clipboardjs" title="Copy IP" data-clipboard-text="<?= $interface_ip ?>"><i class="far fa-copy"></i></button><?php } ?>
                                                <?php } else { ?>
                                                    <span class="text-body-secondary">-</span>
                                                <?php } ?>
                                                <?php if ($interface_nat_ip) { ?><div class="small text-body-secondary font-monospace">NAT <?= $interface_nat_ip ?></div><?php } ?>
                                                <?php if ($interface_ipv6) { ?><div class="small text-body-secondary font-monospace"><?= $interface_ipv6 ?></div><?php } ?>
                                            </td>
                                            <td class="text-nowrap">
                                                <?php if ($interface_mac) { ?>
                                                    <span class="font-monospace"><?= $interface_mac ?></span><button type="button" class="itflow-copy clipboardjs" title="Copy MAC" data-clipboard-text="<?= $interface_mac ?>"><i class="far fa-copy"></i></button>
                                                <?php } else { ?>
                                                    <span class="text-body-secondary">-</span>
                                                <?php } ?>
                                            </td>
                                            <td>
                                                <?php if ($connected_asset_name) { ?>
                                                    <a class="ajax-modal" href="#" data-modal-size="lg" data-modal-url="modals/asset/asset.php?id=<?= $connected_asset_id ?>">
                                                        <i class="fas fa-fw fa-<?= $connected_asset_icon ?> me-1"></i><?= $connected_asset_name ?></a>
                                                    <span class="text-body-secondary">&middot; <?= $connected_interface_name ?></span>
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
                                                            <a class="dropdown-item ajax-modal" href="#" data-modal-url="modals/asset/asset_interface_edit.php?id=<?= $interface_id ?>">
                                                                <i class="fas fa-fw fa-edit text-secondary me-2"></i>Edit
                                                            </a>
                                                            <?php if ($can_delete && !$interface_primary) { ?>
                                                                <div class="dropdown-divider"></div>
                                                                <a class="dropdown-item text-danger text-bold confirm-link" href="post.php?delete_asset_interface=<?= $interface_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>">
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
                            <div class="itflow-asset-empty">No interfaces yet.</div>
                        <?php } ?>
                    </div>

                    <?php if ($can_credentials) { ?>
                    <!-- Credentials -->
                    <div class="tab-pane fade" id="asset-pane-credentials" role="tabpanel">
                        <div class="itflow-asset-pane-bar">
                            <span class="text-body-secondary small">Logins for this asset. Passwords and codes are fetched only when you ask for them.</span>
                            <?php if ($can_edit) { ?>
                                <div class="d-flex gap-2">
                                    <button type="button" class="btn btn-sm btn-outline-secondary ajax-modal" data-modal-url="modals/asset/asset_link_credential.php?id=<?= $asset_id ?>">
                                        <i class="fas fa-link me-2"></i>Link
                                    </button>
                                    <button type="button" class="btn btn-sm btn-primary ajax-modal" data-modal-url="modals/credential/credential_add.php?<?= $client_url ?>asset_id=<?= $asset_id ?>">
                                        <i class="fas fa-plus me-2"></i>New Credential
                                    </button>
                                </div>
                            <?php } ?>
                        </div>
                        <?php if ($credential_count) { ?>
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead class="text-nowrap table-light">
                                    <tr>
                                        <th>Name</th>
                                        <th>Username</th>
                                        <th>Password</th>
                                        <th>OTP</th>
                                        <th>URI</th>
                                        <th></th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    <?php while ($row = mysqli_fetch_assoc($sql_related_credentials)) {
                                        $credential_id = intval($row['credential_id']);
                                        $credential_name = escapeHtml($row['credential_name']);
                                        $credential_description = escapeHtml($row['credential_description']);
                                        $credential_uri = escapeHtml($row['credential_uri']);
                                        $credential_username = escapeHtml(decryptCredentialEntry($row['credential_username']));
                                        $credential_has_otp = !empty($row['credential_otp_secret']);
                                        ?>
                                        <tr>
                                            <td>
                                                <a class="text-body fw-semibold ajax-modal" href="#" data-modal-url="modals/credential/credential_edit.php?id=<?= $credential_id ?>"><?= $credential_name ?></a>
                                                <?php if ($credential_description) { ?><div class="small text-body-secondary"><?= $credential_description ?></div><?php } ?>
                                            </td>
                                            <td class="text-nowrap">
                                                <?php if ($credential_username) { ?>
                                                    <?= $credential_username ?><button type="button" class="itflow-copy clipboardjs" title="Copy username" data-clipboard-text="<?= $credential_username ?>"><i class="far fa-copy"></i></button>
                                                <?php } else { ?>
                                                    <span class="text-body-secondary">-</span>
                                                <?php } ?>
                                            </td>
                                            <td class="text-nowrap">
                                                <button class="btn btn-sm btn-light" aria-label="Show password" title="Show password" type="button" onclick="showPasswordViaCredentialID(this, <?= $credential_id ?>)"><i class="fas fa-ellipsis-h"></i><i class="fas fa-ellipsis-h"></i></button>
                                                <button type="button" class="itflow-copy" title="Copy password" onclick="copyPasswordViaCredentialID(this, <?= $credential_id ?>)"><i class="far fa-copy"></i></button>
                                            </td>
                                            <td class="text-nowrap">
                                                <?php if ($credential_has_otp) { ?>
                                                    <span onmouseenter="showOTPViaCredentialID(<?= $credential_id ?>)"><i class="far fa-clock text-secondary me-1"></i><span id="otp_<?= $credential_id ?>"><i>Hover...</i></span></span>
                                                <?php } else { ?>
                                                    <span class="text-body-secondary">-</span>
                                                <?php } ?>
                                            </td>
                                            <td class="text-nowrap">
                                                <?php if ($credential_uri) { ?>
                                                    <a href="<?= $credential_uri ?>" target="_blank" rel="noopener" title="<?= $credential_uri ?>"><?= truncate($credential_uri, 26) ?></a><button type="button" class="itflow-copy clipboardjs" title="Copy URI" data-clipboard-text="<?= $credential_uri ?>"><i class="far fa-copy"></i></button>
                                                <?php } else { ?>
                                                    <span class="text-body-secondary">-</span>
                                                <?php } ?>
                                            </td>
                                            <td class="text-end">
                                                <div class="dropdown dropstart">
                                                    <button class="btn btn-sm btn-light" title="More actions" type="button" data-bs-toggle="dropdown">
                                                        <i class="fas fa-fw fa-ellipsis-v"></i>
                                                    </button>
                                                    <div class="dropdown-menu">
                                                        <a class="dropdown-item ajax-modal" href="#" data-modal-url="modals/credential/credential_edit.php?id=<?= $credential_id ?>">
                                                            <i class="fas fa-fw fa-edit text-secondary me-2"></i>Edit
                                                        </a>
                                                        <a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#shareModal" onclick="populateShareModal(<?= "$client_id, 'Credential', $credential_id" ?>)">
                                                            <i class="fas fa-fw fa-share-alt text-secondary me-2"></i>Share
                                                        </a>
                                                        <div class="dropdown-divider"></div>
                                                        <a class="dropdown-item" href="post.php?unlink_credential_from_asset&asset_id=<?= $asset_id ?>&credential_id=<?= $credential_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>">
                                                            <i class="fas fa-fw fa-unlink text-secondary me-2"></i>Unlink
                                                        </a>
                                                        <?php if (lookupUserPermission('module_credential') >= 3) { ?>
                                                            <a class="dropdown-item text-danger text-bold confirm-link" href="post.php?delete_credential=<?= $credential_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>">
                                                                <i class="fas fa-fw fa-trash me-2"></i>Delete
                                                            </a>
                                                        <?php } ?>
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php } ?>
                                    </tbody>
                                </table>
                            </div>
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
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead class="text-nowrap table-light">
                                    <tr>
                                        <th>Ticket</th>
                                        <th>Subject</th>
                                        <th>Priority</th>
                                        <th>Status</th>
                                        <th>Assigned</th>
                                        <th>Updated</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ($related_tickets as $row) {
                                        $ticket_id = intval($row['ticket_id']);
                                        $ticket_prefix = escapeHtml($row['ticket_prefix']);
                                        $ticket_number = intval($row['ticket_number']);
                                        $ticket_subject = escapeHtml($row['ticket_subject']);
                                        $ticket_priority = escapeHtml($row['ticket_priority']);
                                        $ticket_status_id = intval($row['ticket_status_id']);
                                        $ticket_status_name = escapeHtml($row['ticket_status_name']);
                                        $ticket_status_color = preg_match('/^#[0-9a-f]{3,8}$/i', (string) $row['ticket_status_color']) ? $row['ticket_status_color'] : '#6c757d';
                                        $ticket_created_at = escapeHtml($row['ticket_created_at']);
                                        $ticket_updated_at = escapeHtml($row['ticket_updated_at'] ?: $row['ticket_created_at']);
                                        $ticket_is_open = empty($row['ticket_resolved_at']) && empty($row['ticket_closed_at']);
                                        $priority_classes = ['Urgent' => 'text-bg-dark', 'High' => 'text-bg-danger', 'Medium' => 'text-bg-warning', 'Low' => 'text-bg-info'];
                                        $ticket_assigned_to_display = formatAssignee($row['ticket_assigned_to'], $row['user_name'], $row['user_avatar'], !in_array($ticket_status_id, [4, 5]));
                                        ?>
                                        <tr class="<?= $ticket_is_open ? '' : 'text-body-secondary' ?>">
                                            <td class="text-nowrap"><a class="font-monospace" href="ticket.php?client_id=<?= $client_id ?>&ticket_id=<?= $ticket_id ?>"><?= "$ticket_prefix$ticket_number" ?></a></td>
                                            <td><a class="<?= $ticket_is_open ? 'text-body fw-semibold' : 'text-body-secondary' ?>" href="ticket.php?client_id=<?= $client_id ?>&ticket_id=<?= $ticket_id ?>"><?= $ticket_subject ?></a></td>
                                            <td><?php if (isset($priority_classes[$row['ticket_priority']])) { ?><span class="badge rounded-pill fw-normal <?= $priority_classes[$row['ticket_priority']] ?>"><?= $ticket_priority ?></span><?php } else { ?>-<?php } ?></td>
                                            <td><span class="badge rounded-pill fw-normal text-white" style="background-color: <?= $ticket_status_color ?>"><?= $ticket_status_name ?></span></td>
                                            <td><?= $ticket_assigned_to_display ?></td>
                                            <td class="text-nowrap small" title="Created <?= $ticket_created_at ?>"><?= timeAgo($ticket_updated_at) ?></td>
                                        </tr>
                                    <?php } ?>
                                    </tbody>
                                </table>
                            </div>
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
                                                <a class="text-body fw-semibold ajax-modal" href="#" data-modal-size="lg" data-modal-url="modals/recurring_ticket/recurring_ticket_edit.php?id=<?= $recurring_ticket_id ?>"><?= $recurring_ticket_subject ?></a>
                                            </td>
                                            <td><?= $recurring_ticket_priority ?></td>
                                            <td><?= $recurring_ticket_frequency ?></td>
                                            <td class="text-nowrap"><?= $recurring_ticket_next_run ?> <span class="small text-body-secondary">(<?= timeAgo($recurring_ticket_next_run) ?>)</span></td>
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
                                                            <a class="dropdown-item" href="post.php?force_recurring_ticket=<?= $recurring_ticket_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>">
                                                                <i class="fa fa-fw fa-paper-plane text-secondary me-2"></i>Force Reoccur
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
                            <span class="text-body-secondary small">Software licensed to this asset.</span>
                            <?php if ($can_edit) { ?>
                                <button type="button" class="btn btn-sm btn-outline-secondary ajax-modal" data-modal-url="modals/asset/asset_link_software.php?id=<?= $asset_id ?>">
                                    <i class="fas fa-link me-2"></i>Link License
                                </button>
                            <?php } ?>
                        </div>
                        <?php if ($software_count) { ?>
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead class="text-nowrap table-light">
                                    <tr>
                                        <th>Software</th>
                                        <th>Type</th>
                                        <th>License</th>
                                        <th>Seats</th>
                                        <th>Expires</th>
                                        <th></th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    <?php while ($row = mysqli_fetch_assoc($sql_related_software)) {
                                        $software_id = intval($row['software_id']);
                                        $software_name = escapeHtml($row['software_name']);
                                        $software_version = escapeHtml($row['software_version']);
                                        $software_type = escapeHtml($row['software_type']);
                                        $software_license_type = escapeHtml($row['software_license_type']);
                                        $software_seats = escapeHtml($row['software_seats']);
                                        $software_seats_used = intval($row['seats_used']);
                                        $software_expire = assetWarranty($row['software_expire'], 30);
                                        ?>
                                        <tr>
                                            <td>
                                                <a class="text-body fw-semibold ajax-modal" href="#" data-modal-url="modals/software/software_edit.php?id=<?= $software_id ?>"><?= $software_name ?></a>
                                                <?php if ($software_version) { ?><div class="small text-body-secondary"><?= $software_version ?></div><?php } ?>
                                            </td>
                                            <td><?= $software_type ?></td>
                                            <td><?= $software_license_type ?></td>
                                            <td class="text-nowrap <?= $software_seats !== '' && $software_seats_used > intval($software_seats) ? 'text-danger' : '' ?>"><?= $software_seats_used ?> / <?= $software_seats !== '' ? $software_seats : '&infin;' ?></td>
                                            <td class="text-nowrap">
                                                <?php if ($software_expire) { ?>
                                                    <span class="text-<?= $software_expire['tone'] === 'success' ? 'body' : $software_expire['tone'] . '-emphasis' ?>" title="<?= escapeHtml($software_expire['text']) ?>"><?= escapeHtml($software_expire['date']) ?></span>
                                                <?php } else { ?>
                                                    <span class="text-body-secondary">-</span>
                                                <?php } ?>
                                            </td>
                                            <td class="text-end">
                                                <?php if ($can_edit) { ?>
                                                    <a href="post.php?unlink_software_from_asset&asset_id=<?= $asset_id ?>&software_id=<?= $software_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>" class="btn btn-sm btn-light" title="Unlink"><i class="fas fa-fw fa-unlink"></i></a>
                                                <?php } ?>
                                            </td>
                                        </tr>
                                    <?php } ?>
                                    </tbody>
                                </table>
                            </div>
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
                                    <button type="button" class="btn btn-sm btn-primary ajax-modal" data-modal-size="lg" data-modal-url="modals/document/document_add.php?<?= $client_url ?>&asset_id=<?= $asset_id ?>">
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
                                                <a class="fw-semibold" href="document.php?client_id=<?= $client_id ?>&document_id=<?= $document_id ?>"><?= $document_name ?></a>
                                                <?php if ($document_description) { ?><div class="small text-body-secondary"><?= $document_description ?></div><?php } ?>
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
                            $file_icons = [
                                'pdf' => 'file-pdf', 'gz' => 'file-archive', 'tar' => 'file-archive', 'zip' => 'file-archive', '7z' => 'file-archive', 'rar' => 'file-archive',
                                'txt' => 'file-alt', 'md' => 'file-alt', 'msg' => 'envelope', 'doc' => 'file-word', 'docx' => 'file-word', 'odt' => 'file-word',
                                'xls' => 'file-excel', 'xlsx' => 'file-excel', 'ods' => 'file-excel', 'csv' => 'file-csv', 'pptx' => 'file-powerpoint', 'odp' => 'file-powerpoint',
                                'mp3' => 'file-audio', 'wav' => 'file-audio', 'ogg' => 'file-audio', 'mov' => 'file-video', 'mp4' => 'file-video', 'av1' => 'file-video',
                                'jpg' => 'file-image', 'jpeg' => 'file-image', 'png' => 'file-image', 'gif' => 'file-image', 'webp' => 'file-image', 'bmp' => 'file-image', 'tif' => 'file-image',
                            ];
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
                                        $file_icon = $file_icons[strtolower((string) $row['file_ext'])] ?? 'file';
                                        $file_created_at = escapeHtml($row['file_created_at']);
                                        ?>
                                        <tr>
                                            <td>
                                                <i class="far fa-fw fa-<?= $file_icon ?> text-secondary me-2"></i><a class="fw-semibold" href="file.php?file_id=<?= $file_id ?>&action=view" target="_blank"><?= $file_name ?></a>
                                                <?php if ($file_description) { ?><div class="small text-body-secondary"><?= $file_description ?></div><?php } ?>
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
                            <span class="text-body-secondary small">Services that depend on this asset.</span>
                            <?php if ($can_edit) { ?>
                                <button type="button" class="btn btn-sm btn-outline-secondary ajax-modal" data-modal-url="modals/asset/asset_link_service.php?id=<?= $asset_id ?>">
                                    <i class="fas fa-link me-2"></i>Link Service
                                </button>
                            <?php } ?>
                        </div>
                        <?php if ($service_count) { ?>
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead class="text-nowrap table-light">
                                    <tr>
                                        <th>Service</th>
                                        <th>Category</th>
                                        <th>Importance</th>
                                        <th></th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    <?php while ($row = mysqli_fetch_assoc($sql_linked_services)) {
                                        $service_id = intval($row['service_id']);
                                        $service_name = escapeHtml($row['service_name']);
                                        $service_description = escapeHtml($row['service_description']);
                                        $service_category = escapeHtml($row['service_category']);
                                        $service_importance = escapeHtml($row['service_importance']);
                                        ?>
                                        <tr>
                                            <td>
                                                <a class="fw-semibold ajax-modal" href="#" data-modal-size="lg" data-modal-url="modals/service/service.php?id=<?= $service_id ?>"><?= $service_name ?></a>
                                                <?php if ($service_description) { ?><div class="small text-body-secondary"><?= $service_description ?></div><?php } ?>
                                            </td>
                                            <td><?= $service_category ?></td>
                                            <td><?= $service_importance ?></td>
                                            <td class="text-end">
                                                <?php if ($can_edit) { ?>
                                                    <a href="post.php?unlink_service_from_asset&asset_id=<?= $asset_id ?>&service_id=<?= $service_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>" class="btn btn-sm btn-light" title="Unlink"><i class="fas fa-fw fa-unlink"></i></a>
                                                <?php } ?>
                                            </td>
                                        </tr>
                                    <?php } ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php } else { ?>
                            <div class="itflow-asset-empty">Not part of any service.</div>
                        <?php } ?>
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

    <?php
    require_once "modals/share_modal.php";
    require_once "modals/asset/asset_interface_multiple_add.php";
    require_once "modals/asset/asset_interface_import.php";
    require_once "modals/asset/asset_interface_export.php";
    ?>

    <script>
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

    <!-- Fetch TOTP codes and passwords only when asked for -->
    <script src="js/credential_show_otp_via_id.js"></script>
    <script src="js/credential_show_password_via_id.js"></script>

    <script src="../js/bulk_actions.js"></script>

<?php
}

require_once "../includes/footer.php";
