<?php

/*
 * Asset quick look - the asset at a glance, opened from a ticket, the client
 * overview, a contact, a credential or a document without leaving the page.
 *
 * A small version of the asset page (agent/asset.php), in the same look: what
 * it is in the header, how to get on it and where it is in a few lines with
 * no field labels, then whatever is linked to it in tabs with counts. Status,
 * location and who it is assigned to change in place (js/quick_pick.js), like
 * on the asset page. Purchase details, IPv6, NAT and the like stay on the
 * asset page - "Open asset" is one click away.
 */

require_once '../../../includes/modal_header.php';

enforceUserPermission('module_support');

$asset_id = intval($_GET['id'] ?? 0);

$sql = mysqli_query($mysqli, "SELECT asset_archived_at, asset_client_id, asset_contact_id, asset_description, asset_favorite, asset_id,
    asset_location_id, asset_make, asset_model, asset_name, asset_notes, asset_os, asset_photo, asset_physical_location,
    asset_serial, asset_status, asset_type, asset_uri, asset_uri_2, asset_uri_client, asset_warranty_expire,
    contact_archived_at, contact_name, contact_photo,
    interface_ip, interface_mac,
    location_archived_at, location_name FROM assets
    LEFT JOIN contacts ON asset_contact_id = contact_id
    LEFT JOIN locations ON asset_location_id = location_id
    LEFT JOIN asset_interfaces ON interface_asset_id = asset_id AND interface_primary = 1
    WHERE asset_id = $asset_id
    LIMIT 1
");

$asset_row = mysqli_fetch_assoc($sql);
if (!$asset_row) {
    echo json_encode(['error' => 'That asset no longer exists.']);
    exit;
}

$client_id = intval($asset_row['asset_client_id']);

enforceClientAccess();

$can_edit = lookupUserPermission('module_support') >= 2;
$can_credentials = lookupUserPermission('module_credential') >= 1;

$asset_name = escapeHtml($asset_row['asset_name']);
$asset_type = escapeHtml($asset_row['asset_type']);
$asset_description = escapeHtml($asset_row['asset_description']);
$asset_make_model = escapeHtml(trim($asset_row['asset_make'] . ' ' . $asset_row['asset_model']));
$asset_os = escapeHtml($asset_row['asset_os']);
$asset_os_icon = assetOsIcon($asset_row['asset_os']);
$asset_serial = escapeHtml($asset_row['asset_serial']);
$asset_ip = escapeHtml($asset_row['interface_ip']);
$asset_mac = escapeHtml($asset_row['interface_mac']);
$asset_links = assetLinks($asset_row['asset_uri'], $asset_row['asset_uri_2'], $asset_row['asset_uri_client']);
$asset_warranty = assetWarranty($asset_row['asset_warranty_expire']);
$asset_photo = escapeHtml($asset_row['asset_photo']);
$asset_physical_location = escapeHtml($asset_row['asset_physical_location']);
$asset_notes = escapeHtml($asset_row['asset_notes']);
$asset_favorite = intval($asset_row['asset_favorite']);
$asset_archived = !empty($asset_row['asset_archived_at']);
$asset_contact_id = intval($asset_row['asset_contact_id']);
$asset_tone = assetTypeTone($asset_row['asset_type']);
$device_icon = getAssetIcon($asset_row['asset_type']);
$asset_tags_display = tagPills('asset', $asset_id, tagsForRecord('asset', $asset_id), "assets.php?client_id=$client_id&");

// The rack it sits in, if any
$asset_rack = rackPositionForAsset($asset_id);

// Interfaces, primary first, and what each one is plugged into
$sql_related_interfaces = mysqli_query($mysqli, "
    SELECT ai.interface_id, ai.interface_name, ai.interface_mac, ai.interface_ip, ai.interface_primary,
        connected_interfaces.interface_name AS connected_interface_name,
        connected_assets.asset_name AS connected_asset_name,
        connected_assets.asset_id AS connected_asset_id,
        connected_assets.asset_type AS connected_asset_type
    FROM asset_interfaces AS ai
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
    WHERE ai.interface_asset_id = $asset_id
      AND ai.interface_archived_at IS NULL
    ORDER BY ai.interface_primary DESC, ai.interface_name ASC
");
$interface_count = mysqli_num_rows($sql_related_interfaces);

// Credentials - only for agents who may see them
$credential_count = 0;
if ($can_credentials) {
    $sql_related_credentials = mysqli_query($mysqli, "
        SELECT credential_id, credential_name, credential_uri, credential_username, credential_otp_secret
        FROM credentials
        WHERE credential_asset_id = $asset_id
          AND credential_archived_at IS NULL
        ORDER BY credential_name ASC
    ");
    $credential_count = mysqli_num_rows($sql_related_credentials);
}

// Tickets - open ones first; the most recent few here, the rest on the asset page
$quick_ticket_limit = 8;
$sql_related_tickets = mysqli_query($mysqli, "
    SELECT tickets.ticket_id, ticket_prefix, ticket_number, ticket_subject, ticket_updated_at, ticket_created_at,
        ticket_resolved_at, ticket_closed_at, ticket_assigned_to, user_name, user_avatar,
        ticket_status_name, ticket_status_color
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
    $is_open = empty($row['ticket_resolved_at']) && empty($row['ticket_closed_at']);
    $ticket_open_count += $is_open ? 1 : 0;
    if (count($related_tickets) < $quick_ticket_limit) {
        $related_tickets[] = $row + ['is_open' => $is_open];
    }
}

// Recurring tickets
$sql_related_recurring_tickets = mysqli_query($mysqli, "SELECT recurring_tickets.recurring_ticket_id, recurring_ticket_subject,
        recurring_ticket_frequency, recurring_ticket_next_run
    FROM recurring_tickets
    LEFT JOIN recurring_ticket_assets ON recurring_tickets.recurring_ticket_id = recurring_ticket_assets.recurring_ticket_id
    WHERE recurring_ticket_asset_id = $asset_id OR recurring_ticket_assets.asset_id = $asset_id
    GROUP BY recurring_tickets.recurring_ticket_id
    ORDER BY recurring_ticket_next_run ASC"
);
$recurring_ticket_count = mysqli_num_rows($sql_related_recurring_tickets);

// Licenses, with seats in use counted in the same query
$sql_related_software = mysqli_query($mysqli, "SELECT software_assets.software_id, software_name, software_version, software_seats,
        (SELECT COUNT(*) FROM software_assets AS sa WHERE sa.software_id = software.software_id)
        + (SELECT COUNT(*) FROM software_contacts AS sc WHERE sc.software_id = software.software_id) AS seats_used
    FROM software_assets
    LEFT JOIN software ON software_assets.software_id = software.software_id
    WHERE software_assets.asset_id = $asset_id
      AND software_archived_at IS NULL
    ORDER BY software_name ASC"
);
$software_count = mysqli_num_rows($sql_related_software);

// Documents
$sql_related_documents = mysqli_query($mysqli, "SELECT documents.document_id, document_name, document_created_at, document_updated_at
    FROM asset_documents
    JOIN documents ON asset_documents.document_id = documents.document_id
    WHERE asset_documents.asset_id = $asset_id
      AND document_archived_at IS NULL
    ORDER BY document_name ASC"
);
$document_count = mysqli_num_rows($sql_related_documents);

// Files
$sql_related_files = mysqli_query($mysqli, "SELECT files.file_id, file_name, file_ext, file_created_at
    FROM asset_files
    JOIN files ON asset_files.file_id = files.file_id
    WHERE asset_files.asset_id = $asset_id
      AND file_archived_at IS NULL
    ORDER BY file_name ASC"
);
$file_count = mysqli_num_rows($sql_related_files);

// Notes
$sql_related_notes = mysqli_query($mysqli, "SELECT asset_note, asset_note_created_at, asset_note_type, user_name FROM asset_notes
    LEFT JOIN users ON asset_note_created_by = user_id
    WHERE asset_note_asset_id = $asset_id
      AND asset_note_archived_at IS NULL
    ORDER BY asset_note_created_at DESC"
);
$note_count = mysqli_num_rows($sql_related_notes);
$note_type_icons = [];
$sql_note_type_icons = mysqli_query($mysqli, "SELECT category_name, category_icon FROM categories WHERE category_type = 'asset_note_type'");
while ($row = mysqli_fetch_assoc($sql_note_type_icons)) {
    $note_type_icons[$row['category_name']] = escapeHtml($row['category_icon']);
}

// Only the tabs with something in them - same order as the asset page
$quick_tabs = array_filter([
    'interfaces' => ['label' => 'Interfaces', 'count' => $interface_count],
    'credentials' => ['label' => 'Credentials', 'count' => $credential_count],
    'tickets' => ['label' => 'Tickets', 'count' => $ticket_count, 'badge' => $ticket_open_count],
    'recurring' => ['label' => 'Recurring', 'count' => $recurring_ticket_count],
    'licenses' => ['label' => 'Licenses', 'count' => $software_count],
    'documents' => ['label' => 'Documents', 'count' => $document_count],
    'files' => ['label' => 'Files', 'count' => $file_count],
    'notes' => ['label' => 'Notes', 'count' => $note_count],
], function ($tab) {
    return $tab['count'] > 0;
});
$first_tab = array_key_first($quick_tabs);

$has_left = $asset_ip || $asset_mac || $asset_serial || $asset_warranty;
$has_right = $can_edit || $asset_row['location_name'] || $asset_row['contact_name'] || $asset_rack;

ob_start();

?>

<!-- What it is - the asset page's header, in the modal's dark bar -->
<div class="modal-header bg-dark" data-bs-theme="dark">
    <div class="d-flex align-items-center gap-3 min-w-0">
        <?php if ($asset_photo) { ?>
            <img class="itflow-asset-icon" src="<?= "../uploads/clients/$client_id/$asset_photo" ?>" alt="">
        <?php } else { ?>
            <span class="itflow-asset-icon itflow-asset-tone-<?= $asset_tone ?>" aria-hidden="true"><i class="fas fa-fw fa-<?= $device_icon ?>"></i></span>
        <?php } ?>
        <div class="min-w-0">
            <h5 class="modal-title d-flex flex-wrap align-items-center column-gap-2 row-gap-1">
                <span class="fw-semibold text-break"><?= $asset_name ?></span>
                <?php if ($asset_favorite) { ?><i class="fas fa-star text-warning fs-6" title="Favorite"></i><?php } ?>
                <?php if ($asset_archived) { ?><span class="badge rounded-pill text-bg-secondary fw-normal fs-6">Archived</span><?php } ?>
                <span class="fs-6">
                    <?php if ($can_edit) { ?>
                        <?= quickPick('asset_status', $asset_id, (string) $asset_row['asset_status'], assetQuickHtml('asset_status', $asset_row), 'list', 0, $client_id) ?>
                    <?php } else { ?>
                        <?= assetStatusPill($asset_row['asset_status']) ?>
                    <?php } ?>
                </span>
            </h5>
            <div class="small text-body-secondary d-flex flex-wrap column-gap-3 mt-1">
                <span><?= $asset_type ?></span>
                <?php if ($asset_make_model) { ?><span><?= $asset_make_model ?></span><?php } ?>
                <?php if ($asset_os) { ?><span><i class="<?= $asset_os_icon ?> fa-fw me-1"></i><?= $asset_os ?></span><?php } ?>
            </div>
        </div>
    </div>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
</div>

<div class="modal-body">

    <?php if ($asset_description || $asset_tags_display || $asset_links) { ?>
        <div class="d-flex flex-wrap align-items-start justify-content-between gap-2 mb-3">
            <div class="min-w-0">
                <?php if ($asset_description) { ?><div class="text-body-secondary"><?= $asset_description ?></div><?php } ?>
                <?php if ($asset_tags_display) { ?><div class="<?= $asset_description ? 'mt-1' : '' ?>"><?= $asset_tags_display ?></div><?php } ?>
            </div>
            <?php if ($asset_links) { ?>
                <div class="d-flex flex-wrap gap-2">
                    <?php foreach ($asset_links as [$link_label, $link_url]) { ?>
                        <a class="btn btn-sm btn-outline-primary" href="<?= escapeUrl($link_url) ?>" target="_blank" rel="noopener" title="<?= $link_label ?>: <?= escapeHtml($link_url) ?>">
                            <i class="<?= assetLinkIcon($link_url) ?> me-1"></i><?= escapeHtml(assetLinkLabel($link_url)) ?><?= $link_label === 'Client link' ? ' (client)' : '' ?>
                        </a>
                    <?php } ?>
                </div>
            <?php } ?>
        </div>
    <?php } ?>

    <!-- At a glance: how to get on it, and where it is - no labels, the icons say what each line is -->
    <?php if ($has_left || $has_right) { ?>
        <div class="row g-2 itflow-asset-glance">
            <?php if ($has_left) { ?>
                <div class="col-sm-6">
                    <?php if ($asset_ip) { ?>
                        <div class="itflow-glance-item" title="IP address">
                            <i class="fas fa-fw fa-globe"></i>
                            <span><span class="font-monospace"><?= $asset_ip ?></span><?php if ($asset_ip !== 'DHCP') { ?><button type="button" class="itflow-copy clipboardjs" title="Copy IP" data-clipboard-text="<?= $asset_ip ?>"><i class="far fa-copy"></i></button><?php } ?></span>
                        </div>
                    <?php } ?>
                    <?php if ($asset_mac) { ?>
                        <div class="itflow-glance-item" title="MAC address">
                            <i class="fas fa-fw fa-ethernet"></i>
                            <span><span class="font-monospace"><?= $asset_mac ?></span><button type="button" class="itflow-copy clipboardjs" title="Copy MAC" data-clipboard-text="<?= $asset_mac ?>"><i class="far fa-copy"></i></button></span>
                        </div>
                    <?php } ?>
                    <?php if ($asset_serial) { ?>
                        <div class="itflow-glance-item" title="Serial number">
                            <i class="fas fa-fw fa-barcode"></i>
                            <span><span class="font-monospace"><?= $asset_serial ?></span><button type="button" class="itflow-copy clipboardjs" title="Copy serial" data-clipboard-text="<?= $asset_serial ?>"><i class="far fa-copy"></i></button></span>
                        </div>
                    <?php } ?>
                    <?php if ($asset_warranty) { ?>
                        <div class="itflow-glance-item" title="Warranty <?= escapeHtml($asset_warranty['date']) ?>">
                            <i class="fas fa-fw fa-shield-alt"></i>
                            <span class="text-<?= $asset_warranty['tone'] ?>-emphasis">Warranty <?= strtolower(escapeHtml($asset_warranty['text'])) ?></span>
                        </div>
                    <?php } ?>
                </div>
            <?php } ?>
            <?php if ($has_right) { ?>
                <div class="col-sm-6">
                    <?php if ($can_edit || $asset_row['location_name']) { ?>
                        <div class="itflow-glance-item" title="Location">
                            <i class="fas fa-fw fa-map-marker-alt"></i>
                            <div class="flex-grow-1 min-w-0">
                                <?php if ($can_edit) { ?>
                                    <?= quickPick('asset_location', $asset_id, (string) intval($asset_row['asset_location_id']), assetQuickHtml('asset_location', $asset_row, 'detail'), 'detail', 0, $client_id) ?>
                                <?php } else { ?>
                                    <?= assetQuickHtml('asset_location', $asset_row, 'detail') ?>
                                <?php } ?>
                                <?php if ($asset_physical_location) { ?><div class="small text-body-secondary"><?= $asset_physical_location ?></div><?php } ?>
                            </div>
                        </div>
                    <?php } ?>
                    <?php if ($can_edit || $asset_row['contact_name']) { ?>
                        <div class="itflow-glance-item" title="Assigned to">
                            <i class="fas fa-fw fa-user"></i>
                            <div class="flex-grow-1 min-w-0">
                                <?php if ($can_edit) { ?>
                                    <?= quickPick('asset_contact', $asset_id, (string) $asset_contact_id, assetQuickHtml('asset_contact', $asset_row, 'detail'), 'detail', 0, $client_id) ?>
                                <?php } else { ?>
                                    <?= assetQuickHtml('asset_contact', $asset_row, 'detail') ?>
                                <?php } ?>
                            </div>
                        </div>
                    <?php } ?>
                    <?php if ($asset_rack) { ?>
                        <div class="itflow-glance-item" title="Rack">
                            <i class="fas fa-fw fa-server"></i>
                            <span><a href="rack.php?client_id=<?= $client_id ?>&rack_id=<?= intval($asset_rack['rack_id']) ?>"><?= escapeHtml($asset_rack['rack_name']) ?></a> <span class="text-body-secondary">&middot; <?= rackPositionLabel($asset_rack['unit_start_number'], $asset_rack['unit_end_number']) ?>, <?= $asset_rack['unit_face'] === 'rear' ? 'rear' : 'front' ?></span></span>
                        </div>
                    <?php } ?>
                </div>
            <?php } ?>
        </div>
    <?php } ?>

    <?php if ($can_edit || $asset_notes) { ?>
        <textarea class="form-control form-control-sm mt-3 itflow-asset-quicknote" rows="2" placeholder="Quick notes - saves when you click away" aria-label="Quick notes"
            data-asset-id="<?= $asset_id ?>" data-csrf="<?= escapeHtml($_SESSION['csrf_token'] ?? '') ?>" <?= $can_edit ? '' : 'readonly' ?>><?= $asset_notes ?></textarea>
    <?php } ?>

    <?php if ($quick_tabs) { ?>
        <!-- Everything linked to it -->
        <div class="itflow-asset-related itflow-asset-quick-related mt-3" data-asset-quick="<?= $asset_id ?>">
            <ul class="nav nav-pills itflow-asset-pills mb-2" role="tablist">
                <?php foreach ($quick_tabs as $tab_key => $tab) { ?>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link text-nowrap <?= $tab_key === $first_tab ? 'active' : '' ?>" data-bs-toggle="pill" data-bs-target="#asset-quick-<?= $asset_id ?>-<?= $tab_key ?>" data-asset-quick-tab="<?= $tab_key ?>" type="button" role="tab">
                            <?= $tab['label'] ?> <span class="badge rounded-pill ms-1"><?= $tab['count'] ?></span>
                            <?php if (!empty($tab['badge'])) { ?><span class="badge rounded-pill text-bg-danger" title="Open"><?= intval($tab['badge']) ?> open</span><?php } ?>
                        </button>
                    </li>
                <?php } ?>
            </ul>

            <div class="tab-content">

                <?php if ($interface_count) { ?>
                    <div class="tab-pane fade <?= $first_tab === 'interfaces' ? 'show active' : '' ?>" id="asset-quick-<?= $asset_id ?>-interfaces" role="tabpanel">
                        <div class="table-responsive">
                            <table class="table table-sm table-hover align-middle mb-0">
                                <thead class="text-nowrap"><tr><th>Port</th><th>IP</th><th>MAC</th><th>Connected to</th></tr></thead>
                                <tbody>
                                <?php while ($row = mysqli_fetch_assoc($sql_related_interfaces)) {
                                    $interface_ip = escapeHtml($row['interface_ip']);
                                    $interface_mac = escapeHtml($row['interface_mac']);
                                    $connected_asset_id = intval($row['connected_asset_id']); ?>
                                    <tr>
                                        <td class="text-nowrap">
                                            <?= escapeHtml($row['interface_name']) ?>
                                            <?php if (intval($row['interface_primary'])) { ?><span class="badge rounded-pill text-bg-primary fw-normal ms-1">Primary</span><?php } ?>
                                        </td>
                                        <td class="text-nowrap"><?php if ($interface_ip) { ?><span class="font-monospace"><?= $interface_ip ?></span><?php if ($interface_ip !== 'DHCP') { ?><button type="button" class="itflow-copy clipboardjs" title="Copy IP" data-clipboard-text="<?= $interface_ip ?>"><i class="far fa-copy"></i></button><?php } ?><?php } else { ?><span class="text-body-secondary">-</span><?php } ?></td>
                                        <td class="text-nowrap"><?php if ($interface_mac) { ?><span class="font-monospace"><?= $interface_mac ?></span><button type="button" class="itflow-copy clipboardjs" title="Copy MAC" data-clipboard-text="<?= $interface_mac ?>"><i class="far fa-copy"></i></button><?php } else { ?><span class="text-body-secondary">-</span><?php } ?></td>
                                        <td>
                                            <?php if ($connected_asset_id) { ?>
                                                <a class="ajax-modal" href="#" data-modal-size="lg" data-modal-url="modals/asset/asset.php?id=<?= $connected_asset_id ?>"><i class="fas fa-fw fa-<?= getAssetIcon($row['connected_asset_type']) ?> me-1"></i><?= escapeHtml($row['connected_asset_name']) ?></a>
                                                <span class="text-body-secondary">&middot; <?= escapeHtml($row['connected_interface_name']) ?></span>
                                            <?php } else { ?>
                                                <span class="text-body-secondary">-</span>
                                            <?php } ?>
                                        </td>
                                    </tr>
                                <?php } ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php } ?>

                <?php if ($credential_count) { ?>
                    <div class="tab-pane fade <?= $first_tab === 'credentials' ? 'show active' : '' ?>" id="asset-quick-<?= $asset_id ?>-credentials" role="tabpanel">
                        <div class="table-responsive">
                            <table class="table table-sm table-hover align-middle mb-0">
                                <thead class="text-nowrap"><tr><th>Name</th><th>Username</th><th>Password</th><th>OTP</th></tr></thead>
                                <tbody>
                                <?php while ($row = mysqli_fetch_assoc($sql_related_credentials)) {
                                    $credential_id = intval($row['credential_id']);
                                    $credential_uri = escapeHtml($row['credential_uri']);
                                    $credential_username = escapeHtml(decryptCredentialEntry($row['credential_username'])); ?>
                                    <tr>
                                        <td>
                                            <?= escapeHtml($row['credential_name']) ?>
                                            <?php if ($credential_uri) { ?><a class="ms-1 small" href="<?= escapeUrl($row['credential_uri']) ?>" target="_blank" rel="noopener" title="<?= $credential_uri ?>"><i class="fas fa-external-link-alt"></i></a><?php } ?>
                                        </td>
                                        <td class="text-nowrap"><?php if ($credential_username) { ?><?= $credential_username ?><button type="button" class="itflow-copy clipboardjs" title="Copy username" data-clipboard-text="<?= $credential_username ?>"><i class="far fa-copy"></i></button><?php } else { ?><span class="text-body-secondary">-</span><?php } ?></td>
                                        <td class="text-nowrap">
                                            <button class="btn btn-sm btn-light" aria-label="Show password" title="Show password" type="button" onclick="showPasswordViaCredentialID(this, <?= $credential_id ?>)"><i class="fas fa-ellipsis-h"></i><i class="fas fa-ellipsis-h"></i></button>
                                            <button type="button" class="itflow-copy" title="Copy password" onclick="copyPasswordViaCredentialID(this, <?= $credential_id ?>)"><i class="far fa-copy"></i></button>
                                        </td>
                                        <td class="text-nowrap">
                                            <?php if (!empty($row['credential_otp_secret'])) { ?>
                                                <span onmouseenter="showOTPViaCredentialID(<?= $credential_id ?>)"><i class="far fa-clock text-secondary me-1"></i><span id="otp_<?= $credential_id ?>"><i>Hover...</i></span></span>
                                            <?php } else { ?>
                                                <span class="text-body-secondary">-</span>
                                            <?php } ?>
                                        </td>
                                    </tr>
                                <?php } ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <?php /* js/, not ../js/ - this file is injected by js/ajax_modal.js, so a
                             relative src resolves against the host page's URL (/agent/...) */ ?>
                    <script src="js/credential_show_otp_via_id.js"></script>
                    <script src="js/credential_show_password_via_id.js"></script>
                <?php } ?>

                <?php if ($ticket_count) { ?>
                    <div class="tab-pane fade <?= $first_tab === 'tickets' ? 'show active' : '' ?>" id="asset-quick-<?= $asset_id ?>-tickets" role="tabpanel">
                        <div class="table-responsive">
                            <table class="table table-sm table-hover align-middle mb-0">
                                <tbody>
                                <?php foreach ($related_tickets as $row) {
                                    $ticket_id = intval($row['ticket_id']);
                                    $ticket_last = $row['ticket_updated_at'] ?: $row['ticket_created_at']; ?>
                                    <tr>
                                        <td class="text-nowrap"><a href="ticket.php?client_id=<?= $client_id ?>&ticket_id=<?= $ticket_id ?>"><?= escapeHtml($row['ticket_prefix']) . intval($row['ticket_number']) ?></a></td>
                                        <td class="w-100"><a class="text-body" href="ticket.php?client_id=<?= $client_id ?>&ticket_id=<?= $ticket_id ?>"><?= escapeHtml($row['ticket_subject']) ?></a></td>
                                        <td class="text-nowrap"><?= ticketStatusHtml($row['ticket_status_name'], $row['ticket_status_color'], 'detail') ?></td>
                                        <td class="text-nowrap"><?= formatAssignee($row['ticket_assigned_to'], $row['user_name'], $row['user_avatar'], $row['is_open']) ?></td>
                                        <td class="text-nowrap small text-body-secondary" title="<?= escapeHtml($ticket_last) ?>"><?= timeAgo($ticket_last) ?></td>
                                    </tr>
                                <?php } ?>
                                </tbody>
                            </table>
                        </div>
                        <?php if ($ticket_count > count($related_tickets)) { ?>
                            <div class="small mt-2"><a href="asset.php?client_id=<?= $client_id ?>&asset_id=<?= $asset_id ?>#tickets">All <?= $ticket_count ?> tickets on the asset page<i class="fas fa-arrow-right ms-1"></i></a></div>
                        <?php } ?>
                    </div>
                <?php } ?>

                <?php if ($recurring_ticket_count) { ?>
                    <div class="tab-pane fade <?= $first_tab === 'recurring' ? 'show active' : '' ?>" id="asset-quick-<?= $asset_id ?>-recurring" role="tabpanel">
                        <ul class="list-group list-group-flush">
                            <?php while ($row = mysqli_fetch_assoc($sql_related_recurring_tickets)) { ?>
                                <li class="list-group-item px-0 d-flex justify-content-between gap-3">
                                    <span><?= escapeHtml($row['recurring_ticket_subject']) ?></span>
                                    <span class="small text-body-secondary text-nowrap"><?= escapeHtml($row['recurring_ticket_frequency']) ?> &middot; next <?= escapeHtml($row['recurring_ticket_next_run']) ?></span>
                                </li>
                            <?php } ?>
                        </ul>
                    </div>
                <?php } ?>

                <?php if ($software_count) { ?>
                    <div class="tab-pane fade <?= $first_tab === 'licenses' ? 'show active' : '' ?>" id="asset-quick-<?= $asset_id ?>-licenses" role="tabpanel">
                        <ul class="list-group list-group-flush">
                            <?php while ($row = mysqli_fetch_assoc($sql_related_software)) {
                                $seats = intval($row['software_seats']); ?>
                                <li class="list-group-item px-0 d-flex justify-content-between gap-3">
                                    <span><?= escapeHtml($row['software_name']) ?> <span class="text-body-secondary"><?= escapeHtml($row['software_version']) ?></span></span>
                                    <span class="small text-nowrap <?= $seats && intval($row['seats_used']) > $seats ? 'text-danger-emphasis' : 'text-body-secondary' ?>"><?= intval($row['seats_used']) ?><?= $seats ? " / $seats" : '' ?> seats</span>
                                </li>
                            <?php } ?>
                        </ul>
                    </div>
                <?php } ?>

                <?php if ($document_count) { ?>
                    <div class="tab-pane fade <?= $first_tab === 'documents' ? 'show active' : '' ?>" id="asset-quick-<?= $asset_id ?>-documents" role="tabpanel">
                        <ul class="list-group list-group-flush">
                            <?php while ($row = mysqli_fetch_assoc($sql_related_documents)) {
                                $document_last = $row['document_updated_at'] ?: $row['document_created_at']; ?>
                                <li class="list-group-item px-0 d-flex justify-content-between gap-3">
                                    <a class="ajax-modal" href="#" data-modal-size="lg" data-modal-url="modals/document/document_view.php?id=<?= intval($row['document_id']) ?>"><i class="fas fa-fw fa-file-alt text-secondary me-2"></i><?= escapeHtml($row['document_name']) ?></a>
                                    <span class="small text-body-secondary text-nowrap" title="<?= escapeHtml($document_last) ?>"><?= timeAgo($document_last) ?></span>
                                </li>
                            <?php } ?>
                        </ul>
                    </div>
                <?php } ?>

                <?php if ($file_count) { ?>
                    <div class="tab-pane fade <?= $first_tab === 'files' ? 'show active' : '' ?>" id="asset-quick-<?= $asset_id ?>-files" role="tabpanel">
                        <ul class="list-group list-group-flush">
                            <?php while ($row = mysqli_fetch_assoc($sql_related_files)) { ?>
                                <li class="list-group-item px-0 d-flex justify-content-between gap-3">
                                    <a href="file.php?file_id=<?= intval($row['file_id']) ?>&action=view" target="_blank" rel="noopener"><i class="fas fa-fw fa-<?= getFileIcon((string) $row['file_ext']) ?> text-secondary me-2"></i><?= escapeHtml($row['file_name']) ?></a>
                                    <span class="small text-body-secondary text-nowrap" title="<?= escapeHtml($row['file_created_at']) ?>"><?= timeAgo($row['file_created_at']) ?></span>
                                </li>
                            <?php } ?>
                        </ul>
                    </div>
                <?php } ?>

                <?php if ($note_count) { ?>
                    <div class="tab-pane fade <?= $first_tab === 'notes' ? 'show active' : '' ?>" id="asset-quick-<?= $asset_id ?>-notes" role="tabpanel">
                        <ul class="list-group list-group-flush">
                            <?php while ($row = mysqli_fetch_assoc($sql_related_notes)) {
                                $note_icon = $note_type_icons[$row['asset_note_type']] ?? '' ?: 'fa-sticky-note'; ?>
                                <li class="list-group-item px-0 d-flex gap-3">
                                    <i class="fas fa-fw <?= $note_icon ?> text-secondary mt-1" title="<?= escapeHtml($row['asset_note_type']) ?>"></i>
                                    <div class="min-w-0">
                                        <div><?= nl2br(escapeHtml($row['asset_note'])) ?></div>
                                        <div class="small text-body-secondary"><?= escapeHtml($row['user_name']) ?> &middot; <span title="<?= escapeHtml($row['asset_note_created_at']) ?>"><?= timeAgo($row['asset_note_created_at']) ?></span></div>
                                    </div>
                                </li>
                            <?php } ?>
                        </ul>
                    </div>
                <?php } ?>

            </div>
        </div>
    <?php } ?>

</div>

<div class="modal-footer">
    <a href="asset.php?client_id=<?= $client_id ?>&asset_id=<?= $asset_id ?>" class="btn btn-primary text-bold"><i class="fas fa-arrow-right me-2"></i>Open asset</a>
    <?php if ($can_edit) { ?>
        <button type="button" class="btn btn-outline-secondary ajax-modal" data-modal-size="lg" data-modal-url="modals/asset/asset_edit.php?id=<?= $asset_id ?>"><i class="fas fa-edit me-2"></i>Edit</button>
    <?php } ?>
    <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fa fa-times me-2"></i>Close</button>
</div>

<script>
    (function () {
        // Quick notes save when you click away. Scoped to this modal's own
        // textarea - an id here would collide with the asset page's own
        // Quick notes, which is still on the page under the modal
        document.querySelectorAll('.itflow-asset-quicknote:not([data-wired])').forEach(function (notes) {
            notes.dataset.wired = '1';
            var saved = notes.value;
            notes.addEventListener('blur', function () {
                if (notes.readOnly || notes.value === saved) {
                    return;
                }
                saved = notes.value;
                itflowPost('ajax.php', {
                    asset_set_notes: 'TRUE',
                    csrf_token: notes.dataset.csrf,
                    asset_id: notes.dataset.assetId,
                    notes: notes.value
                });
            });
        });

        // Open on the tab used last time, if this asset has one
        var key = 'itflow.assetQuickTab';
        document.querySelectorAll('[data-asset-quick]:not([data-wired])').forEach(function (box) {
            box.dataset.wired = '1';
            var remembered = null;
            try {
                remembered = localStorage.getItem(key);
            } catch (e) {}
            var tab = remembered && box.querySelector('[data-asset-quick-tab="' + remembered + '"]');
            if (tab && !tab.classList.contains('active')) {
                bootstrap.Tab.getOrCreateInstance(tab).show();
            }
            box.addEventListener('shown.bs.tab', function (e) {
                try {
                    localStorage.setItem(key, e.target.dataset.assetQuickTab);
                } catch (err) {}
            });
        });
    })();
</script>

<?php
require_once '../../../includes/modal_footer.php';
