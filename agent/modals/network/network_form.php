<?php

/*
 * The fields of New Network and Edit Network - included by network_add.php
 * and network_edit.php, which set $form (the values, raw), $form_client_id
 * (0 when the client is picked here) and $is_new. Posts what networkFormSet()
 * (functions/network.php) reads.
 *
 * Tabs: Details (name, type, site, the provider of an internet connection,
 * VLAN, tags), Addressing (subnet, gateway, DHCP range, DNS) and Notes. The subnet
 * is read as it is typed - its size and mask, a gateway and DHCP range to
 * suggest, and the client's networks it overlaps (agent/js/network_form.js).
 */

isset($mysqli) || die("Direct file access is not allowed");

$form_type = isset(NETWORK_TYPES[$form['network_type'] ?? '']) ? $form['network_type'] : 'LAN';
$form_location_id = intval($form['network_location_id'] ?? 0);
$form_vendor_id = intval($form['network_vendor_id'] ?? 0);
$form_id = 'networkForm' . ($is_new ? 'New' : intval($form['network_id']));

$client_options = $location_options = $vendor_options = [];
if ($form_client_id) {
    $sql = mysqli_query($mysqli, "SELECT location_id, location_name, location_primary, location_archived_at FROM locations
        WHERE location_client_id = $form_client_id AND (location_archived_at IS NULL OR location_id = $form_location_id)
        ORDER BY location_primary DESC, location_name ASC");
    while ($row = mysqli_fetch_assoc($sql)) {
        $location_options[] = $row;
    }
    // The client's vendors, then ours - an ISP is usually the client's
    $sql = mysqli_query($mysqli, "SELECT vendor_id, vendor_name, vendor_description, vendor_client_id FROM vendors
        WHERE vendor_client_id IN (0, $form_client_id) AND (vendor_archived_at IS NULL OR vendor_id = $form_vendor_id)
        ORDER BY vendor_client_id = 0, vendor_name ASC");
    while ($row = mysqli_fetch_assoc($sql)) {
        $vendor_options[intval($row['vendor_client_id']) ? 'client' : 'ours'][] = $row;
    }
} else {
    $sql = mysqli_query($mysqli, "SELECT client_id, client_name, client_abbreviation FROM clients WHERE client_archived_at IS NULL " . clientScopeSql('clients.client_id') . " ORDER BY client_name ASC");
    while ($row = mysqli_fetch_assoc($sql)) {
        $client_options[] = $row;
    }
}

$value = function ($key) use ($form) {
    return escapeHtml($form[$key] ?? '');
};

?>

<ul class="nav nav-pills nav-fill flex-nowrap overflow-auto mb-3 itflow-asset-form-tabs" role="tablist">
    <li class="nav-item">
        <a class="nav-link active" data-bs-toggle="pill" href="#<?= $form_id ?>Details"><i class="fas fa-fw fa-network-wired me-2 d-none d-lg-inline"></i>Details</a>
    </li>
    <li class="nav-item">
        <a class="nav-link" data-bs-toggle="pill" href="#<?= $form_id ?>Addressing"><i class="fas fa-fw fa-sitemap me-2 d-none d-lg-inline"></i>Addressing</a>
    </li>
    <li class="nav-item">
        <a class="nav-link" data-bs-toggle="pill" href="#<?= $form_id ?>Notes"><i class="fas fa-fw fa-pen me-2 d-none d-lg-inline"></i>Notes</a>
    </li>
</ul>

<div class="tab-content itflow-network-form" id="<?= $form_id ?>" data-client-id="<?= $form_client_id ?>" data-network-id="<?= intval($form['network_id'] ?? 0) ?>">

<!-- What it is, where -->
<div class="tab-pane fade show active" id="<?= $form_id ?>Details">
    <div class="row g-3">
        <?php if (!$form_client_id) { ?>
            <div class="col-12">
                <label class="form-label">Client <strong class="text-danger">*</strong></label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-user"></i></span>
                    <select class="form-select select2" name="client_id" required>
                        <option value="">- Select Client -</option>
                        <?php foreach ($client_options as $client_option) { ?>
                            <option value="<?= intval($client_option['client_id']) ?>"<?= selectOptionClient($client_option['client_id'], $client_option['client_name'], $client_option['client_abbreviation'] ?? '') ?>><?= escapeHtml($client_option['client_name']) ?></option>
                        <?php } ?>
                    </select>
                </div>
                <div class="form-text">Pick its site and provider once it's saved.</div>
            </div>
        <?php } elseif ($is_new) { ?>
            <input type="hidden" name="client_id" value="<?= $form_client_id ?>">
        <?php } ?>

        <div class="col-md-7">
            <label class="form-label">Name <strong class="text-danger">*</strong></label>
            <div class="input-group">
                <span class="input-group-text"><i class="fas fa-fw fa-tag"></i></span>
                <input type="text" class="form-control" name="name" placeholder="Office LAN, Guest Wireless, Comcast Fiber" maxlength="200" value="<?= $value('network_name') ?>" required <?= $is_new ? 'autofocus' : '' ?> data-network-name>
            </div>
        </div>
        <div class="col-md-5">
            <label class="form-label">Type</label>
            <div class="input-group">
                <span class="input-group-text"><i class="fas fa-fw fa-shapes"></i></span>
                <select class="form-select select2" name="type" data-network-type data-type-picked="<?= $is_new && empty($form['network_type']) ? 0 : 1 ?>"
                    data-type-rules="<?= escapeHtml(json_encode(array_map(function ($patterns) { return $patterns[0]; }, NETWORK_TYPE_RULES))) ?>">
                    <?php foreach (NETWORK_TYPES as $type => [$icon, $tone, $hint]) { ?>
                        <option value="<?= $type ?>" <?= $form_type === $type ? 'selected' : '' ?> data-icon="fas <?= $icon ?>" data-color="<?= networkToneColor($tone) ?>" data-description="<?= escapeHtml($hint) ?>"><?= $type ?></option>
                    <?php } ?>
                </select>
            </div>
        </div>

        <div class="col-12">
            <label class="form-label">Description</label>
            <div class="input-group">
                <span class="input-group-text"><i class="fas fa-fw fa-align-left"></i></span>
                <input type="text" class="form-control" name="description" placeholder="Workstations and printers, internet only, 500/500 fiber" value="<?= $value('network_description') ?>">
            </div>
        </div>

        <?php if ($form_client_id) { ?>
            <div class="col-md-8">
                <label class="form-label">Site</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-fw fa-map-marker-alt"></i></span>
                    <select class="form-select select2" name="location_id">
                        <option value="0">- No site -</option>
                        <?php foreach ($location_options as $location) { ?>
                            <option value="<?= intval($location['location_id']) ?>" <?= $form_location_id === intval($location['location_id']) ? 'selected' : '' ?>
                                data-icon="fas fa-<?= intval($location['location_primary']) ? 'building' : 'map-marker-alt' ?>"<?= $location['location_archived_at'] ? ' data-description="Archived"' : (intval($location['location_primary']) ? ' data-description="Primary"' : '') ?>><?= escapeHtml($location['location_name']) ?></option>
                        <?php } ?>
                    </select>
                </div>
            </div>
        <?php } ?>
        <div class="<?= $form_client_id ? 'col-md-4' : 'col-md-5' ?>">
            <label class="form-label">VLAN</label>
            <div class="input-group">
                <span class="input-group-text"><i class="fas fa-fw fa-layer-group"></i></span>
                <input type="number" class="form-control" name="vlan" min="1" max="4094" placeholder="e.g. 10" value="<?= $value('network_vlan') ?>">
            </div>
        </div>

        <?php if ($form_client_id) { ?>
            <div class="col-12" data-network-provider <?= $form_type === 'WAN' || $form_vendor_id ? '' : 'hidden' ?>>
                <label class="form-label">Provider <span class="small text-body-secondary fw-normal">- who to call when it is down</span></label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-fw fa-building"></i></span>
                    <select class="form-select select2" name="vendor_id">
                        <option value="0">- No provider -</option>
                        <?php foreach (['client' => escapeHtml($form['client_name'] ?? 'The client') . "'s vendors", 'ours' => 'Your vendors'] as $group => $label) {
                            if (empty($vendor_options[$group])) {
                                continue;
                            } ?>
                            <optgroup label="<?= $label ?>">
                                <?php foreach ($vendor_options[$group] as $vendor) { ?>
                                    <option value="<?= intval($vendor['vendor_id']) ?>" <?= $form_vendor_id === intval($vendor['vendor_id']) ? 'selected' : '' ?><?= selectOptionVendor($vendor['vendor_name'], $vendor['vendor_description']) ?>><?= escapeHtml($vendor['vendor_name']) ?></option>
                                <?php } ?>
                            </optgroup>
                        <?php } ?>
                    </select>
                </div>
                <div class="form-text">The ISP - its phone, account number and hours show on the network.</div>
            </div>
        <?php } ?>
        <div class="col-12">
            <?= tagFormFieldHtml('network', $is_new ? [] : tagIdsForRecord('network', $form['network_id']), 'Managed, Segmented, PCI') ?>
        </div>
    </div>
</div>

<!-- The subnet and what hands out its addresses -->
<div class="tab-pane fade" id="<?= $form_id ?>Addressing">
    <div class="row g-3">
        <div class="col-12">
            <label class="form-label">Subnet <strong class="text-danger">*</strong></label>
            <div class="input-group">
                <span class="input-group-text"><i class="fas fa-fw fa-network-wired"></i></span>
                <input type="text" class="form-control font-monospace" name="network" placeholder="192.168.1.0/24" maxlength="200" value="<?= $value('network') ?>" required data-network-cidr>
            </div>
            <div class="small mt-1 itflow-net-cidr-info" data-network-cidr-info>
                <span class="text-body-secondary">An address and its prefix - 192.168.1.0/24, 10.20.0.0/255.255.252.0 or 2001:db8::/64.</span>
            </div>
        </div>
        <div class="col-md-6">
            <label class="form-label">Gateway</label>
            <div class="input-group">
                <span class="input-group-text"><i class="fas fa-fw fa-door-open"></i></span>
                <input type="text" class="form-control font-monospace" name="gateway" placeholder="192.168.1.1" maxlength="200" value="<?= $value('network_gateway') ?>" data-network-gateway>
            </div>
            <div class="form-text" data-network-suggest="gateway" hidden></div>
        </div>
        <div class="col-md-6">
            <label class="form-label">DHCP range</label>
            <div class="input-group">
                <span class="input-group-text"><i class="fas fa-fw fa-random"></i></span>
                <input type="text" class="form-control font-monospace" name="dhcp_range" placeholder="192.168.1.100 - 192.168.1.199" maxlength="200" value="<?= $value('network_dhcp_range') ?>" data-network-range>
            </div>
            <div class="form-text" data-network-suggest="range" hidden></div>
        </div>
        <div class="col-md-6">
            <label class="form-label">Primary DNS</label>
            <div class="input-group">
                <span class="input-group-text"><i class="fas fa-fw fa-search-location"></i></span>
                <input type="text" class="form-control font-monospace" name="primary_dns" placeholder="192.168.1.10" maxlength="200" value="<?= $value('network_primary_dns') ?>">
            </div>
        </div>
        <div class="col-md-6">
            <label class="form-label">Secondary DNS</label>
            <div class="input-group">
                <span class="input-group-text"><i class="fas fa-fw fa-search-location"></i></span>
                <input type="text" class="form-control font-monospace" name="secondary_dns" placeholder="1.1.1.1" maxlength="200" value="<?= $value('network_secondary_dns') ?>">
            </div>
        </div>
    </div>
</div>

<div class="tab-pane fade" id="<?= $form_id ?>Notes">
    <textarea class="form-control" name="notes" rows="10" placeholder="Firewall rules, what it can reach, the switch ports it is on, the DHCP server"><?= $value('network_notes') ?></textarea>
</div>

</div>
