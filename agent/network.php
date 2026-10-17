<?php

/*
 * Network - one subnet at a client: what it is for, its CIDR, VLAN, gateway,
 * DNS and DHCP range, the site it is at and (an internet connection) who
 * provides it, and how full it is.
 *
 * The address plan lays out every address in use, in order - documented ones
 * (fixed, reserved for a MAC, held back, floating) and the ones on the devices
 * set to this network - with the free runs between to pick from, and the DHCP
 * range as one band. Each address says what is wrong with it: two devices on
 * it, a fixed address inside the DHCP range, a reservation for one MAC on a
 * device with another. Devices lists the interfaces on the network and what
 * they plug into. The open tab follows the URL hash (#devices).
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

$network_id = intval($_GET['network_id'] ?? 0);
$network = networkRow($network_id);

if (!$network || ($client_url && intval($network['network_client_id']) !== intval($client_id))) {

    echo "<center><h1 class='text-secondary mt-5'>Nothing to see here</h1><a class='btn btn-lg btn-secondary mt-3' href='javascript:history.back()'><i class='fa fa-fw fa-arrow-left'></i> Go Back</a></center>";

} else {

    $client_id = intval($network['network_client_id']);
    enforceClientAccess();

    $network_name = escapeHtml($network['network_name']);
    $is_archived = !empty($network['network_archived_at']);
    $is_favorite = intval($network['network_favorite']) === 1;
    $can_edit = networkCan(2) && !$is_archived;
    $network_tags_display = tagPills('network', $network_id, tagsForRecord('network', $network_id), "networks.php?client_id=$client_id&", $can_edit);
    $can_restore = networkCan(2);
    $can_delete = networkCan(3) && !empty($config_destructive_deletes_enable);
    $csrf = $_SESSION['csrf_token'];

    // Override Tab Title // No Sanitizing needed as this var will only be used in the tab title
    $page_title = $network['network_name'];

    // Opened - without counting as a change
    mysqli_query($mysqli, "UPDATE networks SET network_accessed_at = NOW(), network_updated_at = network_updated_at WHERE network_id = $network_id");

    $type = networkType($network);
    $info = networkSubnetInfo($network['network']);
    $addresses = networkAddresses([$network_id]);
    $plan = networkAddressPlan($network, $addresses);
    $usage = networkUsage([$network_id => $network], $addresses)[$network_id];
    $overlaps = $is_archived ? [] : networkOverlaps($network);
    $interfaces = $addresses[$network_id]['interfaces'];
    $links = interfaceLinks(array_column($interfaces, 'interface_id'));
    $dns = array_filter([$network['network_primary_dns'], $network['network_secondary_dns']]);

    // Device addresses not documented yet - Document them adds each as a fixed address
    $undocumented = 0;
    foreach ($plan['rows'] as $row) {
        if ($row['kind'] === 'address' && $row['interfaces'] && !$row['documented']) {
            $undocumented++;
        }
    }
    $flagged = array_values(array_filter($plan['rows'], function ($row) {
        return $row['kind'] === 'address' && $row['flags'];
    }));

    $ip_add_url = function ($params = []) use ($network_id) {
        return 'modals/network/network_ip_add.php?' . http_build_query(['network_id' => $network_id] + $params);
    };

    $network_tabs = [
        'addresses' => ['label' => 'Address plan', 'icon' => 'fa-list-ol', 'count' => $usage['used']],
        'devices' => ['label' => 'Devices', 'icon' => 'fa-desktop', 'count' => $usage['devices']],
    ];

    ?>

    <ol class="breadcrumb d-print-none">
        <li class="breadcrumb-item">
            <a href="client_overview.php?client_id=<?= $client_id ?>"><?= escapeHtml($network['client_name']) ?></a>
        </li>
        <li class="breadcrumb-item">
            <a href="networks.php?client_id=<?= $client_id ?>">Networks</a>
        </li>
        <li class="breadcrumb-item active"><?= $network_name ?></li>
    </ol>

    <!-- What it is, where, how full -->
    <div class="card mb-3">
        <div class="card-body d-flex flex-wrap align-items-center gap-3">
            <?= networkTile($type, 'lg') ?>

            <div class="flex-grow-1 min-w-0">
                <h1 class="h4 fw-semibold mb-1 d-flex flex-wrap align-items-center gap-2">
                    <span><?= $network_name ?></span>
                    <?php if ($is_favorite) { ?><i class="fas fa-star text-warning fs-6" title="Starred"></i><?php } ?>
                    <?php if ($is_archived) { ?><span class="badge rounded-pill text-bg-secondary fw-normal fs-6">Archived</span><?php } ?>
                    <span class="fs-6 fw-normal"><?= $can_edit ? quickPick('network_type', $network_id, $type, networkTypeLabel($type)) : networkTypeLabel($type) ?></span>
                    <?php if ($network['network_vlan']) { ?><span class="itflow-net-vlan fs-6">VLAN <?= intval($network['network_vlan']) ?></span><?php } ?>
                </h1>
                <div class="text-body-secondary small d-flex flex-wrap align-items-center column-gap-3 row-gap-1">
                    <span class="font-monospace text-body fw-semibold"><?= escapeHtml($network['network']) ?><button type="button" class="itflow-copy clipboardjs" title="Copy subnet" data-clipboard-text="<?= escapeHtml($network['network']) ?>"><i class="far fa-copy"></i></button></span>
                    <?php if ($info) { ?><span><?= escapeHtml(networkSizeText($info)) ?></span><?php } ?>
                    <?php if ($network['location_name']) { ?>
                        <a class="text-reset ajax-modal" href="#" data-modal-size="lg" data-modal-url="modals/location/location.php?id=<?= intval($network['network_location_id']) ?>"><i class="fas fa-fw fa-map-marker-alt me-1"></i><?= escapeHtml($network['location_name']) ?></a>
                    <?php } ?>
                    <?php if ($network['network_description']) { ?><span><?= escapeHtml($network['network_description']) ?></span><?php } ?>
                </div>
                <?php if ($network_tags_display) { ?>
                    <div class="mt-2"><?= $network_tags_display ?></div>
                <?php } ?>
            </div>

            <div class="d-flex flex-wrap gap-2 d-print-none">
                <?php if ($can_edit) { ?>
                    <?php if ($plan['next_free']) { ?>
                        <div class="btn-group">
                            <button type="button" class="btn btn-outline-secondary clipboardjs" data-clipboard-text="<?= escapeHtml($plan['next_free']) ?>" title="The first address nothing has, outside the DHCP range - copy it">
                                <i class="fas fa-magic me-2"></i>Next free <span class="font-monospace ms-1"><?= escapeHtml($plan['next_free']) ?></span>
                            </button>
                            <button type="button" class="btn btn-outline-secondary ajax-modal" data-modal-url="<?= escapeHtml($ip_add_url(['ip' => $plan['next_free']])) ?>" title="Document it"><i class="fas fa-plus"></i></button>
                        </div>
                    <?php } ?>
                    <button type="button" class="btn btn-primary ajax-modal" data-modal-url="<?= escapeHtml($ip_add_url(['next' => 1])) ?>">
                        <i class="fas fa-plus me-2"></i>Document an address
                    </button>
                    <button type="button" class="btn btn-outline-secondary ajax-modal" data-modal-size="lg" data-modal-url="modals/network/network_edit.php?id=<?= $network_id ?>">
                        <i class="fas fa-edit me-2"></i>Edit
                    </button>
                <?php } ?>
                <div class="dropdown dropstart">
                    <button class="btn btn-outline-secondary" title="More actions" type="button" data-bs-toggle="dropdown">
                        <i class="fas fa-fw fa-ellipsis-v"></i>
                    </button>
                    <div class="dropdown-menu">
                        <?php if ($can_edit) { ?>
                            <?php if ($undocumented) { ?>
                                <form action="post.php" method="post" class="m-0">
                                    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                                    <input type="hidden" name="network_id" value="<?= $network_id ?>">
                                    <button type="submit" name="document_network_interfaces" class="dropdown-item confirm-link"
                                        data-confirm-title="Document <?= $undocumented ?> device address<?= $undocumented === 1 ? '' : 'es' ?>?" data-confirm-button="Document them"
                                        data-confirm-text="Each address a device has here, and is not documented yet, is added as a fixed address named for the device, with its MAC.">
                                        <i class="fas fa-fw fa-clipboard-list text-secondary me-2"></i>Document device addresses (<?= $undocumented ?>)
                                    </button>
                                </form>
                            <?php } ?>
                            <a class="dropdown-item ajax-modal" href="#" data-modal-url="modals/network/network_ip_import.php?network_id=<?= $network_id ?>">
                                <i class="fas fa-fw fa-upload text-secondary me-2"></i>Import addresses
                            </a>
                        <?php } ?>
                        <?php if ($usage['documented']) { ?>
                            <a class="dropdown-item ajax-modal" href="#" data-modal-url="modals/network/network_ip_export.php?network_id=<?= $network_id ?>">
                                <i class="fas fa-fw fa-download text-secondary me-2"></i>Export addresses
                            </a>
                        <?php } ?>
                        <?php if ($can_edit) { ?>
                            <a class="dropdown-item" href="post.php?<?= $is_favorite ? 'unfavorite_network' : 'favorite_network' ?>=<?= $network_id ?>&csrf_token=<?= $csrf ?>">
                                <i class="<?= $is_favorite ? 'far' : 'fas' ?> fa-fw fa-star text-secondary me-2"></i><?= $is_favorite ? 'Unstar' : 'Star' ?>
                            </a>
                        <?php } ?>
                        <?php if ($can_restore) { ?>
                            <div class="dropdown-divider"></div>
                            <?php if ($is_archived) { ?>
                                <a class="dropdown-item" href="post.php?restore_network=<?= $network_id ?>&csrf_token=<?= $csrf ?>">
                                    <i class="fas fa-fw fa-redo text-secondary me-2"></i>Restore
                                </a>
                                <?php if ($can_delete) { ?>
                                    <a class="dropdown-item text-danger text-bold confirm-link" href="post.php?delete_network=<?= $network_id ?>&csrf_token=<?= $csrf ?>"
                                        data-confirm-text="<?= $network_name ?> and the addresses documented on it go for good. The devices on it are left with no network.">
                                        <i class="fas fa-fw fa-trash me-2"></i>Delete
                                    </a>
                                <?php } ?>
                            <?php } else { ?>
                                <a class="dropdown-item text-danger confirm-link" href="post.php?archive_network=<?= $network_id ?>&csrf_token=<?= $csrf ?>"
                                    data-confirm-text="<?= $network_name ?> moves to Archived, off the lists and pickers, until restored. The devices on it keep it.">
                                    <i class="fas fa-fw fa-archive me-2"></i>Archive
                                </a>
                            <?php } ?>
                        <?php } ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php if (!$info) { ?>
        <div class="alert alert-warning d-flex gap-3 mb-3">
            <i class="fas fa-exclamation-triangle mt-1"></i>
            <div><strong><?= escapeHtml($network['network']) ?></strong> is not a subnet, so its addresses cannot be checked - <?= $can_edit ? '<a class="ajax-modal alert-link" href="#" data-modal-size="lg" data-modal-url="modals/network/network_edit.php?id=' . $network_id . '">write it as an address and prefix</a>' : 'it wants an address and prefix' ?>, like 192.168.1.0/24.</div>
        </div>
    <?php } ?>

    <?php if ($overlaps) { ?>
        <div class="alert alert-info d-flex gap-3 mb-3">
            <i class="fas fa-clone mt-1"></i>
            <div class="min-w-0">
                <div class="fw-semibold">Overlaps <?= count($overlaps) === 1 ? 'another network' : count($overlaps) . ' other networks' ?> at <?= escapeHtml($network['client_name']) ?></div>
                <div class="small">
                    <?php foreach ($overlaps as $i => $overlap) { ?>
                        <?= $i ? ' · ' : '' ?><?= networkLink($overlap['network_id'], $overlap['network_name'], $client_id, ['type' => networkType($overlap)]) ?>
                        <span class="font-monospace"><?= escapeHtml($overlap['network']) ?></span><?= $overlap['location_name'] ? ' at ' . escapeHtml($overlap['location_name']) : '' ?>
                    <?php } ?>
                </div>
                <div class="small text-body-secondary">The same range at another site is fine; at the same site, one of them is probably out of date.</div>
            </div>
        </div>
    <?php } ?>

    <?php if (($flagged || $plan['outside']) && !$is_archived) { ?>
        <div class="alert alert-warning d-flex gap-3 mb-3 itflow-service-alert">
            <i class="fas fa-exclamation-triangle mt-1"></i>
            <div class="min-w-0">
                <div class="fw-semibold mb-1"><?= $plan['problems'] ?> thing<?= $plan['problems'] === 1 ? '' : 's' ?> to check in the address plan</div>
                <ul class="mb-0 ps-3 small">
                    <?php foreach (array_slice($flagged, 0, 4) as $row) { ?>
                        <li><span class="font-monospace"><?= escapeHtml($row['ip']) ?></span> - <?= escapeHtml($row['flags'][0][1]) ?></li>
                    <?php } ?>
                    <?php foreach (array_slice($plan['outside'], 0, max(0, 4 - count($flagged))) as $row) { ?>
                        <li><?= escapeHtml($row['asset_name'] . ' ' . $row['interface_name']) ?> has <span class="font-monospace"><?= escapeHtml($row['interface_ip']) ?></span>, which <?= escapeHtml($row['problem']) ?></li>
                    <?php } ?>
                    <?php if ($plan['problems'] > 4) { ?><li class="list-unstyled text-body-secondary">and <?= $plan['problems'] - 4 ?> more below</li><?php } ?>
                </ul>
            </div>
        </div>
    <?php } ?>

    <div class="row g-3">

        <!-- The facts, the provider, the notes -->
        <div class="col-xl-4 col-lg-5">

            <div class="card mb-3">
                <div class="card-header">
                    <h3 class="card-title mb-0"><i class="fas fa-fw fa-network-wired text-secondary me-2"></i>Network</h3>
                </div>
                <div class="card-body">
                    <div class="mb-3"><?= networkUsageBar($usage) ?>
                        <div class="small text-body-secondary d-flex flex-wrap column-gap-3 mt-1">
                            <span><i class="fas fa-fw fa-desktop me-1"></i><?= $usage['devices'] ?> device<?= $usage['devices'] === 1 ? '' : 's' ?></span>
                            <span><i class="fas fa-fw fa-list-ol me-1"></i><?= $usage['documented'] ?> documented</span>
                            <?php if ($plan['free'] !== null) { ?><span title="Free addresses outside the DHCP range"><i class="fas fa-fw fa-circle-notch me-1"></i><?= number_format($plan['free']) ?> free</span><?php } ?>
                        </div>
                    </div>
                    <dl class="itflow-asset-dl mb-0">
                        <?php if ($info) { ?>
                            <dt>Addresses</dt>
                            <dd class="font-monospace small"><?= escapeHtml($info['first_host']) ?> - <?= escapeHtml($info['last_host']) ?></dd>
                            <?php if ($info['family'] === 4) { ?>
                                <dt>Mask</dt><dd class="font-monospace small"><?= escapeHtml($info['mask']) ?> <span class="text-body-secondary">/<?= $info['prefix'] ?></span></dd>
                            <?php } ?>
                        <?php } ?>
                        <dt>Gateway</dt>
                        <dd><?= $network['network_gateway'] ? '<span class="font-monospace">' . escapeHtml($network['network_gateway']) . '</span><button type="button" class="itflow-copy clipboardjs" title="Copy" data-clipboard-text="' . escapeHtml($network['network_gateway']) . '"><i class="far fa-copy"></i></button>' : '<span class="text-body-secondary">-</span>' ?></dd>
                        <dt>DNS</dt>
                        <dd><?php if ($dns) {
                            foreach ($dns as $server) { ?><div class="font-monospace"><?= escapeHtml($server) ?><button type="button" class="itflow-copy clipboardjs" title="Copy" data-clipboard-text="<?= escapeHtml($server) ?>"><i class="far fa-copy"></i></button></div><?php }
                        } else { ?><span class="text-body-secondary">-</span><?php } ?></dd>
                        <dt>DHCP</dt>
                        <dd>
                            <?php if ($network['network_dhcp_range']) { ?>
                                <span class="<?= $plan['pool'] ? 'font-monospace' : '' ?>"><?= escapeHtml($plan['pool'] ? networkRangeText(intToIpv4($plan['pool'][0]), intToIpv4($plan['pool'][1])) : $network['network_dhcp_range']) ?></span>
                                <?php if ($plan['pool']) { ?><div class="small text-body-secondary"><?= number_format($plan['pool'][1] - $plan['pool'][0] + 1) ?> addresses</div><?php } ?>
                            <?php } else { ?>
                                <span class="text-body-secondary">None written down</span>
                            <?php } ?>
                        </dd>
                        <?php if ($network['network_vlan']) { ?><dt>VLAN</dt><dd><?= intval($network['network_vlan']) ?></dd><?php } ?>
                        <dt>Site</dt>
                        <dd><?= $network['location_name'] ? '<a class="ajax-modal" href="#" data-modal-size="lg" data-modal-url="modals/location/location.php?id=' . intval($network['network_location_id']) . '"><i class="fas fa-fw fa-map-marker-alt text-secondary me-1"></i>' . escapeHtml($network['location_name']) . '</a>' : '<span class="text-body-secondary">Not set</span>' ?></dd>
                        <?php if ($network['vendor_name']) { ?>
                            <dt><?= $type === 'WAN' ? 'Provider' : 'Vendor' ?></dt>
                            <dd class="min-w-0">
                                <?= vendorLink($network['network_vendor_id'], $network['vendor_name'], ['archived' => !empty($network['vendor_archived_at'])]) ?>
                                <div class="small d-flex flex-wrap column-gap-3">
                                    <?php $phone = contactPhoneLink($network['vendor_phone'], $network['vendor_phone_country_code'], $network['vendor_extension']);
                                    if ($phone) { ?><span><i class="fas fa-fw fa-phone text-secondary me-1"></i><?= $phone ?></span><?php } ?>
                                    <?php if ($network['vendor_account_number']) { ?><span title="Account number"><i class="fas fa-fw fa-fingerprint text-secondary me-1"></i><span class="font-monospace"><?= escapeHtml($network['vendor_account_number']) ?></span><button type="button" class="itflow-copy clipboardjs" title="Copy account number" data-clipboard-text="<?= escapeHtml($network['vendor_account_number']) ?>"><i class="far fa-copy"></i></button></span><?php } ?>
                                </div>
                                <?php if ($network['vendor_hours']) { ?><div class="small text-body-secondary"><i class="fas fa-fw fa-clock me-1"></i><?= escapeHtml($network['vendor_hours']) ?></div><?php } ?>
                            </dd>
                        <?php } ?>
                        <dt>Added</dt><dd title="<?= escapeHtml($network['network_created_at']) ?>"><?= timeAgo($network['network_created_at']) ?></dd>
                        <?php if ($network['network_updated_at']) { ?>
                            <dt>Updated</dt><dd title="<?= escapeHtml($network['network_updated_at']) ?>"><?= timeAgo($network['network_updated_at']) ?></dd>
                        <?php } ?>
                    </dl>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header">
                    <h3 class="card-title mb-0"><i class="fas fa-fw fa-pen text-secondary me-2"></i>Notes</h3>
                </div>
                <textarea class="form-control border-0 rounded-0 rounded-bottom itflow-service-notes" rows="7" placeholder="Firewall rules, what it can reach, the switch ports it is on, the DHCP server - saves when you click away"
                    data-network-id="<?= $network_id ?>" data-csrf="<?= escapeHtml($csrf) ?>" <?= $can_edit ? '' : 'readonly' ?>><?= escapeHtml($network['network_notes']) ?></textarea>
            </div>

        </div>

        <!-- What is on it -->
        <div class="col-xl-8 col-lg-7">
            <div class="card mb-3 itflow-asset-related">
                <div class="card-header py-2">
                    <ul class="nav nav-pills itflow-asset-pills" role="tablist">
                        <?php foreach ($network_tabs as $tab_key => $tab) { ?>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link text-nowrap <?= $tab['count'] ? '' : 'is-empty' ?>" id="network-tab-<?= $tab_key ?>" data-bs-toggle="pill" data-bs-target="#network-pane-<?= $tab_key ?>" data-network-tab="<?= $tab_key ?>" type="button" role="tab">
                                    <i class="fas fa-fw <?= $tab['icon'] ?> me-1"></i><?= $tab['label'] ?>
                                    <span class="badge rounded-pill ms-1"><?= $tab['count'] ?></span>
                                </button>
                            </li>
                        <?php } ?>
                    </ul>
                </div>
                <div class="tab-content">

                    <!-- Every address in use, in order, with the free runs between -->
                    <div class="tab-pane fade" id="network-pane-addresses" role="tabpanel">
                        <div class="itflow-asset-pane-bar flex-wrap">
                            <div class="input-group input-group-sm itflow-net-plan-search">
                                <span class="input-group-text"><i class="fas fa-fw fa-search"></i></span>
                                <input type="search" class="form-control" id="networkPlanFilter" placeholder="Find an address, hostname, MAC or device" value="<?= escapeHtml(trim((string) ($_GET['q'] ?? ''))) ?>">
                            </div>
                            <?php if ($plan['free'] !== null) { ?>
                                <div class="form-check form-switch small mb-0">
                                    <input class="form-check-input" type="checkbox" id="networkPlanFree" checked>
                                    <label class="form-check-label" for="networkPlanFree">Free addresses</label>
                                </div>
                            <?php } ?>
                            <?php if ($can_edit) { ?>
                                <div class="dropdown ms-auto" id="bulkActionButton" hidden>
                                    <button class="btn btn-sm btn-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                        <i class="fas fa-fw fa-layer-group me-1"></i>Selected (<span id="selectedCount">0</span>)
                                    </button>
                                    <div class="dropdown-menu dropdown-menu-end">
                                        <h6 class="dropdown-header">Make them</h6>
                                        <?php foreach (NETWORK_IP_TYPES as $ip_type => [$icon, $tone, $hint, $label]) { ?>
                                            <button class="dropdown-item" type="submit" form="networkIpBulk" name="bulk_ip_type" value="<?= $ip_type ?>" title="<?= escapeHtml($hint) ?>">
                                                <i class="fas fa-fw <?= $icon ?> text-secondary me-2"></i><?= $label ?>
                                            </button>
                                        <?php } ?>
                                        <div class="dropdown-divider"></div>
                                        <button class="dropdown-item text-danger confirm-link" type="submit" form="networkIpBulk" name="bulk_delete_network_ips"
                                            data-confirm-text="The addresses are no longer documented. The devices that have them keep them.">
                                            <i class="fas fa-fw fa-trash me-2"></i>Delete
                                        </button>
                                    </div>
                                </div>
                            <?php } ?>
                        </div>

                        <?php if ($plan['rows']) { ?>
                            <form id="networkIpBulk" action="post.php" method="post">
                                <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                                <div class="table-responsive">
                                    <table class="table table-sm table-hover align-middle mb-0 itflow-net-plan">
                                        <thead class="table-light text-nowrap">
                                        <tr>
                                            <?php if ($can_edit) { ?><th class="checkbox-column"><div class="form-check"><input class="form-check-input" id="selectAllCheckbox" type="checkbox" onclick="checkAll(this)" title="Select all documented"></div></th><?php } ?>
                                            <th>Address</th>
                                            <th>What has it</th>
                                            <th></th>
                                        </tr>
                                        </thead>
                                        <tbody>
                                        <?php foreach ($plan['rows'] as $row) {
                                            if ($row['kind'] === 'free') { ?>
                                                <tr class="itflow-net-free" data-plan-free>
                                                    <?php if ($can_edit) { ?><td></td><?php } ?>
                                                    <td class="font-monospace small text-nowrap"><?= escapeHtml(networkRangeText($row['start'], $row['end'])) ?></td>
                                                    <td class="small text-body-secondary"><i class="fas fa-fw fa-circle-notch me-1"></i><?= number_format($row['count']) ?> free</td>
                                                    <td class="text-end">
                                                        <?php if ($can_edit) { ?>
                                                            <button type="button" class="btn btn-sm btn-link py-0 ajax-modal" data-modal-url="<?= escapeHtml($ip_add_url(['ip' => $row['start']])) ?>" title="Document <?= escapeHtml($row['start']) ?>">Use</button>
                                                        <?php } ?>
                                                    </td>
                                                </tr>
                                            <?php } elseif ($row['kind'] === 'pool') { ?>
                                                <tr class="itflow-net-pool">
                                                    <?php if ($can_edit) { ?><td></td><?php } ?>
                                                    <td class="font-monospace small text-nowrap"><?= escapeHtml(networkRangeText($row['start'], $row['end'])) ?></td>
                                                    <td colspan="2" class="small"><i class="fas fa-fw fa-random me-1"></i><strong>DHCP range</strong> - <?= number_format($row['count']) ?> addresses handed out as needed<?= $row['used'] ? ', ' . $row['used'] . ' of them below' : '' ?></td>
                                                </tr>
                                            <?php } else {
                                                $first_doc = $row['documented'][0] ?? null;
                                                $mac = $first_doc && $first_doc['ip_mac'] ? $first_doc['ip_mac'] : ($row['interfaces'][0]['interface_mac'] ?? '');
                                                $search = strtolower(implode(' ', array_merge([$row['ip'], $mac],
                                                    array_column($row['documented'], 'ip_hostname'), array_column($row['documented'], 'ip_description'),
                                                    array_column($row['interfaces'], 'asset_name'), array_column($row['interfaces'], 'interface_name')))); ?>
                                                <tr class="<?= $row['in_pool'] ? 'is-in-pool' : '' ?> <?= $row['flags'] ? 'itflow-net-flagged' : '' ?>" data-plan-search="<?= escapeHtml($search) ?>">
                                                    <?php if ($can_edit) { ?>
                                                        <td class="checkbox-column">
                                                            <?php if ($first_doc) { ?>
                                                                <div class="form-check"><input class="form-check-input bulk-select" type="checkbox" name="network_ip_ids[]" value="<?= intval($first_doc['ip_id']) ?>" aria-label="Select <?= escapeHtml($row['ip']) ?>"></div>
                                                            <?php } ?>
                                                        </td>
                                                    <?php } ?>
                                                    <td class="text-nowrap itflow-net-plan-ip">
                                                        <span class="font-monospace fw-semibold"><?= escapeHtml($row['ip']) ?></span><button type="button" class="itflow-copy clipboardjs" title="Copy" data-clipboard-text="<?= escapeHtml($row['ip']) ?>"><i class="far fa-copy"></i></button>
                                                        <?php if ($row['gateway']) { ?><span class="badge rounded-pill bg-primary-subtle text-primary-emphasis fw-normal ms-1">Gateway</span><?php } ?>
                                                    </td>
                                                    <td class="min-w-0">
                                                        <?php foreach ($row['documented'] as $doc) { ?>
                                                            <div class="d-flex flex-wrap align-items-center column-gap-2">
                                                                <?= networkIpTypePill($doc['ip_type']) ?>
                                                                <?php if ($can_edit) { ?>
                                                                    <a class="fw-semibold text-body ajax-modal" href="#" data-modal-url="modals/network/network_ip_edit.php?id=<?= intval($doc['ip_id']) ?>"><?= escapeHtml($doc['ip_hostname'] ?: 'No hostname') ?></a>
                                                                <?php } else { ?>
                                                                    <span class="fw-semibold"><?= escapeHtml($doc['ip_hostname'] ?: 'No hostname') ?></span>
                                                                <?php } ?>
                                                                <?php if ($doc['ip_description']) { ?><span class="small text-body-secondary text-truncate"><?= escapeHtml($doc['ip_description']) ?></span><?php } ?>
                                                                <?php if ($doc['ip_mac']) { ?><span class="small font-monospace text-body-secondary" title="MAC"><?= escapeHtml($doc['ip_mac']) ?></span><?php } ?>
                                                            </div>
                                                        <?php } ?>
                                                        <?php foreach ($row['interfaces'] as $interface) { ?>
                                                            <div class="d-flex flex-wrap align-items-center column-gap-2 small">
                                                                <?= interfaceAssetRef($interface['asset_id'], $interface['asset_name'], $interface['asset_type']) ?>
                                                                <span class="text-body-secondary"><i class="fas fa-ethernet fa-fw opacity-75"></i><?= escapeHtml($interface['interface_name']) ?></span>
                                                                <?php if ($interface['interface_mac'] && !$row['documented']) { ?><span class="font-monospace text-body-secondary" title="MAC"><?= escapeHtml($interface['interface_mac']) ?></span><?php } ?>
                                                            </div>
                                                        <?php } ?>
                                                        <?php if (!$row['documented'] && !$row['interfaces'] && $row['gateway']) { ?>
                                                            <span class="small text-body-secondary">The gateway - the router or firewall</span>
                                                        <?php } ?>
                                                        <?php foreach ($row['flags'] as [$tone, $text]) { ?>
                                                            <div class="small text-<?= $tone ?>-emphasis"><i class="fas fa-fw fa-exclamation-<?= $tone === 'danger' ? 'circle' : 'triangle' ?> me-1"></i><?= escapeHtml($text) ?></div>
                                                        <?php } ?>
                                                    </td>
                                                    <td class="text-end text-nowrap">
                                                        <?php if ($can_edit) {
                                                            if ($first_doc) { ?>
                                                                <div class="dropdown dropstart">
                                                                    <button class="btn btn-sm btn-light" type="button" data-bs-toggle="dropdown" title="More actions"><i class="fas fa-fw fa-ellipsis-v"></i></button>
                                                                    <div class="dropdown-menu">
                                                                        <a class="dropdown-item ajax-modal" href="#" data-modal-url="modals/network/network_ip_edit.php?id=<?= intval($first_doc['ip_id']) ?>"><i class="fas fa-fw fa-edit text-secondary me-2"></i>Edit</a>
                                                                        <div class="dropdown-divider"></div>
                                                                        <a class="dropdown-item text-danger confirm-link" href="post.php?delete_network_ip=<?= intval($first_doc['ip_id']) ?>&csrf_token=<?= $csrf ?>"
                                                                            data-confirm-text="<?= escapeHtml($row['ip']) ?> is no longer documented<?= $row['interfaces'] ? ' - the device that has it keeps it' : '' ?>."><i class="fas fa-fw fa-trash me-2"></i>Delete</a>
                                                                    </div>
                                                                </div>
                                                            <?php } elseif ($row['interfaces']) {
                                                                $interface = $row['interfaces'][0]; ?>
                                                                <button type="button" class="btn btn-sm btn-link py-0 ajax-modal" title="Document it - from the device"
                                                                    data-modal-url="<?= escapeHtml($ip_add_url(['ip' => $row['ip'], 'hostname' => $interface['asset_name'], 'mac' => $interface['interface_mac'], 'description' => $interface['interface_name']])) ?>">Document</button>
                                                            <?php } elseif ($row['gateway']) { ?>
                                                                <button type="button" class="btn btn-sm btn-link py-0 ajax-modal" data-modal-url="<?= escapeHtml($ip_add_url(['ip' => $row['ip'], 'description' => 'Gateway'])) ?>">Document</button>
                                                            <?php }
                                                        } ?>
                                                    </td>
                                                </tr>
                                            <?php }
                                        } ?>
                                        </tbody>
                                    </table>
                                </div>
                            </form>
                            <div class="itflow-asset-empty d-none" id="networkPlanNone">Nothing on this network matches.</div>
                        <?php } else { ?>
                            <div class="itflow-asset-empty">
                                Nothing documented and no device on it yet.
                                <?php if ($can_edit) { ?><div class="mt-2"><button type="button" class="btn btn-sm btn-primary ajax-modal" data-modal-url="<?= escapeHtml($ip_add_url(['next' => 1])) ?>"><i class="fas fa-plus me-2"></i>Document an address</button></div><?php } ?>
                            </div>
                        <?php } ?>

                        <?php if ($plan['dhcp'] || $plan['unaddressed'] || $plan['outside']) { ?>
                            <div class="border-top px-3 py-2 small itflow-net-unplaced">
                                <?php foreach (['outside' => ['Outside the subnet', 'fa-exclamation-triangle text-warning'], 'dhcp' => ['On DHCP', 'fa-random text-info'], 'unaddressed' => ['No address', 'fa-minus text-body-secondary']] as $group => [$label, $icon]) {
                                    if (!$plan[$group]) {
                                        continue;
                                    } ?>
                                    <div class="d-flex flex-wrap align-items-center column-gap-3 row-gap-1 py-1">
                                        <span class="fw-semibold text-nowrap"><i class="fas fa-fw <?= $icon ?> me-1"></i><?= $label ?> (<?= count($plan[$group]) ?>)</span>
                                        <?php
                                        // One entry per device - a switch's 24 ports are one line, not 24
                                        $by_asset = [];
                                        foreach ($plan[$group] as $interface) {
                                            $by_asset[intval($interface['asset_id'])][] = $interface;
                                        }
                                        foreach ($by_asset as $asset_interfaces) {
                                            $interface = $asset_interfaces[0];
                                            $names = array_column($asset_interfaces, 'interface_name');
                                            natcasesort($names); ?>
                                            <span class="d-inline-flex align-items-center gap-1" title="<?= escapeHtml(implode(', ', $names)) ?>">
                                                <?= interfaceAssetRef($interface['asset_id'], $interface['asset_name'], $interface['asset_type']) ?>
                                                <span class="text-body-secondary text-nowrap"><?= count($names) > 2 ? count($names) . ' ports' : escapeHtml(implode(', ', $names)) ?><?= $group === 'outside' ? ' · <span class="font-monospace">' . escapeHtml(implode(', ', array_unique(array_column($asset_interfaces, 'interface_ip')))) . '</span>' : '' ?></span>
                                            </span>
                                        <?php } ?>
                                    </div>
                                <?php } ?>
                            </div>
                        <?php } ?>
                        <?php if ($info && ($info['family'] === 6 || $info['prefix'] < 20)) { ?>
                            <div class="border-top px-3 py-2 small text-body-secondary"><i class="fas fa-fw fa-info-circle me-1"></i>Free addresses are shown for IPv4 networks up to a /20 - this one lists what is in use.</div>
                        <?php } ?>
                    </div>

                    <!-- The interfaces of devices set to this network -->
                    <div class="tab-pane fade" id="network-pane-devices" role="tabpanel">
                        <div class="itflow-asset-pane-bar">
                            <span class="text-body-secondary small">The ports and network cards of devices set to this network - set from the device's Interfaces tab.</span>
                        </div>
                        <?php if ($interfaces) { ?>
                            <div class="table-responsive">
                                <table class="table table-sm table-hover align-middle mb-0">
                                    <thead class="table-light text-nowrap">
                                    <tr>
                                        <th>Device</th>
                                        <th>Interface</th>
                                        <th>Address</th>
                                        <th class="d-none d-md-table-cell">MAC</th>
                                        <th class="d-none d-lg-table-cell">Connected to</th>
                                        <th></th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ($interfaces as $interface) {
                                        $link = $links[intval($interface['interface_id'])] ?? null;
                                        $ip = normalizeIpAddress((string) $interface['interface_ip']);
                                        $outside = $ip !== false && $info && !isIpInSubnet($ip, $info['cidr']); ?>
                                        <tr>
                                            <td class="min-w-0"><?= interfaceAssetRef($interface['asset_id'], $interface['asset_name'], $interface['asset_type']) ?></td>
                                            <td>
                                                <span class="fw-semibold"><?= escapeHtml($interface['interface_name']) ?></span>
                                                <?php if (intval($interface['interface_primary'])) { ?><span class="badge rounded-pill bg-body-secondary text-body-secondary fw-normal ms-1">Primary</span><?php } ?>
                                                <?php if ($interface['interface_description'] || $interface['interface_vlans']) { ?>
                                                    <div class="small text-body-secondary"><?= escapeHtml(implode(' · ', array_filter([$interface['interface_description'], $interface['interface_vlans'] ? 'VLAN ' . $interface['interface_vlans'] : '']))) ?></div>
                                                <?php } ?>
                                            </td>
                                            <td class="text-nowrap">
                                                <?= interfaceIpHtml($interface['interface_ip']) ?>
                                                <?php if ($outside) { ?><div class="small text-warning-emphasis"><i class="fas fa-exclamation-triangle me-1"></i>Outside <?= escapeHtml($info['cidr']) ?></div><?php } ?>
                                            </td>
                                            <td class="d-none d-md-table-cell font-monospace small"><?= escapeHtml($interface['interface_mac']) ?></td>
                                            <td class="d-none d-lg-table-cell small">
                                                <?php if ($link) { ?>
                                                    <?= interfaceAssetRef($link['asset_id'], $link['asset_name'], $link['asset_type'], !empty($link['asset_archived_at'])) ?>
                                                    <span class="text-body-secondary"><?= escapeHtml($link['interface_name']) ?></span>
                                                <?php } else { ?>
                                                    <span class="text-body-secondary">-</span>
                                                <?php } ?>
                                            </td>
                                            <td class="text-end">
                                                <?php if ($can_edit) { ?>
                                                    <button type="button" class="btn btn-sm btn-light ajax-modal" title="Edit interface" data-modal-size="lg" data-modal-url="modals/asset/asset_interface_edit.php?id=<?= intval($interface['interface_id']) ?>"><i class="fas fa-fw fa-edit"></i></button>
                                                <?php } ?>
                                            </td>
                                        </tr>
                                    <?php } ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php } else { ?>
                            <div class="itflow-asset-empty">No device is set to this network - pick it on a device's interface.</div>
                        <?php } ?>
                    </div>

                </div>
            </div>
        </div>

    </div>

    <script src="../js/bulk_actions.js"></script>
    <script>
        // Notes save when you click away
        document.querySelectorAll('.itflow-service-notes[data-network-id]').forEach(function (notes) {
            var saved = notes.value;
            notes.addEventListener('blur', function () {
                if (notes.readOnly || notes.value === saved) {
                    return;
                }
                saved = notes.value;
                itflowPost('post.php', {
                    network_set_notes: 'TRUE',
                    ajax: 1,
                    csrf_token: notes.dataset.csrf,
                    network_id: notes.dataset.networkId,
                    notes: notes.value
                });
            });
        });

        // The address plan's filter and free-address switch - the free runs and the DHCP band step aside while searching
        (function () {
            var filter = document.getElementById('networkPlanFilter');
            var freeSwitch = document.getElementById('networkPlanFree');
            var none = document.getElementById('networkPlanNone');
            var rows = document.querySelectorAll('.itflow-net-plan tbody tr');
            function apply() {
                var term = filter ? filter.value.trim().toLowerCase() : '';
                var showFree = !freeSwitch || freeSwitch.checked;
                var shown = 0;
                rows.forEach(function (row) {
                    var text = row.getAttribute('data-plan-search');
                    var visible = text === null ? (!term && (showFree || !row.hasAttribute('data-plan-free'))) : (!term || text.indexOf(term) !== -1);
                    row.hidden = !visible;
                    shown += visible && text !== null ? 1 : 0;
                });
                if (none) {
                    none.classList.toggle('d-none', !term || shown > 0);
                }
            }
            if (filter) {
                filter.addEventListener('input', apply);
            }
            if (freeSwitch) {
                freeSwitch.addEventListener('change', apply);
            }
            // Opened from search on an address - just that one showing
            if (filter && filter.value.trim() !== '') {
                apply();
            }
        })();

        // Which tab is open: the URL hash wins (#devices), then the address plan
        document.addEventListener('DOMContentLoaded', function () {
            var tabs = document.querySelectorAll('[data-network-tab]');
            if (!tabs.length) {
                return;
            }
            var start = document.querySelector('[data-network-tab="' + location.hash.replace('#', '') + '"]') || tabs[0];
            bootstrap.Tab.getOrCreateInstance(start).show();
            tabs.forEach(function (tab) {
                tab.addEventListener('shown.bs.tab', function () {
                    history.replaceState(null, '', location.pathname + location.search + '#' + tab.getAttribute('data-network-tab'));
                });
            });
        });
    </script>

<?php
}

require_once "../includes/footer.php";
