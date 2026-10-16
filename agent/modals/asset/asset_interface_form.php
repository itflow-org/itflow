<?php

/*
 * The fields of New Interface and Edit Interface - included by
 * asset_interface_add.php and asset_interface_edit.php, which set $asset (the
 * asset's row), $form (the interface's values, raw; empty for a new one) and
 * $is_new. Posts what interfaceFormSet() (functions/asset_interface.php) reads.
 *
 * Tabs: Details (name, type, description, VLANs), Address (network, a fixed
 * address / DHCP / none, MAC, IPv6, NAT) and Connection (what it is plugged
 * into - any port on the client's other devices; one that is plugged in
 * elsewhere moves). The address is checked as it is typed against the network
 * picked, and Next free fills in the first free one (agent/js/interface_form.js).
 */

isset($mysqli) || die("Direct file access is not allowed");

$form_client_id = intval($asset['asset_client_id']);
$form_asset_id = intval($asset['asset_id']);
$form_interface_id = intval($form['interface_id'] ?? 0);
$form_network_id = intval($form['interface_network_id'] ?? 0);
$form_ip = trim((string) ($form['interface_ip'] ?? ''));
$form_ip_mode = strtoupper($form_ip) === 'DHCP' ? 'dhcp' : ($form_ip === '' && !$is_new ? 'none' : 'static');
$form_link_id = intval($form['link']['interface_id'] ?? 0);
$form_id = 'interfaceForm' . ($form_interface_id ?: 'New');

$networks = interfaceNetworkOptions($form_client_id, $form_network_id);
$connections = interfaceConnectionOptions($form_client_id, $form_asset_id, $form_interface_id);
$types = interfaceTypes();
$form_type = (string) ($form['interface_type'] ?? '');

?>

<ul class="nav nav-pills nav-fill flex-nowrap overflow-auto mb-3 itflow-asset-form-tabs" role="tablist">
    <li class="nav-item">
        <a class="nav-link active" data-bs-toggle="pill" href="#<?= $form_id ?>Details"><i class="fas fa-fw fa-ethernet me-2 d-none d-lg-inline"></i>Details</a>
    </li>
    <li class="nav-item">
        <a class="nav-link" data-bs-toggle="pill" href="#<?= $form_id ?>Address"><i class="fas fa-fw fa-network-wired me-2 d-none d-lg-inline"></i>Address</a>
    </li>
    <li class="nav-item">
        <a class="nav-link" data-bs-toggle="pill" href="#<?= $form_id ?>Connection"><i class="fas fa-fw fa-plug me-2 d-none d-lg-inline"></i>Connected to</a>
    </li>
    <li class="nav-item">
        <a class="nav-link" data-bs-toggle="pill" href="#<?= $form_id ?>Notes"><i class="fas fa-fw fa-pen me-2 d-none d-lg-inline"></i>Notes</a>
    </li>
</ul>

<div class="tab-content itflow-interface-form" id="<?= $form_id ?>" data-asset-id="<?= $form_asset_id ?>" data-interface-id="<?= $form_interface_id ?>">

<!-- What it is -->
<div class="tab-pane fade show active" id="<?= $form_id ?>Details">
    <div class="row g-3">
        <div class="col-md-7">
            <label class="form-label">Name or port <strong class="text-danger">*</strong></label>
            <div class="input-group">
                <span class="input-group-text"><i class="fa fa-fw fa-ethernet"></i></span>
                <input type="text" class="form-control" name="name" placeholder="eth0, Gi1/0/1, WAN, LAN 3" maxlength="200" value="<?= escapeHtml($form['interface_name'] ?? '') ?>" required <?= $is_new ? 'autofocus' : '' ?>>
                <label class="input-group-text gap-2 mb-0" title="The address it is reached at - shown on lists and the asset">
                    <input class="form-check-input mt-0" type="checkbox" name="primary_interface" value="1" <?= intval($form['interface_primary'] ?? 0) ? 'checked' : '' ?>>
                    <span class="small">Primary</span>
                </label>
            </div>
        </div>
        <div class="col-md-5">
            <label class="form-label">Type</label>
            <div class="input-group">
                <span class="input-group-text"><i class="fa fa-fw fa-plug"></i></span>
                <select class="form-select select2" name="type">
                    <option value="">- No type -</option>
                    <?php foreach ($types as $type => [$color, $icon]) { ?>
                        <option <?= $form_type === $type ? 'selected' : '' ?><?= selectOptionColor($color ?? '', $icon ?? '') ?>><?= escapeHtml($type) ?></option>
                    <?php }
                    if ($form_type !== '' && !isset($types[$form_type])) { ?>
                        <option selected><?= escapeHtml($form_type) ?></option>
                    <?php } ?>
                </select>
            </div>
        </div>
        <div class="col-md-7">
            <label class="form-label">Description</label>
            <div class="input-group">
                <span class="input-group-text"><i class="fa fa-fw fa-tag"></i></span>
                <input type="text" class="form-control" name="description" placeholder="Uplink to firewall, Reception PC" maxlength="200" value="<?= escapeHtml($form['interface_description'] ?? '') ?>">
            </div>
        </div>
        <div class="col-md-5">
            <label class="form-label">VLANs</label>
            <div class="input-group">
                <span class="input-group-text"><i class="fa fa-fw fa-layer-group"></i></span>
                <input type="text" class="form-control" name="vlans" placeholder="20, or Trunk 1, 10-20" maxlength="200" value="<?= escapeHtml($form['interface_vlans'] ?? '') ?>">
            </div>
        </div>
    </div>
</div>

<!-- The network it is on and its address there -->
<div class="tab-pane fade" id="<?= $form_id ?>Address">
    <div class="mb-3">
        <label class="form-label">Network</label>
        <div class="input-group">
            <span class="input-group-text"><i class="fa fa-fw fa-network-wired"></i></span>
            <select class="form-select select2" name="network_id" data-interface-network>
                <option value="0">- No network -</option>
                <?php foreach ($networks as $network) { ?>
                    <option value="<?= intval($network['network_id']) ?>" <?= $form_network_id === intval($network['network_id']) ? 'selected' : '' ?><?= selectOptionNetwork($network) ?>><?= escapeHtml($network['network_name']) ?><?= $network['network_archived_at'] ? ' (archived)' : '' ?></option>
                <?php } ?>
            </select>
        </div>
        <?php if (!$networks) { ?>
            <div class="form-text"><?= escapeHtml($asset['client_name'] ?? 'The client') ?> has no networks yet - add them under Networks to pick one here.</div>
        <?php } ?>
    </div>

    <div class="mb-3">
        <label class="form-label d-block">Address</label>
        <div class="btn-group w-100 mb-2" role="group">
            <?php foreach (['static' => ['fa-thumbtack', 'Fixed address'], 'dhcp' => ['fa-random', 'DHCP'], 'none' => ['fa-minus', 'None']] as $mode => [$icon, $label]) { ?>
                <input type="radio" class="btn-check" name="ip_mode" id="<?= $form_id ?>Mode<?= $mode ?>" value="<?= $mode ?>" <?= $form_ip_mode === $mode ? 'checked' : '' ?> autocomplete="off" data-interface-mode>
                <label class="btn btn-outline-secondary" for="<?= $form_id ?>Mode<?= $mode ?>"><i class="fas fa-fw <?= $icon ?> me-1"></i><?= $label ?></label>
            <?php } ?>
        </div>
        <div data-interface-static <?= $form_ip_mode === 'static' ? '' : 'hidden' ?>>
            <div class="input-group">
                <span class="input-group-text"><i class="fa fa-fw fa-map-pin"></i></span>
                <input type="text" class="form-control font-monospace" name="ip" placeholder="192.168.1.10" maxlength="200" value="<?= $form_ip_mode === 'static' ? escapeHtml($form_ip) : '' ?>" data-interface-ip>
                <button type="button" class="btn btn-outline-secondary" data-interface-next title="The first address on the network nothing has, outside the DHCP range" <?= $form_network_id ? '' : 'disabled' ?>><i class="fas fa-magic me-1"></i>Next free</button>
            </div>
            <div class="small mt-1" data-interface-feedback></div>
        </div>
        <div class="small text-body-secondary" data-interface-dhcp <?= $form_ip_mode === 'dhcp' ? '' : 'hidden' ?>>Its address comes from DHCP - add a reservation on the network to pin it.</div>
    </div>

    <div class="row g-3">
        <div class="col-md-6">
            <label class="form-label">MAC address</label>
            <div class="input-group">
                <span class="input-group-text"><i class="fa fa-fw fa-fingerprint"></i></span>
                <input type="text" class="form-control font-monospace" name="mac" placeholder="00:1A:2B:3C:4D:5E" maxlength="200" value="<?= escapeHtml($form['interface_mac'] ?? '') ?>" data-interface-mac>
            </div>
        </div>
        <div class="col-md-6">
            <label class="form-label">NAT address</label>
            <div class="input-group">
                <span class="input-group-text"><i class="fa fa-fw fa-exchange-alt"></i></span>
                <input type="text" class="form-control font-monospace" name="nat_ip" placeholder="203.0.113.10" maxlength="200" value="<?= escapeHtml($form['interface_nat_ip'] ?? '') ?>">
            </div>
        </div>
        <div class="col-12">
            <label class="form-label">IPv6 address</label>
            <div class="input-group">
                <span class="input-group-text"><i class="fa fa-fw fa-globe"></i></span>
                <input type="text" class="form-control font-monospace" name="ipv6" placeholder="2001:db8::10" maxlength="200" value="<?= escapeHtml($form['interface_ipv6'] ?? '') ?>">
            </div>
        </div>
    </div>
</div>

<!-- What it is plugged into -->
<div class="tab-pane fade" id="<?= $form_id ?>Connection">
    <label class="form-label">Connected to</label>
    <div class="input-group">
        <span class="input-group-text"><i class="fa fa-fw fa-plug"></i></span>
        <select class="form-select select2" name="connected_to">
            <option value="0">- Not connected -</option>
            <?php foreach ($connections as $group) { ?>
                <optgroup label="<?= escapeHtml($group['asset']['asset_name']) ?>">
                    <?php foreach ($group['interfaces'] as $option) {
                        $detail = implode(' · ', array_filter([$option['interface_description'], $option['link_name'] ? 'now to ' . $option['link_name'] : ''])); ?>
                        <option value="<?= intval($option['interface_id']) ?>" <?= $form_link_id === intval($option['interface_id']) ? 'selected' : '' ?>
                            data-icon="fas fa-<?= getAssetIcon($group['asset']['asset_type']) ?>" data-keywords="<?= escapeHtml($group['asset']['asset_name']) ?>"<?= $detail !== '' ? ' data-description="' . escapeHtml($detail) . '"' : '' ?>><?= escapeHtml($group['asset']['asset_name'] . ' - ' . $option['interface_name']) ?></option>
                    <?php } ?>
                </optgroup>
            <?php } ?>
        </select>
    </div>
    <div class="form-text">The port at the other end of the cable - a switch port, the firewall's LAN. One cable per port: picking one that is connected elsewhere moves it here.</div>
    <?php if (!$connections) { ?>
        <div class="form-text">No other device at this client has interfaces yet.</div>
    <?php } ?>
</div>

<div class="tab-pane fade" id="<?= $form_id ?>Notes">
    <textarea class="form-control" name="notes" rows="8" placeholder="Patch panel port, cable color, what is on the other end"><?= escapeHtml($form['interface_notes'] ?? '') ?></textarea>
</div>

</div>
