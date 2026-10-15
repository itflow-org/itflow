<?php

/*
 * Contact - one person at a client.
 *
 * Built like the asset page (agent/asset.php): who they are in the header,
 * with a click to call or email them; how to reach them and the details a
 * tech needs on a call (location, PIN, portal access) down the side; and
 * everything linked to them - assets, credentials, tickets, licenses,
 * documents, files, services, notes - in tabs with counts. The open tab
 * follows the URL hash (#tickets) and is remembered between visits.
 */

// If client_id is in URI then show client Side Bar and client header
if (isset($_GET['client_id'])) {
    require_once "includes/inc_all_client.php";
    $client_query = "AND contact_client_id = $client_id";
    $client_url = "client_id=$client_id&";
} else {
    require_once "includes/inc_client_overview_all.php";
    $client_query = '';
    $client_url = '';
}

// Perms
enforceUserPermission('module_client');

$contact_id = intval($_GET['contact_id'] ?? 0);

$sql = mysqli_query($mysqli, "SELECT client_id, client_name, contact_archived_at, contact_billing, contact_client_id, contact_created_at,
    contact_department, contact_email, contact_extension, contact_id, contact_important, contact_location_id, contact_mobile,
    contact_mobile_country_code, contact_name, contact_notes, contact_phone, contact_phone_country_code, contact_photo, contact_pin,
    contact_primary, contact_technical, contact_title, contact_updated_at, contact_user_id,
    location_archived_at, location_city, location_name, location_state, user_auth_method
    FROM contacts
    LEFT JOIN clients ON client_id = contact_client_id
    LEFT JOIN locations ON location_id = contact_location_id
    LEFT JOIN users ON user_id = contact_user_id
    WHERE contact_id = $contact_id
    " . clientScopeSql('contact_client_id') . "
    $client_query
    LIMIT 1
");

if (mysqli_num_rows($sql) == 0) {
    echo "<center><h1 class='text-secondary mt-5'>Nothing to see here</h1><a class='btn btn-lg btn-secondary mt-3' href='javascript:history.back()'><i class='fa fa-fw fa-arrow-left'></i> Go Back</a></center>";
    require_once "../includes/footer.php";
    exit();
}

$contact_row = mysqli_fetch_assoc($sql); // raw, for the helpers - $row gets reused below
$row = $contact_row;
$client_id = intval($row['client_id']);
$client_name = escapeHtml($row['client_name']);
$contact_name = escapeHtml($row['contact_name']);
$contact_title = escapeHtml($row['contact_title']);
$contact_department = escapeHtml($row['contact_department']);
$contact_email = escapeHtml($row['contact_email']);
$contact_pin = escapeHtml($row['contact_pin']);
$contact_notes = escapeHtml($row['contact_notes']);
$contact_primary = intval($row['contact_primary']);
$contact_archived_at = $row['contact_archived_at'];
$contact_created_at = escapeHtml($row['contact_created_at']);
$contact_phone_link = contactPhoneLink($row['contact_phone'], $row['contact_phone_country_code'], $row['contact_extension']);
$contact_mobile_link = contactPhoneLink($row['contact_mobile'], $row['contact_mobile_country_code']);
$contact_phone_copy = escapeHtml(formatPhoneNumber($row['contact_phone'], $row['contact_phone_country_code']));
$contact_mobile_copy = escapeHtml(formatPhoneNumber($row['contact_mobile'], $row['contact_mobile_country_code']));
$contact_dial = $row['contact_phone'] ? 'phone' : ($row['contact_mobile'] ? 'mobile' : '');
$contact_portal = contactPortalAccess($row['contact_user_id'], $row['user_auth_method']);
$contact_role_line = implode(' &middot; ', array_filter([$contact_title, $contact_department]));

$can_edit = lookupUserPermission('module_client') >= 2;
$can_delete = lookupUserPermission('module_client') >= 3;
$can_credentials = lookupUserPermission('module_credential') >= 1;
$can_credentials_edit = lookupUserPermission('module_credential') >= 2;
$can_support = lookupUserPermission('module_support') >= 1;
$can_support_edit = lookupUserPermission('module_support') >= 2;

// Override Tab Title // No Sanitizing needed as this var will only be used in the tab title
$page_title = $row['contact_name'];

// Tags - functions/tags.php
$contact_tags_display = tagPills('contact', $contact_id, tagsForRecord('contact', $contact_id), "contacts.php?client_id=$client_id&");

// Assets assigned to them
$sql_related_assets = mysqli_query($mysqli, "SELECT asset_id, asset_name, asset_description, asset_type, asset_make, asset_model, asset_serial,
        asset_status, asset_favorite, interface_ip
    FROM assets
    LEFT JOIN asset_interfaces ON interface_asset_id = asset_id AND interface_primary = 1
    WHERE asset_contact_id = $contact_id
      AND asset_archived_at IS NULL
    ORDER BY asset_name ASC"
);
$asset_count = mysqli_num_rows($sql_related_assets);

// Credentials - only for agents who may see them
$related_credentials = $can_credentials ? credentialRows("credential_contact_id = $contact_id AND credential_client_id = $client_id") : [];
$credential_count = count($related_credentials);

// Tickets they raised or are the contact on - open ones first
$related_tickets = [];
$ticket_open_count = 0;
if ($can_support) {
    $related_tickets = ticketRows("tickets.ticket_contact_id = $contact_id");
    $ticket_open_count = count(array_filter(array_column($related_tickets, 'is_open')));
}
$ticket_count = count($related_tickets);

// Recurring tickets
$recurring_ticket_count = 0;
if ($can_support) {
    $sql_related_recurring_tickets = mysqli_query($mysqli, "SELECT recurring_ticket_id, recurring_ticket_subject, recurring_ticket_priority,
            recurring_ticket_frequency, recurring_ticket_next_run, recurring_ticket_paused_at
        FROM recurring_tickets
        WHERE recurring_ticket_contact_id = $contact_id
        ORDER BY recurring_ticket_next_run ASC"
    );
    $recurring_ticket_count = mysqli_num_rows($sql_related_recurring_tickets);
}

// Licenses they hold a seat on, with each one's seats in use and the key they were given
$held_software = $can_support ? softwareHeldBy('contact', $contact_id) : [];
$software_count = count($held_software);

// Documents
$sql_linked_documents = mysqli_query($mysqli, "SELECT documents.document_id, document_name, document_description, document_created_at,
        document_updated_at, user_name
    FROM contact_documents
    JOIN documents ON documents.document_id = contact_documents.document_id
    LEFT JOIN users ON document_created_by = user_id
    WHERE contact_documents.contact_id = $contact_id
      AND document_archived_at IS NULL
    ORDER BY document_name ASC"
);
$document_count = mysqli_num_rows($sql_linked_documents);

// Files
$sql_linked_files = mysqli_query($mysqli, "SELECT files.file_id, file_name, file_description, file_ext, file_size, file_created_at
    FROM contact_files
    JOIN files ON files.file_id = contact_files.file_id
    WHERE contact_files.contact_id = $contact_id
      AND file_archived_at IS NULL
    ORDER BY file_name ASC"
);
$file_count = mysqli_num_rows($sql_linked_files);

// Services
$sql_linked_services = mysqli_query($mysqli, "SELECT services.service_id, service_name, service_description, service_category, service_importance
    FROM service_contacts
    JOIN services ON services.service_id = service_contacts.service_id
    WHERE service_contacts.contact_id = $contact_id
    ORDER BY service_name ASC"
);
$service_count = mysqli_num_rows($sql_linked_services);

// Notes
$sql_related_notes = mysqli_query($mysqli, "SELECT contact_note, contact_note_created_at, contact_note_id, contact_note_type, user_name
    FROM contact_notes
    LEFT JOIN users ON contact_note_created_by = user_id
    WHERE contact_note_contact_id = $contact_id
      AND contact_note_archived_at IS NULL
    ORDER BY contact_note_created_at DESC"
);
$note_count = mysqli_num_rows($sql_related_notes);

// Note type icons, read from the categories list so the seeded icons stay the single source of truth
$note_type_icons = [];
$sql_note_type_icons = mysqli_query($mysqli, "SELECT category_name, category_icon FROM categories WHERE category_type = 'contact_note_type'");
while ($row = mysqli_fetch_assoc($sql_note_type_icons)) {
    $note_type_icons[escapeHtml($row['category_name'])] = escapeHtml($row['category_icon']);
}

// The tabs, in the order a tech reaches for them
$contact_tabs = [
    'assets' => ['label' => 'Assets', 'icon' => 'fa-desktop', 'count' => $asset_count],
    'credentials' => ['label' => 'Credentials', 'icon' => 'fa-key', 'count' => $credential_count],
    'tickets' => ['label' => 'Tickets', 'icon' => 'fa-life-ring', 'count' => $ticket_count, 'badge' => $ticket_open_count],
    'recurring' => ['label' => 'Recurring', 'icon' => 'fa-redo-alt', 'count' => $recurring_ticket_count],
    'licenses' => ['label' => 'Licenses', 'icon' => 'fa-cube', 'count' => $software_count],
    'documents' => ['label' => 'Documents', 'icon' => 'fa-file-alt', 'count' => $document_count],
    'files' => ['label' => 'Files', 'icon' => 'fa-paperclip', 'count' => $file_count],
    'services' => ['label' => 'Services', 'icon' => 'fa-stream', 'count' => $service_count],
    'notes' => ['label' => 'Notes', 'icon' => 'fa-sticky-note', 'count' => $note_count],
];
if (!$can_credentials) {
    unset($contact_tabs['credentials']);
}
if (!$can_support) {
    unset($contact_tabs['tickets'], $contact_tabs['recurring'], $contact_tabs['licenses']);
}

?>

<ol class="breadcrumb d-print-none">
    <li class="breadcrumb-item">
        <a href="client_overview.php?client_id=<?= $client_id ?>"><?= $client_name ?></a>
    </li>
    <li class="breadcrumb-item">
        <a href="contacts.php?client_id=<?= $client_id ?>">Contacts</a>
    </li>
    <li class="breadcrumb-item active"><?= $contact_name ?></li>
</ol>

<!-- Who they are -->
<div class="card mb-3">
    <div class="card-body d-flex flex-wrap align-items-center gap-3">
        <?php if ($contact_row['contact_photo']) { ?>
            <a href="<?= "../uploads/clients/$client_id/" . escapeHtml($contact_row['contact_photo']) ?>" target="_blank" rel="noopener" title="Open photo">
                <?= contactAvatar($client_id, $contact_id, $contact_row['contact_name'], $contact_row['contact_photo'], 'lg') ?>
            </a>
        <?php } else { ?>
            <?= contactAvatar($client_id, $contact_id, $contact_row['contact_name'], '', 'lg') ?>
        <?php } ?>

        <div class="flex-grow-1 min-w-0">
            <h1 class="h4 fw-semibold mb-1 d-flex flex-wrap align-items-center gap-2">
                <span><?= $contact_name ?></span>
                <span class="fs-6 fw-normal d-inline-flex flex-wrap gap-1"><?= contactFlagPills($contact_row) ?></span>
                <?php if ($contact_archived_at) { ?><span class="badge rounded-pill text-bg-secondary fw-normal fs-6">Archived</span><?php } ?>
            </h1>
            <?php if ($contact_role_line) { ?>
                <div class="text-body-secondary"><?= $contact_role_line ?></div>
            <?php } ?>
            <?php if ($contact_tags_display) { ?>
                <div class="mt-2"><?= $contact_tags_display ?></div>
            <?php } ?>
        </div>

        <div class="d-flex flex-wrap gap-2 d-print-none">
            <?php if ($contact_dial === 'phone') { ?>
                <a class="btn btn-outline-primary" href="tel:<?= escapeHtml(preg_replace('/[^0-9+]/', '', ($contact_row['contact_phone_country_code'] ? '+' . $contact_row['contact_phone_country_code'] : '') . $contact_row['contact_phone']) . ($contact_row['contact_extension'] ? ',' . preg_replace('/[^0-9]/', '', $contact_row['contact_extension']) : '')) ?>" title="Call <?= $contact_phone_copy ?>">
                    <i class="fas fa-phone me-2"></i>Call
                </a>
            <?php } elseif ($contact_dial === 'mobile') { ?>
                <a class="btn btn-outline-primary" href="tel:<?= escapeHtml(preg_replace('/[^0-9+]/', '', ($contact_row['contact_mobile_country_code'] ? '+' . $contact_row['contact_mobile_country_code'] : '') . $contact_row['contact_mobile'])) ?>" title="Call <?= $contact_mobile_copy ?>">
                    <i class="fas fa-mobile-alt me-2"></i>Call
                </a>
            <?php } ?>
            <?php if ($contact_email) { ?>
                <a class="btn btn-outline-primary" href="mailto:<?= $contact_email ?>" title="Email <?= $contact_email ?>">
                    <i class="far fa-envelope me-2"></i>Email
                </a>
            <?php } ?>
            <?php if ($can_edit) { ?>
                <div class="dropdown">
                    <button type="button" class="btn btn-primary dropdown-toggle" data-bs-toggle="dropdown"><i class="fas fa-plus me-2"></i>New</button>
                    <div class="dropdown-menu dropdown-menu-end">
                        <?php if ($can_support_edit) { ?>
                            <a class="dropdown-item ajax-modal" href="#" data-modal-url="modals/ticket/ticket_add.php?<?= $client_url ?>&contact_id=<?= $contact_id ?>" data-modal-size="lg">
                                <i class="fa fa-fw fa-life-ring text-secondary me-2"></i>Ticket
                            </a>
                            <a class="dropdown-item ajax-modal" href="#" data-modal-url="modals/recurring_ticket/recurring_ticket_add.php?<?= $client_url ?>&contact_id=<?= $contact_id ?>" data-modal-size="lg">
                                <i class="fa fa-fw fa-redo-alt text-secondary me-2"></i>Recurring Ticket
                            </a>
                            <a class="dropdown-item ajax-modal" href="#" data-modal-size="lg" data-modal-url="modals/asset/asset_add.php?<?= $client_url ?>&contact_id=<?= $contact_id ?>">
                                <i class="fa fa-fw fa-desktop text-secondary me-2"></i>Asset
                            </a>
                        <?php } ?>
                        <?php if ($can_credentials_edit) { ?>
                            <a class="dropdown-item ajax-modal" href="#" data-modal-size="lg" data-modal-url="modals/credential/credential_add.php?<?= $client_url ?>&contact_id=<?= $contact_id ?>">
                                <i class="fa fa-fw fa-key text-secondary me-2"></i>Credential
                            </a>
                        <?php } ?>
                        <?php if ($can_support_edit) { ?>
                            <a class="dropdown-item ajax-modal" href="#" data-modal-url="modals/document/document_add.php?<?= $client_url ?>&contact_id=<?= $contact_id ?>" data-modal-size="xl">
                                <i class="fa fa-fw fa-file-alt text-secondary me-2"></i>Document
                            </a>
                            <a class="dropdown-item ajax-modal" href="#" data-modal-url="modals/file/file_upload.php?<?= $client_url ?>&contact_id=<?= $contact_id ?>">
                                <i class="fa fa-fw fa-upload text-secondary me-2"></i>Upload File(s)
                            </a>
                            <a class="dropdown-item ajax-modal" href="#" data-modal-size="lg" data-modal-url="modals/software/software_add.php?client_id=<?= $client_id ?>&contact_id=<?= $contact_id ?>">
                                <i class="fa fa-fw fa-cube text-secondary me-2"></i>License
                            </a>
                        <?php } ?>
                        <a class="dropdown-item ajax-modal" href="#" data-modal-url="modals/contact/contact_note_add.php?id=<?= $contact_id ?>">
                            <i class="fas fa-fw fa-sticky-note text-secondary me-2"></i>Note
                        </a>
                    </div>
                </div>
                <div class="dropdown">
                    <button type="button" class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown"><i class="fas fa-link me-2"></i>Link</button>
                    <div class="dropdown-menu dropdown-menu-end">
                        <a class="dropdown-item ajax-modal" href="#" data-modal-url="modals/contact/contact_link_asset.php?id=<?= $contact_id ?>">
                            <i class="fa fa-fw fa-desktop text-secondary me-2"></i>Asset
                        </a>
                        <?php if ($can_credentials_edit) { ?>
                            <a class="dropdown-item ajax-modal" href="#" data-modal-url="modals/contact/contact_link_credential.php?id=<?= $contact_id ?>">
                                <i class="fa fa-fw fa-key text-secondary me-2"></i>Credential
                            </a>
                        <?php } ?>
                        <?php if ($can_support_edit) { ?>
                            <a class="dropdown-item ajax-modal" href="#" data-modal-url="modals/link/link.php?from=contact&id=<?= $contact_id ?>&to=software">
                                <i class="fa fa-fw fa-cube text-secondary me-2"></i>License
                            </a>
                        <?php } ?>
                        <a class="dropdown-item ajax-modal" href="#" data-modal-url="modals/contact/contact_link_service.php?id=<?= $contact_id ?>">
                            <i class="fa fa-fw fa-stream text-secondary me-2"></i>Service
                        </a>
                        <a class="dropdown-item ajax-modal" href="#" data-modal-url="modals/contact/contact_link_document.php?id=<?= $contact_id ?>">
                            <i class="fa fa-fw fa-file-alt text-secondary me-2"></i>Document
                        </a>
                        <a class="dropdown-item ajax-modal" href="#" data-modal-url="modals/contact/contact_link_file.php?id=<?= $contact_id ?>">
                            <i class="fa fa-fw fa-paperclip text-secondary me-2"></i>File
                        </a>
                    </div>
                </div>
                <button type="button" class="btn btn-outline-secondary ajax-modal" data-modal-size="lg" data-modal-url="modals/contact/contact_edit.php?id=<?= $contact_id ?>">
                    <i class="fas fa-user-edit me-2"></i>Edit
                </button>
                <?php if (!$contact_primary) { ?>
                    <div class="dropdown dropstart">
                        <button class="btn btn-outline-secondary" title="More actions" type="button" data-bs-toggle="dropdown">
                            <i class="fas fa-fw fa-ellipsis-v"></i>
                        </button>
                        <div class="dropdown-menu">
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
                                <div class="dropdown-divider"></div>
                                <a class="dropdown-item text-danger text-bold confirm-link" href="post.php?delete_contact=<?= $contact_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>">
                                    <i class="fas fa-fw fa-trash me-2"></i>Delete
                                </a>
                            <?php } ?>
                        </div>
                    </div>
                <?php } ?>
            <?php } ?>
        </div>
    </div>
</div>

<div class="row g-3">

    <!-- How to reach them, and what a tech needs on a call -->
    <div class="col-xl-4 col-lg-5">

        <div class="card mb-3">
            <div class="card-header">
                <h3 class="card-title mb-0"><i class="fas fa-fw fa-phone-alt text-secondary me-2"></i>Reach</h3>
            </div>
            <div class="card-body">
                <?php if ($contact_phone_link || $contact_mobile_link || $contact_email) { ?>
                    <dl class="itflow-asset-dl mb-0">
                        <?php if ($contact_phone_link) { ?>
                            <dt>Phone</dt>
                            <dd><?= $contact_phone_link ?><button type="button" class="itflow-copy clipboardjs" title="Copy phone" data-clipboard-text="<?= $contact_phone_copy ?>"><i class="far fa-copy"></i></button></dd>
                        <?php } ?>
                        <?php if ($contact_mobile_link) { ?>
                            <dt>Mobile</dt>
                            <dd><?= $contact_mobile_link ?><button type="button" class="itflow-copy clipboardjs" title="Copy mobile" data-clipboard-text="<?= $contact_mobile_copy ?>"><i class="far fa-copy"></i></button></dd>
                        <?php } ?>
                        <?php if ($contact_email) { ?>
                            <dt>Email</dt>
                            <dd class="d-flex align-items-center min-w-0"><a class="text-truncate" href="mailto:<?= $contact_email ?>" title="<?= $contact_email ?>"><?= $contact_email ?></a><button type="button" class="itflow-copy clipboardjs" title="Copy email" data-clipboard-text="<?= $contact_email ?>"><i class="far fa-copy"></i></button></dd>
                        <?php } ?>
                    </dl>
                <?php } else { ?>
                    <span class="text-body-secondary">No phone or email recorded.</span>
                <?php } ?>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header">
                <h3 class="card-title mb-0"><i class="fas fa-fw fa-id-card text-secondary me-2"></i>Details</h3>
            </div>
            <div class="card-body">
                <dl class="itflow-asset-dl mb-0">
                    <dt>Location</dt>
                    <dd>
                        <?php if ($can_edit && !$contact_archived_at) { ?>
                            <?= quickPick('contact_location', $contact_id, (string) intval($contact_row['contact_location_id']), contactLocationHtml($contact_row, 'detail'), 'detail', 0, $client_id) ?>
                        <?php } else { ?>
                            <?= contactLocationHtml($contact_row, 'detail') ?>
                        <?php } ?>
                    </dd>
                    <?php if ($contact_pin) { ?>
                        <dt>PIN</dt>
                        <dd><span class="font-monospace"><?= $contact_pin ?></span> <span class="small text-body-secondary">to verify them on a call</span></dd>
                    <?php } ?>
                    <dt>Portal</dt>
                    <dd><?= $contact_portal ? "Can log in &middot; <span class='text-body-secondary'>$contact_portal</span>" : '<span class="text-body-secondary">No access</span>' ?></dd>
                    <dt>Added</dt>
                    <dd title="<?= $contact_created_at ?>"><?= timeAgo($contact_created_at) ?></dd>
                </dl>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header">
                <h3 class="card-title mb-0"><i class="fas fa-fw fa-pen text-secondary me-2"></i>Quick notes</h3>
            </div>
            <textarea class="form-control border-0 rounded-0 rounded-bottom" rows="5" id="contactNotes" placeholder="Personal tidbits, how they like to be contacted - saves when you click away" onblur="updateContactNotes(<?= $contact_id ?>)" <?= $can_edit ? '' : 'readonly' ?>><?= $contact_notes ?></textarea>
        </div>

    </div>

    <!-- Everything linked to them -->
    <div class="col-xl-8 col-lg-7">
        <div class="card mb-3 itflow-asset-related">
            <div class="card-header py-2">
                <ul class="nav nav-pills itflow-asset-pills" role="tablist">
                    <?php foreach ($contact_tabs as $tab_key => $tab) { ?>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link text-nowrap <?= $tab['count'] ? '' : 'is-empty' ?>" id="contact-tab-<?= $tab_key ?>" data-bs-toggle="pill" data-bs-target="#contact-pane-<?= $tab_key ?>" data-contact-tab="<?= $tab_key ?>" type="button" role="tab">
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

                <!-- Assets -->
                <div class="tab-pane fade" id="contact-pane-assets" role="tabpanel">
                    <div class="itflow-asset-pane-bar">
                        <span class="text-body-secondary small">Assets assigned to <?= $contact_name ?>.</span>
                        <?php if ($can_edit) { ?>
                            <div class="d-flex gap-2">
                                <button type="button" class="btn btn-sm btn-outline-secondary ajax-modal" data-modal-url="modals/contact/contact_link_asset.php?id=<?= $contact_id ?>">
                                    <i class="fas fa-link me-2"></i>Link
                                </button>
                                <?php if ($can_support_edit) { ?>
                                    <button type="button" class="btn btn-sm btn-primary ajax-modal" data-modal-size="lg" data-modal-url="modals/asset/asset_add.php?<?= $client_url ?>&contact_id=<?= $contact_id ?>">
                                        <i class="fas fa-plus me-2"></i>New Asset
                                    </button>
                                <?php } ?>
                            </div>
                        <?php } ?>
                    </div>
                    <?php if ($asset_count) { ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="text-nowrap table-light">
                                <tr><th>Asset</th><th>Model / Serial</th><th>IP</th><th>Status</th><th></th></tr>
                                </thead>
                                <tbody>
                                <?php while ($row = mysqli_fetch_assoc($sql_related_assets)) {
                                    $asset_id = intval($row['asset_id']);
                                    $asset_serial = escapeHtml($row['asset_serial']);
                                    $asset_ip = escapeHtml($row['interface_ip']);
                                    $asset_make_model = escapeHtml(trim($row['asset_make'] . ' ' . $row['asset_model'])); ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center gap-2">
                                                <span class="itflow-asset-icon itflow-asset-icon-sm itflow-asset-tone-<?= assetTypeTone($row['asset_type']) ?>" aria-hidden="true"><i class="fas fa-fw fa-<?= getAssetIcon($row['asset_type']) ?>"></i></span>
                                                <div class="min-w-0">
                                                    <a class="fw-semibold text-body ajax-modal" href="#" data-modal-size="lg" data-modal-url="modals/asset/asset.php?id=<?= $asset_id ?>"><?= escapeHtml($row['asset_name']) ?></a>
                                                    <?php if ($row['asset_favorite']) { ?><i class="fas fa-star text-warning ms-1" title="Favorite"></i><?php } ?>
                                                    <div class="small text-body-secondary"><?= escapeHtml($row['asset_type']) ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div><?= $asset_make_model ?: '<span class="text-body-secondary">-</span>' ?></div>
                                            <?php if ($asset_serial) { ?><div class="small text-body-secondary text-nowrap"><span class="font-monospace"><?= $asset_serial ?></span><button type="button" class="itflow-copy clipboardjs" title="Copy serial" data-clipboard-text="<?= $asset_serial ?>"><i class="far fa-copy"></i></button></div><?php } ?>
                                        </td>
                                        <td class="text-nowrap">
                                            <?php if ($asset_ip) { ?><span class="font-monospace"><?= $asset_ip ?></span><?php if ($asset_ip !== 'DHCP') { ?><button type="button" class="itflow-copy clipboardjs" title="Copy IP" data-clipboard-text="<?= $asset_ip ?>"><i class="far fa-copy"></i></button><?php } ?><?php } else { ?><span class="text-body-secondary">-</span><?php } ?>
                                        </td>
                                        <td><?= assetStatusPill($row['asset_status']) ?: '<span class="text-body-secondary">-</span>' ?></td>
                                        <td class="text-end">
                                            <div class="dropdown dropstart">
                                                <button class="btn btn-sm btn-light" title="More actions" type="button" data-bs-toggle="dropdown"><i class="fas fa-fw fa-ellipsis-v"></i></button>
                                                <div class="dropdown-menu">
                                                    <a class="dropdown-item" href="asset.php?client_id=<?= $client_id ?>&asset_id=<?= $asset_id ?>">
                                                        <i class="fas fa-fw fa-arrow-right text-secondary me-2"></i>Open
                                                    </a>
                                                    <?php if ($can_support_edit) { ?>
                                                        <a class="dropdown-item ajax-modal" href="#" data-modal-size="lg" data-modal-url="modals/asset/asset_edit.php?id=<?= $asset_id ?>">
                                                            <i class="fas fa-fw fa-edit text-secondary me-2"></i>Edit
                                                        </a>
                                                    <?php } ?>
                                                    <?php if ($can_edit) { ?>
                                                        <div class="dropdown-divider"></div>
                                                        <a class="dropdown-item" href="post.php?unlink_asset_from_contact&contact_id=<?= $contact_id ?>&asset_id=<?= $asset_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>">
                                                            <i class="fas fa-fw fa-unlink text-secondary me-2"></i>Unlink
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
                        <div class="itflow-asset-empty">No assets assigned.</div>
                    <?php } ?>
                </div>

                <?php if ($can_credentials) { ?>
                <!-- Credentials -->
                <div class="tab-pane fade" id="contact-pane-credentials" role="tabpanel">
                    <div class="itflow-asset-pane-bar">
                        <span class="text-body-secondary small">Their logins. Passwords and codes are fetched only when you ask for them.</span>
                        <?php if ($can_edit && $can_credentials_edit) { ?>
                            <div class="d-flex gap-2">
                                <button type="button" class="btn btn-sm btn-outline-secondary ajax-modal" data-modal-url="modals/contact/contact_link_credential.php?id=<?= $contact_id ?>">
                                    <i class="fas fa-link me-2"></i>Link
                                </button>
                                <button type="button" class="btn btn-sm btn-primary ajax-modal" data-modal-size="lg" data-modal-url="modals/credential/credential_add.php?<?= $client_url ?>&contact_id=<?= $contact_id ?>">
                                    <i class="fas fa-plus me-2"></i>New Credential
                                </button>
                            </div>
                        <?php } ?>
                    </div>
                    <?php if ($related_credentials) { ?>
                        <?= credentialTableHtml($related_credentials, [
                            'unlink' => function ($row) use ($contact_id, $can_edit) {
                                return $can_edit && credentialCan(2) ? "post.php?unlink_credential_from_contact&contact_id=$contact_id&credential_id=" . intval($row['credential_id']) . "&csrf_token=" . $_SESSION['csrf_token'] : '';
                            },
                        ]) ?>
                    <?php } else { ?>
                        <div class="itflow-asset-empty">No credentials for <?= $contact_name ?>.</div>
                    <?php } ?>
                </div>
                <?php } ?>

                <?php if ($can_support) { ?>
                <!-- Tickets -->
                <div class="tab-pane fade" id="contact-pane-tickets" role="tabpanel">
                    <div class="itflow-asset-pane-bar">
                        <span class="text-body-secondary small">Tickets they are the contact on. Open ones first.</span>
                        <?php if ($can_support_edit) { ?>
                            <button type="button" class="btn btn-sm btn-primary ajax-modal" data-modal-size="lg" data-modal-url="modals/ticket/ticket_add.php?<?= $client_url ?>&contact_id=<?= $contact_id ?>">
                                <i class="fas fa-plus me-2"></i>New Ticket
                            </button>
                        <?php } ?>
                    </div>
                    <?php if ($related_tickets) { ?>
                        <?= ticketTableHtml($related_tickets) ?>
                    <?php } else { ?>
                        <div class="itflow-asset-empty">No tickets yet.</div>
                    <?php } ?>
                </div>

                <!-- Recurring tickets -->
                <div class="tab-pane fade" id="contact-pane-recurring" role="tabpanel">
                    <div class="itflow-asset-pane-bar">
                        <span class="text-body-secondary small">Tickets that open on a schedule with them as the contact.</span>
                        <?php if ($can_support_edit) { ?>
                            <button type="button" class="btn btn-sm btn-primary ajax-modal" data-modal-size="lg" data-modal-url="modals/recurring_ticket/recurring_ticket_add.php?<?= $client_url ?>&contact_id=<?= $contact_id ?>">
                                <i class="fas fa-plus me-2"></i>New Recurring Ticket
                            </button>
                        <?php } ?>
                    </div>
                    <?php if ($recurring_ticket_count) { ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="text-nowrap table-light">
                                <tr><th>Subject</th><th>Priority</th><th>Frequency</th><th>Next run</th><th></th></tr>
                                </thead>
                                <tbody>
                                <?php while ($row = mysqli_fetch_assoc($sql_related_recurring_tickets)) {
                                    $recurring_ticket_id = intval($row['recurring_ticket_id']); ?>
                                    <tr>
                                        <td>
                                            <?php if ($can_support_edit) { ?>
                                                <a class="text-body fw-semibold ajax-modal" href="#" data-modal-size="lg" data-modal-url="modals/recurring_ticket/recurring_ticket_edit.php?id=<?= $recurring_ticket_id ?>"><?= escapeHtml($row['recurring_ticket_subject']) ?></a>
                                            <?php } else { ?>
                                                <?= escapeHtml($row['recurring_ticket_subject']) ?>
                                            <?php } ?>
                                        </td>
                                        <td><?= ticketPriorityHtml($row['recurring_ticket_priority'], 'detail') ?></td>
                                        <td><?= escapeHtml($row['recurring_ticket_frequency']) ?></td>
                                        <td class="text-nowrap"><?= recurringTicketNextRunPill($row) ?> <span class="small text-body-secondary ms-1"><?= escapeHtml($row['recurring_ticket_next_run']) ?></span></td>
                                        <td class="text-end">
                                            <?php if ($can_support_edit) { ?>
                                                <div class="dropdown dropstart">
                                                    <button class="btn btn-sm btn-light" title="More actions" type="button" data-bs-toggle="dropdown"><i class="fas fa-fw fa-ellipsis-v"></i></button>
                                                    <div class="dropdown-menu">
                                                        <a class="dropdown-item ajax-modal" href="#" data-modal-size="lg" data-modal-url="modals/recurring_ticket/recurring_ticket_edit.php?id=<?= $recurring_ticket_id ?>">
                                                            <i class="fas fa-fw fa-edit text-secondary me-2"></i>Edit
                                                        </a>
                                                        <a class="dropdown-item confirm-link" href="post.php?force_recurring_ticket=<?= $recurring_ticket_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>"
                                                            data-confirm-text="A ticket is raised now, and the next run moves on a step - this takes its place.">
                                                            <i class="fas fa-fw fa-paper-plane text-secondary me-2"></i>Raise now
                                                        </a>
                                                        <?php if (lookupUserPermission('module_support') >= 3) { ?>
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
                        <div class="itflow-asset-empty">No recurring tickets.</div>
                    <?php } ?>
                </div>
                <?php } ?>

                <?php if ($can_support) { ?>
                <!-- Licenses -->
                <div class="tab-pane fade" id="contact-pane-licenses" role="tabpanel">
                    <div class="itflow-asset-pane-bar">
                        <span class="text-body-secondary small">Licenses they hold a seat on, and the key they were given.</span>
                        <?php if ($can_support_edit) { ?>
                            <div class="d-flex gap-2">
                                <button type="button" class="btn btn-sm btn-outline-secondary ajax-modal" data-modal-url="modals/link/link.php?from=contact&id=<?= $contact_id ?>&to=software">
                                    <i class="fas fa-link me-2"></i>Link
                                </button>
                                <button type="button" class="btn btn-sm btn-primary ajax-modal" data-modal-size="lg" data-modal-url="modals/software/software_add.php?client_id=<?= $client_id ?>&contact_id=<?= $contact_id ?>">
                                    <i class="fas fa-plus me-2"></i>New License
                                </button>
                            </div>
                        <?php } ?>
                    </div>
                    <?php if ($software_count) { ?>
                        <?= softwareHeldTable($held_software, 'contact', $contact_id, $can_support_edit) ?>
                    <?php } else { ?>
                        <div class="itflow-asset-empty">No licenses.</div>
                    <?php } ?>
                </div>
                <?php } ?>

                <!-- Documents -->
                <div class="tab-pane fade" id="contact-pane-documents" role="tabpanel">
                    <div class="itflow-asset-pane-bar">
                        <span class="text-body-secondary small">Documentation about or for them.</span>
                        <?php if ($can_edit) { ?>
                            <div class="d-flex gap-2">
                                <button type="button" class="btn btn-sm btn-outline-secondary ajax-modal" data-modal-url="modals/contact/contact_link_document.php?id=<?= $contact_id ?>">
                                    <i class="fas fa-link me-2"></i>Link
                                </button>
                                <?php if ($can_support_edit) { ?>
                                    <button type="button" class="btn btn-sm btn-primary ajax-modal" data-modal-size="xl" data-modal-url="modals/document/document_add.php?<?= $client_url ?>&contact_id=<?= $contact_id ?>">
                                        <i class="fas fa-plus me-2"></i>New Document
                                    </button>
                                <?php } ?>
                            </div>
                        <?php } ?>
                    </div>
                    <?php if ($document_count) { ?>
                        <div class="list-group list-group-flush">
                            <?php while ($row = mysqli_fetch_assoc($sql_linked_documents)) {
                                $document_id = intval($row['document_id']);
                                $document_last = $row['document_updated_at'] ?: $row['document_created_at']; ?>
                                <div class="list-group-item d-flex align-items-center gap-3 py-2">
                                    <?= documentTile('sm') ?>
                                    <div class="flex-grow-1 min-w-0">
                                        <a class="fw-semibold" href="document.php?client_id=<?= $client_id ?>&document_id=<?= $document_id ?>"><?= escapeHtml($row['document_name']) ?></a>
                                        <?php if ($row['document_description']) { ?><div class="small text-body-secondary text-truncate"><?= escapeHtml($row['document_description']) ?></div><?php } ?>
                                    </div>
                                    <span class="small text-body-secondary text-nowrap" title="<?= escapeHtml($document_last) ?>"><?= escapeHtml($row['user_name']) ?> &middot; <?= timeAgo($document_last) ?></span>
                                    <a class="btn btn-sm btn-light ajax-modal" href="#" title="View" data-modal-size="lg" data-modal-url="modals/document/document_view.php?id=<?= $document_id ?>"><i class="fas fa-fw fa-eye"></i></a>
                                    <?php if ($can_support_edit) { ?>
                                        <a class="btn btn-sm btn-light" title="Unlink" href="post.php?unlink_contact_from_document&contact_id=<?= $contact_id ?>&document_id=<?= $document_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>"><i class="fas fa-fw fa-unlink"></i></a>
                                    <?php } ?>
                                </div>
                            <?php } ?>
                        </div>
                    <?php } else { ?>
                        <div class="itflow-asset-empty">No documents.</div>
                    <?php } ?>
                </div>

                <!-- Files -->
                <div class="tab-pane fade" id="contact-pane-files" role="tabpanel">
                    <div class="itflow-asset-pane-bar">
                        <span class="text-body-secondary small">Files linked to them.</span>
                        <?php if ($can_edit) { ?>
                            <div class="d-flex gap-2">
                                <button type="button" class="btn btn-sm btn-outline-secondary ajax-modal" data-modal-url="modals/contact/contact_link_file.php?id=<?= $contact_id ?>">
                                    <i class="fas fa-link me-2"></i>Link
                                </button>
                                <?php if ($can_support_edit) { ?>
                                    <button type="button" class="btn btn-sm btn-primary ajax-modal" data-modal-url="modals/file/file_upload.php?<?= $client_url ?>&contact_id=<?= $contact_id ?>">
                                        <i class="fas fa-upload me-2"></i>Upload
                                    </button>
                                <?php } ?>
                            </div>
                        <?php } ?>
                    </div>
                    <?php if ($file_count) { ?>
                        <div class="list-group list-group-flush">
                            <?php while ($row = mysqli_fetch_assoc($sql_linked_files)) {
                                $file_id = intval($row['file_id']); ?>
                                <div class="list-group-item d-flex align-items-center gap-3 py-2">
                                    <?= fileTypeTile($row['file_ext'], 'sm') ?>
                                    <div class="flex-grow-1 min-w-0">
                                        <a class="fw-semibold ajax-modal" href="#" data-modal-size="lg" data-modal-url="modals/file/file.php?id=<?= $file_id ?>"><?= escapeHtml($row['file_name']) ?></a>
                                        <?php if ($row['file_description']) { ?><div class="small text-body-secondary text-truncate"><?= escapeHtml($row['file_description']) ?></div><?php } ?>
                                    </div>
                                    <span class="small text-body-secondary text-nowrap" title="<?= escapeHtml($row['file_created_at']) ?>"><?= formatBytes($row['file_size']) ?> &middot; <?= timeAgo($row['file_created_at']) ?></span>
                                    <?php if ($can_edit) { ?>
                                        <a class="btn btn-sm btn-light" title="Unlink" href="post.php?unlink_contact_from_file&contact_id=<?= $contact_id ?>&file_id=<?= $file_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>"><i class="fas fa-fw fa-unlink"></i></a>
                                    <?php } ?>
                                </div>
                            <?php } ?>
                        </div>
                    <?php } else { ?>
                        <div class="itflow-asset-empty">No files.</div>
                    <?php } ?>
                </div>

                <!-- Services -->
                <div class="tab-pane fade" id="contact-pane-services" role="tabpanel">
                    <div class="itflow-asset-pane-bar">
                        <span class="text-body-secondary small">Services they are a contact for.</span>
                        <?php if ($can_edit) { ?>
                            <button type="button" class="btn btn-sm btn-outline-secondary ajax-modal" data-modal-url="modals/contact/contact_link_service.php?id=<?= $contact_id ?>">
                                <i class="fas fa-link me-2"></i>Link Service
                            </button>
                        <?php } ?>
                    </div>
                    <?php if ($service_count) { ?>
                        <div class="list-group list-group-flush">
                            <?php while ($row = mysqli_fetch_assoc($sql_linked_services)) {
                                $service_id = intval($row['service_id']); ?>
                                <div class="list-group-item d-flex align-items-center gap-3 py-2">
                                    <i class="fas fa-fw fa-stream text-secondary"></i>
                                    <div class="flex-grow-1 min-w-0">
                                        <a class="fw-semibold ajax-modal" href="#" data-modal-size="lg" data-modal-url="modals/service/service.php?id=<?= $service_id ?>"><?= escapeHtml($row['service_name']) ?></a>
                                        <?php if ($row['service_description']) { ?><div class="small text-body-secondary text-truncate"><?= escapeHtml($row['service_description']) ?></div><?php } ?>
                                    </div>
                                    <span class="small text-body-secondary text-nowrap"><?= escapeHtml(implode(' · ', array_filter([$row['service_category'], $row['service_importance']]))) ?></span>
                                    <?php if ($can_edit) { ?>
                                        <a class="btn btn-sm btn-light" title="Unlink" href="post.php?unlink_service_from_contact&contact_id=<?= $contact_id ?>&service_id=<?= $service_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>"><i class="fas fa-fw fa-unlink"></i></a>
                                    <?php } ?>
                                </div>
                            <?php } ?>
                        </div>
                    <?php } else { ?>
                        <div class="itflow-asset-empty">Not a contact for any service.</div>
                    <?php } ?>
                </div>

                <!-- Notes -->
                <div class="tab-pane fade" id="contact-pane-notes" role="tabpanel">
                    <div class="itflow-asset-pane-bar">
                        <span class="text-body-secondary small">A dated log - calls, meetings, anything worth remembering.</span>
                        <?php if ($can_edit) { ?>
                            <button type="button" class="btn btn-sm btn-primary ajax-modal" data-modal-url="modals/contact/contact_note_add.php?id=<?= $contact_id ?>">
                                <i class="fas fa-plus me-2"></i>New Note
                            </button>
                        <?php } ?>
                    </div>
                    <?php if ($note_count) { ?>
                        <div class="list-group list-group-flush">
                            <?php while ($row = mysqli_fetch_assoc($sql_related_notes)) {
                                $contact_note_id = intval($row['contact_note_id']);
                                $contact_note_type = escapeHtml($row['contact_note_type']);
                                $contact_note_created_at = escapeHtml($row['contact_note_created_at']);
                                $note_type_icon = !empty($note_type_icons[$contact_note_type]) ? $note_type_icons[$contact_note_type] : 'fa-sticky-note'; ?>
                                <div class="list-group-item d-flex gap-3 py-3">
                                    <span class="itflow-asset-icon itflow-asset-icon-sm itflow-asset-tone-other" title="<?= $contact_note_type ?>"><i class="fas fa-fw <?= $note_type_icon ?>"></i></span>
                                    <div class="flex-grow-1 min-w-0">
                                        <div class="small text-body-secondary mb-1">
                                            <span class="fw-semibold text-body"><?= $contact_note_type ?></span>
                                            &middot; <?= escapeHtml($row['user_name']) ?> &middot; <span title="<?= $contact_note_created_at ?>"><?= timeAgo($contact_note_created_at) ?></span>
                                        </div>
                                        <div><?= nl2br(escapeHtml($row['contact_note'])) ?></div>
                                    </div>
                                    <?php if ($can_edit) { ?>
                                        <div class="dropdown dropstart">
                                            <button class="btn btn-sm btn-light" title="More actions" type="button" data-bs-toggle="dropdown"><i class="fas fa-fw fa-ellipsis-v"></i></button>
                                            <div class="dropdown-menu">
                                                <a class="dropdown-item text-danger" href="post.php?archive_contact_note=<?= $contact_note_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>">
                                                    <i class="fas fa-fw fa-archive me-2"></i>Archive
                                                </a>
                                                <?php if ($can_delete) { ?>
                                                    <a class="dropdown-item text-danger text-bold confirm-link" href="post.php?delete_contact_note=<?= $contact_note_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>">
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
    function updateContactNotes(contact_id) {
        itflowPost("ajax.php", {
            contact_set_notes: 'TRUE',
            csrf_token: '<?= $_SESSION['csrf_token'] ?>',
            contact_id: contact_id,
            notes: document.getElementById("contactNotes").value
        });
    }

    /*
     * Which tab is open: the URL hash wins (#tickets - the contacts list's
     * counts link straight to their tab), then the tab last used on any
     * contact, then Assets. Choosing a tab updates the hash without adding a
     * history entry, so a refresh or the redirect back from a save lands on the
     * same tab. Bootstrap loads in the footer, so this waits for it.
     */
    document.addEventListener('DOMContentLoaded', function () {
        var key = 'itflow.contactTab';
        var tabs = document.querySelectorAll('[data-contact-tab]');
        if (!tabs.length) {
            return;
        }
        function byName(name) {
            return document.querySelector('[data-contact-tab="' + name + '"]');
        }
        var wanted = location.hash.replace('#', '');
        var remembered = '';
        try { remembered = localStorage.getItem(key) || ''; } catch (e) {}
        var start = byName(wanted) || byName(remembered) || tabs[0];
        bootstrap.Tab.getOrCreateInstance(start).show();

        tabs.forEach(function (tab) {
            tab.addEventListener('shown.bs.tab', function () {
                var name = tab.getAttribute('data-contact-tab');
                try { localStorage.setItem(key, name); } catch (e) {}
                history.replaceState(null, '', location.pathname + location.search + '#' + name);
            });
        });
    });
</script>

<?php
require_once "../includes/footer.php";
