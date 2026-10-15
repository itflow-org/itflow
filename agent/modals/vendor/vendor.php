<?php

/*
 * Vendor quick look - the vendor page in a modal, opened from the vendors
 * list, tickets, licenses, assets, domains and search. Everything for the
 * call to them (support line, hours, SLA, the account number and PIN they ask
 * for, the account manager), the notes, then what is open with them and what
 * came from them, in tabs - only those with something in them.
 */

require_once '../../../includes/modal_header.php';

$vendor_id = intval($_GET['id'] ?? 0);
$vendor = vendorRow($vendor_id);
if (!$vendor) {
    echo json_encode(['error' => 'That vendor no longer exists.']);
    exit;
}
$client_id = intval($vendor['vendor_client_id']);
if ($client_id) {
    enforceUserPermission('module_client');
    enforceClientAccess();
}

$vendor_name = escapeHtml($vendor['vendor_name']);
$vendor_archived = !empty($vendor['vendor_archived_at']);
$vendor_phone = contactPhoneLink($vendor['vendor_phone'], $vendor['vendor_phone_country_code'], $vendor['vendor_extension']);
$vendor_dial = vendorDialNumber($vendor['vendor_phone'], $vendor['vendor_phone_country_code']);
$vendor_email = escapeHtml($vendor['vendor_email']);
$vendor_website_url = vendorWebsiteUrl($vendor['vendor_website']);
$vendor_account = escapeHtml($vendor['vendor_account_number']);
$vendor_pin = vendorSecretHtml($vendor['vendor_code']);
$vendor_notes = escapeHtml($vendor['vendor_notes']);
$manager_phone = contactPhoneLink($vendor['vendor_contact_phone'], $vendor['vendor_contact_phone_country_code']);
$manager_email = escapeHtml($vendor['vendor_contact_email']);

$can_edit = vendorCan($client_id, 2) && !$vendor_archived;
$can_support = lookupUserPermission('module_support') >= 1;
$can_support_edit = lookupUserPermission('module_support') >= 2;
$can_credentials = lookupUserPermission('module_credential') >= 1;
$can_financial = lookupUserPermission('module_financial') >= 1;

$cases = $licenses = $assets = $logins = [];
if ($can_support) {
    $cases = ticketRows("tickets.ticket_client_id = $client_id AND tickets.ticket_vendor_id = $vendor_id AND " . TICKET_OPEN_SQL);
    $sql = mysqli_query($mysqli, "SELECT software_id AS id, software_name AS name, software_type, software_version, software_license_type,
            software_seats, software_expire, " . softwareSeatsUsedSql('software.software_id') . " AS seats_used
        FROM software
        WHERE software_client_id = $client_id AND software_vendor_id = $vendor_id AND software_archived_at IS NULL
        ORDER BY software_expire IS NULL, software_expire ASC, software_name ASC");
    while ($row = mysqli_fetch_assoc($sql)) {
        $licenses[] = $row;
    }
    $sql = mysqli_query($mysqli, "SELECT asset_id AS id, asset_name AS name, asset_type, asset_make, asset_model, asset_warranty_expire
        FROM assets
        WHERE asset_client_id = $client_id AND asset_vendor_id = $vendor_id AND asset_archived_at IS NULL
        ORDER BY asset_type ASC, asset_name ASC");
    while ($row = mysqli_fetch_assoc($sql)) {
        $assets[] = $row;
    }
}
if ($can_credentials && $client_id) {
    $logins = linkedRecords('vendor', $vendor_id, 'credential');
}
$spend = !$client_id && $can_financial ? vendorSpend($vendor_id) : null;

$quick_tabs = array_filter([
    'cases' => ['label' => 'Open cases', 'count' => count($cases)],
    'licenses' => ['label' => 'Licenses', 'count' => count($licenses)],
    'assets' => ['label' => 'Assets', 'count' => count($assets)],
    'logins' => ['label' => 'Logins', 'count' => count($logins)],
], function ($tab) {
    return $tab['count'] > 0;
});
$first_tab = array_key_first($quick_tabs);
$quick_id = "vendor-quick-$vendor_id";
$page_url = 'vendor.php?' . ($client_id ? "client_id=$client_id&" : '') . "vendor_id=$vendor_id";

ob_start();

?>

<div class="modal-header bg-dark" data-bs-theme="dark">
    <div class="d-flex align-items-center gap-3 min-w-0">
        <?= vendorTile($vendor['vendor_name']) ?>
        <div class="min-w-0">
            <h5 class="modal-title d-flex flex-wrap align-items-center column-gap-2 row-gap-1">
                <span class="fw-semibold text-break"><?= $vendor_name ?></span>
                <?php if (intval($vendor['vendor_favorite'])) { ?><i class="fas fa-star text-warning small" title="Starred"></i><?php } ?>
                <?php if ($vendor_archived) { ?><span class="badge rounded-pill text-bg-secondary fw-normal fs-6">Archived</span><?php } ?>
            </h5>
            <div class="small text-body-secondary d-flex flex-wrap column-gap-3 mt-1">
                <span><?= $client_id ? escapeHtml($vendor['client_name']) : 'Our vendor' ?></span>
                <?php if ($vendor['vendor_description']) { ?><span><?= escapeHtml($vendor['vendor_description']) ?></span><?php } ?>
            </div>
        </div>
    </div>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
</div>

<div class="modal-body">

    <!-- Calling them - the icons say what each line is -->
    <div class="row g-2 itflow-asset-glance">
        <div class="col-sm-6">
            <div class="itflow-glance-item" title="Support line">
                <i class="fas fa-fw fa-headset"></i>
                <span><?= $vendor_phone ?: '<span class="text-body-secondary">No support line</span>' ?></span>
            </div>
            <?php if ($vendor['vendor_hours'] || $vendor['vendor_sla']) { ?>
                <div class="itflow-glance-item" title="Hours and SLA">
                    <i class="fas fa-fw fa-clock"></i>
                    <span><?= escapeHtml(implode(' · ', array_filter([$vendor['vendor_hours'], $vendor['vendor_sla']]))) ?></span>
                </div>
            <?php } ?>
            <?php if ($vendor_email) { ?>
                <div class="itflow-glance-item" title="Support email">
                    <i class="fas fa-fw fa-envelope"></i>
                    <span class="min-w-0 text-truncate"><a href="mailto:<?= $vendor_email ?>"><?= $vendor_email ?></a><button type="button" class="itflow-copy clipboardjs" title="Copy email" data-clipboard-text="<?= $vendor_email ?>"><i class="far fa-copy"></i></button></span>
                </div>
            <?php } ?>
            <?php if ($vendor_website_url) { ?>
                <div class="itflow-glance-item" title="Support site">
                    <i class="fas fa-fw fa-globe"></i>
                    <span class="min-w-0 text-truncate"><a href="<?= escapeHtml($vendor_website_url) ?>" target="_blank" rel="noopener"><?= escapeHtml(vendorWebsiteLabel($vendor['vendor_website'])) ?></a></span>
                </div>
            <?php } ?>
        </div>
        <div class="col-sm-6">
            <div class="itflow-glance-item" title="Account number">
                <i class="fas fa-fw fa-fingerprint"></i>
                <span><?= $vendor_account ? '<span class="font-monospace">' . $vendor_account . '</span><button type="button" class="itflow-copy clipboardjs" title="Copy account number" data-clipboard-text="' . $vendor_account . '"><i class="far fa-copy"></i></button>' : '<span class="text-body-secondary">No account number</span>' ?></span>
            </div>
            <?php if ($vendor_pin) { ?>
                <div class="itflow-glance-item" title="PIN / passcode">
                    <i class="fas fa-fw fa-key"></i>
                    <span><?= $vendor_pin ?></span>
                </div>
            <?php } ?>
            <?php if ($vendor['vendor_contact_name'] || $manager_phone || $manager_email) { ?>
                <div class="itflow-glance-item" title="Account manager">
                    <i class="fas fa-fw fa-user-tie"></i>
                    <span class="min-w-0">
                        <?= escapeHtml($vendor['vendor_contact_name'] ?: 'Account manager') ?>
                        <?php if ($manager_phone) { ?><span class="text-body-secondary">&middot;</span> <?= $manager_phone ?><?php } ?>
                        <?php if ($manager_email) { ?><div class="text-truncate"><a href="mailto:<?= $manager_email ?>"><?= $manager_email ?></a></div><?php } ?>
                    </span>
                </div>
            <?php } ?>
            <?php if ($spend) { ?>
                <div class="itflow-glance-item" title="Spent with them">
                    <i class="fas fa-fw fa-receipt"></i>
                    <span><?= numfmt_format_currency($currency_format, $spend['year'], $session_company_currency) ?> this year<?php if ($spend['last']) { ?> <span class="text-body-secondary">&middot; last <?= escapeHtml($spend['last']) ?></span><?php } ?></span>
                </div>
            <?php } ?>
        </div>
    </div>

    <?php if ($vendor_dial || $vendor_email || $vendor_website_url || ($client_id && $can_support_edit && !$vendor_archived)) { ?>
        <div class="d-flex flex-wrap gap-2 mt-3">
            <?php if ($vendor_dial) { ?>
                <a class="btn btn-sm btn-outline-primary" href="tel:<?= escapeHtml($vendor_dial) ?><?= $vendor['vendor_extension'] ? ',' . escapeHtml($vendor['vendor_extension']) : '' ?>"><i class="fas fa-phone me-1"></i>Call support</a>
            <?php } ?>
            <?php if ($vendor_email) { ?>
                <a class="btn btn-sm btn-outline-primary" href="mailto:<?= $vendor_email ?>"><i class="fas fa-envelope me-1"></i>Email</a>
            <?php } ?>
            <?php if ($vendor_website_url) { ?>
                <a class="btn btn-sm btn-outline-primary" href="<?= escapeHtml($vendor_website_url) ?>" target="_blank" rel="noopener"><i class="fas fa-external-link-alt me-1"></i>Support site</a>
            <?php } ?>
            <?php if ($client_id && $can_support_edit && !$vendor_archived) { ?>
                <a class="btn btn-sm btn-outline-secondary ajax-modal" href="#" data-modal-size="lg" data-modal-url="modals/ticket/ticket_add.php?client_id=<?= $client_id ?>&vendor_id=<?= $vendor_id ?>"><i class="fas fa-life-ring me-1"></i>New case</a>
            <?php } ?>
        </div>
    <?php } ?>

    <?php if ($can_edit || $vendor_notes) { ?>
        <label class="form-label small text-body-secondary mt-3 mb-1"><i class="fas fa-sticky-note me-1"></i>Notes</label>
        <textarea class="form-control form-control-sm itflow-vendor-notes" rows="3" placeholder="Escalation path, who may call them, circuit IDs - saves when you click away" aria-label="Notes"
            data-vendor-id="<?= $vendor_id ?>" data-csrf="<?= escapeHtml($_SESSION['csrf_token'] ?? '') ?>" <?= $can_edit ? '' : 'readonly' ?>><?= $vendor_notes ?></textarea>
    <?php } ?>

    <?php if ($quick_tabs) { ?>
        <div class="itflow-asset-related itflow-asset-quick-related mt-3" data-vendor-quick="<?= $vendor_id ?>">
            <ul class="nav nav-pills itflow-asset-pills mb-2" role="tablist">
                <?php foreach ($quick_tabs as $tab_key => $tab) { ?>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link text-nowrap <?= $tab_key === $first_tab ? 'active' : '' ?>" data-bs-toggle="pill" data-bs-target="#<?= $quick_id ?>-<?= $tab_key ?>" data-vendor-quick-tab="<?= $tab_key ?>" type="button" role="tab">
                            <?= $tab['label'] ?> <span class="badge rounded-pill ms-1"><?= $tab['count'] ?></span>
                        </button>
                    </li>
                <?php } ?>
            </ul>

            <div class="tab-content">
                <?php if ($cases) { ?>
                    <div class="tab-pane fade <?= $first_tab === 'cases' ? 'show active' : '' ?>" id="<?= $quick_id ?>-cases" role="tabpanel">
                        <?= ticketTableHtml($cases, ['compact' => true, 'vendor_ref' => true]) ?>
                    </div>
                <?php } ?>

                <?php foreach (['licenses' => ['software', $licenses], 'logins' => ['credential', $logins]] as $pane => [$to, $rows]) {
                    if (!$rows) {
                        continue;
                    } ?>
                    <div class="tab-pane fade <?= $first_tab === $pane ? 'show active' : '' ?>" id="<?= $quick_id ?>-<?= $pane ?>" role="tabpanel">
                        <ul class="list-unstyled mb-0 itflow-links">
                            <?php foreach ($rows as $row) { ?>
                                <li class="itflow-link-row"><?= linkRowHtml($to, $row, $client_id) ?></li>
                            <?php } ?>
                        </ul>
                    </div>
                <?php } ?>

                <?php if ($assets) { ?>
                    <div class="tab-pane fade <?= $first_tab === 'assets' ? 'show active' : '' ?>" id="<?= $quick_id ?>-assets" role="tabpanel">
                        <ul class="list-unstyled mb-0 itflow-links">
                            <?php foreach ($assets as $asset) {
                                $warranty = $asset['asset_warranty_expire'] ? strtotime($asset['asset_warranty_expire']) : 0; ?>
                                <li class="itflow-link-row">
                                    <?= linkRowHtml('asset', $asset, $client_id) ?>
                                    <?php if ($warranty) { ?>
                                        <span class="small text-nowrap <?= $warranty < time() ? 'text-danger' : 'text-body-secondary' ?>" title="Warranty"><?= $warranty < time() ? 'Out of warranty' : 'Warranty to ' . escapeHtml($asset['asset_warranty_expire']) ?></span>
                                    <?php } ?>
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
    <a href="<?= $page_url ?>" class="btn btn-primary text-bold"><i class="fas fa-arrow-right me-2"></i>Open vendor</a>
    <?php if ($can_edit) { ?>
        <button type="button" class="btn btn-outline-secondary ajax-modal" data-modal-size="lg" data-modal-url="modals/vendor/vendor_edit.php?id=<?= $vendor_id ?>"><i class="fas fa-edit me-2"></i>Edit</button>
    <?php } ?>
    <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fa fa-times me-2"></i>Close</button>
</div>

<script>
    (function () {
        // Notes save when you click away - this modal's own textarea
        document.querySelectorAll('.modal .itflow-vendor-notes:not([data-wired])').forEach(function (notes) {
            notes.dataset.wired = '1';
            var saved = notes.value;
            notes.addEventListener('blur', function () {
                if (notes.readOnly || notes.value === saved) {
                    return;
                }
                saved = notes.value;
                itflowPost('ajax.php', {
                    vendor_set_notes: 'TRUE',
                    csrf_token: notes.dataset.csrf,
                    vendor_id: notes.dataset.vendorId,
                    notes: notes.value
                });
            });
        });

        // Open on the tab used last time, if this vendor has one
        var key = 'itflow.vendorQuickTab';
        document.querySelectorAll('[data-vendor-quick]:not([data-wired])').forEach(function (box) {
            box.dataset.wired = '1';
            var remembered = null;
            try {
                remembered = localStorage.getItem(key);
            } catch (e) {}
            var tab = remembered && box.querySelector('[data-vendor-quick-tab="' + remembered + '"]');
            if (tab && !tab.classList.contains('active')) {
                bootstrap.Tab.getOrCreateInstance(tab).show();
            }
            box.addEventListener('shown.bs.tab', function (e) {
                try {
                    localStorage.setItem(key, e.target.dataset.vendorQuickTab);
                } catch (err) {}
            });
        });
    })();
</script>

<?php
require_once '../../../includes/modal_footer.php';
