<?php

/*
 * Location quick look - the location page in a modal, opened from the
 * locations list, tickets, networks, racks and search. How to get there and
 * get in (directions, phone, hours, who to ask for, the access notes), then
 * what is on site in tabs - only those with something in them.
 */

require_once '../../../includes/modal_header.php';

enforceUserPermission('module_client');

$location_id = intval($_GET['id'] ?? 0);
$location = locationRow($location_id);
if (!$location) {
    echo json_encode(['error' => 'That location no longer exists.']);
    exit;
}
$client_id = intval($location['location_client_id']);
enforceClientAccess();

$location_name = escapeHtml($location['location_name']);
$location_address_text = locationAddressText($location, true);
$location_map_url = locationMapUrl($location);
$location_directions_url = locationDirectionsUrl($location);
$location_phone = contactPhoneLink($location['location_phone'], $location['location_phone_country_code'], $location['location_phone_extension']);
$location_hours = escapeHtml($location['location_hours']);
$location_notes = escapeHtml($location['location_notes']);
$location_archived = !empty($location['location_archived_at']);

$can_edit = lookupUserPermission('module_client') >= 2 && !$location_archived;
$can_support = lookupUserPermission('module_support') >= 1;

$people = $assets = $networks = [];
$open_tickets = [];
$sql = mysqli_query($mysqli, "SELECT contact_id, contact_name, contact_title, contact_photo, contact_client_id, contact_phone, contact_phone_country_code, contact_extension
    FROM contacts WHERE contact_location_id = $location_id AND contact_archived_at IS NULL ORDER BY contact_primary DESC, contact_name ASC");
while ($row = mysqli_fetch_assoc($sql)) {
    $people[] = $row;
}
if ($can_support) {
    $sql = mysqli_query($mysqli, "SELECT asset_id, asset_name, asset_type, asset_make, asset_model, interface_ip FROM assets
        LEFT JOIN asset_interfaces ON interface_asset_id = asset_id AND interface_primary = 1
        WHERE asset_location_id = $location_id AND asset_archived_at IS NULL ORDER BY asset_type ASC, asset_name ASC");
    while ($row = mysqli_fetch_assoc($sql)) {
        $assets[] = $row;
    }
    $sql = mysqli_query($mysqli, "SELECT network_id, network_name, network, network_vlan, network_gateway FROM networks
        WHERE network_location_id = $location_id AND network_archived_at IS NULL ORDER BY network_name ASC");
    while ($row = mysqli_fetch_assoc($sql)) {
        $networks[] = $row;
    }
    $open_tickets = ticketRows("tickets.ticket_location_id = $location_id AND " . TICKET_OPEN_SQL);
}

$quick_tabs = array_filter([
    'people' => ['label' => 'People', 'count' => count($people)],
    'assets' => ['label' => 'Assets', 'count' => count($assets)],
    'networks' => ['label' => 'Networks', 'count' => count($networks)],
    'tickets' => ['label' => 'Open tickets', 'count' => count($open_tickets)],
], function ($tab) {
    return $tab['count'] > 0;
});
$first_tab = array_key_first($quick_tabs);
$quick_id = "location-quick-$location_id";

ob_start();

?>

<div class="modal-header bg-dark" data-bs-theme="dark">
    <div class="d-flex align-items-center gap-3 min-w-0">
        <?= locationTile($location) ?>
        <div class="min-w-0">
            <h5 class="modal-title d-flex flex-wrap align-items-center column-gap-2 row-gap-1">
                <span class="fw-semibold text-break"><?= $location_name ?></span>
                <?php if (intval($location['location_primary'])) { ?><span class="badge rounded-pill text-bg-primary fw-normal fs-6">Primary</span><?php } ?>
                <?php if ($location_archived) { ?><span class="badge rounded-pill text-bg-secondary fw-normal fs-6">Archived</span><?php } ?>
            </h5>
            <div class="small text-body-secondary d-flex flex-wrap column-gap-3 mt-1">
                <span><?= escapeHtml($location['client_name']) ?></span>
                <?php if ($location['location_description']) { ?><span><?= escapeHtml($location['location_description']) ?></span><?php } ?>
            </div>
        </div>
    </div>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
</div>

<div class="modal-body">

    <!-- Getting there - the icons say what each line is -->
    <div class="row g-2 itflow-asset-glance">
        <div class="col-sm-6">
            <div class="itflow-glance-item" title="Address">
                <i class="fas fa-fw fa-map-marker-alt"></i>
                <?php if ($location_address_text !== '') { ?>
                    <span class="min-w-0">
                        <a class="text-reset" href="<?= escapeHtml($location_map_url) ?>" target="_blank" rel="noopener" title="Open in maps"><?= escapeHtml($location_address_text) ?></a><button type="button" class="itflow-copy clipboardjs" title="Copy address" data-clipboard-text="<?= escapeHtml($location_address_text) ?>"><i class="far fa-copy"></i></button>
                    </span>
                <?php } else { ?>
                    <span class="text-body-secondary">No address</span>
                <?php } ?>
            </div>
            <?php if ($location_hours) { ?>
                <div class="itflow-glance-item" title="Hours">
                    <i class="fas fa-fw fa-clock"></i>
                    <span><?= $location_hours ?></span>
                </div>
            <?php } ?>
        </div>
        <div class="col-sm-6">
            <?php if ($location_phone) { ?>
                <div class="itflow-glance-item" title="Phone">
                    <i class="fas fa-fw fa-phone"></i>
                    <span><?= $location_phone ?></span>
                </div>
            <?php } ?>
            <?php if ($location['contact_id']) {
                $onsite_phone = contactPhoneLink($location['contact_mobile'] ?: $location['contact_phone'], $location['contact_mobile'] ? $location['contact_mobile_country_code'] : $location['contact_phone_country_code']); ?>
                <div class="itflow-glance-item" title="Ask for">
                    <i class="fas fa-fw fa-user"></i>
                    <span class="min-w-0"><?= contactLink($client_id, $location['contact_id'], $location['contact_name'], $location['contact_photo']) ?><?php if ($onsite_phone) { ?> <span class="text-body-secondary">&middot;</span> <?= $onsite_phone ?><?php } ?></span>
                </div>
            <?php } ?>
        </div>
    </div>

    <?php if ($location_directions_url) { ?>
        <div class="d-flex flex-wrap gap-2 mt-3">
            <a class="btn btn-sm btn-outline-primary" href="<?= escapeHtml($location_directions_url) ?>" target="_blank" rel="noopener"><i class="fas fa-directions me-1"></i>Directions</a>
        </div>
    <?php } ?>

    <?php if ($can_edit || $location_notes) { ?>
        <label class="form-label small text-body-secondary mt-3 mb-1"><i class="fas fa-door-open me-1"></i>Access notes</label>
        <textarea class="form-control form-control-sm itflow-location-notes" rows="3" placeholder="Parking, which door, codes, where the comms cupboard is - saves when you click away" aria-label="Access notes"
            data-location-id="<?= $location_id ?>" data-csrf="<?= escapeHtml($_SESSION['csrf_token'] ?? '') ?>" <?= $can_edit ? '' : 'readonly' ?>><?= $location_notes ?></textarea>
    <?php } ?>

    <?php if ($quick_tabs) { ?>
        <div class="itflow-asset-related itflow-asset-quick-related mt-3" data-location-quick="<?= $location_id ?>">
            <ul class="nav nav-pills itflow-asset-pills mb-2" role="tablist">
                <?php foreach ($quick_tabs as $tab_key => $tab) { ?>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link text-nowrap <?= $tab_key === $first_tab ? 'active' : '' ?>" data-bs-toggle="pill" data-bs-target="#<?= $quick_id ?>-<?= $tab_key ?>" data-location-quick-tab="<?= $tab_key ?>" type="button" role="tab">
                            <?= $tab['label'] ?> <span class="badge rounded-pill ms-1"><?= $tab['count'] ?></span>
                        </button>
                    </li>
                <?php } ?>
            </ul>

            <div class="tab-content">
                <?php if ($people) { ?>
                    <div class="tab-pane fade <?= $first_tab === 'people' ? 'show active' : '' ?>" id="<?= $quick_id ?>-people" role="tabpanel">
                        <ul class="list-unstyled mb-0 itflow-links">
                            <?php foreach ($people as $person) {
                                $person_phone = contactPhoneLink($person['contact_phone'], $person['contact_phone_country_code'], $person['contact_extension']); ?>
                                <li class="itflow-link-row">
                                    <?= contactAvatar($person['contact_client_id'], $person['contact_id'], $person['contact_name'], $person['contact_photo'], 'sm') ?>
                                    <div class="min-w-0 flex-grow-1">
                                        <div class="d-flex"><a class="ajax-modal text-truncate" href="#" data-modal-size="lg" data-modal-url="modals/contact/contact.php?id=<?= intval($person['contact_id']) ?>"><?= escapeHtml($person['contact_name']) ?></a></div>
                                        <?php if ($person['contact_title']) { ?><div class="small text-body-secondary text-truncate"><?= escapeHtml($person['contact_title']) ?></div><?php } ?>
                                    </div>
                                    <?php if ($person_phone) { ?><span class="small text-nowrap"><?= $person_phone ?></span><?php } ?>
                                </li>
                            <?php } ?>
                        </ul>
                    </div>
                <?php } ?>

                <?php if ($assets) { ?>
                    <div class="tab-pane fade <?= $first_tab === 'assets' ? 'show active' : '' ?>" id="<?= $quick_id ?>-assets" role="tabpanel">
                        <ul class="list-unstyled mb-0 itflow-links">
                            <?php foreach ($assets as $asset) {
                                $asset_ip = escapeHtml($asset['interface_ip']); ?>
                                <li class="itflow-link-row">
                                    <span class="itflow-asset-icon itflow-asset-icon-sm itflow-asset-tone-<?= assetTypeTone($asset['asset_type']) ?>" aria-hidden="true"><i class="fas fa-fw fa-<?= getAssetIcon($asset['asset_type']) ?>"></i></span>
                                    <div class="min-w-0 flex-grow-1">
                                        <div class="d-flex"><a class="ajax-modal text-truncate" href="#" data-modal-size="lg" data-modal-url="modals/asset/asset.php?id=<?= intval($asset['asset_id']) ?>"><?= escapeHtml($asset['asset_name']) ?></a></div>
                                        <div class="small text-body-secondary text-truncate"><?= escapeHtml(trim($asset['asset_make'] . ' ' . $asset['asset_model']) ?: $asset['asset_type']) ?></div>
                                    </div>
                                    <?php if ($asset_ip) { ?><span class="small text-nowrap font-monospace"><?= $asset_ip ?><?php if ($asset_ip !== 'DHCP') { ?><button type="button" class="itflow-copy clipboardjs" title="Copy IP" data-clipboard-text="<?= $asset_ip ?>"><i class="far fa-copy"></i></button><?php } ?></span><?php } ?>
                                </li>
                            <?php } ?>
                        </ul>
                    </div>
                <?php } ?>

                <?php if ($networks) { ?>
                    <div class="tab-pane fade <?= $first_tab === 'networks' ? 'show active' : '' ?>" id="<?= $quick_id ?>-networks" role="tabpanel">
                        <ul class="list-group list-group-flush">
                            <?php foreach ($networks as $network) { ?>
                                <li class="list-group-item px-0 d-flex justify-content-between gap-3">
                                    <a href="network.php?client_id=<?= $client_id ?>&network_id=<?= intval($network['network_id']) ?>"><?= escapeHtml($network['network_name']) ?></a>
                                    <span class="small text-body-secondary text-nowrap font-monospace"><?= escapeHtml($network['network']) ?><?= intval($network['network_vlan']) ? ' · VLAN ' . intval($network['network_vlan']) : '' ?><?= $network['network_gateway'] ? ' · gw ' . escapeHtml($network['network_gateway']) : '' ?></span>
                                </li>
                            <?php } ?>
                        </ul>
                    </div>
                <?php } ?>

                <?php if ($open_tickets) { ?>
                    <div class="tab-pane fade <?= $first_tab === 'tickets' ? 'show active' : '' ?>" id="<?= $quick_id ?>-tickets" role="tabpanel">
                        <?= ticketTableHtml($open_tickets, ['compact' => true]) ?>
                    </div>
                <?php } ?>
            </div>
        </div>
    <?php } ?>

</div>

<div class="modal-footer">
    <a href="location.php?client_id=<?= $client_id ?>&location_id=<?= $location_id ?>" class="btn btn-primary text-bold"><i class="fas fa-arrow-right me-2"></i>Open location</a>
    <?php if ($can_edit) { ?>
        <button type="button" class="btn btn-outline-secondary ajax-modal" data-modal-size="lg" data-modal-url="modals/location/location_edit.php?id=<?= $location_id ?>"><i class="fas fa-edit me-2"></i>Edit</button>
    <?php } ?>
    <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fa fa-times me-2"></i>Close</button>
</div>

<script>
    (function () {
        // Access notes save when you click away - this modal's own textarea
        document.querySelectorAll('.modal .itflow-location-notes:not([data-wired])').forEach(function (notes) {
            notes.dataset.wired = '1';
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

        // Open on the tab used last time, if this location has one
        var key = 'itflow.locationQuickTab';
        document.querySelectorAll('[data-location-quick]:not([data-wired])').forEach(function (box) {
            box.dataset.wired = '1';
            var remembered = null;
            try {
                remembered = localStorage.getItem(key);
            } catch (e) {}
            var tab = remembered && box.querySelector('[data-location-quick-tab="' + remembered + '"]');
            if (tab && !tab.classList.contains('active')) {
                bootstrap.Tab.getOrCreateInstance(tab).show();
            }
            box.addEventListener('shown.bs.tab', function (e) {
                try {
                    localStorage.setItem(key, e.target.dataset.locationQuickTab);
                } catch (err) {}
            });
        });
    })();
</script>

<?php
require_once '../../../includes/modal_footer.php';
