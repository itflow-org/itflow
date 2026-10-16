<?php

/*
 * Network quick look - from a list, a location, an asset's interface, global
 * search. The subnet, its gateway, DNS and DHCP range, the site and provider,
 * how full it is and what in its address plan wants checking; the network
 * page has the whole plan.
 */

require_once '../../../includes/modal_header.php';

$network = networkForModal($_GET['id'] ?? 0, 1);
$network_id = intval($network['network_id']);
$client_id = intval($network['network_client_id']);
$type = networkType($network);
$info = networkSubnetInfo($network['network']);
$addresses = networkAddresses([$network_id]);
$plan = networkAddressPlan($network, $addresses);
$usage = networkUsage([$network_id => $network], $addresses)[$network_id];
$can_edit = networkCan(2) && empty($network['network_archived_at']);
$dns = array_filter([$network['network_primary_dns'], $network['network_secondary_dns']]);
$flagged = array_values(array_filter($plan['rows'], function ($row) {
    return $row['kind'] === 'address' && $row['flags'];
}));

ob_start();

?>
<div class="modal-header bg-dark" data-bs-theme="dark">
    <div class="d-flex align-items-center gap-3 min-w-0">
        <?= networkTile($type) ?>
        <div class="min-w-0">
            <h5 class="modal-title fw-semibold text-break"><?= escapeHtml($network['network_name']) ?></h5>
            <div class="small text-body-secondary d-flex flex-wrap column-gap-3">
                <span class="font-monospace"><?= escapeHtml($network['network']) ?></span>
                <span><?= escapeHtml($type) ?><?= $network['network_vlan'] ? ' · VLAN ' . intval($network['network_vlan']) : '' ?></span>
                <span><?= escapeHtml($network['client_name']) ?></span>
            </div>
        </div>
    </div>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
</div>

<div class="modal-body">

    <?php if (!empty($network['network_archived_at'])) { ?>
        <div class="alert alert-secondary py-2 small"><i class="fas fa-archive me-2"></i>Archived <?= escapeHtml(timeAgo($network['network_archived_at'])) ?></div>
    <?php } ?>

    <div class="mb-3">
        <?= networkUsageBar($usage) ?>
        <div class="small text-body-secondary d-flex flex-wrap column-gap-3 mt-1">
            <span><i class="fas fa-fw fa-desktop me-1"></i><?= $usage['devices'] ?> device<?= $usage['devices'] === 1 ? '' : 's' ?></span>
            <span><i class="fas fa-fw fa-list-ol me-1"></i><?= $usage['documented'] ?> documented</span>
            <?php if ($plan['next_free']) { ?>
                <span>Next free <span class="font-monospace text-body"><?= escapeHtml($plan['next_free']) ?></span><button type="button" class="itflow-copy clipboardjs" title="Copy" data-clipboard-text="<?= escapeHtml($plan['next_free']) ?>"><i class="far fa-copy"></i></button></span>
            <?php } ?>
        </div>
    </div>

    <dl class="itflow-asset-dl mb-0">
        <?php if ($info) { ?>
            <dt>Addresses</dt>
            <dd><span class="font-monospace small"><?= escapeHtml($info['first_host']) ?> - <?= escapeHtml($info['last_host']) ?></span> <span class="small text-body-secondary"><?= escapeHtml(networkSizeText($info)) ?></span></dd>
        <?php } ?>
        <dt>Gateway</dt>
        <dd><?= $network['network_gateway'] ? '<span class="font-monospace">' . escapeHtml($network['network_gateway']) . '</span>' : '<span class="text-body-secondary">-</span>' ?></dd>
        <?php if ($dns) { ?>
            <dt>DNS</dt>
            <dd class="font-monospace"><?= escapeHtml(implode(', ', $dns)) ?></dd>
        <?php } ?>
        <?php if ($network['network_dhcp_range']) { ?>
            <dt>DHCP</dt>
            <dd><?= $plan['pool'] ? '<span class="font-monospace">' . escapeHtml(networkRangeText(intToIpv4($plan['pool'][0]), intToIpv4($plan['pool'][1]))) . '</span>' : escapeHtml($network['network_dhcp_range']) ?></dd>
        <?php } ?>
        <?php if ($network['location_name']) { ?>
            <dt>Site</dt>
            <dd><a class="ajax-modal" href="#" data-modal-size="lg" data-modal-url="modals/location/location.php?id=<?= intval($network['network_location_id']) ?>"><i class="fas fa-fw fa-map-marker-alt text-secondary me-1"></i><?= escapeHtml($network['location_name']) ?></a></dd>
        <?php } ?>
        <?php if ($network['vendor_name']) { ?>
            <dt><?= $type === 'WAN' ? 'Provider' : 'Vendor' ?></dt>
            <dd class="min-w-0">
                <?= vendorLink($network['network_vendor_id'], $network['vendor_name']) ?>
                <?php $phone = contactPhoneLink($network['vendor_phone'], $network['vendor_phone_country_code'], $network['vendor_extension']);
                if ($phone || $network['vendor_account_number']) { ?>
                    <div class="small d-flex flex-wrap column-gap-3">
                        <?php if ($phone) { ?><span><i class="fas fa-fw fa-phone text-secondary me-1"></i><?= $phone ?></span><?php } ?>
                        <?php if ($network['vendor_account_number']) { ?><span><i class="fas fa-fw fa-fingerprint text-secondary me-1"></i><span class="font-monospace"><?= escapeHtml($network['vendor_account_number']) ?></span></span><?php } ?>
                    </div>
                <?php } ?>
            </dd>
        <?php } ?>
        <?php if ($network['network_description']) { ?>
            <dt>About</dt>
            <dd><?= escapeHtml($network['network_description']) ?></dd>
        <?php } ?>
    </dl>

    <?php if ($flagged || $plan['outside']) { ?>
        <div class="small text-body-secondary fw-semibold text-uppercase mt-3 mb-1">To check</div>
        <ul class="list-unstyled small mb-0">
            <?php foreach (array_slice($flagged, 0, 5) as $row) { ?>
                <li class="text-<?= $row['flags'][0][0] ?>-emphasis"><i class="fas fa-fw fa-exclamation-triangle me-1"></i><span class="font-monospace"><?= escapeHtml($row['ip']) ?></span> - <?= escapeHtml($row['flags'][0][1]) ?></li>
            <?php } ?>
            <?php foreach (array_slice($plan['outside'], 0, 3) as $row) { ?>
                <li class="text-warning-emphasis"><i class="fas fa-fw fa-exclamation-triangle me-1"></i><?= escapeHtml($row['asset_name'] . ' ' . $row['interface_name']) ?> has <span class="font-monospace"><?= escapeHtml($row['interface_ip']) ?></span>, which <?= escapeHtml($row['problem']) ?></li>
            <?php } ?>
        </ul>
    <?php } ?>

    <?php if (trim((string) $network['network_notes']) !== '') { ?>
        <div class="small text-body-secondary fw-semibold text-uppercase mt-3 mb-1">Notes</div>
        <div class="itflow-service-text"><?= escapeHtml($network['network_notes']) ?></div>
    <?php } ?>

</div>

<div class="modal-footer">
    <a class="btn btn-outline-secondary me-auto" href="<?= escapeHtml(networkUrl($network_id, $client_id)) ?>"><i class="fas fa-external-link-alt me-2"></i>Open</a>
    <?php if ($can_edit) { ?>
        <button type="button" class="btn btn-outline-secondary ajax-modal" data-modal-url="modals/network/network_ip_add.php?network_id=<?= $network_id ?>&next=1"><i class="fas fa-plus me-2"></i>Document an address</button>
        <button type="button" class="btn btn-primary ajax-modal" data-modal-size="lg" data-modal-url="modals/network/network_edit.php?id=<?= $network_id ?>"><i class="fas fa-edit me-2"></i>Edit</button>
    <?php } ?>
</div>

<?php
require_once '../../../includes/modal_footer.php';
