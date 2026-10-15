<?php

/*
 * Contact quick look - the person at a glance, opened from the client
 * overview, the clients list, an asset, a ticket and elsewhere without
 * leaving the page.
 *
 * A small version of the contact page (agent/contact.php), in the same look
 * as the asset quick look: who they are in the header, how to reach them and
 * how to verify them in a few lines with no field labels, then whatever is
 * linked to them in tabs with counts. Their location changes in place
 * (js/quick_pick.js). "Open contact" is one click away for the rest.
 */

require_once '../../../includes/modal_header.php';

enforceUserPermission('module_client');

$contact_id = intval($_GET['id'] ?? 0);

$contact_row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT contact_archived_at, contact_billing, contact_client_id, contact_department,
    contact_email, contact_extension, contact_id, contact_important, contact_location_id, contact_mobile, contact_mobile_country_code,
    contact_name, contact_notes, contact_phone, contact_phone_country_code, contact_photo, contact_pin, contact_primary, contact_technical,
    contact_title, contact_user_id, location_archived_at, location_name, user_auth_method
    FROM contacts
    LEFT JOIN locations ON location_id = contact_location_id
    LEFT JOIN users ON user_id = contact_user_id
    WHERE contact_id = $contact_id
    LIMIT 1
"));
if (!$contact_row) {
    echo json_encode(['error' => 'That contact no longer exists.']);
    exit;
}

$client_id = intval($contact_row['contact_client_id']);

enforceClientAccess();

$can_edit = lookupUserPermission('module_client') >= 2;
$can_credentials = lookupUserPermission('module_credential') >= 1;
$can_support = lookupUserPermission('module_support') >= 1;

$contact_name = escapeHtml($contact_row['contact_name']);
$contact_email = escapeHtml($contact_row['contact_email']);
$contact_pin = escapeHtml($contact_row['contact_pin']);
$contact_notes = escapeHtml($contact_row['contact_notes']);
$contact_archived = !empty($contact_row['contact_archived_at']);
$contact_role_line = implode(' &middot; ', array_filter([escapeHtml($contact_row['contact_title']), escapeHtml($contact_row['contact_department'])]));
$contact_phone_link = contactPhoneLink($contact_row['contact_phone'], $contact_row['contact_phone_country_code'], $contact_row['contact_extension']);
$contact_mobile_link = contactPhoneLink($contact_row['contact_mobile'], $contact_row['contact_mobile_country_code']);
$contact_phone_copy = escapeHtml(formatPhoneNumber($contact_row['contact_phone'], $contact_row['contact_phone_country_code']));
$contact_mobile_copy = escapeHtml(formatPhoneNumber($contact_row['contact_mobile'], $contact_row['contact_mobile_country_code']));
$contact_portal = contactPortalAccess($contact_row['contact_user_id'], $contact_row['user_auth_method']);
$contact_tags_display = tagPills('contact', $contact_id, tagsForRecord('contact', $contact_id), "contacts.php?client_id=$client_id&");

// Assets assigned to them
$sql_related_assets = mysqli_query($mysqli, "SELECT asset_id, asset_name, asset_type, asset_make, asset_model, asset_status, interface_ip
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

// Tickets - open ones first; the most recent few here, the rest on the contact page
$quick_ticket_limit = 8;
$related_tickets = [];
$ticket_count = $ticket_open_count = 0;
if ($can_support) {
    [$ticket_count, $ticket_open_count] = ticketCounts("tickets.ticket_contact_id = $contact_id");
    $related_tickets = ticketRows("tickets.ticket_contact_id = $contact_id", $quick_ticket_limit);
}

// Recurring tickets
$recurring_ticket_count = 0;
if ($can_support) {
    $sql_related_recurring_tickets = mysqli_query($mysqli, "SELECT recurring_ticket_subject, recurring_ticket_frequency, recurring_ticket_next_run
        FROM recurring_tickets
        WHERE recurring_ticket_contact_id = $contact_id
        ORDER BY recurring_ticket_next_run ASC"
    );
    $recurring_ticket_count = mysqli_num_rows($sql_related_recurring_tickets);
}

// Licenses they hold a seat on, with the key they were given
$held_software = $can_support ? softwareHeldBy('contact', $contact_id) : [];
$software_count = count($held_software);

// Documents
$linked_documents = linkedRecords('contact', $contact_id, 'document');
$document_count = count($linked_documents);

// Files
$linked_files = linkedRecords('contact', $contact_id, 'file');
$file_count = count($linked_files);

// Notes
$sql_related_notes = mysqli_query($mysqli, "SELECT contact_note, contact_note_created_at, contact_note_type, user_name FROM contact_notes
    LEFT JOIN users ON contact_note_created_by = user_id
    WHERE contact_note_contact_id = $contact_id
      AND contact_note_archived_at IS NULL
    ORDER BY contact_note_created_at DESC"
);
$note_count = mysqli_num_rows($sql_related_notes);
$note_type_icons = [];
$sql_note_type_icons = mysqli_query($mysqli, "SELECT category_name, category_icon FROM categories WHERE category_type = 'contact_note_type'");
while ($row = mysqli_fetch_assoc($sql_note_type_icons)) {
    $note_type_icons[$row['category_name']] = escapeHtml($row['category_icon']);
}

// Only the tabs with something in them - same order as the contact page
$quick_tabs = array_filter([
    'assets' => ['label' => 'Assets', 'count' => $asset_count],
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

$has_reach = $contact_phone_link || $contact_mobile_link || $contact_email;

ob_start();

?>

<!-- Who they are - the contact page's header, in the modal's dark bar -->
<div class="modal-header bg-dark" data-bs-theme="dark">
    <div class="d-flex align-items-center gap-3 min-w-0">
        <?= contactAvatar($client_id, $contact_id, $contact_row['contact_name'], $contact_row['contact_photo']) ?>
        <div class="min-w-0">
            <h5 class="modal-title d-flex flex-wrap align-items-center column-gap-2 row-gap-1">
                <span class="fw-semibold text-break"><?= $contact_name ?></span>
                <span class="fs-6 d-inline-flex flex-wrap gap-1"><?= contactFlagPills($contact_row, false) ?></span>
                <?php if ($contact_archived) { ?><span class="badge rounded-pill text-bg-secondary fw-normal fs-6">Archived</span><?php } ?>
            </h5>
            <?php if ($contact_role_line) { ?>
                <div class="small text-body-secondary mt-1"><?= $contact_role_line ?></div>
            <?php } ?>
        </div>
    </div>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
</div>

<div class="modal-body">

    <?php if ($contact_tags_display || $contact_row['contact_phone'] || $contact_row['contact_mobile'] || $contact_email) { ?>
        <div class="d-flex flex-wrap align-items-start justify-content-between gap-2 mb-3">
            <div class="min-w-0"><?= $contact_tags_display ?></div>
            <div class="d-flex flex-wrap gap-2">
                <?php if ($contact_row['contact_phone'] || $contact_row['contact_mobile']) {
                    $dial_number = $contact_row['contact_phone'] ?: $contact_row['contact_mobile'];
                    $dial_country = $contact_row['contact_phone'] ? $contact_row['contact_phone_country_code'] : $contact_row['contact_mobile_country_code'];
                    $dial = preg_replace('/[^0-9+]/', '', ($dial_country ? "+$dial_country" : '') . $dial_number); ?>
                    <a class="btn btn-sm btn-outline-primary" href="tel:<?= escapeHtml($dial) ?>"><i class="fas fa-phone me-1"></i>Call</a>
                <?php } ?>
                <?php if ($contact_email) { ?>
                    <a class="btn btn-sm btn-outline-primary" href="mailto:<?= $contact_email ?>"><i class="far fa-envelope me-1"></i>Email</a>
                <?php } ?>
            </div>
        </div>
    <?php } ?>

    <!-- At a glance: how to reach them, where they are, how to verify them - no labels, the icons say what each line is -->
    <div class="row g-2 itflow-asset-glance">
        <?php if ($has_reach) { ?>
            <div class="col-sm-6">
                <?php if ($contact_phone_link) { ?>
                    <div class="itflow-glance-item" title="Phone">
                        <i class="fas fa-fw fa-phone"></i>
                        <span><?= $contact_phone_link ?><button type="button" class="itflow-copy clipboardjs" title="Copy phone" data-clipboard-text="<?= $contact_phone_copy ?>"><i class="far fa-copy"></i></button></span>
                    </div>
                <?php } ?>
                <?php if ($contact_mobile_link) { ?>
                    <div class="itflow-glance-item" title="Mobile">
                        <i class="fas fa-fw fa-mobile-alt"></i>
                        <span><?= $contact_mobile_link ?><button type="button" class="itflow-copy clipboardjs" title="Copy mobile" data-clipboard-text="<?= $contact_mobile_copy ?>"><i class="far fa-copy"></i></button></span>
                    </div>
                <?php } ?>
                <?php if ($contact_email) { ?>
                    <div class="itflow-glance-item" title="Email">
                        <i class="far fa-fw fa-envelope"></i>
                        <span class="d-flex align-items-center min-w-0"><a class="text-truncate" href="mailto:<?= $contact_email ?>"><?= $contact_email ?></a><button type="button" class="itflow-copy clipboardjs" title="Copy email" data-clipboard-text="<?= $contact_email ?>"><i class="far fa-copy"></i></button></span>
                    </div>
                <?php } ?>
            </div>
        <?php } ?>
        <div class="col-sm-6">
            <div class="itflow-glance-item" title="Location">
                <i class="fas fa-fw fa-map-marker-alt"></i>
                <div class="flex-grow-1 min-w-0">
                    <?php if ($can_edit && !$contact_archived) { ?>
                        <?= quickPick('contact_location', $contact_id, (string) intval($contact_row['contact_location_id']), contactLocationHtml($contact_row, 'detail'), 'detail', 0, $client_id) ?>
                    <?php } else { ?>
                        <?= contactLocationHtml($contact_row, 'detail') ?>
                    <?php } ?>
                </div>
            </div>
            <?php if ($contact_pin) { ?>
                <div class="itflow-glance-item" title="PIN - ask for it to verify who's calling">
                    <i class="fas fa-fw fa-key"></i>
                    <span>PIN <span class="font-monospace"><?= $contact_pin ?></span></span>
                </div>
            <?php } ?>
            <?php if ($contact_portal) { ?>
                <div class="itflow-glance-item" title="Client portal">
                    <i class="fas fa-fw fa-user-circle"></i>
                    <span>Portal login <span class="text-body-secondary">&middot; <?= $contact_portal ?></span></span>
                </div>
            <?php } ?>
        </div>
    </div>

    <?php if ($can_edit || $contact_notes) { ?>
        <textarea class="form-control form-control-sm mt-3 itflow-contact-quicknote" rows="2" placeholder="Quick notes - saves when you click away" aria-label="Quick notes"
            data-contact-id="<?= $contact_id ?>" data-csrf="<?= escapeHtml($_SESSION['csrf_token'] ?? '') ?>" <?= $can_edit ? '' : 'readonly' ?>><?= $contact_notes ?></textarea>
    <?php } ?>

    <?php if ($quick_tabs) { ?>
        <!-- Everything linked to them -->
        <div class="itflow-asset-related itflow-asset-quick-related mt-3" data-contact-quick="<?= $contact_id ?>">
            <ul class="nav nav-pills itflow-asset-pills mb-2" role="tablist">
                <?php foreach ($quick_tabs as $tab_key => $tab) { ?>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link text-nowrap <?= $tab_key === $first_tab ? 'active' : '' ?>" data-bs-toggle="pill" data-bs-target="#contact-quick-<?= $contact_id ?>-<?= $tab_key ?>" data-contact-quick-tab="<?= $tab_key ?>" type="button" role="tab">
                            <?= $tab['label'] ?> <span class="badge rounded-pill ms-1"><?= $tab['count'] ?></span>
                            <?php if (!empty($tab['badge'])) { ?><span class="badge rounded-pill text-bg-danger" title="Open"><?= intval($tab['badge']) ?> open</span><?php } ?>
                        </button>
                    </li>
                <?php } ?>
            </ul>

            <div class="tab-content">

                <?php if ($asset_count) { ?>
                    <div class="tab-pane fade <?= $first_tab === 'assets' ? 'show active' : '' ?>" id="contact-quick-<?= $contact_id ?>-assets" role="tabpanel">
                        <div class="table-responsive">
                            <table class="table table-sm table-hover align-middle mb-0">
                                <tbody>
                                <?php while ($row = mysqli_fetch_assoc($sql_related_assets)) {
                                    $asset_ip = escapeHtml($row['interface_ip']); ?>
                                    <tr>
                                        <td class="w-100">
                                            <i class="fas fa-fw fa-<?= getAssetIcon($row['asset_type']) ?> text-secondary me-1"></i>
                                            <a class="ajax-modal" href="#" data-modal-size="lg" data-modal-url="modals/asset/asset.php?id=<?= intval($row['asset_id']) ?>"><?= escapeHtml($row['asset_name']) ?></a>
                                            <span class="text-body-secondary small ms-1"><?= escapeHtml(trim($row['asset_make'] . ' ' . $row['asset_model'])) ?></span>
                                        </td>
                                        <td class="text-nowrap"><?php if ($asset_ip) { ?><span class="font-monospace"><?= $asset_ip ?></span><?php if ($asset_ip !== 'DHCP') { ?><button type="button" class="itflow-copy clipboardjs" title="Copy IP" data-clipboard-text="<?= $asset_ip ?>"><i class="far fa-copy"></i></button><?php } ?><?php } ?></td>
                                        <td class="text-nowrap"><?= assetStatusPill($row['asset_status']) ?></td>
                                    </tr>
                                <?php } ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php } ?>

                <?php if ($credential_count) { ?>
                    <div class="tab-pane fade <?= $first_tab === 'credentials' ? 'show active' : '' ?>" id="contact-quick-<?= $contact_id ?>-credentials" role="tabpanel">
                        <?= credentialListHtml($related_credentials) ?>
                    </div>
                <?php } ?>

                <?php if ($ticket_count) { ?>
                    <div class="tab-pane fade <?= $first_tab === 'tickets' ? 'show active' : '' ?>" id="contact-quick-<?= $contact_id ?>-tickets" role="tabpanel">
                        <?= ticketTableHtml($related_tickets, ['compact' => true]) ?>
                        <?php if ($ticket_count > count($related_tickets)) { ?>
                            <div class="small mt-2"><a href="contact.php?client_id=<?= $client_id ?>&contact_id=<?= $contact_id ?>#tickets">All <?= $ticket_count ?> tickets on the contact page<i class="fas fa-arrow-right ms-1"></i></a></div>
                        <?php } ?>
                    </div>
                <?php } ?>

                <?php if ($recurring_ticket_count) { ?>
                    <div class="tab-pane fade <?= $first_tab === 'recurring' ? 'show active' : '' ?>" id="contact-quick-<?= $contact_id ?>-recurring" role="tabpanel">
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
                    <div class="tab-pane fade <?= $first_tab === 'licenses' ? 'show active' : '' ?>" id="contact-quick-<?= $contact_id ?>-licenses" role="tabpanel">
                        <!-- Each opens its quick look - seats, renewal, the key to install -->
                        <?= softwareHeldList($held_software) ?>
                    </div>
                <?php } ?>

                <?php if ($document_count) { ?>
                    <div class="tab-pane fade <?= $first_tab === 'documents' ? 'show active' : '' ?>" id="contact-quick-<?= $contact_id ?>-documents" role="tabpanel">
                        <!-- The same rows as a document's Linked card - tile, name, when it changed -->
                        <ul class="list-unstyled mb-0 itflow-links">
                            <?php foreach ($linked_documents as $row) { ?>
                                <li class="itflow-link-row"><?= linkRowHtml('document', $row, $client_id) ?></li>
                            <?php } ?>
                        </ul>
                    </div>
                <?php } ?>

                <?php if ($file_count) { ?>
                    <div class="tab-pane fade <?= $first_tab === 'files' ? 'show active' : '' ?>" id="contact-quick-<?= $contact_id ?>-files" role="tabpanel">
                        <!-- Each opens its quick look - preview, details, what else it is linked to -->
                        <ul class="list-unstyled mb-0 itflow-links">
                            <?php foreach ($linked_files as $row) { ?>
                                <li class="itflow-link-row"><?= linkRowHtml('file', $row, $client_id) ?></li>
                            <?php } ?>
                        </ul>
                    </div>
                <?php } ?>

                <?php if ($note_count) { ?>
                    <div class="tab-pane fade <?= $first_tab === 'notes' ? 'show active' : '' ?>" id="contact-quick-<?= $contact_id ?>-notes" role="tabpanel">
                        <ul class="list-group list-group-flush">
                            <?php while ($row = mysqli_fetch_assoc($sql_related_notes)) {
                                $note_icon = ($note_type_icons[$row['contact_note_type']] ?? '') ?: 'fa-sticky-note'; ?>
                                <li class="list-group-item px-0 d-flex gap-3">
                                    <i class="fas fa-fw <?= $note_icon ?> text-secondary mt-1" title="<?= escapeHtml($row['contact_note_type']) ?>"></i>
                                    <div class="min-w-0">
                                        <div><?= nl2br(escapeHtml($row['contact_note'])) ?></div>
                                        <div class="small text-body-secondary"><?= escapeHtml($row['user_name']) ?> &middot; <span title="<?= escapeHtml($row['contact_note_created_at']) ?>"><?= timeAgo($row['contact_note_created_at']) ?></span></div>
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
    <a href="contact.php?client_id=<?= $client_id ?>&contact_id=<?= $contact_id ?>" class="btn btn-primary text-bold"><i class="fas fa-arrow-right me-2"></i>Open contact</a>
    <?php if ($can_edit) { ?>
        <button type="button" class="btn btn-outline-secondary ajax-modal" data-modal-size="lg" data-modal-url="modals/contact/contact_edit.php?id=<?= $contact_id ?>"><i class="fas fa-user-edit me-2"></i>Edit</button>
    <?php } ?>
    <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fa fa-times me-2"></i>Close</button>
</div>

<script>
    (function () {
        // Quick notes save when you click away - this modal's own box, never
        // the contact page's, which may be on the page underneath
        document.querySelectorAll('.itflow-contact-quicknote:not([data-wired])').forEach(function (notes) {
            notes.dataset.wired = '1';
            var saved = notes.value;
            notes.addEventListener('blur', function () {
                if (notes.readOnly || notes.value === saved) {
                    return;
                }
                saved = notes.value;
                itflowPost('ajax.php', {
                    contact_set_notes: 'TRUE',
                    csrf_token: notes.dataset.csrf,
                    contact_id: notes.dataset.contactId,
                    notes: notes.value
                });
            });
        });

        // Open on the tab used last time, if this contact has one
        var key = 'itflow.contactQuickTab';
        document.querySelectorAll('[data-contact-quick]:not([data-wired])').forEach(function (box) {
            box.dataset.wired = '1';
            var remembered = null;
            try {
                remembered = localStorage.getItem(key);
            } catch (e) {}
            var tab = remembered && box.querySelector('[data-contact-quick-tab="' + remembered + '"]');
            if (tab && !tab.classList.contains('active')) {
                bootstrap.Tab.getOrCreateInstance(tab).show();
            }
            box.addEventListener('shown.bs.tab', function (e) {
                try {
                    localStorage.setItem(key, e.target.dataset.contactQuickTab);
                } catch (err) {}
            });
        });
    })();
</script>

<?php
require_once '../../../includes/modal_footer.php';
