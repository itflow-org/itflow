<?php

/*
 * License quick look - the license page in a modal, opened from the asset
 * and contact pages, the license list, a document's or file's links and the
 * search. Header with renewal and seat state, the facts to copy (key, order
 * reference, vendor account and phone), quick notes, then tabs for only what
 * has something in it: devices, users, keys, credentials, documents, files.
 * A license made from a license template says which - and, for someone who
 * sees Sales, the product it is sold as.
 */

require_once '../../../includes/modal_header.php';

enforceUserPermission('module_support');

$software_id = intval($_GET['id'] ?? 0);
$license = softwareRow($software_id);
if (!$license) {
    echo json_encode(['error' => 'That license no longer exists.']);
    exit;
}
$client_id = intval($license['software_client_id']);
enforceClientAccess();

$software_name = escapeHtml($license['software_name']);
$software_version = escapeHtml($license['software_version']);
$software_description = escapeHtml($license['software_description']);
$software_purchase_reference = escapeHtml($license['software_purchase_reference']);
$software_notes = escapeHtml($license['software_notes']);
$software_archived = !empty($license['software_archived_at']);
$vendor_id = intval($license['vendor_id']);
$vendor_name = escapeHtml($license['vendor_name']);
$vendor_account = escapeHtml($license['vendor_account_number']);
$vendor_phone = contactPhoneLink($license['vendor_phone'], $license['vendor_phone_country_code'], $license['vendor_extension']);

$seats = softwareSeats($license['seats_used'], $license['software_seats']);
$template = intval($license['software_template_id']) ? softwareTemplateRow($license['software_template_id']) : null;
$renewal = softwareRenewal($license['software_expire']);

$can_edit = lookupUserPermission('module_support') >= 2 && !$software_archived;
$can_credentials = lookupUserPermission('module_credential') >= 1;
$license_tags_display = tagPills('license', $software_id, tagsForRecord('license', $software_id), "software.php?client_id=$client_id&", $can_edit);

$holders = softwareSeatHolders($software_id);
$keys = softwareKeys($software_id);
$credentials = $can_credentials ? credentialRows("credential_id IN (SELECT credential_id FROM software_credentials WHERE software_id = $software_id)") : [];
$documents = linkedRecords('software', $software_id, 'document');
$files = linkedRecords('software', $software_id, 'file');

$quick_tabs = array_filter([
    'devices' => ['label' => 'Devices', 'count' => count($holders['assets'])],
    'users' => ['label' => 'Users', 'count' => count($holders['contacts'])],
    'keys' => ['label' => 'Keys', 'count' => count($keys)],
    'credentials' => ['label' => 'Credentials', 'count' => count($credentials)],
    'documents' => ['label' => 'Documents', 'count' => count($documents)],
    'files' => ['label' => 'Files', 'count' => count($files)],
], function ($tab) {
    return $tab['count'] > 0;
});
$first_tab = array_key_first($quick_tabs);
$quick_id = "license-quick-$software_id";

ob_start();

?>

<div class="modal-header bg-dark" data-bs-theme="dark">
    <div class="d-flex align-items-center gap-3 min-w-0">
        <?= softwareTile($license['software_type']) ?>
        <div class="min-w-0">
            <h5 class="modal-title d-flex flex-wrap align-items-center column-gap-2 row-gap-1">
                <span class="fw-semibold text-break"><?= $software_name ?></span>
                <?php if ($software_version) { ?><span class="text-body-secondary fw-normal fs-6"><?= $software_version ?></span><?php } ?>
                <?php if (intval($license['software_favorite'])) { ?><i class="fas fa-star text-warning fs-6" title="Favorite"></i><?php } ?>
                <?php if ($software_archived) { ?><span class="badge rounded-pill text-bg-secondary fw-normal fs-6">Archived</span><?php } ?>
                <span class="fs-6"><?= $renewal && $renewal['state'] !== 'active' ? softwareRenewalPill($renewal) : '' ?></span>
                <?php if ($seats['state'] === 'over') { ?>
                    <span class="badge rounded-pill bg-danger-subtle text-danger-emphasis border border-danger-subtle fw-normal fs-6"><?= $seats['over'] ?> over seats</span>
                <?php } ?>
            </h5>
            <div class="small text-body-secondary d-flex flex-wrap column-gap-3 mt-1">
                <?php if ($license['software_type']) { ?><span><?= escapeHtml($license['software_type']) ?></span><?php } ?>
                <?php if ($license['software_license_type']) { ?><span><i class="fas fa-fw <?= softwareLicenseTypeIcon($license['software_license_type']) ?> me-1"></i><?= escapeHtml($license['software_license_type']) ?></span><?php } ?>
                <span><?= escapeHtml($license['client_name']) ?></span>
                <?php if ($template) { ?>
                    <span title="The license template it was made from"><i class="fas fa-fw fa-puzzle-piece me-1"></i>From <?= !empty($session_is_admin) ? '<a class="text-reset" href="/admin/software_template.php?software_template_id=' . intval($template['software_template_id']) . '">' . escapeHtml($template['software_template_name']) . '</a>' : escapeHtml($template['software_template_name']) ?></span>
                    <?php if (!empty($template['product_name']) && !$template['product_archived_at'] && lookupUserPermission('module_sales') >= 1) { ?>
                        <span title="The product it is sold as"><i class="fas fa-fw fa-box-open me-1"></i>Sold as <a class="text-reset" href="/agent/product.php?product_id=<?= intval($template['software_template_product_id']) ?>"><?= escapeHtml($template['product_name']) ?></a> · <?= escapeHtml(softwareTemplatePriceText($template)) ?></span>
                    <?php } ?>
                <?php } ?>
            </div>
        </div>
    </div>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
</div>

<div class="modal-body">

    <?php if ($software_description) { ?>
        <div class="text-body-secondary mb-3"><?= $software_description ?></div>
    <?php } ?>
    <?php if ($license_tags_display) { ?>
        <div class="mb-3"><?= $license_tags_display ?></div>
    <?php } ?>

    <!-- At a glance - the icons say what each line is -->
    <div class="row g-2 itflow-asset-glance">
        <div class="col-sm-6">
            <div class="itflow-glance-item" title="Seats">
                <i class="fas fa-fw fa-users"></i>
                <div class="flex-grow-1 min-w-0 itflow-seats-glance"><?= softwareSeatBar($seats) ?></div>
            </div>
            <?php if ($license['software_key']) { ?>
                <div class="itflow-glance-item" title="License key">
                    <i class="fas fa-fw fa-key"></i>
                    <span class="min-w-0"><?= softwareKeyHtml($license['software_key']) ?></span>
                </div>
            <?php } elseif ($keys) { ?>
                <div class="itflow-glance-item" title="Keys">
                    <i class="fas fa-fw fa-key"></i>
                    <span><?= count($keys) ?> individual key<?= count($keys) === 1 ? '' : 's' ?></span>
                </div>
            <?php } ?>
            <?php if ($software_purchase_reference) { ?>
                <div class="itflow-glance-item" title="Order reference">
                    <i class="fas fa-fw fa-receipt"></i>
                    <span><?= $software_purchase_reference ?><button type="button" class="itflow-copy clipboardjs" title="Copy reference" data-clipboard-text="<?= $software_purchase_reference ?>"><i class="far fa-copy"></i></button></span>
                </div>
            <?php } ?>
        </div>
        <div class="col-sm-6">
            <div class="itflow-glance-item" title="Renewal">
                <i class="fas fa-fw fa-calendar-alt"></i>
                <?php if ($renewal) { ?>
                    <span><?= escapeHtml($renewal['date']) ?> <span class="small text-<?= $renewal['tone'] === 'success' ? 'body-secondary' : $renewal['tone'] . '-emphasis' ?>">&middot; <?= escapeHtml($renewal['text']) ?></span></span>
                <?php } else { ?>
                    <span class="text-body-secondary">No renewal date</span>
                <?php } ?>
            </div>
            <?php if ($vendor_name) { ?>
                <div class="itflow-glance-item" title="Vendor">
                    <i class="fas fa-fw fa-building"></i>
                    <span class="min-w-0">
                        <?= vendorLink($vendor_id, $license['vendor_name']) ?>
                        <?php if ($vendor_phone) { ?><span class="text-body-secondary">&middot;</span> <?= $vendor_phone ?><?php } ?>
                    </span>
                </div>
                <?php if ($vendor_account) { ?>
                    <div class="itflow-glance-item" title="Vendor account number">
                        <i class="fas fa-fw fa-id-card"></i>
                        <span><span class="font-monospace"><?= $vendor_account ?></span><button type="button" class="itflow-copy clipboardjs" title="Copy account number" data-clipboard-text="<?= $vendor_account ?>"><i class="far fa-copy"></i></button></span>
                    </div>
                <?php } ?>
            <?php } ?>
        </div>
    </div>

    <?php if ($can_edit || $software_notes) { ?>
        <textarea class="form-control form-control-sm mt-3 itflow-license-notes" rows="2" placeholder="Notes - saves when you click away" aria-label="Notes"
            data-software-id="<?= $software_id ?>" data-csrf="<?= escapeHtml($_SESSION['csrf_token'] ?? '') ?>" <?= $can_edit ? '' : 'readonly' ?>><?= $software_notes ?></textarea>
    <?php } ?>

    <?php if ($quick_tabs) { ?>
        <div class="itflow-asset-related itflow-asset-quick-related mt-3" data-license-quick="<?= $software_id ?>">
            <ul class="nav nav-pills itflow-asset-pills mb-2" role="tablist">
                <?php foreach ($quick_tabs as $tab_key => $tab) { ?>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link text-nowrap <?= $tab_key === $first_tab ? 'active' : '' ?>" data-bs-toggle="pill" data-bs-target="#<?= $quick_id ?>-<?= $tab_key ?>" data-license-quick-tab="<?= $tab_key ?>" type="button" role="tab">
                            <?= $tab['label'] ?> <span class="badge rounded-pill ms-1"><?= $tab['count'] ?></span>
                        </button>
                    </li>
                <?php } ?>
            </ul>

            <div class="tab-content">

                <?php if ($holders['assets']) { ?>
                    <div class="tab-pane fade <?= $first_tab === 'devices' ? 'show active' : '' ?>" id="<?= $quick_id ?>-devices" role="tabpanel">
                        <ul class="list-unstyled mb-0 itflow-links">
                            <?php foreach ($holders['assets'] as $holder) { ?>
                                <li class="itflow-link-row">
                                    <span class="itflow-asset-icon itflow-asset-icon-sm itflow-asset-tone-<?= assetTypeTone($holder['asset_type']) ?>" aria-hidden="true"><i class="fas fa-fw fa-<?= getAssetIcon($holder['asset_type']) ?>"></i></span>
                                    <div class="min-w-0 flex-grow-1">
                                        <div class="d-flex"><a class="ajax-modal text-truncate" href="#" data-modal-size="lg" data-modal-url="modals/asset/asset.php?id=<?= intval($holder['asset_id']) ?>"><?= escapeHtml($holder['asset_name']) ?></a></div>
                                        <div class="small text-body-secondary text-truncate"><?= escapeHtml(implode(' · ', array_filter([trim($holder['asset_make'] . ' ' . $holder['asset_model']), $holder['asset_contact_name']]))) ?></div>
                                    </div>
                                    <?php if ($holder['held_keys']) { ?><span class="small text-nowrap"><?= softwareKeyHtml($holder['held_keys'][0]) ?></span><?php } ?>
                                </li>
                            <?php } ?>
                        </ul>
                    </div>
                <?php } ?>

                <?php if ($holders['contacts']) { ?>
                    <div class="tab-pane fade <?= $first_tab === 'users' ? 'show active' : '' ?>" id="<?= $quick_id ?>-users" role="tabpanel">
                        <ul class="list-unstyled mb-0 itflow-links">
                            <?php foreach ($holders['contacts'] as $holder) { ?>
                                <li class="itflow-link-row">
                                    <?= contactAvatar($holder['contact_client_id'], $holder['contact_id'], $holder['contact_name'], $holder['contact_photo'], 'sm') ?>
                                    <div class="min-w-0 flex-grow-1">
                                        <div class="d-flex"><a class="ajax-modal text-truncate" href="#" data-modal-size="lg" data-modal-url="modals/contact/contact.php?id=<?= intval($holder['contact_id']) ?>"><?= escapeHtml($holder['contact_name']) ?></a></div>
                                        <div class="small text-body-secondary text-truncate"><?= escapeHtml(implode(' · ', array_filter([$holder['contact_title'], $holder['contact_email']]))) ?></div>
                                    </div>
                                    <?php if ($holder['held_keys']) { ?><span class="small text-nowrap"><?= softwareKeyHtml($holder['held_keys'][0]) ?></span><?php } ?>
                                </li>
                            <?php } ?>
                        </ul>
                    </div>
                <?php } ?>

                <?php if ($keys) { ?>
                    <div class="tab-pane fade <?= $first_tab === 'keys' ? 'show active' : '' ?>" id="<?= $quick_id ?>-keys" role="tabpanel">
                        <ul class="list-group list-group-flush">
                            <?php foreach ($keys as $key) {
                                $handed = array_merge(array_column($key['assets'], 'asset_name'), array_column($key['contacts'], 'contact_name')); ?>
                                <li class="list-group-item px-0 d-flex align-items-center justify-content-between gap-3">
                                    <span class="min-w-0"><?= softwareKeyHtml($key['key']) ?></span>
                                    <?php if ($handed) { ?>
                                        <span class="small text-body-secondary text-truncate"><?= escapeHtml(implode(', ', $handed)) ?></span>
                                    <?php } else { ?>
                                        <span class="badge rounded-pill bg-success-subtle text-success-emphasis border border-success-subtle fw-normal">Free</span>
                                    <?php } ?>
                                </li>
                            <?php } ?>
                        </ul>
                    </div>
                <?php } ?>

                <?php if ($credentials) { ?>
                    <div class="tab-pane fade <?= $first_tab === 'credentials' ? 'show active' : '' ?>" id="<?= $quick_id ?>-credentials" role="tabpanel">
                        <?= credentialListHtml($credentials) ?>
                    </div>
                <?php } ?>

                <?php foreach (['documents' => ['document', $documents], 'files' => ['file', $files]] as $pane => [$to, $linked]) {
                    if (!$linked) {
                        continue;
                    } ?>
                    <div class="tab-pane fade <?= $first_tab === $pane ? 'show active' : '' ?>" id="<?= $quick_id ?>-<?= $pane ?>" role="tabpanel">
                        <ul class="list-unstyled mb-0 itflow-links">
                            <?php foreach ($linked as $linked_row) { ?>
                                <li class="itflow-link-row"><?= linkRowHtml($to, $linked_row, $client_id) ?></li>
                            <?php } ?>
                        </ul>
                    </div>
                <?php } ?>

            </div>
        </div>
    <?php } elseif ($can_edit) { ?>
        <div class="text-body-secondary small mt-3">Nothing and nobody on this license yet - <a class="ajax-modal" href="#" data-modal-url="modals/software/software_seats.php?id=<?= $software_id ?>">add devices and users</a>.</div>
    <?php } ?>

</div>

<div class="modal-footer">
    <a href="license.php?client_id=<?= $client_id ?>&software_id=<?= $software_id ?>" class="btn btn-primary text-bold"><i class="fas fa-arrow-right me-2"></i>Open license</a>
    <?php if ($can_edit) { ?>
        <button type="button" class="btn btn-outline-secondary ajax-modal" data-modal-url="modals/software/software_seats.php?id=<?= $software_id ?>"><i class="fas fa-users me-2"></i>Seats</button>
        <button type="button" class="btn btn-outline-secondary ajax-modal" data-modal-size="lg" data-modal-url="modals/software/software_edit.php?id=<?= $software_id ?>"><i class="fas fa-edit me-2"></i>Edit</button>
    <?php } ?>
    <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fa fa-times me-2"></i>Close</button>
</div>

<script>
    (function () {
        // Notes save when you click away - scoped to this modal's own textarea
        document.querySelectorAll('.modal .itflow-license-notes:not([data-wired])').forEach(function (notes) {
            notes.dataset.wired = '1';
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

        // Open on the tab used last time, if this license has one
        var key = 'itflow.licenseQuickTab';
        document.querySelectorAll('[data-license-quick]:not([data-wired])').forEach(function (box) {
            box.dataset.wired = '1';
            var remembered = null;
            try {
                remembered = localStorage.getItem(key);
            } catch (e) {}
            var tab = remembered && box.querySelector('[data-license-quick-tab="' + remembered + '"]');
            if (tab && !tab.classList.contains('active')) {
                bootstrap.Tab.getOrCreateInstance(tab).show();
            }
            box.addEventListener('shown.bs.tab', function (e) {
                try {
                    localStorage.setItem(key, e.target.dataset.licenseQuickTab);
                } catch (err) {}
            });
        });
    })();
</script>

<?php
require_once '../../../includes/modal_footer.php';
