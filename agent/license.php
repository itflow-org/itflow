<?php

/*
 * License - one license (a row in the software table).
 *
 * What an MSP needs from it in one place: when it renews and whether the
 * seats add up (header), the key and order details to copy, who to call at
 * the vendor and their account number, and then everything hung off it in
 * tabs with counts - the devices and users on it (each with the key they were
 * given), the individual keys and who has each, the portal logins, documents
 * and files, and its history from the audit log. The open tab follows the
 * URL hash (#keys) and is remembered between visits.
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

$software_id = intval($_GET['software_id'] ?? 0);
$license = softwareRow($software_id);

if (!$license || ($client_url && intval($license['software_client_id']) !== intval($client_id))) {

    echo "<center><h1 class='text-secondary mt-5'>Nothing to see here</h1><a class='btn btn-lg btn-secondary mt-3' href='javascript:history.back()'><i class='fa fa-fw fa-arrow-left'></i> Go Back</a></center>";

} else {

    $client_id = intval($license['software_client_id']);
    enforceClientAccess();
    $client_url = "client_id=$client_id&";

    $client_name = escapeHtml($license['client_name']);
    $software_name = escapeHtml($license['software_name']);
    $software_version = escapeHtml($license['software_version']);
    $software_type = escapeHtml($license['software_type']);
    $software_description = escapeHtml($license['software_description']);
    $software_license_type = escapeHtml($license['software_license_type']);
    $software_key = $license['software_key'];
    $software_purchase_reference = escapeHtml($license['software_purchase_reference']);
    $software_purchase = escapeHtml($license['software_purchase']);
    $software_purchase_age = assetAge($license['software_purchase']);
    $software_notes = escapeHtml($license['software_notes']);
    $software_favorite = intval($license['software_favorite']);
    $software_created_at = escapeHtml($license['software_created_at']);
    $software_archived_at = escapeHtml($license['software_archived_at']);
    $vendor_id = intval($license['vendor_id']);
    $vendor_name = escapeHtml($license['vendor_name']);

    $seats = softwareSeats($license['seats_used'], $license['software_seats']);
    $renewal = softwareRenewal($license['software_expire']);

    $can_edit = lookupUserPermission('module_support') >= 2 && !$software_archived_at;
    $can_restore = lookupUserPermission('module_support') >= 2;
    $can_delete = lookupUserPermission('module_support') >= 3 && !empty($config_destructive_deletes_enable);
    $can_credentials = lookupUserPermission('module_credential') >= 1;
    $can_link_credentials = $can_edit && lookupUserPermission('module_credential') >= 2;

    // Override Tab Title // No Sanitizing needed as this var will only be used in the tab title
    $page_title = $license['software_name'];

    $holders = softwareSeatHolders($software_id);
    $keys = softwareKeys($software_id);
    $keys_free = count(array_filter($keys, function ($key) {
        return !$key['assets'] && !$key['contacts'];
    }));
    $credentials = $can_credentials ? credentialRows("credential_id IN (SELECT credential_id FROM software_credentials WHERE software_id = $software_id)") : [];
    $documents = linkedRecords('software', $software_id, 'document');
    $files = linkedRecords('software', $software_id, 'file');
    $history = softwareHistory($software_id, 100);

    $license_tabs = [
        'seats' => ['label' => 'Seats', 'icon' => 'fa-users', 'count' => $seats['used']],
        'keys' => ['label' => 'Keys', 'icon' => 'fa-key', 'count' => count($keys) + ($software_key ? 1 : 0)],
        'credentials' => ['label' => 'Credentials', 'icon' => 'fa-key', 'count' => count($credentials)],
        'documents' => ['label' => 'Documents', 'icon' => 'fa-file-alt', 'count' => count($documents)],
        'files' => ['label' => 'Files', 'icon' => 'fa-paperclip', 'count' => count($files)],
        'history' => ['label' => 'History', 'icon' => 'fa-history', 'count' => count($history)],
    ];
    if (!$can_credentials) {
        unset($license_tabs['credentials']);
    }

    // A renewal ticket, filled in with what the person doing it needs
    $renewal_subject = 'Renew ' . $license['software_name'] . ($renewal ? ' - ' . strtolower($renewal['text']) : '');
    // Plain text, one fact a line - the ticket form escapes it
    $renewal_details = implode("\n", array_filter([
        'Renew ' . trim($license['software_name'] . ' ' . $license['software_version']) . ' for ' . $license['client_name'] . '.',
        $renewal ? 'Renewal date: ' . $renewal['date'] : '',
        $seats['state'] !== 'unlimited' ? 'Seats: ' . $seats['used'] . ' in use of ' . $seats['total'] : 'Seats in use: ' . $seats['used'],
        $license['vendor_name'] ? 'Vendor: ' . $license['vendor_name'] . ($license['vendor_account_number'] ? ' (account ' . $license['vendor_account_number'] . ')' : '') : '',
        $license['software_purchase_reference'] ? 'Last order: ' . $license['software_purchase_reference'] : '',
    ]));
    $renewal_ticket_url = 'modals/ticket/ticket_add.php?' . http_build_query(['client_id' => $client_id, 'subject' => $renewal_subject, 'details' => $renewal_details]);

    $link_url = "modals/link/link.php?from=software&id=$software_id&to=";
    $unlink_url = function ($to, $other_id) use ($software_id) {
        return "post.php?unlink_item&from=software&id=$software_id&to=$to&other=" . intval($other_id) . "&csrf_token=" . $_SESSION['csrf_token'];
    };

    ?>

    <ol class="breadcrumb d-print-none">
        <li class="breadcrumb-item">
            <a href="client_overview.php?client_id=<?= $client_id ?>"><?= $client_name ?></a>
        </li>
        <li class="breadcrumb-item">
            <a href="software.php?client_id=<?= $client_id ?>">Licenses</a>
        </li>
        <li class="breadcrumb-item active"><?= $software_name ?></li>
    </ol>

    <!-- What it is, when it renews, whether the seats add up -->
    <div class="card mb-3">
        <div class="card-body d-flex flex-wrap align-items-center gap-3">
            <?= softwareTile($license['software_type'], 'lg') ?>

            <div class="flex-grow-1 min-w-0">
                <h1 class="h4 fw-semibold mb-1 d-flex flex-wrap align-items-center gap-2">
                    <span><?= $software_name ?></span>
                    <?php if ($software_version) { ?><span class="text-body-secondary fw-normal"><?= $software_version ?></span><?php } ?>
                    <?php if ($software_favorite) { ?><i class="fas fa-star text-warning fs-6" title="Favorite"></i><?php } ?>
                    <?php if ($software_archived_at) { ?><span class="badge rounded-pill text-bg-secondary fw-normal fs-6">Archived</span><?php } ?>
                    <span class="fs-6"><?= softwareRenewalPill($renewal, true) ?></span>
                    <?php if ($seats['state'] === 'over') { ?>
                        <span class="fs-6"><span class="badge rounded-pill bg-danger-subtle text-danger-emphasis border border-danger-subtle fw-normal"><i class="fas fa-exclamation-triangle me-1"></i><?= $seats['over'] ?> over seats</span></span>
                    <?php } ?>
                </h1>
                <div class="text-body-secondary small d-flex flex-wrap column-gap-3 row-gap-1">
                    <?php if ($software_type) { ?><span><i class="fas fa-fw <?= softwareTypeStyle($license['software_type'])['icon'] ?> me-1"></i><?= $software_type ?></span><?php } ?>
                    <?php if ($software_license_type) { ?><span><i class="fas fa-fw <?= softwareLicenseTypeIcon($license['software_license_type']) ?> me-1"></i><?= $software_license_type ?></span><?php } ?>
                    <?php if ($vendor_name) { ?><span><i class="fas fa-fw fa-building me-1"></i><?= $vendor_name ?></span><?php } ?>
                    <?php if ($software_description) { ?><span><?= $software_description ?></span><?php } ?>
                </div>
            </div>

            <div class="d-flex flex-wrap gap-2 d-print-none">
                <?php if ($can_edit) { ?>
                    <button type="button" class="btn btn-outline-primary ajax-modal" data-modal-size="lg" data-modal-url="modals/software/software_renew.php?id=<?= $software_id ?>">
                        <i class="fas fa-redo-alt me-2"></i>Renew
                    </button>
                    <div class="dropdown">
                        <button type="button" class="btn btn-primary dropdown-toggle" data-bs-toggle="dropdown"><i class="fas fa-plus me-2"></i>New</button>
                        <div class="dropdown-menu dropdown-menu-end">
                            <a class="dropdown-item ajax-modal" href="#" data-modal-size="lg" data-modal-url="<?= escapeHtml($renewal_ticket_url) ?>">
                                <i class="fa fa-fw fa-life-ring text-secondary me-2"></i>Renewal Ticket
                            </a>
                            <a class="dropdown-item ajax-modal" href="#" data-modal-url="modals/software/software_key_add.php?software_id=<?= $software_id ?>">
                                <i class="fa fa-fw fa-key text-secondary me-2"></i>Keys
                            </a>
                            <a class="dropdown-item ajax-modal" href="#" data-modal-size="xl" data-modal-url="modals/document/document_add.php?client_id=<?= $client_id ?>&software_id=<?= $software_id ?>">
                                <i class="fa fa-fw fa-file-alt text-secondary me-2"></i>Document
                            </a>
                            <a class="dropdown-item ajax-modal" href="#" data-modal-url="modals/file/file_upload.php?client_id=<?= $client_id ?>&software_id=<?= $software_id ?>">
                                <i class="fa fa-fw fa-upload text-secondary me-2"></i>Upload File(s)
                            </a>
                        </div>
                    </div>
                    <div class="dropdown">
                        <button type="button" class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown"><i class="fas fa-link me-2"></i>Link</button>
                        <div class="dropdown-menu dropdown-menu-end">
                            <a class="dropdown-item ajax-modal" href="#" data-modal-url="<?= $link_url ?>asset"><i class="fa fa-fw fa-desktop text-secondary me-2"></i>Device</a>
                            <a class="dropdown-item ajax-modal" href="#" data-modal-url="<?= $link_url ?>contact"><i class="fa fa-fw fa-user text-secondary me-2"></i>User</a>
                            <?php if ($can_link_credentials) { ?>
                                <a class="dropdown-item ajax-modal" href="#" data-modal-url="<?= $link_url ?>credential"><i class="fa fa-fw fa-lock text-secondary me-2"></i>Credential</a>
                            <?php } ?>
                            <a class="dropdown-item ajax-modal" href="#" data-modal-url="<?= $link_url ?>document"><i class="fa fa-fw fa-file-alt text-secondary me-2"></i>Document</a>
                            <a class="dropdown-item ajax-modal" href="#" data-modal-url="<?= $link_url ?>file"><i class="fa fa-fw fa-paperclip text-secondary me-2"></i>File</a>
                        </div>
                    </div>
                    <button type="button" class="btn btn-outline-secondary ajax-modal" data-modal-size="lg" data-modal-url="modals/software/software_edit.php?id=<?= $software_id ?>">
                        <i class="fas fa-edit me-2"></i>Edit
                    </button>
                <?php } ?>
                <?php if ($can_restore) { ?>
                    <div class="dropdown dropstart">
                        <button class="btn btn-outline-secondary" title="More actions" type="button" data-bs-toggle="dropdown">
                            <i class="fas fa-fw fa-ellipsis-v"></i>
                        </button>
                        <div class="dropdown-menu">
                            <?php if ($software_archived_at) { ?>
                                <a class="dropdown-item" href="post.php?restore_software=<?= $software_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>">
                                    <i class="fas fa-fw fa-redo text-secondary me-2"></i>Restore
                                </a>
                                <?php if ($can_delete) { ?>
                                    <a class="dropdown-item text-danger text-bold confirm-link" href="post.php?delete_software=<?= $software_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>"
                                        data-confirm-text="<?= $software_name ?> goes for good, with its seats and keys.">
                                        <i class="fas fa-fw fa-trash me-2"></i>Delete
                                    </a>
                                <?php } ?>
                            <?php } else { ?>
                                <a class="dropdown-item ajax-modal" href="#" data-modal-url="modals/software/software_seats.php?id=<?= $software_id ?>">
                                    <i class="fas fa-fw fa-users text-secondary me-2"></i>Seats
                                </a>
                                <div class="dropdown-divider"></div>
                                <a class="dropdown-item text-danger confirm-link" href="post.php?archive_software=<?= $software_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>"
                                    data-confirm-text="It drops off every device and user until it is restored.">
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

        <!-- The facts: seats, key, order, dates - then the vendor -->
        <div class="col-xl-4 col-lg-5">

            <div class="card mb-3">
                <div class="card-header">
                    <h3 class="card-title mb-0"><i class="fas fa-fw fa-cube text-secondary me-2"></i>License</h3>
                </div>
                <div class="card-body">
                    <dl class="itflow-asset-dl mb-0">
                        <dt>Seats</dt>
                        <dd><?= softwareSeatBar($seats) ?></dd>
                        <?php if ($software_license_type) { ?>
                            <dt>Type</dt>
                            <dd><i class="fas fa-fw <?= softwareLicenseTypeIcon($license['software_license_type']) ?> text-secondary me-1"></i><?= $software_license_type ?></dd>
                        <?php } ?>
                        <dt>Key</dt>
                        <dd>
                            <?php if ($software_key) { ?>
                                <?= softwareKeyHtml($software_key) ?>
                            <?php } elseif ($keys) { ?>
                                <a href="#keys" data-license-tab-link="keys"><?= count($keys) ?> individual key<?= count($keys) === 1 ? '' : 's' ?></a>
                            <?php } else { ?>
                                <span class="text-body-secondary">None recorded</span>
                            <?php } ?>
                        </dd>
                        <dt>Renews</dt>
                        <dd>
                            <?php if ($renewal) { ?>
                                <?= escapeHtml($renewal['date']) ?>
                                <div class="small text-<?= $renewal['tone'] === 'success' ? 'body-secondary' : $renewal['tone'] . '-emphasis' ?>"><?= escapeHtml($renewal['text']) ?></div>
                            <?php } else { ?>
                                <span class="text-body-secondary">No date</span>
                            <?php } ?>
                        </dd>
                        <?php if ($software_purchase) { ?>
                            <dt>Purchased</dt>
                            <dd><?= $software_purchase ?><?php if ($software_purchase_age) { ?><div class="small text-body-secondary"><?= $software_purchase_age ?> ago</div><?php } ?></dd>
                        <?php } ?>
                        <?php if ($software_purchase_reference) { ?>
                            <dt>Order ref</dt>
                            <dd><?= $software_purchase_reference ?><button type="button" class="itflow-copy clipboardjs" title="Copy reference" data-clipboard-text="<?= $software_purchase_reference ?>"><i class="far fa-copy"></i></button></dd>
                        <?php } ?>
                        <dt>Added</dt><dd title="<?= $software_created_at ?>"><?= timeAgo($software_created_at) ?></dd>
                    </dl>
                </div>
            </div>

            <?php if ($vendor_name) {
                $vendor_phone = contactPhoneLink($license['vendor_phone'], $license['vendor_phone_country_code'], $license['vendor_extension']);
                $vendor_email = escapeHtml($license['vendor_email']);
                $vendor_website = escapeHtml($license['vendor_website']);
                $vendor_account = escapeHtml($license['vendor_account_number']);
                $vendor_pin = vendorSecretHtml($license['vendor_code']); ?>
                <div class="card mb-3">
                    <div class="card-header d-flex align-items-center">
                        <h3 class="card-title mb-0 me-auto"><i class="fas fa-fw fa-building text-secondary me-2"></i>Vendor</h3>
                        <span class="small"><?= vendorLink($vendor_id, $license['vendor_name'], ['page' => true, 'client_id' => $client_id]) ?></span>
                    </div>
                    <div class="card-body">
                        <dl class="itflow-asset-dl mb-0">
                            <?php if ($vendor_account) { ?>
                                <dt>Account</dt>
                                <dd><span class="font-monospace"><?= $vendor_account ?></span><button type="button" class="itflow-copy clipboardjs" title="Copy account number" data-clipboard-text="<?= $vendor_account ?>"><i class="far fa-copy"></i></button></dd>
                            <?php } ?>
                            <?php if ($vendor_pin) { ?>
                                <dt>PIN</dt><dd><?= $vendor_pin ?></dd>
                            <?php } ?>
                            <?php if ($vendor_phone) { ?>
                                <dt>Phone</dt><dd><?= $vendor_phone ?></dd>
                            <?php } ?>
                            <?php if ($vendor_email) { ?>
                                <dt>Email</dt>
                                <dd><a href="mailto:<?= $vendor_email ?>"><?= $vendor_email ?></a><button type="button" class="itflow-copy clipboardjs" title="Copy email" data-clipboard-text="<?= $vendor_email ?>"><i class="far fa-copy"></i></button></dd>
                            <?php } ?>
                            <?php if ($vendor_website) { ?>
                                <dt>Website</dt><dd><a href="<?= escapeHtml(vendorWebsiteUrl($license['vendor_website'])) ?>" target="_blank" rel="noopener"><?= escapeHtml(vendorWebsiteLabel($license['vendor_website'])) ?><i class="fas fa-external-link-alt ms-1 small"></i></a></dd>
                            <?php } ?>
                            <?php if ($license['vendor_hours']) { ?>
                                <dt>Hours</dt><dd><?= escapeHtml($license['vendor_hours']) ?></dd>
                            <?php } ?>
                            <?php if ($license['vendor_sla']) { ?>
                                <dt>SLA</dt><dd><?= escapeHtml($license['vendor_sla']) ?></dd>
                            <?php } ?>
                            <?php if (!$vendor_account && !$vendor_phone && !$vendor_email && !$vendor_website && !$license['vendor_hours'] && !$license['vendor_sla']) { ?>
                                <dt>Contact</dt><dd class="text-body-secondary">Nothing recorded on the vendor yet</dd>
                            <?php } ?>
                        </dl>
                    </div>
                </div>
            <?php } ?>

            <div class="card mb-3">
                <div class="card-header">
                    <h3 class="card-title mb-0"><i class="fas fa-fw fa-pen text-secondary me-2"></i>Notes</h3>
                </div>
                <textarea class="form-control border-0 rounded-0 rounded-bottom itflow-license-notes" rows="6" placeholder="Admin portal, how it is billed, who to call to renew - saves when you click away"
                    data-software-id="<?= $software_id ?>" data-csrf="<?= escapeHtml($_SESSION['csrf_token']) ?>" <?= $can_edit ? '' : 'readonly' ?>><?= $software_notes ?></textarea>
            </div>

        </div>

        <!-- Everything hung off it -->
        <div class="col-xl-8 col-lg-7">
            <div class="card mb-3 itflow-asset-related">
                <div class="card-header py-2">
                    <ul class="nav nav-pills itflow-asset-pills" role="tablist">
                        <?php foreach ($license_tabs as $tab_key => $tab) { ?>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link text-nowrap <?= $tab['count'] ? '' : 'is-empty' ?>" id="license-tab-<?= $tab_key ?>" data-bs-toggle="pill" data-bs-target="#license-pane-<?= $tab_key ?>" data-license-tab="<?= $tab_key ?>" type="button" role="tab">
                                    <i class="fas fa-fw <?= $tab['icon'] ?> me-1 d-none d-xxl-inline"></i><?= $tab['label'] ?>
                                    <span class="badge rounded-pill ms-1"><?= $tab['count'] ?><?= $tab_key === 'seats' && $seats['total'] ? ' / ' . $seats['total'] : '' ?></span>
                                </button>
                            </li>
                        <?php } ?>
                    </ul>
                </div>
                <div class="tab-content">

                    <!-- Seats: the devices and users on it -->
                    <div class="tab-pane fade" id="license-pane-seats" role="tabpanel">
                        <div class="itflow-asset-pane-bar">
                            <span class="text-body-secondary small">
                                <?php if ($seats['state'] === 'unlimited') { ?>
                                    <?= $seats['used'] ?> in use - no seat limit.
                                <?php } else { ?>
                                    <?= $seats['used'] ?> of <?= $seats['total'] ?> seats in use<?= $seats['over'] ? ' - <span class="text-danger-emphasis fw-semibold">' . $seats['over'] . ' over</span>' : ($seats['free'] ? ', ' . $seats['free'] . ' free' : '') ?>.
                                <?php } ?>
                            </span>
                            <?php if ($can_edit) { ?>
                                <button type="button" class="btn btn-sm btn-primary ajax-modal" data-modal-url="modals/software/software_seats.php?id=<?= $software_id ?>">
                                    <i class="fas fa-users me-2"></i>Manage Seats
                                </button>
                            <?php } ?>
                        </div>
                        <?php if ($holders['assets']) { ?>
                            <div class="itflow-license-section"><i class="fas fa-fw fa-desktop me-1"></i>Devices <span class="badge rounded-pill ms-1"><?= count($holders['assets']) ?></span></div>
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <tbody>
                                    <?php foreach ($holders['assets'] as $holder) {
                                        $holder_id = intval($holder['asset_id']); ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center gap-2">
                                                    <span class="itflow-asset-icon itflow-asset-icon-sm itflow-asset-tone-<?= assetTypeTone($holder['asset_type']) ?>" aria-hidden="true"><i class="fas fa-fw fa-<?= getAssetIcon($holder['asset_type']) ?>"></i></span>
                                                    <div class="min-w-0">
                                                        <a class="fw-semibold ajax-modal" href="#" data-modal-size="lg" data-modal-url="modals/asset/asset.php?id=<?= $holder_id ?>"><?= escapeHtml($holder['asset_name']) ?></a>
                                                        <div class="small text-body-secondary text-truncate"><?= escapeHtml(implode(' · ', array_filter([$holder['asset_type'], trim($holder['asset_make'] . ' ' . $holder['asset_model'])]))) ?></div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <?php if ($holder['asset_contact_name']) { ?>
                                                    <?= contactLink($client_id, $holder['asset_contact_id'], $holder['asset_contact_name'], $holder['asset_contact_photo']) ?>
                                                <?php } else { ?>
                                                    <span class="text-body-secondary small">Nobody assigned</span>
                                                <?php } ?>
                                            </td>
                                            <td class="itflow-license-key-cell">
                                                <?php foreach ($holder['held_keys'] as $held_key) { ?>
                                                    <div><?= softwareKeyHtml($held_key) ?></div>
                                                <?php } ?>
                                                <?php if (!$holder['held_keys'] && $software_key) { ?>
                                                    <span class="small text-body-secondary" title="No key of its own - the license key">License key</span>
                                                <?php } ?>
                                            </td>
                                            <td class="text-end">
                                                <?php if ($can_edit) { ?>
                                                    <a href="<?= $unlink_url('asset', $holder_id) ?>" class="btn btn-sm btn-light confirm-link" title="Take off this license" data-confirm-text="Frees the seat, and any key it had."><i class="fas fa-fw fa-unlink"></i></a>
                                                <?php } ?>
                                            </td>
                                        </tr>
                                    <?php } ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php } ?>
                        <?php if ($holders['contacts']) { ?>
                            <div class="itflow-license-section"><i class="fas fa-fw fa-user me-1"></i>Users <span class="badge rounded-pill ms-1"><?= count($holders['contacts']) ?></span></div>
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <tbody>
                                    <?php foreach ($holders['contacts'] as $holder) {
                                        $holder_id = intval($holder['contact_id']);
                                        $holder_email = escapeHtml($holder['contact_email']); ?>
                                        <tr>
                                            <td>
                                                <?= contactLink($holder['contact_client_id'], $holder_id, $holder['contact_name'], $holder['contact_photo'], ['size' => 'sm', 'class' => 'fw-semibold']) ?>
                                                <?php if ($holder['contact_title'] || $holder_email) { ?>
                                                    <div class="small text-body-secondary itflow-license-holder-sub text-truncate">
                                                        <?= escapeHtml($holder['contact_title']) ?><?= $holder['contact_title'] && $holder_email ? ' &middot; ' : '' ?><?php if ($holder_email) { ?><a class="text-reset" href="mailto:<?= $holder_email ?>"><?= $holder_email ?></a><button type="button" class="itflow-copy clipboardjs" title="Copy email" data-clipboard-text="<?= $holder_email ?>"><i class="far fa-copy"></i></button><?php } ?>
                                                    </div>
                                                <?php } ?>
                                            </td>
                                            <td class="itflow-license-key-cell">
                                                <?php foreach ($holder['held_keys'] as $held_key) { ?>
                                                    <div><?= softwareKeyHtml($held_key) ?></div>
                                                <?php } ?>
                                            </td>
                                            <td class="text-end">
                                                <?php if ($can_edit) { ?>
                                                    <a href="<?= $unlink_url('contact', $holder_id) ?>" class="btn btn-sm btn-light confirm-link" title="Take off this license" data-confirm-text="Frees the seat, and any key they had."><i class="fas fa-fw fa-unlink"></i></a>
                                                <?php } ?>
                                            </td>
                                        </tr>
                                    <?php } ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php } ?>
                        <?php if (!$holders['assets'] && !$holders['contacts']) { ?>
                            <div class="itflow-asset-empty">
                                <div>Nothing and nobody on this license yet.</div>
                                <?php if ($can_edit) { ?>
                                    <button type="button" class="btn btn-sm btn-outline-primary mt-3 ajax-modal" data-modal-url="modals/software/software_seats.php?id=<?= $software_id ?>"><i class="fas fa-users me-2"></i>Add devices and users</button>
                                <?php } ?>
                            </div>
                        <?php } ?>
                    </div>

                    <!-- Keys: the license key, and a key per device or user -->
                    <div class="tab-pane fade" id="license-pane-keys" role="tabpanel">
                        <div class="itflow-asset-pane-bar">
                            <span class="text-body-secondary small">
                                <?php if ($keys) { ?>
                                    <?= count($keys) ?> individual key<?= count($keys) === 1 ? '' : 's' ?><?= $keys_free ? ", $keys_free not handed out" : ', all handed out' ?>. A key shows on the page of whoever has it.
                                <?php } else { ?>
                                    A key per device or user, for licenses sold that way. Each shows on the page of whoever has it.
                                <?php } ?>
                            </span>
                            <?php if ($can_edit) { ?>
                                <button type="button" class="btn btn-sm btn-primary ajax-modal" data-modal-url="modals/software/software_key_add.php?software_id=<?= $software_id ?>">
                                    <i class="fas fa-plus me-2"></i>Add Keys
                                </button>
                            <?php } ?>
                        </div>
                        <?php if ($software_key) { ?>
                            <div class="d-flex flex-wrap align-items-center gap-2 px-3 py-2 border-bottom itflow-license-main-key">
                                <span class="badge rounded-pill text-bg-primary fw-normal">License key</span>
                                <?= softwareKeyHtml($software_key, 'itflow-copy-always') ?>
                                <span class="small text-body-secondary ms-auto">Covers every seat - change it with Edit</span>
                            </div>
                        <?php } ?>
                        <?php if ($keys) { ?>
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead class="text-nowrap table-light">
                                    <tr><th>Key</th><th>Handed to</th><th></th></tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ($keys as $key) { ?>
                                        <tr>
                                            <td class="itflow-license-key-cell"><?= softwareKeyHtml($key['key']) ?></td>
                                            <td>
                                                <div class="d-flex flex-wrap gap-2">
                                                    <?php foreach ($key['assets'] as $key_asset) { ?>
                                                        <a class="itflow-contact-chip ajax-modal" href="#" data-modal-size="lg" data-modal-url="modals/asset/asset.php?id=<?= intval($key_asset['asset_id']) ?>">
                                                            <i class="fas fa-fw fa-<?= getAssetIcon($key_asset['asset_type']) ?> text-secondary"></i><?= escapeHtml($key_asset['asset_name']) ?>
                                                        </a>
                                                    <?php } ?>
                                                    <?php foreach ($key['contacts'] as $key_contact) { ?>
                                                        <?= contactLink($key_contact['contact_client_id'], $key_contact['contact_id'], $key_contact['contact_name'], $key_contact['contact_photo'], ['archived' => !empty($key_contact['contact_archived_at'])]) ?>
                                                    <?php } ?>
                                                    <?php if (!$key['assets'] && !$key['contacts']) { ?>
                                                        <span class="badge rounded-pill bg-success-subtle text-success-emphasis border border-success-subtle fw-normal">Free</span>
                                                    <?php } ?>
                                                </div>
                                            </td>
                                            <td class="text-end text-nowrap">
                                                <?php if ($can_edit) { ?>
                                                    <button type="button" class="btn btn-sm btn-light ajax-modal" title="Hand out or change" data-modal-url="modals/software/software_key_edit.php?id=<?= $key['id'] ?>"><i class="fas fa-fw fa-user-tag"></i></button>
                                                    <a href="post.php?delete_software_key=<?= $key['id'] ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>" class="btn btn-sm btn-light text-danger confirm-link" title="Delete key" data-confirm-text="The key is removed from the license and from whoever has it."><i class="fas fa-fw fa-trash"></i></a>
                                                <?php } ?>
                                            </td>
                                        </tr>
                                    <?php } ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php } elseif (!$software_key) { ?>
                            <div class="itflow-asset-empty">No keys recorded.</div>
                        <?php } ?>
                    </div>

                    <?php if ($can_credentials) { ?>
                    <!-- Credentials: the vendor portal, the admin console -->
                    <div class="tab-pane fade" id="license-pane-credentials" role="tabpanel">
                        <div class="itflow-asset-pane-bar">
                            <span class="text-body-secondary small">Logins that go with it - the vendor portal, the admin console. Passwords are fetched only when you ask.</span>
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
                        <?php } else { ?>
                            <div class="itflow-asset-empty">No credentials linked.</div>
                        <?php } ?>
                    </div>
                    <?php } ?>

                    <!-- Documents and files -->
                    <?php foreach (['documents' => ['document', $documents, 'Setup guides, the renewal process, what is included.', 'No documents linked.'],
                                    'files' => ['file', $files, 'Invoices, license certificates, installers, exports.', 'No files linked.']] as $pane => [$to, $linked, $hint, $empty]) { ?>
                        <div class="tab-pane fade" id="license-pane-<?= $pane ?>" role="tabpanel">
                            <div class="itflow-asset-pane-bar">
                                <span class="text-body-secondary small"><?= $hint ?></span>
                                <?php if ($can_edit) { ?>
                                    <div class="d-flex gap-2">
                                        <button type="button" class="btn btn-sm btn-outline-secondary ajax-modal" data-modal-url="<?= $link_url . $to ?>">
                                            <i class="fas fa-link me-2"></i>Link
                                        </button>
                                        <?php if ($to === 'document') { ?>
                                            <button type="button" class="btn btn-sm btn-primary ajax-modal" data-modal-size="xl" data-modal-url="modals/document/document_add.php?client_id=<?= $client_id ?>&software_id=<?= $software_id ?>">
                                                <i class="fas fa-plus me-2"></i>New Document
                                            </button>
                                        <?php } else { ?>
                                            <button type="button" class="btn btn-sm btn-primary ajax-modal" data-modal-url="modals/file/file_upload.php?client_id=<?= $client_id ?>&software_id=<?= $software_id ?>">
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
                                            <?php if ($can_edit) { ?>
                                                <a class="itflow-link-remove" href="<?= $unlink_url($to, $linked_row['id']) ?>" title="Unlink <?= $to ?>"><i class="fas fa-unlink"></i></a>
                                            <?php } ?>
                                        </li>
                                    <?php } ?>
                                </ul>
                            <?php } else { ?>
                                <div class="itflow-asset-empty"><?= $empty ?></div>
                            <?php } ?>
                        </div>
                    <?php } ?>

                    <!-- History, from the audit log -->
                    <div class="tab-pane fade" id="license-pane-history" role="tabpanel">
                        <div class="itflow-asset-pane-bar">
                            <span class="text-body-secondary small">Renewals, seat and key changes, edits - newest first.</span>
                        </div>
                        <?php if ($history) {
                            $history_icons = ['Create' => 'fa-plus', 'Edit' => 'fa-edit', 'Renew' => 'fa-redo-alt', 'Seats' => 'fa-users', 'Keys' => 'fa-key',
                                'Link' => 'fa-link', 'Unlink' => 'fa-unlink', 'Archive' => 'fa-archive', 'Restore' => 'fa-redo', 'Export' => 'fa-download']; ?>
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
        // Notes save when you click away
        document.querySelectorAll('.itflow-license-notes').forEach(function (notes) {
            var saved = notes.value;
            notes.addEventListener('blur', function () {
                if (notes.readOnly || notes.value === saved) {
                    return;
                }
                saved = notes.value;
                itflowPost('ajax.php', {
                    software_set_notes: 'TRUE',
                    csrf_token: notes.dataset.csrf,
                    software_id: notes.dataset.softwareId,
                    notes: notes.value
                });
            });
        });

        /*
         * Which tab is open: the URL hash wins (#keys), then the tab last used
         * on any license, then Seats. Choosing a tab updates the hash without
         * a history entry, so the redirect back from a save lands on the same
         * tab. Bootstrap loads in the footer, so this waits for it.
         */
        document.addEventListener('DOMContentLoaded', function () {
            var key = 'itflow.licenseTab';
            var tabs = document.querySelectorAll('[data-license-tab]');
            if (!tabs.length) {
                return;
            }
            function byName(name) {
                return document.querySelector('[data-license-tab="' + name + '"]');
            }
            var remembered = '';
            try { remembered = localStorage.getItem(key) || ''; } catch (e) {}
            var start = byName(location.hash.replace('#', '')) || byName(remembered) || tabs[0];
            bootstrap.Tab.getOrCreateInstance(start).show();

            tabs.forEach(function (tab) {
                tab.addEventListener('shown.bs.tab', function () {
                    var name = tab.getAttribute('data-license-tab');
                    try { localStorage.setItem(key, name); } catch (e) {}
                    history.replaceState(null, '', location.pathname + location.search + '#' + name);
                });
            });
            document.querySelectorAll('[data-license-tab-link]').forEach(function (link) {
                link.addEventListener('click', function (e) {
                    e.preventDefault();
                    bootstrap.Tab.getOrCreateInstance(byName(link.dataset.licenseTabLink)).show();
                });
            });
        });
    </script>


<?php
}

require_once "../includes/footer.php";
